<?php
final class PSR1ClassConstants extends FormatterPass {
	public function candidate($source, $foundTokens) {
		if (isset($foundTokens[T_CONST]) || isset($foundTokens[T_STRING])) {
			return true;
		}

		return false;
	}
	public function format($source) {
		$this->tkns = token_get_all($source);
		$this->code = '';
		$uc_const = false;
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->getToken($token);
			$this->ptr = $index;
			switch ($id) {
				case T_CONST:
					$uc_const = true;
					$this->appendCode($text);
					break;
				case T_STRING:
					if ($uc_const) {
						$text = strtoupper($text);
						$uc_const = false;
					}
					$this->appendCode($text);
					break;
				default:
					$this->appendCode($text);
					break;
			}
		}
		return $this->code;
	}
}