<?php
/**
 * @codeCoverageIgnore
 */
final class Cache {
	public function create_db() {}

	public function upsert($target, $filename, $content) {}

	public function is_changed($target, $filename) {
		return file_get_contents($filename);
	}
}
