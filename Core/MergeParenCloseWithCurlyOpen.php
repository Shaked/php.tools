<?php
final class MergeParenCloseWithCurlyOpen extends FormatterPass {
	public function candidate($source, $found_tokens) {
		if (isset($found_tokens[ST_CURLY_OPEN]) || isset($found_tokens[T_ELSE]) || isset($found_tokens[T_ELSEIF])) {
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
				case ST_CURLY_OPEN:
					if ($this->leftTokenIs([T_ELSE, T_STRING, ST_PARENTHESES_CLOSE])) {
						$this->rtrimAndAppendCode($text);
					} else {
						$this->appendCode($text);
					}
					break;
				case T_ELSE:
				case T_ELSEIF:
					if ($this->leftTokenIs(ST_CURLY_CLOSE)) {
						$this->rtrimAndAppendCode($text);
					} else {
						$this->appendCode($text);
					}
					break;
				default:
					$this->appendCode($text);
					break;
			}
		}
		return $this->code;
	}
}
