<?php
final class OrganizeClass extends OrderMethod {
	public function orderMethods($source) {
		$tokens = token_get_all($source);

		// It takes classes' body, and looks for methods, constants
		// and attributes, and recreates an organized class out of them.
		$attributeList = [];
		$commentStack = [];
		$constList = [];
		$docCommentStack = '';
		$functionList = [];
		$touchedDocComment = false;

		while (list($index, $token) = each($tokens)) {
			list($id, $text) = $this->getToken($token);
			$this->ptr = $index;
			switch ($id) {
			case T_COMMENT:
				if (strpos($text, "\x2") === false) {
					$commentStack[] = $text;
				}
				break;

			case T_DOC_COMMENT:
				if (!$touchedDocComment) {
					$touchedDocComment = true;
					$docCommentStack = ' ';
				}
				$docCommentStack .= $text;
				break;

			case T_CONST:
				$stack = '';
				if ($touchedDocComment) {
					$touchedDocComment = false;
					$stack .= $docCommentStack;
				}
				$stack .= $text;
				$constName = $this->walkAndAccumulateUntil($tokens, T_STRING);
				$stack .= $constName;
				$stack .= $this->walkAndAccumulateUntil($tokens, ST_SEMI_COLON);
				$constList[$constName] = $stack;
				break;

			case T_ABSTRACT:
			case T_FUNCTION:
			case T_PRIVATE:
			case T_PROTECTED:
			case T_PUBLIC:
			case T_STATIC:
			case T_VARIABLE:
				$stack = '';
				if ($touchedDocComment) {
					$touchedDocComment = false;
					$stack .= $docCommentStack;
				}
				$touchedMethod = false;
				$touchedAttribute = false;
				$functionName = '';
				$attributeName = '';
				$visibilityLevel = 0;

				$searchFor = [
					T_ABSTRACT,
					T_FUNCTION,
					T_PRIVATE,
					T_PROTECTED,
					T_PUBLIC,
					T_STATIC,
					T_STRING,
					T_VARIABLE,
				];
				prev($tokens);

				do {
					list($foundText, $foundId) = $this->walkAndAccumulateUntilAny($tokens, $searchFor);
					if (T_PROTECTED == $foundId) {
						$visibilityLevel = 1;
					} elseif (T_PRIVATE == $foundId) {
						$visibilityLevel = 2;
					} elseif (T_FUNCTION == $foundId) {
						$touchedMethod = true;
					} elseif (T_VARIABLE == $foundId) {
						$touchedAttribute = true;
						$attributeName = $foundText;
					} elseif (T_STRING == $foundId && $touchedMethod) {
						$functionName = $foundText;
					}
					$stack .= $foundText;
				} while (empty($functionName) && empty($attributeName));

				if ($touchedMethod) {
					$stack .= $this->walkAndAccumulateUntil($tokens, ST_CURLY_OPEN);
					$stack .= $this->walkAndAccumulateCurlyBlock($tokens);
					$functionList[$visibilityLevel . ':' . $functionName] = $stack;
				} elseif ($touchedAttribute) {
					$stack .= $this->walkAndAccumulateUntil($tokens, ST_SEMI_COLON);
					$attributeList[$visibilityLevel . ':' . $attributeName] = $stack;
				}
				break;

			default:
				if ($touchedDocComment) {
					$docCommentStack .= $text;
					break;
				}
				break;
			}
		}
		ksort($constList);
		ksort($attributeList);
		ksort($functionList);

		$final = $this->newLine;
		foreach ($commentStack as $text) {
			$final .= ' ' . $text;
			if ($this->substrCountTrailing($text, "\n") === 0) {
				$final .= $this->newLine;
			}
		}

		$final .= $this->newLine;
		foreach ($constList as $text) {
			$final .= $text . $this->newLine . $this->newLine;
		}

		$final .= $this->newLine;
		foreach ($attributeList as $text) {
			$final .= $text . $this->newLine . $this->newLine;
		}

		$final .= $this->newLine;
		foreach ($functionList as $text) {
			$final .= $text . $this->newLine . $this->newLine;
		}

		return $final . $this->newLine . ST_CURLY_CLOSE;
	}

	/**
	 * @codeCoverageIgnore
	 */
	public function getDescription() {
		return 'Organize class structure (beta).';
	}

	/**
	 * @codeCoverageIgnore
	 */
	public function getExample() {
		return <<<'EOT'
<?php
// From
class A {
	public function d(){}
	protected function b(){}
	private $a = "";
	private function c(){}
	public function a(){}
	public $b = "";
	const B = 0;
	const A = 0;
}

// To
class A {
	const A = 0;

	const B = 0;

	public $b = "";

	private $a = "";

	public function a(){}

	public function d(){}

	protected function b(){}

	private function c(){}
}
?>
EOT;
	}
}
