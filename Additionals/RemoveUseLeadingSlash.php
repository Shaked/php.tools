<?php
class RemoveUseLeadingSlash extends AdditionalPass {
	public function candidate($source, $found_tokens) {
		if (isset($found_tokens[T_NAMESPACE]) || isset($found_tokens[T_TRAIT]) || isset($found_tokens[T_CLASS]) || isset($found_tokens[T_FUNCTION]) || isset($found_tokens[T_NS_SEPARATOR])) {
			return true;
		}

		return false;
	}
	public function format($source) {
		$this->tkns = token_get_all($source);
		$this->code = '';
		$last_touched_token = null;
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->getToken($token);
			$this->ptr = $index;
			switch ($id) {
				case T_NAMESPACE:
				case T_TRAIT:
				case T_CLASS:
				case T_FUNCTION:
					$last_touched_token = $id;
				case T_NS_SEPARATOR:
					if (T_NAMESPACE == $last_touched_token && $this->leftTokenIs([T_USE])) {
						continue;
					}
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
		return 'Remove leading slash in T_USE imports.';
	}

	/**
	 * @codeCoverageIgnore
	 */
	public function get_example() {
		return <<<'EOT'
<?php
namespace NS1;
use \B;
use \D;

new B();
new D();
?>
to
<?php
namespace NS1;
use B;
use D;

new B();
new D();
?>
EOT;
	}
}
