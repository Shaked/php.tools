<?php
//Inspired by http://phpstylist.sourceforge.net/
//
//Copyright (c) 2014, Carlos C
//All rights reserved.
//
//Redistribution and use in source and binary forms, with or without modification, are permitted provided that the following conditions are met:
//
//1. Redistributions of source code must retain the above copyright notice, this list of conditions and the following disclaimer.
//
//2. Redistributions in binary form must reproduce the above copyright notice, this list of conditions and the following disclaimer in the documentation and/or other materials provided with the distribution.
//
//3. Neither the name of the copyright holder nor the names of its contributors may be used to endorse or promote products derived from this software without specific prior written permission.
//
//THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT HOLDER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
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
if (!isset($testEnv)) {
	$fmt = new CodeFormatter();
	if (!isset($argv[1])) {
		exit();
	}
	echo $fmt->formatCode(file_get_contents($argv[1]));
}
class CodeFormatter {
	private $options = array(
		"ADD_MISSING_BRACES" => true,
		"ALIGN_ASSIGNMENTS" => true,
		"CATCH_ALONG_CURLY" => true,
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
		"ORDER_USE" => true,
		"REMOVE_UNUSED_USE_STATEMENTS" => true,
		"SPACE_AFTER_COMMA" => true,
		"SPACE_AFTER_IF" => true,
		"SPACE_AROUND_ARITHMETIC" => false,
		"SPACE_AROUND_ASSIGNMENT" => true,
		"SPACE_AROUND_COLON_QUESTION" => false,
		"SPACE_AROUND_COMPARISON" => true,
		"SPACE_AROUND_CONCAT" => false,
		"SPACE_AROUND_DOUBLE_ARROW" => true,
		"SPACE_AROUND_DOUBLE_COLON" => false,
		"SPACE_AROUND_LOGICAL" => true,
		"SPACE_AROUND_OBJ_OPERATOR" => false,
		"SPACE_INSIDE_FOR" => false,
		"SPACE_INSIDE_PARENTHESES" => false,
		"SPACE_OUTSIDE_PARENTHESES" => false,
		"VERTICAL_ARRAY" => true,
		"VERTICAL_CONCAT" => false,
		"WHILE_ALONG_CURLY" => true,
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
	private function orderUseClauses($source = '') {
		$use_stack = [];
		$tokens = token_get_all($source);
		$new_tokens = [];
		$next_tokens = [];
		while (list(, $pop_token) = each($tokens)) {
			$next_tokens[] = $pop_token;
			while (($token = array_shift($next_tokens))) {
				list($id, $text) = $this->get_token($token);
				if (T_USE == $id) {
					$use_item = $text;
					while (list(, $token) = each($tokens)) {
						list($id, $text) = $this->get_token($token);
						if (ST_SEMI_COLON == $id) {
							$use_item .= $text;
							break;
						} elseif (ST_COMMA == $id) {
							$use_item .= ST_SEMI_COLON;
							$next_tokens[] = [
								T_USE,
								'use',
							];
							break;
						} else {
							$use_item .= $text;
						}
					}
					$use_stack[] = $use_item;
					$token = new SurrogateToken();
				}
				$new_tokens[] = $token;
				if (T_CLASS == $id || T_FUNCTION == $id) {
					break 2;
				}
			}
		}
		natcasesort($use_stack);
		$alias_list = [];
		$alias_count = [];
		foreach ($use_stack as $use) {
			if (false !== stripos($use, ' as ')) {
				$alias = substr(strstr($use, ' as '), strlen(' as '),-1);
			} else {
				$alias = basename(str_replace('\\', '/', trim(substr($use, strlen('use'),-1))));
			}
			$alias = strtolower($alias);
			$alias_list[$alias] = strtolower($use);
			$alias_count[$alias] = 0;
		}
		$return = '';
		foreach ($new_tokens as $token) {
			if ($token instanceof SurrogateToken) {
				$return .= array_shift($use_stack);
			} else {
				list($id, $text) = $this->get_token($token);
				$lower_text = strtolower($text);
				if (T_STRING == $id && isset($alias_list[$lower_text])) {
					$alias_count[$lower_text]++;
				}
				$return .= $text;
			}
		}
		/*prev($tokens);*/
		while (list(, $token) = each($tokens)) {
			list($id, $text) = $this->get_token($token);
			$lower_text = strtolower($text);
			if (T_STRING == $id && isset($alias_list[$lower_text])) {
				$alias_count[$lower_text]++;
			}
			$return .= $text;
		}
		if ($this->options['REMOVE_UNUSED_USE_STATEMENTS']) {
			$unused_import = array_keys(array_filter($alias_count, function($v) {
				return 0 == $v;
			}));
			foreach ($unused_import as $v) {
				$return = str_ireplace($alias_list[$v], null, $return);
			}
		}
		return $return;
	}
	public function formatCode($source = '') {
		if ($this->options['ORDER_USE']) {
			$source = $this->orderUseClauses($source);
		}
		$this->tkns = token_get_all($source);
		$after = false;
		$arr_bracket = array();
		$arr_parentheses = array();
		$array_level = 0;
		$curly_open = false;
		$else_pending = false;
		$halt_parser = false;
		$if_level = 0;
		$if_parentheses = array();
		$if_pending = 0;
		$in_break = false;
		$in_concat = false;
		$in_do_context = false;
		$in_for = false;
		$in_for_context = false;
		$in_function = false;
		$in_heredoc_context = false;
		$inside_array_dereference = 0;
		$space_after = false;
		$space_after_t_use = false;
		$space_before_bracket = false;
		$switch_arr = array();
		$switch_level = 0;
		foreach ($this->tkns as $index => $token) {
			list($id, $text) = $this->get_token($token);
			$this->ptr = $index;
			if ($halt_parser && $id != ST_QUOTE) {
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
						if (($in_break || $this->is_token(ST_CURLY_CLOSE)) && $switch_level > 0 && $switch_arr["l".$switch_level] > 0 && $switch_arr["s".$switch_level] == $this->indent-2) {
							if ($this->options["INDENT_CASE"]) {
								$this->set_indent(-1);
							}
							$switch_arr["l".$switch_level]--;
							$switch_arr["c".$switch_level]--;
						} while ($switch_level > 0 && $switch_arr["l".$switch_level] == 0 && $this->options["INDENT_CASE"]) {
							unset($switch_arr["s".$switch_level]);
							unset($switch_arr["c".$switch_level]);
							unset($switch_arr["l".$switch_level]);
							$switch_level--;
							if ($switch_level > 0) {
								$switch_arr["l".$switch_level]--;
							}
							$this->set_indent(-1);
							$this->append_code($this->get_crlf_indent().$text.$this->get_crlf_indent());
							$text = '';
						}
						if ($text != '') {
							$this->set_indent(-1);
							$this->append_code($this->get_crlf_indent().$text.$this->get_crlf_indent());
						}
					}
					break;
				case ST_SEMI_COLON:
					if (($in_break || $this->is_token(ST_CURLY_CLOSE)) && $switch_level > 0 && $switch_arr["l".$switch_level] > 0 && $switch_arr["s".$switch_level] == $this->indent-2) {
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
					$this->append_code($this->get_crlf($in_heredoc_context).$text.$this->get_crlf($this->options["LINE_AFTER_BREAK"] && $in_break).$this->get_crlf_indent($in_for));
					while ($if_pending > 0) {
						$text = $this->options["ADD_MISSING_BRACES"]?"}":"";
						$this->set_indent(-1);
						if ($text != "") {
							$this->append_code($this->get_crlf_indent().$text.$this->get_crlf_indent());
						} else {
							$this->append_code($this->get_crlf_indent());
						}
						$if_pending--;
						if ($this->is_token(array(T_ELSE, T_ELSEIF))) {
							break;
						}
					}
					if ($in_heredoc_context) {
						$in_heredoc_context = false;
					}
					if ($this->for_idx == 0) {
						$in_for = false;
					}
					$in_break = false;
					$in_function = false;
					break;
				case ST_BRACKET_OPEN:
					if (!$this->is_token(array(T_VARIABLE, T_STRING), true) && !$this->is_token(ST_BRACKET_CLOSE, true)) {
						if ($this->is_token(ST_EQUAL, true) && !$this->is_token(ST_BRACKET_CLOSE) || $array_level > 0) {
							$array_level++;
							$arr_bracket["i".$array_level] = 0;
						}
						if ($array_level > 0) {
							$arr_bracket["i".$array_level]++;
							$this->set_indent(+1);
							$this->append_code((!$this->options["LINE_BEFORE_ARRAY"]?'':$this->get_crlf_indent(false,-1)).$text.$this->get_crlf().$this->get_indent(), false);
							break;
						}
					} else {
						$inside_array_dereference++;
					}
					if ($this->is_token(array(T_DOUBLE_ARROW, T_RETURN), true) || $this->is_token(ST_EQUAL, true)) {
						$space_before_bracket = true;
					}
					$break_line_after_semicolon = $this->is_token(ST_SEMI_COLON, true)?$this->get_crlf_indent():'';
					$this->append_code($break_line_after_semicolon.$this->get_space($space_before_bracket).$text);
					$space_before_bracket = false;
					break;
				case ST_BRACKET_CLOSE:
					if (0 == $inside_array_dereference && $array_level > 0) {
						$arr_bracket["i".$array_level]--;
						if ($arr_bracket["i".$array_level] == 0) {
							$comma = !$this->is_token(array(T_COMMENT, T_DOC_COMMENT), true) && substr(trim($this->code),-1) != "," && substr(trim($this->code),-1) != "[" && $this->options['VERTICAL_ARRAY']?",":"";
							$this->set_indent(-1);
							$this->append_code($comma.$this->get_crlf_indent().$text.$this->get_crlf_indent());
							unset($arr_bracket["i".$array_level]);
							$array_level--;
							break;
						}
					}
					if ($inside_array_dereference > 0) {
						$inside_array_dereference--;
					}
					$this->append_code($this->get_space($this->options["SPACE_INSIDE_PARENTHESES"]).$text.$this->get_space($this->options["SPACE_OUTSIDE_PARENTHESES"]));
					break;
				case ST_PARENTHESES_OPEN:
					if ($if_level > 0) {
						$if_parentheses["i".$if_level]++;
					}
					if ($array_level > 0) {
						if (isset($arr_parentheses["i".$array_level])) {
							$arr_parentheses["i".$array_level]++;
							if ($this->is_token(array(T_ARRAY), true) && !$this->is_token(ST_PARENTHESES_CLOSE)) {
								$this->set_indent(+1);
								$this->append_code((!$this->options["LINE_BEFORE_ARRAY"]?'':$this->get_crlf_indent(false,-1)).$text.$this->get_crlf_indent());
								break;
							}
						}
					}
					$break_line_after_semicolon = $this->is_token(ST_SEMI_COLON, true)?$this->get_crlf_indent():'';
					$this->append_code($break_line_after_semicolon.$this->get_space($this->options["SPACE_OUTSIDE_PARENTHESES"] || $space_after || $space_after_t_use).$text.$this->get_space($this->options["SPACE_INSIDE_PARENTHESES"]));
					$space_after = false;
					$space_after_t_use = false;
					break;
				case ST_PARENTHESES_CLOSE:
					if ($array_level > 0) {
						if (isset($arr_parentheses["i".$array_level])) {
							$arr_parentheses["i".$array_level]--;
							if ($arr_parentheses["i".$array_level] == 0) {
								$comma = !$this->is_token(array(T_COMMENT, T_DOC_COMMENT), true) && substr(trim($this->code),-1) != "," && $this->options['VERTICAL_ARRAY']?",":"";
								$this->set_indent(-1);
								$this->append_code($comma.$this->get_crlf_indent().$text.$this->get_crlf_indent());
								unset($arr_parentheses["i".$array_level]);
								$array_level--;
								break;
							}
						}
					}
					$this->append_code($this->get_crlf($in_heredoc_context).$this->get_space($this->options["SPACE_INSIDE_PARENTHESES"]).$text.$this->get_space($this->options["SPACE_OUTSIDE_PARENTHESES"]));
					if ($if_level > 0) {
						if (isset($arr_parentheses["i".$array_level])) {
							$if_parentheses["i".$if_level]--;
							if ($if_parentheses["i".$if_level] == 0) {
								if (!$this->is_token(ST_CURLY_OPEN) && !$this->is_token(ST_SEMI_COLON)) {
									$text = $this->options["ADD_MISSING_BRACES"]?"{":"";
									$this->set_indent(+1);
									$this->append_code((!$this->options["LINE_BEFORE_CURLY"] || $text == ""?' ':$this->get_crlf_indent(false,-1)).$text.$this->get_crlf_indent());
									$if_pending++;
								}
								unset($if_parentheses["i".$if_level]);
								$if_level--;
							}
						}
					}
					if ($in_heredoc_context) {
						$in_heredoc_context = false;
					}
					break;
				case ST_COMMA:
					if ($array_level > 0) {
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
					if ($switch_level > 0 && $switch_arr["l".$switch_level] > 0 && $switch_arr["c".$switch_level] < $switch_arr["l".$switch_level]) {
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
					if (($in_break || $this->is_token(ST_CURLY_CLOSE)) && $switch_level > 0 && $switch_arr["l".$switch_level] > 0) {
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
					$in_for_context = true;
				case T_FOREACH:
				case T_DO:
					$in_do_context = true;
				case T_IF:
				case T_SWITCH:
					$space_after = $this->options["SPACE_AFTER_IF"];
					$this->append_code($text.$this->get_space($space_after), false);
					if ($id == T_SWITCH) {
						$switch_level++;
						$switch_arr["s".$switch_level] = $this->indent;
						$switch_arr["l".$switch_level] = 0;
						$switch_arr["c".$switch_level] = 0;
					}
					$if_level++;
					$if_parentheses["i".$if_level] = 0;
					break;
				case T_WHILE:
					$space_after = $this->options["SPACE_AFTER_IF"];
					$condition = !$in_for_context && $in_do_context && $this->options['WHILE_ALONG_CURLY'] && $this->is_token(ST_CURLY_CLOSE, true);
					$in_do_context = false;
					$in_for_context = false;
					$this->append_code($this->get_space($condition).$text.$this->get_space($space_after), $condition);
					break;
				case T_USE:
					$space_after_t_use = true;
					$space_before_t_use = $this->is_token(ST_PARENTHESES_CLOSE, true);
					$this->append_code($this->get_space($space_before_t_use).$text.$this->get_space(), false);
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
					$this->append_code($this->get_crlf().$text.$this->get_crlf().$this->get_crlf_indent(), false);
					$in_heredoc_context = true;
					break;
				case T_COMMENT:
					if ($this->is_token(ST_CURLY_OPEN) || $this->is_token(array(T_OBJECT_OPERATOR))) {
						if ('//' == substr($text, 0, 2)) {
							$text = '/*'.trim(str_replace(['/*', '*/'], '', substr($text, 2))).'*/';
						} elseif ('#' == substr($text, 0, 1)) {
							$text = '/*'.trim(str_replace(['/*', '*/'], '', substr($text, 1))).'*/';
						}
					}
				case T_DOC_COMMENT:
					if (is_array($this->tkns[$index-1])) {
						$pad = $this->tkns[$index-1][1];
						$i = strlen($pad)-1;
						$k = "";
						while (substr($pad, $i, 1) != "\n" && substr($pad, $i, 1) != "\r" && $i >= 0) {
							$k .= substr($pad, $i--, 1);
						}
						$text = preg_replace("/\r?\n$k/", $this->get_crlf_indent(), $text);
					}
					$after = $id == (T_COMMENT && preg_match("/^\/\//", $text))?$this->options["LINE_AFTER_COMMENT"]:$this->options["LINE_AFTER_COMMENT_MULTI"];
					$before = $id == (T_COMMENT && preg_match("/^\/\//", $text))?$this->options["LINE_BEFORE_COMMENT"]:$this->options["LINE_BEFORE_COMMENT_MULTI"];
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
						$lines = $lines > 0?$lines-1:0;
						$redundant = $lines > 0?str_repeat($this->new_line, $lines):"";
						$current_indent = $this->get_indent();
						if (substr($this->code, strlen($current_indent)*-1) == $current_indent && $lines > 0) {
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
						$next |= $array_level > 0;
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
				case T_VARIABLE:
					if ($this->is_token(array(T_STRING, T_ARRAY), true) && !$this->is_token(array(T_OPEN_TAG), true)) {
						$this->append_code(' ', false);
					}
					$this->append_code($text, false);
					break;
				case T_STRING:
				case T_CONSTANT_ENCAPSED_STRING:
				case T_ENCAPSED_AND_WHITESPACE:
				case T_CHARACTER:
				case T_STRING_VARNAME:
				case ST_AT:
				case ST_EXCLAMATION:
				case T_OPEN_TAG:
				case T_OPEN_TAG_WITH_ECHO:
				case T_NS_SEPARATOR:
					$space_after_t_use = false;
					$this->append_code($text, false);
					break;
				case T_CLOSE_TAG:
					$this->append_code($text, !$this->is_token_lf(true));
					break;
				case T_CASE:
				case T_DEFAULT:
					if ($switch_arr["l".$switch_level] > 0 && $this->options["INDENT_CASE"]) {
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
					if (!$this->is_token(ST_COLON) && !$this->is_token(ST_CURLY_OPEN) && !$this->is_token(array(T_IF))) {
						$text = $this->options["ADD_MISSING_BRACES"]?"{":"";
						$this->set_indent(+1);
						$this->append_code((!$this->options["LINE_BEFORE_CURLY"] || $text == ""?' ':$this->get_crlf_indent(false,-1)).$text.$this->get_crlf_indent());
						$if_pending++;
					}
					break;
				case T_CATCH:
					$condition = $this->options['CATCH_ALONG_CURLY'] && $this->is_token(ST_CURLY_CLOSE, true);
					$this->append_code($this->get_space($condition).$text, $condition);
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
			if ($this->for_idx > 2) {
				$this->for_idx = 0;
			}
		}
		if ($this->for_idx == 0 || !$in_for) {
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
		if ($this->indent < 0) {
			$this->indent = 0;
		}
	}
	private function is_token($token, $prev = false, $i = 99999, $idx = false) {
		if ($i == 99999) {
			$i = $this->ptr;
		}
		if ($prev) {
			while (--$i >= 0 && is_array($this->tkns[$i]) && $this->tkns[$i][0] == T_WHITESPACE);
		} else {
			while (++$i < sizeof($this->tkns)-1 && is_array($this->tkns[$i]) && $this->tkns[$i][0] == T_WHITESPACE);
		}
		if (isset($this->tkns[$i]) && is_string($this->tkns[$i]) && $this->tkns[$i] == $token) {
			return $idx?$i:true;
		} elseif (is_array($token) && is_array($this->tkns[$i])) {
			if (in_array($this->tkns[$i][0], $token)) {
				return $idx?$i:true;
			} elseif ($prev && $this->tkns[$i][0] == T_OPEN_TAG) {
				return $idx?$i:true;
			}
		}
		return false;
	}
	private function is_token_lf($prev = false, $i = 99999) {
		if ($i == 99999) {
			$i = $this->ptr;
		}
		if ($prev) {
			$count = 0;
			while (--$i >= 0 && is_array($this->tkns[$i]) && $this->tkns[$i][0] == T_WHITESPACE && strpos($this->tkns[$i][1], "\n") === false);
		} else {
			$count = 1;
			while (++$i < sizeof($this->tkns) && is_array($this->tkns[$i]) && $this->tkns[$i][0] == T_WHITESPACE && strpos($this->tkns[$i][1], "\n") === false);
		}
		if (is_array($this->tkns[$i]) && preg_match_all("/\r?\n/", $this->tkns[$i][1], $matches) > $count) {
			return true;
		}
		return false;
	}
	private function process_block($current_block_name) {
		if (!is_null($current_block_name)) {
			$matches = null;
			preg_match_all('/\$.+'.preg_quote($current_block_name).'/', $this->code, $matches);
			$biggest = 0;
			foreach ($matches[0] as $match) {
				$len = strlen(str_replace($current_block_name, '', $match));
				$biggest = max($biggest, $len);
			}
			foreach ($matches[0] as $match) {
				$len = strlen($match)-strlen($current_block_name);
				$ws = str_repeat(' ', $biggest-$len+1);
				$this->code = preg_replace('/'.preg_quote($current_block_name).'/', $ws, $this->code, 1);
			}
			$this->code = str_replace($current_block_name, '', $this->code);
		}
	}
	private function align_operators() {
		if (!$this->options['ALIGN_ASSIGNMENTS']) {
			return $this->code;
		}
		$this->tkns = token_get_all($this->code);
		$this->code = '';
		$bracket_context_counter = 0;
		$current_block_name = null;
		$parentheses_context_counter = 0;
		foreach ($this->tkns as $index => $token) {
			list($id, $text) = $this->get_token($token);
			$this->ptr = $index;
			switch ($id) {
				case ST_EQUAL:
					if (!is_null($current_block_name) && 0 == $parentheses_context_counter && 0 == $bracket_context_counter) {
						$this->append_code($current_block_name, false);
					}
					$this->append_code($text, false);
					break;
				case ST_BRACKET_OPEN:
					$bracket_context_counter++;
					$this->append_code($text, false);
					break;
				case ST_BRACKET_CLOSE:
					$bracket_context_counter--;
					$this->append_code($text, false);
					break;
				case T_COMMENT:
				case T_DOC_COMMENT:
					$this->process_block($current_block_name);
					$current_block_name = null;
					$this->append_code($text, false);
					break;
				case T_IF:
				case T_SWITCH:
				case T_FOR:
				case T_FUNCTION:
					if (0 == $bracket_context_counter) {
						$parentheses_context_counter++;
						$this->process_block($current_block_name);
						$current_block_name = null;
					}
					$this->append_code($text, false);
					break;
				case ST_PARENTHESES_CLOSE:
					if (0 == $bracket_context_counter) {
						$parentheses_context_counter--;
					}
					$this->append_code($text, false);
					break;
				case T_VARIABLE:
					if (0 == $parentheses_context_counter && 0 == $bracket_context_counter) {
						if (is_null($current_block_name)) {
							$current_block_name = "\0\0\0".uniqid()."\0\0\0";
						}
					}
					$this->append_code($text, false);
					break;
				case T_WHITESPACE:
					if (!is_null($current_block_name) && $this->is_token(ST_EQUAL) && 0 == $parentheses_context_counter && 0 == $bracket_context_counter) {
						break;
					}
				default:
					$this->append_code($text, false);
					break;
			}
		}
		$this->process_block($current_block_name);
		return $this->code;
	}
}
class SurrogateToken {
}
