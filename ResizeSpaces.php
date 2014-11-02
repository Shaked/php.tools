<?php
final class ResizeSpaces extends FormatterPass {
	public function format($source) {
		$source = $this->basicSpacing($source);

		return $source;
	}

	private function filterWhitespaces($source) {
		$tkns = token_get_all($source);
		$new_tokens = array_values(array_filter(
			$tkns,
			function ($token) {
				list($id, $text) = $this->get_token($token);
				// if (T_WHITESPACE === $id && 0 === substr_count($text, $this->new_line)) {
				if (T_WHITESPACE === $id && false === strpos($text, $this->new_line)) {
					return false;
				}
				return true;
			}
		));
		return $new_tokens;
	}

	private function basicSpacing($source) {
		$this->tkns = $this->filterWhitespaces($source);
		$this->code = '';
		$this->use_cache = true;

		$in_ternary_operator = false;
		$short_ternary_operator = false;

		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->get_token($token);
			$this->ptr = $index;
			$this->cache = [];
			switch ($id) {
				case '+':
				case '-':
					list($prev_id, $prev_text) = $this->inspect_token(-1);
					list($next_id, $next_text) = $this->inspect_token(+1);
					if (
						(T_LNUMBER == $prev_id || T_DNUMBER == $prev_id || T_VARIABLE == $prev_id || ST_PARENTHESES_CLOSE == $prev_id || T_STRING == $prev_id)
					 	&&
						(T_LNUMBER == $next_id || T_DNUMBER == $next_id || T_VARIABLE == $next_id || ST_PARENTHESES_CLOSE == $next_id || T_STRING == $next_id)
					) {
						$this->append_code($this->get_space() . $text . $this->get_space(), false);
					} else {
						$this->append_code($text, false);
					}
					break;
				case '*':
					list($prev_id, $prev_text) = $this->inspect_token(-1);
					list($next_id, $next_text) = $this->inspect_token(+1);
					if ('*' == $next_text) {
						$text .= '*';
						list($index, $token) = each($this->tkns);
						$this->ptr = $index;
						list($next_id, $next_text) = $this->inspect_token(+1);
					}
					if (
						T_WHITESPACE == $prev_id &&
						T_WHITESPACE != $next_id
					) {
						$this->append_code($text . $this->get_space(), false);
					} elseif (
						T_WHITESPACE != $prev_id &&
						T_WHITESPACE == $next_id
					) {
						$this->append_code($this->get_space() . $text, false);
					} elseif (
						T_WHITESPACE != $prev_id &&
						T_WHITESPACE != $next_id
					) {
						$this->append_code($this->get_space() . $text . $this->get_space(), false);
					} else {
						$this->append_code($text, false);
					}
					break;

				case '%':
				case '/':
				case T_POW:

				case ST_QUESTION:
				case ST_CONCAT:
					if (ST_QUESTION == $id) {
						$in_ternary_operator = true;
						$short_ternary_operator = $this->is_token(ST_COLON);
					}
					list($prev_id, $prev_text) = $this->inspect_token(-1);
					list($next_id, $next_text) = $this->inspect_token(+1);
					if (
						T_WHITESPACE == $prev_id &&
						T_WHITESPACE != $next_id
					) {
						$this->append_code($text . $this->get_space(!$this->is_token(ST_COLON)), false);
						break;
					} elseif (
						T_WHITESPACE != $prev_id &&
						T_WHITESPACE == $next_id
					) {
						$this->append_code($this->get_space() . $text, false);
						break;
					} elseif (
						T_WHITESPACE != $prev_id &&
						T_WHITESPACE != $next_id
					) {
						$this->append_code($this->get_space() . $text . $this->get_space(!$this->is_token(ST_COLON)), false);
						break;
					}
				case ST_COLON:
					list($prev_id, $prev_text) = $this->inspect_token(-1);
					list($next_id, $next_text) = $this->inspect_token(+1);
					if (
						$in_ternary_operator &&
						T_WHITESPACE == $prev_id &&
						T_WHITESPACE != $next_id
					) {
						$this->append_code($text . $this->get_space(), false);
						$in_ternary_operator = false;
					} elseif (
						$in_ternary_operator &&
						T_WHITESPACE != $prev_id &&
						T_WHITESPACE == $next_id
					) {
						$this->append_code($this->get_space(!$short_ternary_operator) . $text, false);
						$in_ternary_operator = false;
					} elseif (
						$in_ternary_operator &&
						T_WHITESPACE != $prev_id &&
						T_WHITESPACE != $next_id
					) {
						$this->append_code($this->get_space(!$short_ternary_operator) . $text . $this->get_space(), false);
						$in_ternary_operator = false;
					} else {
						$this->append_code($text, false);
					}
					break;

				case T_PRINT:
					$this->append_code($text . $this->get_space(!$this->is_token([ST_PARENTHESES_OPEN])), false);
					break;
				case T_ARRAY:
					if ($this->is_token([T_VARIABLE, ST_REFERENCE])) {
						$this->append_code($text . $this->get_space(), false);
						break;
					} elseif ($this->is_token(ST_PARENTHESES_OPEN)) {
						$this->append_code($text, false);
						break;
					}
				case T_STRING:
					if ($this->is_token([T_VARIABLE, T_DOUBLE_ARROW])) {
						$this->append_code($text . $this->get_space(), false);
						break;
					} else {
						$this->append_code($text, false);
						break;
					}
				case ST_CURLY_OPEN:
					if ($this->is_token([T_STRING, T_DO, ST_PARENTHESES_CLOSE], true)) {
						$this->append_code($this->get_space() . $text, false);
						break;
					} elseif ($this->is_token(ST_CURLY_CLOSE) || ($this->is_token([T_VARIABLE]) && $this->is_token([T_OBJECT_OPERATOR], true))) {
						$this->append_code($text, false);
						break;
					}
				case ST_SEMI_COLON:
					if ($this->is_token([T_VARIABLE, T_INC, T_DEC])) {
						$this->append_code($text . $this->get_space(), false);
						break;
					}
				case ST_PARENTHESES_OPEN:
				case ST_PARENTHESES_CLOSE:
					$this->append_code($text, false);
					break;
				case T_USE:
					if ($this->is_token(ST_PARENTHESES_CLOSE, true)) {
						$this->append_code($this->get_space() . $text . $this->get_space(), false);
					} elseif ($this->is_token(ST_SEMI_COLON)) {
						$this->append_code($text, false);
					} else {
						$this->append_code($text . $this->get_space(), false);
					}
					break;
				case T_RETURN:
				case T_YIELD:
				case T_ECHO:
				case T_NAMESPACE:
				case T_VAR:
				case T_NEW:
				case T_CONST:
				case T_FINAL:
				case T_CASE:
				case T_BREAK:
					$this->append_code($text . $this->get_space(!$this->is_token(ST_SEMI_COLON)), false);
					break;
				case T_WHILE:
					if ($this->is_token(ST_SEMI_COLON)) {
						$this->append_code($text . $this->get_space(), false);
						break;
					} elseif ($this->is_token(ST_CURLY_CLOSE, true) && !$this->has_ln_before()) {
						$this->append_code($this->get_space() . $text . $this->get_space(), false);
						break;
					}
				case T_DOUBLE_ARROW:
					if (T_DOUBLE_ARROW == $id && $this->is_token([T_CONSTANT_ENCAPSED_STRING, T_STRING, T_VARIABLE, T_LNUMBER, T_DNUMBER, ST_PARENTHESES_CLOSE, ST_BRACKET_CLOSE, ST_CURLY_CLOSE], true)) {
						$this->append_code($this->get_space() . $text . $this->get_space());
						break;
					}
				case T_STATIC:
					$this->append_code($text . $this->get_space(!$this->is_token([ST_SEMI_COLON, T_DOUBLE_COLON])), false);
					break;
				case T_PUBLIC:
				case T_PRIVATE:
				case T_PROTECTED:
				case T_CLASS:
				case T_TRAIT:
				case T_INTERFACE:
				case T_THROW:
				case T_GLOBAL:
				case T_ABSTRACT:
				case T_INCLUDE:
				case T_REQUIRE:
				case T_INCLUDE_ONCE:
				case T_REQUIRE_ONCE:
				case T_FUNCTION:
				case T_IF:
				case T_FOR:
				case T_FOREACH:
				case T_SWITCH:
				case T_TRY:
				case ST_COMMA:
				case T_CLONE:
				case T_CONTINUE:
					$this->append_code($text . $this->get_space(!$this->is_token(ST_SEMI_COLON)), false);
					break;
				case T_EXTENDS:
				case T_IMPLEMENTS:
				case T_INSTANCEOF:
				case T_LOGICAL_AND:
				case T_LOGICAL_OR:
				case T_LOGICAL_XOR:
				case T_AND_EQUAL:
				case T_BOOLEAN_AND:
				case T_BOOLEAN_OR:
				case T_CONCAT_EQUAL:
				case T_DIV_EQUAL:
				case T_IS_EQUAL:
				case T_IS_GREATER_OR_EQUAL:
				case T_IS_IDENTICAL:
				case T_IS_NOT_EQUAL:
				case T_IS_NOT_IDENTICAL:
				case T_IS_SMALLER_OR_EQUAL:
				case T_MINUS_EQUAL:
				case T_MOD_EQUAL:
				case T_MUL_EQUAL:
				case T_OR_EQUAL:
				case T_PLUS_EQUAL:
				case T_SL:
				case T_SL_EQUAL:
				case T_SR:
				case T_SR_EQUAL:
				case T_XOR_EQUAL:
				case ST_IS_GREATER:
				case ST_IS_SMALLER:
				case T_AS:
				case ST_EQUAL:
				case T_CATCH:
					$this->append_code($this->get_space() . $text . $this->get_space(), false);
					break;
				case T_ELSEIF:
					if (!$this->is_token(ST_CURLY_CLOSE, true)) {
						$this->append_code($text . $this->get_space(), false);
					} else {
						$this->append_code($this->get_space() . $text . $this->get_space(), false);
					}
					break;
				case T_ELSE:
					if (!$this->is_token(ST_CURLY_CLOSE, true)) {
						$this->append_code($text, false);
					} else {
						$this->append_code($this->get_space() . $text . $this->get_space(), false);
					}
					break;
				case T_ARRAY_CAST:
				case T_BOOL_CAST:
				case T_DOUBLE_CAST:
				case T_INT_CAST:
				case T_OBJECT_CAST:
				case T_STRING_CAST:
				case T_UNSET_CAST:
				case T_GOTO:
					$this->append_code($text . $this->get_space(), false);
					break;
				case ST_CONCAT:
					if (
						!$this->is_token([ST_PARENTHESES_CLOSE, ST_BRACKET_CLOSE, T_VARIABLE, T_STRING, T_CONSTANT_ENCAPSED_STRING, T_WHITESPACE], true)
					) {
						$this->append_code($this->get_space() . $text, false);
					} else {
						$this->append_code($text, false);
					}
					break;
				case ST_REFERENCE:
					if (($this->is_token([T_VARIABLE], true) && $this->is_token([T_VARIABLE])) || ($this->is_token([T_VARIABLE], true) && $this->is_token([T_STRING])) || ($this->is_token([T_STRING], true) && $this->is_token([T_STRING]))) {
						$this->append_code($this->get_space() . $text . $this->get_space(), false);
						break;
					} elseif ($this->is_token([T_STRING], true)) {
						$this->append_code($this->get_space() . $text, false);
						break;
					}
				default:
					$this->append_code($text, false);
					break;
			}
		}

		return $this->code;
	}
}
