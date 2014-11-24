<?php
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
