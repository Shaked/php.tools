<?php
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
						if (ST_CURLY_OPEN == $id) {
							break;
						}
					}
					$namespace_block = '';
					$curly_count = 1;
					while (list($index, $token) = each($tokens)) {
						list($id, $text) = $this->get_token($token);
						$this->ptr = $index;
						$namespace_block .= $text;
						if (ST_CURLY_OPEN == $id) {
							++$curly_count;
						} elseif (ST_CURLY_CLOSE == $id) {
							--$curly_count;
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