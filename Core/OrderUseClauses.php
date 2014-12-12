<?php
final class OrderUseClauses extends FormatterPass {
	const OPENER_PLACEHOLDER = "<?php /*\x2 ORDERBY \x3*/";
	public function candidate($source, $found_tokens) {
		if (isset($found_tokens[T_USE])) {
			return true;
		}

		return false;
	}
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
		if (empty($use_stack)) {
			return $source;
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
			} elseif (T_WHITESPACE == $token[0] && isset($new_tokens[$idx - 1], $new_tokens[$idx + 1]) && $new_tokens[$idx - 1] instanceof SurrogateToken && $new_tokens[$idx + 1] instanceof SurrogateToken) {
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
