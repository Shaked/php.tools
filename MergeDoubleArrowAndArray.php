<?php
final class MergeDoubleArrowAndArray extends FormatterPass {
	public function format($source) {
		$this->tkns = token_get_all($source);
		$this->code = '';
		$in_do_while_context = 0;
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->get_token($token);
			$this->ptr = $index;
			switch ($id) {
				case T_ARRAY:
					if ($this->is_token([T_DOUBLE_ARROW], true)) {
						$in_do_while_context--;
						$this->append_code($text);
						break;
					}
				default:
					$this->append_code($text, false);
					break;
			}
		}
		return $this->code;
	}
}