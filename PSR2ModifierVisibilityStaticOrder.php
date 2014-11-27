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
					$this->append_code($text);
					$this->print_until_the_end_of_string();
					break;
				case T_CLASS:
					$found[] = T_CLASS;
					$this->append_code($text);
					break;
				case T_INTERFACE:
					$found[] = T_INTERFACE;
					$this->append_code($text);
					break;
				case ST_CURLY_OPEN:
				case ST_PARENTHESES_OPEN:
					$found[] = $text;
					$this->append_code($text);
					break;
				case ST_CURLY_CLOSE:
				case ST_PARENTHESES_CLOSE:
					array_pop($found);
					if (1 === sizeof($found)) {
						array_pop($found);
					}
					$this->append_code($text);
					break;
				case T_WHITESPACE:
					if (!$skip_whitespaces) {
						$this->append_code($text);
					}
					break;
				case T_PUBLIC:
				case T_PRIVATE:
				case T_PROTECTED:
					$visibility = $text;
					$skip_whitespaces = true;
					break;
				case T_FINAL:
				case T_ABSTRACT:
					if (!$this->right_token_is([T_CLASS])) {
						$final_or_abstract = $text;
						$skip_whitespaces = true;
					} else {
						$this->append_code($text);
					}
					break;
				case T_STATIC:
					if (!is_null($visibility)) {
						$static = $text;
						$skip_whitespaces = true;
					} elseif (!$this->right_token_is([T_VARIABLE, T_DOUBLE_COLON]) && !$this->left_token_is([T_NEW])) {
						$static = $text;
						$skip_whitespaces = true;
					} else {
						$this->append_code($text);
					}
					break;
				case T_VARIABLE:
					if (
						null !== $visibility ||
						null !== $final_or_abstract ||
						null !== $static
					) {
						null !== $final_or_abstract && $this->append_code($final_or_abstract . $this->get_space());
						null !== $visibility && $this->append_code($visibility . $this->get_space());
						null !== $static && $this->append_code($static . $this->get_space());
						$final_or_abstract = null;
						$visibility = null;
						$static = null;
						$skip_whitespaces = false;
					}
					$this->append_code($text);
					break;
				case T_FUNCTION:
					$has_found_class_or_interface = isset($found[0]) && (T_CLASS === $found[0] || T_INTERFACE === $found[0]);
					if (isset($found[0]) && $has_found_class_or_interface && null !== $final_or_abstract) {
						$this->append_code($final_or_abstract . $this->get_space());
					}
					if (isset($found[0]) && $has_found_class_or_interface && null !== $visibility) {
						$this->append_code($visibility . $this->get_space());
					} elseif (
						isset($found[0]) && $has_found_class_or_interface &&
						!$this->left_token_is([T_DOUBLE_ARROW, T_RETURN, ST_EQUAL, ST_COMMA, ST_PARENTHESES_OPEN])
					) {
						$this->append_code('public' . $this->get_space());
					}
					if (isset($found[0]) && $has_found_class_or_interface && null !== $static) {
						$this->append_code($static . $this->get_space());
					}
					$this->append_code($text);
					$final_or_abstract = null;
					$visibility = null;
					$static = null;
					$skip_whitespaces = false;
					break;
				default:
					$this->append_code($text);
					break;
			}
		}
		return $this->code;
	}
}