<?php
class AddMissingParentheses extends AdditionalPass {
	public function candidate($source, $found_tokens) {
		if (isset($found_tokens[T_NEW])) {
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
				case T_NEW:
					$this->append_code($text);
					list($found_id, $found_text) = $this->print_and_stop_at([ST_PARENTHESES_OPEN, T_COMMENT, T_DOC_COMMENT, ST_SEMI_COLON]);
					if (ST_PARENTHESES_OPEN != $found_id) {
						$this->append_code('()' . $found_text);
					}
					break;
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
		return 'Add extra parentheses in new instantiations.';
	}

	/**
	 * @codeCoverageIgnore
	 */
	public function get_example() {
		return <<<'EOT'
<?php
$a = new SomeClass;

$a = new SomeClass();
?>
EOT;
	}
}
