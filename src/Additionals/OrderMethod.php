<?php
class OrderMethod extends AdditionalPass {

	const METHOD_REPLACEMENT_PLACEHOLDER = "\x2 METHODPLACEHOLDER \x3";

	const OPENER_PLACEHOLDER = "<?php /*\x2 ORDERMETHOD \x3*/";

	public function candidate($source, $foundTokens) {
		if (isset($foundTokens[T_CLASS], $foundTokens[T_FUNCTION])) {
			return true;
		}

		return false;
	}

	public function format($source) {
		$this->tkns = token_get_all($source);

		// It scans for classes body and organizes functions internally.
		$return = '';
		$classBlock = '';

		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->getToken($token);
			$this->ptr = $index;
			switch ($id) {
			case T_CLASS:
				$return = $text;
				$return .= $this->walkAndAccumulateUntil($this->tkns, ST_CURLY_OPEN);
				$classBlock = $this->walkAndAccumulateCurlyBlock($this->tkns);
				$return .= str_replace(
					self::OPENER_PLACEHOLDER,
					'',
					static::orderMethods(self::OPENER_PLACEHOLDER . $classBlock)
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

	public function orderMethods($source) {
		$tokens = token_get_all($source);

		// It takes classes' body, and looks for methods and sorts them
		$return = '';
		$functionList = [];
		$curlyCount = null;
		$touchedMethod = false;
		$functionName = '';
		$touchedDocComment = false;
		$docCommentStack = '';

		while (list($index, $token) = each($tokens)) {
			list($id, $text) = $this->getToken($token);
			$this->ptr = $index;
			switch ($id) {
			case T_DOC_COMMENT:
				if (!$touchedDocComment) {
					$touchedDocComment = true;
					$docCommentStack = '';
				}
				$docCommentStack .= $text;
				break;

			case T_VARIABLE:
			case T_STRING:
				if ($touchedDocComment) {
					$touchedDocComment = false;
					$return .= $docCommentStack;
				}
				$return .= $text;
				break;

			case T_ABSTRACT:
			case T_STATIC:
			case T_PRIVATE:
			case T_PROTECTED:
			case T_PUBLIC:
				$stack = '';
				if ($touchedDocComment) {
					$touchedDocComment = false;
					$stack .= $docCommentStack;
				}
				$stack .= $text;
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
				$appendWith = $stack;
				if ($touchedMethod) {
					$functionList[$functionName] = $stack;
					$appendWith = self::METHOD_REPLACEMENT_PLACEHOLDER;
				}
				$return .= $appendWith;
				break;
			default:
				if ($touchedDocComment) {
					$docCommentStack .= $text;
					break;
				}
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

}
