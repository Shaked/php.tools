<?php
final class PSR1ClassNames extends FormatterPass {
	public function candidate($source, $found_tokens) {
		if (isset($found_tokens[T_CLASS]) || isset($found_tokens[T_STRING])) {
			return true;
		}

		return false;
	}
	public function format($source) {
		$this->tkns = token_get_all($source);
		$this->code = '';
		$found_class = false;
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->get_token($token);
			$this->ptr = $index;
			switch ($id) {
				case T_CLASS:
					$found_class = true;
					$this->append_code($text);
					break;
				case T_STRING:
					if ($found_class) {
						$count = 0;
						$tmp = ucwords(str_replace(['-', '_'], ' ', strtolower($text), $count));
						if ($count > 0) {
							$text = str_replace(' ', '', $tmp);
						}
						$this->append_code($text);

						$found_class = false;
						break;
					}
				default:
					$this->append_code($text);
					break;
			}
		}
		return $this->code;
	}
}
