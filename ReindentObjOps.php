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
		$paren_stack = [];
		$bracket_count = 0;
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->get_token($token);
			$this->ptr = $index;
			switch ($id) {
				case T_WHILE:
				case T_IF:
				case T_FOR:
				case T_FOREACH:
					$this->append_code($text);
					$this->print_until(ST_PARENTHESES_OPEN);
					$this->print_block(ST_PARENTHESES_OPEN, ST_PARENTHESES_CLOSE);
					break;
				case ST_PARENTHESES_OPEN:
					if ($this->token_is([T_ARRAY], true)) {
						$paren_stack[] = T_ARRAY;
					} else {
						$paren_stack[] = 0;
						++$paren_count;
					}
					$this->append_code($text);
					break;
				case ST_PARENTHESES_CLOSE:
					$stack_pop = array_pop($paren_stack);
					if (T_ARRAY != $stack_pop) {
						--$paren_count;
					}
					$this->append_code($text);
					break;
				case ST_BRACKET_OPEN:
					++$bracket_count;
					$this->append_code($text);
					break;
				case ST_BRACKET_CLOSE:
					--$bracket_count;
					$this->append_code($text);
					break;
				case T_OBJECT_OPERATOR:
					$has_ln_before = ($this->has_ln_before() || $this->has_ln_left_token());
					if (0 === $in_objop_context && $has_ln_before) {
						$this->set_indent(-1);
						$in_objop_context = 1;
						$this->set_indent(+1);
					} elseif (0 === $in_objop_context && !$has_ln_before) {
						++$alignable_objop_counter;
						$in_objop_context = 2;
					} elseif ($paren_count > 0) {
						$in_objop_context = 0;
					}
					if (1 === $in_objop_context) {
						$this->append_code($this->get_indent() . $text);
					} elseif (2 === $in_objop_context) {
						$placeholder = '';
						if (!$printed_placeholder) {
							$placeholder = sprintf(self::ALIGNABLE_OBJOP, $alignable_objop_counter);
							$printed_placeholder = true;
						}
						$this->append_code($placeholder . $text);
					} else {
						$this->append_code($text);
					}
					break;
				case T_VARIABLE:
					if (0 === $paren_count && 0 === $bracket_count && 0 !== $in_objop_context) {
						$in_objop_context = 0;
					}
					$this->append_code($text);
					break;
				case T_DOUBLE_ARROW:
				case ST_SEMI_COLON:
					if (0 !== $in_objop_context) {
						$in_objop_context = 0;
					}
					$this->append_code($text);
					break;
				case T_COMMENT:
				case T_DOC_COMMENT:
					if ($in_objop_context > 0) {
						$this->append_code($this->get_indent() . $text);
						break;
					}
				default:
					$this->append_code($text);
					break;
			}
			if ($this->has_ln($text)) {
				$printed_placeholder = false;
			}
		}

		for ($j = $alignable_objop_counter; $j > 0; --$j) {
			$current_align_objop = sprintf(self::ALIGNABLE_OBJOP, $j);
			if (false === strpos($this->code, $current_align_objop)) {
				continue;
			}
			if (1 === substr_count($this->code, $current_align_objop)) {
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
					$lines_with_objop[$block_count] = [];
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
