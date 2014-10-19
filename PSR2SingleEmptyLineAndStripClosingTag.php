<?php
final class PSR2SingleEmptyLineAndStripClosingTag extends FormatterPass {
	public function format($source) {
		$this->tkns = token_get_all($source);
		$this->code = '';

		$open_tag_count = 0;
		while (list($index, $token) = each($this->tkns)) {
			list($id, ) = $this->get_token($token);
			if (T_OPEN_TAG === $id) {
				++$open_tag_count;
				break;
			}
		}

		reset($this->tkns);
		if (1 === $open_tag_count) {
			while (list($index, $token) = each($this->tkns)) {
				list($id, $text) = $this->get_token($token);
				$this->ptr = $index;
				switch ($id) {
					case T_CLOSE_TAG:
						$this->append_code($this->get_crlf(), false);
						break;
					default:
						$this->append_code($text, false);
						break;
				}
			}
			$this->code = rtrim($this->code);
		} else {
			while (list($index, $token) = each($this->tkns)) {
				list($id, $text) = $this->get_token($token);
				$this->ptr = $index;
				$this->append_code($text, false);
			}
		}
		$this->code = rtrim($this->code) . $this->get_crlf();

		return $this->code;
	}
}
