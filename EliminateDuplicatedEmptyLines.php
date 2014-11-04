<?php
final class EliminateDuplicatedEmptyLines extends FormatterPass {
	const EMPTY_LINE = "\x2 EMPTYLINE \x3";

	public function format($source) {
		$this->tkns = token_get_all($source);
		$this->code = '';
		$paren_count = 0;
		$bracket_count = 0;
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->get_token($token);
			$this->ptr = $index;
			switch ($id) {
				case T_WHITESPACE:
					$text = str_replace($this->new_line, self::EMPTY_LINE . $this->new_line, $text);
					$this->append_code($text, false);
					break;
				default:
					$this->append_code($text, false);
					break;
			}
		}

		$lines = explode($this->new_line, $this->code);
		$empty_lines = [];
		$block_count = 0;

		foreach ($lines as $idx => $line) {
			if (trim($line) === self::EMPTY_LINE) {
				$empty_lines[$block_count][] = $idx;
			} else {
				++$block_count;
				$empty_lines[$block_count] = [];
			}
		}

		foreach ($empty_lines as $group) {
			array_pop($group);
			foreach ($group as $line_number) {
				unset($lines[$line_number]);
			}
		}

		$this->code = str_replace(self::EMPTY_LINE, '', implode($this->new_line, $lines));

		list($id, $text) = $this->get_token(array_pop($this->tkns));
		if (T_WHITESPACE === $id && '' === trim($text)) {
			$this->code = rtrim($this->code) . $this->new_line;
		}

		return $this->code;
	}
}