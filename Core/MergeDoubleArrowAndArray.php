<?php
final class MergeDoubleArrowAndArray extends FormatterPass {
	public function candidate($source, $found_tokens) {
		if (isset($found_tokens[T_ARRAY])) {
			return true;
		}

		return false;
	}
	public function format($source) {
		$this->tkns = token_get_all($source);
		$this->code = '';
		$in_do_while_context = 0;
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->getToken($token);
			$this->ptr = $index;
			switch ($id) {
				case T_ARRAY:
					if ($this->leftTokenIs([T_DOUBLE_ARROW])) {
						--$in_do_while_context;
						$this->rtrimAndAppendCode($text);
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