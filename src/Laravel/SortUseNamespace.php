<?php
class SortUseNameSpace extends FormatterPass {
	private $pass = null;
	public function __construct() {
		$sortFunction = function ($useStack) {
			usort($useStack, function ($a, $b) {
				return strlen($a) - strlen($b);
			});
			return $useStack;
		};
		$this->pass = new OrderUseClauses($sortFunction);
	}
	public function candidate($source, $foundTokens) {
		return $this->pass->candidate($source, $foundTokens);
	}
	public function format($source) {
		return $this->pass->format($source);
	}
}
