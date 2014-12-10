<?php
final class ResizeSpaces extends FormatterPass {
	private function filterWhitespaces($source) {
		$tkns = token_get_all($source);

		$new_tkns = [];
		foreach ($tkns as $idx => $token) {
			if (T_WHITESPACE === $token[0] && !$this->has_ln($token[1])) {
				continue;
			}
			$new_tkns[] = $token;
		}

		return $new_tkns;
	}

	public function format($source) {
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
				case T_START_HEREDOC:
					$this->append_code($text);
					$this->print_until(ST_SEMI_COLON);
					break;
				case T_CALLABLE:
					$this->append_code($text . $this->get_space());
					break;

				case '+':
				case '-':
					list($prev_id, $prev_text) = $this->inspect_token(-1);
					list($next_id, $next_text) = $this->inspect_token(+1);
					if (
						(T_LNUMBER === $prev_id || T_DNUMBER === $prev_id || T_VARIABLE === $prev_id || ST_PARENTHESES_CLOSE === $prev_id || T_STRING === $prev_id)
						&&
						(T_LNUMBER === $next_id || T_DNUMBER === $next_id || T_VARIABLE === $next_id || ST_PARENTHESES_CLOSE === $next_id || T_STRING === $next_id)
					) {
						$this->append_code($this->get_space() . $text . $this->get_space());
					} else {
						$this->append_code($text);
					}
					break;
				case '*':
					list($prev_id, $prev_text) = $this->inspect_token(-1);
					list($next_id, $next_text) = $this->inspect_token(+1);
					if (
						T_WHITESPACE === $prev_id &&
						T_WHITESPACE !== $next_id
					) {
						$this->append_code($text . $this->get_space());
					} elseif (
						T_WHITESPACE !== $prev_id &&
						T_WHITESPACE === $next_id
					) {
						$this->append_code($this->get_space() . $text);
					} elseif (
						T_WHITESPACE !== $prev_id &&
						T_WHITESPACE !== $next_id
					) {
						$this->append_code($this->get_space() . $text . $this->get_space());
					} else {
						$this->append_code($text);
					}
					break;

				case '%':
				case '/':
				case T_POW:

				case ST_QUESTION:
				case ST_CONCAT:
					if (ST_QUESTION == $id) {
						$in_ternary_operator = true;
						$short_ternary_operator = $this->right_token_is(ST_COLON);
					}
					list($prev_id, $prev_text) = $this->inspect_token(-1);
					list($next_id, $next_text) = $this->inspect_token(+1);
					if (
						T_WHITESPACE === $prev_id &&
						T_WHITESPACE !== $next_id
					) {
						$this->append_code($text . $this->get_space(!$this->right_token_is(ST_COLON)));
						break;
					} elseif (
						T_WHITESPACE !== $prev_id &&
						T_WHITESPACE === $next_id
					) {
						$this->append_code($this->get_space() . $text);
						break;
					} elseif (
						T_WHITESPACE !== $prev_id &&
						T_WHITESPACE !== $next_id
					) {
						$this->append_code($this->get_space() . $text . $this->get_space(!$this->right_token_is(ST_COLON)));
						break;
					}
				case ST_COLON:
					list($prev_id, $prev_text) = $this->inspect_token(-1);
					list($next_id, $next_text) = $this->inspect_token(+1);
					if (
						$in_ternary_operator &&
						T_WHITESPACE === $prev_id &&
						T_WHITESPACE !== $next_id
					) {
						$this->append_code($text . $this->get_space());
						$in_ternary_operator = false;
					} elseif (
						$in_ternary_operator &&
						T_WHITESPACE !== $prev_id &&
						T_WHITESPACE === $next_id
					) {
						$this->append_code($this->get_space(!$short_ternary_operator) . $text);
						$in_ternary_operator = false;
					} elseif (
						$in_ternary_operator &&
						T_WHITESPACE !== $prev_id &&
						T_WHITESPACE !== $next_id
					) {
						$this->append_code($this->get_space(!$short_ternary_operator) . $text . $this->get_space());
						$in_ternary_operator = false;
					} else {
						$this->append_code($text);
					}
					break;

				case T_PRINT:
					$this->append_code($text . $this->get_space(!$this->right_token_is([ST_PARENTHESES_OPEN])));
					break;
				case T_ARRAY:
					if ($this->right_token_is([T_VARIABLE, ST_REFERENCE])) {
						$this->append_code($text . $this->get_space());
						break;
					} elseif ($this->right_token_is(ST_PARENTHESES_OPEN)) {
						$this->append_code($text);
						break;
					}
				case T_STRING:
					if ($this->right_token_is([T_VARIABLE, T_DOUBLE_ARROW])) {
						$this->append_code($text . $this->get_space());
						break;
					} else {
						$this->append_code($text);
						break;
					}
				case ST_CURLY_OPEN:
					if (!$this->has_ln_left_token() && $this->left_useful_token_is([T_STRING, T_DO, T_FINALLY, ST_PARENTHESES_CLOSE])) {
						$this->rtrim_and_append_code($this->get_space() . $text);
						break;
					} elseif ($this->right_token_is(ST_CURLY_CLOSE) || ($this->right_token_is([T_VARIABLE]) && $this->left_token_is([T_OBJECT_OPERATOR, ST_DOLLAR]))) {
						$this->append_code($text);
						break;
					} elseif ($this->right_token_is([T_VARIABLE, T_INC, T_DEC])) {
						$this->append_code($text . $this->get_space());
						break;
					} else {
						$this->append_code($text);
						break;
					}

				case ST_SEMI_COLON:
					if ($this->right_token_is([T_VARIABLE, T_INC, T_DEC, T_LNUMBER, T_DNUMBER])) {
						$this->append_code($text . $this->get_space());
						break;
					}
				case ST_PARENTHESES_OPEN:
					if (!$this->has_ln_left_token() && $this->left_useful_token_is([T_WHILE, T_CATCH])) {
						$this->rtrim_and_append_code($this->get_space() . $text);
					} else {
						$this->append_code($text);
					}
					break;
				case ST_PARENTHESES_CLOSE:
					$this->append_code($text);
					break;
				case T_USE:
					if ($this->left_token_is(ST_PARENTHESES_CLOSE)) {
						$this->append_code($this->get_space() . $text . $this->get_space());
					} else {
						$this->append_code($text . $this->get_space());
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
					$this->append_code($text . $this->get_space(!$this->right_token_is(ST_SEMI_COLON)));
					break;
				case T_WHILE:
					if ($this->left_token_is(ST_CURLY_CLOSE) && !$this->has_ln_before()) {
						$this->append_code($this->get_space() . $text . $this->get_space());
						break;
					}
				case T_DOUBLE_ARROW:
					if (T_DOUBLE_ARROW == $id && $this->left_token_is([T_CONSTANT_ENCAPSED_STRING, T_STRING, T_VARIABLE, T_LNUMBER, T_DNUMBER, ST_PARENTHESES_CLOSE, ST_BRACKET_CLOSE, ST_CURLY_CLOSE])) {
						$this->rtrim_and_append_code($this->get_space() . $text . $this->get_space());
						break;
					}
				case T_STATIC:
					$this->append_code($text . $this->get_space(!$this->right_token_is([ST_SEMI_COLON, T_DOUBLE_COLON, ST_PARENTHESES_OPEN])));
					break;
				case T_PUBLIC:
				case T_PRIVATE:
				case T_PROTECTED:
				case T_TRAIT:
				case T_INTERFACE:
				case T_THROW:
				case T_GLOBAL:
				case T_ABSTRACT:
				case T_INCLUDE:
				case T_REQUIRE:
				case T_INCLUDE_ONCE:
				case T_REQUIRE_ONCE:
				case T_DECLARE:
				case T_FUNCTION:
				case T_IF:
				case T_FOR:
				case T_FOREACH:
				case T_SWITCH:
				case T_TRY:
				case ST_COMMA:
				case T_CLONE:
				case T_CONTINUE:
					$this->append_code($text . $this->get_space(!$this->right_token_is(ST_SEMI_COLON)));
					break;
				case T_CLASS:
					$this->append_code($text . $this->get_space(!$this->right_token_is(ST_SEMI_COLON) && !$this->left_token_is([T_DOUBLE_COLON])));
					break;
				case T_EXTENDS:
				case T_IMPLEMENTS:
				case T_INSTANCEOF:
				case T_INSTEADOF:
				case T_AS:
					$this->append_code($this->get_space() . $text . $this->get_space());
					break;
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
				case ST_EQUAL:
					$this->append_code($this->get_space(!$this->has_ln_before()) . $text . $this->get_space());
					break;
				case T_CATCH:
				case T_FINALLY:
					if ($this->has_ln_left_token()) {
						$this->append_code($this->get_space() . $text . $this->get_space());
					} else {
						$this->rtrim_and_append_code($this->get_space() . $text . $this->get_space());
					}
					break;
				case T_ELSEIF:
					if (!$this->left_token_is(ST_CURLY_CLOSE)) {
						$this->append_code($text . $this->get_space());
					} else {
						$this->append_code($this->get_space() . $text . $this->get_space());
					}
					break;
				case T_ELSE:
					if (!$this->left_useful_token_is(ST_CURLY_CLOSE)) {
						$this->append_code($text);
					} else {
						$this->append_code($this->get_space(!$this->left_token_is([T_COMMENT, T_DOC_COMMENT])) . $text . $this->get_space());
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
					$this->append_code(str_replace([' ', "\t"], '', $text) . $this->get_space());
					break;
				case ST_REFERENCE:
					if (($this->left_token_is([T_VARIABLE]) && $this->right_token_is([T_VARIABLE])) || ($this->left_token_is([T_VARIABLE]) && $this->right_token_is([T_STRING])) || ($this->left_token_is([T_STRING]) && $this->right_token_is([T_STRING]))) {
						$this->append_code($this->get_space() . $text . $this->get_space());
						break;
					} elseif ($this->left_token_is([T_STRING])) {
						$this->append_code($this->get_space() . $text);
						break;
					}
				default:
					$this->append_code($text);
					break;
			}
		}

		return $this->code;
	}
}
