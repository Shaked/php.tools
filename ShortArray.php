<?php
/**
 * From PHP-CS-Fixer
 */
class ShortArray extends AdditionalPass {
	const FOUND_ARRAY = 'array';
	const FOUND_PARENTHESES = 'paren';
	public function format($source) {
		$this->tkns = token_get_all($source);
		$this->code = '';
		$found_paren = [];

		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->get_token($token);
			$this->ptr = $index;
			switch ($id) {
				case T_ARRAY:
					if ($this->right_token_is([ST_PARENTHESES_OPEN])) {
						$found_paren[] = self::FOUND_ARRAY;
						$this->print_and_stop_at(ST_PARENTHESES_OPEN);
						$this->append_code(ST_BRACKET_OPEN);
						break;
					}
				case ST_PARENTHESES_OPEN:
					$found_paren[] = self::FOUND_PARENTHESES;
					$this->append_code($text);
					break;

				case ST_PARENTHESES_CLOSE:
					$pop_token = array_pop($found_paren);
					if (self::FOUND_ARRAY == $pop_token) {
						$this->append_code(ST_BRACKET_CLOSE);
						break;
					}
				default:
					$this->append_code($text);
					break;
			}
		}

		return $this->code;
	}

	public function get_description() {
		return 'Convert old array into new array. (array() -> [])';
	}

	public function get_example() {
		return <<<'EOT'
<?php
echo array();
?>
to
<?php
echo [];
?>
EOT;
	}
}
