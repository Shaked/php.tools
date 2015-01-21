<?php
final class AlignDoubleSlashComments extends AdditionalPass {
	const ALIGNABLE_COMMENT = "\x2 COMMENT%d \x3";
	/**
	 * @codeCoverageIgnore
	 */
	public function candidate($source, $foundTokens) {
		if (isset($foundTokens[T_COMMENT])) {
			return true;
		}
		return false;
	}
	public function format($source) {
		$this->tkns = token_get_all($source);
		$this->code = '';
		$contextCounter = 0;
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->getToken($token);
			$this->ptr = $index;
			switch ($id) {
				case T_COMMENT:
					$prefix = '';
					if (substr($text, 0, 2) == '//') {
						$prefix = sprintf(self::ALIGNABLE_COMMENT, $contextCounter);
					}
					$this->appendCode($prefix . $text);

					break;

				case T_WHITESPACE:
					if ($this->hasLn($text)) {
						++$contextCounter;
					}
				default:
					$this->appendCode($text);
					break;
			}
		}

		for ($j = 0; $j <= $contextCounter; ++$j) {
			$placeholder = sprintf(self::ALIGNABLE_COMMENT, $j);
			if (false === strpos($this->code, $placeholder)) {
				continue;
			}
			if (1 === substr_count($this->code, $placeholder)) {
				$this->code = str_replace($placeholder, '', $this->code);
				continue;
			}

			$lines = explode($this->newLine, $this->code);
			$linesWithComment = [];
			$blockCount = 0;

			foreach ($lines as $idx => $line) {
				if (false !== strpos($line, $placeholder)) {
					$linesWithComment[$blockCount][] = $idx;
				} else {
					++$blockCount;
					$linesWithComment[$blockCount] = [];
				}
			}

			$i = 0;
			foreach ($linesWithComment as $group) {
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
		return 'Vertically align "//" comments.';
	}

	/**
	 * @codeCoverageIgnore
	 */
	public function getExample() {
		return <<<'EOT'
<?php
//From:
$a = 1; // Comment 1
$bb = 22;  // Comment 2
$ccc = 333;  // Comment 3

//To:
$a = 1;      // Comment 1
$bb = 22;    // Comment 2
$ccc = 333;  // Comment 3

?>
EOT;
	}
}