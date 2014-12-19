<?php
abstract class FormatterPass {
	protected $indent_char = "\t";
	protected $new_line = "\n";
	protected $indent = 0;
	protected $code = '';
	protected $ptr = 0;
	protected $tkns = [];
	protected $use_cache = false;
	protected $cache = [];
	protected $ignore_futile_tokens = [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT];

	protected function appendCode($code = "") {
		$this->code .= $code;
	}

	private function calculateCacheKey($direction, $ignore_list, $token) {
		return $direction . "\x2" . implode('', $ignore_list) . "\x2" . (is_array($token) ? implode("\x2", $token) : $token);
	}

	abstract public function candidate($source, $found_tokens);
	abstract public function format($source);

	protected function getToken($token) {
		if (isset($token[1])) {
			return $token;
		} else {
			return [$token, $token];
		}
	}

	protected function getCrlf($true = true) {
		return $true ? $this->new_line : "";
	}

	protected function getCrlfIndent() {
		return $this->getCrlf() . $this->getIndent();
	}

	protected function getIndent($increment = 0) {
		return str_repeat($this->indent_char, $this->indent + $increment);
	}

	protected function getSpace($true = true) {
		return $true ? " " : "";
	}

	protected function hasLn($text) {
		return (false !== strpos($text, $this->new_line));
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
		list($id, $text) = $this->getToken($this->leftToken());
		return $this->hasLn($text);
	}

	protected function hasLnRightToken() {
		list($id, $text) = $this->getToken($this->rightToken());
		return $this->hasLn($text);
	}

	protected function inspectToken($delta = 1) {
		if (!isset($this->tkns[$this->ptr + $delta])) {
			return [null, null];
		}
		return $this->getToken($this->tkns[$this->ptr + $delta]);
	}

	protected function leftToken($ignore_list = [], $idx = false) {
		$i = $this->leftTokenIdx($ignore_list);

		return $this->tkns[$i];
	}

	protected function leftTokenIdx($ignore_list = []) {
		$ignore_list = $this->resolveIgnoreList($ignore_list);

		$i = $this->walkLeft($this->tkns, $this->ptr, $ignore_list);

		return $i;
	}

	protected function leftTokenIs($token, $ignore_list = []) {
		return $this->tokenIs('left', $token, $ignore_list);
	}

	protected function leftTokenSubsetIsAtIdx($tkns, $idx, $token, $ignore_list = []) {
		$ignore_list = $this->resolveIgnoreList($ignore_list);

		$idx = $this->walkLeft($tkns, $idx, $ignore_list);

		return $this->resolveTokenMatch($tkns, $idx, $token);
	}

	protected function leftUsefulToken() {
		return $this->leftToken($this->ignore_futile_tokens);
	}

	protected function leftUsefulTokenIdx() {
		return $this->leftTokenIdx($this->ignore_futile_tokens);
	}

	protected function leftUsefulTokenIs($token) {
		return $this->leftTokenIs($token, $this->ignore_futile_tokens);
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
		$whitespace_new_line = false;
		if (isset($tknids[$this->new_line])) {
			$whitespace_new_line = true;
		}
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->getToken($token);
			$this->ptr = $index;
			$this->cache = [];
			$this->appendCode($text);
			if ($whitespace_new_line && T_WHITESPACE == $id && $this->hasLn($text)) {
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
			list($id, $text) = $this->getToken($token);
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

	private function resolveIgnoreList($ignore_list = []) {
		if (empty($ignore_list)) {
			$ignore_list[T_WHITESPACE] = true;
		} else {
			$ignore_list = array_flip($ignore_list);
		}
		return $ignore_list;
	}

	private function resolveTokenMatch($tkns, $idx, $token) {
		if (!isset($tkns[$idx])) {
			return false;
		}

		$found_token = $tkns[$idx];
		if ($found_token === $token) {
			return true;
		} elseif (is_array($token) && isset($found_token[1]) && in_array($found_token[0], $token)) {
			return true;
		} elseif (is_array($token) && !isset($found_token[1]) && in_array($found_token, $token)) {
			return true;
		} elseif (isset($found_token[1]) && $found_token[0] == $token) {
			return true;
		}

		return false;
	}

	protected function rightToken($ignore_list = []) {
		$i = $this->rightTokenIdx($ignore_list);

		return $this->tkns[$i];
	}

	protected function rightTokenIdx($ignore_list = []) {
		$ignore_list = $this->resolveIgnoreList($ignore_list);

		$i = $this->walkRight($this->tkns, $this->ptr, $ignore_list);

		return $i;
	}

	protected function rightTokenIs($token, $ignore_list = []) {
		return $this->tokenIs('right', $token, $ignore_list);
	}

	protected function rightTokenSubsetIsAtIdx($tkns, $idx, $token, $ignore_list = []) {
		$ignore_list = $this->resolveIgnoreList($ignore_list);

		$idx = $this->walkRight($tkns, $idx, $ignore_list);

		return $this->resolveTokenMatch($tkns, $idx, $token);
	}

	protected function rightUsefulToken() {
		return $this->rightToken($this->ignore_futile_tokens);
	}

	// protected function right_useful_token_idx($idx = false) {
	// 	return $this->right_token_idx($this->ignore_futile_tokens);
	// }

	protected function rightUsefulTokenIs($token) {
		return $this->rightTokenIs($token, $this->ignore_futile_tokens);
	}

	protected function rtrimAndAppendCode($code = "") {
		$this->code = rtrim($this->code) . $code;
	}

	protected function scanAndReplace(&$tkns, &$ptr, $start, $end, $call, $look_for) {
		$look_for = array_flip($look_for);
		$placeholder = '<?php' . ' /*\x2 PHPOPEN \x3*/';
		$tmp = '';
		$tkn_count = 1;
		$found_potential_tokens = false;
		while (list($ptr, $token) = each($tkns)) {
			list($id, $text) = $this->getToken($token);
			if (isset($look_for[$id])) {
				$found_potential_tokens = true;
			}
			if ($start == $id) {
				++$tkn_count;
			}
			if ($end == $id) {
				--$tkn_count;
			}
			$tkns[$ptr] = null;
			if (0 == $tkn_count) {
				break;
			}
			$tmp .= $text;
		}
		if ($found_potential_tokens) {
			return $start . str_replace($placeholder, '', $this->{$call}($placeholder . $tmp)) . $end;
		}
		return $start . $tmp . $end;

	}

	protected function setIndent($increment) {
		$this->indent += $increment;
		if ($this->indent < 0) {
			$this->indent = 0;
		}
	}

	protected function siblings($tkns, $ptr) {
		$ignore_list = $this->resolveIgnoreList([T_WHITESPACE]);
		$left = $this->walkLeft($tkns, $ptr, $ignore_list);
		$right = $this->walkRight($tkns, $ptr, $ignore_list);
		return [$left, $right];
	}

	protected function substrCountTrailing($haystack, $needle) {
		return strlen(rtrim($haystack, " \t")) - strlen(rtrim($haystack, " \t" . $needle));
	}

	protected function tokenIs($direction, $token, $ignore_list = []) {
		if ('left' != $direction) {
			$direction = 'right';
		}
		if (!$this->use_cache) {
			return $this->{$direction . 'tokenSubsetIsAtIdx'}($this->tkns, $this->ptr, $token, $ignore_list);
		}

		$key = $this->calculateCacheKey($direction, $ignore_list, $token);
		if (isset($this->cache[$key])) {
			return $this->cache[$key];
		}

		$ret = $this->{$direction . 'tokenSubsetIsAtIdx'}($this->tkns, $this->ptr, $token, $ignore_list);
		$this->cache[$key] = $ret;

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

	private function walkLeft($tkns, $idx, $ignore_list) {
		$i = $idx;
		while (--$i >= 0 && isset($tkns[$i][1]) && isset($ignore_list[$tkns[$i][0]]));
		return $i;
	}

	private function walkRight($tkns, $idx, $ignore_list) {
		$i = $idx;
		$tkns_size = sizeof($tkns) - 1;
		while (++$i < $tkns_size && isset($tkns[$i][1]) && isset($ignore_list[$tkns[$i][0]]));
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
}
