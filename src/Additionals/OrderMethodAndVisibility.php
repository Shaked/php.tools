<?php
final class OrderMethodAndVisibility extends OrderMethod {
	const METHOD_REPLACEMENT_PLACEHOLDER = "\x2 METHODPLACEHOLDER \x3";

	const OPENER_PLACEHOLDER = "<?php /*\x2 ORDERMETHOD \x3*/";

	/**
	 * @codeCoverageIgnore
	 */
	public function getDescription() {
		return 'Sort methods within class in alphabetic and visibility order .';
	}

	/**
	 * @codeCoverageIgnore
	 */
	public function getExample() {
		return <<<'EOT'
<?php
class A {
	public function d(){}
	protected function b(){}
	private function c(){}
	public function a(){}
}
?>
to
<?php
class A {
	public function a(){}
	public function d(){}
	protected function b(){}
	private function c(){}
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
				$visibilityLevel = 0;
				if (T_PROTECTED == $id) {
					$visibilityLevel = 1;
				} elseif (T_PRIVATE == $id) {
					$visibilityLevel = 2;
				}
				while (list($index, $token) = each($tokens)) {
					list($id, $text) = $this->getToken($token);
					$this->ptr = $index;

					$stack .= $text;
					if (T_PROTECTED == $id) {
						$visibilityLevel = 1;
					} elseif (T_PRIVATE == $id) {
						$visibilityLevel = 2;
					}

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
					$functionList[$visibilityLevel . ':' . $functionName] = $stack;
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
