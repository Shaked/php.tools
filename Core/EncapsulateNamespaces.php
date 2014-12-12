<?php
class EncapsulateNamespaces extends AdditionalPass {
	public function candidate($source) {
		$this->tkns = token_get_all($source);
		$this->code = '';

		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->get_token($token);
			$this->ptr = $index;
			switch ($id) {
				case T_NAMESPACE:
					prev($this->tkns);
					return true;
			}
			$this->append_code($text);
		}

		return false;
	}
	public function format($source) {
		$in_namespace_context = false;
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->get_token($token);
			$this->ptr = $index;
			switch ($id) {
				case T_NAMESPACE:
					$this->append_code($text);
					list($found_id, $found_text) = $this->print_and_stop_at([ST_CURLY_OPEN, ST_SEMI_COLON]);
					if (ST_CURLY_OPEN == $found_id) {
						$this->append_code($found_text);
						$this->print_block(ST_CURLY_OPEN, ST_CURLY_CLOSE);
					} elseif (ST_SEMI_COLON == $found_id) {
						$in_namespace_context = true;
						$this->append_code(ST_CURLY_OPEN);
						list($found_id, $found_text) = $this->print_and_stop_at([T_NAMESPACE, T_CLOSE_TAG]);
						if (T_CLOSE_TAG == $found_id) {
							return $source;
						}
						$this->append_code($this->get_crlf() . ST_CURLY_CLOSE . $this->get_crlf());
						prev($this->tkns);
						continue;
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
		return 'Encapsulate namespaces with curly braces';
	}

	/**
	 * @codeCoverageIgnore
	 */
	public function get_example() {
		return <<<'EOT'
<?php
namespace NS1;
class A {
}
?>
to
<?php
namespace NS1 {
	class A {
	}
}
?>
EOT;
	}
}
