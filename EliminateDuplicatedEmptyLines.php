<?php
final class EliminateDuplicatedEmptyLines extends FormatterPass {
	const ALIGNABLE_EQUAL = "\x2 EQUAL%d \x3";
	const AGGRESSIVE = 'aggressive';
	const MILD = 'mild';
	private $policiesSizes = [
		self::AGGRESSIVE => 1,
		self::MILD => 5,
	];
	private $policy = null;

	public function __construct($policy = self::AGGRESSIVE) {
		$this->policy = $this->policiesSizes[self::AGGRESSIVE];
		if (isset($this->policiesSizes[$policy])) {
			$this->policy = $this->policiesSizes[$policy];
		}
	}

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
					$text = str_replace($this->new_line, self::ALIGNABLE_EQUAL . $this->new_line, $text);
					$this->append_code($text, false);
					break;
				default:
					$this->append_code($text, false);
					break;
			}
		}

		$lines = explode($this->new_line, $this->code);
		$lines_with_objop = [];
		$block_count = 0;

		foreach ($lines as $idx => $line) {
			if (trim($line) === self::ALIGNABLE_EQUAL) {
				$lines_with_objop[$block_count][] = $idx;
			} else {
				$block_count++;
			}
		}

		foreach ($lines_with_objop as $group) {
			if (sizeof($group) <= $this->policy) {
				continue;
			}
			for ($i = 0; $i < $this->policy; $i++) {
				array_pop($group);
			}
			foreach ($group as $line_number) {
				unset($lines[$line_number]);
			}
		}

		$this->code = str_replace(self::ALIGNABLE_EQUAL, '', implode($this->new_line, $lines));

		$tkns = token_get_all($this->code);
		list($id, $text) = $this->get_token(array_pop($tkns));
		if (T_WHITESPACE === $id && '' === trim($text)) {
			$this->code = rtrim($this->code) . $this->new_line;
		}

		return $this->code;
	}
}