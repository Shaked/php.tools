<?php
class SpaceBetweenMethods extends AdditionalPass {
	public function format($source) {
		$this->tkns = token_get_all($source);
		$this->code = '';
		$last_touched_token = null;
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->get_token($token);
			$this->ptr = $index;
			switch ($id) {
				case T_FUNCTION:
					$this->append_code($text);
					$this->print_until(ST_CURLY_OPEN);
					$this->print_block(ST_CURLY_OPEN, ST_CURLY_CLOSE);
					if (!$this->right_token_is([ST_CURLY_CLOSE, ST_SEMI_COLON, ST_COMMA, ST_PARENTHESES_CLOSE])) {
						$this->append_code($this->get_crlf());
					}
					break;
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
		return 'Put space between methods.';
	}

	/**
	 * @codeCoverageIgnore
	 */
	public function get_example() {
		return <<<'EOT'
<?php
class A {
	function b(){

	}
	function c(){

	}
}
?>
to
<?php
class A {
	function b(){

	}

	function c(){

	}

}
?>
EOT;
	}
}
