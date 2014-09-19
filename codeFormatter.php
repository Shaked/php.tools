<?php
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
if (!defined("T_POW")) {
	define("T_POW", "**");
}
if (!defined("T_YIELD")) {
	define("T_YIELD", "yield");
}
if (!defined("T_FINALLY")) {
	define("T_FINALLY", "finally");
}
final class AddMissingCurlyBraces extends FormatterPass {
	public function format($source) {
		$tmp = $this->addBraces($source);
		while (true) {
			$source = $this->addBraces($tmp);
			if ($source === $tmp) {
				break;
			}
			$tmp = $source;
		}
		return $source;
	}
	private function addBraces($source) {
		$this->tkns = token_get_all($source);
		$this->code = '';
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->get_token($token);
			$this->ptr       = $index;
			switch ($id) {
				case T_FOREACH:
				case T_FOR:
					$this->append_code($text, false);
					$paren_count = null;
					while (list($index, $token) = each($this->tkns)) {
						list($id, $text) = $this->get_token($token);
						$this->ptr       = $index;
						if (ST_PARENTHESES_OPEN === $id) {
							$paren_count++;
						} elseif (ST_PARENTHESES_CLOSE === $id) {
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
							$this->append_code($this->new_line . '{');
						} else {
							$this->append_code('{');
						}
						while (list($index, $token) = each($this->tkns)) {
							list($id, $text) = $this->get_token($token);
							$this->ptr       = $index;
							if (ST_PARENTHESES_OPEN === $id || ST_CURLY_OPEN === $id || ST_BRACKET_OPEN === $id) {
								$ignore_count++;
							} elseif (ST_PARENTHESES_CLOSE === $id || ST_CURLY_CLOSE === $id || ST_BRACKET_CLOSE === $id) {
								$ignore_count--;
							}
							$this->append_code($text, false);
							if ($ignore_count <= 0 && !($this->is_token(ST_CURLY_CLOSE) || $this->is_token(ST_SEMI_COLON) || $this->is_token(array(T_WHILE))) && (ST_CURLY_CLOSE === $id || ST_SEMI_COLON === $id || T_ELSE === $id || T_ELSEIF === $id)) {
								break;
							}
						}
						$this->append_code($this->get_crlf_indent() . '}' . $this->get_crlf_indent(), false);
						break 2;
					}
					break;
				case T_IF:
				case T_ELSEIF:
					$this->append_code($text, false);
					$paren_count = null;
					while (list($index, $token) = each($this->tkns)) {
						list($id, $text) = $this->get_token($token);
						$this->ptr       = $index;
						if (ST_PARENTHESES_OPEN === $id) {
							$paren_count++;
						} elseif (ST_PARENTHESES_CLOSE === $id) {
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
							$this->append_code($this->new_line . '{');
						} else {
							// $this->append_code('{'.$this->new_line);
							$this->append_code('{');
						}
						while (list($index, $token) = each($this->tkns)) {
							list($id, $text) = $this->get_token($token);
							$this->ptr       = $index;
							if (ST_PARENTHESES_OPEN === $id || ST_CURLY_OPEN === $id || ST_BRACKET_OPEN === $id) {
								$ignore_count++;
							} elseif (ST_PARENTHESES_CLOSE === $id || ST_CURLY_CLOSE === $id || ST_BRACKET_CLOSE === $id) {
								$ignore_count--;
							}
							$this->append_code($text, false);
							if ($ignore_count <= 0 && !($this->is_token(ST_CURLY_CLOSE) || $this->is_token(ST_SEMI_COLON) || $this->is_token(array(T_WHILE))) && (ST_CURLY_CLOSE === $id || ST_SEMI_COLON === $id || T_ELSE === $id || T_ELSEIF === $id)) {
								break;
							}
						}
						$this->append_code($this->get_crlf_indent() . '}' . $this->get_crlf_indent(), false);
						break 2;
					}
					break;
				case T_ELSE:
					$this->append_code($text, false);
					if (!$this->is_token(ST_CURLY_OPEN) && !$this->is_token(ST_COLON) && !$this->is_token(array(T_IF))) {
						$ignore_count = 0;
						$this->append_code('{' . $this->new_line);
						while (list($index, $token) = each($this->tkns)) {
							list($id, $text) = $this->get_token($token);
							$this->ptr       = $index;
							if (ST_PARENTHESES_OPEN === $id || ST_CURLY_OPEN === $id || ST_BRACKET_OPEN === $id) {
								$ignore_count++;
							} elseif (ST_PARENTHESES_CLOSE === $id || ST_CURLY_CLOSE === $id || ST_BRACKET_CLOSE === $id) {
								$ignore_count--;
							}
							$this->append_code($text, false);
							if ($ignore_count <= 0 && !($this->is_token(ST_CURLY_CLOSE) || $this->is_token(ST_SEMI_COLON) || $this->is_token(array(T_WHILE))) && (ST_CURLY_CLOSE === $id || ST_SEMI_COLON === $id || T_ELSE === $id || T_ELSEIF === $id)) {
								break;
							}
						}
						$this->append_code($this->get_crlf_indent() . '}' . $this->get_crlf_indent(), false);
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
			$this->ptr       = $index;
			$this->append_code($text, false);
		}

		return $this->code;
	}
}

final class AlignDoubleArrow extends FormatterPass {
	const ALIGNABLE_EQUAL = "\x2 EQUAL%d \x3";
	public function format($source) {
		$this->tkns = token_get_all($source);
		$this->code = '';

		$context_counter = 0;
		$in_bracket      = 0;
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->get_token($token);
			$this->ptr       = $index;
			switch ($id) {
				case T_FOREACH:
				case ST_SEMI_COLON:
				case T_ARRAY:
					$context_counter++;
					$this->append_code($text, false);
					break;

				case T_DOUBLE_ARROW:
					$this->append_code(sprintf(self::ALIGNABLE_EQUAL, $context_counter) . $text, false);
					break;

				case ST_BRACKET_OPEN:
					if ($this->is_token(array(T_DOUBLE_ARROW), true)) {
						$context_counter++;
					}
					$this->append_code($text, false);
					break;

				default:
					$this->append_code($text, false);
					break;
			}
		}

		for ($j = 0; $j <= $context_counter; $j++) {
			$placeholder      = sprintf(self::ALIGNABLE_EQUAL, $j);
			$lines            = explode($this->new_line, $this->code);
			$lines_with_objop = [];
			$block_count      = 0;

			foreach ($lines as $idx => $line) {
				if (substr_count($line, $placeholder) > 0) {
					$lines_with_objop[$block_count][] = $idx;
				} else {
					$block_count++;
				}
			}

			$i = 0;
			foreach ($lines_with_objop as $group) {
				if (1 === sizeof($group)) {
					continue;
				}
				$i++;
				$farthest_objop = 0;
				foreach ($group as $idx) {
					$farthest_objop = max($farthest_objop, strpos($lines[$idx], $placeholder));
				}
				foreach ($group as $idx) {
					$line          = $lines[$idx];
					$current_objop = strpos($line, $placeholder);
					$delta         = abs($farthest_objop - $current_objop);
					if ($delta > 0) {
						$line        = str_replace($placeholder, str_repeat(' ', $delta) . $placeholder, $line);
						$lines[$idx] = $line;
					}
				}
			}

			$this->code = str_replace($placeholder, '', implode($this->new_line, $lines));
		}
		return $this->code;
	}
}

final class AlignEquals extends FormatterPass {
	const ALIGNABLE_EQUAL = "\x2 EQUAL%d \x3";
	public function format($source) {
		$this->tkns      = token_get_all($source);
		$this->code      = '';
		$paren_count     = 0;
		$bracket_count   = 0;
		$context_counter = 0;
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->get_token($token);
			$this->ptr       = $index;
			switch ($id) {
				case T_FUNCTION:
					$context_counter++;
					$this->append_code($text, false);
					break;
				case ST_PARENTHESES_OPEN:
					$paren_count++;
					$this->append_code($text, false);
					break;
				case ST_PARENTHESES_CLOSE:
					$paren_count--;
					$this->append_code($text, false);
					break;
				case ST_BRACKET_OPEN:
					$bracket_count++;
					$this->append_code($text, false);
					break;
				case ST_BRACKET_CLOSE:
					$bracket_count--;
					$this->append_code($text, false);
					break;
				case ST_EQUAL:
					if (!$paren_count && !$bracket_count) {
						$this->append_code(sprintf(self::ALIGNABLE_EQUAL, $context_counter) . $text, false);
						break;
					}

				default:
					$this->append_code($text, false);
					break;
			}
		}

		for ($j = 0; $j <= $context_counter; $j++) {
			$placeholder      = sprintf(self::ALIGNABLE_EQUAL, $j);
			$lines            = explode($this->new_line, $this->code);
			$lines_with_objop = [];
			$block_count      = 0;

			foreach ($lines as $idx => $line) {
				if (substr_count($line, $placeholder) > 0) {
					$lines_with_objop[$block_count][] = $idx;
				} else {
					$block_count++;
				}
			}

			$i = 0;
			foreach ($lines_with_objop as $group) {
				if (1 === sizeof($group)) {
					continue;
				}
				$i++;
				$farthest_objop = 0;
				foreach ($group as $idx) {
					$farthest_objop = max($farthest_objop, strpos($lines[$idx], $placeholder));
				}
				foreach ($group as $idx) {
					$line          = $lines[$idx];
					$current_objop = strpos($line, $placeholder);
					$delta         = abs($farthest_objop - $current_objop);
					if ($delta > 0) {
						$line        = str_replace($placeholder, str_repeat(' ', $delta) . $placeholder, $line);
						$lines[$idx] = $line;
					}
				}
			}

			$this->code = str_replace($placeholder, '', implode($this->new_line, $lines));
		}

		return $this->code;
	}
}

final class CodeFormatter {
	private $passes = [];

	public function addPass(FormatterPass $pass) {
		$this->passes[] = $pass;
	}

	public function formatCode($source = '') {
		gc_enable();
		$passes = array_map(
			function ($pass) {
				return clone $pass;
			},
			$this->passes
		);
		while ($pass = array_shift($passes)) {
			$source = $pass->format($source);
			gc_collect_cycles();
		}
		gc_disable();
		return $source;
	}
}

final class EliminateDuplicatedEmptyLines extends FormatterPass {
	const ALIGNABLE_EQUAL = "\x2 EQUAL%d \x3";
	public function format($source) {
		$this->tkns    = token_get_all($source);
		$this->code    = '';
		$paren_count   = 0;
		$bracket_count = 0;
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->get_token($token);
			$this->ptr       = $index;
			switch ($id) {
				case T_WHITESPACE:
					$text = str_replace($this->new_line, self::ALIGNABLE_EQUAL . $this->new_line, $text);
					$this->append_code($text, false);
					break;
				default:
					$this->append_code($text, false);
					break;
			}
		}

		$lines            = explode($this->new_line, $this->code);
		$lines_with_objop = [];
		$block_count      = 0;

		foreach ($lines as $idx => $line) {
			if (trim($line) === self::ALIGNABLE_EQUAL) {
				//if (substr_count($line, self::ALIGNABLE_EQUAL) > 0) {
				$lines_with_objop[$block_count][] = $idx;
			} else {
				$block_count++;
			}
		}

		$i = 0;
		foreach ($lines_with_objop as $group) {
			if (1 === sizeof($group)) {
				continue;
			}
			array_pop($group);
			foreach ($group as $line_number) {
				unset($lines[$line_number]);
			}
		}

		$this->code = str_replace(self::ALIGNABLE_EQUAL, '', implode($this->new_line, $lines));

		$tkns            = token_get_all($this->code);
		list($id, $text) = $this->get_token(array_pop($tkns));
		if (T_WHITESPACE === $id && '' === trim($text)) {
			$this->code = rtrim($this->code) . $this->new_line;
		}

		return $this->code;
	}
}

final class ExtraCommaInArray extends FormatterPass {
	public function format($source) {
		$this->tkns = token_get_all($source);
		$this->code = '';

		$context_stack = [];
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->get_token($token);
			$this->ptr       = $index;
			switch ($id) {
				case T_STRING:
					if ($this->is_token(ST_PARENTHESES_OPEN)) {
						array_unshift($context_stack, T_STRING);
					}
					$this->append_code($text, false);
					break;
				case T_ARRAY:
					if ($this->is_token(ST_PARENTHESES_OPEN)) {
						array_unshift($context_stack, T_ARRAY);
					}
					$this->append_code($text, false);
					break;
				case ST_PARENTHESES_OPEN:
					if (isset($context_stack[0]) && $this->is_token(ST_PARENTHESES_CLOSE)) {
						array_shift($context_stack);
					}
					$this->append_code($text, false);
					break;
				case ST_PARENTHESES_CLOSE:
					if (isset($context_stack[0])) {
						array_shift($context_stack);
					}
					$this->append_code($text, false);
					break;
				default:
					if (isset($context_stack[0]) && T_ARRAY === $context_stack[0] && $this->is_token(ST_PARENTHESES_CLOSE)) {
						array_shift($context_stack);
						if (ST_COMMA === $id || T_COMMENT === $id || T_DOC_COMMENT === $id || !$this->has_ln_after()) {
							$this->append_code($text, false);
						} else {
							$this->append_code($text . ',', false);
						}
						break;
					} else {
						$this->append_code($text, false);
					}
					break;
			}
		}
		return $this->code;
	}
}

abstract class FormatterPass {
	protected $indent_size = 1;
	protected $indent_char = "\t";
	protected $block_size  = 1;
	protected $new_line    = "\n";
	protected $indent      = 0;
	protected $for_idx     = 0;
	protected $code        = '';
	protected $ptr         = 0;
	protected $tkns        = 0;
	abstract public function format($source);
	protected function get_token($token) {
		if (is_string($token)) {
			return array($token, $token);
		} else {
			return $token;
		}
	}
	protected function append_code($code = "", $trim = true) {
		if ($trim) {
			$this->code = rtrim($this->code) . $code;
		} else {
			$this->code .= $code;
		}
	}
	protected function get_crlf_indent($in_for = false, $increment = 0) {
		if ($in_for) {
			$this->for_idx++;
			if ($this->for_idx > 2) {
				$this->for_idx = 0;
			}
		}
		if ($this->for_idx === 0 || !$in_for) {
			return $this->get_crlf() . $this->get_indent($increment);
		} else {
			return $this->get_space(false);
		}
	}
	protected function get_crlf($true = true) {
		return $true ? $this->new_line : "";
	}
	protected function get_space($true = true) {
		return $true ? " " : "";
	}
	protected function get_indent($increment = 0) {
		return str_repeat($this->indent_char, ($this->indent + $increment) * $this->indent_size);
	}
	protected function set_indent($increment) {
		$this->indent += $increment;
		if ($this->indent < 0) {
			$this->indent = 0;
		}
	}
	protected function inspect_token($delta = 1) {
		if (!isset($this->tkns[$this->ptr + $delta])) {
			return [null, null];
		}
		return $this->get_token($this->tkns[$this->ptr + $delta]);
	}
	protected function is_token($token, $prev = false, $i = 99999, $idx = false) {
		if ($i === 99999) {
			$i = $this->ptr;
		}
		if ($prev) {
			while (--$i >= 0 && is_array($this->tkns[$i]) && $this->tkns[$i][0] === T_WHITESPACE);
		} else {
			while (++$i < sizeof($this->tkns) - 1 && is_array($this->tkns[$i]) && $this->tkns[$i][0] === T_WHITESPACE);
		}
		if (isset($this->tkns[$i]) && is_string($this->tkns[$i]) && $this->tkns[$i] === $token) {
			return $idx ? $i : true;
		} elseif (is_array($token) && isset($this->tkns[$i]) && is_array($this->tkns[$i])) {
			if (in_array($this->tkns[$i][0], $token)) {
				return $idx ? $i : true;
			} elseif ($prev && $this->tkns[$i][0] === T_OPEN_TAG) {
				return $idx ? $i : true;
			}
		}
		return false;
	}
	protected function prev_token() {
		$i = $this->ptr;
		while (--$i >= 0 && is_array($this->tkns[$i]) && $this->tkns[$i][0] === T_WHITESPACE);
		return $this->tkns[$i];
	}
	protected function has_ln_after() {
		$id              = null;
		$text            = null;
		list($id, $text) = $this->inspect_token();
		return T_WHITESPACE === $id && substr_count($text, $this->new_line) > 0;
	}
	protected function has_ln_before() {
		$id              = null;
		$text            = null;
		list($id, $text) = $this->inspect_token(-1);
		return T_WHITESPACE === $id && substr_count($text, $this->new_line) > 0;
	}
	protected function has_ln_prev_token() {
		list($id, $text) = $this->get_token($this->prev_token());
		return substr_count($text, $this->new_line) > 0;
	}
	protected function substr_count_trailing($haystack, $needle) {
		$cnt = 0;
		$i   = strlen($haystack) - 1;
		for ($i = $i; $i >= 0; $i--) {
			$char = substr($haystack, $i, 1);
			if ($needle === $char) {
				$cnt++;
			} elseif (' ' !== $char && "\t" !== $char) {
				break;
			}
		}
		return $cnt;
	}
}
final class LeftAlignComment extends FormatterPass {
	const NON_INDENTABLE_COMMENT = "/*\x2 COMMENT \x3*/";
	public function format($source) {
		$this->tkns = token_get_all($source);
		$this->code = '';
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->get_token($token);
			$this->ptr       = $index;
			if ($text === self::NON_INDENTABLE_COMMENT) {
				continue;
			}
			switch ($id) {
				case T_COMMENT:
				case T_DOC_COMMENT:
					list(, $prev_text) = $this->inspect_token(-1);
					if ($prev_text === self::NON_INDENTABLE_COMMENT) {
						$lines = explode($this->new_line, $text);
						$lines = array_map(function ($v) {
							$v = ltrim($v);
							if ('*' === substr($v, 0, 1)) {
								$v = ' ' . $v;
							}
							return $v;
						}, $lines);
						$this->append_code(implode($this->new_line, $lines), false);
						break;
					}
				case T_WHITESPACE:
					list(, $next_text) = $this->inspect_token(1);
					if ($next_text === self::NON_INDENTABLE_COMMENT && substr_count($text, "\n") >= 2) {
						$text = substr($text, 0, strrpos($text, "\n") + 1);
						$this->append_code($text, false);
						break;
					} elseif ($next_text === self::NON_INDENTABLE_COMMENT && substr_count($text, "\n") === 1) {
						$text = substr($text, 0, strrpos($text, "\n") + 1);
						$this->append_code($text, false);
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

final class MergeCurlyCloseAndDoWhile extends FormatterPass {
	public function format($source) {
		$this->tkns = token_get_all($source);
		$this->code = '';
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->get_token($token);
			$this->ptr       = $index;
			switch ($id) {
				case T_WHILE:
					$str                   = $text;
					list($pt_id, $pt_text) = $this->get_token($this->prev_token());
					while (list($index, $token) = each($this->tkns)) {
						list($id, $text) = $this->get_token($token);
						$this->ptr       = $index;
						$str .= $text;
						if (
							ST_CURLY_OPEN == $id ||
							ST_COLON == $id ||
							(ST_SEMI_COLON == $id && (ST_CURLY_OPEN == $pt_id || T_COMMENT == $pt_id || T_DOC_COMMENT == $pt_id))
						) {
							$this->append_code($str, false);
							break;
						} elseif (ST_SEMI_COLON == $id && !(ST_CURLY_OPEN == $pt_id || T_COMMENT == $pt_id || T_DOC_COMMENT == $pt_id)) {
							$this->append_code($str);
							break;
						}
					}
					break;
				default:
					$this->append_code($text, false);
					break;
			}
		}
		return $this->code;
	}
}

final class MergeDoubleArrowAndArray extends FormatterPass {
	public function format($source) {
		$this->tkns          = token_get_all($source);
		$this->code          = '';
		$in_do_while_context = 0;
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->get_token($token);
			$this->ptr       = $index;
			switch ($id) {
				case T_ARRAY:
					if ($this->is_token(array(T_DOUBLE_ARROW), true)) {
						$in_do_while_context--;
						$this->append_code($text);
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

final class MergeParenCloseWithCurlyOpen extends FormatterPass {
	public function format($source) {
		$this->tkns = token_get_all($source);
		$this->code = '';
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->get_token($token);
			$this->ptr       = $index;
			switch ($id) {
				case ST_CURLY_OPEN:
					if ($this->is_token(ST_PARENTHESES_CLOSE, true)) {
						$this->append_code($text, true);
					} elseif ($this->is_token(array(T_ELSE, T_STRING), true)) {
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
}

final class NormalizeLnAndLtrimLines extends FormatterPass {
	public function format($source) {
		$this->tkns = token_get_all($source);
		$this->code = '';
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->get_token($token);
			$this->ptr       = $index;
			$this->ptr       = $index;
			switch ($id) {
				case T_COMMENT:
				case T_DOC_COMMENT:
					list($prev_id, $prev_text) = $this->inspect_token(-1);

					$prev_text = strrev($prev_text);
					$first_ln  = strpos($prev_text, "\n");
					$second_ln = strpos($prev_text, "\n", $first_ln + 1);
					if ($prev_id === T_WHITESPACE && substr_count($prev_text, "\n") >= 2 && 0 === $first_ln && 1 === $second_ln) {
						$this->append_code(LeftAlignComment::NON_INDENTABLE_COMMENT, false);
					} elseif ($prev_id === T_WHITESPACE && "\n" === $prev_text) {
						$this->append_code(LeftAlignComment::NON_INDENTABLE_COMMENT, false);
					}

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
					$lines = explode($this->new_line, $text);
					$lines = array_map(function ($v) {
						$v = ltrim($v);
						if ('*' === substr($v, 0, 1)) {
							$v = ' ' . $v;
						}
						return $v;
					}, $lines);
					$this->append_code(implode($this->new_line, $lines), false);
					break;
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
						$text = trim($text) . str_repeat($this->new_line, $this->substr_count_trailing($text, $this->new_line));
					} elseif (0 === $this->substr_count_trailing($text, $this->new_line) && T_WHITESPACE === $id) {
						$text = $this->get_space() . ltrim($text) . str_repeat($this->new_line, $this->substr_count_trailing($text, $this->new_line));
					}
					$this->append_code($text, false);
					break;
			}
		}

		return $this->code;
	}
}

final class OrderUseClauses extends FormatterPass {
	const OPENER_PLACEHOLDER = "<?php /*\x2 ORDERBY \x3*/";
	private function singleNamespace($source) {
		$tokens            = token_get_all($source);
		$use_stack         = [];
		$new_tokens        = [];
		$next_tokens       = [];
		$touched_namespace = false;
		while (list(, $pop_token) = each($tokens)) {
			$next_tokens[] = $pop_token;
			while (($token = array_shift($next_tokens))) {
				list($id, $text) = $this->get_token($token);
				if (T_NAMESPACE == $id) {
					$touched_namespace = true;
				}
				if (T_USE === $id) {
					$use_item = $text;
					while (list(, $token) = each($tokens)) {
						list($id, $text) = $this->get_token($token);
						if (ST_SEMI_COLON === $id) {
							$use_item .= $text;
							break;
						} elseif (ST_COMMA === $id) {
							$use_item .= ST_SEMI_COLON . $this->new_line;
							$next_tokens[] = [T_USE, 'use', ];
							break;
						} else {
							$use_item .= $text;
						}
					}
					$use_stack[] = $use_item;
					$token       = new SurrogateToken();
				}
				if (T_FINAL === $id || T_ABSTRACT === $id || T_INTERFACE === $id || T_CLASS === $id || T_FUNCTION === $id || T_TRAIT === $id || T_VARIABLE === $id) {
					if (sizeof($use_stack) > 0) {
						$new_tokens[] = $this->new_line;
						$new_tokens[] = $this->new_line;
					}
					$new_tokens[] = $token;
					break 2;
				} elseif ($touched_namespace && (T_DOC_COMMENT === $id || T_COMMENT === $id)) {
					if (sizeof($use_stack) > 0) {
						$new_tokens[] = $this->new_line;
					}
					$new_tokens[] = $token;
					break 2;
				}
				$new_tokens[] = $token;
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
			$alias               = strtolower($alias);
			$alias_list[$alias]  = strtolower($use);
			$alias_count[$alias] = 0;
		}
		$return = '';
		foreach ($new_tokens as $token) {
			if ($token instanceof SurrogateToken) {
				$return .= array_shift($use_stack);
			} else {
				list($id, $text) = $this->get_token($token);
				$lower_text      = strtolower($text);
				if (T_STRING === $id && isset($alias_list[$lower_text])) {
					$alias_count[$lower_text]++;
				}
				$return .= $text;
			}
		}

		while (list(, $token) = each($tokens)) {
			list($id, $text) = $this->get_token($token);
			$lower_text      = strtolower($text);
			if (T_STRING === $id && isset($alias_list[$lower_text])) {
				$alias_count[$lower_text]++;
			} elseif (T_DOC_COMMENT === $id) {
				foreach ($alias_list as $alias => $use) {
					if (false !== stripos($text, $alias)) {
						$alias_count[$alias]++;
					}
				}
			}
			$return .= $text;
		}
		$unused_import = array_keys(
			array_filter(
				$alias_count, function ($v) {
					return 0 === $v;
				}
			)
		);
		foreach ($unused_import as $v) {
			$return = str_ireplace($alias_list[$v] . $this->new_line, null, $return);
		}

		return $return;
	}
	public function format($source = '') {
		$namespace_count = 0;
		$tokens          = token_get_all($source);
		while (list(, $token) = each($tokens)) {
			list($id, $text) = $this->get_token($token);
			if (T_NAMESPACE == $id) {
				$namespace_count++;
			}
		}
		if ($namespace_count <= 1) {
			return $this->singleNamespace($source);
		}

		$return = '';
		reset($tokens);
		while (list($index, $token) = each($tokens)) {
			list($id, $text) = $this->get_token($token);
			$this->ptr       = $index;
			switch ($id) {
				case T_NAMESPACE:
					$return .= $text;
					while (list($index, $token) = each($tokens)) {
						list($id, $text) = $this->get_token($token);
						$this->ptr       = $index;
						$return .= $text;
						if ($id == ST_CURLY_OPEN) {
							break;
						}
					}
					$namespace_block = '';
					$curly_count     = 1;
					while (list($index, $token) = each($tokens)) {
						list($id, $text) = $this->get_token($token);
						$this->ptr       = $index;
						$namespace_block .= $text;
						if ($id == ST_CURLY_OPEN) {
							$curly_count++;
						} elseif ($id == ST_CURLY_CLOSE) {
							$curly_count--;
						}

						if (0 == $curly_count) {
							break;
						}
					}
					$return .= str_replace(
						self::OPENER_PLACEHOLDER,
						'',
						$this->singleNamespace(self::OPENER_PLACEHOLDER . $namespace_block)
					);
					break;
				default:
					$return .= $text;
			}
		}

		return $return;
	}
}

final class Reindent extends FormatterPass {
	private function normalizeHereDocs($source) {
		$this->tkns = token_get_all($source);
		$this->code = '';
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->get_token($token);
			$this->ptr       = $index;
			switch ($id) {
				case T_ENCAPSED_AND_WHITESPACE:
					$tmp = str_replace(' ', '', $text);
					if ('=<<<' === substr($tmp, 0, 4)) {
						$initial     = strpos($text, $this->new_line);
						$heredoc_tag = trim(substr($text, strpos($text, '<<<') + 3, strpos($text, $this->new_line)-(strpos($text, '<<<') + 3)));

						$this->append_code(substr($text, 0, $initial), false);
						$text = rtrim(substr($text, $initial));
						$text = substr($text, 0, strlen($text) - 1) . $this->new_line . ST_SEMI_COLON . $this->new_line;
					}
					$this->append_code($text);
					break;
				case T_START_HEREDOC:
					$this->append_code($text, false);
					$heredoc_tag = trim(str_replace('<<<', '', $text));
					while (list($index, $token) = each($this->tkns)) {
						list($id, $text) = $this->get_token($token);
						$this->ptr       = $index;
						if (ST_SEMI_COLON === substr(rtrim($text), -1)) {
							$this->append_code(
								substr(
									rtrim($text),
									0,
									strlen(rtrim($text)) - 1
								) . $this->new_line . ST_SEMI_COLON . $this->new_line,
								false
							);
							break;
						} else {
							$this->append_code($text, false);
						}
					}
					break;
				default:
					$this->append_code($text, false);
					break;
			}
		}
		return $this->code;
	}
	private function indent($source) {
		$this->tkns  = token_get_all($source);
		$this->code  = '';
		$found_stack = [];
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->get_token($token);
			$this->ptr       = $index;

			if (
				(
					T_WHITESPACE === $id ||
					(T_COMMENT === $id && '//' == substr($text, 0, 2))
				) && substr_count($text, $this->new_line) > 0
			) {
				$bottom_found_stack = end($found_stack);
				if (isset($bottom_found_stack['implicit']) && $bottom_found_stack['implicit']) {
					$idx                           = sizeof($found_stack) - 1;
					$found_stack[$idx]['implicit'] = false;
					$this->set_indent(+1);
				}
			}
			switch ($id) {
				case T_CLOSE_TAG:
					$this->append_code($text, false);
					while (list($index, $token) = each($this->tkns)) {
						list($id, $text) = $this->get_token($token);
						$this->ptr       = $index;
						$this->append_code($text, false);
						if ($id == T_OPEN_TAG) {
							break;
						}
					}
					break;
				case T_START_HEREDOC:
					$this->append_code(rtrim($text) . $this->get_crlf(), false);
					break;
				case T_CONSTANT_ENCAPSED_STRING:
				case T_ENCAPSED_AND_WHITESPACE:
				case T_STRING_VARNAME:
				case T_NUM_STRING:
					$this->append_code($text, false);
					break;
				case T_CURLY_OPEN:
				case ST_CURLY_OPEN:
				case ST_PARENTHESES_OPEN:
				case ST_BRACKET_OPEN:
					$indent_token = [
						'id'       => $id,
						'implicit' => true
					];
					$this->append_code($text, false);
					if ($this->has_ln_after()) {
						$indent_token['implicit'] = false;
						$this->set_indent(+1);
					}
					$found_stack[] = $indent_token;
					break;
				case ST_CURLY_CLOSE:
				case ST_PARENTHESES_CLOSE:
				case ST_BRACKET_CLOSE:
					$popped_id = array_pop($found_stack);
					if (false === $popped_id['implicit']) {
						$this->set_indent(-1);
					}
					$this->append_code($text, false);
					break;

				default:
					if (substr_count($text, $this->new_line) > 0 && !$this->is_token(ST_CURLY_CLOSE) && !$this->is_token(ST_PARENTHESES_CLOSE) && !$this->is_token(ST_BRACKET_CLOSE)) {
						$text = str_replace($this->new_line, $this->new_line . $this->get_indent(), $text);
					} elseif (substr_count($text, $this->new_line) > 0 && ($this->is_token(ST_CURLY_CLOSE) || $this->is_token(ST_PARENTHESES_CLOSE) || $this->is_token(ST_BRACKET_CLOSE))) {
						$this->set_indent(-1);
						$text = str_replace($this->new_line, $this->new_line . $this->get_indent(), $text);
						$this->set_indent(+1);
					}
					$this->append_code($text, false);
					break;
			}
		}
		return $this->code;
	}
	public function format($source) {
		$source = $this->normalizeHereDocs($source);
		$source = $this->indent($source);
		return $source;
	}
}

final class ReindentColonBlocks extends FormatterPass {
	public function format($source) {
		$this->tkns = token_get_all($source);
		$this->code = '';

		$switch_level                      = 0;
		$switch_curly_count                = [];
		$switch_curly_count[$switch_level] = 0;
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->get_token($token);
			$this->ptr       = $index;
			switch ($id) {
				case T_SWITCH:
					$switch_level++;
					$switch_curly_count[$switch_level] = 0;
					$this->append_code($text, false);
					break;
				case ST_CURLY_OPEN:
					$switch_curly_count[$switch_level]++;
					$this->append_code($text, false);
					break;
				case ST_CURLY_CLOSE:
					$switch_curly_count[$switch_level]--;
					if (0 === $switch_curly_count[$switch_level] && $switch_level > 0) {
						$switch_level--;
					}
					$this->append_code($this->get_indent($switch_level) . $text, false);
					break;
				case T_DEFAULT:
				case T_CASE:
					$this->append_code($text, false);
					break;
				default:
					if (substr_count($text, $this->new_line) > 0 && !$this->is_token(array(T_CASE, T_DEFAULT)) && !$this->is_token(ST_CURLY_CLOSE)) {
						$this->append_code($text . $this->get_indent($switch_level), false);
					} elseif (substr_count($text, $this->new_line) > 0 && $this->is_token(array(T_CASE, T_DEFAULT))) {
						$this->append_code($text, false);
					} else {
						$this->append_code($text, false);
					}
					break;
			}
		}
		return $this->code;
	}
}

final class ReindentLoopColonBlocks extends FormatterPass {
	public function format($source) {
		$source = $this->format_for_blocks($source);
		$source = $this->format_foreach_blocks($source);
		$source = $this->format_while_blocks($source);
		return $source;
	}

	private function format_blocks($source, $open_token, $close_token) {
		$this->tkns = token_get_all($source);
		$this->code = '';

		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->get_token($token);
			$this->ptr       = $index;
			switch ($id) {
				case $close_token:
					$this->set_indent(-1);
					$this->append_code($text, false);
					break;
				case $open_token:
					$this->append_code($text, false);
					while (list($index, $token) = each($this->tkns)) {
						list($id, $text) = $this->get_token($token);
						$this->ptr       = $index;
						$this->append_code($text, false);
						if (ST_CURLY_OPEN === $id) {
							break;
						} elseif (ST_COLON === $id && !$this->is_token(array(T_CLOSE_TAG))) {
							$this->set_indent(+1);
							break;
						} elseif (ST_COLON === $id) {
							break;
						}
					}
					break;
				default:
					if (substr_count($text, $this->new_line) > 0 && !$this->is_token(array($close_token))) {
						$text = str_replace($this->new_line, $this->new_line . $this->get_indent(), $text);
					} elseif (substr_count($text, $this->new_line) > 0 && $this->is_token(array($close_token))) {
						$this->set_indent(-1);
						$text = str_replace($this->new_line, $this->new_line . $this->get_indent(), $text);
						$this->set_indent(+1);
					}
					$this->append_code($text, false);
					break;
			}
		}
		return $this->code;
	}
	private function format_for_blocks($source) {
		return $this->format_blocks($source, T_FOR, T_ENDFOR);
	}
	private function format_foreach_blocks($source) {
		return $this->format_blocks($source, T_FOREACH, T_ENDFOREACH);
	}
	private function format_while_blocks($source) {
		$this->tkns = token_get_all($source);
		$this->code = '';

		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->get_token($token);
			$this->ptr       = $index;
			switch ($id) {
				case T_ENDWHILE:
					$this->set_indent(-1);
					$this->append_code($text, false);
					break;
				case T_WHILE:
					$this->append_code($text, false);
					while (list($index, $token) = each($this->tkns)) {
						list($id, $text) = $this->get_token($token);
						$this->ptr       = $index;
						$this->append_code($text, false);
						if (ST_CURLY_OPEN === $id) {
							break;
						} elseif (ST_SEMI_COLON === $id) {
							break;
						} elseif (ST_COLON === $id) {
							$this->set_indent(+1);
							break;
						}
					}
					break;
				default:
					if (substr_count($text, $this->new_line) > 0 && !$this->is_token(array(T_ENDWHILE))) {
						$text = str_replace($this->new_line, $this->new_line . $this->get_indent(), $text);
					} elseif (substr_count($text, $this->new_line) > 0 && $this->is_token(array(T_ENDWHILE))) {
						$this->set_indent(-1);
						$text = str_replace($this->new_line, $this->new_line . $this->get_indent(), $text);
						$this->set_indent(+1);
					}
					$this->append_code($text, false);
					break;
			}
		}
		return $this->code;
	}
}

final class ReindentIfColonBlocks extends FormatterPass {
	public function format($source) {
		$this->tkns = token_get_all($source);
		$this->code = '';

		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->get_token($token);
			$this->ptr       = $index;
			switch ($id) {
				case T_ENDIF:
					$this->set_indent(-1);
					$this->append_code($text, false);
					break;
				case T_ELSE:
				case T_ELSEIF:
					$this->set_indent(-1);
				case T_IF:
					$this->append_code($text, false);
					while (list($index, $token) = each($this->tkns)) {
						list($id, $text) = $this->get_token($token);
						$this->ptr       = $index;
						$this->append_code($text, false);
						if (ST_CURLY_OPEN === $id) {
							break;
						} elseif (ST_COLON === $id && !$this->is_token(array(T_CLOSE_TAG))) {
							$this->set_indent(+1);
							break;
						} elseif (ST_COLON === $id) {
							break;
						}
					}
					break;
				default:
					if (substr_count($text, $this->new_line) > 0 && !$this->is_token(array(T_ENDIF, T_ELSE, T_ELSEIF))) {
						$text = str_replace($this->new_line, $this->new_line . $this->get_indent(), $text);
					} elseif (substr_count($text, $this->new_line) > 0 && $this->is_token(array(T_ENDIF, T_ELSE, T_ELSEIF))) {
						$this->set_indent(-1);
						$text = str_replace($this->new_line, $this->new_line . $this->get_indent(), $text);
						$this->set_indent(+1);
					}
					$this->append_code($text, false);
					break;
			}
		}
		return $this->code;
	}
}
final class ReindentObjOps extends FormatterPass {
	const ALIGNABLE_OBJOP = "\x2 OBJOP%d \x3";
	public function format($source) {
		$this->tkns              = token_get_all($source);
		$this->code              = '';
		$in_objop_context        = 0;// 1 - indent, 2 - don't indent, so future auto-align takes place
		$alignable_objop_counter = 0;
		$printed_placeholder     = false;
		$paren_count             = 0;
		$bracket_count           = 0;
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->get_token($token);
			$this->ptr       = $index;
			switch ($id) {
				case ST_PARENTHESES_OPEN:
					$paren_count++;
					$this->append_code($text, false);
					break;
				case ST_PARENTHESES_CLOSE:
					$paren_count--;
					$this->append_code($text, false);
					break;
				case ST_BRACKET_OPEN:
					$bracket_count++;
					$this->append_code($text, false);
					break;
				case ST_BRACKET_CLOSE:
					$bracket_count--;
					$this->append_code($text, false);
					break;
				case T_OBJECT_OPERATOR:
					if (0 === $in_objop_context && ($this->has_ln_before() || $this->has_ln_prev_token())) {
						$in_objop_context = 1;
					} elseif (0 === $in_objop_context && !($this->has_ln_before() || $this->has_ln_prev_token())) {
						$alignable_objop_counter++;
						$in_objop_context = 2;
					} elseif ($paren_count > 0) {
						$in_objop_context = 0;
					}
					if (1 === $in_objop_context) {
						$this->set_indent(+1);
						$this->append_code($this->get_indent() . $text, false);
						$this->set_indent(-1);
					} elseif (2 === $in_objop_context) {
						$placeholder = '';
						if (!$printed_placeholder) {
							$placeholder         = sprintf(self::ALIGNABLE_OBJOP, $alignable_objop_counter);
							$printed_placeholder = true;
						}
						$this->append_code($placeholder . $text, false);
					} else {
						$this->append_code($text, false);
					}
					break;
				case T_VARIABLE:
					if (0 === $paren_count && 0 === $bracket_count && 0 !== $in_objop_context) {
						$in_objop_context = 0;
					}
					$this->append_code($text, false);
					break;
				case T_DOUBLE_ARROW:
				case ST_SEMI_COLON:
					if (0 !== $in_objop_context) {
						$in_objop_context = 0;
					}
					$this->append_code($text, false);
					break;
				default:
					$this->append_code($text, false);
					break;
			}
			if (substr_count($text, $this->new_line) > 0) {
				$printed_placeholder = false;
			}
		}

		for ($j = $alignable_objop_counter; $j > 0; $j--) {
			$current_align_objop = sprintf(self::ALIGNABLE_OBJOP, $j);
			if (substr_count($this->code, $current_align_objop) <= 1) {
				$this->code = str_replace($current_align_objop, '', $this->code);
				continue;
			}

			$lines            = explode($this->new_line, $this->code);
			$lines_with_objop = [];
			$block_count      = 0;

			foreach ($lines as $idx => $line) {
				if (substr_count($line, $current_align_objop) > 0) {
					$lines_with_objop[$block_count][] = $idx;
				} else {
					$block_count++;
				}
			}

			$i = 0;
			foreach ($lines_with_objop as $group) {
				if (1 === sizeof($group)) {
					continue;
				}
				$i++;
				$farthest_objop = 0;
				foreach ($group as $idx) {
					$farthest_objop = max($farthest_objop, strpos($lines[$idx], $current_align_objop));
				}
				foreach ($group as $idx) {
					$line          = $lines[$idx];
					$current_objop = strpos($line, $current_align_objop);
					$delta         = abs($farthest_objop - $current_objop);
					if ($delta > 0) {
						$line        = str_replace($current_align_objop, str_repeat(' ', $delta) . $current_align_objop, $line);
						$lines[$idx] = $line;
					}
				}
			}

			$this->code = str_replace($current_align_objop, '', implode($this->new_line, $lines));
		}

		return $this->code;
	}
}

final class ResizeSpaces extends FormatterPass {
	public function format($source) {
		$source = $this->basicSpacing($source);
		$source = $this->airOutSpacing($source);

		return $source;
	}

	private function airOutSpacing($source) {
		$new_tokens          = [];
		$this->tkns          = token_get_all($source);
		$this->code          = '';
		$in_ternary_operator = false;
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->get_token($token);
			$this->ptr       = $index;
			switch ($id) {
				case '+':
				case '-':
					list($prev_id, $prev_text) = $this->inspect_token(-1);
					list($next_id, $next_text) = $this->inspect_token(+1);
					if (
						($prev_id == T_LNUMBER || $prev_id == T_DNUMBER || $prev_id == T_VARIABLE || $prev_id == ST_PARENTHESES_CLOSE || $prev_id == T_STRING)
					 	&&
						($next_id == T_LNUMBER || $next_id == T_DNUMBER || $next_id == T_VARIABLE || $next_id == ST_PARENTHESES_CLOSE || $next_id == T_STRING)
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
						list($index, $token)       = each($this->tkns);
						$this->ptr                 = $index;
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
					list($prev_id, $prev_text) = $this->inspect_token(-1);
					list($next_id, $next_text) = $this->inspect_token(+1);
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
					if (ST_QUESTION == $id) {
						$in_ternary_operator = true;
					}
					break;
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
						$this->append_code($this->get_space() . $text, false);
						$in_ternary_operator = false;
					} elseif (
						$in_ternary_operator &&
						T_WHITESPACE != $prev_id &&
						T_WHITESPACE != $next_id
					) {
						$this->append_code($this->get_space() . $text . $this->get_space(), false);
						$in_ternary_operator = false;
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

	private function basicSpacing($source) {
		$new_tokens = [];
		$this->tkns = token_get_all($source);
		$this->code = '';
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->get_token($token);
			$this->ptr       = $index;
			switch ($id) {
				case T_WHITESPACE:
					if (0 === substr_count($text, $this->new_line)) {
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
			$this->ptr       = $index;
			switch ($id) {
				case T_ARRAY:
					if ($this->is_token(array(T_VARIABLE)) || $this->is_token(ST_REFERENCE)) {
						$this->append_code($text . $this->get_space(), false);
						break;
					} elseif ($this->is_token(ST_PARENTHESES_OPEN)) {
						$this->append_code($text, false);
						break;
					}
				case T_STRING:
					if ($this->is_token(array(T_VARIABLE, T_DOUBLE_ARROW))) {
						$this->append_code($text . $this->get_space(), false);
						break;
						//} elseif ($this->is_token(array(T_DOUBLE_COLON)) || $this->is_token(ST_PARENTHESES_OPEN, true) || $this->is_token(ST_CONCAT) || $this->is_token(ST_COMMA)) {
					} else {
						$this->append_code($text, false);
						break;
					}
				case ST_CURLY_OPEN:
					if ($this->is_token(array(T_STRING, T_DO), true) || $this->is_token(ST_PARENTHESES_CLOSE, true)) {
						$this->append_code($this->get_space() . $text, false);
						break;
					} elseif ($this->is_token(ST_CURLY_CLOSE)) {
						$this->append_code($text, false);
						break;
					}
				case ST_SEMI_COLON:
					if ($this->is_token(array(T_VARIABLE, T_INC))) {
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
					if ($this->is_token(ST_SEMI_COLON)) {
						$this->append_code($text, false);
					} else {
						$this->append_code($text . $this->get_space(), false);
					}
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
					if ($this->is_token(array(T_CONSTANT_ENCAPSED_STRING, T_STRING, T_VARIABLE, T_LNUMBER, T_DNUMBER), true)) {
						$this->append_code($this->get_space() . $text . $this->get_space());
						break;
					}
				case T_STATIC:
					$this->append_code($text . $this->get_space(!$this->is_token(ST_SEMI_COLON) && !$this->is_token([T_DOUBLE_COLON])), false);
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
					$this->append_code($text . $this->get_space(), false);
					break;
				case ST_CONCAT:
					if (
						!$this->is_token(ST_PARENTHESES_CLOSE, true) &&
						!$this->is_token(ST_BRACKET_CLOSE, true) &&
						!$this->is_token(array(T_VARIABLE, T_STRING, T_CONSTANT_ENCAPSED_STRING, T_WHITESPACE), true)
					) {
						$this->append_code($this->get_space() . $text, false);
					} else {
						$this->append_code($text, false);
					}
					break;
				case ST_REFERENCE:
					if ($this->is_token(array(T_STRING), true)) {
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

final class RTrim extends FormatterPass {

	public function format($source) {
		return implode(
			$this->new_line,
			array_map(
				function ($v) {
					return rtrim($v);
				},
				explode($this->new_line, $source)
			)
		);
	}

}

final class SurrogateToken {
}

final class TwoCommandsInSameLine extends FormatterPass {
	public function format($source) {
		$lines = explode($this->new_line, $source);
		foreach ($lines as $idx => $line) {
			if (substr_count($line, ';') <= 1) {
				continue;
			}
			$new_line           = '';
			$ignore_stack       = 0;
			$double_quote_state = false;
			$single_quote_state = false;
			$len                = strlen($line);
			for ($i = 0; $i < $len; $i++) {
				$char = substr($line, $i, 1);
				if (ST_PARENTHESES_OPEN === $char || ST_PARENTHESES_OPEN === $char || ST_CURLY_OPEN === $char || ST_BRACKET_OPEN === $char) {
					$ignore_stack++;
				}
				if (ST_PARENTHESES_CLOSE === $char || ST_CURLY_CLOSE === $char || ST_BRACKET_CLOSE === $char) {
					$ignore_stack--;
				}
				if ('"' === $char) {
					$double_quote_state = !$double_quote_state;
				}
				if ("'" === $char) {
					$single_quote_state = !$single_quote_state;
				}
				$new_line .= $char;
				if (!$single_quote_state && !$double_quote_state && 0 === $ignore_stack && ST_SEMI_COLON === $char && $i + 1 < $len) {
					$new_line .= $this->new_line;
				}
			}
			$lines[$idx] = $new_line;
		}
		return implode($this->new_line, $lines);
	}
}

final class PSR1OpenTags extends FormatterPass {
	public function format($source) {
		$this->tkns = token_get_all($source);
		$this->code = '';
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->get_token($token);
			$this->ptr       = $index;
			switch ($id) {
				case T_OPEN_TAG:
					if ('<?php' !== $text) {
						$this->append_code('<?php' . $this->new_line, false);
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

final class PSR1BOMMark extends FormatterPass {
	public function format($source) {
		$bom = "\xef\xbb\xbf";
		if ($bom === substr($source, 0, 3)) {
			return substr($source, 3);
		}
		return $source;
	}
}

final class PSR1ClassNames extends FormatterPass {
	public function format($source) {
		$this->tkns  = token_get_all($source);
		$this->code  = '';
		$found_class = false;
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->get_token($token);
			$this->ptr       = $index;
			switch ($id) {
				case T_CLASS:
					$found_class = true;
					$this->append_code($text, false);
					break;
				case T_STRING:
					if ($found_class) {
						$count = 0;
						$tmp   = ucwords(str_replace(array('-', '_'), ' ', strtolower($text), $count));
						if ($count > 0) {
							$text = str_replace(' ', '', $tmp);
						}
						$this->append_code($text, false);

						$found_class = false;
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

final class PSR1ClassConstants extends FormatterPass {
	public function format($source) {
		$this->tkns = token_get_all($source);
		$this->code = '';
		$uc_const   = false;
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->get_token($token);
			$this->ptr       = $index;
			switch ($id) {
				case T_CONST:
					$uc_const = true;
					$this->append_code($text, false);
					break;
				case T_STRING:
					if ($uc_const) {
						$text     = strtoupper($text);
						$uc_const = false;
					}
					$this->append_code($text, false);
					break;
				default:
					$this->append_code($text, false);
					break;
			}
		}
		return $this->code;
	}
}
final class PSR1MethodNames extends FormatterPass {
	public function format($source) {
		$this->tkns   = token_get_all($source);
		$this->code   = '';
		$found_method = false;
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->get_token($token);
			$this->ptr       = $index;
			switch ($id) {
				case T_FUNCTION:
					$found_method = true;
					$this->append_code($text, false);
					break;
				case T_STRING:
					if ($found_method) {
						$count = 0;
						$tmp   = ucwords(str_replace(array('-', '_'), ' ', strtolower($text), $count));
						if ($count > 0 && '' !== trim($tmp) && '_' !== substr($text, 0, 1)) {
							$text = lcfirst(str_replace(' ', '', $tmp));
						}
						$this->append_code($text, false);

						$found_method = false;
						break;
					}
				case ST_PARENTHESES_OPEN:
					$found_method = false;
				default:
					$this->append_code($text, false);
					break;
			}
		}
		return $this->code;
	}
}

final class PSR2IndentWithSpace extends FormatterPass {
	private $indent_spaces = '    ';

	public function format($source) {
		$this->tkns = token_get_all($source);
		$this->code = '';
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->get_token($token);
			$this->ptr       = $index;
			switch ($id) {
				case T_COMMENT:
				case T_DOC_COMMENT:
				case T_WHITESPACE:
					$this->append_code(str_replace($this->indent_char, $this->indent_spaces, $text), false);
					break;
				default:
					$this->append_code($text, false);
					break;
			}
		}
		return $this->code;
	}
}

final class PSR2KeywordsLowerCase extends FormatterPass {
	public function format($source) {
		$this->tkns = token_get_all($source);
		$this->code = '';
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->get_token($token);
			$this->ptr       = $index;
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
					$this->append_code(strtolower($text), false);
					break;
				default:
					$lc_text = strtolower($text);
					if ('true' === $lc_text || 'false' === $lc_text || 'null' === $lc_text) {
						$text = $lc_text;
					}
					$this->append_code($text, false);
					break;
			}
		}
		return $this->code;
	}
}
final class PSR2LnAfterNamespace extends FormatterPass {
	public function format($source) {
		$this->tkns = token_get_all($source);
		$this->code = '';

		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->get_token($token);
			$this->ptr       = $index;
			switch ($id) {
				case T_NAMESPACE:
					$this->append_code($text, false);
					while (list($index, $token) = each($this->tkns)) {
						list($id, $text) = $this->get_token($token);
						$this->ptr       = $index;
						if (ST_SEMI_COLON === $id) {
							$this->append_code($text, false);
							list(, $text) = $this->inspect_token();
							if (1 === substr_count($text, $this->new_line)) {
								$this->append_code($this->new_line, false);
							}
							break;
						} else {
							$this->append_code($text, false);
						}
					}
					break;
				default:
					$this->append_code($text, false);
					break;
			}
		}
		return $this->code;
	}
}

final class PSR2CurlyOpenNextLine extends FormatterPass {
	public function format($source) {
		$this->indent_char = '    ';
		$this->tkns        = token_get_all($source);
		$this->code        = '';

		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->get_token($token);
			$this->ptr       = $index;
			switch ($id) {
				case T_INTERFACE:
				case T_TRAIT:
				case T_CLASS:
					$this->append_code($text, false);
					while (list($index, $token) = each($this->tkns)) {
						list($id, $text) = $this->get_token($token);
						$this->ptr       = $index;
						if (ST_CURLY_OPEN === $id) {
							$this->append_code($this->get_crlf_indent(), false);
							prev($this->tkns);
							break;
						} else {
							$this->append_code($text, false);
						}
					}
					break;
				case T_FUNCTION:
					if (!$this->is_token([T_DOUBLE_ARROW, T_RETURN], true) && !$this->is_token(ST_EQUAL, true) && !$this->is_token(ST_PARENTHESES_OPEN, true) && !$this->is_token(ST_COMMA, true)) {
						$this->append_code($text, false);
						while (list($index, $token) = each($this->tkns)) {
							list($id, $text) = $this->get_token($token);
							$this->ptr       = $index;
							if (ST_CURLY_OPEN === $id) {
								$this->append_code($this->get_crlf_indent(), false);
								prev($this->tkns);
								break;
							} else {
								$this->append_code($text, false);
							}
						}
						break;
					} else {
						$this->append_code($text, false);
					}
					break;
				case ST_CURLY_OPEN:
					$this->append_code($text, false);
					$this->set_indent(+1);
					break;
				case ST_CURLY_CLOSE:
					$this->set_indent(-1);
					$this->append_code($text, false);
					break;
				default:
					$this->append_code($text, false);
					break;
			}
		}
		return $this->code;
	}
}

final class PSR2ModifierVisibilityStaticOrder extends FormatterPass {
	public function format($source) {
		$this->tkns = token_get_all($source);
		$this->code = '';

		$found             = [];
		$visibility        = null;
		$final_or_abstract = null;
		$static            = null;
		$skip_whitespaces  = false;
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->get_token($token);
			$this->ptr       = $index;
			switch ($id) {
				case T_CLASS:
					$found[] = T_CLASS;
					$this->append_code($text, false);
					break;
				case ST_CURLY_OPEN:
				case ST_PARENTHESES_OPEN:
					$found[] = $text;
					$this->append_code($text, false);
					break;
				case ST_CURLY_CLOSE:
				case ST_PARENTHESES_CLOSE:
					array_pop($found);
					if (1 === sizeof($found)) {
						array_pop($found);
					}
					$this->append_code($text, false);
					break;
				case T_WHITESPACE:
					if (!$skip_whitespaces) {
						$this->append_code($text, false);
					}
					break;
				case T_PUBLIC:
				case T_PRIVATE:
				case T_PROTECTED:
					if (!$this->is_token(array(T_VARIABLE))) {
						$visibility       = $text;
						$skip_whitespaces = true;
					} else {
						$this->append_code($text, false);
					}
					break;
				case T_FINAL:
				case T_ABSTRACT:
					if (!$this->is_token(array(T_CLASS))) {
						$final_or_abstract = $text;
						$skip_whitespaces  = true;
					} else {
						$this->append_code($text, false);
					}
					break;
				case T_STATIC:
					if (!$this->is_token(array(T_VARIABLE)) && !$this->is_token(array(T_NEW), true)) {
						$static           = $text;
						$skip_whitespaces = true;
					} else {
						$this->append_code($text, false);
					}
					break;
				case T_VARIABLE:
					if (
						null !== $visibility ||
						null !== $final_or_abstract ||
						null !== $static
					) {
						null !== $visibility && $this->append_code($final_or_abstract . $this->get_space(), false);
						null !== $final_or_abstract && $this->append_code($visibility . $this->get_space(), false);
						null !== $static && $this->append_code($static . $this->get_space(), false);
						$final_or_abstract = null;
						$visibility        = null;
						$static            = null;
						$skip_whitespaces  = false;
					}
					$this->append_code($text, false);
					break;
				case T_FUNCTION:
					if (isset($found[0]) && T_CLASS === $found[0] && null !== $final_or_abstract) {
						$this->append_code($final_or_abstract . $this->get_space(), false);
					}
					if (isset($found[0]) && T_CLASS === $found[0] && null !== $visibility) {
						$this->append_code($visibility . $this->get_space(), false);
					} elseif (
						isset($found[0]) && T_CLASS === $found[0] &&
						!$this->is_token([T_DOUBLE_ARROW, T_RETURN], true) &&
						!$this->is_token(ST_EQUAL, true) &&
						!$this->is_token(ST_COMMA, true) &&
						!$this->is_token(ST_PARENTHESES_OPEN, true)
					) {
						$this->append_code('public' . $this->get_space(), false);
					}
					if (isset($found[0]) && T_CLASS === $found[0] && null !== $static) {
						$this->append_code($static . $this->get_space(), false);
					}
					$this->append_code($text, false);
					$final_or_abstract = null;
					$visibility        = null;
					$static            = null;
					$skip_whitespaces  = false;
					break;
				default:
					$this->append_code($text, false);
					break;
			}
		}
		return $this->code;
	}
}

final class PSR2SingleEmptyLineAndStripClosingTag extends FormatterPass {
	public function format($source) {
		$this->tkns = token_get_all($source);
		$this->code = '';

		$open_tag_count = 0;
		while (list($index, $token) = each($this->tkns)) {
			list($id, ) = $this->get_token($token);
			if (T_OPEN_TAG === $id) {
				$open_tag_count++;
				break;
			}
		}

		reset($this->tkns);
		if (1 === $open_tag_count) {
			while (list($index, $token) = each($this->tkns)) {
				list($id, $text) = $this->get_token($token);
				$this->ptr       = $index;
				switch ($id) {
					case T_CLOSE_TAG:
						$this->append_code($this->get_crlf(), false);
						break;
					default:
						$this->append_code($text, false);
						break;
				}
			}
			$this->code = rtrim($this->code);
		} else {
			while (list($index, $token) = each($this->tkns)) {
				list($id, $text) = $this->get_token($token);
				$this->ptr       = $index;
				$this->append_code($text, false);
			}
		}
		$this->code = rtrim($this->code) . $this->get_crlf();

		return $this->code;
	}
}

class PsrDecorator {
	public static function PSR1(CodeFormatter $fmt) {
		$fmt->addPass(new PSR1OpenTags());
		$fmt->addPass(new PSR1BOMMark());
		$fmt->addPass(new PSR1ClassNames());
		$fmt->addPass(new PSR1ClassConstants());
		$fmt->addPass(new PSR1MethodNames());
	}

	public static function PSR2(CodeFormatter $fmt) {
		$fmt->addPass(new PSR2KeywordsLowerCase());
		$fmt->addPass(new PSR2IndentWithSpace());
		$fmt->addPass(new PSR2LnAfterNamespace());
		$fmt->addPass(new PSR2CurlyOpenNextLine());
		$fmt->addPass(new PSR2ModifierVisibilityStaticOrder());
		$fmt->addPass(new PSR2SingleEmptyLineAndStripClosingTag());
	}

	public static function decorate(CodeFormatter $fmt) {
		self::PSR1($fmt);
		self::PSR2($fmt);
	}
}
if (!isset($testEnv)) {
	$fmt = new CodeFormatter();
	$fmt->addPass(new TwoCommandsInSameLine());
	$fmt->addPass(new OrderUseClauses());
	$fmt->addPass(new AddMissingCurlyBraces());
	$fmt->addPass(new NormalizeLnAndLtrimLines());
	$fmt->addPass(new MergeParenCloseWithCurlyOpen());
	$fmt->addPass(new MergeCurlyCloseAndDoWhile());
	$fmt->addPass(new MergeDoubleArrowAndArray());
	$fmt->addPass(new ExtraCommaInArray());
	$fmt->addPass(new ResizeSpaces());
	$fmt->addPass(new Reindent());
	$fmt->addPass(new ReindentColonBlocks());
	$fmt->addPass(new ReindentLoopColonBlocks());
	$fmt->addPass(new ReindentIfColonBlocks());
	$fmt->addPass(new ReindentObjOps());
	$fmt->addPass(new EliminateDuplicatedEmptyLines());

	$opts = getopt('o:', ['psr', 'psr1', 'psr2', 'indent_with_space', 'disable_auto_align']);
	if (!isset($opts['disable_auto_align'])) {
		$fmt->addPass(new AlignEquals());
		$fmt->addPass(new AlignDoubleArrow());
	} else {
		$argv = array_values(
			array_filter($argv,
				function ($v) {
					return $v !== '--disable_auto_align';
				}
			)
		);
	}
	if (isset($opts['indent_with_space'])) {
		$fmt->addPass(new PSR2IndentWithSpace());
		$argv = array_values(
			array_filter($argv,
				function ($v) {
					return $v !== '--indent_with_space';
				}
			)
		);
	}
	if (isset($opts['psr'])) {
		PsrDecorator::decorate($fmt);
		$argv = array_values(
			array_filter($argv,
				function ($v) {
					return $v !== '--psr';
				}
			)
		);
	}
	if (isset($opts['psr1'])) {
		PsrDecorator::PSR1($fmt);
		$argv = array_values(
			array_filter($argv,
				function ($v) {
					return $v !== '--psr1';
				}
			)
		);
	}
	if (isset($opts['psr2'])) {
		PsrDecorator::PSR2($fmt);
		$argv = array_values(
			array_filter($argv,
				function ($v) {
					return $v !== '--psr2';
				}
			)
		);
	}
	$fmt->addPass(new LeftAlignComment());
	$fmt->addPass(new RTrim());

	if (!isset($argv[1])) {
		exit();
	}

	if (isset($opts['o'])) {
		unset($argv[1]);
		unset($argv[2]);
		$argv = array_values($argv);
		file_put_contents($opts['o'], $fmt->formatCode(file_get_contents($argv[1])));
	} elseif (is_file($argv[1])) {
		echo $fmt->formatCode(file_get_contents($argv[1]));
	} else {
		$dir   = new RecursiveDirectoryIterator($argv[1]);
		$it    = new RecursiveIteratorIterator($dir);
		$files = new RegexIterator($it, '/^.+\.php$/i', RecursiveRegexIterator::GET_MATCH);
		foreach ($files as $file) {
			$file = $file[0];
			echo $file;
			file_put_contents($file . '-tmp', $fmt->formatCode(file_get_contents($file)));
			rename($file, $file . '~');
			rename($file . '-tmp', $file);
			echo PHP_EOL;
		}
	}
}