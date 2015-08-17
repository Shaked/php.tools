<?php
/**
 * @codeCoverageIgnore
 */
final class Cache implements Cacher {

	private $db;

	private $noop = false;

	public function __construct($filename) {
		if (empty($filename)) {
			$this->noop = true;
			return;
		}

		$startDbCreation = false;
		if (is_dir($filename)) {
			$filename = realpath($filename) . DIRECTORY_SEPARATOR . self::DEFAULT_CACHE_FILENAME;
		}
		if (!file_exists($filename)) {
			$startDbCreation = true;
		}

		$this->setDb(new SQLite3($filename));
		$this->db->busyTimeout(1000);
		if ($startDbCreation) {
			$this->create_db();
		}
	}

	public function __destruct() {
		if ($this->noop) {
			return;
		}
		$this->db->close();
	}

	public function create_db() {
		if ($this->noop) {
			return;
		}
		$this->db->exec('CREATE TABLE cache (target TEXT, filename TEXT, hash TEXT, unique(target, filename));');
	}

	public function is_changed($target, $filename) {
		$content = file_get_contents($filename);
		if ($this->noop) {
			return $content;
		}
		$row = $this->db->querySingle('SELECT hash FROM cache WHERE target = "' . SQLite3::escapeString($target) . '" AND filename = "' . SQLite3::escapeString($filename) . '"', true);
		if (empty($row)) {
			return $content;
		}
		if ($this->calculateHash($content) != $row['hash']) {
			return $content;
		}
		return false;
	}

	public function upsert($target, $filename, $content) {
		if ($this->noop) {
			return;
		}
		$hash = $this->calculateHash($content);
		$this->db->exec('REPLACE INTO cache VALUES ("' . SQLite3::escapeString($target) . '","' . SQLite3::escapeString($filename) . '", "' . SQLite3::escapeString($hash) . '")');
	}

	private function calculateHash($content) {
		return sprintf('%u', crc32($content));
	}

	private function setDb($db) {
		$this->db = $db;
	}
}
