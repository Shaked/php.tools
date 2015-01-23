<?php
final class PSR2ModifierVisibilityStaticOrder extends FormatterPass {
	public function candidate($source, $foundTokens) {
		return true;
	}
	public function format($source) {
		$this->tkns = token_get_all($source);
		$this->code = '';

		$found = [];
		$visibility = null;
		$finalOrAbstract = null;
		$static = null;
		$skipWhitespaces = false;
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
					if (!$skipWhitespaces) {
						$this->appendCode($text);
					}
					break;
				case T_PUBLIC:
				case T_PRIVATE:
				case T_PROTECTED:
					$visibility = $text;
					$skipWhitespaces = true;
					break;
				case T_FINAL:
				case T_ABSTRACT:
					if (!$this->rightTokenIs([T_CLASS])) {
						$finalOrAbstract = $text;
						$skipWhitespaces = true;
					} else {
						$this->appendCode($text);
					}
					break;
				case T_STATIC:
					if (!is_null($visibility)) {
						$static = $text;
						$skipWhitespaces = true;
					} elseif (!$this->rightTokenIs([T_VARIABLE, T_DOUBLE_COLON]) && !$this->leftTokenIs([T_NEW])) {
						$static = $text;
						$skipWhitespaces = true;
					} else {
						$this->appendCode($text);
					}
					break;
				case T_VARIABLE:
					if (
						null !== $visibility ||
						null !== $finalOrAbstract ||
						null !== $static
					) {
						null !== $finalOrAbstract && $this->appendCode($finalOrAbstract . $this->getSpace());
						null !== $visibility && $this->appendCode($visibility . $this->getSpace());
						null !== $static && $this->appendCode($static . $this->getSpace());
						$finalOrAbstract = null;
						$visibility = null;
						$static = null;
						$skipWhitespaces = false;
					}
					$this->appendCode($text);
					break;
				case T_FUNCTION:
					$has_found_class_or_interface = isset($found[0]) && (T_CLASS === $found[0] || T_INTERFACE === $found[0] || T_TRAIT === $found[0]) && $this->rightUsefulTokenIs([T_STRING, ST_REFERENCE]);
					if (isset($found[0]) && $has_found_class_or_interface && null !== $finalOrAbstract) {
						$this->appendCode($finalOrAbstract . $this->getSpace());
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
					if ('abstract' == strtolower($finalOrAbstract)) {
						$this->printUntil(ST_SEMI_COLON);
					} else {
						$this->printUntil(ST_CURLY_OPEN);
						$this->printCurlyBlock();
					}
					$finalOrAbstract = null;
					$visibility = null;
					$static = null;
					$skipWhitespaces = false;
					break;
				default:
					$this->appendCode($text);
					break;
			}
		}
		return $this->code;
	}
}