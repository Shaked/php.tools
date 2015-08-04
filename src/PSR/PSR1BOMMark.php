<?php
final class PSR1BOMMark extends FormatterPass {

	const BOM = "\xef\xbb\xbf";

	public function candidate($source, $foundTokens) {
		return substr($source, 0, 3) === self::BOM;
	}

	public function format($source) {
		return substr($source, 3);
	}

}
