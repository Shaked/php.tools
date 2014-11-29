<?php

class Cache {
	const DEFAULT_CACHE_FILENAME = '.php.tools.cache';

	private $db;

	public function __construct($filename) {
		$start_db_creation = false;
		if (is_dir($filename)) {
			$filename = realpath($filename) . DIRECTORY_SEPARATOR . self::DEFAULT_CACHE_FILENAME;
		}
		if (!file_exists($filename)) {
			$start_db_creation = true;
		}

		$this->set_db(new SQLite3($filename));
		if ($start_db_creation) {
			$this->create_db();
		}
	}

	public function __destruct() {
		$this->db->close();
	}

	public function create_db() {
		$this->db->exec('CREATE TABLE cache (target TEXT, filename TEXT, hash TEXT, unique(target, filename));');
	}

	public function upsert($target, $filename, $content) {
		$hash = $this->calculate_hash($content);
		$this->db->exec('REPLACE INTO cache VALUES ("' . SQLite3::escapeString($target) . '","' . SQLite3::escapeString($filename) . '", "' . SQLite3::escapeString($hash) . '")');
	}

	public function is_changed($target, $filename) {
		$row = $this->db->querySingle('SELECT hash FROM cache WHERE target = "' . SQLite3::escapeString($target) . '" AND filename = "' . SQLite3::escapeString($filename) . '"', true);
		if (empty($row)) {
			return true;
		}
		$content = file_get_contents($filename);
		if ($this->calculate_hash($content) != $row['hash']) {
			return $content;
		}
		return false;
	}

	private function set_db($db) {
		$this->db = $db;
	}

	private function calculate_hash($content) {
		return sprintf('%u', crc32($content));
	}
}