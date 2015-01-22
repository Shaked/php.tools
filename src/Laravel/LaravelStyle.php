<?php
class LaravelStyle extends AdditionalPass {

	// trying to match http://laravel.com/docs/4.2/contributions#coding-style
	// PSR-0 and PSR-1 will use sublime-text settings.
	// # The class namespace declaration must be on the same line as <?php. [ok]
	// # A class' opening { must be on the same line as the class name. [ok]
	// # Functions and control structures must use Allman style braces. [ok with bug]
	// # Indent with tabs, align with spaces.
	// # reference with laravel/laravel/**/*.php & laravel/framework/**/*.php

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

		$fmt = new NoSpaceBetweenFunctionAndBracket();
		if ($fmt->candidate($source, $this->foundTokens)) {
			$source = $fmt->format($source);
		}

		$fmt = new SpaceAroundExclaimationMark();
		if ($fmt->candidate($source, $this->foundTokens)) {
			$source = $fmt->format($source);
		}

		$fmt = new NoneDocBlockMinorCleanUp();
		if ($fmt->candidate($source, $this->foundTokens)) {
			$source = $fmt->format($source);
		}

		// Vetoed because it used Regex to modify PHP code,
		// with no consideration about context book-keeping.
		// Therefore, this is not safe as it does not
		// distinguish between PHP code and inline strings.
		// $fmt = new SortUseNameSpace();
		// if ($fmt->candidate($source, $this->foundTokens)) {
		// 	$source = $fmt->format($source);
		// }

		// Vetoed because it used Regex to modify PHP code,
		// with no consideration about context book-keeping.
		// Therefore, this is not safe as it does not
		// distinguish between PHP code and inline strings.
		// $fmt = new AlignEqualsByConsecutiveBlocks();
		// if ($fmt->candidate($source, $this->foundTokens)) {
		// 	$source = $fmt->format($source);
		// }

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
		$foundStack = [];

		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->getToken($token);
			$this->ptr = $index;
			switch ($id) {
				case T_DOLLAR_OPEN_CURLY_BRACES:
				case T_CURLY_OPEN:
				case ST_CURLY_OPEN:
					if ($this->leftUsefulTokenIs([ST_PARENTHESES_CLOSE, T_ELSE, T_FINALLY, T_DO])) {
						list($prevId, $prevText) = $this->getToken($this->leftToken());
						if (!$this->hasLn($prevText)) {
							$this->appendCode($this->getCrlfIndent());
						}
					}
					$indentToken = [
						'id' => $id,
						'implicit' => true,
					];
					$this->appendCode($text);
					if ($this->hasLnAfter()) {
						$indentToken['implicit'] = false;
						$this->setIndent(+1);
					}
					$foundStack[] = $indentToken;
					break;

				case ST_BRACKET_OPEN:
				case ST_PARENTHESES_OPEN:
					$indentToken = [
						'id' => $id,
						'implicit' => true,
					];
					$this->appendCode($text);
					if ($this->hasLnAfter()) {
						$indentToken['implicit'] = false;
						$this->setIndent(+1);
					}
					$foundStack[] = $indentToken;
					break;

				case ST_BRACKET_CLOSE:
				case ST_PARENTHESES_CLOSE:
				case ST_CURLY_CLOSE:
					$poppedID = array_pop($foundStack);
					if (false === $poppedID['implicit']) {
						$this->setIndent(-1);
					}
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
