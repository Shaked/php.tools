<?php
final class SmartLnAfterCurlyOpen extends AdditionalPass {
	public function candidate($source, $found_tokens) {
		if (isset($found_tokens[ST_CURLY_OPEN])) {
			return true;
		}

		return false;
	}
	public function format($source) {
		$this->tkns = token_get_all($source);
		$this->code = '';
		$curly_count = 0;
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->getToken($token);
			$this->ptr = $index;
			switch ($id) {
				case ST_CURLY_OPEN:
					$this->appendCode($text);
					$curly_count = 1;
					$stack = '';
					$found_line_break = false;
					$has_ln_after = $this->hasLnAfter();
					while (list($index, $token) = each($this->tkns)) {
						list($id, $text) = $this->getToken($token);
						$this->ptr = $index;
						$stack .= $text;
						if (T_START_HEREDOC == $id) {
							$stack .= $this->walkAndAccumulateUntil($this->tkns, T_END_HEREDOC);
							continue;
						}
						if (ST_QUOTE == $id) {
							$stack .= $this->walkAndAccumulateUntil($this->tkns, ST_QUOTE);
							continue;
						}
						if (ST_CURLY_OPEN == $id) {
							++$curly_count;
						}
						if (ST_CURLY_CLOSE == $id) {
							--$curly_count;
						}
						if (T_WHITESPACE === $id && $this->hasLn($text)) {
							$found_line_break = true;
							break;
						}
						if (0 == $curly_count) {
							break;
						}
					}
					if ($found_line_break && !$has_ln_after) {
						$this->appendCode($this->new_line);
					}
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
	public function get_description() {
		return 'Add line break when implicit curly block is added.';
	}

	/**
	 * @codeCoverageIgnore
	 */
	public function get_example() {
		return <<<'EOT'
<?php
if($a) echo array();
?>
to
<?php
if($a) {
	echo array();
}
?>
EOT;
	}
}
