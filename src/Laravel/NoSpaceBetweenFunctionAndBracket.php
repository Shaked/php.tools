<?php
final class NoSpaceBetweenFunctionAndBracket extends FormatterPass {
	public function candidate($source, $foundTokens) {
		if (isset($foundTokens[T_FUNCTION])) {
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
				case ST_PARENTHESES_OPEN:
					if ($this->leftUsefulTokenIs(T_FUNCTION)) {
						$this->rtrimAndAppendCode($text);
						break;
					}

				default:
					$this->appendCode($text);
					break;
			}
		}

		return $this->code;
	}
}
