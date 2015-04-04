<?php
final class ReindentLoopColonBlocks extends FormatterPass {
	public function candidate($source, $foundTokens) {
		if (isset($foundTokens[T_ENDWHILE]) || isset($foundTokens[T_ENDFOREACH]) || isset($foundTokens[T_ENDFOR])) {
			return true;
		}

		return false;
	}

	public function format($source) {
		$tkns = token_get_all($source);
		$foundEndwhile = false;
		$foundEndforeach = false;
		$foundEndfor = false;
		foreach ($tkns as $token) {
			list($id) = $this->getToken($token);
			if (!$foundEndwhile && T_ENDWHILE == $id) {
				$source = $this->formatWhileBlocks($source);
				$foundEndwhile = true;
			} elseif (!$foundEndforeach && T_ENDFOREACH == $id) {
				$source = $this->formatForeachBlocks($source);
				$foundEndforeach = true;
			} elseif (!$foundEndfor && T_ENDFOR == $id) {
				$source = $this->formatForBlocks($source);
				$foundEndfor = true;
			} elseif ($foundEndwhile && $foundEndforeach && $foundEndfor) {
				break;
			}
		}
		return $source;
	}

	private function formatBlocks($source, $open_token, $close_token) {
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
						$text = str_replace($this->newLine, $this->newLine . $this->getIndent(), $text);
					} elseif ($this->hasLn($text) && $this->rightTokenIs([$close_token])) {
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
	private function formatForBlocks($source) {
		return $this->formatBlocks($source, T_FOR, T_ENDFOR);
	}
	private function formatForeachBlocks($source) {
		return $this->formatBlocks($source, T_FOREACH, T_ENDFOREACH);
	}
	private function formatWhileBlocks($source) {
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
						$text = str_replace($this->newLine, $this->newLine . $this->getIndent(), $text);
					} elseif ($this->hasLn($text) && $this->rightTokenIs([T_ENDWHILE])) {
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