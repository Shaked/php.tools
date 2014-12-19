<?php
final class MergeCurlyCloseAndDoWhile extends FormatterPass {
	public function candidate($source, $foundTokens) {
		if (isset($foundTokens[T_WHILE])) {
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
				case T_WHILE:
					$str = $text;
					list($pt_id, $pt_text) = $this->getToken($this->leftToken());
					while (list($index, $token) = each($this->tkns)) {
						list($id, $text) = $this->getToken($token);
						$this->ptr = $index;
						$str .= $text;
						if (
							ST_CURLY_OPEN == $id ||
							ST_COLON == $id ||
							(ST_SEMI_COLON == $id && (ST_SEMI_COLON == $pt_id || ST_CURLY_OPEN == $pt_id || T_COMMENT == $pt_id || T_DOC_COMMENT == $pt_id))
						) {
							$this->appendCode($str);
							break;
						} elseif (ST_SEMI_COLON == $id && !(ST_SEMI_COLON == $pt_id || ST_CURLY_OPEN == $pt_id || T_COMMENT == $pt_id || T_DOC_COMMENT == $pt_id)) {
							$this->rtrimAndAppendCode($str);
							break;
						}
					}
					break;
				default:
					$this->appendCode($text);
					break;
			}
		}
		return $this->code;
	}
}
