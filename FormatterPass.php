<?php
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
	protected function is_token($token, $prev = false) {
		if ($this->use_cache) {
			$key = ((int) $prev) . "\x2" . (is_array($token) ? implode("\x2", $token) : $token);
			if (isset($this->cache[$key])) {
				return $this->cache[$key];
			}
		}
		$ret = $this->is_token_idx($this->ptr, $token, $prev);
		if ($this->use_cache) {
			$this->cache[$key] = $ret;
		}
		return $ret;
	}
	protected function is_token_idx($idx, $token, $prev = false) {
		$i = $idx;
		if ($prev) {
			while (--$i >= 0 && is_array($this->tkns[$i]) && T_WHITESPACE === $this->tkns[$i][0]);
		} else {
			$tkns_size = sizeof($this->tkns) - 1;
			while (++$i < $tkns_size && is_array($this->tkns[$i]) && T_WHITESPACE === $this->tkns[$i][0]);
		}

		if (!isset($this->tkns[$i])) {
			return false;
		}

		$found_token = $this->tkns[$i];
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
	protected function is_token_in_subset($tkns, $idx, $token, $prev = false) {
		$i = $idx;
		if ($prev) {
			while (--$i >= 0 && is_array($tkns[$i]) && T_WHITESPACE === $tkns[$i][0]);
		} else {
			$tkns_size = sizeof($tkns) - 1;
			while (++$i < $tkns_size && is_array($tkns[$i]) && T_WHITESPACE === $tkns[$i][0]);
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

	protected function prev_token() {
		$i = $this->ptr;
		while (--$i >= 0 && is_array($this->tkns[$i]) && T_WHITESPACE === $this->tkns[$i][0]);
		return $this->tkns[$i];
	}
	protected function siblings($tkns, $ptr) {
		$i = $ptr;
		while (--$i >= 0 && is_array($tkns[$i]) && T_WHITESPACE === $tkns[$i][0]);
		$left = $i;
		$i = $ptr;
		$tkns_size = sizeof($tkns) - 1;
		while (++$i < $tkns_size && is_array($tkns[$i]) && T_WHITESPACE === $tkns[$i][0]);
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
}
