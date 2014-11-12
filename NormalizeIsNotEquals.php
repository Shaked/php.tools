<?php
final class NormalizeIsNotEquals extends FormatterPass {
	public function format($source) {
		$this->tkns = token_get_all($source);
		$this->code = '';
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->get_token($token);
			$this->ptr = $index;
			switch ($id) {
				case T_IS_NOT_EQUAL:
					$this->append_code(str_replace('<>', '!=', $text) . $this->get_space(), false);
					break;
				default:
					$this->append_code($text, false);
					break;
			}
		}

		return $this->code;
	}
}
