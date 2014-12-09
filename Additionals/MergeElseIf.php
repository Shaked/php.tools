<?php
/**
 * From PHP-CS-Fixer
 */
class MergeElseIf extends AdditionalPass {
	public function format($source) {
		$this->tkns = token_get_all($source);
		$this->code = '';
		$paren_count = 0;

		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->get_token($token);
			$this->ptr = $index;
			switch ($id) {
				case T_IF:
					if ($this->left_token_is([T_ELSE]) && !$this->left_token_is([T_OPEN_TAG, T_OPEN_TAG_WITH_ECHO])) {
						$this->rtrim_and_append_code($text);
						break;
					}
				default:
					$this->append_code($text);
					break;
			}
		}

		return $this->code;
	}
	/**
	 * @codeCoverageIgnore
	 */
	public function get_description() {
		return 'Merge if with else. ';
	}

	/**
	 * @codeCoverageIgnore
	 */
	public function get_example() {
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
