<?php
class WrongConstructorName extends FormatterPass {
	public function format($source) {
		$this->tkns = token_get_all($source);
		$this->code = '';
		$touched_namespace = false;
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->get_token($token);
			$this->ptr = $index;
			switch ($id) {
				case T_NAMESPACE:
					$touched_namespace = true;
					$this->append_code($text, false);
					break;
				case T_CLASS:
					$this->append_code($text, false);
					if ($this->is_token([T_DOUBLE_COLON], true, $this->ignore_futile_tokens)) {
						break;
					}
					if ($touched_namespace) {
						break;
					}
					$class_local_name = '';
					while (list($index, $token) = each($this->tkns)) {
						list($id, $text) = $this->get_token($token);
						$this->ptr = $index;
						$this->append_code($text, false);
						if (T_STRING == $id) {
							$class_local_name = strtolower($text);
						}
						if (T_EXTENDS == $id || T_IMPLEMENTS == $id || ST_CURLY_OPEN == $id) {
							break;
						}
					}
					if (ST_CURLY_OPEN != $id) {
						$this->print_until_the_end_of(ST_CURLY_OPEN);
					}
					$count = 1;
					while (list($index, $token) = each($this->tkns)) {
						list($id, $text) = $this->get_token($token);
						$this->ptr = $index;

						if (T_STRING == $id && $this->is_token([T_FUNCTION], true, $this->ignore_futile_tokens) && strtolower($text) == $class_local_name) {
							$text = '__construct';
						}
						$this->append_code($text, false);

						if (ST_CURLY_OPEN == $id) {
							++$count;
						}
						if (ST_CURLY_CLOSE == $id) {
							--$count;
						}
						if (0 == $count) {
							break;
						}
					}
					break;
				default:
					$this->append_code($text, false);
			}
		}

		return $this->code;
	}
}