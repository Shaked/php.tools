<?php
final class RTrim extends FormatterPass {
	public function candidate($source, $found_tokens) {
		return true;
	}
	public function format($source) {
		return implode(
			$this->new_line,
			array_map(
				function ($v) {
					return rtrim($v);
				},
				explode($this->new_line, $source)
			)
		);
	}
}