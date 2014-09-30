<?php
final class PSR1BOMMark extends FormatterPass {
	public function format($source) {
		$bom = "\xef\xbb\xbf";
		if ($bom === substr($source, 0, 3)) {
			return substr($source, 3);
		}
		return $source;
	}
}
