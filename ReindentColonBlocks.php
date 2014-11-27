<?php
final class ReindentColonBlocks extends FormatterPass {
	public function format($source) {
		$this->tkns = token_get_all($source);
		$found_colon = false;
		foreach ($this->tkns as $token) {
			list($id, $text) = $this->get_token($token);
			if (T_DEFAULT == $id || T_CASE == $id || T_SWITCH == $id) {
				$found_colon = true;
				break;
			}
		}
		if (!$found_colon) {
			return $source;
		}
		reset($this->tkns);
		$this->code = '';

		$switch_level = 0;
		$switch_curly_count = [];
		$switch_curly_count[$switch_level] = 0;
		$is_next_case_or_default = false;
		$touched_colon = false;
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->get_token($token);
			$this->ptr = $index;
			switch ($id) {
				case ST_QUOTE:
					$this->append_code($text);
					$this->print_until_the_end_of_string();
					break;
				case T_SWITCH:
					++$switch_level;
					$switch_curly_count[$switch_level] = 0;
					$touched_colon = false;
					$this->append_code($text);
					break;
				case ST_CURLY_OPEN:
					$this->append_code($text);
					if ($this->left_token_is([T_VARIABLE])) {
						$this->print_until(ST_CURLY_CLOSE);
						break;
					}
					++$switch_curly_count[$switch_level];
					break;
				case ST_CURLY_CLOSE:
					--$switch_curly_count[$switch_level];
					if (0 === $switch_curly_count[$switch_level] && $switch_level > 0) {
						--$switch_level;
					}
					$this->append_code($this->get_indent($switch_level) . $text);
					break;
				case T_DEFAULT:
				case T_CASE:
					$touched_colon = false;
					$this->append_code($text);
					break;
				case ST_COLON:
					$touched_colon = true;
					$this->append_code($text);
					break;
				default:
					$has_ln = $this->has_ln($text);
					if ($has_ln) {
						$is_next_case_or_default = $this->right_useful_token_is([T_CASE, T_DEFAULT]);
						if ($touched_colon && T_COMMENT == $id && $is_next_case_or_default) {
							$this->append_code($text);
						} elseif ($touched_colon && T_COMMENT == $id && !$is_next_case_or_default) {
							$this->append_code($this->get_indent($switch_level) . $text);
						} elseif (!$is_next_case_or_default && !$this->right_token_is([ST_CURLY_CLOSE, T_COMMENT, T_DOC_COMMENT])) {
							$this->append_code($text . $this->get_indent($switch_level));
						} else {
							$this->append_code($text);
						}
					} else {
						$this->append_code($text);
					}
					break;
			}
		}
		return $this->code;
	}
}