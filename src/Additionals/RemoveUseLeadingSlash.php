<?php
final class RemoveUseLeadingSlash extends AdditionalPass {

	public function candidate($source, $foundTokens) {
		if (isset($foundTokens[T_NAMESPACE]) || isset($foundTokens[T_TRAIT]) || isset($foundTokens[T_CLASS]) || isset($foundTokens[T_FUNCTION]) || isset($foundTokens[T_NS_SEPARATOR])) {
			return true;
		}

		return false;
	}

	public function format($source) {
		$this->tkns = token_get_all($source);
		$this->code = '';
		$lastTouchedToken = null;
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->getToken($token);
			$this->ptr = $index;
			switch ($id) {
			case T_NAMESPACE:
			case T_TRAIT:
			case T_CLASS:
			case T_FUNCTION:
				$lastTouchedToken = $id;
			case T_NS_SEPARATOR:
				if (T_NAMESPACE == $lastTouchedToken && $this->leftTokenIs([T_USE])) {
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
	public function getDescription() {
		return 'Remove leading slash in T_USE imports.';
	}

	/**
	 * @codeCoverageIgnore
	 */
	public function getExample() {
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
