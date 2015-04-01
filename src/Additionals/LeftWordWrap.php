<?php
final class LeftWordWrap extends AdditionalPass {
	private static $length = 80;
	private static $tabSizeInSpace = 8;

	public function candidate($source, $foundTokens) {
		return true;
	}

	public function format($source) {
		$this->tkns = token_get_all($source);
		$this->code = '';

		$currentLineLength = 0;
		$detectedTab = false;
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->getToken($token);
			$this->ptr = $index;

			$originalText = $text;
			if (T_WHITESPACE == $id) {
				if (!$detectedTab && false !== strpos($text, "\t")) {
					$detectedTab = true;
				}
				$text = str_replace(
					$this->indentChar,
					str_repeat(' ', self::$tabSizeInSpace),
					$text
				);
				$textLen = strlen($text);
			} else {
				$textLen = strlen($text);
			}

			$currentLineLength += $textLen;
			if ($this->hasLn($text)) {
				$currentLineLength = $textLen - strrpos($text, $this->newLine);
			}

			if ($currentLineLength > self::$length) {
				$currentLineLength = $textLen - strrpos($text, $this->newLine);
				$this->appendCode($this->newLine);
			}

			$this->appendCode($originalText);
		}

		return $this->code;
	}

	/**
	 * @codeCoverageIgnore
	 */
	public function getDescription() {
		return 'Word wrap at 80 columns - left justify.';
	}

	/**
	 * @codeCoverageIgnore
	 */
	public function getExample() {
		return '';
	}
}