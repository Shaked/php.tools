<?php
final class TwoCommandsInSameLine extends FormatterPass {
	public function candidate($source, $found_tokens) {
		return true;
	}
	public function format($source) {
		$this->tkns = token_get_all($source);
		$this->code = '';
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->get_token($token);
			$this->ptr = $index;

			switch ($id) {
				case ST_SEMI_COLON:
					if ($this->left_token_is(ST_SEMI_COLON)) {
						break;
					}
					$this->append_code($text);
					if (!$this->has_ln_after() && $this->right_token_is([T_VARIABLE, T_STRING])) {
						$this->append_code($this->new_line);
					}
					break;

				case ST_PARENTHESES_OPEN:
					$this->append_code($text);
					$this->print_block(ST_PARENTHESES_OPEN, ST_PARENTHESES_CLOSE);
					break;
				default:
					$this->append_code($text);
					break;

			}
		}
		return $this->code;
	}
}
