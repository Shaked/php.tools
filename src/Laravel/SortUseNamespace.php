<?php
final class SortUseNameSpace extends FormatterPass {

	private $pass = null;

	public function __construct() {
		$sortFunction = function ($useStack) {
			usort($useStack, function ($a, $b) {
				$len = strlen($a) - strlen($b);
				if (0 === $len) {
					return strcmp($a, $b);
				}
				return $len;
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
