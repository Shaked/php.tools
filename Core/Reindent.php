<?php
final class Reindent extends FormatterPass {
	public function candidate($source, $foundTokens) {
		return true;
	}
	public function format($source) {
		$this->tkns = token_get_all($source);
		$this->code = '';
		$this->useCache = true;
		$found_stack = [];
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->getToken($token);
			$this->ptr = $index;
			$this->cache = [];

			if (
				(
					T_WHITESPACE === $id ||
					(T_COMMENT === $id && '//' == substr($text, 0, 2))
				) && $this->hasLn($text)
			) {
				$bottom_found_stack = end($found_stack);
				if (isset($bottom_found_stack['implicit']) && $bottom_found_stack['implicit']) {
					$idx = sizeof($found_stack) - 1;
					$found_stack[$idx]['implicit'] = false;
					$this->setIndent(+1);
				}
			}
			switch ($id) {
				case ST_QUOTE:
					$this->appendCode($text);
					$this->printUntilTheEndOfString();
					break;
				case T_CLOSE_TAG:
					$this->appendCode($text);
					$this->printUntil(T_OPEN_TAG);
					break;
				case T_START_HEREDOC:
					$this->appendCode(rtrim($text) . $this->getCrlf());
					break;
				case T_CONSTANT_ENCAPSED_STRING:
				case T_ENCAPSED_AND_WHITESPACE:
				case T_STRING_VARNAME:
				case T_NUM_STRING:
					$this->appendCode($text);
					break;
				case T_DOLLAR_OPEN_CURLY_BRACES:
				case T_CURLY_OPEN:
				case ST_CURLY_OPEN:
				case ST_PARENTHESES_OPEN:
				case ST_BRACKET_OPEN:
					$indent_token = [
						'id' => $id,
						'implicit' => true,
					];
					$this->appendCode($text);
					if ($this->hasLnAfter()) {
						$indent_token['implicit'] = false;
						$this->setIndent(+1);
					}
					$found_stack[] = $indent_token;
					break;
				case ST_CURLY_CLOSE:
				case ST_PARENTHESES_CLOSE:
				case ST_BRACKET_CLOSE:
					$popped_id = array_pop($found_stack);
					if (false === $popped_id['implicit']) {
						$this->setIndent(-1);
					}
					$this->appendCode($text);
					break;

				case T_DOC_COMMENT:
					$text = str_replace($this->newLine, $this->newLine . $this->getIndent(), $text);
					$this->appendCode($text);
					break;
				default:
					$has_ln = ($this->hasLn($text));
					if ($has_ln) {
						$is_next_curly_paren_bracket_close = $this->rightTokenIs([ST_CURLY_CLOSE, ST_PARENTHESES_CLOSE, ST_BRACKET_CLOSE]);
						if (!$is_next_curly_paren_bracket_close) {
							$text = str_replace($this->newLine, $this->newLine . $this->getIndent(), $text);
						} elseif ($is_next_curly_paren_bracket_close) {
							$this->setIndent(-1);
							$text = str_replace($this->newLine, $this->newLine . $this->getIndent(), $text);
							$this->setIndent(+1);
						}
					}
					$this->appendCode($text);
					break;
			}
		}
		return $this->code;
	}

}
