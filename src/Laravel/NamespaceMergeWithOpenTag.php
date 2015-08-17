<?php
final class NamespaceMergeWithOpenTag extends FormatterPass {
	public function candidate($source, $foundTokens) {
		if (isset($foundTokens[T_NAMESPACE])) {
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
				if ($this->leftTokenIs(T_OPEN_TAG) && !$this->rightUsefulTokenIs(T_NS_SEPARATOR)) {
					$this->rtrimAndAppendCode($this->getSpace() . $text);
					break 2;
				}
			default:
				$this->appendCode($text);
			}
		}
		while (list(, $token) = each($this->tkns)) {
			list(, $text) = $this->getToken($token);
			$this->appendCode($text);
		}
		return $this->code;
	}
}
