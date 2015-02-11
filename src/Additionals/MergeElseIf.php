<?php
/**
 * From PHP-CS-Fixer
 */
final class MergeElseIf extends AdditionalPass {
	public function candidate($source, $foundTokens) {
		if (isset($foundTokens[T_ELSE]) || isset($foundTokens[T_ELSEIF])) {
			return true;
		}

		return false;
	}
	public function format($source) {
		$this->tkns = token_get_all($source);
		$this->code = '';
		$isHHVM = defined('HHVM_VERSION');
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->getToken($token);
			$this->ptr = $index;
			switch ($id) {
				case T_IF:
					if ($this->leftTokenIs([T_ELSE]) && !$this->leftTokenIs([T_OPEN_TAG, T_OPEN_TAG_WITH_ECHO])) {
						$this->rtrimAndAppendCode($text);
						break;
					}
					$this->appendCode($text);
					break;
				case T_ELSEIF:
					if ($isHHVM) {
						$text = str_replace(' ', '', $text);
					}
					$this->appendCode($text);
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
		return 'Merge if with else.';
	}

	/**
	 * @codeCoverageIgnore
	 */
	public function getExample() {
		return <<<'EOT'
<?php
if($a){

} else if($b) {

}

if($a){

} elseif($b) {

}
?>
EOT;
	}
}
