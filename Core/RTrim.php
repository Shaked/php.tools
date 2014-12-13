<?php
final class RTrim extends FormatterPass {
	public function candidate($source, $found_tokens) {
		return true;
	}
	public function format($source) {
		return preg_replace('/\h+$/m', '', $source);
	}
}