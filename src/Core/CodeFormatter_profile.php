<?php
/**
 * @codeCoverageIgnore
 */
final class CodeFormatter extends BaseCodeFormatter {

	private $currentTiming = null;

	private $timings = [];

	public function afterExecutedPass($source, $className) {
		$cn = get_class($className);
		$this->timings[$cn] = microtime(true) - $this->currentTiming;
		echo $cn, ':', (memory_get_usage() / 1024 / 1024), "\t", (memory_get_peak_usage() / 1024 / 1024), PHP_EOL;
	}

	public function afterFormat($source) {
		asort($this->timings, SORT_NUMERIC);
		$total = array_sum($this->timings);

		$lines = [];
		foreach ($this->timings as $pass => $timing) {
			$lines[] = [$pass, $timing, str_pad(round($timing / $total * 100, 3) . '%', 8, ' ', STR_PAD_LEFT)];
		}
		echo tabwriter($lines);
	}

	public function beforeFormat($source) {
		echo 'before:', (memory_get_usage() / 1024 / 1024), "\t", (memory_get_peak_usage() / 1024 / 1024), PHP_EOL;
	}

	public function beforePass($source, $className) {
		$this->currentTiming = microtime(true);
	}
}
