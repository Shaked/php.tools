<?php
class TranslateNativeCalls extends FormatterPass {

	protected static $aliasList = [
		'sprintf' => 'fmt::Sprintf',
	];

	private $touchedEmptyNs = false;

	public function candidate($source, $foundTokens) {
		$this->tkns = token_get_all($source);
		$this->code = '';

		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->getToken($token);
			$this->ptr = $index;
			$this->checkIfEmptyNS($id);
			switch ($id) {
			case T_STRING:
			case T_EXIT:
				if (isset(static::$aliasList[strtolower($text)])) {
					prev($this->tkns);
					return true;
				}
			}
			$this->appendCode($text);
		}
		return false;
	}

	public function format($source) {
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->getToken($token);
			$this->ptr = $index;
			$this->checkIfEmptyNS($id);
			if (
				(T_STRING == $id || T_EXIT == $id) &&
				isset(static::$aliasList[strtolower($text)]) &&
				(
					!(
						$this->leftUsefulTokenIs([
							T_NEW,
							T_NS_SEPARATOR,
							T_STRING,
							T_DOUBLE_COLON,
							T_OBJECT_OPERATOR,
							T_FUNCTION,
						]) ||
						$this->rightUsefulTokenIs([
							T_NS_SEPARATOR,
							T_DOUBLE_COLON,
						])
					)
					||
					(
						$this->leftUsefulTokenIs([
							T_NS_SEPARATOR,
						]) &&
						$this->touchedEmptyNs
					)
				)
			) {
				$this->appendCode(static::$aliasList[strtolower($text)]);
				continue;
			}
			$this->appendCode($text);
		}

		return $this->code;
	}

	private function checkIfEmptyNS($id) {
		if (T_NS_SEPARATOR != $id) {
			return;
		}

		$this->touchedEmptyNs = !$this->leftUsefulTokenIs(T_STRING);
	}

}
