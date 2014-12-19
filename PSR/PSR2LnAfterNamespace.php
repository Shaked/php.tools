<?php
final class PSR2LnAfterNamespace extends FormatterPass {
	public function candidate($source, $found_tokens) {
		if (isset($found_tokens[T_NAMESPACE])) {
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
				case T_NAMESPACE:
					$this->appendCode($this->getCrlf($this->leftTokenIs(ST_CURLY_CLOSE)) . $text);
					while (list($index, $token) = each($this->tkns)) {
						list($id, $text) = $this->getToken($token);
						$this->ptr = $index;
						if (ST_SEMI_COLON === $id) {
							$this->appendCode($text);
							list(, $text) = $this->inspectToken();
							if (1 === substr_count($text, $this->new_line)) {
								$this->appendCode($this->new_line);
							}
							break;
						} elseif (ST_CURLY_OPEN === $id) {
							$this->appendCode($text);
							break;
						} else {
							$this->appendCode($text);
						}
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