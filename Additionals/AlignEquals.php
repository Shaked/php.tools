<?php
final class AlignEquals extends AdditionalPass {
	const ALIGNABLE_EQUAL = "\x2 EQUAL%d \x3";
	public function candidate($source, $foundTokens) {
		return true;
	}
	public function format($source) {
		$this->tkns = token_get_all($source);
		$this->code = '';
		$paren_count = 0;
		$bracket_count = 0;
		$context_counter = 0;
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->getToken($token);
			$this->ptr = $index;
			switch ($id) {
				case T_FUNCTION:
					++$context_counter;
					$this->appendCode($text);
					break;
				case ST_PARENTHESES_OPEN:
					++$paren_count;
					$this->appendCode($text);
					break;
				case ST_PARENTHESES_CLOSE:
					--$paren_count;
					$this->appendCode($text);
					break;
				case ST_BRACKET_OPEN:
					++$bracket_count;
					$this->appendCode($text);
					break;
				case ST_BRACKET_CLOSE:
					--$bracket_count;
					$this->appendCode($text);
					break;
				case ST_EQUAL:
					if (!$paren_count && !$bracket_count) {
						$this->appendCode(sprintf(self::ALIGNABLE_EQUAL, $context_counter) . $text);
						break;
					}

				default:
					$this->appendCode($text);
					break;
			}
		}

		for ($j = 0; $j <= $context_counter; ++$j) {
			$placeholder = sprintf(self::ALIGNABLE_EQUAL, $j);
			if (false === strpos($this->code, $placeholder)) {
				continue;
			}
			if (1 === substr_count($this->code, $placeholder)) {
				$this->code = str_replace($placeholder, '', $this->code);
				continue;
			}

			$lines = explode($this->newLine, $this->code);
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

			$i = 0;
			foreach ($lines_with_objop as $group) {
				++$i;
				$farthest = 0;
				foreach ($group as $idx) {
					$farthest = max($farthest, strpos($lines[$idx], $placeholder));
				}
				foreach ($group as $idx) {
					$line = $lines[$idx];
					$current = strpos($line, $placeholder);
					$delta = abs($farthest - $current);
					if ($delta > 0) {
						$line = str_replace($placeholder, str_repeat(' ', $delta) . $placeholder, $line);
						$lines[$idx] = $line;
					}
				}
			}

			$this->code = str_replace($placeholder, '', implode($this->newLine, $lines));
		}

		return $this->code;
	}

	/**
	 * @codeCoverageIgnore
	 */
	public function getDescription() {
		return 'Vertically align "=".';
	}

	/**
	 * @codeCoverageIgnore
	 */
	public function getExample() {
		return <<<'EOT'
<?php
$a = 1;
$bb = 22;
$ccc = 333;

$a   = 1;
$bb  = 22;
$ccc = 333;

?>
EOT;
	}
}