<?php
class NoSpaceBetweenFunctionAndBracket extends AdditionalPass {
	public function candidate($source, $foundTokens) {
		if (isset($foundTokens[T_FUNCTION])) {
			return true;
		}

		return false;
	}

	public function format($source) {
		$this->tkns = token_get_all($source);
		$this->code = '';

		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->getToken($token);
			$this->ptr = $index;
			switch ($id) {
				case T_FUNCTION:
					if ($this->rightTokenIs([T_WHITESPACE, '('])) {
						$grab = $text;
						$grab .= $this->walkAndAccumulateUntil($this->tkns, '(');
						$this->appendCode(str_replace(' ', '', $grab));
					} else {
						$this->appendCode($text);
					}
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
		return 'Remove space(s) between function and (';
	}

	/**
	 * @codeCoverageIgnore
	 */
	public function getExample() {
		return <<<'EOT'
<?php
Route::filter('auth.basic', function () {
	return Auth::basic();
});
App::before(function ($request) {
	//
});
?>
to
<?php
Route::filter('auth.basic', function()
{
	return Auth::basic();
});
App::before(function($request)
{
	//
});
?>
EOT;
	}
}
