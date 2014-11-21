<?php
final class AlignDoubleArrow extends FormatterPass {
	const ALIGNABLE_EQUAL = "\x2 EQUAL%d.%d.%d \x3";// level.levelentracecounter.counter
	public function format($source) {
		$this->tkns = token_get_all($source);
		$this->code = '';

		$level_counter = 0;
		$level_entrance_counter = [];
		$context_counter = [];

		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->get_token($token);
			$this->ptr = $index;
			switch ($id) {
				case ST_COMMA:
					if (!$this->has_ln_after()) {
						if (!isset($level_entrance_counter[$level_counter])) {
							$level_entrance_counter[$level_counter] = 0;
						}
						if (!isset($context_counter[$level_counter][$level_entrance_counter[$level_counter]])) {
							$context_counter[$level_counter][$level_entrance_counter[$level_counter]] = 0;
						}
						++$context_counter[$level_counter][$level_entrance_counter[$level_counter]];
					}
					$this->append_code($text, false);
					break;

				case T_DOUBLE_ARROW:
					$this->append_code(
						sprintf(
							self::ALIGNABLE_EQUAL,
							$level_counter,
							$level_entrance_counter[$level_counter],
							$context_counter[$level_counter][$level_entrance_counter[$level_counter]]
						) . $text,
						false
					);
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
					}
					++$context_counter[$level_counter][$level_entrance_counter[$level_counter]];

					$this->append_code($text, false);
					break;

				case ST_PARENTHESES_CLOSE:
				case ST_BRACKET_CLOSE:
					--$level_counter;
					$this->append_code($text, false);
					break;

				default:
					$this->append_code($text, false);
					break;
			}
		}

		foreach ($context_counter as $level => $entrances) {
			foreach ($entrances as $entrance => $context) {
				for ($j = 0; $j <= $context; ++$j) {
					$placeholder = sprintf(self::ALIGNABLE_EQUAL, $level, $entrance, $j);
					if (false === strpos($this->code, $placeholder)) {
						continue;
					}
					if (1 === substr_count($this->code, $placeholder)) {
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
