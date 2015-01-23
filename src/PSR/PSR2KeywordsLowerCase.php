<?php
final class PSR2KeywordsLowerCase extends FormatterPass {
	public function candidate($source, $foundTokens) {
		return true;
	}
	public function format($source) {
		$this->tkns = token_get_all($source);
		$this->code = '';
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->getToken($token);
			$this->ptr = $index;
			if (
				T_WHITESPACE == $id ||
				T_VARIABLE == $id ||
				T_INLINE_HTML == $id ||
				T_COMMENT == $id ||
				T_DOC_COMMENT == $id ||
				T_CONSTANT_ENCAPSED_STRING == $id
			) {
				$this->appendCode($text);
				continue;
			}

			if (T_START_HEREDOC == $id) {
				$this->appendCode($text);
				$this->printUntil(ST_SEMI_COLON);
				continue;
			}
			if (ST_QUOTE == $id) {
				$this->appendCode($text);
				$this->printUntilTheEndOfString();
				continue;
			}
			$lc_text = strtolower($text);
			if (
				T_STRING !== $id ||
				(
					!$this->leftUsefulTokenIs([
						T_NS_SEPARATOR, T_AS, T_CLASS, T_EXTENDS, T_IMPLEMENTS, T_INSTANCEOF, T_INTERFACE, T_NEW, T_NS_SEPARATOR, T_PAAMAYIM_NEKUDOTAYIM, T_USE, T_TRAIT, T_INSTEADOF, T_CONST,
					]) &&
					!$this->rightUsefulTokenIs([
						T_NS_SEPARATOR, T_AS, T_CLASS, T_EXTENDS, T_IMPLEMENTS, T_INSTANCEOF, T_INTERFACE, T_NEW, T_NS_SEPARATOR, T_PAAMAYIM_NEKUDOTAYIM, T_USE, T_TRAIT, T_INSTEADOF, T_CONST,
					]) &&
					('true' === $lc_text || 'false' === $lc_text || 'null' === $lc_text)
				)
			) {
				$text = $lc_text;
			}
			$this->appendCode($text);
		}
		return $this->code;
	}
}