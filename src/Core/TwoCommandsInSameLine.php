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
					if (!$this->hasLnAfter() && $this->rightTokenIs([T_VARIABLE, T_STRING, T_CONTINUE, T_BREAK, T_ECHO, T_PRINT])) {
						$this->appendCode($this->newLine);
					}
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
