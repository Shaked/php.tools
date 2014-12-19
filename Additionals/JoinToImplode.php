<?php
class JoinToImplode extends AdditionalPass {
	public function candidate($source, $foundTokens) {
		$this->tkns = token_get_all($source);
		$this->code = '';

		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->getToken($token);
			$this->ptr = $index;
			switch ($id) {
				case T_STRING:
					if (strtolower($text) == 'join') {
						prev($this->tkns);
						return true;
					}
			}
			$this->appendCode($text);
		}
		return false;
	}
	public function format($source) {
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->getToken($token);
			$this->ptr = $index;
			if (T_STRING == $id && strtolower($text) == 'join' && !($this->leftUsefulTokenIs([T_NEW, T_NS_SEPARATOR, T_STRING, T_DOUBLE_COLON, T_OBJECT_OPERATOR, T_FUNCTION]) || $this->rightUsefulTokenIs([T_NS_SEPARATOR, T_DOUBLE_COLON]))) {
				$this->appendCode('implode');
				continue;
			}
			$this->appendCode($text);
		}

		return $this->code;
	}

	/**
	 * @codeCoverageIgnore
	 */
	public function getDescription() {
		return 'Replace implode() alias (join() -> implode()).';
	}

	/**
	 * @codeCoverageIgnore
	 */
	public function getExample() {
		return <<<'EOT'
<?php
$a = join(',', $arr);

$a = implode(',', $arr);
?>
EOT;
	}

}
