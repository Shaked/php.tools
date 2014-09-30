<?php
final class LeftAlignComment extends FormatterPass {
	const NON_INDENTABLE_COMMENT = "/*\x2 COMMENT \x3*/";
	public function format($source) {
		$this->tkns = token_get_all($source);
		$this->code = '';
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->get_token($token);
			$this->ptr       = $index;
			if ($text === self::NON_INDENTABLE_COMMENT) {
				continue;
			}
			switch ($id) {
				case T_COMMENT:
				case T_DOC_COMMENT:
					list(, $prev_text) = $this->inspect_token(-1);
					if ($prev_text === self::NON_INDENTABLE_COMMENT) {
						$lines = explode($this->new_line, $text);
						$lines = array_map(function ($v) {
							$v = ltrim($v);
							if ('*' === substr($v, 0, 1)) {
								$v = ' ' . $v;
							}
							return $v;
						}, $lines);
						$this->append_code(implode($this->new_line, $lines), false);
						break;
					}
				case T_WHITESPACE:
					list(, $next_text) = $this->inspect_token(1);
					if ($next_text === self::NON_INDENTABLE_COMMENT && substr_count($text, "\n") >= 2) {
						$text = substr($text, 0, strrpos($text, "\n") + 1);
						$this->append_code($text, false);
						break;
					} elseif ($next_text === self::NON_INDENTABLE_COMMENT && substr_count($text, "\n") === 1) {
						$text = substr($text, 0, strrpos($text, "\n") + 1);
						$this->append_code($text, false);
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
