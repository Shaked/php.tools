<?php
final class SettersAndGettersPass extends FormatterPass {
	const TYPE_CAMEL_CASE = 'camel';
	const TYPE_SNAKE_CASE = 'snake';
	const TYPE_GOLANG = 'golang';
	public function __construct($type) {
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
						if ($id == ST_CURLY_OPEN) {
							$curly_count++;
						}
						if ($id == ST_CURLY_CLOSE) {
							$curly_count--;
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
				return $this->generateSnakeCase($visibility, $var);
			case self::TYPE_CAMEL_CASE:
				return $this->generateCamelCase($visibility, $var);
			case self::TYPE_GOLANG:
				return $this->generateGolang($visibility, $var);
		}
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
}