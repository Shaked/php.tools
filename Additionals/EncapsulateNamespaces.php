<?php
class EncapsulateNamespaces extends AdditionalPass {
	public function candidate($source, $foundTokens) {
		if (isset($foundTokens[T_NAMESPACE])) {
			return true;
		}

		return false;
	}
	public function format($source) {
		$this->tkns = token_get_all($source);
		$this->code = '';
		$in_namespace_context = false;
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->getToken($token);
			$this->ptr = $index;
			switch ($id) {
				case T_NAMESPACE:
					$this->appendCode($text);
					list($found_id, $found_text) = $this->printAndStopAt([ST_CURLY_OPEN, ST_SEMI_COLON]);
					if (ST_CURLY_OPEN == $found_id) {
						$this->appendCode($found_text);
						$this->printCurlyBlock();
					} elseif (ST_SEMI_COLON == $found_id) {
						$in_namespace_context = true;
						$this->appendCode(ST_CURLY_OPEN);
						list($found_id, $found_text) = $this->printAndStopAt([T_NAMESPACE, T_CLOSE_TAG]);
						if (T_CLOSE_TAG == $found_id) {
							return $source;
						}
						$this->appendCode($this->getCrlf() . ST_CURLY_CLOSE . $this->getCrlf());
						prev($this->tkns);
						continue;
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
