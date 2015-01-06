<?php
final class CodeFormatter {
	private $passes = [];
	public function addPass(FormatterPass $pass) {
		array_unshift($this->passes, $pass);
	}
	public function removePass($passName) {
		$idx = [];
		foreach ($this->passes as $k => $pass) {
			if (get_class($pass) == $passName) {
				$idx[] = $k;
			}
		}
		foreach ($idx as $k) {
			unset($this->passes[$k]);
		}
		$this->passes = array_values($this->passes);
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
		$foundTokens = [];
		$tkns = token_get_all($source);
		foreach ($tkns as $token) {
			list($id, $text) = $this->getToken($token);
			$foundTokens[$id] = $id;
		}
		while (($pass = array_pop($passes))) {
			if ($pass->candidate($source, $foundTokens)) {
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