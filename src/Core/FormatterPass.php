<?php
abstract class FormatterPass {
	protected $indentChar = "\t";
	protected $newLine = "\n";
	protected $indent = 0;
	protected $code = '';
	protected $ptr = 0;
	protected $tkns = [];
	protected $useCache = false;
	protected $cache = [];
	protected $ignoreFutileTokens = [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT];

	protected function alignPlaceholders($origPlaceholder, $contextCounter) {
		for ($j = 0; $j <= $contextCounter; ++$j) {
			$placeholder = sprintf($origPlaceholder, $j);
			if (false === strpos($this->code, $placeholder)) {
				continue;
			}
			if (1 === substr_count($this->code, $placeholder)) {
				$this->code = str_replace($placeholder, '', $this->code);
				continue;
			}
			$lines = explode($this->newLine, $this->code);
			$linesWithPlaceholder = [];
			$blockCount = 0;

			foreach ($lines as $idx => $line) {
				if (false !== strpos($line, $placeholder)) {
					$linesWithPlaceholder[$blockCount][] = $idx;
				} else {
					++$blockCount;
					$linesWithPlaceholder[$blockCount] = [];
				}
			}

			$i = 0;
			foreach ($linesWithPlaceholder as $group) {
				++$i;
				$farthest = 0;
				foreach ($group as $idx) {
					$farthest = max($farthest, strpos($lines[$idx], $placeholder));
				}
				foreach ($group as $idx) {
					$line = $lines[$idx];
					$current = strpos($line, $placeholder);
					$delta = abs($farthest - $current);
					if ($delta > 0) {
						$line = str_replace($placeholder, str_repeat(' ', $delta) . $placeholder, $line);
						$lines[$idx] = $line;
					}
				}
			}
			$this->code = str_replace($placeholder, '', implode($this->newLine, $lines));
		}
	}

	protected function appendCode($code = '') {
		$this->code .= $code;
	}

	private function calculateCacheKey($direction, $ignoreList, $token) {
		return $direction . "\x2" . implode('', $ignoreList) . "\x2" . (is_array($token) ? implode("\x2", $token) : $token);
	}

	abstract public function candidate($source, $foundTokens);
	abstract public function format($source);

	protected function getToken($token) {
		$ret = [$token, $token];
		if (isset($token[1])) {
			$ret = $token;
		}
		return $ret;
	}

	protected function getCrlf() {
		return $this->newLine;
	}

	protected function getCrlfIndent() {
		return $this->getCrlf() . $this->getIndent();
	}

	protected function getIndent($increment = 0) {
		return str_repeat($this->indentChar, $this->indent + $increment);
	}

	protected function getSpace($true = true) {
		return $true ? ' ' : '';
	}

	protected function hasLn($text) {
		return (false !== strpos($text, $this->newLine));
	}

	protected function hasLnAfter() {
		$id = null;
		$text = null;
		list($id, $text) = $this->inspectToken();
		return T_WHITESPACE === $id && $this->hasLn($text);
	}

	protected function hasLnBefore() {
		$id = null;
		$text = null;
		list($id, $text) = $this->inspectToken(-1);
		return T_WHITESPACE === $id && $this->hasLn($text);
	}

	protected function hasLnLeftToken() {
		list(, $text) = $this->getToken($this->leftToken());
		return $this->hasLn($text);
	}

	protected function hasLnRightToken() {
		list(, $text) = $this->getToken($this->rightToken());
		return $this->hasLn($text);
	}

	protected function inspectToken($delta = 1) {
		if (!isset($this->tkns[$this->ptr + $delta])) {
			return [null, null];
		}
		return $this->getToken($this->tkns[$this->ptr + $delta]);
	}

	protected function isShortArray() {
		return !$this->leftTokenIs([
			ST_BRACKET_CLOSE,
			ST_CURLY_CLOSE,
			ST_PARENTHESES_CLOSE,
			ST_QUOTE,
			T_CONSTANT_ENCAPSED_STRING,
			T_STRING,
			T_VARIABLE,
		]);
	}
	protected function leftToken($ignoreList = []) {
		$i = $this->leftTokenIdx($ignoreList);

		return $this->tkns[$i];
	}

	protected function leftTokenIdx($ignoreList = []) {
		$ignoreList = $this->resolveIgnoreList($ignoreList);

		$i = $this->walkLeft($this->tkns, $this->ptr, $ignoreList);

		return $i;
	}

	protected function leftTokenIs($token, $ignoreList = []) {
		return $this->tokenIs('left', $token, $ignoreList);
	}

	protected function leftTokenSubsetIsAtIdx($tkns, $idx, $token, $ignoreList = []) {
		$ignoreList = $this->resolveIgnoreList($ignoreList);

		$idx = $this->walkLeft($tkns, $idx, $ignoreList);

		return $this->resolveTokenMatch($tkns, $idx, $token);
	}

	protected function leftUsefulToken() {
		return $this->leftToken($this->ignoreFutileTokens);
	}

	protected function leftUsefulTokenIdx() {
		return $this->leftTokenIdx($this->ignoreFutileTokens);
	}

	protected function leftUsefulTokenIs($token) {
		return $this->leftTokenIs($token, $this->ignoreFutileTokens);
	}

	protected function peekAndCountUntilAny($tkns, $ptr, $tknids) {
		$tknids = array_flip($tknids);
		$tknsSize = sizeof($tkns);
		$countTokens = [];
		$id = null;
		for ($i = $ptr; $i < $tknsSize; ++$i) {
			$token = $tkns[$i];
			list($id) = $this->getToken($token);
			if (T_WHITESPACE == $id || T_COMMENT == $id || T_DOC_COMMENT == $id) {
				continue;
			}
			if (!isset($countTokens[$id])) {
				$countTokens[$id] = 0;
			}
			++$countTokens[$id];
			if (isset($tknids[$id])) {
				break;
			}
		}
		return [$id, $countTokens];
	}

	protected function printAndStopAt($tknids) {
		if (is_scalar($tknids)) {
			$tknids = [$tknids];
		}
		$tknids = array_flip($tknids);
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->getToken($token);
			$this->ptr = $index;
			$this->cache = [];
			if (isset($tknids[$id])) {
				return [$id, $text];
			}
			$this->appendCode($text);
		}
	}

	protected function printAndStopAtEndOfParamBlock() {
		$count = 1;
		$paramCount = 1;
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->getToken($token);
			$this->ptr = $index;
			$this->cache = [];

			if (ST_COMMA == $id && 1 == $count) {
				++$paramCount;
			}
			if (ST_BRACKET_OPEN == $id) {
				$this->appendCode($text);
				$this->printBlock(ST_BRACKET_OPEN, ST_BRACKET_CLOSE);
				continue;
			}
			if (ST_CURLY_OPEN == $id || T_CURLY_OPEN == $id || T_DOLLAR_OPEN_CURLY_BRACES == $id) {
				$this->appendCode($text);
				$this->printCurlyBlock();
				continue;
			}
			if (ST_PARENTHESES_OPEN == $id) {
				++$count;
			}
			if (ST_PARENTHESES_CLOSE == $id) {
				--$count;
			}
			if (0 == $count) {
				prev($this->tkns);
				break;
			}
			$this->appendCode($text);
		}
		return $paramCount;
	}

	protected function printBlock($start, $end) {
		$count = 1;
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->getToken($token);
			$this->ptr = $index;
			$this->cache = [];
			$this->appendCode($text);

			if ($start == $id) {
				++$count;
			}
			if ($end == $id) {
				--$count;
			}
			if (0 == $count) {
				break;
			}
		}
	}

	protected function printCurlyBlock() {
		$count = 1;
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->getToken($token);
			$this->ptr = $index;
			$this->cache = [];
			$this->appendCode($text);

			if (ST_CURLY_OPEN == $id) {
				++$count;
			}
			if (T_CURLY_OPEN == $id) {
				++$count;
			}
			if (T_DOLLAR_OPEN_CURLY_BRACES == $id) {
				++$count;
			}
			if (ST_CURLY_CLOSE == $id) {
				--$count;
			}
			if (0 == $count) {
				break;
			}
		}
	}

	protected function printUntil($tknid) {
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->getToken($token);
			$this->ptr = $index;
			$this->cache = [];
			$this->appendCode($text);
			if ($tknid == $id) {
				break;
			}
		}
	}

	protected function printUntilAny($tknids) {
		$tknids = array_flip($tknids);
		$whitespaceNewLine = false;
		$id = null;
		if (isset($tknids[$this->newLine])) {
			$whitespaceNewLine = true;
		}
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->getToken($token);
			$this->ptr = $index;
			$this->cache = [];
			$this->appendCode($text);
			if ($whitespaceNewLine && T_WHITESPACE == $id && $this->hasLn($text)) {
				break;
			}
			if (isset($tknids[$id])) {
				break;
			}
		}
		return $id;
	}

	protected function printUntilTheEndOfString() {
		$this->printUntil(ST_QUOTE);
	}

	protected function render($tkns = null) {
		if (null == $tkns) {
			$tkns = $this->tkns;
		}

		$tkns = array_filter($tkns);
		$str = '';
		foreach ($tkns as $token) {
			list(, $text) = $this->getToken($token);
			$str .= $text;
		}
		return $str;
	}

	protected function renderLight($tkns = null) {
		if (null == $tkns) {
			$tkns = $this->tkns;
		}
		$str = '';
		foreach ($tkns as $token) {
			$str .= $token[1];
		}
		return $str;
	}

	private function resolveIgnoreList($ignoreList = []) {
		if (empty($ignoreList)) {
			$ignoreList[T_WHITESPACE] = true;
		} else {
			$ignoreList = array_flip($ignoreList);
		}
		return $ignoreList;
	}

	private function resolveTokenMatch($tkns, $idx, $token) {
		if (!isset($tkns[$idx])) {
			return false;
		}

		$foundToken = $tkns[$idx];
		if ($foundToken === $token) {
			return true;
		} elseif (is_array($token) && isset($foundToken[1]) && in_array($foundToken[0], $token)) {
			return true;
		} elseif (is_array($token) && !isset($foundToken[1]) && in_array($foundToken, $token)) {
			return true;
		} elseif (isset($foundToken[1]) && $foundToken[0] == $token) {
			return true;
		}

		return false;
	}

	protected function rightToken($ignoreList = []) {
		$i = $this->rightTokenIdx($ignoreList);

		return $this->tkns[$i];
	}

	protected function rightTokenIdx($ignoreList = []) {
		$ignoreList = $this->resolveIgnoreList($ignoreList);

		$i = $this->walkRight($this->tkns, $this->ptr, $ignoreList);

		return $i;
	}

	protected function rightTokenIs($token, $ignoreList = []) {
		return $this->tokenIs('right', $token, $ignoreList);
	}

	protected function rightTokenSubsetIsAtIdx($tkns, $idx, $token, $ignoreList = []) {
		$ignoreList = $this->resolveIgnoreList($ignoreList);

		$idx = $this->walkRight($tkns, $idx, $ignoreList);

		return $this->resolveTokenMatch($tkns, $idx, $token);
	}

	protected function rightUsefulToken() {
		return $this->rightToken($this->ignoreFutileTokens);
	}

	protected function rightUsefulTokenIdx() {
		return $this->rightTokenIdx($this->ignoreFutileTokens);
	}

	protected function rightUsefulTokenIs($token) {
		return $this->rightTokenIs($token, $this->ignoreFutileTokens);
	}

	protected function rtrimAndAppendCode($code = '') {
		$this->code = rtrim($this->code) . $code;
	}

	protected function scanAndReplace(&$tkns, &$ptr, $start, $end, $call, $lookFor) {
		$lookFor = array_flip($lookFor);
		$placeholder = '<?php' . ' /*\x2 PHPOPEN \x3*/';
		$tmp = '';
		$tknCount = 1;
		$foundPotentialTokens = false;
		while (list($ptr, $token) = each($tkns)) {
			list($id, $text) = $this->getToken($token);
			if (isset($lookFor[$id])) {
				$foundPotentialTokens = true;
			}
			if ($start == $id) {
				++$tknCount;
			}
			if ($end == $id) {
				--$tknCount;
			}
			$tkns[$ptr] = null;
			if (0 == $tknCount) {
				break;
			}
			$tmp .= $text;
		}
		if ($foundPotentialTokens) {
			return $start . str_replace($placeholder, '', $this->{$call}($placeholder . $tmp)) . $end;
		}
		return $start . $tmp . $end;

	}

	protected function scanAndReplaceCurly(&$tkns, &$ptr, $start, $call, $lookFor) {
		$lookFor = array_flip($lookFor);
		$placeholder = '<?php' . ' /*\x2 PHPOPEN \x3*/';
		$tmp = '';
		$tknCount = 1;
		$foundPotentialTokens = false;
		while (list($ptr, $token) = each($tkns)) {
			list($id, $text) = $this->getToken($token);
			if (isset($lookFor[$id])) {
				$foundPotentialTokens = true;
			}
			if (ST_CURLY_OPEN == $id) {
				if (empty($start)) {
					$start = ST_CURLY_OPEN;
				}
				++$tknCount;
			}
			if (T_CURLY_OPEN == $id) {
				if (empty($start)) {
					$start = ST_CURLY_OPEN;
				}
				++$tknCount;
			}
			if (T_DOLLAR_OPEN_CURLY_BRACES == $id) {
				if (empty($start)) {
					$start = ST_DOLLAR . ST_CURLY_OPEN;
				}
				++$tknCount;
			}
			if (ST_CURLY_CLOSE == $id) {
				--$tknCount;
			}
			$tkns[$ptr] = null;
			if (0 == $tknCount) {
				break;
			}
			$tmp .= $text;
		}
		if ($foundPotentialTokens) {
			return $start . str_replace($placeholder, '', $this->{$call}($placeholder . $tmp)) . ST_CURLY_CLOSE;
		}
		return $start . $tmp . ST_CURLY_CLOSE;

	}

	protected function setIndent($increment) {
		$this->indent += $increment;
		if ($this->indent < 0) {
			$this->indent = 0;
		}
	}

	protected function siblings($tkns, $ptr) {
		$ignoreList = $this->resolveIgnoreList([T_WHITESPACE]);
		$left = $this->walkLeft($tkns, $ptr, $ignoreList);
		$right = $this->walkRight($tkns, $ptr, $ignoreList);
		return [$left, $right];
	}

	protected function substrCountTrailing($haystack, $needle) {
		return strlen(rtrim($haystack, " \t")) - strlen(rtrim($haystack, " \t" . $needle));
	}

	protected function tokenIs($direction, $token, $ignoreList = []) {
		if ('left' != $direction) {
			$direction = 'right';
		}
		if (!$this->useCache) {
			return $this->{$direction . 'tokenSubsetIsAtIdx'}($this->tkns, $this->ptr, $token, $ignoreList);
		}

		$key = $this->calculateCacheKey($direction, $ignoreList, $token);
		if (isset($this->cache[$key])) {
			return $this->cache[$key];
		}

		$ret = $this->{$direction . 'tokenSubsetIsAtIdx'}($this->tkns, $this->ptr, $token, $ignoreList);
		$this->cache[$key] = $ret;

		return $ret;
	}

	protected function walkAndAccumulateStopAt(&$tkns, $tknid) {
		$ret = '';
		while (list($index, $token) = each($tkns)) {
			list($id, $text) = $this->getToken($token);
			$this->ptr = $index;
			if ($tknid == $id) {
				prev($tkns);
				break;
			}
			$ret .= $text;
		}
		return $ret;
	}

	protected function walkAndAccumulateUntil(&$tkns, $tknid) {
		$ret = '';
		while (list($index, $token) = each($tkns)) {
			list($id, $text) = $this->getToken($token);
			$this->ptr = $index;
			$ret .= $text;
			if ($tknid == $id) {
				break;
			}
		}
		return $ret;
	}

	protected function walkAndAccumulateStopAtAny(&$tkns, $tknids) {
		$tknids = array_flip($tknids);
		$ret = '';
		$id = null;
		while (list($index, $token) = each($tkns)) {
			list($id, $text) = $this->getToken($token);
			$this->ptr = $index;
			if (isset($tknids[$id])) {
				prev($tkns);
				break;
			}
			$ret .= $text;
		}
		return [$ret, $id];
	}

	private function walkLeft($tkns, $idx, $ignoreList) {
		$i = $idx;
		while (--$i >= 0 && isset($tkns[$i][1]) && isset($ignoreList[$tkns[$i][0]]));
		return $i;
	}

	private function walkRight($tkns, $idx, $ignoreList) {
		$i = $idx;
		$tknsSize = sizeof($tkns) - 1;
		while (++$i < $tknsSize && isset($tkns[$i][1]) && isset($ignoreList[$tkns[$i][0]]));
		return $i;
	}

	protected function walkUntil($tknid) {
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->getToken($token);
			$this->ptr = $index;
			if ($id == $tknid) {
				return [$id, $text];
			}
		}
	}

	protected function refWalkUsefulUntil($tkns, &$ptr, $expectedId) {
		do {
			$ptr = $this->walkRight($tkns, $ptr, $this->ignoreFutileTokens);
		} while ($expectedId != $tkns[$ptr][0]);
	}

	protected function refWalkBlock($tkns, &$ptr, $start, $end) {
		$count = 0;
		for ($sizeOfTkns = sizeof($tkns); $ptr < $sizeOfTkns; ++$ptr) {
			$id = $tkns[$ptr][0];
			if ($start == $id) {
				++$count;
			}
			if ($end == $id) {
				--$count;
			}
			if (0 == $count) {
				break;
			}
		}
	}

	protected function refWalkCurlyBlock($tkns, &$ptr) {
		$count = 0;
		for ($sizeOfTkns = sizeof($tkns); $ptr < $sizeOfTkns; ++$ptr) {
			$id = $tkns[$ptr][0];
			if (ST_CURLY_OPEN == $id) {
				++$count;
			}
			if (T_CURLY_OPEN == $id) {
				++$count;
			}
			if (T_DOLLAR_OPEN_CURLY_BRACES == $id) {
				++$count;
			}
			if (ST_CURLY_CLOSE == $id) {
				--$count;
			}
			if (0 == $count) {
				break;
			}
		}
	}

	protected function refSkipIfTokenIsAny($tkns, &$ptr, $skipIds) {
		$skipIds = array_flip($skipIds);
		++$ptr;
		for ($sizeOfTkns = sizeof($tkns); $ptr < $sizeOfTkns; ++$ptr) {
			$id = $tkns[$ptr][0];
			if (!isset($skipIds[$id])) {
				break;
			}
		}
	}

	protected function refSkipBlocks($tkns, &$ptr) {
		for ($sizeOfTkns = sizeof($tkns); $ptr < $sizeOfTkns; ++$ptr) {
			$id = $tkns[$ptr][0];

			if (T_CLOSE_TAG == $id) {
				return;
			}

			if (T_DO == $id) {
				$this->refWalkUsefulUntil($tkns, $ptr, ST_CURLY_OPEN);
				$this->refWalkCurlyBlock($tkns, $ptr);
				$this->refWalkUsefulUntil($tkns, $ptr, ST_PARENTHESES_OPEN);
				$this->refWalkBlock($tkns, $ptr, ST_PARENTHESES_OPEN, ST_PARENTHESES_CLOSE);
				continue;
			}

			if (T_WHILE == $id) {
				$this->refWalkUsefulUntil($tkns, $ptr, ST_PARENTHESES_OPEN);
				$this->refWalkBlock($tkns, $ptr, ST_PARENTHESES_OPEN, ST_PARENTHESES_CLOSE);
				if ($this->rightTokenSubsetIsAtIdx(
					$tkns,
					$ptr,
					ST_CURLY_OPEN,
					$this->ignoreFutileTokens
				)) {
					$this->refWalkUsefulUntil($tkns, $ptr, ST_CURLY_OPEN);
					$this->refWalkCurlyBlock($tkns, $ptr);
					return;
				}
			}

			if (T_FOR == $id || T_FOREACH == $id || T_SWITCH == $id) {
				$this->refWalkUsefulUntil($tkns, $ptr, ST_PARENTHESES_OPEN);
				$this->refWalkBlock($tkns, $ptr, ST_PARENTHESES_OPEN, ST_PARENTHESES_CLOSE);
				$this->refWalkUsefulUntil($tkns, $ptr, ST_CURLY_OPEN);
				$this->refWalkCurlyBlock($tkns, $ptr);
				return;
			}

			if (T_TRY == $id) {
				$this->refWalkUsefulUntil($tkns, $ptr, ST_CURLY_OPEN);
				$this->refWalkCurlyBlock($tkns, $ptr);
				while (
					$this->rightTokenSubsetIsAtIdx(
						$tkns,
						$ptr,
						T_CATCH,
						$this->ignoreFutileTokens
					)
				) {
					$this->refWalkUsefulUntil($tkns, $ptr, ST_PARENTHESES_OPEN);
					$this->refWalkBlock($tkns, $ptr, ST_PARENTHESES_OPEN, ST_PARENTHESES_CLOSE);
					$this->refWalkUsefulUntil($tkns, $ptr, ST_CURLY_OPEN);
					$this->refWalkCurlyBlock($tkns, $ptr);
				}
				if ($this->rightTokenSubsetIsAtIdx(
					$tkns,
					$ptr,
					T_FINALLY,
					$this->ignoreFutileTokens
				)) {
					$this->refWalkUsefulUntil($tkns, $ptr, T_FINALLY);
					$this->refWalkUsefulUntil($tkns, $ptr, ST_CURLY_OPEN);
					$this->refWalkCurlyBlock($tkns, $ptr);
				}
				return;
			}

			if (T_IF == $id) {
				$this->refWalkUsefulUntil($tkns, $ptr, ST_PARENTHESES_OPEN);
				$this->refWalkBlock($tkns, $ptr, ST_PARENTHESES_OPEN, ST_PARENTHESES_CLOSE);
				$this->refWalkUsefulUntil($tkns, $ptr, ST_CURLY_OPEN);
				$this->refWalkCurlyBlock($tkns, $ptr);
				while (true) {
					if (
						$this->rightTokenSubsetIsAtIdx(
							$tkns,
							$ptr,
							T_ELSEIF,
							$this->ignoreFutileTokens
						)
					) {
						$this->refWalkUsefulUntil($tkns, $ptr, ST_PARENTHESES_OPEN);
						$this->refWalkBlock($tkns, $ptr, ST_PARENTHESES_OPEN, ST_PARENTHESES_CLOSE);
						$this->refWalkUsefulUntil($tkns, $ptr, ST_CURLY_OPEN);
						$this->refWalkCurlyBlock($tkns, $ptr);
					} elseif (
						$this->rightTokenSubsetIsAtIdx(
							$tkns,
							$ptr,
							T_ELSE,
							$this->ignoreFutileTokens
						)
					) {
						$this->refWalkUsefulUntil($tkns, $ptr, ST_CURLY_OPEN);
						$this->refWalkCurlyBlock($tkns, $ptr);
						break;
					} else {
						break;
					}
				}
				return;
			}

			if (
				ST_CURLY_OPEN == $id ||
				T_CURLY_OPEN == $id ||
				T_DOLLAR_OPEN_CURLY_BRACES == $id
			) {
				$this->refWalkCurlyBlock($tkns, $ptr);
				continue;
			}

			if (ST_PARENTHESES_OPEN == $id) {
				$this->refWalkBlock($tkns, $ptr, ST_PARENTHESES_OPEN, ST_PARENTHESES_CLOSE);
				continue;
			}

			if (ST_BRACKET_OPEN == $id) {
				$this->refWalkBlock($tkns, $ptr, ST_BRACKET_OPEN, ST_BRACKET_CLOSE);
				continue;
			}

			if (ST_SEMI_COLON == $id) {
				return;
			}
		}
		--$ptr;
	}

	protected function refInsert(&$tkns, &$ptr, $item) {
		array_splice($tkns, $ptr, 0, [$item]);
		++$ptr;
	}
}
