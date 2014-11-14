<?php
class SpaceBetweenMethods extends FormatterPass {
	public function format($source) {
		$this->tkns = token_get_all($source);
		$this->code = '';
		$last_touched_token = null;
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->get_token($token);
			$this->ptr = $index;
			switch ($id) {
				case T_FUNCTION:
					$this->append_code($text, false);
					$this->print_until_the_end_of(ST_CURLY_OPEN);
					$this->print_block(ST_CURLY_OPEN, ST_CURLY_CLOSE);
					if (!$this->is_token(ST_CURLY_CLOSE)) {
						$this->append_code($this->get_crlf(), false);
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
