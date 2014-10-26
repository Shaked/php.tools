<?php
final class TwoCommandsInSameLine extends FormatterPass {
	public function format($source) {
		$this->tkns = token_get_all($source);
		$this->code = '';
		$for_paren_count = -1;
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->get_token($token);
			$this->ptr = $index;

			switch ($id) {
				case T_FOR:
					$for_paren_count = 0;
					$this->append_code($text, false);
					break;

				case ST_SEMI_COLON:
					$this->append_code($text, false);
					if (!$this->has_ln_after() && $for_paren_count !== 0 && $this->is_token([T_VARIABLE, T_STRING])) {
						$for_paren_count = -1;
						$this->append_code($this->new_line, false);
					}
					break;

				case ST_PARENTHESES_OPEN:
					if ($for_paren_count !== -1) {
						$for_paren_count++;
					}

					$this->append_code($text, false);
					while (list($index, $token) = each($this->tkns)) {
						list($id, $text) = $this->get_token($token);
						$this->ptr = $index;
						$this->append_code($text, false);
						if (ST_PARENTHESES_CLOSE == $id) {
							if ($for_paren_count !== -1) {
								$for_paren_count--;
							}
							if ($for_paren_count <= 0) {
								$for_paren_count = -1;
								break;
							}
						} elseif (ST_PARENTHESES_OPEN == $id) {
							if ($for_paren_count !== -1) {
								$for_paren_count++;
							}
						}
					}
					break;
				default:
					$this->append_code($text, false);
					break;

			}
		}
		return $this->code;
	}
}
