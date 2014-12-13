<?php
final class NormalizeLnAndLtrimLines extends FormatterPass {
	public function candidate($source, $found_tokens) {
		return true;
	}
	public function format($source) {
		$source = str_replace(["\r\n", "\n\r", "\r", "\n"], $this->new_line, $source);
		$source = preg_replace('/\h+$/m', '', $source);

		$this->tkns = token_get_all($source);
		$this->code = '';
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->get_token($token);
			$this->ptr = $index;
			switch ($id) {
				case ST_QUOTE:
					$this->append_code($text);
					$this->print_until_the_end_of_string();
					break;
				case T_START_HEREDOC:
					$this->append_code($text);
					$this->print_until(T_END_HEREDOC);
					break;
				case T_COMMENT:
				case T_DOC_COMMENT:
					list($prev_id, $prev_text) = $this->inspect_token(-1);

					if (T_WHITESPACE === $prev_id && ("\n" === $prev_text || "\n\n" == substr($prev_text, -2, 2))) {
						$this->append_code(LeftAlignComment::NON_INDENTABLE_COMMENT);
					}

					$lines = explode($this->new_line, $text);
					$new_text = '';
					foreach ($lines as $v) {
						$v = ltrim($v);
						if ('*' === substr($v, 0, 1)) {
							$v = ' ' . $v;
						}
						$new_text .= $this->new_line . $v;
					}

					$this->append_code(ltrim($new_text));
					break;
				case T_CONSTANT_ENCAPSED_STRING:
					$this->append_code($text);
					break;
				default:
					if ($this->has_ln($text)) {
						$trailing_new_line = $this->substr_count_trailing($text, $this->new_line);
						if ($trailing_new_line > 0) {
							$text = trim($text) . str_repeat($this->new_line, $trailing_new_line);
						}
					}
					$this->append_code($text);
					break;
			}
		}

		return $this->code;
	}
}
