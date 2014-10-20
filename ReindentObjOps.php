<?php
final class ReindentObjOps extends FormatterPass {
	const ALIGNABLE_OBJOP = "\x2 OBJOP%d \x3";
	public function format($source) {
		$this->tkns = token_get_all($source);
		$this->code = '';
		$in_objop_context = 0;// 1 - indent, 2 - don't indent, so future auto-align takes place
		$alignable_objop_counter = 0;
		$printed_placeholder = false;
		$paren_count = 0;
		$bracket_count = 0;
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->get_token($token);
			$this->ptr = $index;
			switch ($id) {
				case ST_PARENTHESES_OPEN:
					++$paren_count;
					$this->append_code($text, false);
					break;
				case ST_PARENTHESES_CLOSE:
					--$paren_count;
					$this->append_code($text, false);
					break;
				case ST_BRACKET_OPEN:
					++$bracket_count;
					$this->append_code($text, false);
					break;
				case ST_BRACKET_CLOSE:
					--$bracket_count;
					$this->append_code($text, false);
					break;
				case T_OBJECT_OPERATOR:
					if (0 === $in_objop_context && ($this->has_ln_before() || $this->has_ln_prev_token())) {
						$in_objop_context = 1;
					} elseif (0 === $in_objop_context && !($this->has_ln_before() || $this->has_ln_prev_token())) {
						++$alignable_objop_counter;
						$in_objop_context = 2;
					} elseif ($paren_count > 0) {
						$in_objop_context = 0;
					}
					if (1 === $in_objop_context) {
						$this->set_indent(+1);
						$this->append_code($this->get_indent() . $text, false);
						$this->set_indent(-1);
					} elseif (2 === $in_objop_context) {
						$placeholder = '';
						if (!$printed_placeholder) {
							$placeholder = sprintf(self::ALIGNABLE_OBJOP, $alignable_objop_counter);
							$printed_placeholder = true;
						}
						$this->append_code($placeholder . $text, false);
					} else {
						$this->append_code($text, false);
					}
					break;
				case T_VARIABLE:
					if (0 === $paren_count && 0 === $bracket_count && 0 !== $in_objop_context) {
						$in_objop_context = 0;
					}
					$this->append_code($text, false);
					break;
				case T_DOUBLE_ARROW:
				case ST_SEMI_COLON:
					if (0 !== $in_objop_context) {
						$in_objop_context = 0;
					}
					$this->append_code($text, false);
					break;
				default:
					$this->append_code($text, false);
					break;
			}
			if (substr_count($text, $this->new_line) > 0) {
				$printed_placeholder = false;
			}
		}

		for ($j = $alignable_objop_counter; $j > 0;--$j) {
			$current_align_objop = sprintf(self::ALIGNABLE_OBJOP, $j);

			if (substr_count($this->code, $current_align_objop) <= 1) {
				$this->code = str_replace($current_align_objop, '', $this->code);
				continue;
			}

			$lines = explode($this->new_line, $this->code);
			$lines_with_objop = [];
			$block_count = 0;

			foreach ($lines as $idx => $line) {
				if (substr_count($line, $current_align_objop) > 0) {
					$lines_with_objop[$block_count][] = $idx;
				} else {
					++$block_count;
				}
			}

			$i = 0;
			foreach ($lines_with_objop as $group) {
				if (1 === sizeof($group)) {
					continue;
				}
				++$i;
				$farthest_objop = 0;
				foreach ($group as $idx) {
					$farthest_objop = max($farthest_objop, strpos($lines[$idx], $current_align_objop));
				}
				foreach ($group as $idx) {
					$line = $lines[$idx];
					$current_objop = strpos($line, $current_align_objop);
					$delta = abs($farthest_objop - $current_objop);
					if ($delta > 0) {
						$line = str_replace($current_align_objop, str_repeat(' ', $delta) . $current_align_objop, $line);
						$lines[$idx] = $line;
					}
				}
			}

			$this->code = str_replace($current_align_objop, '', implode($this->new_line, $lines));
		}

		return $this->code;
	}
}