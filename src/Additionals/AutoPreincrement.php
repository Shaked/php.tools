<?php
class AutoPreincrement extends AdditionalPass {
	protected $candidateTokens = [T_INC, T_DEC];
	protected $checkAgainstConcat = false;
	const CHAIN_VARIABLE = 'CHAIN_VARIABLE';
	const CHAIN_LITERAL = 'CHAIN_LITERAL';
	const CHAIN_FUNC = 'CHAIN_FUNC';
	const CHAIN_STRING = 'CHAIN_STRING';
	const PARENTHESES_BLOCK = 'PARENTHESES_BLOCK';
	public function candidate($source, $foundTokens) {
		if (isset($foundTokens[T_INC]) || isset($foundTokens[T_DEC])) {
			return true;
		}

		return false;
	}
	public function format($source) {
		return $this->swap($source);
	}
	protected function swap($source) {
		$tkns = $this->aggregateVariables($source);
		$touchedConcat = false;
		while (list($ptr, $token) = each($tkns)) {
			list($id) = $this->getToken($token);
			switch ($id) {
				case ST_CONCAT:
					$touchedConcat = true;
					break;
				case T_INC:
				case T_DEC:
					$prevToken = $tkns[$ptr - 1];
					list($prevId) = $prevToken;
					if (
						(
							!$this->checkAgainstConcat
							||
							($this->checkAgainstConcat && !$touchedConcat)
						) &&
						(T_VARIABLE == $prevId || self::CHAIN_VARIABLE == $prevId)
					) {
						list($tkns[$ptr], $tkns[$ptr - 1]) = [$tkns[$ptr - 1], $tkns[$ptr]];
						break;
					}
					$touchedConcat = false;
			}
		}
		return $this->render($tkns);
	}

	private function aggregateVariables($source) {
		$tkns = token_get_all($source);
		reset($tkns);
		while (list($ptr, $token) = each($tkns)) {
			list($id, $text) = $this->getToken($token);

			if (ST_PARENTHESES_OPEN == $id) {
				$initialPtr = $ptr;
				$tmp = $this->scanAndReplace($tkns, $ptr, ST_PARENTHESES_OPEN, ST_PARENTHESES_CLOSE, 'swap', $this->candidateTokens);
				$tkns[$initialPtr] = [self::PARENTHESES_BLOCK, $tmp];
				continue;
			}
			if (ST_QUOTE == $id) {
				$stack = $text;
				$initialPtr = $ptr;
				while (list($ptr, $token) = each($tkns)) {
					list($id, $text) = $this->getToken($token);
					$stack .= $text;
					$tkns[$ptr] = null;
					if (ST_QUOTE == $id) {
						break;
					}
				}

				$tkns[$initialPtr] = [self::CHAIN_STRING, $stack];
				continue;
			}

			if (ST_DOLLAR == $id) {
				$initialIndex = $ptr;
				$tkns[$ptr] = null;
				$stack = '';
				do {
					list($ptr, $token) = each($tkns);
					list($id, $text) = $this->getToken($token);
					$tkns[$ptr] = null;
					$stack .= $text;
				} while (ST_CURLY_OPEN != $id);
				$stack = $this->scanAndReplace($tkns, $ptr, ST_CURLY_OPEN, ST_CURLY_CLOSE, 'swap', $this->candidateTokens);
				$tkns[$initialIndex] = [self::CHAIN_VARIABLE, '$' . $stack];
			}

			if (T_STRING == $id || T_VARIABLE == $id || T_NS_SEPARATOR == $id) {
				$initialIndex = $ptr;
				$stack = $text;
				$touchedVariable = false;
				if (T_VARIABLE == $id) {
					$touchedVariable = true;
				}
				if (!$this->rightTokenSubsetIsAtIdx(
					$tkns,
					$ptr,
					[T_STRING, T_VARIABLE, T_NS_SEPARATOR, T_OBJECT_OPERATOR, T_DOUBLE_COLON, ST_CURLY_OPEN, ST_PARENTHESES_OPEN, ST_BRACKET_OPEN]
				)) {
					continue;
				}

				while (list($ptr, $token) = each($tkns)) {
					list($id, $text) = $this->getToken($token);
					$tkns[$ptr] = null;
					if (ST_CURLY_OPEN == $id) {
						$text = $this->scanAndReplaceCurly($tkns, $ptr, ST_CURLY_OPEN, 'swap', $this->candidateTokens);
					} elseif (T_CURLY_OPEN == $id) {
						$text = $this->scanAndReplaceCurly($tkns, $ptr, ST_CURLY_OPEN, 'swap', $this->candidateTokens);
					} elseif (T_DOLLAR_OPEN_CURLY_BRACES == $id) {
						$text = $this->scanAndReplaceCurly($tkns, $ptr, ST_DOLLAR . ST_CURLY_OPEN, 'swap', $this->candidateTokens);
					} elseif (ST_BRACKET_OPEN == $id) {
						$text = $this->scanAndReplace($tkns, $ptr, ST_BRACKET_OPEN, ST_BRACKET_CLOSE, 'swap', $this->candidateTokens);
					} elseif (ST_PARENTHESES_OPEN == $id) {
						$text = $this->scanAndReplace($tkns, $ptr, ST_PARENTHESES_OPEN, ST_PARENTHESES_CLOSE, 'swap', $this->candidateTokens);
					}

					$stack .= $text;

					if (!$touchedVariable && T_VARIABLE == $id) {
						$touchedVariable = true;
					}

					if (
						!$this->rightTokenSubsetIsAtIdx(
							$tkns,
							$ptr,
							[T_STRING, T_VARIABLE, T_NS_SEPARATOR, T_OBJECT_OPERATOR, T_DOUBLE_COLON, ST_CURLY_OPEN, ST_PARENTHESES_OPEN, ST_BRACKET_OPEN]
						)
					) {
						break;
					}
				}
				$tkns[$initialIndex] = [self::CHAIN_LITERAL, $stack];
				if (substr(trim($stack), -1, 1) == ST_PARENTHESES_CLOSE) {
					$tkns[$initialIndex] = [self::CHAIN_FUNC, $stack];
				} elseif ($touchedVariable) {
					$tkns[$initialIndex] = [self::CHAIN_VARIABLE, $stack];
				}
			}
		}
		$tkns = array_values(array_filter($tkns));
		return $tkns;
	}

	/**
	 * @codeCoverageIgnore
	 */
	public function getDescription() {
		return 'Automatically convert postincrement to preincrement.';
	}
	/**
	 * @codeCoverageIgnore
	 */
	public function getExample() {
		return <<<'EOT'
<?php
$a++;
$b--;

++$a;
--$b;
?>
EOT;
	}
}