<?php
final class PSR1MethodNames extends FormatterPass {
	public function format($source) {
		$this->tkns = token_get_all($source);
		$this->code = '';
		$found_method = false;
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->get_token($token);
			$this->ptr = $index;
			switch ($id) {
				case T_FUNCTION:
					$found_method = true;
					$this->append_code($text, false);
					break;
				case T_STRING:
					if ($found_method) {
						$count = 0;
						$tmp = ucwords(str_replace(['-', '_'], ' ', strtolower($text), $count));
						if ($count > 0 && '' !== trim($tmp) && '_' !== substr($text, 0, 1)) {
							$text = lcfirst(str_replace(' ', '', $tmp));
						}
						$this->append_code($text, false);

						$found_method = false;
						break;
					}
				case ST_PARENTHESES_OPEN:
					$found_method = false;
				default:
					$this->append_code($text, false);
					break;
			}
		}
		return $this->code;
	}
}
