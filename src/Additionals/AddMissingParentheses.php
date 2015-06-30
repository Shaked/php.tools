<?php
final class AddMissingParentheses extends AdditionalPass {
	public function candidate($source, $foundTokens) {
		if (isset($foundTokens[T_NEW])) {
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
				case T_NEW:
					$this->appendCode($text);
					list($foundId, $foundText) = $this->printAndStopAt([ST_PARENTHESES_OPEN, ST_PARENTHESES_CLOSE, T_COMMENT, T_DOC_COMMENT, ST_SEMI_COLON]);
					if (ST_PARENTHESES_OPEN != $foundId) {
						$this->appendCode('()' . $foundText);
					} elseif (ST_PARENTHESES_OPEN == $foundId) {
						$this->appendCode($foundText);
					}
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
		return 'Add extra parentheses in new instantiations.';
	}

	/**
	 * @codeCoverageIgnore
	 */
	public function getExample() {
		return <<<'EOT'
<?php
$a = new SomeClass;

$a = new SomeClass();
?>
EOT;
	}
}
