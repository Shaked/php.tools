<?php
final class PSR2ModifierVisibilityStaticOrder extends FormatterPass {
	public function candidate($source, $found_tokens) {
		return true;
	}
	public function format($source) {
		$this->tkns = token_get_all($source);
		$this->code = '';

		$found = [];
		$visibility = null;
		$final_or_abstract = null;
		$static = null;
		$skip_whitespaces = false;
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->getToken($token);
			$this->ptr = $index;
			switch ($id) {
				case T_START_HEREDOC:
					$this->appendCode($text);
					$this->printUntil(T_END_HEREDOC);
					break;
				case ST_QUOTE:
					$this->appendCode($text);
					$this->printUntilTheEndOfString();
					break;
				case T_CLASS:
					$found[] = T_CLASS;
					$this->appendCode($text);
					break;
				case T_INTERFACE:
					$found[] = T_INTERFACE;
					$this->appendCode($text);
					break;
				case T_TRAIT:
					$found[] = T_TRAIT;
					$this->appendCode($text);
					break;
				case ST_CURLY_OPEN:
				case ST_PARENTHESES_OPEN:
					$found[] = $text;
					$this->appendCode($text);
					break;
				case ST_CURLY_CLOSE:
				case ST_PARENTHESES_CLOSE:
					array_pop($found);
					if (1 === sizeof($found)) {
						array_pop($found);
					}
					$this->appendCode($text);
					break;
				case T_WHITESPACE:
					if (!$skip_whitespaces) {
						$this->appendCode($text);
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
					if (!$this->rightTokenIs([T_CLASS])) {
						$final_or_abstract = $text;
						$skip_whitespaces = true;
					} else {
						$this->appendCode($text);
					}
					break;
				case T_STATIC:
					if (!is_null($visibility)) {
						$static = $text;
						$skip_whitespaces = true;
					} elseif (!$this->rightTokenIs([T_VARIABLE, T_DOUBLE_COLON]) && !$this->leftTokenIs([T_NEW])) {
						$static = $text;
						$skip_whitespaces = true;
					} else {
						$this->appendCode($text);
					}
					break;
				case T_VARIABLE:
					if (
						null !== $visibility ||
						null !== $final_or_abstract ||
						null !== $static
					) {
						null !== $final_or_abstract && $this->appendCode($final_or_abstract . $this->getSpace());
						null !== $visibility && $this->appendCode($visibility . $this->getSpace());
						null !== $static && $this->appendCode($static . $this->getSpace());
						$final_or_abstract = null;
						$visibility = null;
						$static = null;
						$skip_whitespaces = false;
					}
					$this->appendCode($text);
					break;
				case T_FUNCTION:
					$has_found_class_or_interface = isset($found[0]) && (T_CLASS === $found[0] || T_INTERFACE === $found[0] || T_TRAIT === $found[0]) && $this->rightUsefulTokenIs(T_STRING);
					if (isset($found[0]) && $has_found_class_or_interface && null !== $final_or_abstract) {
						$this->appendCode($final_or_abstract . $this->getSpace());
					}
					if (isset($found[0]) && $has_found_class_or_interface && null !== $visibility) {
						$this->appendCode($visibility . $this->getSpace());
					} elseif (
						isset($found[0]) && $has_found_class_or_interface &&
						!$this->leftTokenIs([T_DOUBLE_ARROW, T_RETURN, ST_EQUAL, ST_COMMA, ST_PARENTHESES_OPEN])
					) {
						$this->appendCode('public' . $this->getSpace());
					}
					if (isset($found[0]) && $has_found_class_or_interface && null !== $static) {
						$this->appendCode($static . $this->getSpace());
					}
					$this->appendCode($text);
					if ('abstract' == strtolower($final_or_abstract)) {
						$this->printUntil(ST_SEMI_COLON);
					} else {
						$this->printUntil(ST_CURLY_OPEN);
						$this->printCurlyBlock();
					}
					$final_or_abstract = null;
					$visibility = null;
					$static = null;
					$skip_whitespaces = false;
					break;
				default:
					$this->appendCode($text);
					break;
			}
		}
		return $this->code;
	}
}