<?php
final class PSR2MultilineFunctionParams extends AdditionalPass {

	const LINE_BREAK = "\x2 LN \x3";

	public function candidate($source, $foundTokens) {
		if (isset($foundTokens[T_FUNCTION])) {
			return true;
		}

		return false;
	}

	public function format($source) {
		$this->tkns = token_get_all($source);
		$this->code = '';
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->getToken($token);
			$this->ptr = $index;
			switch ($id) {
			case T_FUNCTION:
				$this->appendCode($text);
				$this->printUntil(ST_PARENTHESES_OPEN);
				$this->appendCode(self::LINE_BREAK);
				$touchedComma = false;
				while (list($index, $token) = each($this->tkns)) {
					list($id, $text) = $this->getToken($token);
					$this->ptr = $index;

					if (ST_PARENTHESES_OPEN === $id) {
						$this->appendCode($text);
						$this->printUntil(ST_PARENTHESES_CLOSE);
						continue;
					} elseif (ST_BRACKET_OPEN === $id) {
						$this->appendCode($text);
						$this->printUntil(ST_BRACKET_CLOSE);
						continue;
					} elseif (ST_PARENTHESES_CLOSE === $id) {
						$this->appendCode(self::LINE_BREAK);
						$this->appendCode($text);
						break;
					}
					$this->appendCode($text);

					if (ST_COMMA === $id && !$this->hasLnAfter()) {
						$touchedComma = true;
						$this->appendCode(self::LINE_BREAK);
					}

				}
				$placeholderReplace = $this->newLine;
				if (!$touchedComma) {
					$placeholderReplace = '';
				}
				$this->code = str_replace(self::LINE_BREAK, $placeholderReplace, $this->code);
				break;
			default:
				$this->appendCode($text);
			}
		}

		return $this->code;
	}

	/**
	 * @codeCoverageIgnore
	 */
	public function getDescription() {
		return 'Break function parameters into multiple lines.';
	}

	/**
	 * @codeCoverageIgnore
	 */
	public function getExample() {
		return <<<'EOT'
<?php
// PSR2 Mode - From
function a($a, $b, $c)
{}

// To
function a(
	$a,
	$b,
	$c
) {}
?>
EOT;
	}

}
