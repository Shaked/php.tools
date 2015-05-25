<?php
final class AlignPHPCode extends AdditionalPass {
	public function candidate($source, $foundTokens) {
		if (isset($foundTokens[T_INLINE_HTML])) {
			return true;
		}

		return false;
	}

	public function format($source) {
		$this->tkns = token_get_all($source);
		$this->code = '';
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->getToken($token);
			$this->ptr = $index;
			switch ($id) {
				case T_OPEN_TAG:
					list(, $prevText) = $this->getToken($this->leftToken());

					$prevSpace = substr(strrchr($prevText, $this->newLine), 1);
					$skipPadLeft = false;
					if (rtrim($prevSpace) == $prevSpace) {
						$skipPadLeft = true;
					}
					$prevSpace = preg_replace('/[^\s\t]/', ' ', $prevSpace);

					$stack = $text;
					while (list($index, $token) = each($this->tkns)) {
						list($id, $text) = $this->getToken($token);
						$this->ptr = $index;
						$stack .= $text;

						if (T_CLOSE_TAG == $id) {
							break;
						}
					}

					$tmp = explode($this->newLine, $stack);
					$lastLine = sizeof($tmp) - 2;
					foreach ($tmp as $idx => $line) {
						$before = $prevSpace;
						if ('' === trim($line)) {
							continue;
						}
						$indent = '';
						if (0 != $idx && $idx < $lastLine) {
							$indent = $this->indentChar;
						}
						if ($skipPadLeft) {
							$before = '';
							$skipPadLeft = false;
						}
						$tmp[$idx] = $before . $indent . $line;
					}

					$stack = implode($this->newLine, $tmp);

					$this->code = rtrim($this->code, " \t");
					$this->appendCode($stack);
					break;

				default:
					$this->appendCode($text);
					break;
			}
		}

		return $this->code;
	}

	/**
	 * @codeCoverageIgnore
	 */
	public function getDescription() {
		return 'Align PHP code within HTML block.';
	}

	/**
	 * @codeCoverageIgnore
	 */
	public function getExample() {
		return <<<'EOT'
<div>
	<?php
		echo $a;
	?>
</div>
EOT;
	}
}
