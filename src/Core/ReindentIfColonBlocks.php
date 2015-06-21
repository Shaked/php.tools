<?php
final class ReindentIfColonBlocks extends FormatterPass {
	public function candidate($source, $foundTokens) {
		if (isset($foundTokens[ST_COLON])) {
			return true;
		}

		return false;
	}

	public function format($source) {
		$this->tkns = token_get_all($source);
		$this->code = '';

		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->getToken($token);
			$this->ptr = $index;
			switch ($id) {
				case T_ENDIF:
					$this->setIndent(-1);
					$this->appendCode($text);
					break;
				case T_ELSE:
				case T_ELSEIF:
					$this->setIndent(-1);
				case T_IF:
					$this->appendCode($text);
					while (list($index, $token) = each($this->tkns)) {
						list($id, $text) = $this->getToken($token);
						$this->ptr = $index;
						$this->appendCode($text);
						if (ST_PARENTHESES_OPEN === $id) {
							$parenCount = 1;
							while (list($index, $token) = each($this->tkns)) {
								list($id, $text) = $this->getToken($token);
								$this->ptr = $index;
								$this->appendCode($text);
								if (ST_PARENTHESES_OPEN === $id) {
									++$parenCount;
								}
								if (ST_PARENTHESES_CLOSE === $id) {
									--$parenCount;
								}
								if (0 == $parenCount) {
									break;
								}
							}
						} elseif (ST_CURLY_OPEN === $id) {
							break;
						} elseif (ST_COLON === $id && !$this->rightTokenIs([T_CLOSE_TAG])) {
							$this->setIndent(+1);
							break;
						} elseif (ST_COLON === $id) {
							break;
						}
					}
					break;
				case T_END_HEREDOC:
					$this->code = rtrim($this->code, " \t");
					$this->appendCode($text);
					break;
				default:
					$hasLn = $this->hasLn($text);
					if ($hasLn && !$this->rightTokenIs([T_ENDIF, T_ELSE, T_ELSEIF])) {
						$text = str_replace($this->newLine, $this->newLine . $this->getIndent(), $text);
					} elseif ($hasLn && $this->rightTokenIs([T_ENDIF, T_ELSE, T_ELSEIF])) {
						$this->setIndent(-1);
						$text = str_replace($this->newLine, $this->newLine . $this->getIndent(), $text);
						$this->setIndent(+1);
					}
					$this->appendCode($text);
					break;
			}
		}
		return $this->code;
	}
}