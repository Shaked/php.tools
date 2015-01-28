<?php
final class ExtraCommaInArray extends FormatterPass {
	const ST_SHORT_ARRAY_OPEN = 'SHORT_ARRAY_OPEN';

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
					if (!$this->leftTokenIs([ST_BRACKET_CLOSE, ST_CURLY_CLOSE, ST_PARENTHESES_CLOSE, T_STRING, T_VARIABLE, T_ARRAY_CAST])) {
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
				case ST_PARENTHESES_OPEN:
					if ($this->leftUsefulTokenIs(T_STRING)) {
						$contextStack[] = T_STRING;
					} elseif ($this->leftUsefulTokenIs(T_ARRAY)) {
						$contextStack[] = T_ARRAY;
					} else {
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