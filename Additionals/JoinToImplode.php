<?php
class JoinToImplode extends AdditionalPass {
	public function candidate($source, $found_tokens) {
		$this->tkns = token_get_all($source);
		$this->code = '';

		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->get_token($token);
			$this->ptr = $index;
			switch ($id) {
				case T_STRING:
					if (strtolower($text) == 'join') {
						prev($this->tkns);
						return true;
					}
			}
			$this->append_code($text);
		}
		return false;
	}
	public function format($source) {
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->get_token($token);
			$this->ptr = $index;
			if (T_STRING == $id && strtolower($text) == 'join' && !($this->left_useful_token_is([T_NEW, T_NS_SEPARATOR, T_STRING, T_DOUBLE_COLON, T_OBJECT_OPERATOR, T_FUNCTION]) || $this->right_useful_token_is([T_NS_SEPARATOR, T_DOUBLE_COLON]))) {
				$this->append_code('implode');
				continue;
			}
			$this->append_code($text);
		}

		return $this->code;
	}

	/**
	 * @codeCoverageIgnore
	 */
	public function get_description() {
		return 'Replace implode() alias (join() -> implode()).';
	}

	/**
	 * @codeCoverageIgnore
	 */
	public function get_example() {
		return <<<'EOT'
<?php
$a = join(',', $arr);

$a = implode(',', $arr);
?>
EOT;
	}

}
