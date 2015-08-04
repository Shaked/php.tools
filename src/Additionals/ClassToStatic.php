<?php
final class ClassToStatic extends ClassToSelf {

	const PLACEHOLDER = 'static';

	/**
	 * @codeCoverageIgnore
	 */
	public function getDescription() {
		return '"static" is preferred within class, trait or interface.';
	}

	/**
	 * @codeCoverageIgnore
	 */
	public function getExample() {
		return <<<'EOT'
<?php
// From
class A {
	const constant = 1;
	function b(){
		A::constant;
	}
}

// To
class A {
	const constant = 1;
	function b(){
		static::constant;
	}
}
?>
EOT;
	}

}
