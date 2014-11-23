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
		$this->print_until_the_end_of(ST_QUOTE);
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
	protected function print_until_the_end_of($tknid) {
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
final class RefactorPass extends FormatterPass {
	private $from;
	private $to;
	public function __construct($from, $to) {
		$this->setFrom($from);
		$this->setTo($to);
	}
	private function setFrom($from) {
		$tkns = token_get_all('<?php ' . $from);
		array_shift($tkns);
		$tkns = array_map(function ($v) {
			return $this->get_token($v);
		}, $tkns);
		$this->from = $tkns;
		return $this;
	}
	private function getFrom() {
		return $this->from;
	}
	private function setTo($to) {
		$tkns = token_get_all('<?php ' . $to);
		array_shift($tkns);
		$tkns = array_map(function ($v) {
			return $this->get_token($v);
		}, $tkns);
		$this->to = $tkns;
		return $this;
	}
	private function getTo() {
		return $this->to;
	}

	public function format($source) {
		$from = $this->getFrom();
		$from_size = sizeof($from);
		$from_str = implode('', array_map(function ($v) {
			return $v[1];
		}, $from));
		$to = $this->getTo();
		$to_str = implode('', array_map(function ($v) {
			return $v[1];
		}, $to));

		$this->tkns = token_get_all($source);
		$this->code = '';
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->get_token($token);
			$this->ptr = $index;

			if ($id == $from[0][0]) {
				$match = true;
				$buffer = $text;
				$i = 1;
				for ($i = 1; $i < $from_size; ++$i) {
					list($index, $token) = each($this->tkns);
					$this->ptr = $index;
					list($id, $text) = $this->get_token($token);
					$buffer .= $text;
					if ($id != $from[$i][0]) {
						$match = false;
						break;
					}
				}
				if ($match) {
					$buffer = str_replace($from_str, $to_str, $buffer);
				}
				$this->append_code($buffer, false);
			} else {
				$this->append_code($text, false);
			}
		}
		return $this->code;
	}
};

final class CodeFormatter {
	private $passes = [];
	private $debug = false;
	public function __construct($debug = false) {
		$this->debug = (bool) $debug;
	}
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
		while (($pass = array_shift($passes))) {
			$source = $pass->format($source);
			gc_collect_cycles();
		}
		gc_disable();
		return $source;
	}
}
if (!isset($testEnv)) {
	$opts = getopt('ho:', ['from:', 'to:', 'help']);
	if (isset($opts['h']) || isset($opts['help'])) {
		echo 'Usage: ' . $argv[0] . ' [-ho] [--from=from --to=to] <target>', PHP_EOL;
		$options = [
			'--from=from, --to=to' => 'Search for "from" and replace with "to" - context aware search and replace',
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
	if (isset($opts['from']) && !isset($opts['to'])) {
		fwrite(STDERR, "Refactor must have --from and --to parameters" . PHP_EOL);
		exit(255);
	}

	$debug = false;

	$fmt = new CodeFormatter($debug);

	if (isset($opts['from']) && isset($opts['to'])) {
		$argv = array_values(
			array_filter($argv,
				function ($v) {
					$param_from = '--from';
					$param_to = '--to';
					return substr($v, 0, strlen($param_from)) !== $param_from && substr($v, 0, strlen($param_to)) !== $param_to;
				}
			)
		);
		$fmt->addPass(new RefactorPass($opts['from'], $opts['to']));
	}

	if (isset($opts['o'])) {
		unset($argv[1]);
		unset($argv[2]);
		$argv = array_values($argv);
		file_put_contents($opts['o'], $fmt->formatCode(file_get_contents($argv[1])));
	} elseif (isset($argv[1]) && is_file($argv[1])) {
		echo $fmt->formatCode(file_get_contents($argv[1]));
	} elseif (isset($argv[1]) && is_dir($argv[1])) {
		$dir = new RecursiveDirectoryIterator($argv[1]);
		$it = new RecursiveIteratorIterator($dir);
		$files = new RegexIterator($it, '/^.+\.php$/i', RecursiveRegexIterator::GET_MATCH);
		foreach ($files as $file) {
			$file = $file[0];
			echo $file;
			file_put_contents($file . '-tmp', $fmt->formatCode(file_get_contents($file)));
			rename($file, $file . '~');
			rename($file . '-tmp', $file);
			echo PHP_EOL;
		}
	} else {
		echo $fmt->formatCode(file_get_contents('php://stdin'));
	}
}
