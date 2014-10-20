<?php
final class AlignDoubleArrow extends FormatterPass {
	const ALIGNABLE_EQUAL = "\x2 EQUAL%d \x3";
	public function format($source) {
		$this->tkns = token_get_all($source);
		$this->code = '';

		$context_counter = 0;
		$in_bracket = 0;
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->get_token($token);
			$this->ptr = $index;
			switch ($id) {
				case T_FOREACH:
				case ST_SEMI_COLON:
				case T_ARRAY:
					++$context_counter;
					$this->append_code($text, false);
					break;

				case T_DOUBLE_ARROW:
					$this->append_code(sprintf(self::ALIGNABLE_EQUAL, $context_counter) . $text, false);
					break;

				case ST_BRACKET_OPEN:
					if ($this->is_token([T_DOUBLE_ARROW], true)) {
						++$context_counter;
					}
					$this->append_code($text, false);
					break;

				default:
					$this->append_code($text, false);
					break;
			}
		}

		for ($j = 0; $j <= $context_counter; ++$j) {
			$placeholder = sprintf(self::ALIGNABLE_EQUAL, $j);
			if (false === strpos($this->code, $placeholder)) {
				continue;
			}
			$lines = explode($this->new_line, $this->code);
			$lines_with_objop = [];
			$block_count = 0;

			foreach ($lines as $idx => $line) {
				if (substr_count($line, $placeholder) > 0) {
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
					$farthest_objop = max($farthest_objop, strpos($lines[$idx], $placeholder));
				}
				foreach ($group as $idx) {
					$line = $lines[$idx];
					$current_objop = strpos($line, $placeholder);
					$delta = abs($farthest_objop - $current_objop);
					if ($delta > 0) {
						$line = str_replace($placeholder, str_repeat(' ', $delta) . $placeholder, $line);
						$lines[$idx] = $line;
					}
				}
			}

			$this->code = str_replace($placeholder, '', implode($this->new_line, $lines));
		}
		return $this->code;
	}
}
