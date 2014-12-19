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
		$found_method = false;
		$method_replace_list = [];
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->getToken($token);
			$this->ptr = $index;
			switch ($id) {
				case T_FUNCTION:
					$found_method = true;
					$this->appendCode($text);
					break;
				case T_STRING:
					if ($found_method) {
						$count = 0;
						$orig_text = $text;
						$tmp = ucwords(str_replace(['-', '_'], ' ', strtolower($text), $count));
						if ($count > 0 && '' !== trim($tmp) && '_' !== substr($text, 0, 1)) {
							$text = lcfirst(str_replace(' ', '', $tmp));
						}

						$method_replace_list[$orig_text] = $text;
						$this->appendCode($text);

						$found_method = false;
						break;
					}
				case ST_PARENTHESES_OPEN:
					$found_method = false;
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
					if (isset($method_replace_list[$text]) && $this->rightUsefulTokenIs(ST_PARENTHESES_OPEN)) {

						$this->appendCode($method_replace_list[$text]);
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
