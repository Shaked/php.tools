<?php
class LaravelStyle extends AdditionalPass {

	// trying to match http://laravel.com/docs/4.2/contributions#coding-style
	// PSR-0 and PSR-1 will use sublime-text settings.
	// # The class namespace declaration must be on the same line as <?php. [ok]
	// # A class' opening { must be on the same line as the class name. [ok]
	// # Functions and control structures must use Allman style braces. [ok with bug]
	// # Indent with tabs, align with spaces.
	// ## tabs:not yet consider indent-with_space = true in phpfmt.sublime-settings
	// ## align:waiting for bugs feedback]
	// # addition: match formatting of laravel4.2/app/config/*.php & framework/**/*.php

	private $foundTokens;
	public function candidate($source, $foundTokens) {
		$this->foundTokens = $foundTokens;
		return true;
	}

	public function format($source) {
		$source = $this->namespaceMergeWithOpenTag($source);
		$source = $this->allmanStyleBraces($source);
		$source = (new RTrim())->format($source);

		$fmt = new TightConcat();
		if ($fmt->candidate($source, $this->foundTokens)) {
			$source = $fmt->format($source);
		}

		$source = $this->noneDocBlockMinorCleanUp($source);
		$source = $this->alignConsecutiveEqualSign($source);
		return $source;
	}

	private function namespaceMergeWithOpenTag($source) {
		$this->tkns = token_get_all($source);
		$this->code = '';
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->getToken($token);
			$this->ptr = $index;
			switch ($id) {
				case T_NAMESPACE:
					if ($this->leftTokenIs(T_OPEN_TAG)) {
						$this->rtrimAndAppendCode($this->getSpace() . $text);
						break;
					}
				default:
					$this->appendCode($text);
			}
		}

		return $this->code;
	}

	private function allmanStyleBraces($source) {
		$this->tkns = token_get_all($source);
		$this->code = '';
		$max_detected_indent = 0;
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->getToken($token);
			$this->ptr = $index;
			switch ($id) {
				case ST_CURLY_OPEN:
					if ($this->leftUsefulTokenIs([ST_PARENTHESES_CLOSE, T_ELSE, T_FINALLY, T_DO])) {
						list($prevId, $prevText) = $this->getToken($this->leftToken());
						if (!$this->hasLn($prevText)) {
							$this->appendCode($this->getCrlfIndent());
						}
					}
					$this->setIndent(+1);
					$this->appendCode($text);
					break;

				case ST_CURLY_CLOSE:
					$this->setIndent(-1);
					$this->appendCode($text);
					break;

				case T_ELSE:
				case T_ELSEIF:
				case T_FINALLY:
					list($prevId, $prevText) = $this->getToken($this->leftToken());
					if (!$this->hasLn($prevText) && T_OPEN_TAG != $prevId) {
						$this->appendCode($this->getCrlfIndent());
					}
					$this->appendCode($text);
					break;
				case T_CATCH:
					if (' ' == substr($this->code, -1, 1)) {
						$this->code = substr($this->code, 0, -1);
					}
					list($prevId, $prevText) = $this->getToken($this->leftToken());
					if (!$this->hasLn($prevText)) {
						$this->appendCode($this->getCrlfIndent());
					}
					$this->appendCode($text);
					break;
				default:
					$this->appendCode($text);
			}
		}

		return $this->code;
	}

	private function indentWithTabs($source) {
		// pending overwrite of spaces if sublime settings is set to psr2 or indent_with_space option
	}

	private function noneDocBlockMinorCleanUp($source) {
		// # addition: match formatting of laravel4.2/app/config/app.php

		$this->tkns = token_get_all($source);
		$this->code = '';
		$prevText = '';
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->getToken($token);
			$this->ptr = $index;
			switch ($id) {
				case T_COMMENT:
					if (substr($text, 0, 3) != '/**') {
						// flawed as it assumes indent_with_space == false
						$counts = substr_count($prevText, "\t");
						$replacement = "\n" . str_repeat("\t", $counts);
						$this->appendCode(preg_replace('/\n(\s+)/', $replacement, $text));
					}
					break;
				default:
					$this->appendCode($text);
			}
			$prevText = $text;
		}

		return $this->code;
	}

	private function tokensInLine($source) {
		$tokens = token_get_all($source);
		$processed = array();
		$seen = 1; // token_get_all always starts with 1
		$tokensLine = '';
		foreach ($tokens as $index => $token) {
			if (isset($token[2])) {
				$currLine = $token[2];
				if ($seen != $currLine) {
					$processed[($seen - 1)] = $tokensLine;
					// $tokensLine = token_name($token[0]) . "($index) ";
					$tokensLine = token_name($token[0]) . " ";
					$seen = $currLine;
				} else {
					// $tokensLine .= token_name($token[0]) . "($index) ";
					$tokensLine .= token_name($token[0]) . " ";
					// echo ($tokensLine);die;
				}
			} else {
				// $tokensLine .= $token . "($index) ";
				$tokensLine .= $token . " ";
			}
		}
		$processed[($seen - 1)] = $tokensLine; // consider the last line
		return $processed;
	}

	private function getConsecutiveFromArray($seenArray) {
		$temp = array();
		$seenBuckets = array();
		foreach ($seenArray as $j => $index) {
			// echo "$j => $index ";
			if ($j !== 0) {
				if (($index - 1) !== $seenArray[($j - 1)]) {
					// echo "diff with previous ";
					if (count($temp) > 1) {
						array_push($seenBuckets, $temp); //push to bucket
						// echo "pushed ";
					}
					$temp = array(); // clear temp
				}
			}
			array_push($temp, $index);
			if ($j == (count($seenArray) - 1) and (count($temp) > 1)) {
				// echo "reached end ";
				array_push($seenBuckets, $temp); //push to bucket
			}
			// echo PHP_EOL;
		}
		return $seenBuckets;
	}

	private function generateConsecutiveFromArray($seenArray, $source) {
		$lines = explode("\n", $source);
		// print_r($this->getConsecutiveFromArray($seenArray));
		foreach ($this->getConsecutiveFromArray($seenArray) as $bucket) {
			//get max position of =
			$maxPosition = 0;
			$eq = ' =';
			$toBeSorted = array();
			foreach ($bucket as $indexInBucket) {
				// echo "$indexInBucket(", strpos($lines[$indexInBucket], $eq), ') ';
				$position = strpos($lines[$indexInBucket], $eq);
				$maxPosition = max($maxPosition, $position);
				array_push($toBeSorted, $position);
			}
			// echo ' ', $maxPosition, PHP_EOL;

			// find alternative max if there's a further = position
			// ratio of highest : second highest > 1.5, else use the second highest
			// just run the top 5 to seek the laternative
			rsort($toBeSorted);
			// print_r($toBeSorted);
			for ($i = 1; $i <= 5; $i++) {
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
				// echo $lines[$indexInBucket], PHP_EOL;
			}
			// break;
		}
		// print_r($this->getConsecutiveFromArray($seenDoubleArrows));
		return implode("\n", $lines); //$source;
	}

	private function alignConsecutiveEqualSign($source) {
		// should align '= and '=>'
		$digFromHere = $this->tokensInLine($source);

		$seenEquals = array();
		$seenDoubleArrows = array();
		foreach ($digFromHere as $index => $line) {
			if (preg_match('/^T_VARIABLE T_WHITESPACE =.+;/', $line, $match)) {
				array_push($seenEquals, $index);
			}
			if (preg_match('/^T_CONSTANT_ENCAPSED_STRING T_WHITESPACE T_DOUBLE_ARROW /', $line, $match) and
				!strstr($line, 'T_ARRAY ( ')) {
				array_push($seenDoubleArrows, $index);
			}
		}
		// print_r($seenEquals);
		// print_r($seenDoubleArrows);
		$source = $this->generateConsecutiveFromArray($seenEquals, $source);
		$source = $this->generateConsecutiveFromArray($seenDoubleArrows, $source);

		return $source;
	}

	/**
	 * @codeCoverageIgnore
	 */
	public function getDescription() {
		return 'Applies Laravel Coding Style';
	}

	/**
	 * @codeCoverageIgnore
	 */
	public function getExample() {
		return <<<'EOT'
<?php namespace A;

class A {
	function b()
	{
		if($a)
		{
			noop();
		}
		else
		{
			noop();
		}
	}

}
?>
EOT;
	}
}
