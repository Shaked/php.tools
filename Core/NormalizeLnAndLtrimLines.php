<?php
final class NormalizeLnAndLtrimLines extends FormatterPass {
	public function candidate($source, $foundTokens) {
		return true;
	}
	public function format($source) {
		$source = str_replace(["\r\n", "\n\r", "\r", "\n"], $this->newLine, $source);
		$source = preg_replace('/\h+$/mu', '', $source);

		$this->tkns = token_get_all($source);
		$this->code = '';
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->getToken($token);
			$this->ptr = $index;
			switch ($id) {
				case ST_QUOTE:
					$this->appendCode($text);
					$this->printUntilTheEndOfString();
					break;
				case T_START_HEREDOC:
					$this->appendCode($text);
					$this->printUntil(T_END_HEREDOC);
					break;
				case T_COMMENT:
				case T_DOC_COMMENT:
					list($prev_id, $prev_text) = $this->inspectToken(-1);

					if (T_WHITESPACE === $prev_id && ("\n" === $prev_text || "\n\n" == substr($prev_text, -2, 2))) {
						$this->appendCode(LeftAlignComment::NON_INDENTABLE_COMMENT);
					}

					$lines = explode($this->newLine, $text);
					$new_text = '';
					foreach ($lines as $v) {
						$v = ltrim($v);
						if ('*' === substr($v, 0, 1)) {
							$v = ' ' . $v;
						}
						$new_text .= $this->newLine . $v;
					}

					$this->appendCode(ltrim($new_text));
					break;
				case T_CONSTANT_ENCAPSED_STRING:
					$this->appendCode($text);
					break;
				default:
					if ($this->hasLn($text)) {
						$trailing_new_line = $this->substrCountTrailing($text, $this->newLine);
						if ($trailing_new_line > 0) {
							$text = trim($text) . str_repeat($this->newLine, $trailing_new_line);
						}
					}
					$this->appendCode($text);
					break;
			}
		}

		return $this->code;
	}
}
