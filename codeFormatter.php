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
	const ALIGNABLE_COMMENT = "\x2 FMT \x3";
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
		// extra comma
		$source = $this->two_commands_in_same_line($source);

		$tmp = $this->add_missing_curly_braces($source);
		while (true) {
			$source = $this->add_missing_curly_braces($tmp);
			if ($source == $tmp) {
				break;
			}
			$tmp = $source;
		}
		$source = $this->normalize_ln_and_ltrim_lines($source);
		$source = $this->merge_paren_close_with_curly_open($source);
		$source = $this->resize_spaces($source);
		//$source = $this->merge_curly_close_and_do_while($source);
		//$source = $this->merge_double_arrow_and_array($source);
		$source = $this->reindent($source);
		$source = $this->reindent_colon_blocks($source);
		if ($this->options['ORDER_USE']) {
			$source = $this->orderUseClauses($source);
		}
		$source = $this->eliminate_duplicated_empty_lines($source);
		if ($this->options['ALIGN_ASSIGNMENTS']) {
			$source = $this->align_operators($source);
		}
		return implode($this->new_line, array_map(function ($v) {
			return rtrim($v);
		}, explode($this->new_line, $source)));
	}

	private function normalize_ln_and_ltrim_lines($source) {
		$this->tkns = token_get_all($source);
		$this->code = '';
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->get_token($token);
			$this->ptr = $index;
			$this->ptr = $index;
			switch ($id) {
				case T_CONSTANT_ENCAPSED_STRING:
					$this->append_code($text, false);
					break;
				default:
					if (substr_count($text, "\r\n")) {
						$text = str_replace("\r\n", $this->new_line, $text);
					}
					if (substr_count($text, "\n\r")) {
						$text = str_replace("\n\r", $this->new_line, $text);
					}
					if (substr_count($text, "\r")) {
						$text = str_replace("\r", $this->new_line, $text);
					}
					if (substr_count($text, "\n")) {
						$text = str_replace("\n", $this->new_line, $text);
					}

					if ($this->substr_count_trailing($text, $this->new_line) > 0) {
						$text = trim($text).str_repeat($this->new_line, $this->substr_count_trailing($text, $this->new_line));
					} elseif (0 == $this->substr_count_trailing($text, $this->new_line) && T_WHITESPACE == $id) {
						$text = $this->get_space().ltrim($text).str_repeat($this->new_line, $this->substr_count_trailing($text, $this->new_line));
					}
					$this->append_code($text, false);
					break;
			}
		}

		return $this->code;
	}

	private function merge_paren_close_with_curly_open($source) {
		$this->tkns = token_get_all($source);
		$this->code = '';
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->get_token($token);
			$this->ptr = $index;
			switch ($id) {
				case ST_CURLY_OPEN:
					if ($this->is_token(ST_PARENTHESES_CLOSE, true)) {
						$this->append_code($text, true);
					} elseif ($this->is_token(array(T_ELSE), true)) {
						$this->append_code($text, true);
					} else {
						$this->append_code($text, false);
					}
					break;
				case T_ELSE:
				case T_ELSEIF:
					if ($this->is_token(ST_CURLY_CLOSE, true)) {
						$this->append_code($text, true);
					} else {
						$this->append_code($text, false);
					}
					break;
				default:
					$this->append_code($text, false);
					break;
			}
		}
		return $this->code;
	}

	private function reindent($source) {
		$this->tkns = token_get_all($source);
		$this->code = '';
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->get_token($token);
			$this->ptr = $index;
			switch ($id) {
				case T_START_HEREDOC:
					$this->append_code($text, false);
					while (list($index, $token) = each($this->tkns)) {
						list($id, $text) = $this->get_token($token);
						$this->ptr = $index;
						switch ($id) {
							case T_END_HEREDOC:
								$this->append_code($text, false);
								break 2;
							default:
								$this->append_code($text, false);
								break;
						}
				}
				break;
			case ST_CURLY_OPEN:
			case ST_PARENTHESES_OPEN:
			case ST_BRACKET_OPEN:
				$this->set_indent(+1);
				$this->append_code($text, false);
				break;
			case ST_CURLY_CLOSE:
			case ST_PARENTHESES_CLOSE:
			case ST_BRACKET_CLOSE:
				$this->set_indent(-1);
				$this->append_code($text, false);
				break;
			default:
				if (substr_count($text, $this->new_line) > 0 && !$this->is_token(ST_CURLY_CLOSE) && !$this->is_token(ST_PARENTHESES_CLOSE) && !$this->is_token(ST_BRACKET_CLOSE)) {
					$text = str_replace($this->new_line, $this->new_line.$this->get_indent(), $text);
				} elseif (substr_count($text, $this->new_line) > 0 && ($this->is_token(ST_CURLY_CLOSE) || $this->is_token(ST_PARENTHESES_CLOSE) || $this->is_token(ST_BRACKET_CLOSE))) {
					$this->set_indent(-1);
					$text = str_replace($this->new_line, $this->new_line.$this->get_indent(), $text);
					$this->set_indent(+1);
				}
				$this->append_code($text, false);
				break;
			}
		}
		return $this->code;
	}
	private function reindent_colon_blocks($source) {
		$this->tkns = token_get_all($source);
		$this->code = '';
		$case_level = 0;
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->get_token($token);
			$this->ptr = $index;
			switch ($id) {
				case T_DEFAULT:
				case T_CASE:
					$this->append_code($text, false);
					$case_level = 1;
					break;
				case ST_CURLY_CLOSE:
					$this->append_code($text, false);
					$case_level = 0;
					break;
				default:
					if (substr_count($text, $this->new_line) > 0 && !$this->is_token(array(T_CASE, T_DEFAULT)) && !$this->is_token(ST_CURLY_CLOSE)) {
						$this->append_code($text.$this->get_indent($case_level), false);
					} elseif (substr_count($text, $this->new_line) > 0 && $this->is_token(array(T_CASE, T_DEFAULT))) {
						$case_level = 0;
						$this->append_code($text.$this->get_indent($case_level), false);
					} else {
						$this->append_code($text, false);
					}
					break;
			}
		}

		return $this->code;
	}
	private function two_commands_in_same_line($source) {
		$lines = explode($this->new_line, $source);
		foreach ($lines as $idx => $line) {
			if (substr_count($line, ';') <= 1) {
				continue;
			}
			$new_line           = '';
			$ignore_stack       = 0;
			$double_quote_state = false;
			$single_quote_state = false;
			$len = strlen($line);
			for ($i = 0;$i < $len;$i++) {
				$char = substr($line, $i, 1);
				if (ST_PARENTHESES_OPEN == $char || ST_PARENTHESES_OPEN == $char || ST_CURLY_OPEN == $char || ST_BRACKET_OPEN == $char) {
					$ignore_stack++;
				}
				if (ST_PARENTHESES_CLOSE == $char || ST_CURLY_CLOSE == $char || ST_BRACKET_CLOSE == $char) {
					$ignore_stack--;
				}
				if ('"' == $char) {
					$double_quote_state = !$double_quote_state;
				}
				if ("'" == $char) {
					$single_quote_state = !$single_quote_state;
				}
				$new_line .= $char;
				if (!$single_quote_state && !$double_quote_state && 0 == $ignore_stack && ST_SEMI_COLON == $char && $i+1 < $len) {
					$new_line .= $this->new_line;
				}
			}
			$lines[$idx] = $new_line;
		}
		return implode($this->new_line, $lines);
	}

	private function add_missing_curly_braces($source) {
		$this->tkns = token_get_all($source);
		$this->code = '';
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->get_token($token);
			$this->ptr = $index;
			switch ($id) {
				case T_FOR:
					$this->append_code($text, false);
					$paren_count = null;
					while (list($index, $token) = each($this->tkns)) {
						list($id, $text) = $this->get_token($token);
						$this->ptr = $index;
						if (ST_PARENTHESES_OPEN == $id) {
							$paren_count++;
						} elseif (ST_PARENTHESES_CLOSE == $id) {
							$paren_count--;
						}
						$this->append_code($text, false);
						if (0 === $paren_count && !$this->is_token(array(T_COMMENT, T_DOC_COMMENT))) {
							break;
						}
					}
					if (!$this->is_token(ST_CURLY_OPEN) && !$this->is_token(ST_COLON)) {
						$ignore_count = 0;
						if (!$this->is_token(array(T_COMMENT, T_DOC_COMMENT), true)) {
							$this->append_code($this->new_line.'{');
						} else {
							$this->append_code('{');
						}
						while (list($index, $token) = each($this->tkns)) {
							list($id, $text) = $this->get_token($token);
							$this->ptr = $index;
							if (ST_PARENTHESES_OPEN == $id || ST_CURLY_OPEN == $id || ST_BRACKET_OPEN == $id) {
								$ignore_count++;
							} elseif (ST_PARENTHESES_CLOSE == $id || ST_CURLY_CLOSE == $id || ST_BRACKET_CLOSE == $id) {
								$ignore_count--;
							}
							$this->append_code($text, false);
							if ($ignore_count <= 0 && !($this->is_token(ST_CURLY_CLOSE) || $this->is_token(ST_SEMI_COLON) || $this->is_token(array(T_WHILE))) && (ST_CURLY_CLOSE == $id || ST_SEMI_COLON == $id || T_ELSE == $id || T_ELSEIF == $id)) {
								break;
							}
						}
						$this->append_code($this->get_crlf_indent().'}'.$this->get_crlf_indent(), false);
						break 2;
				}
				break;
			case T_IF:
			case T_ELSEIF:
				$this->append_code($text, false);
				$paren_count = null;
				while (list($index, $token) = each($this->tkns)) {
					list($id, $text) = $this->get_token($token);
					$this->ptr = $index;
					if (ST_PARENTHESES_OPEN == $id) {
						$paren_count++;
					} elseif (ST_PARENTHESES_CLOSE == $id) {
						$paren_count--;
					}
					$this->append_code($text, false);
					if (0 === $paren_count && !$this->is_token(array(T_COMMENT, T_DOC_COMMENT))) {
						break;
					}
				}
				if (!$this->is_token(ST_CURLY_OPEN) && !$this->is_token(ST_COLON)) {
					$ignore_count = 0;
					if (!$this->is_token(array(T_COMMENT, T_DOC_COMMENT), true)) {
						// $this->append_code($this->new_line.'{'.$this->new_line);
						$this->append_code($this->new_line.'{');
					} else {
						// $this->append_code('{'.$this->new_line);
						$this->append_code('{');
					}
					while (list($index, $token) = each($this->tkns)) {
						list($id, $text) = $this->get_token($token);
						$this->ptr = $index;
						if (ST_PARENTHESES_OPEN == $id || ST_CURLY_OPEN == $id || ST_BRACKET_OPEN == $id) {
							$ignore_count++;
						} elseif (ST_PARENTHESES_CLOSE == $id || ST_CURLY_CLOSE == $id || ST_BRACKET_CLOSE == $id) {
							$ignore_count--;
						}
						$this->append_code($text, false);
						if ($ignore_count <= 0 && !($this->is_token(ST_CURLY_CLOSE) || $this->is_token(ST_SEMI_COLON) || $this->is_token(array(T_WHILE))) && (ST_CURLY_CLOSE == $id || ST_SEMI_COLON == $id || T_ELSE == $id || T_ELSEIF == $id)) {
							break;
						}
					}
					$this->append_code($this->get_crlf_indent().'}'.$this->get_crlf_indent(), false);
					break 2;
				}
				break;
			case T_ELSE:
				$this->append_code($text, false);
				if (!$this->is_token(ST_CURLY_OPEN) && !$this->is_token(ST_COLON)) {
					$ignore_count = 0;
					$this->append_code('{'.$this->new_line);
					while (list($index, $token) = each($this->tkns)) {
						list($id, $text) = $this->get_token($token);
						$this->ptr = $index;
						if (ST_PARENTHESES_OPEN == $id || ST_CURLY_OPEN == $id || ST_BRACKET_OPEN == $id) {
							$ignore_count++;
						} elseif (ST_PARENTHESES_CLOSE == $id || ST_CURLY_CLOSE == $id || ST_BRACKET_CLOSE == $id) {
							$ignore_count--;
						}
						$this->append_code($text, false);
						if ($ignore_count <= 0 && !($this->is_token(ST_CURLY_CLOSE) || $this->is_token(ST_SEMI_COLON) || $this->is_token(array(T_WHILE))) && (ST_CURLY_CLOSE == $id || ST_SEMI_COLON == $id || T_ELSE == $id || T_ELSEIF == $id)) {
							break;
						}
					}
					$this->append_code($this->get_crlf_indent().'}'.$this->get_crlf_indent(), false);
					break 2;
				}
				break;
			default:
				$this->append_code($text, false);
				break;
			}
		}
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->get_token($token);
			$this->ptr = $index;
			$this->append_code($text, false);
		}

		return $this->code;
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
	private function get_indent_level() {
		return $this->indent;
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
		if (!isset($this->tkns[$this->ptr+$delta])) {
			return [null, null];
		}
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
	private function prev_token() {
		$i = $this->ptr;
		while (--$i >= 0 && is_array($this->tkns[$i]) && $this->tkns[$i][0] == T_WHITESPACE);
		return $this->tkns[$i];
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
	private function has_ln_after() {
		$id   = null;
		$text = null;
		list($id, $text) = $this->inspect_token();
		return T_WHITESPACE == $id && substr_count($text, PHP_EOL) > 0;
	}
	private function has_ln_before() {
		$id   = null;
		$text = null;
		list($id, $text) = $this->inspect_token(-1);
		return T_WHITESPACE == $id && substr_count($text, PHP_EOL) > 0;
	}
	private function has_ln_prev_token() {
		list($id, $text) = $this->get_token($this->prev_token());
		return substr_count($text, PHP_EOL) > 0;
	}
	private function count_ln_before() {
		$id   = null;
		$text = null;
		list($id, $text) = $this->inspect_token(-1);
		$count_ln = substr_count($text, PHP_EOL);
		if (T_WHITESPACE == $id && $count_ln > 0) {
			return $count_ln;
		} else {
			return false;
		}
	}
	private function eliminate_duplicated_empty_lines($source) {
		$lines = explode($this->new_line, $source);
		$empty_lines_chunks = [];
		$block_count        = 0;
		foreach ($lines as $idx => $line) {
			if ('' == trim($line)) {
				$empty_lines_chunks[$block_count][] = $idx;
			} else {
				$block_count++;
			}
		}
		foreach ($empty_lines_chunks as $group) {
			if (1 == sizeof($group)) {
				continue;
			}

			array_shift($group);
			foreach ($group as $idx) {
				unset($lines[$idx]);
			}
		}

		return implode($this->new_line, $lines);
	}
	private function align_operators($source) {
		$lines = explode($this->new_line, $source);
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

		$lines_with_obj_operator = [];
		$block_count             = 0;
		foreach ($lines as $idx => $line) {
			// replicate this elsewhere
			$line_comment = strpos($line, '//');
			$block_comment = strpos($line, '/*');
			if (false !== $line_comment && strpos($line, '->') >= $line_comment) {
				$block_count++;
			} elseif (false !== $block_comment && strpos($line, '->') >= $block_comment) {
				$block_count++;
			} elseif (substr_count($line, '->') > 0 && 0 == substr_count($line, '{') && 0 == substr_count($line, '||') && 0 == substr_count($line, '&&') && 0 == substr_count($line, '=>') && 0 == substr_count($line, '=')) {
				$lines_with_obj_operator[$block_count][] = $idx;
			} else {
				$block_count++;
			}
		}

		foreach ($lines_with_obj_operator as $group) {
			if (1 == sizeof($group)) {
				continue;
			}
			$farthest_obj_op = 0;
			foreach ($group as $idx) {
				$farthest_obj_op = max($farthest_obj_op, strpos($lines[$idx], '->'));
			}
			foreach ($group as $idx) {
				$line = $lines[$idx];
				$current_obj_op = strpos($line, '->');
				$delta = abs($farthest_obj_op-$current_obj_op);
				if ($delta > 0) {
					$line = preg_replace('/->/', str_repeat(' ', $delta).'->', $line, 1);
					$lines[$idx] = $line;
				}
			}
		}

		$lines_with_alignable_comments = [];
		$block_count                   = 0;
		foreach ($lines as $idx => $line) {
			if (substr_count($line, self::ALIGNABLE_COMMENT) > 0 && strpos($line, self::ALIGNABLE_COMMENT) > 0) {
				$lines_with_alignable_comments[$block_count][] = $idx;
			} else {
				$block_count++;
			}
		}

		foreach ($lines_with_alignable_comments as $group) {
			if (1 == sizeof($group)) {
				continue;
			}
			$farthest_comment = 0;
			foreach ($group as $idx) {
				$farthest_comment = max($farthest_comment, strpos($lines[$idx], self::ALIGNABLE_COMMENT));
			}
			foreach ($group as $idx) {
				$line = $lines[$idx];
				$current_comment = strpos($line, self::ALIGNABLE_COMMENT);
				$delta = abs($farthest_comment-$current_comment);
				if ($delta > 0) {
					$line = str_replace(self::ALIGNABLE_COMMENT, str_repeat(' ', $delta).self::ALIGNABLE_COMMENT, $line);
					$lines[$idx] = $line;
				}
			}
		}
		$ret = implode($this->new_line, $lines);
		return str_replace(self::ALIGNABLE_COMMENT, '//', $ret);
	}
	public function resize_spaces($source) {
		$new_tokens = [];
		$this->tkns = token_get_all($source);
		$this->code = '';
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->get_token($token);
			$this->ptr = $index;
			switch ($id) {
				case T_WHITESPACE:
					if (0 == substr_count($text, $this->new_line)) {
						break;
					}
				default:
					$new_tokens[] = $token;
			}
		}

		$this->tkns = $new_tokens;
		$this->code = '';
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->get_token($token);
			$this->ptr = $index;
			switch ($id) {
				case T_ARRAY:
					if ($this->is_token(array(T_VARIABLE))) {
						$this->append_code($text.$this->get_space(), false);
						break;
					}
				case ST_CURLY_OPEN:
					if ($this->is_token(array(T_STRING), true)) {
						$this->append_code($this->get_space().$text, false);
						break;
					} elseif ($this->is_token(ST_CURLY_CLOSE)) {
						$this->append_code($text, false);
						break;
					}
				case ST_SEMI_COLON:
					if ($this->is_token(array(T_VARIABLE))) {
						$this->append_code($text.$this->get_space(), false);
						break;
					}
				case ST_PARENTHESES_CLOSE:
					if ($this->is_token(ST_CURLY_OPEN)) {
						$this->append_code($text.$this->get_space(), false);
						break;
					} elseif ($this->is_token(array(T_OBJECT_OPERATOR)) || $this->is_token(ST_SEMI_COLON) || $this->is_token(ST_PARENTHESES_CLOSE)) {
						$this->append_code($text, false);
						break;
					}
				case T_RETURN:
				case T_YIELD:
				case T_ECHO:
				case T_NAMESPACE:
				case T_USE:
				case T_NEW:
				case T_CONST:
				case T_FINAL:
				case T_CASE:
				case T_BREAK:
					if ($this->is_token(ST_SEMI_COLON)) {
						$this->append_code($text, false);
					} else {

						$this->append_code($text.$this->get_space(), false);
					}
					break;
				case T_WHILE:
					if ($this->is_token(ST_SEMI_COLON)) {
						$this->append_code($text.$this->get_space(), false);
						break;
					}
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
				case T_DOUBLE_ARROW:
				case T_FUNCTION:
				case T_IF:
				case T_FOR:
				case T_SWITCH:
				case ST_COMMA:
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
				case T_AS:
				case T_ELSE:
				case T_ELSEIF:
				case ST_EQUAL:
					$this->append_code($this->get_space().$text.$this->get_space(), false);
					break;
				case T_ARRAY_CAST:
				case T_BOOL_CAST:
				case T_DOUBLE_CAST:
				case T_INT_CAST:
				case T_OBJECT_CAST:
				case T_STRING_CAST:
				case T_UNSET_CAST:
					$this->append_code($text.$this->get_space(), false);
					break;
				default:
					$this->append_code($text, false);
					break;
			}
		}

		return $this->code;
	}
	private function substr_count_trailing($haystack, $needle) {
		$cnt = 0;
		$i = strlen($haystack)-1;
		for ($i = $i;$i >= 0;$i--) {
			$char = substr($haystack, $i, 1);
			if ($needle == $char) {
				$cnt++;
			} elseif (' ' != $char && "\t" != $char) {
				break;
			}
		}
		return $cnt;
	}
	private function debug($str) {
		if ($this->debug) {
			return $str;
		}
	}
}
class SurrogateToken {
}
