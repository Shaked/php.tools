<?php
final class DoubleToSingleQuote extends AdditionalPass {
	public function candidate($source, $foundTokens) {
		if (isset($foundTokens[T_CONSTANT_ENCAPSED_STRING])) {
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
				case T_CONSTANT_ENCAPSED_STRING:
					if ('"' == $text[0]) {
						$text[0] = '\'';
						$lastByte = strlen($text) - 1;
						$text[$lastByte] = '\'';
						$text = str_replace('\"', '"', $text);
					}
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
		return 'Convert from double to single quotes.';
	}

	/**
	 * @codeCoverageIgnore
	 */
	public function getExample() {
		return <<<'EOT'
<?php
$a = "";

$a = '';
?>
EOT;
	}
}
