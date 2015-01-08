<?php
class PrettyPrintDocBlocks extends AdditionalPass {
	public function candidate($source, $foundTokens) {
		if (isset($foundTokens[T_DOC_COMMENT])) {
			return true;
		}

		return false;
	}
	public function format($source) {
		$this->tkns = token_get_all($source);
		$this->code = '';
		$touchedNamespace = false;
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->getToken($token);
			$this->ptr = $index;
			if (T_DOC_COMMENT == $id) {
				$text = $this->prettify($text);
			}
			$this->appendCode($text);
		}

		return $this->code;
	}

	private function prettify($docBlock) {
		$isUTF8 = $this->isUTF8($docBlock);

		$weights = [
			'@param' => 1,
			'@return' => 2,
		];
		$weightsLen = [
			'@param' => strlen('@param'),
			'@return' => strlen('@return'),
		];

		// Strip envelope
		$docBlock = trim(str_replace(['/**', '*/'], '', $docBlock));
		$lines = explode($this->newLine, $docBlock);
		$newText = '';
		foreach ($lines as $idx => $v) {
			$v = ltrim($v);
			if ('*' === substr($v, 0, 1)) {
				$v = trim(substr($v, 1));
			}
			$lines[$idx] = $v . ':' . $idx;
		}

		/**
		 * organize lines
		 */
		usort($lines, function ($a, $b) use ($weights, $weightsLen) {
			$weightA = 0;
			foreach ($weights as $pattern => $weight) {
				if (strtolower(substr($a, 0, $weightsLen[$pattern])) == $pattern) {
					$weightA = $weight;
					break;
				}
			}

			$weightB = 0;
			foreach ($weights as $pattern => $weight) {
				if (strtolower(substr($b, 0, $weightsLen[$pattern])) == $pattern) {
					$weightB = $weight;
					break;
				}
			}

			if ($weightA == $weightB) {
				$weightA = substr(strrchr($a, ":"), 1);
				$weightB = substr(strrchr($b, ":"), 1);
			}
			return $weightA - $weightB;
		});

		// Align tags
		$patterns = [
			'@param' => strlen('@param'),
			'@throws' => strlen('@throws'),
			'@return' => strlen('@return'),
			'@var' => strlen('@var'),
			'@type' => strlen('@type'),
		];
		$alignableIdx = [];
		$maxColumn = [];

		foreach ($lines as $idx => $line) {
			foreach ($patterns as $pattern => $len) {
				if (strtolower(substr($line, 0, $len)) == $pattern) {
					if ($isUTF8) {
						$line = utf8_decode($line);
					}
					$words = explode(' ', $line);
					foreach ($words as $i => $w) {
						$maxColumn[$i] = isset($maxColumn[$i]) ? max($maxColumn[$i], strlen($w)) : 0;
					}
				}
			}
		}

		foreach ($lines as $idx => $line) {
			foreach ($patterns as $pattern => $len) {
				if (strtolower(substr($line, 0, $len)) == $pattern) {
					if ($isUTF8) {
						$line = utf8_decode($line);
					}
					$parts = str_word_count($line, 2, '@$01234567890_-:' . "\x7f" . "\xff");
					reset($maxColumn);
					$currentLine = '';
					$pad = 0;
					foreach ($parts as $part) {
						$currentLine .= $isUTF8 ? utf8_encode($part) : $part;
						$pad += current($maxColumn) + 1;
						$currentLine = str_pad($currentLine, $pad);
						next($maxColumn);
					}
					$lines[$idx] = trim($currentLine);
				}
			}
		}

		$docBlock = '/**' . $this->newLine;
		foreach ($lines as $line) {
			$docBlock .= ' * ' . substr($line, 0, -2) . $this->newLine;
		}
		$docBlock .= ' */';

		return $docBlock;
	}

	private function isUTF8($usStr) {
		return (utf8_encode(utf8_decode($usStr)) == $usStr);
	}
	private function utf8Decode($usStr) {
		if ($this->isUTF8($usStr)) {
			return utf8_decode($usStr);
		}
		return $usStr;
	}

	/**
	 * @codeCoverageIgnore
	 */
	public function getDescription() {
		return 'Prettify Doc Blocks';
	}

	/**
	 * @codeCoverageIgnore
	 */
	public function getExample() {
		return <<<'EOT'
<?php
/**
 * some description.
 * @param array $b
 * @param LongTypeName $c
 */
function A(array $b, LongTypeName $c) {
}
?>

to
<?php
/**
 * some description.
 * @param array        $b
 * @param LongTypeName $c
 */
function A(array $b, LongTypeName $c) {
}
?>
EOT;
	}
}