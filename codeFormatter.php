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
		"ALIGN_ASSIGNMENTS"            => true,
		"ORDER_USE"                    => true,
		"REMOVE_UNUSED_USE_STATEMENTS" => true,
	);
	private $indent_size = 1;
	private $indent_char = "\t";
	private $block_size  = 1;
	private $new_line    = "\n";
	private $indent      = 0;
	private $for_idx     = 0;
	private $code        = '';
	private $ptr         = 0;
	private $tkns        = 0;
	private $debug       = DEBUG;
	private function orderUseClauses($source = '') {
		$use_stack = [];
		$tokens = token_get_all($source);
		$new_tokens  = [];
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
							$next_tokens[] = [T_USE, 'use', ];
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
		$alias_list  = [];
		$alias_count = [];
		foreach ($use_stack as $use) {
			if (false !== stripos($use, ' as ')) {
				$alias = substr(strstr($use, ' as '), strlen(' as '), -1);
			} else {
				$alias = basename(str_replace('\\', '/', trim(substr($use, strlen('use'), -1))));
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
			$unused_import = array_keys(array_filter($alias_count, function ($v) {
				return 0 == $v;
			}
			));
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
		$artificial_curly_open  = false;
		$if_pending             = 0;
		$in_array_counter       = 0;
		$in_attribution_counter = 0;
		$in_bracket_counter     = 0;
		$in_call_context        = false;
		$in_call_counter        = 0;
		$in_case_counter        = 0;
		$in_do_counter          = 0;
		$in_elseif_counter      = 0;
		$in_for_counter         = 0;
		$in_foreach_counter     = 0;
		$in_function_counter    = 0;
		$in_curly_block         = 0;
		$in_if_counter          = 0;
		$in_parentheses_counter = 0;
		$in_question_counter    = 0;
		$in_switch_counter      = 0;
		$in_switch_curly_block = array();
		$in_while_counter = 0;
		$way_clear        = true;
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->get_token($token);
			$this->ptr = $index;
			if ($if_pending > 0) {
				if (T_DO == $id || T_FOR == $id || T_FOREACH == $id || T_FUNCTION == $id || T_WHILE == $id) {
					$way_clear = false;
				}
			}
			switch ($id) {
				case ST_BRACKET_OPEN:
					$this->append_code($text, false);
					$in_bracket_counter++;
					break;
				case ST_BRACKET_CLOSE:
					$this->append_code($text, false);
					$in_bracket_counter--;
					break;
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
					if ($artificial_curly_close) {
						$artificial_curly_close = false;
					}
					$this->append_code($text.$this->debug('[RetYield]').$this->get_space(), false);
					break;
				case T_PUBLIC:
				case T_PRIVATE:
				case T_PROTECTED:
				case T_STATIC:
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
					$this->append_code($text.$this->get_space(), false);
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
					if ($this->is_token(array(T_COMMENT, T_DOC_COMMENT), true)) {
						$this->append_code($this->get_crlf_indent().$text.$this->get_space());
					} elseif ($this->is_token(array(T_DOUBLE_ARROW), true) || $this->is_token(ST_EQUAL, true)) {
						$this->append_code($this->get_space().$text.$this->get_space());
					} else {
						$this->append_code($text.$this->get_space());
					}
					break;
				case T_COMMENT:
				case T_DOC_COMMENT:
					list($pt_id, $pt_text) = $this->inspect_token(-1);
					if (T_WHITESPACE == $pt_id && substr_count($pt_text, PHP_EOL) > 0 && substr_count($text, PHP_EOL) > 0) {
						$this->append_code($this->get_crlf_indent().rtrim($text).$this->debug('[//.alone]').$this->get_crlf_indent(), true);
						break;
					}
					$this->append_code(trim($text).$this->debug('[//.else]').$this->get_crlf_indent(), false);
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
						$this->append_code($text.$this->debug('[Arr.Var]').$this->get_space(), false);
						break;
					} elseif ($this->is_token(array(T_RETURN, T_YIELD, T_COMMENT, T_DOC_COMMENT), true)) {
						$this->append_code($text.$this->debug('[Arr.Ret]'), false);
						break;
					} elseif ($in_array_counter > 0 && !$this->is_token(array(T_DOUBLE_ARROW), true)) {
						$in_array_counter++;
						$this->append_code($this->get_crlf_indent().$text.$this->debug('[Arr.ArrCounter>0]'));
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
					if ($this->is_token(array(T_COMMENT, T_DOC_COMMENT), true)) {
						$this->append_code($this->get_crlf_indent().$text.$this->get_space());
						break;
					} elseif ($in_array_counter > 0 && 0 == $in_bracket_counter) {
						$this->append_code($text.$this->get_crlf_indent());
						break;
					} else {
						$this->append_code($text.$this->get_space());
						break;
					}
				case T_BREAK:
					$this->append_code($this->get_crlf_indent().$text.$this->debug('[break]'));
					if (!$this->is_token(ST_SEMI_COLON)) {
						$this->append_code($this->get_space(), false);
					}
					break;
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
					array_unshift($in_switch_curly_block, $in_curly_block);
					$in_case_counter = 0;
					$this->append_code($this->get_crlf_indent().$text.$this->get_space(), true);
					break;
				case T_DEFAULT:
					if ($in_case_counter > 0) {
						$in_case_counter--;
						$this->set_indent(-1);
					}
					$in_case_counter++;
					$this->append_code($this->get_crlf_indent().$text, true);
					break;
				case T_CASE:
					if ($in_case_counter > 0) {
						$in_case_counter--;
						$this->set_indent(-1);
					}
					$in_case_counter++;
					$this->append_code($this->get_crlf_indent().$text.$this->debug('[{}:'.$in_curly_block.':case]').$this->get_space(), true);
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
					if (!$this->is_token(ST_CURLY_CLOSE, true)) {
						$in_curly_block++;
					}
					$in_while_counter++;
					$this->append_code($text.$this->get_space(), false);
					break;
				case T_FOR:
					$in_curly_block++;
					$in_for_counter++;
					$this->append_code($text.$this->get_space(), false);
					break;
				case T_FOREACH:
					$in_curly_block++;
					$in_foreach_counter++;
					$this->append_code($text.$this->get_space(), false);
					break;
				case T_DO:
					$in_curly_block++;
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
					$redundant = "";
					$matches   = 0;
					$lines = preg_match_all("/\r?\n/", $text, $matches);
					$lines = $lines > 1?1:0;
					$redundant = $lines > 0?str_repeat($this->new_line, $lines):"";
					$current_indent = $this->get_indent();
					if (substr($this->code, strlen($current_indent)*-1) == $current_indent && $lines > 0) {
						$redundant .= $current_indent;
					}
					$this->append_code($redundant.trim($text).$this->debug("[WS:".$lines."]"), false);
					break;
				case ST_SEMI_COLON:
					if ($this->is_token(array(T_END_HEREDOC), true)) {
						$this->append_code($this->get_crlf_indent().$text.$this->get_crlf_indent(), true);
						break;
					} elseif (0 == $in_for_counter && $in_attribution_counter > 0) {
						$in_attribution_counter--;
						$this->append_code($text.$this->debug('[OFF.at]').$this->get_crlf_indent(), false);
						if ($this->is_token(array(T_ELSE, T_ELSEIF)) && $if_pending > 0) {
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
						} elseif ($this->is_token(array(T_VARIABLE))) {
							$this->set_indent(-1);
							$this->append_code($text.$this->debug('[;.ElseIfArtif}]').$this->get_crlf_indent().'} ', false);
						} else {
							$this->append_code($text.$this->debug('[;.Else}]').$this->get_crlf_indent(), false);
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
					if ($in_if_counter > 0) {
						$in_if_counter--;
						$this->set_indent(+1);
						$this->append_code($this->get_space().$text.$this->debug('[{.if]').$this->get_crlf_indent(), false);
					} elseif ($in_elseif_counter > 0) {
						$in_elseif_counter--;
						$this->set_indent(+1);
						$this->append_code($this->get_space().$text.$this->debug('[{.elseif]').$this->get_crlf_indent(), false);
					} elseif ($in_for_counter > 0) {
						$in_for_counter--;
						$this->set_indent(+1);
						$this->append_code($this->get_space().$text.$this->debug('[{.for]').$this->get_crlf_indent(), false);
					} elseif ($in_foreach_counter > 0) {
						$in_foreach_counter--;
						$this->set_indent(+1);
						$this->append_code($this->get_space().$text.$this->debug('[{.foreach]').$this->get_crlf_indent(), false);
					} elseif ($this->is_token(array(T_COMMENT, T_DOC_COMMENT), true) && $artificial_curly_open) {
						$artificial_curly_open = false;
						$this->append_code($this->get_crlf_indent(), false);
						if ($if_pending > 0) {
							$if_pending--;
						}
					} else {
						$this->set_indent(+1);
						$this->append_code($this->get_space().$text.$this->debug('[{.else]').$this->get_crlf_indent(), false);
					}
					break;
				case ST_CURLY_CLOSE:
					$this->set_indent(-1);
					if ($in_switch_counter > 0 && isset($in_switch_curly_block[0]) && $in_switch_curly_block[0] == $in_curly_block) {
						$this->set_indent(-1);
						array_shift($in_switch_curly_block);
					}
					if (!$this->is_token(ST_CURLY_CLOSE, true) && !$this->is_token(ST_SEMI_COLON, true)) {
						$this->append_code($this->get_crlf_indent().$text.$this->debug('[{}:'.$in_curly_block.':a]'), false);
					} else {
						$this->append_code($this->get_crlf_indent().$text.$this->debug('[{}:'.$in_curly_block.':b]'), true);
					}
					if ($in_do_counter > 0 && $this->is_token(array(T_WHILE))) {
						$this->append_code($this->get_space().$this->debug('[}.DoWhile]'), false);
						$in_do_counter--;
						$in_while_counter--;
						if ($in_curly_block > 0) {
							$in_curly_block--;
						}
					} elseif ($in_foreach_counter > 0) {
						$this->append_code($this->get_crlf_indent().$this->debug('[}.Foreach]'), false);
						$in_foreach_counter--;
					} elseif ($this->is_token(array(T_CATCH, T_ELSE, T_ELSEIF))) {
						if ($in_curly_block > 0) {
							$in_curly_block--;
						}
						$this->append_code($this->get_space().$this->debug('[}.CatchElseElseIf]'), false);
					} elseif ($in_elseif_counter > 0) {
						$in_elseif_counter--;
						if ($in_curly_block > 0) {
							$in_curly_block--;
						}
						$this->append_code($this->get_crlf_indent().$this->debug('[}.ElseIfCounter:'.$in_elseif_counter.']'), true);
					} elseif ($in_if_counter > 0 || $in_curly_block > 0) {
						if ($in_if_counter > 0) {
							$in_if_counter--;
						}
						if ($in_curly_block > 0) {
							$in_curly_block--;
						}
						$this->append_code($this->get_crlf_indent().$this->debug('[}.IfCounter:'.$in_if_counter.$in_curly_block.']'), true);
					} elseif ($in_switch_counter > 0) {
						$in_switch_counter--;
						$this->append_code($this->get_crlf_indent().$this->debug('[}.Switch:'.$in_switch_counter.$in_if_counter.$in_elseif_counter.']'), true);
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
						$this->append_code($text.$this->debug('[).CC.OFF.'.$in_call_counter.']'), false);
						if ($in_function_counter > 0) {
							$in_function_counter--;
							$this->append_code($this->debug('[).CC.InFunc--]'), false);
						} elseif ($in_if_counter > 0 && $this->is_token(array(T_VARIABLE, T_STRING, T_DOC_COMMENT, T_COMMENT))) {
							$this->set_indent(+1);
							$this->append_code(' {'.$this->debug('[).CC.Artif{]').$this->get_crlf_indent(), false);
							$artificial_curly_open = true;
							$if_pending++;
							$in_if_counter--;
						} elseif ($in_elseif_counter > 0 && $this->is_token(array(T_VARIABLE, T_STRING, T_DOC_COMMENT, T_COMMENT))) {
							$this->append_code(' {'.$this->debug("[).CC.ElseIf>0($,str,//).Artif{"));
							$artificial_curly_open = true;
							$if_pending++;
							$in_elseif_counter--;
							$this->set_indent(+1);
							$this->append_code($this->get_crlf_indent(), false);
						} elseif ($in_if_counter > 0 && $this->is_token(array(T_DO, T_FOR, T_FOREACH, T_WHILE, T_FUNCTION, T_RETURN))) {
							$this->append_code($this->debug('[).CC.next:LOOP.IF:'.$in_if_counter.']').$this->get_space(), false);
							$in_if_counter--;
						} elseif ($this->is_token(array(T_DO, T_FOR, T_FOREACH, T_WHILE, T_FUNCTION, T_RETURN, T_SWITCH))) {
							$this->append_code($this->get_space(), false);
							$in_curly_block--;
						} elseif ($in_for_counter > 0) {
							$this->append_code($this->debug("[).CC.For--]"));
							$in_for_counter--;
						}
						break;
					} elseif ($in_function_counter > 0) {
						$in_function_counter--;
						$this->append_code($text.$this->debug('[).InFunc--]'), false);
						if ($in_attribution_counter > 0) {
							$in_attribution_counter--;
						}
						break;
					} elseif ($in_array_counter > 0 && $in_parentheses_counter < $in_array_counter) {
						$this->set_indent(-1);
						$tmp_code = trim($this->code);
						if (!$this->is_token(array(T_DOC_COMMENT, T_COMMENT), true) && ',' != substr($tmp_code, -1, 1) && '(' != substr($tmp_code, -1, 1) && ')' != substr($tmp_code, -1, 1)) {
							$this->append_code(',');
							$this->append_code($this->get_crlf_indent().$text.$this->debug('[).Arr>0.,:"'.substr($tmp_code, -1, 1).'"]'), false);
						} elseif ('(' == substr($tmp_code, -1, 1)) {
							$this->append_code($text.$this->debug('[).Arr>0.substr")":"'.substr($tmp_code, -1, 1).'"]'), true);
						} else {
							$this->append_code($this->get_crlf_indent().$text.$this->debug('[).Arr>0:"'.substr($tmp_code, -1, 1).'"]'), true);
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
						$this->append_code($text.$this->debug('[).ElseIf>0($,str,//).Artif{]').' {'.$this.$this->get_crlf_indent(), false);
						$artificial_curly_open = true;
					} elseif (0 == $in_parentheses_counter && $in_if_counter > 0 && $this->is_token(array(T_VARIABLE, T_STRING, T_DOC_COMMENT, T_COMMENT))) {
						$this->set_indent(+1);
						$this->append_code($text.$this->debug('[).If>0($,str,//).Artif{]').' {'.$this->get_crlf_indent(), false);
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
					$in_curly_block++;
					if ($artificial_curly_close) {
						$artificial_curly_close = false;
					}
					$this->append_code($text.$this->get_space(), false);
					$in_elseif_counter++;
					break;
				case T_ELSE:
					$way_clear = true;
					$in_curly_block++;
					$this->append_code($text, false);
					if ($this->is_token(array(T_DO, T_FOR, T_FOREACH, T_WHILE, T_THROW, T_ECHO, T_CONTINUE, T_RETURN))) {
						$this->append_code($this->get_space());
					} elseif ($this->is_token(array(T_VARIABLE, T_STRING))) {
						$this->set_indent(+1);
						$this->append_code(' {'.$this->get_crlf_indent(), false);
						$if_pending++;
					}
					break;
				case T_IF:
					$way_clear = true;
					$in_curly_block++;
					$in_if_counter++;
					if ($artificial_curly_close) {
						$this->append_code($this->get_crlf_indent(), false);
						$artificial_curly_close = false;
					}
					$this->append_code($this->get_crlf_indent().$text.$this->get_space());
					break;
				case T_OBJECT_OPERATOR:
					$this->append_code($text.$this->debug("[ObjOp]"), false);
					break;
				default:
					$this->append_code($text.$this->debug("[Default:".$id.":".(is_numeric($id)?token_name($id):$id)."]"), false);
					break;
			}
		}
		$ret = $this->align_operators();
		return implode($this->new_line, array_map(function ($v) {
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
			return $this->get_space(false);
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
	private function inspect_token($delta = 1) {
		return $this->get_token($this->tkns[$this->ptr+$delta]);
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
	private function align_operators() {
		if (!$this->options['ALIGN_ASSIGNMENTS']) {
			return $this->code;
		}
		$lines = explode($this->new_line, $this->code);
		$lines_with_equals = [];
		$block_count       = 0;
		foreach ($lines as $idx => $line) {
			if (1 == substr_count($line, '=') && 0 == substr_count($line, '==') && 0 == substr_count($line, '(') && 0 == substr_count($line, '.=') && 0 == substr_count($line, '+=') && 0 == substr_count($line, '-=') && 0 == substr_count($line, '*=') && 0 == substr_count($line, '&=') && 0 == substr_count($line, '|=') && 0 == substr_count($line, '>=') && 0 == substr_count($line, '!=') && 0 == substr_count($line, '<=') && 0 == substr_count($line, '<<=') && 0 == substr_count($line, '>>=') && 0 == substr_count($line, '^=') && 0 == substr_count($line, '%=')) {
				$lines_with_equals[$block_count][] = $idx;
			} else {
				$block_count++;
			}
		}

		foreach ($lines_with_equals as $group) {
			if (1 == sizeof($group)) {
				continue;
			}
			$farthest_equals_sign = 0;
			foreach ($group as $idx) {
				$farthest_equals_sign = max($farthest_equals_sign, strpos($lines[$idx], '='));
			}
			foreach ($group as $idx) {
				$line = $lines[$idx];
				$current_equals = strpos($line, '=');
				$delta = abs($farthest_equals_sign-$current_equals);
				if ($delta > 0) {
					$line = preg_replace('/=/', str_repeat(' ', $delta).'=', $line, 1);
					$lines[$idx] = $line;
				}
			}
		}

		return implode($this->new_line, $lines);
	}
	private function debug($str) {
		if ($this->debug) {
			return $str;
		}
	}
}
class SurrogateToken {


}
