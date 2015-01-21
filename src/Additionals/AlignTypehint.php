<?php
final class AlignTypehint extends AdditionalPass {
	const ALIGNABLE_TYPEHINT = "\x2 TYPEHINT%d \x3";

	public function candidate($source, $foundTokens) {
		if (isset($foundTokens[T_FUNCTION])) {
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
				case T_FUNCTION:
					$this->appendCode($text);
					$this->printUntil(ST_PARENTHESES_OPEN);
					do {
						list($id, $text) = $this->printAndStopAt([T_VARIABLE, ST_PARENTHESES_OPEN, ST_PARENTHESES_CLOSE]);
						if (ST_PARENTHESES_OPEN == $id) {
							$this->appendCode($text);
							$this->printBlock(ST_PARENTHESES_OPEN, ST_PARENTHESES_CLOSE);
							continue;
						}
						if (ST_PARENTHESES_CLOSE == $id) {
							$this->appendCode($text);
							break;
						}
						$this->appendCode(sprintf(self::ALIGNABLE_TYPEHINT, $contextCounter) . $text);
					} while (true);
					++$contextCounter;
					break;

				default:
					$this->appendCode($text);
					break;
			}
		}

		for ($j = 0; $j <= $contextCounter; ++$j) {
			$placeholder = sprintf(self::ALIGNABLE_TYPEHINT, $j);
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
function a(
	TypeA $a,
	TypeBB $bb,
	TypeCCC $ccc = array(),
	TypeDDDD $dddd,
	TypeEEEEE $eeeee
){
	noop();
}


//To:
function a(
	TypeA     $a,
	TypeBB    $bb,
	TypeCCC   $ccc = array(),
	TypeDDDD  $dddd,
	TypeEEEEE $eeeee
){
	noop();
}


?>
EOT;
	}
}