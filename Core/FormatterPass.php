<?php
abstract class FormatterPass {
	protected $indent_char = "\t";
	protected $new_line = "\n";
	protected $indent = 0;
	protected $code = '';
	protected $ptr = 0;
	protected $tkns = [];
	protected $use_cache = false;
	protected $cache = [];
	protected $ignore_futile_tokens = [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT];

	protected function append_code($code = "") {
		$this->code .= $code;
	}

	private function calculate_cache_key($direction, $ignore_list, $token) {
		return $direction . "\x2" . implode('', $ignore_list) . "\x2" . (is_array($token) ? implode("\x2", $token) : $token);
	}

	abstract public function candidate($source, $found_tokens);
	abstract public function format($source);

	protected function get_token($token) {
		if (isset($token[1])) {
			return $token;
		} else {
			return [$token, $token];
		}
	}

	protected function get_crlf($true = true) {
		return $true ? $this->new_line : "";
	}

	protected function get_crlf_indent() {
		return $this->get_crlf() . $this->get_indent();
	}

	protected function get_indent($increment = 0) {
		return str_repeat($this->indent_char, $this->indent + $increment);
	}

	protected function get_space($true = true) {
		return $true ? " " : "";
	}

	protected function has_ln($text) {
		return (false !== strpos($text, $this->new_line));
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

	protected function has_ln_left_token() {
		list($id, $text) = $this->get_token($this->left_token());
		return $this->has_ln($text);
	}

	protected function has_ln_right_token() {
		list($id, $text) = $this->get_token($this->right_token());
		return $this->has_ln($text);
	}

	protected function inspect_token($delta = 1) {
		if (!isset($this->tkns[$this->ptr + $delta])) {
			return [null, null];
		}
		return $this->get_token($this->tkns[$this->ptr + $delta]);
	}

	protected function left_token($ignore_list = [], $idx = false) {
		$i = $this->left_token_idx($ignore_list);

		return $this->tkns[$i];
	}

	protected function left_token_idx($ignore_list = []) {
		$ignore_list = $this->resolve_ignore_list($ignore_list);

		$i = $this->walk_left($this->tkns, $this->ptr, $ignore_list);

		return $i;
	}

	protected function left_token_is($token, $ignore_list = []) {
		return $this->token_is('left', $token, $ignore_list);
	}

	protected function left_token_subset_is_at_idx($tkns, $idx, $token, $ignore_list = []) {
		$ignore_list = $this->resolve_ignore_list($ignore_list);

		$idx = $this->walk_left($tkns, $idx, $ignore_list);

		return $this->resolve_token_match($tkns, $idx, $token);
	}

	protected function left_useful_token() {
		return $this->left_token($this->ignore_futile_tokens);
	}

	protected function left_useful_token_idx() {
		return $this->left_token_idx($this->ignore_futile_tokens);
	}

	protected function left_useful_token_is($token) {
		return $this->left_token_is($token, $this->ignore_futile_tokens);
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
			$this->append_code($text);
		}
	}

	protected function print_block($start, $end) {
		$count = 1;
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->get_token($token);
			$this->ptr = $index;
			$this->cache = [];
			$this->append_code($text);

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

	protected function print_curly_block() {
		$count = 1;
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->get_token($token);
			$this->ptr = $index;
			$this->cache = [];
			$this->append_code($text);

			if (ST_CURLY_OPEN == $id) {
				++$count;
			}
			if (T_CURLY_OPEN == $id) {
				++$count;
			}
			if (T_DOLLAR_OPEN_CURLY_BRACES == $id) {
				++$count;
			}
			if (ST_CURLY_CLOSE == $id) {
				--$count;
			}
			if (0 == $count) {
				break;
			}
		}
	}

	protected function print_until($tknid) {
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->get_token($token);
			$this->ptr = $index;
			$this->cache = [];
			$this->append_code($text);
			if ($tknid == $id) {
				break;
			}
		}
	}

	protected function print_until_any($tknids) {
		$tknids = array_flip($tknids);
		$whitespace_new_line = false;
		if (isset($tknids[$this->new_line])) {
			$whitespace_new_line = true;
		}
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->get_token($token);
			$this->ptr = $index;
			$this->cache = [];
			$this->append_code($text);
			if ($whitespace_new_line && T_WHITESPACE == $id && $this->has_ln($text)) {
				break;
			}
			if (isset($tknids[$id])) {
				break;
			}
		}
		return $id;
	}

	protected function print_until_the_end_of_string() {
		$this->print_until(ST_QUOTE);
	}

	protected function render($tkns = null) {
		if (null == $tkns) {
			$tkns = $this->tkns;
		}

		$tkns = array_filter($tkns);
		$str = '';
		foreach ($tkns as $token) {
			list($id, $text) = $this->get_token($token);
			$str .= $text;
		}
		return $str;
	}

	protected function render_light($tkns = null) {
		if (null == $tkns) {
			$tkns = $this->tkns;
		}
		$str = '';
		foreach ($tkns as $token) {
			$str .= $token[1];
		}
		return $str;
	}

	private function resolve_ignore_list($ignore_list = []) {
		if (empty($ignore_list)) {
			$ignore_list[T_WHITESPACE] = true;
		} else {
			$ignore_list = array_flip($ignore_list);
		}
		return $ignore_list;
	}

	private function resolve_token_match($tkns, $idx, $token) {
		if (!isset($tkns[$idx])) {
			return false;
		}

		$found_token = $tkns[$idx];
		if ($found_token === $token) {
			return true;
		} elseif (is_array($token) && isset($found_token[1]) && in_array($found_token[0], $token)) {
			return true;
		} elseif (is_array($token) && !isset($found_token[1]) && in_array($found_token, $token)) {
			return true;
		} elseif (isset($found_token[1]) && $found_token[0] == $token) {
			return true;
		}

		return false;
	}

	protected function right_token($ignore_list = []) {
		$i = $this->right_token_idx($ignore_list);

		return $this->tkns[$i];
	}

	protected function right_token_idx($ignore_list = []) {
		$ignore_list = $this->resolve_ignore_list($ignore_list);

		$i = $this->walk_right($this->tkns, $this->ptr, $ignore_list);

		return $i;
	}

	protected function right_token_is($token, $ignore_list = []) {
		return $this->token_is('right', $token, $ignore_list);
	}

	protected function right_token_subset_is_at_idx($tkns, $idx, $token, $ignore_list = []) {
		$ignore_list = $this->resolve_ignore_list($ignore_list);

		$idx = $this->walk_right($tkns, $idx, $ignore_list);

		return $this->resolve_token_match($tkns, $idx, $token);
	}

	protected function right_useful_token() {
		return $this->right_token($this->ignore_futile_tokens);
	}

	// protected function right_useful_token_idx($idx = false) {
	// 	return $this->right_token_idx($this->ignore_futile_tokens);
	// }

	protected function right_useful_token_is($token) {
		return $this->right_token_is($token, $this->ignore_futile_tokens);
	}

	protected function rtrim_and_append_code($code = "") {
		$this->code = rtrim($this->code) . $code;
	}

	protected function scan_and_replace(&$tkns, &$ptr, $start, $end, $call, $look_for) {
		$look_for = array_flip($look_for);
		$placeholder = '<?php' . ' /*\x2 PHPOPEN \x3*/';
		$tmp = '';
		$tkn_count = 1;
		$found_potential_tokens = false;
		while (list($ptr, $token) = each($tkns)) {
			list($id, $text) = $this->get_token($token);
			if (isset($look_for[$id])) {
				$found_potential_tokens = true;
			}
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
		if ($found_potential_tokens) {
			return $start . str_replace($placeholder, '', $this->{$call}($placeholder . $tmp)) . $end;
		}
		return $start . $tmp . $end;

	}

	protected function set_indent($increment) {
		$this->indent += $increment;
		if ($this->indent < 0) {
			$this->indent = 0;
		}
	}

	protected function siblings($tkns, $ptr) {
		$ignore_list = $this->resolve_ignore_list([T_WHITESPACE]);
		$left = $this->walk_left($tkns, $ptr, $ignore_list);
		$right = $this->walk_right($tkns, $ptr, $ignore_list);
		return [$left, $right];
	}

	protected function substr_count_trailing($haystack, $needle) {
		return strlen(rtrim($haystack, " \t")) - strlen(rtrim($haystack, " \t" . $needle));
	}

	protected function token_is($direction, $token, $ignore_list = []) {
		if ('left' != $direction) {
			$direction = 'right';
		}
		if (!$this->use_cache) {
			return $this->{$direction . '_token_subset_is_at_idx'}($this->tkns, $this->ptr, $token, $ignore_list);
		}

		$key = $this->calculate_cache_key($direction, $ignore_list, $token);
		if (isset($this->cache[$key])) {
			return $this->cache[$key];
		}

		$ret = $this->{$direction . '_token_subset_is_at_idx'}($this->tkns, $this->ptr, $token, $ignore_list);
		$this->cache[$key] = $ret;

		return $ret;
	}

	protected function walk_and_accumulate_until(&$tkns, $tknid) {
		$ret = '';
		while (list($index, $token) = each($tkns)) {
			list($id, $text) = $this->get_token($token);
			$this->ptr = $index;
			$ret .= $text;
			if ($tknid == $id) {
				break;
			}
		}
		return $ret;
	}

	private function walk_left($tkns, $idx, $ignore_list) {
		$i = $idx;
		while (--$i >= 0 && isset($tkns[$i][1]) && isset($ignore_list[$tkns[$i][0]]));
		return $i;
	}

	private function walk_right($tkns, $idx, $ignore_list) {
		$i = $idx;
		$tkns_size = sizeof($tkns) - 1;
		while (++$i < $tkns_size && isset($tkns[$i][1]) && isset($ignore_list[$tkns[$i][0]]));
		return $i;
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
}
