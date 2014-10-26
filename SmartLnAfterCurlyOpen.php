<?php
final class SmartLnAfterCurlyOpen extends FormatterPass {
	public function format($source) {
		$this->tkns = token_get_all($source);
		$this->code = '';
		$curly_count = 0;
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->get_token($token);
			$this->ptr = $index;
			switch ($id) {
				case ST_CURLY_OPEN:
					$this->append_code($text, false);
					$curly_count = 1;
					$stack = '';
					$found_line_break = false;
					$has_ln_after = $this->has_ln_after();
					while (list($index, $token) = each($this->tkns)) {
						list($id, $text) = $this->get_token($token);
						$this->ptr = $index;
						$stack .= $text;
						if (T_START_HEREDOC == $id || ST_QUOTE == $id) {
							while (list($index2, $token2) = each($this->tkns)) {
								list($id2, $text2) = $this->get_token($token2);
								$this->ptr = $index2;
								$stack .= $text2;
								if (T_END_HEREDOC == $id2 || ST_QUOTE == $id2) {
									break;
								}
							}
							continue;
						}
						if (ST_CURLY_OPEN == $id) {
							++$curly_count;
						}
						if (ST_CURLY_CLOSE == $id) {
							--$curly_count;
						}
						if (T_WHITESPACE === $id && substr_count($text, $this->new_line) > 0) {
							$found_line_break = true;
							break;
						}
						if (0 == $curly_count) {
							break;
						}
					}
					if ($found_line_break && !$has_ln_after) {
						$this->append_code($this->new_line, false);
					}
					$this->append_code($stack, false);
					break;
				default:
					$this->append_code($text, false);
					break;
			}
		}
		return $this->code;
	}
}
