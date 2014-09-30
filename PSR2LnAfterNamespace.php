<?php
final class PSR2LnAfterNamespace extends FormatterPass {
	public function format($source) {
		$this->tkns = token_get_all($source);
		$this->code = '';

		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->get_token($token);
			$this->ptr       = $index;
			switch ($id) {
				case T_NAMESPACE:
					$this->append_code($text, false);
					while (list($index, $token) = each($this->tkns)) {
						list($id, $text) = $this->get_token($token);
						$this->ptr       = $index;
						if (ST_SEMI_COLON === $id) {
							$this->append_code($text, false);
							list(, $text) = $this->inspect_token();
							if (1 === substr_count($text, $this->new_line)) {
								$this->append_code($this->new_line, false);
							}
							break;
						} else {
							$this->append_code($text, false);
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