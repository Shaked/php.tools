<?php
final class PSR2IndentWithSpace extends FormatterPass {
	private $indent_spaces = '    ';

	public function format($source) {
		$this->tkns = token_get_all($source);
		$this->code = '';
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->get_token($token);
			$this->ptr       = $index;
			switch ($id) {
				case T_COMMENT:
				case T_DOC_COMMENT:
				case T_WHITESPACE:
					$this->append_code(str_replace($this->indent_char, $this->indent_spaces, $text), false);
					break;
				default:
					$this->append_code($text, false);
					break;
			}
		}
		return $this->code;
	}
}