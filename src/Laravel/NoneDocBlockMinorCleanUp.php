<?php
final class NoneDocBlockMinorCleanUp extends FormatterPass {
	public function candidate($source, $foundTokens) {
		if (isset($foundTokens[T_COMMENT])) {
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
				case T_COMMENT:
					if (substr($text, 0, 3) != '/**' ||
						substr($text, 0, 2) != '//') {
						if (substr($text, -3) == ' */' && $this->hasLn($text)) {
							$text = substr($text, 0, -3) . '*/';
						}
						$this->appendCode($text);
						break;
					}

					list(, $prevText) = $this->inspectToken(-1);
					$counts = substr_count($prevText, "\t");
					$replacement = "\n" . str_repeat("\t", $counts);
					$text = preg_replace('/\n\s*/', $replacement, $text);
					if (substr($text, -3) == ' */' && $this->hasLn($text)) {
						$text = substr($text, 0, -3) . '*/';
					}
					$this->appendCode($text);

					break;
				default:
					$this->appendCode($text);
			}
		}
		return $this->code;
	}
}
