<?php
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
			if ($pass->candidate($source)) {
				$source = $pass->format($source);
			}
		}
		return $source;
	}
}