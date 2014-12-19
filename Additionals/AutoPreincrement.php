<?php
class AutoPreincrement extends AdditionalPass {
	protected $candidate_tokens = [T_INC, T_DEC];
	protected $check_against_concat = false;
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
		$tkns = $this->aggregate_variables($source);
		$touched_concat = false;
		while (list($ptr, $token) = each($tkns)) {
			list($id, $text) = $this->getToken($token);
			switch ($id) {
				case ST_CONCAT:
					$touched_concat = true;
					break;
				case T_INC:
				case T_DEC:
					$prev_token = $tkns[$ptr - 1];
					list($prev_id, ) = $prev_token;
					if (
						(
							!$this->check_against_concat
							||
							($this->check_against_concat && !$touched_concat)
						) &&
						(T_VARIABLE == $prev_id || self::CHAIN_VARIABLE == $prev_id)
					) {
						list($tkns[$ptr], $tkns[$ptr - 1]) = [$tkns[$ptr - 1], $tkns[$ptr]];
						break;
					}
					$touched_concat = false;
			}
		}
		return $this->render($tkns);
	}

	private function aggregate_variables($source) {
		$tkns = token_get_all($source);
		reset($tkns);
		while (list($ptr, $token) = each($tkns)) {
			list($id, $text) = $this->getToken($token);

			if (ST_PARENTHESES_OPEN == $id) {
				$initial_ptr = $ptr;
				$tmp = $this->scanAndReplace($tkns, $ptr, ST_PARENTHESES_OPEN, ST_PARENTHESES_CLOSE, 'swap', $this->candidate_tokens);
				$tkns[$initial_ptr] = [self::PARENTHESES_BLOCK, $tmp];
				continue;
			}
			if (ST_QUOTE == $id) {
				$stack = $text;
				$initial_ptr = $ptr;
				while (list($ptr, $token) = each($tkns)) {
					list($id, $text) = $this->getToken($token);
					$stack .= $text;
					$tkns[$ptr] = null;
					if (ST_QUOTE == $id) {
						break;
					}
				}

				$tkns[$initial_ptr] = [self::CHAIN_STRING, $stack];
				continue;
			}

			if (ST_DOLLAR == $id) {
				$initial_index = $ptr;
				$tkns[$ptr] = null;
				$stack = '';
				do {
					list($ptr, $token) = each($tkns);
					list($id, $text) = $this->getToken($token);
					$tkns[$ptr] = null;
					$stack .= $text;
				} while (ST_CURLY_OPEN != $id);
				$stack = $this->scanAndReplace($tkns, $ptr, ST_CURLY_OPEN, ST_CURLY_CLOSE, 'swap', $this->candidate_tokens);
				$tkns[$initial_index] = [self::CHAIN_VARIABLE, '$' . $stack];
			}

			if (T_STRING == $id || T_VARIABLE == $id || T_NS_SEPARATOR == $id) {
				$initial_index = $ptr;
				$stack = $text;
				$touched_variable = false;
				if (T_VARIABLE == $id) {
					$touched_variable = true;
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
						$text = $this->scanAndReplace($tkns, $ptr, ST_CURLY_OPEN, ST_CURLY_CLOSE, 'swap', $this->candidate_tokens);
					} elseif (ST_BRACKET_OPEN == $id) {
						$text = $this->scanAndReplace($tkns, $ptr, ST_BRACKET_OPEN, ST_BRACKET_CLOSE, 'swap', $this->candidate_tokens);
					} elseif (ST_PARENTHESES_OPEN == $id) {
						$text = $this->scanAndReplace($tkns, $ptr, ST_PARENTHESES_OPEN, ST_PARENTHESES_CLOSE, 'swap', $this->candidate_tokens);
					}

					$stack .= $text;

					if (!$touched_variable && T_VARIABLE == $id) {
						$touched_variable = true;
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
				if (substr(trim($stack), -1, 1) == ST_PARENTHESES_CLOSE) {
					$tkns[$initial_index] = [self::CHAIN_FUNC, $stack];
				} elseif ($touched_variable) {
					$tkns[$initial_index] = [self::CHAIN_VARIABLE, $stack];
				} else {
					$tkns[$initial_index] = [self::CHAIN_LITERAL, $stack];
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