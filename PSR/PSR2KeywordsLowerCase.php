<?php
final class PSR2KeywordsLowerCase extends FormatterPass {
	public function candidate($source, $found_tokens) {
		return true;
	}
	public function format($source) {
		$this->tkns = token_get_all($source);
		$this->code = '';
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->get_token($token);
			$this->ptr = $index;
			switch ($id) {
				case T_ABSTRACT:
				case T_ARRAY:
				case T_ARRAY_CAST:
				case T_AS:
				case T_BOOL_CAST:
				case T_BREAK:
				case T_CASE:
				case T_CATCH:
				case T_CLASS:
				case T_CLONE:
				case T_CONST:
				case T_CONTINUE:
				case T_DECLARE:
				case T_DEFAULT:
				case T_DO:
				case T_DOUBLE_CAST:
				case T_ECHO:
				case T_ELSE:
				case T_ELSEIF:
				case T_EMPTY:
				case T_ENDDECLARE:
				case T_ENDFOR:
				case T_ENDFOREACH:
				case T_ENDIF:
				case T_ENDSWITCH:
				case T_ENDWHILE:
				case T_EVAL:
				case T_EXIT:
				case T_EXTENDS:
				case T_FINAL:
				case T_FINALLY:
				case T_FOR:
				case T_FOREACH:
				case T_FUNCTION:
				case T_GLOBAL:
				case T_GOTO:
				case T_IF:
				case T_IMPLEMENTS:
				case T_INCLUDE:
				case T_INCLUDE_ONCE:
				case T_INSTANCEOF:
				case T_INSTEADOF:
				case T_INT_CAST:
				case T_INTERFACE:
				case T_ISSET:
				case T_LIST:
				case T_LOGICAL_AND:
				case T_LOGICAL_OR:
				case T_LOGICAL_XOR:
				case T_NAMESPACE:
				case T_NEW:
				case T_OBJECT_CAST:
				case T_PRINT:
				case T_PRIVATE:
				case T_PUBLIC:
				case T_PROTECTED:
				case T_REQUIRE:
				case T_REQUIRE_ONCE:
				case T_RETURN:
				case T_STATIC:
				case T_STRING_CAST:
				case T_SWITCH:
				case T_THROW:
				case T_TRAIT:
				case T_TRY:
				case T_UNSET:
				case T_UNSET_CAST:
				case T_USE:
				case T_VAR:
				case T_WHILE:
				case T_XOR_EQUAL:
				case T_YIELD:
					$this->append_code(strtolower($text));
					break;
				default:
					$lc_text = strtolower($text);
					if (
						!$this->left_useful_token_is([
							T_NS_SEPARATOR, T_AS, T_CLASS, T_EXTENDS, T_IMPLEMENTS, T_INSTANCEOF, T_INTERFACE, T_NEW, T_NS_SEPARATOR, T_PAAMAYIM_NEKUDOTAYIM, T_USE, T_TRAIT, T_INSTEADOF, T_CONST,
						]) &&
						!$this->right_useful_token_is([
							T_NS_SEPARATOR, T_AS, T_CLASS, T_EXTENDS, T_IMPLEMENTS, T_INSTANCEOF, T_INTERFACE, T_NEW, T_NS_SEPARATOR, T_PAAMAYIM_NEKUDOTAYIM, T_USE, T_TRAIT, T_INSTEADOF, T_CONST,
						]) &&
						('true' === $lc_text || 'false' === $lc_text || 'null' === $lc_text)) {
						$text = $lc_text;
					}
					$this->append_code($text);
					break;
			}
		}
		return $this->code;
	}
}