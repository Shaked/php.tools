<?php
class SpaceAroundExclaimationMark extends AdditionalPass {
	public function candidate($source, $foundTokens) {
		if (isset($foundTokens[ST_EXCLAMATION])) {
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
				case ST_EXCLAMATION:
					$this->appendCode(" $text ");
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
		return 'Add spaces around !';
	}

	/**
	 * @codeCoverageIgnore
	 */
	public function getExample() {
		return <<<'EOT'
<?php
if (!is_null($this->layout))
?>
to
<?php
if ( ! is_null($this->layout))
?>
EOT;
	}
}
