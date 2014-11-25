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
define("ST_BITWISE_OR", "|");
define("ST_BITWISE_XOR", "^");
if (!defined("T_POW")) {
	define("T_POW", "**");
}
if (!defined("T_POW_EQUAL")) {
	define("T_POW_EQUAL", "**=");
}
if (!defined("T_YIELD")) {
	define("T_YIELD", "yield");
}
if (!defined("T_FINALLY")) {
	define("T_FINALLY", "finally");
}
;
abstract class FormatterPass {
	protected $indent_size = 1;
	protected $indent_char = "\t";
	protected $block_size = 1;
	protected $new_line = "\n";
	protected $indent = 0;
	protected $for_idx = 0;
	protected $code = '';
	protected $ptr = 0;
	protected $tkns = [];
	protected $use_cache = false;
	protected $cache = [];
	protected $ignore_futile_tokens = [
		T_WHITESPACE,
		T_COMMENT,
		T_DOC_COMMENT,
	];

	abstract public function format($source);
	protected function get_token($token) {
		if (!isset($token[1])) {
			return [$token, $token];
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
			++$this->for_idx;
			if ($this->for_idx > 2) {
				$this->for_idx = 0;
			}
		}
		if (0 === $this->for_idx || !$in_for) {
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
	protected function is_token($token, $prev = false, $ignore_list = []) {
		if ($this->use_cache) {
			$key = ((int) $prev) . "\x2" . implode('', $ignore_list) . "\x2" . (is_array($token) ? implode("\x2", $token) : $token);
			if (isset($this->cache[$key])) {
				return $this->cache[$key];
			}
		}
		$ret = $this->is_token_idx($this->ptr, $token, $prev, $ignore_list);
		if ($this->use_cache) {
			$this->cache[$key] = $ret;
		}
		return $ret;
	}
	protected function is_token_idx($idx, $token, $prev = false, $ignore_list = []) {
		if (empty($ignore_list)) {
			$ignore_list[T_WHITESPACE] = true;
		} else {
			$ignore_list = array_flip($ignore_list);
		}
		$i = $idx;
		if ($prev) {
			while (--$i >= 0 && isset($this->tkns[$i][1]) && isset($ignore_list[$this->tkns[$i][0]]));
		} else {
			$tkns_size = sizeof($this->tkns) - 1;
			while (++$i < $tkns_size && isset($this->tkns[$i][1]) && isset($ignore_list[$this->tkns[$i][0]]));
		}

		if (!isset($this->tkns[$i])) {
			return false;
		}

		$found_token = $this->tkns[$i];
		if ($found_token === $token) {
			return true;
		} elseif (is_array($token) && is_array($found_token) && in_array($found_token[0], $token)) {
			return true;
		} elseif (is_array($token) && is_string($found_token) && in_array($found_token, $token)) {
			return true;
		}
		return false;
	}
	protected function is_token_in_subset($tkns, $idx, $token, $prev = false) {
		$i = $idx;
		if ($prev) {
			while (--$i >= 0 && isset($tkns[$i][1]) && T_WHITESPACE === $tkns[$i][0]);
		} else {
			$tkns_size = sizeof($tkns) - 1;
			while (++$i < $tkns_size && isset($tkns[$i][1]) && T_WHITESPACE === $tkns[$i][0]);
		}

		if (!isset($tkns[$i])) {
			return false;
		}

		$found_token = $tkns[$i];
		if ($found_token === $token) {
			return true;
		} elseif (is_array($token) && is_array($found_token)) {
			if (in_array($found_token[0], $token)) {
				return true;
			} elseif ($prev && T_OPEN_TAG === $found_token[0]) {
				return true;
			}
		} elseif (is_array($token) && is_string($found_token) && in_array($found_token, $token)) {
			return true;
		}
		return false;
	}

	protected function prev_token($ignore_list = [], $idx = false) {
		if (empty($ignore_list)) {
			$ignore_list[T_WHITESPACE] = true;
		} else {
			$ignore_list = array_flip($ignore_list);
		}
		$i = $this->ptr;
		while (--$i >= 0 && isset($this->tkns[$i][1]) && isset($ignore_list[$this->tkns[$i][0]]));
		if ($idx) {
			return $i;
		}
		return $this->tkns[$i];
	}

	protected function next_token($ignore_list = [], $idx = false) {
		if (empty($ignore_list)) {
			$ignore_list[T_WHITESPACE] = true;
		} else {
			$ignore_list = array_flip($ignore_list);
		}
		$i = $this->ptr;
		$tkns_size = sizeof($this->tkns) - 1;
		while (++$i < $tkns_size && isset($this->tkns[$i][1]) && isset($ignore_list[$this->tkns[$i][0]]));
		if ($idx) {
			return $i;
		}
		return $this->tkns[$i];
	}

	protected function siblings($tkns, $ptr) {
		$i = $ptr;
		while (--$i >= 0 && isset($tkns[$i][1]) && T_WHITESPACE === $tkns[$i][0]);
		$left = $i;
		$i = $ptr;
		$tkns_size = sizeof($tkns) - 1;
		while (++$i < $tkns_size && isset($tkns[$i][1]) && T_WHITESPACE === $tkns[$i][0]);
		$right = $i;
		return [$left, $right];
	}
	protected function has_ln_after() {
		$id = null;
		$text = null;
		list($id, $text) = $this->inspect_token();
		return T_WHITESPACE === $id && $this->has_ln($text);
	}
	protected function has_ln_before() {
		$id = null;
		$text = null;
		list($id, $text) = $this->inspect_token(-1);
		return T_WHITESPACE === $id && $this->has_ln($text);
	}
	protected function has_ln_prev_token() {
		list($id, $text) = $this->get_token($this->prev_token());
		return $this->has_ln($text);
	}
	protected function has_ln_next_token() {
		list($id, $text) = $this->get_token($this->next_token());
		return $this->has_ln($text);
	}
	protected function substr_count_trailing($haystack, $needle) {
		return strlen(rtrim($haystack, " \t")) - strlen(rtrim($haystack, " \t" . $needle));
	}
	protected function print_until_the_end_of_string() {
		$this->print_until(ST_QUOTE);
	}
	protected function walk_until($tknid) {
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->get_token($token);
			$this->ptr = $index;
			if ($id == $tknid) {
				return [$id, $text];
			}
		}
	}
	protected function print_until($tknid) {
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->get_token($token);
			$this->ptr = $index;
			$this->cache = [];
			$this->append_code($text, false);
			if ($tknid == $id) {
				break;
			}
		}
	}
	protected function print_and_stop_at($tknids) {
		if (is_scalar($tknids)) {
			$tknids = [$tknids];
		}
		$tknids = array_flip($tknids);
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->get_token($token);
			$this->ptr = $index;
			$this->cache = [];
			if (isset($tknids[$id])) {
				return [$id, $text];
			}
			$this->append_code($text, false);
		}
	}
	protected function print_block($start, $end) {
		$count = 1;
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->get_token($token);
			$this->ptr = $index;
			$this->cache = [];
			$this->append_code($text, false);

			if ($start == $id) {
				++$count;
			}
			if ($end == $id) {
				--$count;
			}
			if (0 == $count) {
				break;
			}
		}
	}

	protected function walk_and_accumulate_until(&$tkns, $tknid) {
		$ret = '';
		while (list($index, $token) = each($tkns)) {
			list($id, $text) = $this->get_token($token);
			$this->ptr = $index;
			$ret .= $text;
			if ($tknid == $id) {
				return $ret;
			}
		}
	}

	protected function has_ln($text) {
		return (false !== strpos($text, $this->new_line));
	}

	protected function render($tkns = null) {
		if (null == $tkns) {
			$tkns = $this->tkns;
		}
		return implode('', array_map(function ($token) {
			list($id, $text) = $this->get_token($token);
			return $text;
		}, array_filter($tkns)));
	}
}
;
final class AddMissingCurlyBraces extends FormatterPass {
	public function format($source) {
		list($tmp, $changed) = $this->addBraces($source);
		while ($changed) {
			list($source, $changed) = $this->addBraces($tmp);
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
		$this->use_cache = true;
		$changed = false;
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->get_token($token);
			$this->ptr = $index;
			$this->cache = [];
			switch ($id) {
				case T_WHILE:
				case T_FOREACH:
				case T_FOR:
					$this->append_code($text, false);
					$paren_count = null;
					while (list($index, $token) = each($this->tkns)) {
						list($id, $text) = $this->get_token($token);
						$this->ptr = $index;
						$this->cache = [];
						if (ST_PARENTHESES_OPEN === $id) {
							++$paren_count;
						} elseif (ST_PARENTHESES_CLOSE === $id) {
							--$paren_count;
						}
						$this->append_code($text, false);
						if (0 === $paren_count && !$this->is_token([T_COMMENT, T_DOC_COMMENT])) {
							break;
						}
					}
					if (!$this->is_token([ST_CURLY_OPEN, ST_COLON, ST_SEMI_COLON])) {
						$while_in_next_token = $this->is_token([T_WHILE, T_DO]);
						$ignore_count = 0;
						if (!$this->is_token([T_COMMENT, T_DOC_COMMENT], true)) {
							$this->append_code($this->new_line . '{');
							// } else {
							// $this->append_code('{');
						}
						while (list($index, $token) = each($this->tkns)) {
							list($id, $text) = $this->get_token($token);
							$this->ptr = $index;
							$this->cache = [];

							if (ST_QUOTE == $id) {
								$this->append_code($text, false);
								$this->print_until_the_end_of_string();
								continue;
							}

							if (ST_PARENTHESES_OPEN === $id || ST_CURLY_OPEN === $id || ST_BRACKET_OPEN === $id) {
								++$ignore_count;
							} elseif (ST_PARENTHESES_CLOSE === $id || ST_CURLY_CLOSE === $id || ST_BRACKET_CLOSE === $id) {
								--$ignore_count;
							}
							$this->append_code($text, false);
							if ($ignore_count <= 0 && !($this->is_token([ST_CURLY_CLOSE, ST_SEMI_COLON, T_OBJECT_OPERATOR, ST_PARENTHESES_OPEN, ST_EQUAL]) || ($while_in_next_token && $this->is_token([T_WHILE]))) && (ST_CURLY_CLOSE === $id || ST_SEMI_COLON === $id || T_ELSE === $id || T_ELSEIF === $id)) {
								break;
							}
						}
						$this->append_code($this->get_crlf_indent() . '}' . $this->get_crlf_indent(), false);
						$changed = true;
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
						$this->cache = [];
						if (ST_PARENTHESES_OPEN === $id) {
							++$paren_count;
						} elseif (ST_PARENTHESES_CLOSE === $id) {
							--$paren_count;
						}
						$this->append_code($text, false);
						if (0 === $paren_count && !$this->is_token([T_COMMENT, T_DOC_COMMENT])) {
							break;
						}
					}
					if (!$this->is_token([ST_CURLY_OPEN, ST_COLON])) {
						$while_in_next_token = $this->is_token([T_WHILE, T_DO]);
						$ignore_count = 0;
						if (!$this->is_token([T_COMMENT, T_DOC_COMMENT], true)) {
							$this->append_code($this->new_line . '{');
							// } else {
							// $this->append_code('{');
						}
						while (list($index, $token) = each($this->tkns)) {
							list($id, $text) = $this->get_token($token);
							$this->ptr = $index;
							$this->cache = [];

							if (ST_QUOTE == $id) {
								$this->append_code($text, false);
								$this->print_until_the_end_of_string();
								continue;
							}

							if (ST_PARENTHESES_OPEN === $id || ST_CURLY_OPEN === $id || ST_BRACKET_OPEN === $id) {
								++$ignore_count;
							} elseif (ST_PARENTHESES_CLOSE === $id || ST_CURLY_CLOSE === $id || ST_BRACKET_CLOSE === $id) {
								--$ignore_count;
							}
							$this->append_code($text, false);
							if ($ignore_count <= 0 && !($this->is_token([ST_CURLY_CLOSE, ST_SEMI_COLON, T_OBJECT_OPERATOR, ST_PARENTHESES_OPEN]) || ($while_in_next_token && $this->is_token([T_WHILE]))) && (ST_CURLY_CLOSE === $id || ST_SEMI_COLON === $id || T_ELSE === $id || T_ELSEIF === $id)) {
								break;
							}
						}
						$this->append_code($this->get_crlf_indent() . '}' . $this->get_crlf_indent(), false);
						$changed = true;
						break 2;
					}
					break;
				case T_ELSE:
					$this->append_code($text, false);
					if (!$this->is_token([ST_CURLY_OPEN, ST_COLON, T_IF])) {
						$while_in_next_token = $this->is_token([T_WHILE, T_DO]);
						$ignore_count = 0;
						$this->append_code('{');
						while (list($index, $token) = each($this->tkns)) {
							list($id, $text) = $this->get_token($token);
							$this->ptr = $index;
							$this->cache = [];

							if (ST_QUOTE == $id) {
								$this->append_code($text, false);
								$this->print_until_the_end_of_string();
								continue;
							}

							if (ST_PARENTHESES_OPEN === $id || ST_CURLY_OPEN === $id || ST_BRACKET_OPEN === $id) {
								++$ignore_count;
							} elseif (ST_PARENTHESES_CLOSE === $id || ST_CURLY_CLOSE === $id || ST_BRACKET_CLOSE === $id) {
								--$ignore_count;
							}
							$this->append_code($text, false);
							if ($ignore_count <= 0 && !($this->is_token([ST_CURLY_CLOSE, ST_SEMI_COLON, T_OBJECT_OPERATOR, ST_PARENTHESES_OPEN]) || ($while_in_next_token && $this->is_token([T_WHILE]))) && (ST_CURLY_CLOSE === $id || ST_SEMI_COLON === $id || T_ELSE === $id || T_ELSEIF === $id)) {
								break;
							}
						}
						$this->append_code($this->get_crlf_indent() . '}' . $this->get_crlf_indent(), false);
						$changed = true;
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

		return [$this->code, $changed];
	}
}
;
class AddMissingParentheses extends FormatterPass {
	public function format($source) {
		$this->tkns = token_get_all($source);
		$this->code = '';
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->get_token($token);
			$this->ptr = $index;
			switch ($id) {
				case T_NEW:
					$this->append_code($text, false);
					list($found_id, $found_text) = $this->print_and_stop_at([ST_PARENTHESES_OPEN, T_COMMENT, T_DOC_COMMENT, ST_SEMI_COLON]);
					if (ST_PARENTHESES_OPEN != $found_id) {
						$this->append_code('()' . $found_text, false);
					}
					break;
				default:
					$this->append_code($text, false);
			}
		}

		return $this->code;
	}
}
;
final class AlignDoubleArrow extends FormatterPass {
	const ALIGNABLE_EQUAL = "\x2 EQUAL%d.%d.%d \x3";// level.levelentracecounter.counter
	public function format($source) {
		$this->tkns = token_get_all($source);
		$this->code = '';

		$level_counter = 0;
		$level_entrance_counter = [];
		$context_counter = [];
		$max_context_counter = [];

		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->get_token($token);
			$this->ptr = $index;
			switch ($id) {
				case ST_COMMA:
					if (!$this->has_ln_after() && !$this->has_ln_next_token()) {
						if (!isset($level_entrance_counter[$level_counter])) {
							$level_entrance_counter[$level_counter] = 0;
						}
						if (!isset($context_counter[$level_counter][$level_entrance_counter[$level_counter]])) {
							$context_counter[$level_counter][$level_entrance_counter[$level_counter]] = 0;
							$max_context_counter[$level_counter][$level_entrance_counter[$level_counter]] = 0;
						}
						++$context_counter[$level_counter][$level_entrance_counter[$level_counter]];
						$max_context_counter[$level_counter][$level_entrance_counter[$level_counter]] = max($max_context_counter[$level_counter][$level_entrance_counter[$level_counter]], $context_counter[$level_counter][$level_entrance_counter[$level_counter]]);
					} elseif ($context_counter[$level_counter][$level_entrance_counter[$level_counter]] > 1) {
						$context_counter[$level_counter][$level_entrance_counter[$level_counter]] = 1;
					}
					$this->append_code($text, false);
					break;

				case T_DOUBLE_ARROW:
					$this->append_code(
						sprintf(
							self::ALIGNABLE_EQUAL,
							$level_counter,
							$level_entrance_counter[$level_counter],
							$context_counter[$level_counter][$level_entrance_counter[$level_counter]]
						) . $text,
						false
					);
					break;

				case ST_PARENTHESES_OPEN:
				case ST_BRACKET_OPEN:
					++$level_counter;
					if (!isset($level_entrance_counter[$level_counter])) {
						$level_entrance_counter[$level_counter] = 0;
					}
					++$level_entrance_counter[$level_counter];
					if (!isset($context_counter[$level_counter][$level_entrance_counter[$level_counter]])) {
						$context_counter[$level_counter][$level_entrance_counter[$level_counter]] = 0;
						$max_context_counter[$level_counter][$level_entrance_counter[$level_counter]] = 0;
					}
					++$context_counter[$level_counter][$level_entrance_counter[$level_counter]];
					$max_context_counter[$level_counter][$level_entrance_counter[$level_counter]] = max($max_context_counter[$level_counter][$level_entrance_counter[$level_counter]], $context_counter[$level_counter][$level_entrance_counter[$level_counter]]);

					$this->append_code($text, false);
					break;

				case ST_PARENTHESES_CLOSE:
				case ST_BRACKET_CLOSE:
					--$level_counter;
					$this->append_code($text, false);
					break;

				default:
					$this->append_code($text, false);
					break;
			}
		}

		foreach ($max_context_counter as $level => $entrances) {
			foreach ($entrances as $entrance => $context) {
				for ($j = 0; $j <= $context; ++$j) {
					$placeholder = sprintf(self::ALIGNABLE_EQUAL, $level, $entrance, $j);
					if (false === strpos($this->code, $placeholder)) {
						continue;
					}
					if (1 === substr_count($this->code, $placeholder)) {
						$this->code = str_replace($placeholder, '', $this->code);
						continue;
					}

					$lines = explode($this->new_line, $this->code);
					$lines_with_objop = [];
					$block_count = 0;

					foreach ($lines as $idx => $line) {
						if (false !== strpos($line, $placeholder)) {
							$lines_with_objop[] = $idx;
						}
					}

					$farthest = 0;
					foreach ($lines_with_objop as $idx) {
						$farthest = max($farthest, strpos($lines[$idx], $placeholder));
					}
					foreach ($lines_with_objop as $idx) {
						$line = $lines[$idx];
						$current = strpos($line, $placeholder);
						$delta = abs($farthest - $current);
						if ($delta > 0) {
							$line = str_replace($placeholder, str_repeat(' ', $delta) . $placeholder, $line);
							$lines[$idx] = $line;
						}
					}

					$this->code = str_replace($placeholder, '', implode($this->new_line, $lines));
				}
			}
		}

		return $this->code;
	}
}
;
final class AlignEquals extends FormatterPass {
	const ALIGNABLE_EQUAL = "\x2 EQUAL%d \x3";
	public function format($source) {
		$this->tkns = token_get_all($source);
		$this->code = '';
		$paren_count = 0;
		$bracket_count = 0;
		$context_counter = 0;
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->get_token($token);
			$this->ptr = $index;
			switch ($id) {
				case T_FUNCTION:
					++$context_counter;
					$this->append_code($text, false);
					break;
				case ST_PARENTHESES_OPEN:
					++$paren_count;
					$this->append_code($text, false);
					break;
				case ST_PARENTHESES_CLOSE:
					--$paren_count;
					$this->append_code($text, false);
					break;
				case ST_BRACKET_OPEN:
					++$bracket_count;
					$this->append_code($text, false);
					break;
				case ST_BRACKET_CLOSE:
					--$bracket_count;
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

		for ($j = 0; $j <= $context_counter; ++$j) {
			$placeholder = sprintf(self::ALIGNABLE_EQUAL, $j);
			if (false === strpos($this->code, $placeholder)) {
				continue;
			}
			if (1 === substr_count($this->code, $placeholder)) {
				$this->code = str_replace($placeholder, '', $this->code);
				continue;
			}

			$lines = explode($this->new_line, $this->code);
			$lines_with_objop = [];
			$block_count = 0;

			foreach ($lines as $idx => $line) {
				if (false !== strpos($line, $placeholder)) {
					$lines_with_objop[$block_count][] = $idx;
				} else {
					++$block_count;
					$lines_with_objop[$block_count] = [];
				}
			}

			$i = 0;
			foreach ($lines_with_objop as $group) {
				++$i;
				$farthest = 0;
				foreach ($group as $idx) {
					$farthest = max($farthest, strpos($lines[$idx], $placeholder));
				}
				foreach ($group as $idx) {
					$line = $lines[$idx];
					$current = strpos($line, $placeholder);
					$delta = abs($farthest - $current);
					if ($delta > 0) {
						$line = str_replace($placeholder, str_repeat(' ', $delta) . $placeholder, $line);
						$lines[$idx] = $line;
					}
				}
			}

			$this->code = str_replace($placeholder, '', implode($this->new_line, $lines));
		}

		return $this->code;
	}
};
final class AutoImportPass extends FormatterPass {
	const OPENER_PLACEHOLDER = "<?php /*\x2 AUTOIMPORTNS \x3*/";
	const AUTOIMPORT_PLACEHOLDER = "/*\x2 AUTOIMPORT \x3*/";
	private $oracle = null;

	public function __construct($oracleFn) {
		$this->oracle = unserialize(file_get_contents($oracleFn));
	}

	private function used_alias_list($source) {
		$tokens = token_get_all($source);
		$use_stack = [];
		$new_tokens = [];
		$next_tokens = [];
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
					$token = new SurrogateToken();
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
		$alias_list = [];
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
		foreach ($new_tokens as $token) {
			if (!($token instanceof SurrogateToken)) {
				list($id, $text) = $this->get_token($token);
				$lower_text = strtolower($text);
				if (T_STRING === $id && isset($alias_list[$lower_text])) {
					++$alias_count[$lower_text];
				}
			}
		}

		while (list(, $token) = each($tokens)) {
			list($id, $text) = $this->get_token($token);
			$lower_text = strtolower($text);
			if (T_STRING === $id && isset($alias_list[$lower_text])) {
				++$alias_count[$lower_text];
			} elseif (T_DOC_COMMENT === $id) {
				foreach ($alias_list as $alias => $use) {
					if (false !== stripos($text, $alias)) {
						++$alias_count[$alias];
					}
				}
			}
		}
		return $alias_count;
	}

	private function singleNamespace($source) {
		$class_list = [];
		$class_names = array_keys($this->oracle['all_classes']);
		foreach ($class_names as $class_name) {
			$class_name_parts = explode('\\', $class_name);
			$base_class_name = '';
			while ($cnp = array_pop($class_name_parts)) {
				$base_class_name = $cnp . $base_class_name;
				$class_list[strtolower($base_class_name)][] = ltrim(str_replace('\\\\', '\\', '\\' . $class_name) . ' as ' . $base_class_name, '\\');
			}
		}

		$tokens = token_get_all($source);
		$alias_count = [];
		$namespace_name = '';
		while (list($index, $token) = each($tokens)) {
			list($id, $text) = $this->get_token($token);
			if (T_NAMESPACE == $id) {
				while (list($index, $token) = each($tokens)) {
					list($id, $text) = $this->get_token($token);
					if (T_NS_SEPARATOR == $id || T_STRING == $id) {
						$namespace_name .= $text;
					}
					if (ST_SEMI_COLON == $id || ST_CURLY_OPEN == $id) {
						break;
					}
				}
			}
			if (T_USE == $id || T_NAMESPACE == $id || T_FUNCTION == $id || T_DOUBLE_COLON == $id || T_OBJECT_OPERATOR == $id) {
				while (list($index, $token) = each($tokens)) {
					list($id, $text) = $this->get_token($token);
					if (ST_SEMI_COLON == $id || ST_PARENTHESES_OPEN == $id || ST_CURLY_OPEN == $id) {
						break;
					}
				}
			}
			if (T_CLASS == $id) {
				while (list($index, $token) = each($tokens)) {
					list($id, $text) = $this->get_token($token);
					if (T_EXTENDS == $id || T_IMPLEMENTS == $id || ST_CURLY_OPEN == $id) {
						break;
					}
				}
			}

			$lower_text = strtolower($text);
			if (T_STRING === $id) {
				if (!isset($alias_count[$lower_text])) {
					$alias_count[$lower_text] = 0;
				}
				++$alias_count[$lower_text];
			}
		}
		$auto_import_candidates = array_intersect_key($class_list, $alias_count);

		$tokens = token_get_all($source);
		$touched_namespace = false;
		$touched_function = false;
		$return = '';
		while (list(, $token) = each($tokens)) {
			list($id, $text) = $this->get_token($token);

			if (T_NAMESPACE == $id) {
				$touched_namespace = true;
			}
			if (T_FUNCTION == $id) {
				$touched_function = true;
			}
			if (!$touched_function && $touched_namespace && (T_FINAL == $id || T_STATIC == $id || T_USE == $id || T_CLASS == $id || T_INTERFACE == $id || T_TRAIT == $id)) {
				$return .= self::AUTOIMPORT_PLACEHOLDER . $this->new_line;
				$return .= $text;

				break;
			}
			$return .= $text;
		}
		while (list(, $token) = each($tokens)) {
			list($id, $text) = $this->get_token($token);
			$return .= $text;
		}

		$used_alias = $this->used_alias_list($source);
		$replacement = '';
		foreach ($auto_import_candidates as $alias => $candidates) {
			if (isset($used_alias[$alias])) {
				continue;
			}
			usort($candidates, function ($a, $b) use ($namespace_name) {
				return similar_text($a, $namespace_name) < similar_text($b, $namespace_name);
			});
			$replacement .= 'use ' . implode(';' . $this->new_line . '//use ', $candidates) . ';' . $this->new_line;
		}

		$return = str_replace(self::AUTOIMPORT_PLACEHOLDER . $this->new_line, $replacement, $return);
		return $return;
	}
	public function format($source = '') {
		$namespace_count = 0;
		$tokens = token_get_all($source);
		while (list(, $token) = each($tokens)) {
			list($id, $text) = $this->get_token($token);
			if (T_NAMESPACE == $id) {
				++$namespace_count;
			}
		}
		if ($namespace_count <= 1) {
			return $this->singleNamespace($source);
		}

		$return = '';
		reset($tokens);
		while (list($index, $token) = each($tokens)) {
			list($id, $text) = $this->get_token($token);
			$this->ptr = $index;
			switch ($id) {
				case T_NAMESPACE:
					$return .= $text;
					while (list($index, $token) = each($tokens)) {
						list($id, $text) = $this->get_token($token);
						$this->ptr = $index;
						$return .= $text;
						if ($id == ST_CURLY_OPEN) {
							break;
						}
					}
					$namespace_block = '';
					$curly_count = 1;
					while (list($index, $token) = each($tokens)) {
						list($id, $text) = $this->get_token($token);
						$this->ptr = $index;
						$namespace_block .= $text;
						if ($id == ST_CURLY_OPEN) {
							++$curly_count;
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
};
final class AutoPreincrement extends FormatterPass {
	const CHAIN_VARIABLE = 'CHAIN_VARIABLE';
	const CHAIN_LITERAL = 'CHAIN_LITERAL';
	const CHAIN_FUNC = 'CHAIN_FUNC';
	const CHAIN_STRING = 'CHAIN_STRING';
	const PARENTHESES_BLOCK = 'PARENTHESES_BLOCK';
	public function format($source) {
		return $this->swap($source);
	}
	private function swap($source) {
		$tkns = $this->aggregate_variables($source);
		reset($tkns);
		while (list($ptr, $token) = each($tkns)) {
			list($id, $text) = $this->get_token($token);
			switch ($id) {
				case T_INC:
				case T_DEC:
					$prev_token = $tkns[$ptr - 1];
					list($prev_id, ) = $prev_token;
					if (T_VARIABLE == $prev_id || self::CHAIN_VARIABLE == $prev_id) {
						list($tkns[$ptr], $tkns[$ptr - 1]) = [$tkns[$ptr - 1], $tkns[$ptr]];
						break;
					}
			}
		}
		return implode('', array_map(function ($token) {
			list($id, $text) = $this->get_token($token);
			return $text;
		}, array_filter($tkns)));
	}

	private function aggregate_variables($source) {
		$tkns = token_get_all($source);
		reset($tkns);
		while (list($ptr, $token) = each($tkns)) {
			list($id, $text) = $this->get_token($token);

			if (ST_PARENTHESES_OPEN == $id) {
				$initial_ptr = $ptr;
				$tmp = $this->scan_and_replace($tkns, $ptr, ST_PARENTHESES_OPEN, ST_PARENTHESES_CLOSE);
				$tkns[$initial_ptr] = [self::PARENTHESES_BLOCK, $tmp];
				continue;
			}
			if (ST_QUOTE == $id) {
				$stack = $text;
				$initial_ptr = $ptr;
				while (list($ptr, $token) = each($tkns)) {
					list($id, $text) = $this->get_token($token);
					$stack .= $text;
					$tkns[$ptr] = null;
					if (ST_QUOTE == $id) {
						break;
					}
				}

				$tkns[$initial_ptr] = [self::CHAIN_STRING, $stack];
				continue;
			}

			if (T_STRING == $id || T_VARIABLE == $id || T_NS_SEPARATOR == $id) {
				$initial_index = $ptr;
				$stack = $text;
				$touched_variable = false;
				if (T_VARIABLE == $id) {
					$touched_variable = true;
				}
				if (!$this->is_token_in_subset(
					$tkns,
					$ptr,
					[T_STRING, T_VARIABLE, T_NS_SEPARATOR, T_OBJECT_OPERATOR, T_DOUBLE_COLON, ST_CURLY_OPEN, ST_PARENTHESES_OPEN, ST_BRACKET_OPEN]
				)) {
					continue;
				}

				while (list($ptr, $token) = each($tkns)) {
					list($id, $text) = $this->get_token($token);
					// if (ST_CURLY_CLOSE == $id || ST_BRACKET_CLOSE == $id || ST_PARENTHESES_CLOSE == $id || ST_SEMI_COLON == $id) {
					// 	$token = prev($tkns);
					// 	$ptr = key($tkns);
					// 	list($id, $text) = $this->get_token($token);
					// 	break;
					// }
					$tkns[$ptr] = null;
					if (ST_CURLY_OPEN == $id) {
						$text = $this->scan_and_replace($tkns, $ptr, ST_CURLY_OPEN, ST_CURLY_CLOSE);
					} elseif (ST_BRACKET_OPEN == $id) {
						$text = $this->scan_and_replace($tkns, $ptr, ST_BRACKET_OPEN, ST_BRACKET_CLOSE);
					} elseif (ST_PARENTHESES_OPEN == $id) {
						$text = $this->scan_and_replace($tkns, $ptr, ST_PARENTHESES_OPEN, ST_PARENTHESES_CLOSE);
					}

					$stack .= $text;

					if (!$touched_variable && T_VARIABLE == $id) {
						$touched_variable = true;
					}

					if (
						!$this->is_token_in_subset(
							$tkns,
							$ptr,
							[T_STRING, T_VARIABLE, T_NS_SEPARATOR, T_OBJECT_OPERATOR, T_DOUBLE_COLON, ST_CURLY_OPEN, ST_PARENTHESES_OPEN, ST_BRACKET_OPEN]
						)
					) {
						break;
					}
				}
				if (substr(trim($stack), -1, 1) == ST_PARENTHESES_CLOSE) {
					$tkns[$initial_index] = [self::CHAIN_FUNC, $stack];
				} elseif ($touched_variable) {
					$tkns[$initial_index] = [self::CHAIN_VARIABLE, $stack];
				} else {
					$tkns[$initial_index] = [self::CHAIN_LITERAL, $stack];
				}
			}
		}
		$tkns = array_values(array_filter($tkns));
		return $tkns;
	}

	private function scan_and_replace(&$tkns, &$ptr, $start, $end) {
		$placeholder = '<?php' . ' /*\x2 PHPOPEN \x3*/';
		$tmp = $placeholder;
		$tkn_count = 1;
		while (list($ptr, $token) = each($tkns)) {
			list($id, $text) = $this->get_token($token);
			if ($start == $id) {
				++$tkn_count;
			}
			if ($end == $id) {
				--$tkn_count;
			}
			$tkns[$ptr] = null;
			if (0 == $tkn_count) {
				break;
			}
			$tmp .= $text;
		}
		return $start . str_replace($placeholder, '', $this->swap($tmp)) . $end;
	}
};
final class ConstructorPass extends FormatterPass {
	const TYPE_CAMEL_CASE = 'camel';
	const TYPE_SNAKE_CASE = 'snake';
	const TYPE_GOLANG = 'golang';
	public function __construct($type = self::TYPE_CAMEL_CASE) {
		if (self::TYPE_CAMEL_CASE == $type || self::TYPE_SNAKE_CASE == $type || self::TYPE_GOLANG == $type) {
			$this->type = $type;
		} else {
			$this->type = self::TYPE_CAMEL_CASE;
		}
	}

	public function format($source) {
		$this->tkns = token_get_all($source);
		$this->code = '';

		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->get_token($token);
			$this->ptr = $index;
			switch ($id) {
				case T_CLASS:
					$attributes = [];
					$function_list = [];
					$touched_visibility = false;
					$touched_function = false;
					$curly_count = null;
					$this->append_code($text, false);
					while (list($index, $token) = each($this->tkns)) {
						list($id, $text) = $this->get_token($token);
						$this->ptr = $index;
						if (ST_CURLY_OPEN == $id) {
							++$curly_count;
						}
						if (ST_CURLY_CLOSE == $id) {
							--$curly_count;
						}
						if (0 === $curly_count) {
							break;
						}
						$this->append_code($text, false);
						if (T_PUBLIC == $id) {
							$touched_visibility = T_PUBLIC;
						} elseif (T_PRIVATE == $id) {
							$touched_visibility = T_PRIVATE;
						} elseif (T_PROTECTED == $id) {
							$touched_visibility = T_PROTECTED;
						}
						if (
							T_VARIABLE == $id &&
							(
								T_PUBLIC == $touched_visibility ||
								T_PRIVATE == $touched_visibility ||
								T_PROTECTED == $touched_visibility
							)
						) {
							$attributes[] = $text;
							$touched_visibility = null;
						} elseif (T_FUNCTION == $id) {
							$touched_function = true;
						} elseif ($touched_function && T_STRING == $id) {
							$function_list[] = $text;
							$touched_visibility = null;
							$touched_function = false;
						}
					}
					$function_list = array_combine($function_list, $function_list);
					if (!isset($function_list['__construct'])) {
						$this->append_code('function __construct(' . implode(', ', $attributes) . '){' . $this->new_line, false);
						foreach ($attributes as $var) {
							$this->append_code($this->generate($var), false);
						}
						$this->append_code('}' . $this->new_line, false);
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

	private function generate($var) {
		switch ($this->type) {
			case self::TYPE_SNAKE_CASE:
				$ret = $this->generateSnakeCase($var);
				break;
			case self::TYPE_GOLANG:
				$ret = $this->generateGolang($var);
				break;
			case self::TYPE_CAMEL_CASE:
			default:
				$ret = $this->generateCamelCase($var);
				break;
		}
		return $ret;
	}
	private function generateCamelCase($var) {
		$str = '$this->set' . ucfirst(str_replace('$', '', $var)) . '(' . $var . ');' . $this->new_line;
		return $str;
	}
	private function generateSnakeCase($var) {
		$str = '$this->set_' . (str_replace('$', '', $var)) . '(' . $var . ');' . $this->new_line;
		return $str;
	}
	private function generateGolang($var) {
		$str = '$this->Set' . ucfirst(str_replace('$', '', $var)) . '(' . $var . ');' . $this->new_line;
		return $str;
	}
};
class WrongConstructorName extends FormatterPass {
	public function format($source) {
		$this->tkns = token_get_all($source);
		$this->code = '';
		$touched_namespace = false;
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->get_token($token);
			$this->ptr = $index;
			switch ($id) {
				case T_NAMESPACE:
					$touched_namespace = true;
					$this->append_code($text, false);
					break;
				case T_CLASS:
					$this->append_code($text, false);
					if ($this->is_token([T_DOUBLE_COLON], true, $this->ignore_futile_tokens)) {
						break;
					}
					if ($touched_namespace) {
						break;
					}
					$class_local_name = '';
					while (list($index, $token) = each($this->tkns)) {
						list($id, $text) = $this->get_token($token);
						$this->ptr = $index;
						$this->append_code($text, false);
						if (T_STRING == $id) {
							$class_local_name = strtolower($text);
						}
						if (T_EXTENDS == $id || T_IMPLEMENTS == $id || ST_CURLY_OPEN == $id) {
							break;
						}
					}
					if (ST_CURLY_OPEN != $id) {
						$this->print_until(ST_CURLY_OPEN);
					}
					$count = 1;
					while (list($index, $token) = each($this->tkns)) {
						list($id, $text) = $this->get_token($token);
						$this->ptr = $index;

						if (T_STRING == $id && $this->is_token([T_FUNCTION], true, $this->ignore_futile_tokens) && strtolower($text) == $class_local_name) {
							$text = '__construct';
						}
						$this->append_code($text, false);

						if (ST_CURLY_OPEN == $id) {
							++$count;
						}
						if (ST_CURLY_CLOSE == $id) {
							--$count;
						}
						if (0 == $count) {
							break;
						}
					}
					break;
				default:
					$this->append_code($text, false);
			}
		}

		return $this->code;
	}
};
final class EliminateDuplicatedEmptyLines extends FormatterPass {
	const EMPTY_LINE = "\x2 EMPTYLINE \x3";

	public function format($source) {
		$this->tkns = token_get_all($source);
		$this->code = '';
		$paren_count = 0;
		$bracket_count = 0;
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->get_token($token);
			$this->ptr = $index;
			switch ($id) {
				case T_WHITESPACE:
					$text = str_replace($this->new_line, self::EMPTY_LINE . $this->new_line, $text);
					$this->append_code($text, false);
					break;
				default:
					$this->append_code($text, false);
					break;
			}
		}

		$lines = explode($this->new_line, $this->code);
		$empty_lines = [];
		$block_count = 0;

		foreach ($lines as $idx => $line) {
			if (trim($line) === self::EMPTY_LINE) {
				$empty_lines[$block_count][] = $idx;
			} else {
				++$block_count;
				$empty_lines[$block_count] = [];
			}
		}

		foreach ($empty_lines as $group) {
			array_pop($group);
			foreach ($group as $line_number) {
				unset($lines[$line_number]);
			}
		}

		$this->code = str_replace(self::EMPTY_LINE, '', implode($this->new_line, $lines));

		list($id, $text) = $this->get_token(array_pop($this->tkns));
		if (T_WHITESPACE === $id && '' === trim($text)) {
			$this->code = rtrim($this->code) . $this->new_line;
		}

		return $this->code;
	}
};
final class ExtraCommaInArray extends FormatterPass {
	const ST_SHORT_ARRAY_OPEN = 'SHORT_ARRAY_OPEN';
	public function format($source) {
		$this->tkns = token_get_all($source);

		$context_stack = [];
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->get_token($token);
			$this->ptr = $index;
			switch ($id) {
				case ST_BRACKET_OPEN:
					if (!$this->is_token([ST_BRACKET_CLOSE, T_STRING, T_VARIABLE], true)) {
						$context_stack[] = self::ST_SHORT_ARRAY_OPEN;
					} else {
						$context_stack[] = ST_BRACKET_OPEN;
					}
					break;
				case ST_BRACKET_CLOSE:
					if (isset($context_stack[0]) && !$this->is_token(ST_BRACKET_OPEN, true)) {
						if (self::ST_SHORT_ARRAY_OPEN == end($context_stack) && $this->has_ln_before() && !$this->is_token(ST_COMMA, true, [T_WHITESPACE, T_DOC_COMMENT, T_COMMENT])) {
							$prev_token_idx = $this->prev_token([T_WHITESPACE, T_DOC_COMMENT, T_COMMENT], true);
							list($tkn_id, $tkn_text) = $this->get_token($this->tkns[$prev_token_idx]);
							if (T_END_HEREDOC != $tkn_id && ST_BRACKET_OPEN != $tkn_id) {
								$this->tkns[$prev_token_idx] = [$tkn_id, $tkn_text . ','];
							}
						}
						array_pop($context_stack);
					}
					break;
				case T_STRING:
					if ($this->is_token(ST_PARENTHESES_OPEN)) {
						$context_stack[] = T_STRING;
					}
					break;
				case T_ARRAY:
					if ($this->is_token(ST_PARENTHESES_OPEN)) {
						$context_stack[] = T_ARRAY;
					}
					break;
				case ST_PARENTHESES_OPEN:
					if (isset($context_stack[0]) && T_ARRAY == end($context_stack) && $this->is_token(ST_PARENTHESES_CLOSE)) {
						array_pop($context_stack);
					} elseif (!$this->is_token([T_ARRAY, T_STRING], true)) {
						$context_stack[] = ST_PARENTHESES_OPEN;
					}
					break;
				case ST_PARENTHESES_CLOSE:
					if (isset($context_stack[0])) {
						if (T_ARRAY == end($context_stack) && ($this->has_ln_prev_token() || $this->has_ln_before()) && !$this->is_token(ST_COMMA, true, [T_WHITESPACE, T_DOC_COMMENT, T_COMMENT])) {
							$prev_token_idx = $this->prev_token([T_WHITESPACE, T_DOC_COMMENT, T_COMMENT], true);
							list($tkn_id, $tkn_text) = $this->get_token($this->tkns[$prev_token_idx]);
							if (T_END_HEREDOC != $tkn_id && ST_PARENTHESES_OPEN != $tkn_id) {
								$this->tkns[$prev_token_idx] = [$tkn_id, $tkn_text . ','];
							}
						}
						array_pop($context_stack);
					}
					break;

			}
		}
		return $this->render();
	}
};
final class GeneratePHPDoc extends FormatterPass {
	public function format($source) {
		$this->tkns = token_get_all($source);
		$this->code = '';
		$touched_visibility = false;
		$touched_doc_comment = false;
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->get_token($token);
			$this->ptr = $index;
			switch ($id) {
				case T_DOC_COMMENT:
					$touched_doc_comment = true;
				case T_FINAL:
				case T_ABSTRACT:
				case T_PUBLIC:
				case T_PROTECTED:
				case T_PRIVATE:
				case T_STATIC:
					if (!$this->is_token([T_FINAL, T_PUBLIC, T_PROTECTED, T_PRIVATE, T_STATIC, T_ABSTRACT], true)) {
						$touched_visibility = true;
						$visibility_idx = $this->ptr;
					}
				case T_FUNCTION:
					if ($touched_doc_comment) {
						$touched_doc_comment = false;
						break;
					}
					if (!$touched_visibility) {
						$orig_idx = $this->ptr;
					} else {
						$orig_idx = $visibility_idx;
					}
					list($nt_id, $nt_text) = $this->get_token($this->next_token());
					if (T_STRING != $nt_id) {
						$this->append_code($text, false);
						break;
					}
					$this->walk_until(ST_PARENTHESES_OPEN);
					$param_stack = [];
					$tmp = ['type' => '', 'name' => ''];
					$count = 1;
					while (list($index, $token) = each($this->tkns)) {
						list($id, $text) = $this->get_token($token);
						$this->ptr = $index;

						if (ST_PARENTHESES_OPEN == $id) {
							++$count;
						}
						if (ST_PARENTHESES_CLOSE == $id) {
							--$count;
						}
						if (0 == $count) {
							break;
						}
						if (T_STRING == $id || T_NS_SEPARATOR == $id) {
							$tmp['type'] .= $text;
							continue;
						}
						if (T_VARIABLE == $id) {
							if ($this->is_token([ST_EQUAL]) && $this->walk_until(ST_EQUAL) && $this->is_token([T_ARRAY])) {
								$tmp['type'] = 'array';
							}
							$tmp['name'] = $text;
							$param_stack[] = $tmp;
							$tmp = ['type' => '', 'name' => ''];
							continue;
						}
					}

					$return_stack = '';
					if (!$this->is_token(ST_SEMI_COLON, false, $this->ignore_futile_tokens)) {
						$this->walk_until(ST_CURLY_OPEN);
						$count = 1;
						while (list($index, $token) = each($this->tkns)) {
							list($id, $text) = $this->get_token($token);
							$this->ptr = $index;

							if (ST_CURLY_OPEN == $id) {
								++$count;
							}
							if (ST_CURLY_CLOSE == $id) {
								--$count;
							}
							if (0 == $count) {
								break;
							}
							if (T_RETURN == $id) {
								if ($this->is_token([T_DNUMBER])) {
									$return_stack = 'float';
								} elseif ($this->is_token([T_LNUMBER])) {
									$return_stack = 'int';
								} elseif ($this->is_token([T_VARIABLE])) {
									$return_stack = 'mixed';
								} elseif ($this->is_token([ST_SEMI_COLON])) {
									$return_stack = 'null';
								}
							}
						}
					}

					$func_token = &$this->tkns[$orig_idx];
					$func_token[1] = $this->render_doc_block($param_stack, $return_stack) . $func_token[1];
					$touched_visibility = false;
			}
		}

		return implode('', array_map(function ($token) {
			list(, $text) = $this->get_token($token);
			return $text;
		}, $this->tkns));
	}

	private function render_doc_block(array $param_stack, $return_stack) {
		if (empty($param_stack) && empty($return_stack)) {
			return '';
		}
		$str = '/**' . $this->new_line;
		foreach ($param_stack as $param) {
			$str .= rtrim(' * @param ' . $param['type']) . ' ' . $param['name'] . $this->new_line;
		}
		if (!empty($return_stack)) {
			$str .= ' * @return ' . $return_stack . $this->new_line;
		}
		$str .= ' */' . $this->new_line;
		return $str;
	}
}
;
class JoinToImplode extends FormatterPass {
	public function format($source) {
		$this->tkns = token_get_all($source);

		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->get_token($token);
			$this->ptr = $index;
			if (T_STRING == $id && strtolower($text) == 'join' && !$this->is_token([T_STRING, T_DOUBLE_COLON, T_OBJECT_OPERATOR], true, $this->ignore_futile_tokens)) {
				$this->append_code('implode', false);
				continue;
			}
			$this->append_code($text, false);
		}

		return $this->code;
	}
}
;
final class LeftAlignComment extends FormatterPass {
	const NON_INDENTABLE_COMMENT = "/*\x2 COMMENT \x3*/";
	public function format($source) {
		$this->tkns = token_get_all($source);
		$this->code = '';
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->get_token($token);
			$this->ptr = $index;
			if (self::NON_INDENTABLE_COMMENT === $text) {
				continue;
			}
			switch ($id) {
				case T_COMMENT:
				case T_DOC_COMMENT:
					list(, $prev_text) = $this->inspect_token(-1);
					if (self::NON_INDENTABLE_COMMENT === $prev_text) {
						// Benchmark me
						// $new_text = '';
						// $tok = strtok($text, $this->new_line);
						// while (false !== $tok) {
						// 	$v = ltrim($tok);
						// 	if ('*' === substr($v, 0, 1)) {
						// 		$v = ' ' . $v;
						// 	}
						// 	$new_text .= $v;
						// 	if (substr($v, -2, 2) != '*/') {
						// 		$new_text .= $this->new_line;
						// 	}
						// 	$tok = strtok($this->new_line);
						// }
						// $this->append_code($new_text, false);
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
					if (self::NON_INDENTABLE_COMMENT === $next_text && substr_count($text, "\n") >= 2) {
						$text = substr($text, 0, strrpos($text, "\n") + 1);
						$this->append_code($text, false);
						break;
					} elseif (self::NON_INDENTABLE_COMMENT === $next_text && substr_count($text, "\n") === 1) {
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
;
final class MergeCurlyCloseAndDoWhile extends FormatterPass {
	public function format($source) {
		$this->tkns = token_get_all($source);
		$this->code = '';
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->get_token($token);
			$this->ptr = $index;
			switch ($id) {
				case T_WHILE:
					$str = $text;
					list($pt_id, $pt_text) = $this->get_token($this->prev_token());
					while (list($index, $token) = each($this->tkns)) {
						list($id, $text) = $this->get_token($token);
						$this->ptr = $index;
						$str .= $text;
						if (
							ST_CURLY_OPEN == $id ||
							ST_COLON == $id ||
							(ST_SEMI_COLON == $id && (ST_SEMI_COLON == $pt_id || ST_CURLY_OPEN == $pt_id || T_COMMENT == $pt_id || T_DOC_COMMENT == $pt_id))
						) {
							$this->append_code($str, false);
							break;
						} elseif (ST_SEMI_COLON == $id && !(ST_SEMI_COLON == $pt_id || ST_CURLY_OPEN == $pt_id || T_COMMENT == $pt_id || T_DOC_COMMENT == $pt_id)) {
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
;
final class MergeDoubleArrowAndArray extends FormatterPass {
	public function format($source) {
		$this->tkns = token_get_all($source);
		$this->code = '';
		$in_do_while_context = 0;
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->get_token($token);
			$this->ptr = $index;
			switch ($id) {
				case T_ARRAY:
					if ($this->is_token([T_DOUBLE_ARROW], true)) {
						--$in_do_while_context;
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
};
/**
 * From PHP-CS-Fixer
 */
class MergeElseIf extends FormatterPass {
	public function format($source) {
		$this->tkns = token_get_all($source);
		$this->code = '';
		$paren_count = 0;

		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->get_token($token);
			$this->ptr = $index;
			switch ($id) {
				case T_IF:
					if ($this->is_token([T_ELSE], true) && !$this->is_token([T_OPEN_TAG, T_OPEN_TAG_WITH_ECHO], true)) {
						$this->append_code($text, true);
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
;
final class MergeParenCloseWithCurlyOpen extends FormatterPass {
	public function format($source) {
		$this->tkns = token_get_all($source);
		$this->code = '';
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->get_token($token);
			$this->ptr = $index;
			switch ($id) {
				case ST_CURLY_OPEN:
					if ($this->is_token([T_ELSE, T_STRING, ST_PARENTHESES_CLOSE], true)) {
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
;
final class NormalizeLnAndLtrimLines extends FormatterPass {
	public function format($source) {
		$source = str_replace(["\r\n", "\n\r", "\r", "\n"], $this->new_line, $source);

		$this->tkns = token_get_all($source);
		$this->code = '';
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->get_token($token);
			$this->ptr = $index;
			switch ($id) {
				case ST_QUOTE:
					$this->append_code($text, false);
					$this->print_until_the_end_of_string();
					break;
				case T_START_HEREDOC:
					$this->append_code($text, false);
					$this->print_until(T_END_HEREDOC);
					break;
				case T_COMMENT:
				case T_DOC_COMMENT:
					list($prev_id, $prev_text) = $this->inspect_token(-1);

					if (T_WHITESPACE === $prev_id && ("\n" === $prev_text || "\n\n" == substr($prev_text, -2, 2))) {
						$this->append_code(LeftAlignComment::NON_INDENTABLE_COMMENT, false);
					}

					$text = implode(
						$this->new_line,
						array_map(function ($v) {
							$v = ltrim($v);
							if ('*' === substr($v, 0, 1)) {
								$v = ' ' . $v;
							}
							return $v;
						}, explode($this->new_line, $text))
					);

					$this->append_code($text, false);
					break;
				case T_CONSTANT_ENCAPSED_STRING:
					$this->append_code($text, false);
					break;
				default:
					$trailing_new_line = $this->substr_count_trailing($text, $this->new_line);
					if ($trailing_new_line > 0) {
						$text = trim($text) . str_repeat($this->new_line, $trailing_new_line);
					} elseif (0 === $trailing_new_line && T_WHITESPACE === $id) {
						$text = $this->get_space() . ltrim($text);
					}
					$this->append_code($text, false);
					break;
			}
		}

		return $this->code;
	}
}
;
final class NormalizeIsNotEquals extends FormatterPass {
	public function format($source) {
		$this->tkns = token_get_all($source);
		$this->code = '';
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->get_token($token);
			$this->ptr = $index;
			switch ($id) {
				case T_IS_NOT_EQUAL:
					$this->append_code(str_replace('<>', '!=', $text) . $this->get_space(), false);
					break;
				default:
					$this->append_code($text, false);
					break;
			}
		}

		return $this->code;
	}
}
;
final class OrderMethod extends FormatterPass {
	const OPENER_PLACEHOLDER = "<?php /*\x2 ORDERMETHOD \x3*/";
	const METHOD_REPLACEMENT_PLACEHOLDER = "\x2 METHODPLACEHOLDER \x3";

	public function orderMethods($source) {
		$tokens = token_get_all($source);
		$return = '';
		$function_list = [];
		while (list($index, $token) = each($tokens)) {
			list($id, $text) = $this->get_token($token);
			$this->ptr = $index;
			switch ($id) {
				case T_PRIVATE:
				case T_PROTECTED:
				case T_PUBLIC:
					$stack = $text;
					$curly_count = null;
					$touched_method = false;
					$function_name = '';
					while (list($index, $token) = each($tokens)) {
						list($id, $text) = $this->get_token($token);
						$this->ptr = $index;

						$stack .= $text;
						if ($id == T_FUNCTION) {
							$touched_method = true;
						}
						if ($id == T_VARIABLE && !$touched_method) {
							break;
						}
						if ($id == T_STRING && $touched_method && empty($function_name)) {
							$function_name = $text;
						}

						if ($id == ST_CURLY_OPEN) {
							++$curly_count;
						}
						if ($id == ST_CURLY_CLOSE) {
							--$curly_count;
						}
						if (0 === $curly_count) {
							break;
						}
					}
					if (!$touched_method) {
						$return .= $stack;
					} else {
						$function_list[$function_name] = $stack;
						$return .= self::METHOD_REPLACEMENT_PLACEHOLDER;
					}
					break;
				default:
					$return .= $text;
					break;
			}
		}
		ksort($function_list);
		foreach ($function_list as $function_body) {
			$return = preg_replace('/' . self::METHOD_REPLACEMENT_PLACEHOLDER . '/', $function_body, $return, 1);
		}
		return $return;
	}
	public function format($source) {
		$this->tkns = token_get_all($source);
		$return = '';
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->get_token($token);
			$this->ptr = $index;
			switch ($id) {
				case T_CLASS:
					$return .= $text;
					while (list($index, $token) = each($this->tkns)) {
						list($id, $text) = $this->get_token($token);
						$this->ptr = $index;
						$return .= $text;
						if ($id == ST_CURLY_OPEN) {
							break;
						}
					}
					$class_block = '';
					$curly_count = 1;
					while (list($index, $token) = each($this->tkns)) {
						list($id, $text) = $this->get_token($token);
						$this->ptr = $index;
						$class_block .= $text;
						if ($id == ST_CURLY_OPEN) {
							++$curly_count;
						} elseif ($id == ST_CURLY_CLOSE) {
							--$curly_count;
						}

						if (0 == $curly_count) {
							break;
						}
					}
					$return .= str_replace(
						self::OPENER_PLACEHOLDER,
						'',
						$this->orderMethods(self::OPENER_PLACEHOLDER . $class_block)
					);
					$this->append_code($return, false);
					break;
				default:
					$this->append_code($text, false);
					break;
			}
		}
		return $this->code;
	}
}
;
final class OrderUseClauses extends FormatterPass {
	const OPENER_PLACEHOLDER = "<?php /*\x2 ORDERBY \x3*/";
	private function singleNamespace($source) {
		$tokens = token_get_all($source);
		$use_stack = [];
		$new_tokens = [];
		$next_tokens = [];
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
							$use_item .= ST_SEMI_COLON;
							$next_tokens[] = [T_WHITESPACE, $this->new_line, ];
							$next_tokens[] = [T_USE, 'use', ];
							break;
						} else {
							$use_item .= $text;
						}
					}
					$use_stack[] = trim($use_item);
					$token = new SurrogateToken();
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
		$alias_list = [];
		$alias_count = [];
		foreach ($use_stack as $use) {
			if (false !== stripos($use, ' as ')) {
				$alias = substr(strstr($use, ' as '), strlen(' as '), -1);
			} else {
				$alias = basename(str_replace('\\', '/', trim(substr($use, strlen('use'), -1))));
			}
			$alias = str_replace(ST_SEMI_COLON, '', strtolower($alias));
			$alias_list[$alias] = trim(strtolower($use));
			$alias_count[$alias] = 0;
		}

		$return = '';
		foreach ($new_tokens as $idx => $token) {
			if ($token instanceof SurrogateToken) {
				$return .= array_shift($use_stack);
			} elseif (T_WHITESPACE == $token[0] && $new_tokens[$idx - 1] instanceof SurrogateToken && $new_tokens[$idx + 1] instanceof SurrogateToken) {
				$return .= $this->new_line;
				continue;
			} else {
				list($id, $text) = $this->get_token($token);
				$lower_text = strtolower($text);
				if (T_STRING === $id && isset($alias_list[$lower_text])) {
					++$alias_count[$lower_text];
				} elseif (T_DOC_COMMENT === $id) {
					foreach ($alias_list as $alias => $use) {
						if (false !== stripos($text, $alias)) {
							++$alias_count[$alias];
						}
					}
				}
				$return .= $text;
			}
		}

		while (list(, $token) = each($tokens)) {
			list($id, $text) = $this->get_token($token);
			$lower_text = strtolower($text);
			if (T_STRING === $id && isset($alias_list[$lower_text])) {
				++$alias_count[$lower_text];
			} elseif (T_DOC_COMMENT === $id) {
				foreach ($alias_list as $alias => $use) {
					if (false !== stripos($text, $alias)) {
						++$alias_count[$alias];
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
		$tokens = token_get_all($source);
		$touched_t_use = false;
		while (list(, $token) = each($tokens)) {
			list($id, $text) = $this->get_token($token);
			if (T_USE === $id) {
				$touched_t_use = true;
			}
			if (T_NAMESPACE == $id) {
				++$namespace_count;
			}
		}
		if ($namespace_count <= 1 && $touched_t_use) {
			return $this->singleNamespace($source);
		} elseif ($namespace_count <= 1) {
			return $source;
		}

		$return = '';
		reset($tokens);
		while (list($index, $token) = each($tokens)) {
			list($id, $text) = $this->get_token($token);
			$this->ptr = $index;
			switch ($id) {
				case T_NAMESPACE:
					$return .= $text;
					$touched_t_use = false;
					while (list($index, $token) = each($tokens)) {
						list($id, $text) = $this->get_token($token);
						$this->ptr = $index;
						$return .= $text;
						if (ST_CURLY_OPEN == $id || ST_SEMI_COLON == $id) {
							break;
						}
					}
					if (ST_CURLY_OPEN === $id) {
						$namespace_block = '';
						$curly_count = 1;
						while (list($index, $token) = each($tokens)) {
							list($id, $text) = $this->get_token($token);
							$this->ptr = $index;
							$namespace_block .= $text;

							if (T_USE === $id) {
								$touched_t_use = true;
							}

							if (ST_CURLY_OPEN == $id) {
								++$curly_count;
							} elseif (ST_CURLY_CLOSE == $id) {
								--$curly_count;
							}

							if (0 == $curly_count) {
								break;
							}
						}
					} elseif (ST_SEMI_COLON === $id) {
						$namespace_block = '';
						while (list($index, $token) = each($tokens)) {
							list($id, $text) = $this->get_token($token);
							$this->ptr = $index;

							if (T_USE === $id) {
								$touched_t_use = true;
							}

							if (T_NAMESPACE == $id) {
								prev($tokens);
								break;
							}

							$namespace_block .= $text;
						}
					}
					if ($touched_t_use) {
						$return .= str_replace(
							self::OPENER_PLACEHOLDER,
							'',
							$this->singleNamespace(self::OPENER_PLACEHOLDER . $namespace_block)
						);
					} else {
						$return .= $namespace_block;
					}
					break;
				default:
					$return .= $text;
			}
		}

		return $return;
	}
}
;
final class Reindent extends FormatterPass {
	public function format($source) {
		$this->tkns = token_get_all($source);
		$this->code = '';
		$this->use_cache = true;
		$found_stack = [];
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->get_token($token);
			$this->ptr = $index;
			$this->cache = [];

			if (
				(
					T_WHITESPACE === $id ||
					(T_COMMENT === $id && '//' == substr($text, 0, 2))
				) && $this->has_ln($text)
			) {
				$bottom_found_stack = end($found_stack);
				if (isset($bottom_found_stack['implicit']) && $bottom_found_stack['implicit']) {
					$idx = sizeof($found_stack) - 1;
					$found_stack[$idx]['implicit'] = false;
					$this->set_indent(+1);
				}
			}
			switch ($id) {
				case ST_QUOTE:
					$this->append_code($text, false);
					$this->print_until_the_end_of_string();
					break;
				case T_CLOSE_TAG:
					$this->append_code($text, false);
					$this->print_until(T_OPEN_TAG);
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
						'id' => $id,
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
					$has_ln = ($this->has_ln($text));
					if ($has_ln) {
						$is_next_curly_paren_bracket_close = $this->is_token([ST_CURLY_CLOSE, ST_PARENTHESES_CLOSE, ST_BRACKET_CLOSE]);
						if (!$is_next_curly_paren_bracket_close) {
							$text = str_replace($this->new_line, $this->new_line . $this->get_indent(), $text);
						} elseif ($is_next_curly_paren_bracket_close) {
							$this->set_indent(-1);
							$text = str_replace($this->new_line, $this->new_line . $this->get_indent(), $text);
							$this->set_indent(+1);
						}
					}
					$this->append_code($text, false);
					break;
			}
		}
		return $this->code;
	}

}
;
final class ReindentColonBlocks extends FormatterPass {
	public function format($source) {
		$this->tkns = token_get_all($source);
		$found_colon = false;
		foreach ($this->tkns as $token) {
			list($id, $text) = $this->get_token($token);
			if (T_DEFAULT == $id || T_CASE == $id || T_SWITCH == $id) {
				$found_colon = true;
				break;
			}
		}
		if (!$found_colon) {
			return $source;
		}
		reset($this->tkns);
		$this->code = '';

		$switch_level = 0;
		$switch_curly_count = [];
		$switch_curly_count[$switch_level] = 0;
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->get_token($token);
			$this->ptr = $index;
			switch ($id) {
				case ST_QUOTE:
					$this->append_code($text, false);
					$this->print_until_the_end_of_string();
					break;
				case T_SWITCH:
					++$switch_level;
					$switch_curly_count[$switch_level] = 0;
					$this->append_code($text, false);
					break;
				case ST_CURLY_OPEN:
					$this->append_code($text, false);
					if ($this->is_token([T_VARIABLE], true)) {
						$this->print_until(ST_CURLY_CLOSE);
						break;
					}
					++$switch_curly_count[$switch_level];
					break;
				case ST_CURLY_CLOSE:
					--$switch_curly_count[$switch_level];
					if (0 === $switch_curly_count[$switch_level] && $switch_level > 0) {
						--$switch_level;
					}
					$this->append_code($this->get_indent($switch_level) . $text, false);
					break;
				case T_DEFAULT:
				case T_CASE:
					$this->append_code($text, false);
					break;
				default:
					$has_ln = $this->has_ln($text);
					if ($has_ln) {
						$is_next_case_or_default = $this->is_token([T_CASE, T_DEFAULT]);
						if (!$is_next_case_or_default && !$this->is_token(ST_CURLY_CLOSE)) {
							$this->append_code($text . $this->get_indent($switch_level), false);
						} else {
							$this->append_code($text, false);
						}
					} else {
						$this->append_code($text, false);
					}
					break;
			}
		}
		return $this->code;
	}
};
final class ReindentIfColonBlocks extends FormatterPass {
	public function format($source) {
		$this->tkns = token_get_all($source);
		$found_colon = false;
		foreach ($this->tkns as $token) {
			list($id, $text) = $this->get_token($token);
			if (ST_COLON == trim($text)) {
				$found_colon = true;
				break;
			}
		}
		if (!$found_colon) {
			return $source;
		}
		reset($this->tkns);
		$this->code = '';

		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->get_token($token);
			$this->ptr = $index;
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
						$this->ptr = $index;
						$this->append_code($text, false);
						if (ST_PARENTHESES_OPEN === $id) {
							$paren_count = 1;
							while (list($index, $token) = each($this->tkns)) {
								list($id, $text) = $this->get_token($token);
								$this->ptr = $index;
								$this->append_code($text, false);
								if (ST_PARENTHESES_OPEN === $id) {
									++$paren_count;
								}
								if (ST_PARENTHESES_CLOSE === $id) {
									--$paren_count;
								}
								if (0 == $paren_count) {
									break;
								}
							}
						} elseif (ST_CURLY_OPEN === $id) {
							break;
						} elseif (ST_COLON === $id && !$this->is_token([T_CLOSE_TAG])) {
							$this->set_indent(+1);
							break;
						} elseif (ST_COLON === $id) {
							break;
						}
					}
					break;
				default:
					$has_ln = $this->has_ln($text);
					if ($has_ln && !$this->is_token([T_ENDIF, T_ELSE, T_ELSEIF])) {
						$text = str_replace($this->new_line, $this->new_line . $this->get_indent(), $text);
					} elseif ($has_ln && $this->is_token([T_ENDIF, T_ELSE, T_ELSEIF])) {
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
};
final class ReindentLoopColonBlocks extends FormatterPass {
	public function format($source) {
		$tkns = token_get_all($source);
		$found_endwhile = false;
		$found_endforeach = false;
		$found_endfor = false;
		foreach ($tkns as $token) {
			list($id, $text) = $this->get_token($token);
			if (!$found_endwhile && T_ENDWHILE == $id) {
				$source = $this->format_while_blocks($source);
				$found_endwhile = true;
			} elseif (!$found_endforeach && T_ENDFOREACH == $id) {
				$source = $this->format_foreach_blocks($source);
				$found_endforeach = true;
			} elseif (!$found_endfor && T_ENDFOR == $id) {
				$source = $this->format_for_blocks($source);
				$found_endfor = true;
			} elseif ($found_endwhile && $found_endforeach && $found_endfor) {
				break;
			}
		}
		return $source;
	}

	private function format_blocks($source, $open_token, $close_token) {
		$this->tkns = token_get_all($source);
		$this->code = '';

		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->get_token($token);
			$this->ptr = $index;
			switch ($id) {
				case $close_token:
					$this->set_indent(-1);
					$this->append_code($text, false);
					break;
				case $open_token:
					$this->append_code($text, false);
					while (list($index, $token) = each($this->tkns)) {
						list($id, $text) = $this->get_token($token);
						$this->ptr = $index;
						$this->append_code($text, false);
						if (ST_CURLY_OPEN === $id) {
							break;
						} elseif (ST_COLON === $id && !$this->is_token([T_CLOSE_TAG])) {
							$this->set_indent(+1);
							break;
						} elseif (ST_COLON === $id) {
							break;
						}
					}
					break;
				default:
					if ($this->has_ln($text) && !$this->is_token([$close_token])) {
						$text = str_replace($this->new_line, $this->new_line . $this->get_indent(), $text);
					} elseif ($this->has_ln($text) && $this->is_token([$close_token])) {
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
			$this->ptr = $index;
			switch ($id) {
				case T_ENDWHILE:
					$this->set_indent(-1);
					$this->append_code($text, false);
					break;
				case T_WHILE:
					$this->append_code($text, false);
					while (list($index, $token) = each($this->tkns)) {
						list($id, $text) = $this->get_token($token);
						$this->ptr = $index;
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
					if ($this->has_ln($text) && !$this->is_token([T_ENDWHILE])) {
						$text = str_replace($this->new_line, $this->new_line . $this->get_indent(), $text);
					} elseif ($this->has_ln($text) && $this->is_token([T_ENDWHILE])) {
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
};
final class ReindentObjOps extends FormatterPass {
	const ALIGNABLE_OBJOP = "\x2 OBJOP%d \x3";
	public function format($source) {
		$this->tkns = token_get_all($source);
		$this->code = '';
		$in_objop_context = 0;// 1 - indent, 2 - don't indent, so future auto-align takes place
		$alignable_objop_counter = 0;
		$printed_placeholder = false;
		$paren_count = 0;
		$paren_stack = [];
		$bracket_count = 0;
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->get_token($token);
			$this->ptr = $index;
			switch ($id) {
				case T_WHILE:
				case T_IF:
				case T_FOR:
				case T_FOREACH:
					$this->append_code($text, false);
					$this->print_until(ST_PARENTHESES_OPEN);
					$this->print_block(ST_PARENTHESES_OPEN, ST_PARENTHESES_CLOSE);
					break;
				case ST_PARENTHESES_OPEN:
					if ($this->is_token([T_ARRAY], true)) {
						$paren_stack[] = T_ARRAY;
					} else {
						$paren_stack[] = 0;
						++$paren_count;
					}
					$this->append_code($text, false);
					break;
				case ST_PARENTHESES_CLOSE:
					$stack_pop = array_pop($paren_stack);
					if (T_ARRAY != $stack_pop) {
						--$paren_count;
					}
					$this->append_code($text, false);
					break;
				case ST_BRACKET_OPEN:
					++$bracket_count;
					$this->append_code($text, false);
					break;
				case ST_BRACKET_CLOSE:
					--$bracket_count;
					$this->append_code($text, false);
					break;
				case T_OBJECT_OPERATOR:
					$has_ln_before = ($this->has_ln_before() || $this->has_ln_prev_token());
					if (0 === $in_objop_context && $has_ln_before) {
						$in_objop_context = 1;
						$this->set_indent(+1);
					} elseif (0 === $in_objop_context && !$has_ln_before) {
						++$alignable_objop_counter;
						$in_objop_context = 2;
					} elseif ($paren_count > 0) {
						$this->set_indent(-1);
						$in_objop_context = 0;
					}
					if (1 === $in_objop_context) {
						$this->append_code($this->get_indent() . $text, false);
					} elseif (2 === $in_objop_context) {
						$placeholder = '';
						if (!$printed_placeholder) {
							$placeholder = sprintf(self::ALIGNABLE_OBJOP, $alignable_objop_counter);
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
				case T_COMMENT:
				case T_DOC_COMMENT:
					$this->append_code($this->get_indent() . $text, false);
					break;
				default:
					$this->append_code($text, false);
					break;
			}
			if ($this->has_ln($text)) {
				$printed_placeholder = false;
			}
		}

		for ($j = $alignable_objop_counter; $j > 0; --$j) {
			$current_align_objop = sprintf(self::ALIGNABLE_OBJOP, $j);
			if (false === strpos($this->code, $current_align_objop)) {
				continue;
			}
			if (1 === substr_count($this->code, $current_align_objop)) {
				$this->code = str_replace($current_align_objop, '', $this->code);
				continue;
			}

			$lines = explode($this->new_line, $this->code);
			$lines_with_objop = [];
			$block_count = 0;

			foreach ($lines as $idx => $line) {
				if (substr_count($line, $current_align_objop) > 0) {
					$lines_with_objop[$block_count][] = $idx;
				} else {
					++$block_count;
					$lines_with_objop[$block_count] = [];
				}
			}

			$i = 0;
			foreach ($lines_with_objop as $group) {
				if (1 === sizeof($group)) {
					continue;
				}
				++$i;
				$farthest_objop = 0;
				foreach ($group as $idx) {
					$farthest_objop = max($farthest_objop, strpos($lines[$idx], $current_align_objop));
				}
				foreach ($group as $idx) {
					$line = $lines[$idx];
					$current_objop = strpos($line, $current_align_objop);
					$delta = abs($farthest_objop - $current_objop);
					if ($delta > 0) {
						$line = str_replace($current_align_objop, str_repeat(' ', $delta) . $current_align_objop, $line);
						$lines[$idx] = $line;
					}
				}
			}

			$this->code = str_replace($current_align_objop, '', implode($this->new_line, $lines));
		}

		return $this->code;
	}
}
;
class RemoveUseLeadingSlash extends FormatterPass {
	public function format($source) {
		$this->tkns = token_get_all($source);
		$this->code = '';
		$last_touched_token = null;
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->get_token($token);
			$this->ptr = $index;
			switch ($id) {
				case T_NAMESPACE:
				case T_TRAIT:
				case T_CLASS:
				case T_FUNCTION:
					$last_touched_token = $id;
				case T_NS_SEPARATOR:
					if (T_NAMESPACE == $last_touched_token && $this->is_token([T_USE], true)) {
						continue;
					}
				default:
					$this->append_code($text, false);
			}
		}

		return $this->code;
	}
}
;
final class RemoveIncludeParentheses extends FormatterPass {
	public function format($source) {
		$this->tkns = token_get_all($source);
		$this->code = '';
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->get_token($token);
			$this->ptr = $index;
			switch ($id) {
				case T_INCLUDE:
				case T_REQUIRE:
				case T_INCLUDE_ONCE:
				case T_REQUIRE_ONCE:
					$this->append_code($text . $this->get_space(), false);

					if (!$this->is_token(ST_PARENTHESES_OPEN)) {
						break;
					}
					$this->walk_until(ST_PARENTHESES_OPEN);
					$count = 1;
					while (list($index, $token) = each($this->tkns)) {
						list($id, $text) = $this->get_token($token);
						$this->ptr = $index;
						$this->cache = [];

						if (ST_PARENTHESES_OPEN == $id) {
							++$count;
						}
						if (ST_PARENTHESES_CLOSE == $id) {
							--$count;
						}
						if (0 == $count) {
							break;
						}
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
;
final class ResizeSpaces extends FormatterPass {
	private function filterWhitespaces($source) {
		$tkns = token_get_all($source);

		foreach ($tkns as $idx => &$token) {
			if (T_WHITESPACE === $token[0] && !$this->has_ln($token[1])) {
				unset($tkns[$idx]);
			}
		}

		return array_values($tkns);
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
				case '+':
				case '-':
					list($prev_id, $prev_text) = $this->inspect_token(-1);
					list($next_id, $next_text) = $this->inspect_token(+1);
					if (
						(T_LNUMBER === $prev_id || T_DNUMBER === $prev_id || T_VARIABLE === $prev_id || ST_PARENTHESES_CLOSE === $prev_id || T_STRING === $prev_id)
					 	&&
						(T_LNUMBER === $next_id || T_DNUMBER === $next_id || T_VARIABLE === $next_id || ST_PARENTHESES_CLOSE === $next_id || T_STRING === $next_id)
					) {
						$this->append_code($this->get_space() . $text . $this->get_space(), false);
					} else {
						$this->append_code($text, false);
					}
					break;
				case '*':
					list($prev_id, $prev_text) = $this->inspect_token(-1);
					list($next_id, $next_text) = $this->inspect_token(+1);
					if (
						T_WHITESPACE === $prev_id &&
						T_WHITESPACE !== $next_id
					) {
						$this->append_code($text . $this->get_space(), false);
					} elseif (
						T_WHITESPACE !== $prev_id &&
						T_WHITESPACE === $next_id
					) {
						$this->append_code($this->get_space() . $text, false);
					} elseif (
						T_WHITESPACE !== $prev_id &&
						T_WHITESPACE !== $next_id
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
						T_WHITESPACE === $prev_id &&
						T_WHITESPACE !== $next_id
					) {
						$this->append_code($text . $this->get_space(!$this->is_token(ST_COLON)), false);
						break;
					} elseif (
						T_WHITESPACE !== $prev_id &&
						T_WHITESPACE === $next_id
					) {
						$this->append_code($this->get_space() . $text, false);
						break;
					} elseif (
						T_WHITESPACE !== $prev_id &&
						T_WHITESPACE !== $next_id
					) {
						$this->append_code($this->get_space() . $text . $this->get_space(!$this->is_token(ST_COLON)), false);
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
						$this->append_code($text . $this->get_space(), false);
						$in_ternary_operator = false;
					} elseif (
						$in_ternary_operator &&
						T_WHITESPACE !== $prev_id &&
						T_WHITESPACE === $next_id
					) {
						$this->append_code($this->get_space(!$short_ternary_operator) . $text, false);
						$in_ternary_operator = false;
					} elseif (
						$in_ternary_operator &&
						T_WHITESPACE !== $prev_id &&
						T_WHITESPACE !== $next_id
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
					if ($this->is_token([T_STRING, T_DO, T_FINALLY, ST_PARENTHESES_CLOSE], true)) {
						$this->append_code($this->get_space() . $text, !$this->has_ln_prev_token());
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
					if ($this->is_token([T_WHILE, T_CATCH], true)) {
						$this->append_code($this->get_space() . $text, !$this->has_ln_prev_token());
					} else {
						$this->append_code($text, false);
					}
					break;
				case ST_PARENTHESES_CLOSE:
					$this->append_code($text, false);
					break;
				case T_USE:
					if ($this->is_token(ST_PARENTHESES_CLOSE, true)) {
						$this->append_code($this->get_space() . $text . $this->get_space(), false);
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
					if ($this->is_token(ST_CURLY_CLOSE, true) && !$this->has_ln_before()) {
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
					$this->append_code($text . $this->get_space(!$this->is_token(ST_SEMI_COLON)), false);
					break;
				case T_CLASS:
					$this->append_code($text . $this->get_space(!$this->is_token(ST_SEMI_COLON) && !$this->is_token([T_DOUBLE_COLON], true)), false);
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
					$this->append_code($this->get_space() . $text . $this->get_space(), false);
					break;
				case T_CATCH:
				case T_FINALLY:
					$this->append_code($this->get_space() . $text . $this->get_space(), !$this->has_ln_prev_token());
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
					$this->append_code(str_replace([' ', "\t"], '', $text) . $this->get_space(), false);
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
;
class ReturnNull extends FormatterPass {
	public function format($source) {
		$this->tkns = token_get_all($source);
		$this->code = '';
		$this->use_cache = true;
		$touched_return = false;
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->get_token($token);
			$this->ptr = $index;
			$this->cache = [];

			if (ST_PARENTHESES_OPEN == $id && $this->is_token([T_RETURN], true)) {
				$paren_count = 1;
				$touched_another_valid_token = false;
				$stack = $text;
				while (list($index, $token) = each($this->tkns)) {
					list($id, $text) = $this->get_token($token);
					$this->ptr = $index;
					$this->cache = [];
					if (ST_PARENTHESES_OPEN == $id) {
						++$paren_count;
					}
					if (ST_PARENTHESES_CLOSE == $id) {
						--$paren_count;
					}
					$stack .= $text;
					if (0 == $paren_count) {
						break;
					}
					if (
						!(
							(T_STRING == $id && strtolower($text) == 'null') ||
							ST_PARENTHESES_OPEN == $id ||
							ST_PARENTHESES_CLOSE == $id
						)
					) {
						$touched_another_valid_token = true;
					}
				}
				if ($touched_another_valid_token) {
					$this->append_code($stack, false);
				}
				continue;
			}
			if (T_STRING == $id && strtolower($text) == 'null') {
				list($prev_id, ) = $this->prev_token([T_WHITESPACE, T_COMMENT, T_DOC_COMMENT]);
				list($next_id, ) = $this->next_token([T_WHITESPACE, T_COMMENT, T_DOC_COMMENT]);
				if (T_RETURN == $prev_id && ST_SEMI_COLON == $next_id) {
					continue;
				}
			}

			$this->append_code($text, false);
		}

		return $this->code;
	}
}
;
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
};
final class SettersAndGettersPass extends FormatterPass {
	const TYPE_CAMEL_CASE = 'camel';
	const TYPE_SNAKE_CASE = 'snake';
	const TYPE_GOLANG = 'golang';
	public function __construct($type = self::TYPE_CAMEL_CASE) {
		if (self::TYPE_CAMEL_CASE == $type || self::TYPE_SNAKE_CASE == $type || self::TYPE_GOLANG == $type) {
			$this->type = $type;
		} else {
			$this->type = self::TYPE_CAMEL_CASE;
		}
	}

	public function format($source) {
		$this->tkns = token_get_all($source);
		$this->code = '';

		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->get_token($token);
			$this->ptr = $index;
			switch ($id) {
				case T_CLASS:
					$attributes = [
						'private' => [],
						'public' => [],
						'protected' => [],
					];
					$function_list = [];
					$touched_visibility = false;
					$touched_function = false;
					$curly_count = null;
					$this->append_code($text, false);
					while (list($index, $token) = each($this->tkns)) {
						list($id, $text) = $this->get_token($token);
						$this->ptr = $index;
						if (ST_CURLY_OPEN == $id) {
							++$curly_count;
						}
						if (ST_CURLY_CLOSE == $id) {
							--$curly_count;
						}
						if (0 === $curly_count) {
							break;
						}
						$this->append_code($text, false);
						if (T_PUBLIC == $id) {
							$touched_visibility = T_PUBLIC;
						} elseif (T_PRIVATE == $id) {
							$touched_visibility = T_PRIVATE;
						} elseif (T_PROTECTED == $id) {
							$touched_visibility = T_PROTECTED;
						}
						if (T_VARIABLE == $id && T_PUBLIC == $touched_visibility) {
							$attributes['public'][] = $text;
							$touched_visibility = null;
						} elseif (T_VARIABLE == $id && T_PRIVATE == $touched_visibility) {
							$attributes['private'][] = $text;
							$touched_visibility = null;
						} elseif (T_VARIABLE == $id && T_PROTECTED == $touched_visibility) {
							$attributes['protected'][] = $text;
							$touched_visibility = null;
						} elseif (T_FUNCTION == $id) {
							$touched_function = true;
						} elseif ($touched_function && T_STRING == $id) {
							$function_list[] = $text;
							$touched_visibility = null;
							$touched_function = false;
						}
					}
					$function_list = array_combine($function_list, $function_list);
					foreach ($attributes as $visibility => $variables) {
						foreach ($variables as $var) {
							$str = $this->generate($visibility, $var);
							foreach ($function_list as $k => $v) {
								if (false !== stripos($str, $v)) {
									unset($function_list[$k]);
									continue 2;
								}
							}
							$this->append_code($str, false);
						}
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

	private function generate($visibility, $var) {
		switch ($this->type) {
			case self::TYPE_SNAKE_CASE:
				$ret = $this->generateSnakeCase($visibility, $var);
				break;
			case self::TYPE_GOLANG:
				$ret = $this->generateGolang($visibility, $var);
				break;
			case self::TYPE_CAMEL_CASE:
			default:
				$ret = $this->generateCamelCase($visibility, $var);
				break;
		}
		return $ret;
	}
	private function generateCamelCase($visibility, $var) {
		$str = $visibility . ' function set' . ucfirst(str_replace('$', '', $var)) . '(' . $var . '){' . $this->new_line . '$this->' . str_replace('$', '', $var) . ' = ' . $var . ';' . $this->new_line . '}' . $this->new_line;
		$str .= $visibility . ' function get' . ucfirst(str_replace('$', '', $var)) . '(){' . $this->new_line . 'return $this->' . str_replace('$', '', $var) . ';' . $this->new_line . '}' . $this->new_line;
		return $str;
	}
	private function generateSnakeCase($visibility, $var) {
		$str = $visibility . ' function set_' . (str_replace('$', '', $var)) . '(' . $var . '){' . $this->new_line . '$this->' . str_replace('$', '', $var) . ' = ' . $var . ';' . $this->new_line . '}' . $this->new_line;
		$str .= $visibility . ' function get_' . (str_replace('$', '', $var)) . '(){' . $this->new_line . 'return $this->' . str_replace('$', '', $var) . ';' . $this->new_line . '}' . $this->new_line;
		return $str;
	}
	private function generateGolang($visibility, $var) {
		$str = $visibility . ' function Set' . ucfirst(str_replace('$', '', $var)) . '(' . $var . '){' . $this->new_line . '$this->' . str_replace('$', '', $var) . ' = ' . $var . ';' . $this->new_line . '}' . $this->new_line;
		$str .= $visibility . ' function ' . ucfirst(str_replace('$', '', $var)) . '(){' . $this->new_line . 'return $this->' . str_replace('$', '', $var) . ';' . $this->new_line . '}' . $this->new_line;
		return $str;
	}
};
/**
 * From PHP-CS-Fixer
 */
class ShortArray extends FormatterPass {
	const FOUND_ARRAY = 'array';
	const FOUND_PARENTHESES = 'paren';
	public function format($source) {
		$this->tkns = token_get_all($source);
		$this->code = '';
		$found_paren = [];

		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->get_token($token);
			$this->ptr = $index;
			switch ($id) {
				case T_ARRAY:
					if ($this->is_token([ST_PARENTHESES_OPEN])) {
						$found_paren[] = self::FOUND_ARRAY;
						$this->walk_until_paren_open();
						break;
					}
				case ST_PARENTHESES_OPEN:
					$found_paren[] = self::FOUND_PARENTHESES;
					$this->append_code($text, false);
					break;

				case ST_PARENTHESES_CLOSE:
					$pop_token = array_pop($found_paren);
					if (self::FOUND_ARRAY == $pop_token) {
						$this->append_code(']', false);
						break;
					}
				default:
					$this->append_code($text, false);
					break;
			}
		}

		return $this->code;
	}

	private function walk_until_paren_open() {
		do {

			list($index, $token) = each($this->tkns);
			list($id, $text) = $this->get_token($token);
			$this->ptr = $index;
			if (ST_PARENTHESES_OPEN == $id) {
				$this->append_code('[', false);
			}
		} while (ST_PARENTHESES_OPEN != $id);
	}
}
;
final class SmartLnAfterCurlyOpen extends FormatterPass {
	public function format($source) {
		$this->tkns = token_get_all($source);
		$this->code = '';
		$curly_count = 0;
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->get_token($token);
			$this->ptr = $index;
			switch ($id) {
				case ST_CURLY_OPEN:
					$this->append_code($text, false);
					$curly_count = 1;
					$stack = '';
					$found_line_break = false;
					$has_ln_after = $this->has_ln_after();
					while (list($index, $token) = each($this->tkns)) {
						list($id, $text) = $this->get_token($token);
						$this->ptr = $index;
						$stack .= $text;
						if (T_START_HEREDOC == $id) {
							$stack .= $this->walk_and_accumulate_until($this->tkns, T_END_HEREDOC);
							continue;
						}
						if (ST_QUOTE == $id) {
							$stack .= $this->walk_and_accumulate_until($this->tkns, ST_QUOTE);
							continue;
						}
						if (ST_CURLY_OPEN == $id) {
							++$curly_count;
						}
						if (ST_CURLY_CLOSE == $id) {
							--$curly_count;
						}
						if (T_WHITESPACE === $id && $this->has_ln($text)) {
							$found_line_break = true;
							break;
						}
						if (0 == $curly_count) {
							break;
						}
					}
					if ($found_line_break && !$has_ln_after) {
						$this->append_code($this->new_line, false);
					}
					$this->append_code($stack, false);
					break;
				default:
					$this->append_code($text, false);
					break;
			}
		}
		return $this->code;
	}
}
;
class SpaceBetweenMethods extends FormatterPass {
	public function format($source) {
		$this->tkns = token_get_all($source);
		$this->code = '';
		$last_touched_token = null;
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->get_token($token);
			$this->ptr = $index;
			switch ($id) {
				case T_FUNCTION:
					$this->append_code($text, false);
					$this->print_until(ST_CURLY_OPEN);
					$this->print_block(ST_CURLY_OPEN, ST_CURLY_CLOSE);
					if (!$this->is_token([ST_CURLY_CLOSE, ST_SEMI_COLON, ST_COMMA, ST_PARENTHESES_CLOSE])) {
						$this->append_code($this->get_crlf(), false);
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
;
final class SurrogateToken {
}
;
final class TwoCommandsInSameLine extends FormatterPass {
	public function format($source) {
		$this->tkns = token_get_all($source);
		$this->code = '';
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->get_token($token);
			$this->ptr = $index;

			switch ($id) {
				case ST_SEMI_COLON:
					if ($this->is_token(ST_SEMI_COLON, true)) {
						break;
					}
					$this->append_code($text, false);
					if (!$this->has_ln_after() && $this->is_token([T_VARIABLE, T_STRING])) {
						$this->append_code($this->new_line, false);
					}
					break;

				case ST_PARENTHESES_OPEN:
					$this->append_code($text, false);
					$this->print_block(ST_PARENTHESES_OPEN, ST_PARENTHESES_CLOSE);
					break;
				default:
					$this->append_code($text, false);
					break;

			}
		}
		return $this->code;
	}
}
;
final class YodaComparisons extends FormatterPass {
	const CHAIN_VARIABLE = 'CHAIN_VARIABLE';
	const CHAIN_LITERAL = 'CHAIN_LITERAL';
	const CHAIN_FUNC = 'CHAIN_FUNC';
	const CHAIN_STRING = 'CHAIN_STRING';
	const PARENTHESES_BLOCK = 'PARENTHESES_BLOCK';
	public function format($source) {
		return $this->yodise($source);
	}
	private function yodise($source) {
		$tkns = $this->aggregate_variables($source);
		reset($tkns);
		while (list($ptr, $token) = each($tkns)) {
			if (is_null($token)) {
				continue;
			}
			list($id, $text) = $this->get_token($token);
			switch ($id) {
				case T_IS_EQUAL:
				case T_IS_IDENTICAL:
				case T_IS_NOT_EQUAL:
				case T_IS_NOT_IDENTICAL:
					list($left, $right) = $this->siblings($tkns, $ptr);
					list($left_id, $left_text) = $tkns[$left];
					list($right_id, $right_text) = $tkns[$right];
					if ($left_id == $right_id) {
						continue;
					}

					$left_pure_variable = $this->is_pure_variable($left_id);
					for ($leftmost = $left; $leftmost >= 0; --$leftmost) {
						list($left_scan_id, $left_scan_text) = $this->get_token($tkns[$leftmost]);
						if ($this->is_lower_precedence($left_scan_id)) {
							++$leftmost;
							break;
						}
						$left_pure_variable &= $this->is_pure_variable($left_scan_id);
					}

					$right_pure_variable = $this->is_pure_variable($right_id);
					for ($rightmost = $right; $rightmost < sizeof($tkns) - 1; ++$rightmost) {
						list($right_scan_id, $right_scan_text) = $this->get_token($tkns[$rightmost]);
						if ($this->is_lower_precedence($right_scan_id)) {
							--$rightmost;
							break;
						}
						$right_pure_variable &= $this->is_pure_variable($right_scan_id);
					}

					if ($left_pure_variable && !$right_pure_variable) {
						$orig_left_tokens = $left_tokens = implode('', array_map(function ($token) {
							return isset($token[1]) ? $token[1] : $token;
						}, array_slice($tkns, $leftmost, $left - $leftmost + 1)));
						$orig_right_tokens = $right_tokens = implode('', array_map(function ($token) {
							return isset($token[1]) ? $token[1] : $token;
						}, array_slice($tkns, $right, $rightmost - $right + 1)));

						$left_tokens = (substr($orig_right_tokens, 0, 1) == ' ' ? ' ' : '') . trim($left_tokens) . (substr($orig_right_tokens, -1, 1) == ' ' ? ' ' : '');
						$right_tokens = (substr($orig_left_tokens, 0, 1) == ' ' ? ' ' : '') . trim($right_tokens) . (substr($orig_left_tokens, -1, 1) == ' ' ? ' ' : '');

						$tkns[$leftmost] = ['REPLACED', $right_tokens];
						$tkns[$right] = ['REPLACED', $left_tokens];

						if ($leftmost != $left) {
							for ($i = $leftmost + 1; $i <= $left; ++$i) {
								$tkns[$i] = null;
							}
						}
						if ($rightmost != $right) {
							for ($i = $right + 1; $i <= $rightmost; ++$i) {
								$tkns[$i] = null;
							}
						}
					}
			}
		}
		return $this->render($tkns);
	}

	private function is_pure_variable($id) {
		return self::CHAIN_VARIABLE == $id || T_VARIABLE == $id || T_INC == $id || T_DEC == $id || ST_EXCLAMATION == $id || T_COMMENT == $id || T_DOC_COMMENT == $id || T_WHITESPACE == $id;
	}
	private function is_lower_precedence($id) {
		switch ($id) {
			case ST_REFERENCE:
			case ST_BITWISE_XOR:
			case ST_BITWISE_OR:
			case T_BOOLEAN_AND:
			case T_BOOLEAN_OR:
			case ST_QUESTION:
			case ST_COLON:
			case ST_EQUAL:
			case T_PLUS_EQUAL:
			case T_MINUS_EQUAL:
			case T_MUL_EQUAL:
			case T_POW_EQUAL:
			case T_DIV_EQUAL:
			case T_CONCAT_EQUAL:
			case T_MOD_EQUAL:
			case T_AND_EQUAL:
			case T_OR_EQUAL:
			case T_XOR_EQUAL:
			case T_SL_EQUAL:
			case T_SR_EQUAL:
			case T_DOUBLE_ARROW:
			case T_LOGICAL_AND:
			case T_LOGICAL_XOR:
			case T_LOGICAL_OR:
			case ST_COMMA:
			case ST_SEMI_COLON:
			case T_RETURN:
			case T_THROW:
			case T_GOTO:
			case T_CASE:
			case T_COMMENT:
			case T_DOC_COMMENT:
			case T_OPEN_TAG:
				return true;
		}
		return false;
	}

	private function aggregate_variables($source) {
		$tkns = token_get_all($source);
		reset($tkns);
		while (list($ptr, $token) = each($tkns)) {
			list($id, $text) = $this->get_token($token);

			if (ST_PARENTHESES_OPEN == $id) {
				$initial_ptr = $ptr;
				$tmp = $this->scan_and_replace($tkns, $ptr, ST_PARENTHESES_OPEN, ST_PARENTHESES_CLOSE);
				$tkns[$initial_ptr] = [self::PARENTHESES_BLOCK, $tmp];
				continue;
			}
			if (ST_QUOTE == $id) {
				$stack = $text;
				$initial_ptr = $ptr;
				while (list($ptr, $token) = each($tkns)) {
					list($id, $text) = $this->get_token($token);
					$stack .= $text;
					$tkns[$ptr] = null;
					if (ST_QUOTE == $id) {
						break;
					}
				}

				$tkns[$initial_ptr] = [self::CHAIN_STRING, $stack];
				continue;
			}

			if (T_STRING == $id || T_VARIABLE == $id || T_NS_SEPARATOR == $id) {
				$initial_index = $ptr;
				$stack = $text;
				$touched_variable = false;
				if (T_VARIABLE == $id) {
					$touched_variable = true;
				}
				if (!$this->is_token_in_subset(
					$tkns,
					$ptr,
					[T_STRING, T_VARIABLE, T_NS_SEPARATOR, T_OBJECT_OPERATOR, T_DOUBLE_COLON, ST_CURLY_OPEN, ST_PARENTHESES_OPEN, ST_BRACKET_OPEN]
				)) {
					continue;
				}
				while (list($ptr, $token) = each($tkns)) {
					list($id, $text) = $this->get_token($token);
					// if (ST_CURLY_CLOSE == $id || ST_BRACKET_CLOSE == $id || ST_PARENTHESES_CLOSE == $id || ST_SEMI_COLON == $id ) {
					// 	$token = prev($tkns);
					// 	$ptr = key($tkns);
					// 	list($id, $text) = $this->get_token($token);
					// 	break;
					// }
					$tkns[$ptr] = null;
					if (ST_CURLY_OPEN == $id) {
						$text = $this->scan_and_replace($tkns, $ptr, ST_CURLY_OPEN, ST_CURLY_CLOSE);
					} elseif (ST_BRACKET_OPEN == $id) {
						$text = $this->scan_and_replace($tkns, $ptr, ST_BRACKET_OPEN, ST_BRACKET_CLOSE);
					} elseif (ST_PARENTHESES_OPEN == $id) {
						$text = $this->scan_and_replace($tkns, $ptr, ST_PARENTHESES_OPEN, ST_PARENTHESES_CLOSE);
					}

					$stack .= $text;

					if (!$touched_variable && T_VARIABLE == $id) {
						$touched_variable = true;
					}

					if (
						!$this->is_token_in_subset(
							$tkns,
							$ptr,
							[T_STRING, T_VARIABLE, T_NS_SEPARATOR, T_OBJECT_OPERATOR, T_DOUBLE_COLON, ST_CURLY_OPEN, ST_PARENTHESES_OPEN, ST_BRACKET_OPEN]
						)
					) {
						break;
					}
				}
				if (substr(trim($stack), -1, 1) == ST_PARENTHESES_CLOSE) {
					$tkns[$initial_index] = [self::CHAIN_FUNC, $stack];
				} elseif ($touched_variable) {
					$tkns[$initial_index] = [self::CHAIN_VARIABLE, $stack];
				} else {
					$tkns[$initial_index] = [self::CHAIN_LITERAL, $stack];
				}
			}
		}
		$tkns = array_values(array_filter($tkns));
		return $tkns;
	}

	private function scan_and_replace(&$tkns, &$ptr, $start, $end) {
		$placeholder = '<?php' . ' /*\x2 PHPOPEN \x3*/';
		$tmp = $placeholder;
		$tkn_count = 1;
		while (list($ptr, $token) = each($tkns)) {
			list($id, $text) = $this->get_token($token);
			if ($start == $id) {
				++$tkn_count;
			}
			if ($end == $id) {
				--$tkn_count;
			}
			$tkns[$ptr] = null;
			if (0 == $tkn_count) {
				break;
			}
			$tmp .= $text;
		}
		return $start . str_replace($placeholder, '', $this->yodise($tmp)) . $end;
	}
};
//PSR standards
final class PSR1BOMMark extends FormatterPass {
	public function format($source) {
		$bom = "\xef\xbb\xbf";
		if ($bom === substr($source, 0, 3)) {
			return substr($source, 3);
		}
		return $source;
	}
}
;
final class PSR1ClassConstants extends FormatterPass {
	public function format($source) {
		$this->tkns = token_get_all($source);
		$this->code = '';
		$uc_const = false;
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->get_token($token);
			$this->ptr = $index;
			switch ($id) {
				case T_CONST:
					$uc_const = true;
					$this->append_code($text, false);
					break;
				case T_STRING:
					if ($uc_const) {
						$text = strtoupper($text);
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
};
final class PSR1ClassNames extends FormatterPass {
	public function format($source) {
		$this->tkns = token_get_all($source);
		$this->code = '';
		$found_class = false;
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->get_token($token);
			$this->ptr = $index;
			switch ($id) {
				case T_CLASS:
					$found_class = true;
					$this->append_code($text, false);
					break;
				case T_STRING:
					if ($found_class) {
						$count = 0;
						$tmp = ucwords(str_replace(['-', '_'], ' ', strtolower($text), $count));
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
;
final class PSR1MethodNames extends FormatterPass {
	public function format($source) {
		$this->tkns = token_get_all($source);
		$this->code = '';
		$found_method = false;
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->get_token($token);
			$this->ptr = $index;
			switch ($id) {
				case T_FUNCTION:
					$found_method = true;
					$this->append_code($text, false);
					break;
				case T_STRING:
					if ($found_method) {
						$count = 0;
						$tmp = ucwords(str_replace(['-', '_'], ' ', strtolower($text), $count));
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
;
final class PSR1OpenTags extends FormatterPass {
	public function format($source) {
		$this->tkns = token_get_all($source);
		$this->code = '';
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->get_token($token);
			$this->ptr = $index;
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
;
final class PSR2AlignObjOp extends FormatterPass {
	const ALIGNABLE_TOKEN = "\x2 OBJOP%d \x3";
	public function format($source) {
		$this->tkns = token_get_all($source);
		$this->code = '';

		$context_counter = 0;
		$context_meta_count = [];
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->get_token($token);
			$this->ptr = $index;
			switch ($id) {
				case ST_SEMI_COLON:
				case T_ARRAY:
				case T_DOUBLE_ARROW:
					++$context_counter;
					$this->append_code($text, false);
					break;

				case T_OBJECT_OPERATOR:
					if (!isset($context_meta_count[$context_counter])) {
						$context_meta_count[$context_counter] = 0;
					}
					if ($this->has_ln_before() || 0 == $context_meta_count[$context_counter]) {
						$this->append_code(sprintf(self::ALIGNABLE_TOKEN, $context_counter) . $text, false);
						++$context_meta_count[$context_counter];
						break;
					}
				default:
					$this->append_code($text, false);
					break;
			}
		}

		for ($j = 0; $j <= $context_counter; ++$j) {
			$placeholder = sprintf(self::ALIGNABLE_TOKEN, $j);
			if (false === strpos($this->code, $placeholder)) {
				continue;
			}
			if (1 === substr_count($this->code, $placeholder)) {
				$this->code = str_replace($placeholder, '', $this->code);
				continue;
			}

			$lines = explode($this->new_line, $this->code);
			$lines_with_objop = [];
			$block_count = 0;

			foreach ($lines as $idx => $line) {
				if (false !== strpos($line, $placeholder)) {
					$lines_with_objop[$block_count][] = $idx;
				} else {
					++$block_count;
					$lines_with_objop[$block_count] = [];
				}
			}

			foreach ($lines_with_objop as $group) {
				$first_line = reset($group);
				$position_at_first_line = strpos($lines[$first_line], $placeholder);

				foreach ($group as $idx) {
					if ($idx == $first_line) {
						continue;
					}
					$line = ltrim($lines[$idx]);
					$line = str_replace($placeholder, str_repeat(' ', $position_at_first_line) . $placeholder, $line);
					$lines[$idx] = $line;
				}
			}

			$this->code = str_replace($placeholder, '', implode($this->new_line, $lines));
		}
		return $this->code;
	}
}
;
final class PSR2CurlyOpenNextLine extends FormatterPass {
	public function format($source) {
		$this->indent_char = '    ';
		$this->tkns = token_get_all($source);
		$this->code = '';

		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->get_token($token);
			$this->ptr = $index;
			switch ($id) {
				case ST_QUOTE:
					$this->append_code($text, false);
					$this->print_until_the_end_of_string();
					break;
				case T_INTERFACE:
				case T_TRAIT:
				case T_CLASS:
					$this->append_code($text, false);
					while (list($index, $token) = each($this->tkns)) {
						list($id, $text) = $this->get_token($token);
						$this->ptr = $index;
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
						$touched_ln = false;
						while (list($index, $token) = each($this->tkns)) {
							list($id, $text) = $this->get_token($token);
							$this->ptr = $index;
							if (T_WHITESPACE == $id && $this->has_ln($text)) {
								$touched_ln = true;
							}
							if (ST_CURLY_OPEN === $id && !$touched_ln) {
								$this->append_code($this->get_crlf_indent(), false);
								prev($this->tkns);
								break;
							} elseif (ST_CURLY_OPEN === $id) {
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
};
final class PSR2IndentWithSpace extends FormatterPass {
	private $indent_spaces = '    ';

	public function format($source) {
		$this->tkns = token_get_all($source);
		$this->code = '';
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->get_token($token);
			$this->ptr = $index;
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
};
final class PSR2KeywordsLowerCase extends FormatterPass {
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
					$this->append_code(strtolower($text), false);
					break;
				default:
					$lc_text = strtolower($text);
					if (!$this->is_token([T_NS_SEPARATOR], true) && ('true' === $lc_text || 'false' === $lc_text || 'null' === $lc_text)) {
						$text = $lc_text;
					}
					$this->append_code($text, false);
					break;
			}
		}
		return $this->code;
	}
};
final class PSR2LnAfterNamespace extends FormatterPass {
	public function format($source) {
		$this->tkns = token_get_all($source);
		$this->code = '';

		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->get_token($token);
			$this->ptr = $index;
			switch ($id) {
				case T_NAMESPACE:
					$this->append_code($this->get_crlf($this->is_token(ST_CURLY_CLOSE, true)) . $text, false);
					while (list($index, $token) = each($this->tkns)) {
						list($id, $text) = $this->get_token($token);
						$this->ptr = $index;
						if (ST_SEMI_COLON === $id) {
							$this->append_code($text, false);
							list(, $text) = $this->inspect_token();
							if (1 === substr_count($text, $this->new_line)) {
								$this->append_code($this->new_line, false);
							}
							break;
						} elseif (ST_CURLY_OPEN === $id) {
							$this->append_code($text, false);
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
};
final class PSR2ModifierVisibilityStaticOrder extends FormatterPass {
	public function format($source) {
		$this->tkns = token_get_all($source);
		$this->code = '';

		$found = [];
		$visibility = null;
		$final_or_abstract = null;
		$static = null;
		$skip_whitespaces = false;
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->get_token($token);
			$this->ptr = $index;
			switch ($id) {
				case ST_QUOTE:
					$this->append_code($text, false);
					$this->print_until_the_end_of_string();
					break;
				case T_CLASS:
					$found[] = T_CLASS;
					$this->append_code($text, false);
					break;
				case T_INTERFACE:
					$found[] = T_INTERFACE;
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
					$visibility = $text;
					$skip_whitespaces = true;
					break;
				case T_FINAL:
				case T_ABSTRACT:
					if (!$this->is_token([T_CLASS])) {
						$final_or_abstract = $text;
						$skip_whitespaces = true;
					} else {
						$this->append_code($text, false);
					}
					break;
				case T_STATIC:
					if (!is_null($visibility)) {
						$static = $text;
						$skip_whitespaces = true;
					} elseif (!$this->is_token([T_VARIABLE, T_DOUBLE_COLON]) && !$this->is_token([T_NEW], true)) {
						$static = $text;
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
						null !== $final_or_abstract && $this->append_code($final_or_abstract . $this->get_space(), false);
						null !== $visibility && $this->append_code($visibility . $this->get_space(), false);
						null !== $static && $this->append_code($static . $this->get_space(), false);
						$final_or_abstract = null;
						$visibility = null;
						$static = null;
						$skip_whitespaces = false;
					}
					$this->append_code($text, false);
					break;
				case T_FUNCTION:
					$has_found_class_or_interface = isset($found[0]) && (T_CLASS === $found[0] || T_INTERFACE === $found[0]);
					if (isset($found[0]) && $has_found_class_or_interface && null !== $final_or_abstract) {
						$this->append_code($final_or_abstract . $this->get_space(), false);
					}
					if (isset($found[0]) && $has_found_class_or_interface && null !== $visibility) {
						$this->append_code($visibility . $this->get_space(), false);
					} elseif (
						isset($found[0]) && $has_found_class_or_interface &&
						!$this->is_token([T_DOUBLE_ARROW, T_RETURN], true) &&
						!$this->is_token(ST_EQUAL, true) &&
						!$this->is_token(ST_COMMA, true) &&
						!$this->is_token(ST_PARENTHESES_OPEN, true)
					) {
						$this->append_code('public' . $this->get_space(), false);
					}
					if (isset($found[0]) && $has_found_class_or_interface && null !== $static) {
						$this->append_code($static . $this->get_space(), false);
					}
					$this->append_code($text, false);
					$final_or_abstract = null;
					$visibility = null;
					$static = null;
					$skip_whitespaces = false;
					break;
				default:
					$this->append_code($text, false);
					break;
			}
		}
		return $this->code;
	}
};
final class PSR2SingleEmptyLineAndStripClosingTag extends FormatterPass {
	public function format($source) {
		$this->tkns = token_get_all($source);
		$this->code = '';

		$open_tag_count = 0;
		while (list($index, $token) = each($this->tkns)) {
			list($id, ) = $this->get_token($token);
			if (T_OPEN_TAG === $id) {
				++$open_tag_count;
				break;
			}
		}

		reset($this->tkns);
		if (1 === $open_tag_count) {
			while (list($index, $token) = each($this->tkns)) {
				list($id, $text) = $this->get_token($token);
				$this->ptr = $index;
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
				$this->ptr = $index;
				$this->append_code($text, false);
			}
		}
		$this->code = rtrim($this->code) . $this->get_crlf();

		return $this->code;
	}
}
;
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
};

final class CodeFormatter {
	private $passes = [];
	public function addPass(FormatterPass $pass) {
		array_unshift($this->passes, $pass);
	}

	public function formatCode($source = '') {
		$passes = array_map(
			function ($pass) {
				return clone $pass;
			},
			$this->passes
		);
		while (($pass = array_pop($passes))) {
			$source = $pass->format($source);
		}
		return $source;
	}
}
if (!isset($testEnv)) {
	$opts = getopt('ho:', ['yoda', 'smart_linebreak_after_curly', 'prepasses:', 'passes:', 'oracleDB::', 'help', 'setters_and_getters:', 'constructor:', 'psr', 'psr1', 'psr2', 'indent_with_space', 'enable_auto_align', 'visibility_order']);
	if (isset($opts['h']) || isset($opts['help'])) {
		echo 'Usage: ' . $argv[0] . ' [-ho] [--setters_and_getters=type] [--constructor=type] [--psr] [--psr1] [--psr2] [--indent_with_space] [--enable_auto_align] [--visibility_order] <target>', PHP_EOL;
		$options = [
			'--constructor=type' => 'analyse classes for attributes and generate constructor - camel, snake, golang',
			'--enable_auto_align' => 'disable auto align of ST_EQUAL and T_DOUBLE_ARROW',
			'--indent_with_space' => 'use spaces instead of tabs for indentation',
			'--prepasses=pass1,passN' => 'call specific compiler pass, before the rest of stack',
			'--passes=pass1,passN' => 'call specific compiler pass',
			'--psr' => 'activate PSR1 and PSR2 styles',
			'--psr1' => 'activate PSR1 style',
			'--psr2' => 'activate PSR2 style',
			'--setters_and_getters=type' => 'analyse classes for attributes and generate setters and getters - camel, snake, golang',
			'--smart_linebreak_after_curly' => 'convert multistatement blocks into multiline blocks',
			'--visibility_order' => 'fixes visibiliy order for method in classes. PSR-2 4.2',
			'--yoda' => 'yoda-style comparisons',
			'-h, --help' => 'this help message',
			'-o=file' => 'output the formatted code to "file"',
		];
		$maxLen = max(array_map(function ($v) {
			return strlen($v);
		}, array_keys($options)));
		foreach ($options as $k => $v) {
			echo '  ', str_pad($k, $maxLen), '  ', $v, PHP_EOL;
		}
		echo PHP_EOL, 'If <target> is blank, it reads from stdin', PHP_EOL;
		die();
	}

	$fmt = new CodeFormatter();
	if (isset($opts['prepasses'])) {
		$optPasses = array_map(function ($v) {
			return trim($v);
		}, explode(',', $opts['prepasses']));
		foreach ($optPasses as $optPass) {
			if (class_exists($optPass)) {
				$fmt->addPass(new $optPass());
			}
		}
		$argv = array_values(
			array_filter($argv,
				function ($v) {
					return substr($v, 0, strlen('--prepasses')) !== '--prepasses';
				}
			)
		);
	}
	$fmt->addPass(new TwoCommandsInSameLine());
	$fmt->addPass(new RemoveIncludeParentheses());
	$fmt->addPass(new NormalizeIsNotEquals());
	if (isset($opts['setters_and_getters'])) {
		$argv = array_values(
			array_filter($argv,
				function ($v) {
					return substr($v, 0, strlen('--setters_and_getters')) !== '--setters_and_getters';
				}
			)
		);
		$fmt->addPass(new SettersAndGettersPass($opts['setters_and_getters']));
	}
	if (isset($opts['constructor'])) {
		$argv = array_values(
			array_filter($argv,
				function ($v) {
					return substr($v, 0, strlen('--constructor')) !== '--constructor';
				}
			)
		);
		$fmt->addPass(new ConstructorPass($opts['constructor']));
	}
	if (isset($opts['oracleDB'])) {
		$argv = array_values(
			array_filter($argv,
				function ($v) {
					return substr($v, 0, strlen('--oracleDB')) !== '--oracleDB';
				}
			)
		);
		$fmt->addPass(new AutoImportPass($opts['oracleDB']));
	}

	$fmt->addPass(new OrderUseClauses());
	$fmt->addPass(new AddMissingCurlyBraces());
	if (isset($opts['smart_linebreak_after_curly'])) {
		$fmt->addPass(new SmartLnAfterCurlyOpen());
		$argv = array_values(
			array_filter($argv,
				function ($v) {
					return '--smart_linebreak_after_curly' !== $v;
				}
			)
		);
	}
	$fmt->addPass(new ExtraCommaInArray());
	$fmt->addPass(new NormalizeLnAndLtrimLines());
	$fmt->addPass(new MergeParenCloseWithCurlyOpen());
	$fmt->addPass(new MergeCurlyCloseAndDoWhile());
	$fmt->addPass(new MergeDoubleArrowAndArray());

	if (isset($opts['yoda'])) {
		$fmt->addPass(new YodaComparisons());
		$argv = array_values(
			array_filter($argv,
				function ($v) {
					return '--yoda' !== $v;
				}
			)
		);
	}

	$fmt->addPass(new ResizeSpaces());
	$fmt->addPass(new Reindent());
	$fmt->addPass(new ReindentColonBlocks());
	$fmt->addPass(new ReindentLoopColonBlocks());
	$fmt->addPass(new ReindentIfColonBlocks());

	if (isset($opts['enable_auto_align'])) {
		$fmt->addPass(new AlignEquals());
		$fmt->addPass(new AlignDoubleArrow());
		$argv = array_values(
			array_filter($argv,
				function ($v) {
					return '--enable_auto_align' !== $v;
				}
			)
		);
	}

	$fmt->addPass(new ReindentObjOps());
	$fmt->addPass(new EliminateDuplicatedEmptyLines());

	if (isset($opts['indent_with_space'])) {
		$fmt->addPass(new PSR2IndentWithSpace());
		$argv = array_values(
			array_filter($argv,
				function ($v) {
					return '--indent_with_space' !== $v;
				}
			)
		);
	}
	if (isset($opts['psr'])) {
		PsrDecorator::decorate($fmt);
		$argv = array_values(
			array_filter($argv,
				function ($v) {
					return '--psr' !== $v;
				}
			)
		);
	}
	if (isset($opts['psr1'])) {
		PsrDecorator::PSR1($fmt);
		$argv = array_values(
			array_filter($argv,
				function ($v) {
					return '--psr1' !== $v;
				}
			)
		);
	}
	if (isset($opts['psr2'])) {
		PsrDecorator::PSR2($fmt);
		$argv = array_values(
			array_filter($argv,
				function ($v) {
					return '--psr2' !== $v;
				}
			)
		);
	}
	if ((isset($opts['psr1']) || isset($opts['psr2']) || isset($opts['psr'])) && isset($opts['enable_auto_align'])) {
		$fmt->addPass(new PSR2AlignObjOp());
	}

	if (isset($opts['visibility_order'])) {
		$fmt->addPass(new PSR2ModifierVisibilityStaticOrder());
		$argv = array_values(
			array_filter($argv,
				function ($v) {
					return '--visibility_order' !== $v;
				}
			)
		);
	}
	$fmt->addPass(new LeftAlignComment());
	$fmt->addPass(new RTrim());

	if (isset($opts['passes'])) {
		$optPasses = array_map(function ($v) {
			return trim($v);
		}, explode(',', $opts['passes']));
		foreach ($optPasses as $optPass) {
			if (class_exists($optPass)) {
				$fmt->addPass(new $optPass());
			}
		}
		$argv = array_values(
			array_filter($argv,
				function ($v) {
					return substr($v, 0, strlen('--passes')) !== '--passes';
				}
			)
		);
	}

	if (isset($opts['o'])) {
		unset($argv[1]);
		unset($argv[2]);
		$argv = array_values($argv);
		file_put_contents($opts['o'], $fmt->formatCode(file_get_contents($argv[1])));
	} elseif (isset($argv[1]) && is_file($argv[1])) {
		echo $fmt->formatCode(file_get_contents($argv[1]));
	} elseif (isset($argv[1]) && is_dir($argv[1])) {

		$start = microtime(true);
		echo 'Formatting ', $argv[1], PHP_EOL;
		$dir = new RecursiveDirectoryIterator($argv[1]);
		$it = new RecursiveIteratorIterator($dir);
		$files = new RegexIterator($it, '/^.+\.php$/i', RecursiveRegexIterator::GET_MATCH);
		$fileCount = 0;
		foreach ($files as $file) {
			$file = $file[0];
			++$fileCount;
			echo '.';
			file_put_contents($file . '-tmp', $fmt->formatCode(file_get_contents($file)));
			rename($file, $file . '~');
			rename($file . '-tmp', $file);
			if (0 == ($fileCount % 20)) {
				echo ' ', $fileCount, PHP_EOL;
			}
		}
		echo ' ', $fileCount, ' files', PHP_EOL;
		echo 'Took ', ceil(microtime(true) - $start), ' seconds', PHP_EOL;
	} else {
		echo $fmt->formatCode(file_get_contents('php://stdin'));
	}
}
