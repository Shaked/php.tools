<?php
class SortUseNameSpace extends FormatterPass {
	public function candidate($source, $foundTokens) {
		if (isset($foundTokens[T_USE])) {
			return true;
		}

		return false;
	}

	public function format($source) {
		$digFromHere = $this->tokensInLine($source);
		$seenUseToken = [];
		foreach ($digFromHere as $index => $line) {
			$line = null;
			$match = null;
			if (preg_match('/^(?:T_WHITESPACE )?(T_USE) T_WHITESPACE /', $line, $match)) {
				array_push($seenUseToken, $index);
			}
		}
		$source = $this->sortTokenBlocks($seenUseToken, $source);
		return $source;
	}

	private function sortTokenBlocks($seenArray, $source) {
		$lines = explode("\n", $source);
		$buckets = $this->getTokensBuckets($seenArray);
		foreach ($buckets as $bucket) {
			$start = $bucket[0];
			$stop = $bucket[(count($bucket) - 1)];

			$t_use = array_splice($lines, $start, ($stop - $start + 1));
			$t_use = $this->sortByLength($t_use);

			$head = array_splice($lines, 0, $start);
			$lines = array_merge($head, $t_use, $lines);
		}
		return implode("\n", $lines); //$source;
	}

	private function getTokensBuckets($seenArray) {
		$temp = [];
		$seenBuckets = [];
		foreach ($seenArray as $j => $index) {
			if (0 !== $j) {
				if (($index - 1) !== $seenArray[($j - 1)]) {
					if (count($temp) > 1) {
						array_push($seenBuckets, $temp); //push to bucket
					}
					$temp = []; // clear temp
				}
			}
			array_push($temp, $index);
			if ((count($seenArray) - 1) == $j and (count($temp) > 1)) {
				array_push($seenBuckets, $temp); //push to bucket
			}
		}
		return $seenBuckets;
	}

	private function sortByLength($inArray) {
		$outArray = [];
		// prepend strlen in front, then sort, then remove prepend, done.
		foreach ($inArray as $line) {
			$prepend = strlen($line) . " $line"; // use ' ' + 'use' as delimit later on
			array_push($outArray, $prepend);
		}
		sort($outArray);
		$cleaned = [];
		foreach ($outArray as $line) {
			$unprepend = preg_replace('/^\d+ /', '', $line);
			array_push($cleaned, $unprepend);
		}
		return $cleaned;
	}

	private function tokensInLine($source) {
		$tokens = token_get_all($source);
		$processed = [];
		$seen = 1; // token_get_all always starts with 1
		$tokensLine = '';
		foreach ($tokens as $index => $token) {
			if (isset($token[2])) {
				$currLine = $token[2];
				if ($seen != $currLine) {
					$processed[($seen - 1)] = $tokensLine;
					$tokensLine = token_name($token[0]) . " ";
					$seen = $currLine;
				} else {
					$tokensLine .= token_name($token[0]) . " ";
				}
			} else {
				$tokensLine .= $token . " ";
			}
		}
		$processed[($seen - 1)] = $tokensLine; // consider the last line
		return $processed;
	}
}
