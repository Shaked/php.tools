<?php
final class PSR2SingleEmptyLineAndStripClosingTag extends FormatterPass {
	public function format($source) {
		$this->tkns = token_get_all($source);
		$token_count = count($this->tkns) - 1;
		while (list($index, $token) = each($this->tkns)) {
			list($id, ) = $this->get_token($token);
			$this->ptr = $index;
			if (T_INLINE_HTML == $id && $this->ptr != $token_count) {
				return $source;
			}
		}

		list($id, $text) = $this->get_token(end($this->tkns));
		$this->ptr = key($this->tkns);

		if (T_CLOSE_TAG == $id) {
			unset($this->tkns[$this->ptr]);
		} elseif (T_INLINE_HTML == $id && '' == trim($text) && $this->left_token_is(T_CLOSE_TAG)) {
			unset($this->tkns[$this->ptr]);
			$ptr = $this->left_token_idx([]);
			unset($this->tkns[$ptr]);
		}

		return rtrim($this->render()) . $this->new_line;
	}
}
