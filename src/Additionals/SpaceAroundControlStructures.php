<?php
final class SpaceAroundControlStructures extends AdditionalPass {

	public function candidate($source, $foundTokens) {
		if (
			isset($foundTokens[T_IF]) ||
			isset($foundTokens[T_DO]) ||
			isset($foundTokens[T_WHILE]) ||
			isset($foundTokens[T_FOR]) ||
			isset($foundTokens[T_FOREACH]) ||
			isset($foundTokens[T_SWITCH])
		) {
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
			case T_IF:
			case T_DO:
			case T_FOR:
			case T_FOREACH:
			case T_SWITCH:
				$this->appendCode($this->newLine);
				$this->appendCode($text);
				break;

			case T_WHILE:
				$this->appendCode($this->newLine);
				$this->appendCode($text);
				$this->printUntil(ST_PARENTHESES_OPEN);
				$this->printBlock(ST_PARENTHESES_OPEN, ST_PARENTHESES_CLOSE);
				if ($this->rightUsefulTokenIs(ST_SEMI_COLON)) {
					$this->printUntil(ST_SEMI_COLON);
					$this->appendCode($this->newLine);
				}
				break;

			case ST_CURLY_CLOSE:
				$this->appendCode($text);

				if (!$this->rightTokenIs([T_ENCAPSED_AND_WHITESPACE, ST_QUOTE, ST_COMMA, ST_SEMI_COLON])) {
					$this->appendCode($this->newLine);
				}
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
		return 'Add space around control structures.';
	}

	/**
	 * @codeCoverageIgnore
	 */
	public function getExample() {
		return <<<'EOT'
<?php
// From
if ($a) {

}
if ($b) {

}

// To
if ($a) {

}

if ($b) {

}
?>
EOT;
	}
}
