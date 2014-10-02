<?php
final class TwoCommandsInSameLine extends FormatterPass {
	public function format($source) {
		$this->tkns = token_get_all($source);
		$this->code = '';
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->get_token($token);
			$this->ptr = $index;

			switch ($id) {
				case ST_SEMI_COLON:
					$this->append_code($text, false);
					if (!$this->has_ln_after() && $this->is_token([T_VARIABLE, T_STRING])) {
						$this->append_code($this->new_line, false);
					}
					break;

				case ST_PARENTHESES_OPEN:
					$this->append_code($text, false);
					while (list($index, $token) = each($this->tkns)) {
						list($id, $text) = $this->get_token($token);
						$this->ptr = $index;
						$this->append_code($text, false);
						if (ST_PARENTHESES_CLOSE == $id) {
							break;
						}
					}
					break;
				default:
					$this->append_code($text, false);
					break;

			}
		}
		return $this->code;
	}
}