<?php
class WrongConstructorName extends AdditionalPass {
	public function candidate($source, $found_tokens) {
		if (isset($found_tokens[T_NAMESPACE]) || isset($found_tokens[T_CLASS])) {
			return true;
		}

		return false;
	}
	public function format($source) {
		$this->tkns = token_get_all($source);
		$this->code = '';
		$touched_namespace = false;
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->get_token($token);
			$this->ptr = $index;
			switch ($id) {
				case T_NAMESPACE:
					$touched_namespace = true;
					$this->append_code($text);
					break;
				case T_CLASS:
					$this->append_code($text);
					if ($this->left_useful_token_is([T_DOUBLE_COLON])) {
						break;
					}
					if ($touched_namespace) {
						break;
					}
					$class_local_name = '';
					while (list($index, $token) = each($this->tkns)) {
						list($id, $text) = $this->get_token($token);
						$this->ptr = $index;
						$this->append_code($text);
						if (T_STRING == $id) {
							$class_local_name = strtolower($text);
						}
						if (T_EXTENDS == $id || T_IMPLEMENTS == $id || ST_CURLY_OPEN == $id) {
							break;
						}
					}
					$count = 1;
					while (list($index, $token) = each($this->tkns)) {
						list($id, $text) = $this->get_token($token);
						$this->ptr = $index;

						if (T_STRING == $id && $this->left_useful_token_is([T_FUNCTION]) && strtolower($text) == $class_local_name) {
							$text = '__construct';
						}
						$this->append_code($text);

						if (ST_CURLY_OPEN == $id) {
							++$count;
						}
						if (ST_CURLY_CLOSE == $id) {
							--$count;
						}
						if (0 == $count) {
							break;
						}
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
		return 'Update old constructor names into new ones. http://php.net/manual/en/language.oop5.decon.php';
	}

	/**
	 * @codeCoverageIgnore
	 */
	public function get_example() {
		return <<<'EOT'
<?php
class A {
	function A(){

	}
}
?>
to
<?php
class A {
	function __construct(){

	}
}
?>
EOT;
	}
}