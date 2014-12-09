<?php
final class ReindentObjOps extends FormatterPass {
	const ALIGNABLE_OBJOP = "\x2 OBJOP%d.%d.%d \x3";

	const ALIGN_WITH_INDENT = 1;
	const ALIGN_WITH_SPACES = 2;
	public function format($source) {
		$this->tkns = token_get_all($source);
		$this->code = '';

		$level_counter = 0;
		$level_entrance_counter = [];
		$context_counter = [];
		$touch_counter = [];
		$align_type = [];
		$printed_placeholder = [];
		$max_context_counter = [];
		$touched_ln = false;

		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->get_token($token);
			$this->ptr = $index;
			switch ($id) {
				case T_WHILE:
				case T_IF:
				case T_FOR:
				case T_FOREACH:
				case T_SWITCH:
					$this->append_code($text);
					$this->print_until(ST_PARENTHESES_OPEN);
					$this->print_block(ST_PARENTHESES_OPEN, ST_PARENTHESES_CLOSE);
					break;

				case T_FUNCTION:
					$this->append_code($text);
					if (!$this->right_useful_token_is(T_STRING)) {
						$this->print_until(ST_PARENTHESES_OPEN);
						$this->print_block(ST_PARENTHESES_OPEN, ST_PARENTHESES_CLOSE);
						$this->print_until(ST_CURLY_OPEN);
						$this->print_block(ST_CURLY_OPEN, ST_CURLY_CLOSE);
					}
					break;

				case T_VARIABLE:
				case T_STRING:
					$this->append_code($text);
					if (!isset($level_entrance_counter[$level_counter])) {
						$level_entrance_counter[$level_counter] = 0;
					}
					if (!isset($context_counter[$level_counter][$level_entrance_counter[$level_counter]])) {
						$context_counter[$level_counter][$level_entrance_counter[$level_counter]] = 0;
						$max_context_counter[$level_counter][$level_entrance_counter[$level_counter]] = 0;
						$touch_counter[$level_counter][$level_entrance_counter[$level_counter]] = 0;
						$align_type[$level_counter][$level_entrance_counter[$level_counter]] = 0;
						$printed_placeholder[$level_counter][$level_entrance_counter[$level_counter]][$context_counter[$level_counter][$level_entrance_counter[$level_counter]]] = 0;
					}
					break;

				case ST_PARENTHESES_OPEN:
				case ST_BRACKET_OPEN:
					++$level_counter;
					if (!isset($level_entrance_counter[$level_counter])) {
						$level_entrance_counter[$level_counter] = 0;
					}
					++$level_entrance_counter[$level_counter];
					if (!isset($context_counter[$level_counter][$level_entrance_counter[$level_counter]])) {
						$context_counter[$level_counter][$level_entrance_counter[$level_counter]] = 0;
						$max_context_counter[$level_counter][$level_entrance_counter[$level_counter]] = 0;
						$touch_counter[$level_counter][$level_entrance_counter[$level_counter]] = 0;
						$align_type[$level_counter][$level_entrance_counter[$level_counter]] = 0;
						$printed_placeholder[$level_counter][$level_entrance_counter[$level_counter]][$context_counter[$level_counter][$level_entrance_counter[$level_counter]]] = 0;
					}
					++$context_counter[$level_counter][$level_entrance_counter[$level_counter]];
					$max_context_counter[$level_counter][$level_entrance_counter[$level_counter]] = max($max_context_counter[$level_counter][$level_entrance_counter[$level_counter]], $context_counter[$level_counter][$level_entrance_counter[$level_counter]]);

					$this->append_code($text);
					break;

				case ST_PARENTHESES_CLOSE:
				case ST_BRACKET_CLOSE:
					--$level_counter;
					$this->append_code($text);
					break;

				case T_OBJECT_OPERATOR:
					if (0 == $touch_counter[$level_counter][$level_entrance_counter[$level_counter]]) {
						++$touch_counter[$level_counter][$level_entrance_counter[$level_counter]];
						if ($this->has_ln_before()) {
							$align_type[$level_counter][$level_entrance_counter[$level_counter]] = self::ALIGN_WITH_INDENT;
							$this->append_code($this->get_indent(+1) . $text);
						} else {
							$align_type[$level_counter][$level_entrance_counter[$level_counter]] = self::ALIGN_WITH_SPACES;
							if (!isset($printed_placeholder[$level_counter][$level_entrance_counter[$level_counter]][$context_counter[$level_counter][$level_entrance_counter[$level_counter]]])) {
								$printed_placeholder[$level_counter][$level_entrance_counter[$level_counter]][$context_counter[$level_counter][$level_entrance_counter[$level_counter]]] = 0;
							}
							++$printed_placeholder[$level_counter][$level_entrance_counter[$level_counter]][$context_counter[$level_counter][$level_entrance_counter[$level_counter]]];
							$this->append_code(
								sprintf(
									self::ALIGNABLE_OBJOP,
									$level_counter,
									$level_entrance_counter[$level_counter],
									$context_counter[$level_counter][$level_entrance_counter[$level_counter]]
								) . $text
							);
						}
					} elseif ($this->has_ln_before() || $this->has_ln_left_token()) {
						++$touch_counter[$level_counter][$level_entrance_counter[$level_counter]];
						if (self::ALIGN_WITH_SPACES == $align_type[$level_counter][$level_entrance_counter[$level_counter]]) {
							if (!isset($printed_placeholder[$level_counter][$level_entrance_counter[$level_counter]][$context_counter[$level_counter][$level_entrance_counter[$level_counter]]])) {
								$printed_placeholder[$level_counter][$level_entrance_counter[$level_counter]][$context_counter[$level_counter][$level_entrance_counter[$level_counter]]] = 0;
							}
							++$printed_placeholder[$level_counter][$level_entrance_counter[$level_counter]][$context_counter[$level_counter][$level_entrance_counter[$level_counter]]];
							$this->append_code(
								sprintf(
									self::ALIGNABLE_OBJOP,
									$level_counter,
									$level_entrance_counter[$level_counter],
									$context_counter[$level_counter][$level_entrance_counter[$level_counter]]
								) . $text
							);
						} else {
							$this->append_code($this->get_indent(+1) . $text);
						}
					} else {
						$this->append_code($text);
					}
					break;

				case T_COMMENT:
				case T_DOC_COMMENT:
					if (
						isset($align_type[$level_counter]) &&
						isset($level_entrance_counter[$level_counter]) &&
						isset($align_type[$level_counter][$level_entrance_counter[$level_counter]]) &&
						($this->has_ln_before() || $this->has_ln_left_token())
					) {
						if (self::ALIGN_WITH_SPACES == $align_type[$level_counter][$level_entrance_counter[$level_counter]]) {
							if (!isset($printed_placeholder[$level_counter][$level_entrance_counter[$level_counter]][$context_counter[$level_counter][$level_entrance_counter[$level_counter]]])) {
								$printed_placeholder[$level_counter][$level_entrance_counter[$level_counter]][$context_counter[$level_counter][$level_entrance_counter[$level_counter]]] = 0;
							}
							++$printed_placeholder[$level_counter][$level_entrance_counter[$level_counter]][$context_counter[$level_counter][$level_entrance_counter[$level_counter]]];
							$this->append_code(
								sprintf(
									self::ALIGNABLE_OBJOP,
									$level_counter,
									$level_entrance_counter[$level_counter],
									$context_counter[$level_counter][$level_entrance_counter[$level_counter]]
								)
							);
						} elseif (self::ALIGN_WITH_INDENT == $align_type[$level_counter][$level_entrance_counter[$level_counter]]) {
							$this->append_code($this->get_indent(+1));
						}
					}
					$this->append_code($text);
					break;

				case ST_COMMA:
				case ST_SEMI_COLON:
					if (!isset($level_entrance_counter[$level_counter])) {
						$level_entrance_counter[$level_counter] = 0;
					}
					++$level_entrance_counter[$level_counter];
					$this->append_code($text);
					break;

				default:
					$this->append_code($text);
					break;
			}
		}

		$orig_code = $this->code;
		foreach ($max_context_counter as $level => $entrances) {
			foreach ($entrances as $entrance => $context) {
				for ($j = 0; $j <= $context; ++$j) {
					if (!isset($printed_placeholder[$level][$entrance][$j])) {
						continue;
					}
					if (0 === $printed_placeholder[$level][$entrance][$j]) {
						continue;
					}

					$placeholder = sprintf(self::ALIGNABLE_OBJOP, $level, $entrance, $j);
					if (1 === $printed_placeholder[$level][$entrance][$j]) {
						$this->code = str_replace($placeholder, '', $this->code);
						continue;
					}

					$lines = explode($this->new_line, $this->code);
					$lines_with_objop = [];
					$block_count = 0;

					foreach ($lines as $idx => $line) {
						if (false !== strpos($line, $placeholder)) {
							$lines_with_objop[] = $idx;
						}
					}

					$farthest = 0;
					foreach ($lines_with_objop as $idx) {
						$farthest = max($farthest, strpos($lines[$idx], $placeholder));
					}
					foreach ($lines_with_objop as $idx) {
						$line = $lines[$idx];
						$current = strpos($line, $placeholder);
						$delta = abs($farthest - $current);
						if ($delta > 0) {
							$line = str_replace($placeholder, str_repeat(' ', $delta) . $placeholder, $line);
							$lines[$idx] = $line;
						}
					}

					$this->code = str_replace($placeholder, '', implode($this->new_line, $lines));
				}
			}
		}

		return $this->code;
	}
}
