<?php
final class NormalizeIsNotEquals extends FormatterPass {
	public function candidate($source, $foundTokens) {
		if (isset($foundTokens[T_IS_NOT_EQUAL])) {
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
				case T_IS_NOT_EQUAL:
					$this->appendCode(str_replace('<>', '!=', $text) . $this->getSpace());
					break;
				default:
					$this->appendCode($text);
					break;
			}
		}

		return $this->code;
	}
}
