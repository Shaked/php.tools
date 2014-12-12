<?php
final class NormalizeIsNotEquals extends FormatterPass {
	public function candidate($source, $found_tokens) {
		if (isset($found_tokens[T_IS_NOT_EQUAL])) {
			return true;
		}

		return false;
	}
	public function format($source) {
		$this->tkns = token_get_all($source);
		$this->code = '';

		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->get_token($token);
			$this->ptr = $index;
			switch ($id) {
				case T_IS_NOT_EQUAL:
					$this->append_code(str_replace('<>', '!=', $text) . $this->get_space());
					break;
				default:
					$this->append_code($text);
					break;
			}
		}

		return $this->code;
	}
}
