<?php
final class NormalizeLnAndLtrimLines extends FormatterPass {
	public function format($source) {
		$this->tkns = token_get_all($source);
		$this->code = '';
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->get_token($token);
			$this->ptr       = $index;
			$this->ptr       = $index;
			switch ($id) {
				case T_COMMENT:
				case T_DOC_COMMENT:
					list($prev_id, $prev_text) = $this->inspect_token(-1);

					$prev_text = strrev($prev_text);
					$first_ln  = strpos($prev_text, "\n");
					$second_ln = strpos($prev_text, "\n", $first_ln + 1);
					if ($prev_id === T_WHITESPACE && substr_count($prev_text, "\n") >= 2 && 0 === $first_ln && 1 === $second_ln) {
						$this->append_code(LeftAlignComment::NON_INDENTABLE_COMMENT, false);
					} elseif ($prev_id === T_WHITESPACE && "\n" === $prev_text) {
						$this->append_code(LeftAlignComment::NON_INDENTABLE_COMMENT, false);
					}

					if (substr_count($text, "\r\n")) {
						$text = str_replace("\r\n", $this->new_line, $text);
					}
					if (substr_count($text, "\n\r")) {
						$text = str_replace("\n\r", $this->new_line, $text);
					}
					if (substr_count($text, "\r")) {
						$text = str_replace("\r", $this->new_line, $text);
					}
					if (substr_count($text, "\n")) {
						$text = str_replace("\n", $this->new_line, $text);
					}
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
				case T_CONSTANT_ENCAPSED_STRING:
					$this->append_code($text, false);
					break;
				default:
					if (substr_count($text, "\r\n")) {
						$text = str_replace("\r\n", $this->new_line, $text);
					}
					if (substr_count($text, "\n\r")) {
						$text = str_replace("\n\r", $this->new_line, $text);
					}
					if (substr_count($text, "\r")) {
						$text = str_replace("\r", $this->new_line, $text);
					}
					if (substr_count($text, "\n")) {
						$text = str_replace("\n", $this->new_line, $text);
					}

					if ($this->substr_count_trailing($text, $this->new_line) > 0) {
						$text = trim($text) . str_repeat($this->new_line, $this->substr_count_trailing($text, $this->new_line));
					} elseif (0 === $this->substr_count_trailing($text, $this->new_line) && T_WHITESPACE === $id) {
						$text = $this->get_space() . ltrim($text) . str_repeat($this->new_line, $this->substr_count_trailing($text, $this->new_line));
					}
					$this->append_code($text, false);
					break;
			}
		}

		return $this->code;
	}
}