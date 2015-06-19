<?php
final class AutoSemicolon extends AdditionalPass {
	public function candidate($source, $foundTokens) {
		return true;
	}

	public function format($source) {
		$this->tkns = token_get_all($source);
		$this->code = '';
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->getToken($token);
			$this->ptr = $index;
			switch ($id) {
				case T_WHITESPACE:
					if (!$this->hasLn($text)) {
						$this->appendCode($text);
						continue;
					}

					if (
						$this->leftUsefulTokenIs([
							ST_PARENTHESES_OPEN,
							ST_PARENTHESES_CLOSE,
							ST_CURLY_OPEN,
							ST_CURLY_CLOSE,
							ST_BRACKET_OPEN,
							ST_BRACKET_CLOSE,
							ST_SEMI_COLON,
							ST_COMMA,
						]) ||
						$this->leftTokenIs([
							T_COMMENT,
							T_DOC_COMMENT,
						])
					) {
						$this->appendCode($text);
						continue;
					}
					if (
						$this->rightUsefulTokenIs([
							ST_PARENTHESES_OPEN,
							ST_PARENTHESES_CLOSE,
							ST_CURLY_OPEN,
							ST_BRACKET_OPEN,
							ST_BRACKET_CLOSE,
							ST_SEMI_COLON,
							ST_COMMA,
						]) ||
						$this->rightTokenIs([
							T_COMMENT,
							T_DOC_COMMENT,
						])
					) {
						$this->appendCode($text);
						continue;
					}
					$this->appendCode(ST_SEMI_COLON . $text);
					break;
				default:
					$this->appendCode($text);
					break;
			}
		}

		return $this->code;
	}

	/**
	 * @codeCoverageIgnore
	 */
	public function getDescription() {
		return 'Beta - Add semicolons in statements ends.';
	}

	/**
	 * @codeCoverageIgnore
	 */
	public function getExample() {
		return <<<'EOT'
<?php
// From
$a = new SomeClass()

// To
$a = new SomeClass();
?>
EOT;
	}
}
