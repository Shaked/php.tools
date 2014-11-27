<?php
final class AutoPreincrement extends FormatterPass {
	const CHAIN_VARIABLE = 'CHAIN_VARIABLE';
	const CHAIN_LITERAL = 'CHAIN_LITERAL';
	const CHAIN_FUNC = 'CHAIN_FUNC';
	const CHAIN_STRING = 'CHAIN_STRING';
	const PARENTHESES_BLOCK = 'PARENTHESES_BLOCK';
	public function format($source) {
		return $this->swap($source);
	}
	protected function swap($source) {
		$tkns = $this->aggregate_variables($source);
		reset($tkns);
		while (list($ptr, $token) = each($tkns)) {
			list($id, $text) = $this->get_token($token);
			switch ($id) {
				case T_INC:
				case T_DEC:
					$prev_token = $tkns[$ptr - 1];
					list($prev_id, ) = $prev_token;
					if (T_VARIABLE == $prev_id || self::CHAIN_VARIABLE == $prev_id) {
						list($tkns[$ptr], $tkns[$ptr - 1]) = [$tkns[$ptr - 1], $tkns[$ptr]];
						break;
					}
			}
		}
		return implode('', array_map(function ($token) {
			list($id, $text) = $this->get_token($token);
			return $text;
		}, array_filter($tkns)));
	}

	private function aggregate_variables($source) {
		$tkns = token_get_all($source);
		reset($tkns);
		while (list($ptr, $token) = each($tkns)) {
			list($id, $text) = $this->get_token($token);

			if (ST_PARENTHESES_OPEN == $id) {
				$initial_ptr = $ptr;
				$tmp = $this->scan_and_replace($tkns, $ptr, ST_PARENTHESES_OPEN, ST_PARENTHESES_CLOSE, 'swap');
				$tkns[$initial_ptr] = [self::PARENTHESES_BLOCK, $tmp];
				continue;
			}
			if (ST_QUOTE == $id) {
				$stack = $text;
				$initial_ptr = $ptr;
				while (list($ptr, $token) = each($tkns)) {
					list($id, $text) = $this->get_token($token);
					$stack .= $text;
					$tkns[$ptr] = null;
					if (ST_QUOTE == $id) {
						break;
					}
				}

				$tkns[$initial_ptr] = [self::CHAIN_STRING, $stack];
				continue;
			}

			if (T_STRING == $id || T_VARIABLE == $id || T_NS_SEPARATOR == $id) {
				$initial_index = $ptr;
				$stack = $text;
				$touched_variable = false;
				if (T_VARIABLE == $id) {
					$touched_variable = true;
				}
				if (!$this->right_token_subset_is_at_idx(
					$tkns,
					$ptr,
					[T_STRING, T_VARIABLE, T_NS_SEPARATOR, T_OBJECT_OPERATOR, T_DOUBLE_COLON, ST_CURLY_OPEN, ST_PARENTHESES_OPEN, ST_BRACKET_OPEN]
				)) {
					continue;
				}

				while (list($ptr, $token) = each($tkns)) {
					list($id, $text) = $this->get_token($token);
					// if (ST_CURLY_CLOSE == $id || ST_BRACKET_CLOSE == $id || ST_PARENTHESES_CLOSE == $id || ST_SEMI_COLON == $id) {
					// 	$token = prev($tkns);
					// 	$ptr = key($tkns);
					// 	list($id, $text) = $this->get_token($token);
					// 	break;
					// }
					$tkns[$ptr] = null;
					if (ST_CURLY_OPEN == $id) {
						$text = $this->scan_and_replace($tkns, $ptr, ST_CURLY_OPEN, ST_CURLY_CLOSE, 'swap');
					} elseif (ST_BRACKET_OPEN == $id) {
						$text = $this->scan_and_replace($tkns, $ptr, ST_BRACKET_OPEN, ST_BRACKET_CLOSE, 'swap');
					} elseif (ST_PARENTHESES_OPEN == $id) {
						$text = $this->scan_and_replace($tkns, $ptr, ST_PARENTHESES_OPEN, ST_PARENTHESES_CLOSE, 'swap');
					}

					$stack .= $text;

					if (!$touched_variable && T_VARIABLE == $id) {
						$touched_variable = true;
					}

					if (
						!$this->right_token_subset_is_at_idx(
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
}