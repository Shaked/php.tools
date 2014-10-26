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

	abstract public function format($source);
	protected function get_token($token) {
		if (is_string($token)) {
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
	protected function is_token($token, $prev = false) {
		return $this->is_token_idx($this->ptr, $token, $prev);
	}
	protected function is_token_idx($idx, $token, $prev = false) {
		$i = $idx;
		if ($prev) {
			while (--$i >= 0 && is_array($this->tkns[$i]) && $this->tkns[$i][0] === T_WHITESPACE);
		} else {
			while (++$i < sizeof($this->tkns) - 1 && is_array($this->tkns[$i]) && $this->tkns[$i][0] === T_WHITESPACE);
		}

		if (!isset($this->tkns[$i])) {
			return false;
		}

		$found_token = $this->tkns[$i];
		if (is_string($found_token) && $found_token === $token) {
			return true;
		} elseif (is_array($token) && is_array($found_token)) {
			if (in_array($found_token[0], $token)) {
				return true;
			} elseif ($prev && $found_token[0] === T_OPEN_TAG) {
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
			while (--$i >= 0 && is_array($tkns[$i]) && $tkns[$i][0] === T_WHITESPACE);
		} else {
			while (++$i < sizeof($tkns) - 1 && is_array($tkns[$i]) && $tkns[$i][0] === T_WHITESPACE);
		}

		if (!isset($tkns[$i])) {
			return false;
		}

		$found_token = $tkns[$i];
		if (is_string($found_token) && $found_token === $token) {
			return true;
		} elseif (is_array($token) && is_array($found_token)) {
			if (in_array($found_token[0], $token)) {
				return true;
			} elseif ($prev && $found_token[0] === T_OPEN_TAG) {
				return true;
			}
		} elseif (is_array($token) && is_string($found_token) && in_array($found_token, $token)) {
			return true;
		}
		return false;
	}

	protected function prev_token() {
		$i = $this->ptr;
		while (--$i >= 0 && is_array($this->tkns[$i]) && $this->tkns[$i][0] === T_WHITESPACE);
		return $this->tkns[$i];
	}
	protected function siblings($tkns, $ptr) {
		$i = $ptr;
		while (--$i >= 0 && is_array($tkns[$i]) && $tkns[$i][0] === T_WHITESPACE);
		$left = $i;
		$i = $ptr;
		while (++$i < sizeof($tkns) - 1 && is_array($tkns[$i]) && $tkns[$i][0] === T_WHITESPACE);
		$right = $i;
		return [$left, $right];
	}
	protected function has_ln_after() {
		$id = null;
		$text = null;
		list($id, $text) = $this->inspect_token();
		return T_WHITESPACE === $id && substr_count($text, $this->new_line) > 0;
	}
	protected function has_ln_before() {
		$id = null;
		$text = null;
		list($id, $text) = $this->inspect_token(-1);
		return T_WHITESPACE === $id && substr_count($text, $this->new_line) > 0;
	}
	protected function has_ln_prev_token() {
		list($id, $text) = $this->get_token($this->prev_token());
		return substr_count($text, $this->new_line) > 0;
	}
	protected function substr_count_trailing($haystack, $needle) {
		$cnt = 0;
		$i = strlen($haystack) - 1;
		for ($i = $i; $i >= 0; --$i) {
			$char = substr($haystack, $i, 1);
			if ($needle === $char) {
				++$cnt;
			} elseif (' ' !== $char && "\t" !== $char) {
				break;
			}
		}
		return $cnt;
	}
	protected function printUntilTheEndOfString() {
		$this->printUntilTheEndOf(ST_QUOTE);
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
	protected  function printUntilTheEndOf($tknid) {
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->get_token($token);
			$this->ptr = $index;
			$this->append_code($text, false);
			if ($tknid == $id) {
				break;
			}
		}
	}
}
