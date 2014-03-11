<?php
# Copyright (c) 2014, Carlos C
# All rights reserved.
#
# Redistribution and use in source and binary forms, with or without modification, are permitted provided that the following conditions are met:
#
# 1. Redistributions of source code must retain the above copyright notice, this list of conditions and the following disclaimer.
#
# 2. Redistributions in binary form must reproduce the above copyright notice, this list of conditions and the following disclaimer in the documentation and/or other materials provided with the distribution.
#
# 3. Neither the name of the copyright holder nor the names of its contributors may be used to endorse or promote products derived from this software without specific prior written permission.
#
# THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT HOLDER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
define("ST_AT", "@");
define("ST_BRACKET_CLOSE", "]");
define("ST_BRACKET_OPEN", "[");
define("ST_COLON", ":");
define("ST_COMMA", ",");
define("ST_CONCAT", ".");
define("ST_CURLY_CLOSE", "}");
define("ST_CURLY_OPEN", "{");
define("ST_DIVIDE", "/");
define("ST_DOLLAR", "$");
define("ST_EQUAL", "=");
define("ST_EXCLAMATION", "!");
define("ST_IS_GREATER", ">");
define("ST_IS_SMALLER", "<");
define("ST_MINUS", "-");
define("ST_MODULUS", "%");
define("ST_PARENTHESES_CLOSE", ")");
define("ST_PARENTHESES_OPEN", "(");
define("ST_PLUS", "+");
define("ST_QUESTION", "?");
define("ST_QUOTE", '"');
define("ST_REFERENCE", "&");
define("ST_SEMI_COLON", ";");
define("ST_TIMES", "*");
$fmt = new CodeFormatter();
if (!isset($argv[1])) {
	exit();
}
echo $fmt->formatCode(file_get_contents($argv[1]));
class CodeFormatter {
	private $options = array(
		"ADD_MISSING_BRACES" => true,
		"ALIGN_ARRAY_ASSIGNMENT" => true,
		"ALIGN_VAR_ASSIGNMENT" => true,
		"ELSE_ALONG_CURLY" => true,
		"INDENT_CASE" => true,
		"KEEP_REDUNDANT_LINES" => false,
		"LINE_AFTER_BREAK" => false,
		"LINE_AFTER_COMMENT" => false,
		"LINE_AFTER_COMMENT_MULTI" => false,
		"LINE_AFTER_CURLY_FUNCTION" => false,
		"LINE_BEFORE_ARRAY" => false,
		"LINE_BEFORE_COMMENT" => false,
		"LINE_BEFORE_COMMENT_MULTI" => false,
		"LINE_BEFORE_CURLY" => false,
		"LINE_BEFORE_CURLY_FUNCTION" => false,
		"LINE_BEFORE_FUNCTION" => false,
		"SPACE_AFTER_COMMA" => true,
		"SPACE_AFTER_IF" => true,
		"SPACE_AROUND_ARITHMETIC" => false,
		"SPACE_AROUND_ASSIGNMENT" => true,
		"SPACE_AROUND_COLON_QUESTION" => false,
		"SPACE_AROUND_COMPARISON" => false,
		"SPACE_AROUND_CONCAT" => false,
		"SPACE_AROUND_DOUBLE_ARROW" => true,
		"SPACE_AROUND_DOUBLE_COLON" => true,
		"SPACE_AROUND_LOGICAL" => true,
		"SPACE_AROUND_OBJ_OPERATOR" => false,
		"SPACE_INSIDE_FOR" => false,
		"SPACE_INSIDE_PARENTHESES" => false,
		"SPACE_OUTSIDE_PARENTHESES" => false,
		"VERTICAL_ARRAY" => true,
		"VERTICAL_CONCAT" => false,
	);
	private $indent_size = 1;
	private $indent_char = "\t";
	private $block_size = 1;
	private $new_line = "\n";
	private $indent = 0;
	private $for_idx = 0;
	private $code = '';
	private $ptr = 0;
	private $tkns = 0;
	public function formatCode($source = '') {
		$this->tkns = token_get_all($source);
		$in_for = false;
		$in_break = false;
		$in_function = false;
		$in_concat = false;
		$space_after = false;
		$curly_open = false;
		$space_after_bracket = false;
		$array_level = 0;
		$arr_parentheses = array();
		$switch_level = 0;
		$if_level = 0;
		$if_pending = 0;
		$else_pending = false;
		$if_parentheses = array();
		$switch_arr = array();
		$halt_parser = false;
		$after = false;
		foreach ($this->tkns as $index => $token) {
			list($id, $text) = $this->get_token($token);
			$this->ptr = $index;
			if ($halt_parser && $id!=ST_QUOTE) {
				$this->append_code($text, false);
				continue;
			}
			switch ($id) {
				case ST_CURLY_OPEN:
					$condition = $in_function?$this->options["LINE_BEFORE_CURLY_FUNCTION"]:$this->options["LINE_BEFORE_CURLY"];
					$this->set_indent(+1);
					$this->append_code((!$condition?' ':$this->get_crlf_indent(false,-1)).$text.$this->get_crlf($this->options["LINE_AFTER_CURLY_FUNCTION"] && $in_function && !$this->is_token_lf()).$this->get_crlf_indent());
					$in_function = false;
					break;
				case ST_CURLY_CLOSE:
					if ($curly_open) {
						$curly_open = false;
						$this->append_code(trim($text));
					} else {
						if (($in_break || $this->is_token(ST_CURLY_CLOSE)) && $switch_level>0 && $switch_arr["l".$switch_level]>0 && $switch_arr["s".$switch_level]==$this->indent-2) {
							if ($this->options["INDENT_CASE"]) {
								$this->set_indent(-1);
							}
							$switch_arr["l".$switch_level]--;
							$switch_arr["c".$switch_level]--;
						}
						while ($switch_level>0 && $switch_arr["l".$switch_level]==0 && $this->options["INDENT_CASE"]) {
							unset($switch_arr["s".$switch_level]);
							unset($switch_arr["c".$switch_level]);
							unset($switch_arr["l".$switch_level]);
							$switch_level--;
							if ($switch_level>0) {
								$switch_arr["l".$switch_level]--;
							}
							$this->set_indent(-1);
							$this->append_code($this->get_crlf_indent().$text.$this->get_crlf_indent());
							$text = '';
						}
						if ($text!='') {
							$this->set_indent(-1);
							$this->append_code($this->get_crlf_indent().$text.$this->get_crlf_indent());
						}
					}
					break;
				case ST_SEMI_COLON:
					if (($in_break || $this->is_token(ST_CURLY_CLOSE)) && $switch_level>0 && $switch_arr["l".$switch_level]>0 && $switch_arr["s".$switch_level]==$this->indent-2) {
						if ($this->options["INDENT_CASE"]) {
							$this->set_indent(-1);
						}
						$switch_arr["l".$switch_level]--;
						$switch_arr["c".$switch_level]--;
					}
					if ($in_concat) {
						$this->set_indent(-1);
						$in_concat = false;
					}
					$this->append_code($text.$this->get_crlf($this->options["LINE_AFTER_BREAK"] && $in_break).$this->get_crlf_indent($in_for));
					while ($if_pending>0) {
						$text = $this->options["ADD_MISSING_BRACES"]?"}":"";
						$this->set_indent(-1);
						if ($text!="") {
							$this->append_code($this->get_crlf_indent().$text.$this->get_crlf_indent());
						} else {
							$this->append_code($this->get_crlf_indent());
						}
						$if_pending--;
						if ($this->is_token(array(T_ELSE, T_ELSEIF))) {
							break;
						}
					}
					if ($this->for_idx==0) {
						$in_for = false;
					}
					$in_break = false;
					$in_function = false;
					break;
				case ST_BRACKET_OPEN:
					$this->append_code($this->get_space($space_after_bracket).$text);
					$space_after_bracket = false;
					break;
				case ST_BRACKET_CLOSE:
					$this->append_code($text);
					break;
				case ST_PARENTHESES_OPEN:
					if ($if_level>0) {
						$if_parentheses["i".$if_level]++;
					}
					if ($array_level>0) {
						$arr_parentheses["i".$array_level]++;
						if ($this->is_token(array(T_ARRAY), true) && !$this->is_token(ST_PARENTHESES_CLOSE)) {
							$this->set_indent(+1);
							$this->append_code((!$this->options["LINE_BEFORE_ARRAY"]?'':$this->get_crlf_indent(false,-1)).$text.$this->get_crlf_indent());
							break;
						}
					}
					$this->append_code($this->get_space($this->options["SPACE_OUTSIDE_PARENTHESES"] || $space_after).$text.$this->get_space($this->options["SPACE_INSIDE_PARENTHESES"]));
					$space_after = false;
					break;
				case ST_PARENTHESES_CLOSE:
					if ($array_level>0) {
						$arr_parentheses["i".$array_level]--;
						if ($arr_parentheses["i".$array_level]==0) {
							$comma = substr(trim($this->code),-1)!="," && $this->options['VERTICAL_ARRAY']?",":"";
							$this->set_indent(-1);
							$this->append_code($comma.$this->get_crlf_indent().$text.$this->get_crlf_indent());
							unset($arr_parentheses["i".$array_level]);
							$array_level--;
							break;
						}
					}
					$this->append_code($this->get_space($this->options["SPACE_INSIDE_PARENTHESES"]).$text.$this->get_space($this->options["SPACE_OUTSIDE_PARENTHESES"]));
					if ($if_level>0) {
						$if_parentheses["i".$if_level]--;
						if ($if_parentheses["i".$if_level]==0) {
							if (!$this->is_token(ST_CURLY_OPEN) && !$this->is_token(ST_SEMI_COLON)) {
								$text = $this->options["ADD_MISSING_BRACES"]?"{":"";
								$this->set_indent(+1);
								$this->append_code((!$this->options["LINE_BEFORE_CURLY"] || $text==""?' ':$this->get_crlf_indent(false,-1)).$text.$this->get_crlf_indent());
								$if_pending++;
							}
							unset($if_parentheses["i".$if_level]);
							$if_level--;
						}
					}
					break;
				case ST_COMMA:
					if ($array_level>0) {
						$this->append_code($text.$this->get_crlf_indent($in_for));
					} else {
						$this->append_code($text.$this->get_space($this->options["SPACE_AFTER_COMMA"]));
						if ($this->is_token(ST_PARENTHESES_OPEN)) {
							$space_after = $this->options["SPACE_AFTER_COMMA"];
						}
					}
					break;
				case ST_CONCAT:
					$condition = $this->options["SPACE_AROUND_CONCAT"];
					if ($this->is_token(ST_PARENTHESES_OPEN)) {
						$space_after = $condition;
					}
					if ($this->options["VERTICAL_CONCAT"]) {
						if (!$in_concat) {
							$in_concat = true;
							$this->set_indent(+1);
						}
						$this->append_code($this->get_space($condition).$text.$this->get_crlf_indent());
					} else {
						$this->append_code($this->get_space($condition).$text.$this->get_space($condition));
					}
					break;
				case T_CONCAT_EQUAL:
				case T_DIV_EQUAL:
				case T_MINUS_EQUAL:
				case T_PLUS_EQUAL:
				case T_MOD_EQUAL:
				case T_MUL_EQUAL:
				case T_AND_EQUAL:
				case T_OR_EQUAL:
				case T_XOR_EQUAL:
				case T_SL_EQUAL:
				case T_SR_EQUAL:
				case ST_EQUAL:
					$condition = $this->options["SPACE_AROUND_ASSIGNMENT"];
					if ($this->is_token(ST_PARENTHESES_OPEN)) {
						$space_after = $condition;
					}
					if ($this->is_token(ST_BRACKET_OPEN)) {
						$space_after_bracket = $condition;
					}
					$this->append_code($this->get_space($condition).$text.$this->get_space($condition));
					break;
				case T_IS_EQUAL:
				case ST_IS_GREATER:
				case T_IS_GREATER_OR_EQUAL:
				case T_IS_SMALLER_OR_EQUAL:
				case ST_IS_SMALLER:
				case T_IS_IDENTICAL:
				case T_IS_NOT_EQUAL:
				case T_IS_NOT_IDENTICAL:
					$condition = $this->options["SPACE_AROUND_COMPARISON"];
					if ($this->is_token(ST_PARENTHESES_OPEN)) {
						$space_after = $condition;
					}
					$this->append_code($this->get_space($condition).$text.$this->get_space($condition));
					break;
				case T_BOOLEAN_AND:
				case T_BOOLEAN_OR:
				case T_LOGICAL_AND:
				case T_LOGICAL_OR:
				case T_LOGICAL_XOR:
				case T_SL:
				case T_SR:
					$condition = $this->options["SPACE_AROUND_LOGICAL"];
					if ($this->is_token(ST_PARENTHESES_OPEN)) {
						$space_after = $condition;
					}
					$this->append_code($this->get_space($condition).$text.$this->get_space($condition));
					break;
				case T_DOUBLE_COLON:
					$condition = $this->options["SPACE_AROUND_DOUBLE_COLON"];
					$this->append_code($this->get_space($condition).$text.$this->get_space($condition));
					break;
				case ST_COLON:
					if ($switch_level>0 && $switch_arr["l".$switch_level]>0 && $switch_arr["c".$switch_level]<$switch_arr["l".$switch_level]) {
						$switch_arr["c".$switch_level]++;
						if ($this->options["INDENT_CASE"]) {
							$this->set_indent(+1);
						}
						$this->append_code($text.$this->get_crlf_indent());
					} else {
						$condition = $this->options["SPACE_AROUND_COLON_QUESTION"];
						if ($this->is_token(ST_PARENTHESES_OPEN)) {
							$space_after = $condition;
						}
						$this->append_code($this->get_space($condition).$text.$this->get_space($condition));
					}
					if (($in_break || $this->is_token(ST_CURLY_CLOSE)) && $switch_level>0 && $switch_arr["l".$switch_level]>0) {
						if ($this->options["INDENT_CASE"]) {
							$this->set_indent(-1);
						}
						$switch_arr["l".$switch_level]--;
						$switch_arr["c".$switch_level]--;
					}
					break;
				case ST_QUESTION:
					$condition = $this->options["SPACE_AROUND_COLON_QUESTION"];
					if ($this->is_token(ST_PARENTHESES_OPEN)) {
						$space_after = $condition;
					}
					$this->append_code($this->get_space($condition).$text.$this->get_space($condition));
					break;
				case T_DOUBLE_ARROW:
					$condition = $this->options["SPACE_AROUND_DOUBLE_ARROW"];
					if ($this->is_token(ST_PARENTHESES_OPEN)) {
						$space_after = $condition;
					}
					$this->append_code($this->get_space($condition).$text.$this->get_space($condition));
					break;
				case ST_MINUS:
				case ST_PLUS:
				case ST_TIMES:
				case ST_DIVIDE:
				case ST_MODULUS:
					$condition = $this->options["SPACE_AROUND_ARITHMETIC"];
					if ($this->is_token(ST_PARENTHESES_OPEN)) {
						$space_after = $condition;
					}
					$this->append_code($this->get_space($condition).$text.$this->get_space($condition));
					break;
				case T_OBJECT_OPERATOR:
					$condition = $this->options["SPACE_AROUND_OBJ_OPERATOR"];
					$this->append_code($this->get_space($condition).$text.$this->get_space($condition));
					break;
				case T_FOR:
					$in_for = true;
				case T_FOREACH:
				case T_WHILE:
				case T_DO:
				case T_IF:
				case T_SWITCH:
					$space_after = $this->options["SPACE_AFTER_IF"];
					$this->append_code($text.$this->get_space($space_after), false);
					if ($id==T_SWITCH) {
						$switch_level++;
						$switch_arr["s".$switch_level] = $this->indent;
						$switch_arr["l".$switch_level] = 0;
						$switch_arr["c".$switch_level] = 0;
					}
					$if_level++;
					$if_parentheses["i".$if_level] = 0;
					break;
				case T_FUNCTION:
				case T_CLASS:
				case T_INTERFACE:
				case T_FINAL:
				case T_ABSTRACT:
				case T_PUBLIC:
				case T_PROTECTED:
				case T_PRIVATE:
					if (!$in_function) {
						if ($this->options["LINE_BEFORE_FUNCTION"]) {
							$this->append_code($this->get_crlf($after || !$this->is_token(array(T_COMMENT, T_DOC_COMMENT), true)).$this->get_crlf_indent().$text.$this->get_space());
							$after = false;
						} else {
							$this->append_code($text.$this->get_space(), false);
						}
						$in_function = true;
					} else {
						$this->append_code($this->get_space().$text.$this->get_space());
					}
					break;
				case T_START_HEREDOC:
					$this->append_code($this->get_space($this->options["SPACE_AROUND_ASSIGNMENT"]).$text);
					break;
				case T_END_HEREDOC:
					$this->append_code($this->get_crlf().$text.$this->get_crlf_indent());
					break;
				case T_COMMENT:
				case T_DOC_COMMENT:
					if (is_array($this->tkns[$index-1])) {
						$pad = $this->tkns[$index-1][1];
						$i = strlen($pad)-1;
						$k = "";
						while (substr($pad, $i, 1)!="\n" && substr($pad, $i, 1)!="\r" && $i>=0) {
							$k .= substr($pad, $i--, 1);
						}
						$text = preg_replace("/\r?\n$k/", $this->get_crlf_indent(), $text);
					}
					$after = $id==(T_COMMENT && preg_match("/^\/\//", $text))?$this->options["LINE_AFTER_COMMENT"]:$this->options["LINE_AFTER_COMMENT_MULTI"];
					$before = $id==(T_COMMENT && preg_match("/^\/\//", $text))?$this->options["LINE_BEFORE_COMMENT"]:$this->options["LINE_BEFORE_COMMENT_MULTI"];
					if ($prev = $this->is_token(ST_CURLY_OPEN, true, $index, true)) {
						$before = $before && !$this->is_token_lf(true, $prev);
					}
					$after = $after && (!$this->is_token_lf() || !$this->options["KEEP_REDUNDANT_LINES"]);
					if ($before) {
						$this->append_code($this->get_crlf(!$this->is_token(array(T_COMMENT), true)).$this->get_crlf_indent().trim($text).$this->get_crlf($after).$this->get_crlf_indent());
					} else {
						$this->append_code(trim($text).$this->get_crlf($after).$this->get_crlf_indent(), false);
					}
					break;
				case T_DOLLAR_OPEN_CURLY_BRACES:
				case T_CURLY_OPEN:
					$curly_open = true;
				case T_NUM_STRING:
				case T_BAD_CHARACTER:
					$this->append_code(trim($text));
					break;
				case T_EXTENDS:
				case T_IMPLEMENTS:
				case T_INSTANCEOF:
				case T_AS:
					$this->append_code($this->get_space().$text.$this->get_space());
					break;
				case ST_DOLLAR:
				case ST_REFERENCE:
				case T_INC:
				case T_DEC:
					$this->append_code(trim($text), false);
					break;
				case T_WHITESPACE:
					$redundant = "";
					if ($this->options["KEEP_REDUNDANT_LINES"]) {
						$lines = preg_match_all("/\r?\n/", $text, $matches);
						$lines = $lines>0?$lines-1:0;
						$redundant = $lines>0?str_repeat($this->new_line, $lines):"";
						$current_indent = $this->get_indent();
						if (substr($this->code, strlen($current_indent)*-1)==$current_indent && $lines>0) {
							$redundant .= $current_indent;
						}
					}
					if ($this->is_token(array(T_OPEN_TAG), true)) {
						$this->append_code($text, false);
					} else {
						$this->append_code($redundant.trim($text), false);
					}
					break;
				case ST_QUOTE:
					$this->append_code($text, false);
					$halt_parser = !$halt_parser;
					break;
				case T_ARRAY:
					if ($this->options["VERTICAL_ARRAY"]) {
						$next = $this->is_token(array(T_DOUBLE_ARROW), true);
						$next |= $this->is_token(ST_EQUAL, true);
						$next |= $array_level>0;
						if ($next) {
							$next = $this->is_token(ST_PARENTHESES_OPEN, false, $index, true);
							if ($next) {
								$next = !$this->is_token(ST_PARENTHESES_CLOSE, false, $next);
							}
						}
						if ($next) {
							$array_level++;
							$arr_parentheses["i".$array_level] = 0;
						}
					}
				case T_STRING:
				case T_CONSTANT_ENCAPSED_STRING:
				case T_ENCAPSED_AND_WHITESPACE:
				case T_VARIABLE:
				case T_CHARACTER:
				case T_STRING_VARNAME:
				case ST_AT:
				case ST_EXCLAMATION:
				case T_OPEN_TAG:
				case T_OPEN_TAG_WITH_ECHO:
				case T_NS_SEPARATOR:
					$this->append_code($text, false);
					break;
				case T_CLOSE_TAG:
					$this->append_code($text, !$this->is_token_lf(true));
					break;
				case T_CASE:
				case T_DEFAULT:
					if ($switch_arr["l".$switch_level]>0 && $this->options["INDENT_CASE"]) {
						$switch_arr["c".$switch_level]--;
						$this->set_indent(-1);
						$this->append_code($this->get_crlf_indent().$text.$this->get_space());
					} else {
						$switch_arr["l".$switch_level]++;
						$this->append_code($text.$this->get_space(), false);
					}
					break;
				case T_INLINE_HTML:
					$this->append_code($text, false);
					break;
				case T_BREAK:
				case T_CONTINUE:
					$in_break = true;
				case T_VAR:
				case T_GLOBAL:
				case T_STATIC:
				case T_CONST:
				case T_ECHO:
				case T_PRINT:
				case T_INCLUDE:
				case T_INCLUDE_ONCE:
				case T_REQUIRE:
				case T_REQUIRE_ONCE:
				case T_DECLARE:
				case T_EMPTY:
				case T_ISSET:
				case T_UNSET:
				case T_DNUMBER:
				case T_LNUMBER:
				case T_RETURN:
				case T_EVAL:
				case T_EXIT:
				case T_LIST:
				case T_CLONE:
				case T_NEW:
				case T_FUNC_C:
				case T_CLASS_C:
				case T_FILE:
				case T_LINE:
					$this->append_code($text.$this->get_space(), false);
					break;
				case T_ELSEIF:
					$space_after = $this->options["SPACE_AFTER_IF"];
					$added_braces = $this->is_token(ST_SEMI_COLON, true) && $this->options["ADD_MISSING_BRACES"];
					$condition = $this->options['ELSE_ALONG_CURLY'] && ($this->is_token(ST_CURLY_CLOSE, true) || $added_braces);
					$this->append_code($this->get_space($condition).$text.$this->get_space($space_after), $condition);
					$if_level++;
					$if_parentheses["i".$if_level] = 0;
					break;
				case T_ELSE:
					$added_braces = $this->is_token(ST_SEMI_COLON, true) && $this->options["ADD_MISSING_BRACES"];
					$condition = $this->options['ELSE_ALONG_CURLY'] && ($this->is_token(ST_CURLY_CLOSE, true) || $added_braces);
					$this->append_code($this->get_space($condition).$text, $condition);
					if (!$this->is_token(ST_CURLY_OPEN) && !$this->is_token(array(T_IF))) {
						$text = $this->options["ADD_MISSING_BRACES"]?"{":"";
						$this->set_indent(+1);
						$this->append_code((!$this->options["LINE_BEFORE_CURLY"] || $text==""?' ':$this->get_crlf_indent(false,-1)).$text.$this->get_crlf_indent());
						$if_pending++;
					}
					break;
				default:
					$this->append_code($text.' ', false);
					break;
			}
		}
		return $this->align_operators();
	}
	private function get_token($token) {
		if (is_string($token)) {
			return array($token, $token);
		} else {
			return $token;
		}
	}
	private function append_code($code = "", $trim = true) {
		if ($trim) {
			$this->code = rtrim($this->code).$code;
		} else {
			$this->code .= $code;
		}
	}
	private function get_crlf_indent($in_for = false, $increment = 0) {
		if ($in_for) {
			$this->for_idx++;
			if ($this->for_idx>2) {
				$this->for_idx = 0;
			}
		}
		if ($this->for_idx==0 || !$in_for) {
			return $this->get_crlf().$this->get_indent($increment);
		} else {
			return $this->get_space($this->options["SPACE_INSIDE_FOR"]);
		}
	}
	private function get_crlf($true = true) {
		return $true?$this->new_line:"";
	}
	private function get_space($true = true) {
		return $true?" ":"";
	}
	private function get_indent($increment = 0) {
		return str_repeat($this->indent_char, ($this->indent+$increment)*$this->indent_size);
	}
	private function set_indent($increment) {
		$this->indent += $increment;
		if ($this->indent<0) {
			$this->indent = 0;
		}
	}
	private function is_token($token, $prev = false, $i = 99999, $idx = false) {
		if ($i==99999) {
			$i = $this->ptr;
		}
		if ($prev) {
			while (--$i>=0 && is_array($this->tkns[$i]) && $this->tkns[$i][0]==T_WHITESPACE);
		} else {
			while (++$i<sizeof($this->tkns)-1 && is_array($this->tkns[$i]) && $this->tkns[$i][0]==T_WHITESPACE);
		}
		if (isset($this->tkns[$i]) && is_string($this->tkns[$i]) && $this->tkns[$i]==$token) {
			return $idx?$i:true;
		} elseif (is_array($token) && is_array($this->tkns[$i])) {
			if (in_array($this->tkns[$i][0], $token)) {
				return $idx?$i:true;
			} elseif ($prev && $this->tkns[$i][0]==T_OPEN_TAG) {
				return $idx?$i:true;
			}
		}
		return false;
	}
	private function is_token_lf($prev = false, $i = 99999) {
		if ($i==99999) {
			$i = $this->ptr;
		}
		if ($prev) {
			$count = 0;
			while (--$i>=0 && is_array($this->tkns[$i]) && $this->tkns[$i][0]==T_WHITESPACE && strpos($this->tkns[$i][1], "\n")===false);
		} else {
			$count = 1;
			while (++$i<sizeof($this->tkns) && is_array($this->tkns[$i]) && $this->tkns[$i][0]==T_WHITESPACE && strpos($this->tkns[$i][1], "\n")===false);
		}
		if (is_array($this->tkns[$i]) && preg_match_all("/\r?\n/", $this->tkns[$i][1], $matches)>$count) {
			return true;
		}
		return false;
	}
	private function pad_operators($found) {
		global $quotes;
		$pad_size = 0;
		$result = "";
		$source = explode($this->new_line, $found[0]);
		$position = array();
		array_pop($source);
		foreach ($source as $k => $line) {
			if (preg_match("/'quote[0-9]+'/", $line)) {
				preg_match_all("/'quote([0-9]+)'/", $line, $holders);
				for ($i = 0;$i<sizeof($holders[1]);$i++) {
					$line = preg_replace("/".$holders[0][$i]."/", str_repeat(" ", strlen($quotes[0][$holders[1][$i]])), $line);
				}
			}
			if (strpos($line, "=")>$pad_size) {
				$pad_size = strpos($line, "=");
			}
			$position[$k] = strpos($line, "=");
		}
		foreach ($source as $k => $line) {
			$padding = str_repeat(" ", $pad_size-$position[$k]);
			$padded = preg_replace("/^([^=]+?)([\.\+\*\/\-\%]?=)(.*)$/", "\\1{$padding}\\2\\3".$this->new_line, $line);
			$result .= $padded;
		}
		return $result;
	}
	private function parse_block($blocks) {
		global $quotes;
		$pad_chars = "";
		$holders = array();
		if ($this->options['ALIGN_ARRAY_ASSIGNMENT']) {
			$pad_chars .= ",";
		}
		if ($this->options['ALIGN_VAR_ASSIGNMENT']) {
			$pad_chars .= ";";
		}
		$php_code = $blocks[0];
		preg_match_all("/\/\*.*?\*\/|\/\/[^\n]*|#[^\n]|([\"'])[^\\\\]*?(?:\\\\.[^\\\\]*?)*?\\1/s", $php_code, $quotes);
		$quotes[0] = array_values(array_unique($quotes[0]));
		for ($i = 0;$i<sizeof($quotes[0]);$i++) {
			$patterns[] = "/".preg_quote($quotes[0][$i], '/')."/";
			$holders[] = "'quote$i'";
			$quotes[0][$i] = str_replace('\\\\', '\\\\\\\\', $quotes[0][$i]);
		}
		if (sizeof($holders)>0) {
			$php_code = preg_replace($patterns, $holders, $php_code);
		}
		$php_code = preg_replace_callback("/(?:.+=.+[".$pad_chars."]\r?\n){".$this->block_size.",}/", array($this, "pad_operators"), $php_code);
		for ($i = sizeof($holders)-1;$i>=0;$i--) {
			$holders[$i] = "/".$holders[$i]."/";
		}
		if (sizeof($holders)>0) {
			$php_code = preg_replace($holders, $quotes[0], $php_code);
		}
		return $php_code;
	}
	private function align_operators() {
		if ($this->options['ALIGN_ARRAY_ASSIGNMENT'] || $this->options['ALIGN_VAR_ASSIGNMENT']) {
			return preg_replace_callback("/<\?.*?\?".">/s", array($this, "parse_block"), $this->code);
		} else {
			return $this->code;
		}
	}
}
