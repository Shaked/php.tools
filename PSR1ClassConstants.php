<?php
final class PSR1ClassConstants extends FormatterPass {
	public function format($source) {
		$this->tkns = token_get_all($source);
		$this->code = '';
		$uc_const   = false;
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->get_token($token);
			$this->ptr       = $index;
			switch ($id) {
				case T_CONST:
					$uc_const = true;
					$this->append_code($text, false);
					break;
				case T_STRING:
					if ($uc_const) {
						$text     = strtoupper($text);
						$uc_const = false;
					}
					$this->append_code($text, false);
					break;
				default:
					$this->append_code($text, false);
					break;
			}
		}
		return $this->code;
	}
}