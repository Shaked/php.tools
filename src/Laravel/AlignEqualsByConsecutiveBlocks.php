<?php
final class AlignEqualsByConsecutiveBlocks extends FormatterPass {

	public function candidate($source, $foundTokens) {
		if (isset($foundTokens[ST_EQUAL]) || isset($foundTokens[T_DOUBLE_ARROW])) {
			return true;
		}

		return false;
	}

	public function format($source) {
		// should align '= and '=>'
		$digFromHere = $this->tokensInLine($source);

		$seenEquals = [];
		$seenDoubleArrows = [];
		foreach ($digFromHere as $index => $line) {
			$match = null;
			if (preg_match('/^T_VARIABLE T_WHITESPACE =.+;/', $line, $match)) {
				array_push($seenEquals, $index);
			}
			$match = null;
			if (preg_match('/^(?:T_WHITESPACE )?(T_CONSTANT_ENCAPSED_STRING|T_VARIABLE) T_WHITESPACE T_DOUBLE_ARROW /', $line, $match) &&
				!strstr($line, 'T_ARRAY ( ')) {
				array_push($seenDoubleArrows, $index);
			}
		}

		$source = $this->generateConsecutiveFromArray($seenEquals, $source);
		$source = $this->generateConsecutiveFromArray($seenDoubleArrows, $source);

		return $source;
	}

	private function generateConsecutiveFromArray($seenArray, $source) {
		$lines = explode("\n", $source);
		foreach ($this->getConsecutiveFromArray($seenArray) as $bucket) {
			//get max position of =
			$maxPosition = 0;
			$eq = ' =';
			$toBeSorted = [];
			foreach ($bucket as $indexInBucket) {
				$position = strpos($lines[$indexInBucket], $eq);
				$maxPosition = max($maxPosition, $position);
				array_push($toBeSorted, $position);
			}

			// find alternative max if there's a further = position
			// ratio of highest : second highest > 1.5, else use the second highest
			// just run the top 5 to seek the alternative
			rsort($toBeSorted);
			for ($i = 1; $i <= 5; ++$i) {
				if (isset($toBeSorted[$i])) {
					if ($toBeSorted[($i - 1)] / $toBeSorted[$i] > 1.5) {
						$maxPosition = $toBeSorted[$i];
						break;
					}
				}
			}
			// insert space directly
			foreach ($bucket as $indexInBucket) {
				$delta = $maxPosition - strpos($lines[$indexInBucket], $eq);
				if ($delta > 0) {
					$replace = str_repeat(' ', $delta) . $eq;
					$lines[$indexInBucket] = preg_replace("/$eq/", $replace, $lines[$indexInBucket]);
				}
			}
		}
		return implode("\n", $lines);
	}

	private function getConsecutiveFromArray($seenArray) {
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
			if ((count($seenArray) - 1) == $j && (count($temp) > 1)) {
				array_push($seenBuckets, $temp); //push to bucket
			}
		}
		return $seenBuckets;
	}

	private function tokensInLine($source) {
		$tokens = token_get_all($source);
		$processed = [];
		$seen = 1;
		$tokensLine = '';
		foreach ($tokens as $token) {
			if (!isset($token[2])) {
				$tokensLine .= $token . ' ';
				continue;
			}
			$currLine = $token[2];
			if ($seen == $currLine) {
				$tokensLine .= token_name($token[0]) . ' ';
				continue;
			}
			$processed[($seen - 1)] = $tokensLine;
			$tokensLine = token_name($token[0]) . ' ';
			$seen = $currLine;
		}
		$processed[($seen - 1)] = $tokensLine;
		return $processed;
	}

}
