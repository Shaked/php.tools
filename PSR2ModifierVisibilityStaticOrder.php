<?php
final class PSR2ModifierVisibilityStaticOrder extends FormatterPass {
	public function format($source) {
		$this->tkns = token_get_all($source);
		$this->code = '';

		$found = [];
		$visibility = null;
		$final_or_abstract = null;
		$static = null;
		$skip_whitespaces = false;
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->get_token($token);
			$this->ptr = $index;
			switch ($id) {
				case ST_QUOTE:
					$this->append_code($text, false);
					$this->print_until_the_end_of_string();
					break;
				case T_CLASS:
					$found[] = T_CLASS;
					$this->append_code($text, false);
					break;
				case T_INTERFACE:
					$found[] = T_INTERFACE;
					$this->append_code($text, false);
					break;
				case ST_CURLY_OPEN:
				case ST_PARENTHESES_OPEN:
					$found[] = $text;
					$this->append_code($text, false);
					break;
				case ST_CURLY_CLOSE:
				case ST_PARENTHESES_CLOSE:
					array_pop($found);
					if (1 === sizeof($found)) {
						array_pop($found);
					}
					$this->append_code($text, false);
					break;
				case T_WHITESPACE:
					if (!$skip_whitespaces) {
						$this->append_code($text, false);
					}
					break;
				case T_PUBLIC:
				case T_PRIVATE:
				case T_PROTECTED:
					if (!$this->is_token([T_VARIABLE])) {
						$visibility = $text;
						$skip_whitespaces = true;
					} else {
						$this->append_code($text, false);
					}
					break;
				case T_FINAL:
				case T_ABSTRACT:
					if (!$this->is_token([T_CLASS])) {
						$final_or_abstract = $text;
						$skip_whitespaces = true;
					} else {
						$this->append_code($text, false);
					}
					break;
				case T_STATIC:
					if (!is_null($visibility)) {
						$static = $text;
						$skip_whitespaces = true;
					} elseif (!$this->is_token([T_VARIABLE, T_DOUBLE_COLON]) && !$this->is_token([T_NEW], true)) {
						$static = $text;
						$skip_whitespaces = true;
					} else {
						$this->append_code($text, false);
					}
					break;
				case T_VARIABLE:
					if (
						null !== $visibility ||
						null !== $final_or_abstract ||
						null !== $static
					) {
						null !== $final_or_abstract && $this->append_code($final_or_abstract . $this->get_space(), false);
						null !== $visibility && $this->append_code($visibility . $this->get_space(), false);
						null !== $static && $this->append_code($static . $this->get_space(), false);
						$final_or_abstract = null;
						$visibility = null;
						$static = null;
						$skip_whitespaces = false;
					}
					$this->append_code($text, false);
					break;
				case T_FUNCTION:
					$has_found_class_or_interface = isset($found[0]) && (T_CLASS === $found[0] || T_INTERFACE === $found[0]);
					if (isset($found[0]) && $has_found_class_or_interface && null !== $final_or_abstract) {
						$this->append_code($final_or_abstract . $this->get_space(), false);
					}
					if (isset($found[0]) && $has_found_class_or_interface && null !== $visibility) {
						$this->append_code($visibility . $this->get_space(), false);
					} elseif (
						isset($found[0]) && $has_found_class_or_interface &&
						!$this->is_token([T_DOUBLE_ARROW, T_RETURN], true) &&
						!$this->is_token(ST_EQUAL, true) &&
						!$this->is_token(ST_COMMA, true) &&
						!$this->is_token(ST_PARENTHESES_OPEN, true)
					) {
						$this->append_code('public' . $this->get_space(), false);
					}
					if (isset($found[0]) && $has_found_class_or_interface && null !== $static) {
						$this->append_code($static . $this->get_space(), false);
					}
					$this->append_code($text, false);
					$final_or_abstract = null;
					$visibility = null;
					$static = null;
					$skip_whitespaces = false;
					break;
				default:
					$this->append_code($text, false);
					break;
			}
		}
		return $this->code;
	}
}