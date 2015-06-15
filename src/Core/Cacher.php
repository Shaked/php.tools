<?php
interface Cacher {
	const DEFAULT_CACHE_FILENAME = '.php.tools.cache';
	public function create_db();
	public function upsert($target, $filename, $content);
	public function is_changed($target, $filename);
}
