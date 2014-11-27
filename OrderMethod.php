<?php
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
					$this->append_code($return);
					break;
				default:
					$this->append_code($text);
					break;
			}
		}
		return $this->code;
	}
}
