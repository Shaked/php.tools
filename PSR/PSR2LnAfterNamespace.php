<?php
final class PSR2LnAfterNamespace extends FormatterPass {
	public function candidate($source, $found_tokens) {
		if (isset($found_tokens[T_NAMESPACE])) {
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
				case T_NAMESPACE:
					$this->append_code($this->get_crlf($this->left_token_is(ST_CURLY_CLOSE)) . $text);
					while (list($index, $token) = each($this->tkns)) {
						list($id, $text) = $this->get_token($token);
						$this->ptr = $index;
						if (ST_SEMI_COLON === $id) {
							$this->append_code($text);
							list(, $text) = $this->inspect_token();
							if (1 === substr_count($text, $this->new_line)) {
								$this->append_code($this->new_line);
							}
							break;
						} elseif (ST_CURLY_OPEN === $id) {
							$this->append_code($text);
							break;
						} else {
							$this->append_code($text);
						}
					}
					break;
				default:
					$this->append_code($text);
					break;
			}
		}

		return $this->code;
	}
}