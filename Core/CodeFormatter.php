<?php
final class CodeFormatter {
	private $passes = [];
	public function addPass(FormatterPass $pass) {
		array_unshift($this->passes, $pass);
	}

	public function getPassesNames() {
		return array_map(function ($v) {
			return get_class($v);
		}, $this->passes);
	}
	public function formatCode($source = '') {
		$passes = array_map(
			function ($pass) {
				return clone $pass;
			},
			$this->passes
		);
		$found_tokens = [];
		$tkns = token_get_all($source);
		foreach ($tkns as $token) {
			list($id, $text) = $this->getToken($token);
			$found_tokens[$id] = $id;
		}
		while (($pass = array_pop($passes))) {
			if ($pass->candidate($source, $found_tokens)) {
				$source = $pass->format($source);
			}
		}
		return $source;
	}

	protected function getToken($token) {
		if (isset($token[1])) {
			return $token;
		} else {
			return [$token, $token];
		}
	}
}