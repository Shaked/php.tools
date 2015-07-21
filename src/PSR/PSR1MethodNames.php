<?php
final class PSR1MethodNames extends FormatterPass {
	public function candidate($source, $foundTokens) {
		if (isset($foundTokens[T_FUNCTION]) || isset($foundTokens[T_STRING]) || isset($foundTokens[ST_PARENTHESES_OPEN])) {
			return true;
		}

		return false;
	}
	public function format($source) {
		$this->tkns = token_get_all($source);
		$this->code = '';
		$foundMethod = false;
		$methodReplaceList = [];
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->getToken($token);
			$this->ptr = $index;
			switch ($id) {
			case T_FUNCTION:
				$foundMethod = true;
				$this->appendCode($text);
				break;
			case T_STRING:
				if ($foundMethod) {
					$count = 0;
					$origText = $text;
					$tmp = ucwords(str_replace(['-', '_'], ' ', strtolower($text), $count));
					if ($count > 0 && '' !== trim($tmp) && '_' !== substr($text, 0, 1)) {
						$text = lcfirst(str_replace(' ', '', $tmp));
					}

					$methodReplaceList[$origText] = $text;
					$this->appendCode($text);

					$foundMethod = false;
					break;
				}
			case ST_PARENTHESES_OPEN:
				$foundMethod = false;
			default:
				$this->appendCode($text);
				break;
			}
		}

		$this->tkns = token_get_all($this->code);
		$this->code = '';
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->getToken($token);
			$this->ptr = $index;
			switch ($id) {
			case T_STRING:
				if (isset($methodReplaceList[$text]) && $this->rightUsefulTokenIs(ST_PARENTHESES_OPEN)) {

					$this->appendCode($methodReplaceList[$text]);
					break;
				}

			default:
				$this->appendCode($text);
				break;
			}
		}
		return $this->code;
	}
}
