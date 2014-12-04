<?php
final class PSR2IndentWithSpace extends FormatterPass {
	private $size = 4;

	public function __construct($size = null) {
		if ($size > 0) {
			$this->size = $size;
		}
	}

	public function format($source) {
		$indent_spaces = str_repeat(' ', (int) $this->size);
		$this->tkns = token_get_all($source);
		$this->code = '';
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->get_token($token);
			$this->ptr = $index;
			switch ($id) {
				case T_COMMENT:
				case T_DOC_COMMENT:
				case T_WHITESPACE:
					$this->append_code(str_replace($this->indent_char, $indent_spaces, $text));
					break;
				default:
					$this->append_code($text);
					break;
			}
		}
		return $this->code;
	}
}