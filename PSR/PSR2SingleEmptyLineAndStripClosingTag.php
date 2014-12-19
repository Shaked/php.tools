<?php
final class PSR2SingleEmptyLineAndStripClosingTag extends FormatterPass {
	public function candidate($source, $found_tokens) {
		return true;
	}
	public function format($source) {
		$this->tkns = token_get_all($source);
		$token_count = count($this->tkns) - 1;
		while (list($index, $token) = each($this->tkns)) {
			list($id, ) = $this->getToken($token);
			$this->ptr = $index;
			if (T_INLINE_HTML == $id && $this->ptr != $token_count) {
				return $source;
			}
		}

		list($id, $text) = $this->getToken(end($this->tkns));
		$this->ptr = key($this->tkns);

		if (T_CLOSE_TAG == $id) {
			unset($this->tkns[$this->ptr]);
		} elseif (T_INLINE_HTML == $id && '' == trim($text) && $this->leftTokenIs(T_CLOSE_TAG)) {
			unset($this->tkns[$this->ptr]);
			$ptr = $this->leftTokenIdx([]);
			unset($this->tkns[$ptr]);
		}

		return rtrim($this->render()) . $this->new_line;
	}
}
