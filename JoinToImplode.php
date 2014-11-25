<?php
class JoinToImplode extends FormatterPass {
	public function format($source) {
		$this->tkns = token_get_all($source);

		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->get_token($token);
			$this->ptr = $index;
			if (T_STRING == $id && strtolower($text) == 'join' && !$this->is_token([T_STRING, T_DOUBLE_COLON, T_OBJECT_OPERATOR], true, $this->ignore_futile_tokens)) {
				$this->append_code('implode', false);
				continue;
			}
			$this->append_code($text, false);
		}

		return $this->code;
	}
}
