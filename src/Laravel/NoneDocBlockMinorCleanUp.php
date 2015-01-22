<?php
class NoneDocBlockMinorCleanUp extends AdditionalPass {
	public function candidate($source, $foundTokens) {
		if (isset($foundTokens[T_COMMENT])) {
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
				case T_COMMENT:
					if ((substr($text, 0, 3) != '/**') &&
						(substr($text, 0, 2) != '//')) {
						list(, $prevText) = $this->inspectToken(-1);
						$counts = substr_count($prevText, "\t");
						$replacement = "\n" . str_repeat("\t", $counts);
						$this->appendCode(preg_replace('/\n\s*/', $replacement, $text));
					} else {
						$this->appendCode($text);
					}
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
		return 'Realign /* block, not /** nor //';
	}

	/**
	 * @codeCoverageIgnore
	 */
	public function getExample() {
		return <<<'EOT'
<?php
	/*
	| some text
	 */

	/*
| fixing if this exist in core
	 */

	// this line is ignored

	/** this is also ignored
	 */
?>
to
<?php
	/*
	| some text
	*/

	// this line is ignored

	/** this is also ignored
	 */
?>
EOT;
	}
}
