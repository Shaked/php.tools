<?php
final class SpaceAroundExclamationMark extends FormatterPass {

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

}
