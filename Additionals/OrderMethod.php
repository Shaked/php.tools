<?php
final class OrderMethod extends AdditionalPass {
	const OPENER_PLACEHOLDER = "<?php /*\x2 ORDERMETHOD \x3*/";
	const METHOD_REPLACEMENT_PLACEHOLDER = "\x2 METHODPLACEHOLDER \x3";

	public function orderMethods($source) {
		$tokens = token_get_all($source);
		$return = '';
		$functionList = [];
		while (list($index, $token) = each($tokens)) {
			list($id, $text) = $this->getToken($token);
			$this->ptr = $index;
			switch ($id) {
				case T_ABSTRACT:
				case T_STATIC:
				case T_PRIVATE:
				case T_PROTECTED:
				case T_PUBLIC:
					$stack = $text;
					$curlyCount = null;
					$touchedMethod = false;
					$functionName = '';
					while (list($index, $token) = each($tokens)) {
						list($id, $text) = $this->getToken($token);
						$this->ptr = $index;

						$stack .= $text;
						if (T_FUNCTION == $id) {
							$touchedMethod = true;
						}
						if (T_VARIABLE == $id && !$touchedMethod) {
							break;
						}
						if (T_STRING == $id && $touchedMethod && empty($functionName)) {
							$functionName = $text;
						}

						if (null === $curlyCount && ST_SEMI_COLON == $id) {
							break;
						}

						if (ST_CURLY_OPEN == $id) {
							++$curlyCount;
						}
						if (ST_CURLY_CLOSE == $id) {
							--$curlyCount;
						}
						if (0 === $curlyCount) {
							break;
						}
					}
					if (!$touchedMethod) {
						$return .= $stack;
					} else {
						$functionList[$functionName] = $stack;
						$return .= self::METHOD_REPLACEMENT_PLACEHOLDER;
					}
					break;
				default:
					$return .= $text;
					break;
			}
		}
		ksort($functionList);
		foreach ($functionList as $functionBody) {
			$return = preg_replace('/' . self::METHOD_REPLACEMENT_PLACEHOLDER . '/', $functionBody, $return, 1);
		}
		return $return;
	}

	public function candidate($source, $foundTokens) {
		return true;
	}

	public function format($source) {
		$this->tkns = token_get_all($source);
		$return = '';
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->getToken($token);
			$this->ptr = $index;
			switch ($id) {
				case T_CLASS:
					$return .= $text;
					while (list($index, $token) = each($this->tkns)) {
						list($id, $text) = $this->getToken($token);
						$this->ptr = $index;
						$return .= $text;
						if (ST_CURLY_OPEN == $id) {
							break;
						}
					}
					$classBlock = '';
					$curlyCount = 1;
					while (list($index, $token) = each($this->tkns)) {
						list($id, $text) = $this->getToken($token);
						$this->ptr = $index;
						$classBlock .= $text;
						if (ST_CURLY_OPEN == $id) {
							++$curlyCount;
						} elseif (ST_CURLY_CLOSE == $id) {
							--$curlyCount;
						}

						if (0 == $curlyCount) {
							break;
						}
					}
					$return .= str_replace(
						self::OPENER_PLACEHOLDER,
						'',
						$this->orderMethods(self::OPENER_PLACEHOLDER . $classBlock)
					);
					$this->appendCode($return);
					break;
				default:
					$this->appendCode($text);
					break;
			}
		}
		return $this->code;
	}

	/**
	 * @codeCoverageIgnore
	 */
	public function getDescription() {
		return 'Sort methods within class in alphabetic order.';
	}

	/**
	 * @codeCoverageIgnore
	 */
	public function getExample() {
		return <<<'EOT'
<?php
class A {
	function b(){}
	function c(){}
	function a(){}
}
?>
to
<?php
class A {
	function a(){}
	function b(){}
	function c(){}
}
?>
EOT;
	}
}
