<?php
final class PSR1BOMMark extends FormatterPass {
	public function candidate($source, $found_tokens) {
		return true;
	}
	public function format($source) {
		$bom = "\xef\xbb\xbf";
		if (substr($source, 0, 3) === $bom) {
			return substr($source, 3);
		}
		return $source;
	}
}
