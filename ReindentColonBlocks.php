<?php
final class ReindentColonBlocks extends FormatterPass {
	public function format($source) {
		$this->tkns = token_get_all($source);
		$this->code = '';

		$switch_level                      = 0;
		$switch_curly_count                = [];
		$switch_curly_count[$switch_level] = 0;
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->get_token($token);
			$this->ptr       = $index;
			switch ($id) {
				case ST_QUOTE:
					$this->append_code($text, false);
					$this->printUntilTheEndOfString();
					break;
				case T_SWITCH:
					$switch_level++;
					$switch_curly_count[$switch_level] = 0;
					$this->append_code($text, false);
					break;
				case ST_CURLY_OPEN:
					$switch_curly_count[$switch_level]++;
					$this->append_code($text, false);
					break;
				case ST_CURLY_CLOSE:
					$switch_curly_count[$switch_level]--;
					if (0 === $switch_curly_count[$switch_level] && $switch_level > 0) {
						$switch_level--;
					}
					$this->append_code($this->get_indent($switch_level) . $text, false);
					break;
				case T_DEFAULT:
				case T_CASE:
					$this->append_code($text, false);
					break;
				default:
					if (substr_count($text, $this->new_line) > 0 && !$this->is_token(array(T_CASE, T_DEFAULT)) && !$this->is_token(ST_CURLY_CLOSE)) {
						$this->append_code($text . $this->get_indent($switch_level), false);
					} elseif (substr_count($text, $this->new_line) > 0 && $this->is_token(array(T_CASE, T_DEFAULT))) {
						$this->append_code($text, false);
					} else {
						$this->append_code($text, false);
					}
					break;
			}
		}
		return $this->code;
	}
}