<?php
final class ReindentIfColonBlocks extends FormatterPass {
	public function candidate($source, $found_tokens) {
		return true;
	}
	public function format($source) {
		$this->tkns = token_get_all($source);
		$found_colon = false;
		foreach ($this->tkns as $token) {
			list($id, $text) = $this->getToken($token);
			if (ST_COLON == trim($text)) {
				$found_colon = true;
				break;
			}
		}
		if (!$found_colon) {
			return $source;
		}
		reset($this->tkns);
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
							$paren_count = 1;
							while (list($index, $token) = each($this->tkns)) {
								list($id, $text) = $this->getToken($token);
								$this->ptr = $index;
								$this->appendCode($text);
								if (ST_PARENTHESES_OPEN === $id) {
									++$paren_count;
								}
								if (ST_PARENTHESES_CLOSE === $id) {
									--$paren_count;
								}
								if (0 == $paren_count) {
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
				default:
					$has_ln = $this->hasLn($text);
					if ($has_ln && !$this->rightTokenIs([T_ENDIF, T_ELSE, T_ELSEIF])) {
						$text = str_replace($this->new_line, $this->new_line . $this->getIndent(), $text);
					} elseif ($has_ln && $this->rightTokenIs([T_ENDIF, T_ELSE, T_ELSEIF])) {
						$this->setIndent(-1);
						$text = str_replace($this->new_line, $this->new_line . $this->getIndent(), $text);
						$this->setIndent(+1);
					}
					$this->appendCode($text);
					break;
			}
		}
		return $this->code;
	}
}