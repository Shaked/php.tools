<?php
final class RemoveIncludeParentheses extends FormatterPass {
	public function candidate($source, $foundTokens) {
		if (isset($foundTokens[T_INCLUDE]) || isset($foundTokens[T_REQUIRE]) || isset($foundTokens[T_INCLUDE_ONCE]) || isset($foundTokens[T_REQUIRE_ONCE])) {
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
				case ST_PARENTHESES_OPEN:
					$this->appendCode($text);
					$this->printBlock(ST_PARENTHESES_OPEN, ST_PARENTHESES_CLOSE);
					break;
				case T_INCLUDE:
				case T_REQUIRE:
				case T_INCLUDE_ONCE:
				case T_REQUIRE_ONCE:
					$this->appendCode($text . $this->getSpace());
					if (!$this->rightTokenIs(ST_PARENTHESES_OPEN)) {
						break;
					}
					$this->walkUntil(ST_PARENTHESES_OPEN);
					$this->printAndStopAt(ST_PARENTHESES_CLOSE);
					break;
				default:
					$this->appendCode($text);
					break;
			}
		}

		return $this->code;
	}
}
