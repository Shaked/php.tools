<?php
final class ExtraCommaInArray extends FormatterPass {
	const ST_SHORT_ARRAY_OPEN = 'SHORT_ARRAY_OPEN';
	const EMPTY_ARRAY = 'ST_EMPTY_ARRAY';

	public function candidate($source, $foundTokens) {
		return true;
	}

	public function format($source) {
		$this->tkns = token_get_all($source);

		$contextStack = [];
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->getToken($token);
			$this->ptr = $index;
			switch ($id) {
				case ST_BRACKET_OPEN:
					if (!$this->leftTokenIs([ST_BRACKET_CLOSE, T_STRING, T_VARIABLE, T_ARRAY_CAST])) {
						$contextStack[] = self::ST_SHORT_ARRAY_OPEN;
					} else {
						$contextStack[] = ST_BRACKET_OPEN;
					}
					break;
				case ST_BRACKET_CLOSE:
					if (isset($contextStack[0]) && !$this->leftTokenIs(ST_BRACKET_OPEN)) {
						if (self::ST_SHORT_ARRAY_OPEN == end($contextStack) && ($this->hasLnLeftToken() || $this->hasLnBefore()) && !$this->leftUsefulTokenIs(ST_COMMA)) {
							$prevTokenIdx = $this->leftUsefulTokenIdx();
							list($tknId, $tknText) = $this->getToken($this->tkns[$prevTokenIdx]);
							if (T_END_HEREDOC != $tknId && ST_BRACKET_OPEN != $tknId) {
								$this->tkns[$prevTokenIdx] = [$tknId, $tknText . ','];
							}
						} elseif (self::ST_SHORT_ARRAY_OPEN == end($contextStack) && !($this->hasLnLeftToken() || $this->hasLnBefore()) && $this->leftUsefulTokenIs(ST_COMMA)) {
							$prevTokenIdx = $this->leftUsefulTokenIdx();
							list($tknId, $tknText) = $this->getToken($this->tkns[$prevTokenIdx]);
							$this->tkns[$prevTokenIdx] = [$tknId, rtrim($tknText, ',')];
						}
						array_pop($contextStack);
					}
					break;
				case T_STRING:
					if ($this->rightTokenIs(ST_PARENTHESES_OPEN)) {
						$contextStack[] = T_STRING;
					}
					break;
				case T_ARRAY:
					if ($this->rightTokenIs(ST_PARENTHESES_OPEN)) {
						$contextStack[] = T_ARRAY;
					}
					break;
				case ST_PARENTHESES_OPEN:
					if (isset($contextStack[0]) && T_ARRAY == end($contextStack) && $this->rightTokenIs(ST_PARENTHESES_CLOSE)) {
						array_pop($contextStack);
						$contextStack[] = self::EMPTY_ARRAY;
					} elseif (!$this->leftTokenIs([T_ARRAY, T_STRING])) {
						$contextStack[] = ST_PARENTHESES_OPEN;
					}
					break;
				case ST_PARENTHESES_CLOSE:
					if (isset($contextStack[0])) {
						if (T_ARRAY == end($contextStack) && ($this->hasLnLeftToken() || $this->hasLnBefore()) && !$this->leftUsefulTokenIs(ST_COMMA)) {
							$prevTokenIdx = $this->leftUsefulTokenIdx();
							list($tknId, $tknText) = $this->getToken($this->tkns[$prevTokenIdx]);
							if (T_END_HEREDOC != $tknId && ST_PARENTHESES_OPEN != $tknId) {
								$this->tkns[$prevTokenIdx] = [$tknId, $tknText . ','];
							}
						} elseif (T_ARRAY == end($contextStack) && !($this->hasLnLeftToken() || $this->hasLnBefore()) && $this->leftUsefulTokenIs(ST_COMMA)) {
							$prevTokenIdx = $this->leftUsefulTokenIdx();
							list($tknId, $tknText) = $this->getToken($this->tkns[$prevTokenIdx]);
							$this->tkns[$prevTokenIdx] = [$tknId, rtrim($tknText, ',')];
						}
						array_pop($contextStack);
					}
					break;
			}
			$this->tkns[$this->ptr] = [$id, $text];
		}
		return $this->renderLight();
	}
}