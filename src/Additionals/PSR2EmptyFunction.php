<?php
final class PSR2EmptyFunction extends AdditionalPass {
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
					$this->printBlock(ST_PARENTHESES_OPEN, ST_PARENTHESES_CLOSE);
					$this->printAndStopAt(ST_CURLY_OPEN);
					if ($this->rightTokenIs(ST_CURLY_CLOSE)) {
						$this->rtrimAndAppendCode($this->getSpace() . ST_CURLY_OPEN);
						$this->printAndStopAt(ST_CURLY_CLOSE);
						$this->rtrimAndAppendCode(ST_CURLY_CLOSE);
						break;
					}
					prev($this->tkns);
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
		return 'Merges in the same line of function header the body of empty functions.';
	}

	/**
	 * @codeCoverageIgnore
	 */
	public function getExample() {
		return <<<'EOT'
<?php
// PSR2 Mode - From
function a()
{}

// To
function a() {}
?>
EOT;
	}
}
