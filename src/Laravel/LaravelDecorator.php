<?php
class LaravelDecorator {
	public static function decorate(CodeFormatter &$fmt) {
		$passes = $fmt->getPasses();

		$fmt = new CodeFormatter();

		foreach ($passes as $pass) {
			$fmt->addPass($pass);
			if (get_class($pass) == 'AddMissingCurlyBraces') {
				$fmt->addPass(new SmartLnAfterCurlyOpen());
			}
		}

		$fmt->addPass(new LaravelStyle());
	}
}