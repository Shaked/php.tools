<?php
final class LongArray extends AdditionalPass {
	const ST_SHORT_ARRAY_OPEN = 'SHORT_ARRAY_OPEN';
	const EMPTY_ARRAY = 'ST_EMPTY_ARRAY';

	public function candidate($source, $foundTokens) {
		return true;
	}

	public function format($source) {
		$this->tkns = token_get_all($source);

		$contextStack = array();
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->getToken($token);
			$this->ptr = $index;
			switch ($id) {
				case ST_BRACKET_OPEN:
					if (!$this->leftTokenIs(array(ST_BRACKET_CLOSE, ST_PARENTHESES_CLOSE, T_STRING, T_VARIABLE, T_ARRAY_CAST))) {
						$contextStack[] = self::ST_SHORT_ARRAY_OPEN;
						$id = self::ST_SHORT_ARRAY_OPEN;
						$text = 'array(';
					} else {
						$contextStack[] = ST_BRACKET_OPEN;
					}
					break;
				case ST_BRACKET_CLOSE:
					if (isset($contextStack[0]) && !$this->leftTokenIs(ST_BRACKET_OPEN)) {
						if (self::ST_SHORT_ARRAY_OPEN == end($contextStack)) {
							$id = ')';
							$text = ')';
						}
						array_pop($contextStack);
					}
					break;
				case T_STRING:
					if ($this->rightTokenIs(ST_PARENTHESES_OPEN)) {
						$contextStack[] = T_STRING;
					}
					break;
				case T_ARRAY:
					if ($this->rightTokenIs(ST_PARENTHESES_OPEN)) {
						$contextStack[] = T_ARRAY;
					}
					break;
				case ST_PARENTHESES_OPEN:
					if (isset($contextStack[0]) && T_ARRAY == end($contextStack) && $this->rightTokenIs(ST_PARENTHESES_CLOSE)) {
						array_pop($contextStack);
						$contextStack[] = self::EMPTY_ARRAY;
					} elseif (!$this->leftTokenIs(array(T_ARRAY, T_STRING))) {
						$contextStack[] = ST_PARENTHESES_OPEN;
					}
					break;
				case ST_PARENTHESES_CLOSE:
					if (isset($contextStack[0])) {
						array_pop($contextStack);
					}
					break;
			}
			$this->tkns[$this->ptr] = array($id, $text);
		}

		return $this->renderLight();
	}

	/**
	 * @codeCoverageIgnore
	 */
	public function getDescription() {
		return 'Convert short to long arrays.';
	}

	/**
	 * @codeCoverageIgnore
	 */
	public function getExample() {
		return <<<'EOT'
<?php
// From
$a = [$a, $b];

// To
$b = array($b, $c);
?>
EOT;
	}
}