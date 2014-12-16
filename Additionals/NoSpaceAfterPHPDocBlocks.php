<?php
class NoSpaceAfterPHPDocBlocks extends FormatterPass {
	public function candidate($source, $found_tokens) {
		if (isset($found_tokens[T_DOC_COMMENT])) {
			return true;
		}

		return false;
	}
	public function format($source) {
		$this->tkns = token_get_all($source);
		$this->code = '';
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->get_token($token);
			$this->ptr = $index;
			switch ($id) {
				case T_WHITESPACE:
					if ($this->has_ln($text) && $this->left_token_is(T_DOC_COMMENT)) {
						$text = substr(strrchr($text, 10), 0);
						$this->append_code($text);
						break;
					}
				default:
					$this->append_code($text);
			}
		}

		return $this->code;
	}

	/**
	 * @codeCoverageIgnore
	 */
	public function get_description() {
		return 'Remove empty lines after PHPDoc blocks.';
	}

	/**
	 * @codeCoverageIgnore
	 */
	public function get_example() {
		return <<<'EOT'
<?php
/**
 * @param int $myInt
 */

function a($myInt){
}

/**
 * @param int $myInt
 */
function a($myInt){
}
?>
EOT;
	}
}