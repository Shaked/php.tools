<?php
class LaravelStyle extends AdditionalPass {
	private $foundTokens;
	public function candidate($source, $foundTokens) {
		$this->foundTokens = $foundTokens;
		return true;
	}

	public function format($source) {
		$source = $this->namespaceMergeWithOpenTag($source);
		$source = $this->allmanStyleBraces($source);
		$source = (new RTrim())->format($source);

		$fmt = new TightConcat();
		if ($fmt->candidate($source, $this->foundTokens)) {
			$source = $fmt->format($source);
		}
		return $source;
	}

	private function namespaceMergeWithOpenTag($source) {
		$this->tkns = token_get_all($source);
		$this->code = '';
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->getToken($token);
			$this->ptr = $index;
			switch ($id) {
				case T_NAMESPACE:
					if ($this->leftTokenIs(T_OPEN_TAG)) {
						$this->rtrimAndAppendCode($this->getSpace() . $text);
						break;
					}
				default:
					$this->appendCode($text);
			}
		}

		return $this->code;
	}

	private function allmanStyleBraces($source) {
		$this->tkns = token_get_all($source);
		$this->code = '';
		$max_detected_indent = 0;
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->getToken($token);
			$this->ptr = $index;
			switch ($id) {
				case ST_CURLY_OPEN:
					if ($this->leftUsefulTokenIs([ST_PARENTHESES_CLOSE, T_ELSE, T_FINALLY, T_DO])) {
						list($prevId, $prevText) = $this->getToken($this->leftToken());
						if (!$this->hasLn($prevText)) {
							$this->appendCode($this->getCrlfIndent());
						}
					}
					$this->setIndent(+1);
					$this->appendCode($text);
					break;

				case ST_CURLY_CLOSE:
					$this->setIndent(-1);
					$this->appendCode($text);
					break;

				case T_ELSE:
				case T_ELSEIF:
				case T_FINALLY:
					list($prevId, $prevText) = $this->getToken($this->leftToken());
					if (!$this->hasLn($prevText) && T_OPEN_TAG != $prevId) {
						$this->appendCode($this->getCrlfIndent());
					}
					$this->appendCode($text);
					break;
				case T_CATCH:
					if (' ' == substr($this->code, -1, 1)) {
						$this->code = substr($this->code, 0, -1);
					}
					list($prevId, $prevText) = $this->getToken($this->leftToken());
					if (!$this->hasLn($prevText)) {
						$this->appendCode($this->getCrlfIndent());
					}
					$this->appendCode($text);
					break;
				default:
					$this->appendCode($text);
			}
		}

		return $this->code;
	}

	/**
	 * @codeCoverageIgnore
	 */
	public function getDescription() {
		return 'Applies Laravel Coding Style';
	}

	/**
	 * @codeCoverageIgnore
	 */
	public function getExample() {
		return <<<'EOT'
<?php namespace A;

class A {
	function b()
	{
		if($a)
		{
			noop();
		}
		else
		{
			noop();
		}
	}

}
?>
EOT;
	}
}
