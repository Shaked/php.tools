<?php
final class PSR2AlignObjOp extends FormatterPass {
	const ALIGNABLE_TOKEN = "\x2 OBJOP%d \x3";
	public function format($source) {
		$this->tkns = token_get_all($source);
		$this->code = '';

		$context_counter = 0;
		$context_meta_count = [];
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->get_token($token);
			$this->ptr = $index;
			switch ($id) {
				case ST_SEMI_COLON:
				case T_ARRAY:
				case T_DOUBLE_ARROW:
					++$context_counter;
					$this->append_code($text, false);
					break;

				case T_OBJECT_OPERATOR:
					if (!isset($context_meta_count[$context_counter])) {
						$context_meta_count[$context_counter] = 0;
					}
					if ($this->has_ln_before() || 0 == $context_meta_count[$context_counter]) {
						$this->append_code(sprintf(self::ALIGNABLE_TOKEN, $context_counter) . $text, false);
						++$context_meta_count[$context_counter];
						break;
					}
				default:
					$this->append_code($text, false);
					break;
			}
		}

		for ($j = 0; $j <= $context_counter; ++$j) {
			$placeholder = sprintf(self::ALIGNABLE_TOKEN, $j);
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
					$lines_with_objop[$block_count][] = $idx;
				} else {
					++$block_count;
					$lines_with_objop[$block_count] = [];
				}
			}

			foreach ($lines_with_objop as $group) {
				$first_line = reset($group);
				$position_at_first_line = strpos($lines[$first_line], $placeholder);

				foreach ($group as $idx) {
					if ($idx == $first_line) {
						continue;
					}
					$line = ltrim($lines[$idx]);
					$line = str_replace($placeholder, str_repeat(' ', $position_at_first_line) . $placeholder, $line);
					$lines[$idx] = $line;
				}
			}

			$this->code = str_replace($placeholder, '', implode($this->new_line, $lines));
		}
		return $this->code;
	}
}
