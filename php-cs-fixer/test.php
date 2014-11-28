<?php
include '../FormatterPass.php';
include 'Token.php';
include 'FixerWrapper.php';
include 'Symfony/DoubleArrowMultilineWhitespacesFixer.php';
include 'Symfony/DuplicateSemicolonFixer.php';

final class CodeFormatter {
	private $passes = [];
	public function addPass(FormatterPass $pass) {
		array_unshift($this->passes, $pass);
	}

	public function formatCode($source = '') {
		$passes = array_map(
			function ($pass) {
				return clone $pass;
			},
			$this->passes
		);
		while (($pass = array_pop($passes))) {
			$source = $pass->format($source);
		}
		return $source;
	}
}

$phpcsfixers = new CodeFormatter();
$phpcsfixers->addPass(new DoubleArrowMultilineWhitespacesFixer());

echo $phpcsfixers->formatCode('<?php
$arr = array(
	1  =>  2,
);
'), PHP_EOL;

$phpcsfixers = new CodeFormatter();
$phpcsfixers->addPass(new DuplicateSemicolonFixer());
echo $phpcsfixers->formatCode('<?php
$arr = array(
	1  =>  2,
);;
'), PHP_EOL;