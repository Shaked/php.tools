<?php
final class PSR1ClassNames extends FormatterPass {
	public function candidate($source, $foundTokens) {
		if (isset($foundTokens[T_CLASS]) || isset($foundTokens[T_STRING])) {
			return true;
		}

		return false;
	}
	public function format($source) {
		$this->tkns = token_get_all($source);
		$this->code = '';
		$foundClass = false;
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->getToken($token);
			$this->ptr = $index;
			switch ($id) {
				case T_CLASS:
					$foundClass = true;
					$this->appendCode($text);
					break;
				case T_STRING:
					if ($foundClass) {
						$count = 0;
						$tmp = ucwords(str_replace(['-', '_'], ' ', strtolower($text), $count));
						if ($count > 0) {
							$text = str_replace(' ', '', $tmp);
						}
						$this->appendCode($text);

						$foundClass = false;
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
