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

	private function formatBlocks($source, $openToken, $closeToken) {
		$this->tkns = token_get_all($source);
		$this->code = '';

		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->getToken($token);
			$this->ptr = $index;
			switch ($id) {
				case $closeToken:
					$this->setIndent(-1);
					$this->appendCode($text);
					break;
				case $openToken:
					$this->appendCode($text);
					$this->printUntil(ST_PARENTHESES_OPEN);
					$this->printBlock(ST_PARENTHESES_OPEN, ST_PARENTHESES_CLOSE);
					$foundId = $this->printUntilAny([ST_CURLY_OPEN, ST_SEMI_COLON, ST_COLON]);
					if (ST_COLON === $foundId && !$this->rightTokenIs([T_CLOSE_TAG])) {
						$this->setIndent(+1);
					}
					break;
				default:
					if ($this->hasLn($text) && !$this->rightTokenIs([$closeToken])) {
						$text = str_replace($this->newLine, $this->newLine . $this->getIndent(), $text);
					} elseif ($this->hasLn($text) && $this->rightTokenIs([$closeToken])) {
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
		return $this->formatBlocks($source, T_WHILE, T_ENDWHILE);
	}
}