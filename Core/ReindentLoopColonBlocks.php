<?php
final class ReindentLoopColonBlocks extends FormatterPass {
	public function candidate($source, $found_tokens) {
		return true;
	}

	public function format($source) {
		$tkns = token_get_all($source);
		$found_endwhile = false;
		$found_endforeach = false;
		$found_endfor = false;
		foreach ($tkns as $token) {
			list($id, $text) = $this->getToken($token);
			if (!$found_endwhile && T_ENDWHILE == $id) {
				$source = $this->format_while_blocks($source);
				$found_endwhile = true;
			} elseif (!$found_endforeach && T_ENDFOREACH == $id) {
				$source = $this->format_foreach_blocks($source);
				$found_endforeach = true;
			} elseif (!$found_endfor && T_ENDFOR == $id) {
				$source = $this->format_for_blocks($source);
				$found_endfor = true;
			} elseif ($found_endwhile && $found_endforeach && $found_endfor) {
				break;
			}
		}
		return $source;
	}

	private function format_blocks($source, $open_token, $close_token) {
		$this->tkns = token_get_all($source);
		$this->code = '';

		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->getToken($token);
			$this->ptr = $index;
			switch ($id) {
				case $close_token:
					$this->setIndent(-1);
					$this->appendCode($text);
					break;
				case $open_token:
					$this->appendCode($text);
					while (list($index, $token) = each($this->tkns)) {
						list($id, $text) = $this->getToken($token);
						$this->ptr = $index;
						$this->appendCode($text);
						if (ST_CURLY_OPEN === $id) {
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
					if ($this->hasLn($text) && !$this->rightTokenIs([$close_token])) {
						$text = str_replace($this->new_line, $this->new_line . $this->getIndent(), $text);
					} elseif ($this->hasLn($text) && $this->rightTokenIs([$close_token])) {
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
	private function format_for_blocks($source) {
		return $this->format_blocks($source, T_FOR, T_ENDFOR);
	}
	private function format_foreach_blocks($source) {
		return $this->format_blocks($source, T_FOREACH, T_ENDFOREACH);
	}
	private function format_while_blocks($source) {
		$this->tkns = token_get_all($source);
		$this->code = '';

		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->getToken($token);
			$this->ptr = $index;
			switch ($id) {
				case T_ENDWHILE:
					$this->setIndent(-1);
					$this->appendCode($text);
					break;
				case T_WHILE:
					$this->appendCode($text);
					while (list($index, $token) = each($this->tkns)) {
						list($id, $text) = $this->getToken($token);
						$this->ptr = $index;
						$this->appendCode($text);
						if (ST_CURLY_OPEN === $id) {
							break;
						} elseif (ST_SEMI_COLON === $id) {
							break;
						} elseif (ST_COLON === $id) {
							$this->setIndent(+1);
							break;
						}
					}
					break;
				default:
					if ($this->hasLn($text) && !$this->rightTokenIs([T_ENDWHILE])) {
						$text = str_replace($this->new_line, $this->new_line . $this->getIndent(), $text);
					} elseif ($this->hasLn($text) && $this->rightTokenIs([T_ENDWHILE])) {
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