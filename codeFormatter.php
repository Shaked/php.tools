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
define("DEBUG", false);
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
		"ALIGN_ASSIGNMENTS" => true,
		"ORDER_USE" => true,
		"REMOVE_UNUSED_USE_STATEMENTS" => true,
		"SPACE_INSIDE_FOR" => false,
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
	private $debug = DEBUG;
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
		$artificial_curly_close = false;
		$artificial_curly_open = false;
		$if_pending = 0;
		$in_array_counter = 0;
		$in_attribution_counter = 0;
		$in_call_context = false;
		$in_call_counter = 0;
		$in_case_counter = 0;
		$in_do_counter = 0;
		$in_elseif_counter = 0;
		$in_for_counter = 0;
		$in_foreach_counter = 0;
		$in_function_counter = 0;
		$in_if_counter = 0;
		$in_parentheses_counter = 0;
		$in_question_counter = 0;
		$in_switch_counter = 0;
		$in_while_counter = 0;
		$way_clear = true;
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->get_token($token);
			$this->ptr = $index;
			if ($if_pending > 0) {
				if (T_DO == $id || T_FOR == $id || T_FOREACH == $id || T_FUNCTION == $id || T_WHILE == $id) {
					$way_clear = false;
				}
			}
			switch ($id) {
				case ST_QUESTION:
					$this->append_code($text, false);
					$in_question_counter++;
					break;
				case T_RETURN:
				case T_YIELD:
				case T_ECHO:
				case T_NAMESPACE:
				case T_USE:
				case T_NEW:
				case T_CONST:
				case T_FINAL:
					$this->append_code($text.$this->get_space(), false);
					break;
				case T_PUBLIC:
				case T_PRIVATE:
				case T_PROTECTED:
				case T_STATIC:
				case T_CLASS:
				case T_INTERFACE:
				case T_THROW:
				case T_ABSTRACT:
				case T_INCLUDE:
				case T_REQUIRE:
				case T_INCLUDE_ONCE:
				case T_REQUIRE_ONCE:
					$this->append_code($text.$this->get_space(), false);
					break;
				case T_EXTENDS:
				case T_IMPLEMENTS:
				case T_INSTANCEOF:
				case T_LOGICAL_AND:
				case T_LOGICAL_OR:
				case T_LOGICAL_XOR:
					$this->append_code($this->get_space().$text.$this->get_space(), false);
					break;
				case T_DOUBLE_ARROW:
					$this->append_code($this->get_space().$text.$this->get_space());
					break;
				case T_END_HEREDOC:
					$this->append_code($text.$this->get_crlf_indent(), false);
					break;
				case T_ARRAY_CAST:
				case T_BOOL_CAST:
				case T_DOUBLE_CAST:
				case T_INT_CAST:
				case T_OBJECT_CAST:
				case T_STRING_CAST:
				case T_UNSET_CAST:
					$this->append_code($text.$this->get_space());
					break;
				case T_COMMENT:
				case T_DOC_COMMENT:
					$this->append_code(trim($text).$this->get_crlf_indent(), false);
					break;
				case T_ARRAY:
					if ($in_call_context) {
						if ($this->is_token(ST_COMMA, true) && $this->is_token(array(T_VARIABLE))) {
							$this->append_code($this->get_space().$text.$this->debug('[Arr.Cast]').$this->get_space());
						} elseif ($this->is_token(ST_PARENTHESES_OPEN, true) && $this->is_token(ST_PARENTHESES_OPEN)) {
							$this->append_code($text.$this->debug('[Arr.(ar(]'));
						} elseif ($this->is_token(ST_EQUAL, true) || $this->is_token(ST_PARENTHESES_OPEN)) {
							$this->append_code($this->get_space().$text.$this->debug('[Arr.=]'));
						} else {
							$this->append_code($text.$this->debug('[Arr.Else]'));
						}
						break;
					} elseif ($this->is_token(array(T_VARIABLE))) {
						$this->append_code($text.$this->get_space(), false);
						break;
					} elseif ($this->is_token(array(T_RETURN, T_YIELD, T_COMMENT, T_DOC_COMMENT), true)) {
						$this->append_code($text, false);
						break;
					} elseif ($in_array_counter > 0) {
						$in_array_counter++;
						$this->append_code($this->get_crlf_indent().$text);
						break;
					} elseif ($in_function_counter > 0) {
						$condition = $this->is_token(ST_EQUAL, true);
						$this->append_code($this->get_space($condition).$text.$this->debug('[InFunc]').$this->get_space(!$condition));
						break;
					} elseif ($in_attribution_counter > 0) {
						$in_array_counter++;
						if ($this->is_token(ST_PARENTHESES_OPEN, true)) {
							$this->append_code($text);
						} else {
							$this->append_code($this->get_space().$text);
						}
						break;
					} elseif (0 == $in_array_counter && $this->is_token(ST_PARENTHESES_OPEN)) {
						$in_array_counter++;
						$this->append_code($this->get_crlf_indent().$text.$this->debug('[AR.++]'));
						break;
					}
					$this->append_code($text);
					break;
				case ST_COMMA:
					if ($in_array_counter > 0) {
						$this->append_code($text.$this->get_crlf_indent());
						break;
					} else {
						$this->append_code($text.$this->get_space());
						break;
					}
				case T_BREAK:
					if ($in_case_counter > 0) {
						$this->set_indent(-1);
						$this->append_code($this->get_crlf_indent().$text);
						break;
					}
				case ST_COLON:
					if ($in_question_counter > 0) {
						$this->append_code($text);
						$in_question_counter--;
						break;
					} elseif ($in_case_counter > 0) {
						$this->append_code($text.$this->get_crlf_indent());
						$this->set_indent(+1);
						$this->append_code($this->get_crlf_indent());
						break;
					}
					$this->append_code($text, false);
					break;
				case T_SWITCH:
					$in_switch_counter++;
					$this->append_code($this->get_crlf_indent().$text.$this->get_space(), true);
					break;
				case T_DEFAULT:
					$in_case_counter++;
					if ($this->is_token(ST_COLON, true)) {
						$this->set_indent(-1);
					}
					$this->append_code($this->get_crlf_indent().$text, true);
					break;
				case T_CASE:
					$in_case_counter++;
					if ($this->is_token(ST_COLON, true)) {
						$this->set_indent(-1);
					}
					$this->append_code($this->get_crlf_indent().$text.$this->get_space(), true);
					break;
				case ST_EQUAL:
					$in_attribution_counter++;
					$this->append_code($this->get_space().$text.$this->debug('[ON.at]').$this->get_space(), true);
					break;
				case T_STRING:
					if ($this->is_token(array(T_VARIABLE))) {
						$this->append_code($text.$this->debug('[str.VAR]').$this->get_space(), true);
						break;
					} elseif ($this->is_token(ST_CURLY_CLOSE, true)) {
						$this->append_code($this->get_crlf_indent().$text.$this->debug('[str.}]'), false);
						break;
					} elseif ($artificial_curly_close) {
						$this->append_code($this->get_crlf_indent().$text.$this->debug('[str.Artif}]'), false);
						$artificial_curly_close = false;
						break;
					}
					$this->append_code($text.$this->debug('[str.Else]'), false);
					break;
				case T_FUNCTION:
					$in_function_counter++;
					$this->append_code($text.$this->debug('[InFunc++]').$this->get_space(), false);
					break;
				case T_AS:
					$this->append_code($this->get_space().$text.$this->get_space(), false);
					break;
				case T_WHILE:
					$in_while_counter++;
					$this->append_code($text.$this->get_space(), false);
					break;
				case T_FOR:
					$in_for_counter++;
					$this->append_code($text.$this->get_space(), false);
					break;
				case T_FOREACH:
					$in_foreach_counter++;
					$this->append_code($text.$this->get_space(), false);
					break;
				case T_DO:
					$in_do_counter++;
					if ($this->is_token(ST_CURLY_CLOSE, true)) {
						$this->append_code($this->get_crlf_indent());
					} elseif ($artificial_curly_close) {
						$this->append_code($this->get_crlf_indent());
						$artificial_curly_close = false;
					}
					$this->append_code($text, false);
					break;
				case T_WHITESPACE:
					continue;
				case ST_SEMI_COLON:
					if ($this->is_token(array(T_END_HEREDOC), true)) {
						$this->append_code($this->get_crlf_indent().$text.$this->get_crlf_indent(), true);
						break;
					} elseif (0 == $in_for_counter && $in_attribution_counter > 0) {
						$in_attribution_counter--;
						$this->append_code($text.$this->debug('[OFF.at]').$this->get_crlf_indent(), false);
						if ($this->is_token(array(T_ELSE, T_ELSEIF))) {
							$if_pending--;
							$this->set_indent(-1);
							$this->append_code($this->get_crlf_indent().'}'.$this->debug('[;.Artif}.ElseElseIf]').$this->get_space());
						} elseif ($if_pending > 0) {
							$if_pending--;
							$this->set_indent(-1);
							$this->append_code($this->get_crlf_indent().'}'.$this->debug('[;.Artif}.Else]').$this->get_crlf_indent());
						}
						break;
					} elseif ($in_for_counter > 0 && $in_attribution_counter > 0) {
						$this->append_code($text.$this->debug('[;.For}]'), false);
						break;
					} elseif ($if_pending > 0 && $way_clear) {
						$if_pending--;
						$this->set_indent(-1);
						$this->append_code($text.$this->debug('[;.IfArtif}]').$this->get_crlf_indent().'}'.$this->get_space(), false);
						$artificial_curly_close = true;
						break;
					} elseif ($in_elseif_counter > 0 && $way_clear) {
						$in_elseif_counter--;
						if ($this->is_token(ST_CURLY_CLOSE)) {
							$this->append_code($text.$this->debug('[;.ElseIf}]'), false);
						} else {
							$this->set_indent(-1);
							$this->append_code($text.$this->debug('[;.ElseIfArtif}]').$this->get_crlf_indent().'} ', false);
						}
						$artificial_curly_close = true;
						break;
					} elseif ($in_while_counter > 0 && $in_do_counter == $in_while_counter) {
						$in_while_counter--;
						$in_do_counter--;
						$this->append_code($text.$this->debug('[;.DoWhile]').$this->get_crlf_indent(), false);
						break;
					} elseif ($in_foreach_counter > 0) {
						$in_foreach_counter--;
						$this->append_code($text.$this->debug('[;.Foreach]').$this->get_crlf_indent(), false);
						break;
					} elseif ($in_switch_counter > 0 && $this->is_token(array(T_BREAK, T_CASE))) {
						$this->append_code($text.$this->debug('[;.Switch]'));
						break;
					} elseif ($in_case_counter > 0 && !($this->is_token(array(T_BREAK, T_CASE)) || $this->is_token(ST_CURLY_CLOSE))) {
						$this->append_code($text.$this->debug('[;.Case]').$this->get_crlf_indent());
						break;
					} elseif ($in_call_context) {
						$this->append_code($text.$this->debug('[;.InCall]'), true);
						break;
					}
					$this->append_code($text.$this->debug('[;.else]').$this->get_crlf_indent(), true);
					break;
				case ST_CURLY_OPEN:
					if ($in_for_counter > 0) {
						$in_for_counter--;
						$this->set_indent(+1);
						$this->append_code($this->get_space().$text.$this->get_crlf_indent(), false);
					} elseif ($this->is_token(array(T_COMMENT, T_DOC_COMMENT), true) && $artificial_curly_open) {
						$artificial_curly_open = false;
						$this->append_code($this->get_crlf_indent(), false);
						if ($if_pending > 0) {
							$if_pending--;
						}
					} else {
						$this->set_indent(+1);
						$this->append_code($this->get_space().$text.$this->get_crlf_indent(), false);
					}
					break;
				case ST_CURLY_CLOSE:
					$this->set_indent(-1);
					if (!$this->is_token(ST_CURLY_CLOSE, true) && !$this->is_token(ST_SEMI_COLON, true)) {
						$this->append_code($this->get_crlf_indent().$text, false);
					} else {
						$this->append_code($this->get_crlf_indent().$text, true);
					}
					if ($in_do_counter > 0 && $this->is_token(array(T_WHILE))) {
						$this->append_code($this->get_space().$this->debug('[}.DoWhile]'), false);
						$in_do_counter--;
						$in_while_counter--;
					} elseif ($in_foreach_counter > 0) {
						$this->append_code($this->get_crlf_indent().$this->debug('[}.Foreach]'), false);
						$this->set_indent(-1);
						$in_foreach_counter--;
					} elseif ($in_switch_counter > 0) {
						$in_switch_counter--;
						$this->append_code($this->get_crlf_indent().$this->debug('[}.Switch]'), true);
					} elseif ($this->is_token(array(T_CATCH, T_ELSE, T_ELSEIF))) {
						$this->append_code($this->get_space().$this->debug('[}.CatchElseElseIf]'), false);
					} elseif ($in_if_counter > 0) {
						$in_if_counter--;
						$this->append_code($this->get_crlf_indent().$this->debug('[}.IfCounter]'), true);
					} else {
						$this->append_code($this->get_crlf_indent().$this->debug('[}.Else]'), false);
					}
					break;
				case ST_PARENTHESES_OPEN:
					$in_parentheses_counter++;
					$this->append_code($text.$this->debug('[CC.'.(1*$in_call_context).']').$this->debug('[AR.'.(1*$in_array_counter).']'), false);
					if (!$in_call_context && $this->is_token(array(T_STRING, T_FOR, T_FOREACH, T_WHILE, T_IF, T_ELSEIF), true)) {
						$in_call_context = true;
						$in_call_counter = $in_parentheses_counter-1;
						$this->append_code($this->debug('[ON.'.$in_call_counter.']'), false);
					} elseif (!$in_call_context && $in_array_counter > 0 && $in_parentheses_counter <= $in_array_counter) {
						$this->set_indent(+1);
						$this->append_code($this->get_crlf_indent());
					}
					if ($artificial_curly_close) {
						$artificial_curly_close = false;
					}
					break;
				case ST_PARENTHESES_CLOSE:
					$in_parentheses_counter--;
					if ($in_call_context && $in_parentheses_counter == $in_call_counter) {
						$in_call_context = false;
						$this->append_code($text.$this->debug('[OFF.'.$in_call_counter.']'), false);
						if ($in_function_counter > 0) {
							$in_function_counter--;
							$this->append_code($this->debug('[InFunc--]'), false);
						} elseif ($in_if_counter > 0 && $this->is_token(array(T_VARIABLE, T_STRING, T_DOC_COMMENT, T_COMMENT))) {
							$this->set_indent(+1);
							$this->append_code(' {'.$this->get_crlf_indent(), false);
							$artificial_curly_open = true;
							$if_pending++;
							$in_if_counter--;
						} elseif ($in_elseif_counter > 0 && $this->is_token(array(T_VARIABLE, T_STRING, T_DOC_COMMENT, T_COMMENT))) {
							$this->append_code(' {');
							$artificial_curly_open = true;
							$if_pending++;
							$in_elseif_counter--;
							$this->set_indent(+1);
							$this->append_code($this->get_crlf_indent(), false);
						} elseif ($in_if_counter > 0 && $this->is_token(array(T_DO, T_FOR, T_FOREACH, T_WHILE, T_FUNCTION, T_RETURN))) {
							$this->append_code($this->debug('[).next:LOOP.IF:'.$in_if_counter.']').$this->get_space(), false);
							$in_if_counter--;
						} elseif ($this->is_token(array(T_DO, T_FOR, T_FOREACH, T_WHILE, T_FUNCTION))) {
							$this->append_code($this->get_space(), false);
						} elseif ($in_for_counter > 0) {
							$this->append_code($this->debug("[).CC.For--]"));
							$in_for_counter--;
						}
						break;
					} elseif ($in_function_counter > 0) {
						$in_function_counter--;
						$this->append_code($text.$this->debug('[InFunc--]'), false);
						if ($in_attribution_counter > 0) {
							$in_attribution_counter--;
						}
						break;
					} elseif ($in_array_counter > 0 && $in_parentheses_counter < $in_array_counter) {
						$this->set_indent(-1);
						if (!$this->is_token(array(T_DOC_COMMENT, T_COMMENT), true) && ',' != substr(trim($this->code),-1, 1) && '(' != substr(trim($this->code),-1, 1) && ')' != substr(trim($this->code),-1, 1)) {
							$this->append_code(',');
							$this->append_code($this->get_crlf_indent().$text, false);
						} else {
							$this->append_code($this->get_crlf_indent().$text, true);
						}
						$in_array_counter--;
						break;
					} elseif ($in_for_counter > 0) {
						$this->append_code($text.$this->debug("[).For--]"));
						if (!$this->is_token(ST_CURLY_OPEN)) {
							$this->append_code($this->get_space());
						}
						$in_for_counter--;
					} elseif ($in_elseif_counter > 0 && $this->is_token(array(T_VARIABLE, T_STRING, T_DOC_COMMENT, T_COMMENT))) {
						$this->set_indent(+1);
						$this->append_code($text.' {'.$this->get_crlf_indent(), false);
						$artificial_curly_open = true;
					} elseif ($in_if_counter > 0 && $this->is_token(array(T_VARIABLE, T_STRING, T_DOC_COMMENT, T_COMMENT))) {
						$this->set_indent(+1);
						$this->append_code($text.' {'.$this->get_crlf_indent(), false);
						$artificial_curly_open = true;
						$if_pending++;
					} elseif ($this->is_token(array(T_DO, T_FOR, T_FOREACH, T_WHILE, T_FUNCTION))) {
						$this->append_code($text.$this->get_space(), false);
					} elseif ($in_array_counter > 0 && $in_attribution_counter > 0 && !$this->is_token(ST_QUESTION)) {
						$this->set_indent(-1);
						$this->append_code($text.$this->get_crlf_indent(), false);
						$in_array_counter--;
					} else {
						$this->append_code($text, false);
					}
					break;
				case T_ELSEIF:
					$way_clear = true;
					if ($artificial_curly_close) {
						$artificial_curly_close = false;
					}
					$this->append_code($text.$this->get_space(), false);
					$in_elseif_counter++;
					break;
				case T_ELSE:
					$way_clear = true;
					$this->append_code($text, false);
					if ($this->is_token(array(T_DO, T_FOR, T_FOREACH, T_WHILE, T_THROW, T_ECHO, T_CONTINUE))) {
						$this->append_code($this->get_space());
					} elseif ($this->is_token(array(T_VARIABLE, T_STRING))) {
						$this->set_indent(+1);
						$this->append_code(' {'.$this->get_crlf_indent(), false);
						$if_pending++;
					}
					break;
				case T_IF:
					$way_clear = true;
					$in_if_counter++;
					if ($artificial_curly_close) {
						$this->append_code($this->get_crlf_indent(), false);
						$artificial_curly_close = false;
					}
					$this->append_code($this->get_crlf_indent().$text.$this->get_space());
					break;
				default:
					$this->append_code($text.$this->debug("[Default]"), false);
					break;
			}
		}
		$ret = $this->align_operators();
		return implode($this->new_line, array_map(function($v) {
			return rtrim($v);
		}, explode($this->new_line, $ret)));
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
		} elseif (is_array($token) && isset($this->tkns[$i]) && is_array($this->tkns[$i])) {
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
				$ws = str_repeat(' ', max($biggest-$len+1, 0));
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
	private function debug($str) {
		if ($this->debug) {
			return $str;
		}
	}
}
class SurrogateToken {
}
