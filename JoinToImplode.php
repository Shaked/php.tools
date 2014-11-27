<?php
class JoinToImplode extends FormatterPass {
	public function format($source) {
		$this->tkns = token_get_all($source);

		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->get_token($token);
			$this->ptr = $index;
			if (T_STRING == $id && strtolower($text) == 'join' && !$this->useful_token_is([T_STRING, T_DOUBLE_COLON, T_OBJECT_OPERATOR], true)) {
				$this->append_code('implode');
				continue;
			}
			$this->append_code($text);
		}

		return $this->code;
	}
}
