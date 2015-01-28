<?php
final class TwoCommandsInSameLine extends FormatterPass {
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
				case ST_SEMI_COLON:
					if ($this->leftTokenIs(ST_SEMI_COLON)) {
						break;
					}
					$this->appendCode($text);
					break;

				case T_VARIABLE:
				case T_STRING:
				case T_CONTINUE:
				case T_BREAK:
				case T_ECHO:
				case T_PRINT:
					if (!$this->hasLnBefore() && $this->leftTokenIs(ST_SEMI_COLON)) {
						$this->appendCode($this->newLine);
					}
					$this->appendCode($text);
					break;

				case ST_PARENTHESES_OPEN:
					$this->appendCode($text);
					$this->printBlock(ST_PARENTHESES_OPEN, ST_PARENTHESES_CLOSE);
					break;

				default:
					$this->appendCode($text);
					break;

			}
		}

		return $this->code;
	}
}
