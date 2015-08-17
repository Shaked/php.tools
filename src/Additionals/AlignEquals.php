<?php
final class AlignEquals extends AdditionalPass {
	const ALIGNABLE_EQUAL = "\x2 EQUAL%d \x3";

	public function candidate($source, $foundTokens) {
		return true;
	}

	public function format($source) {
		$this->tkns = token_get_all($source);
		$this->code = '';

		// It skips parentheses and bracket blocks, and aligns '='
		// everywhere else.
		$parenCount = 0;
		$bracketCount = 0;
		$contextCounter = 0;

		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->getToken($token);
			$this->ptr = $index;
			switch ($id) {
			case T_FUNCTION:
				++$contextCounter;
				$this->appendCode($text);
				break;
			case ST_PARENTHESES_OPEN:
				++$parenCount;
				$this->appendCode($text);
				break;
			case ST_PARENTHESES_CLOSE:
				--$parenCount;
				$this->appendCode($text);
				break;
			case ST_BRACKET_OPEN:
				++$bracketCount;
				$this->appendCode($text);
				break;
			case ST_BRACKET_CLOSE:
				--$bracketCount;
				$this->appendCode($text);
				break;
			case ST_EQUAL:
				if (!$parenCount && !$bracketCount) {
					$this->appendCode(sprintf(self::ALIGNABLE_EQUAL, $contextCounter) . $text);
					break;
				}

			default:
				$this->appendCode($text);
				break;
			}
		}

		$this->alignPlaceholders(self::ALIGNABLE_EQUAL, $contextCounter);

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