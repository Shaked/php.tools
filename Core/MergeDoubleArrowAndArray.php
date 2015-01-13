<?php
final class MergeDoubleArrowAndArray extends FormatterPass {
	public function candidate($source, $foundTokens) {
		if (isset($foundTokens[T_ARRAY])) {
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

			if (T_ARRAY === $id && $this->leftUsefulTokenIs([T_DOUBLE_ARROW])) {
				$this->rtrimAndAppendCode($text);
				continue;
			}
			$this->appendCode($text);
		}
		return $this->code;
	}
}