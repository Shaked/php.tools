<?php
final class ReindentColonBlocks extends FormatterPass {
	public function candidate($source, $foundTokens) {
		if (isset($foundTokens[ST_COLON]) && (isset($foundTokens[T_DEFAULT]) || isset($foundTokens[T_CASE]) || isset($foundTokens[T_SWITCH]))) {
			return true;
		}

		return false;
	}

	public function format($source) {
		$this->tkns = token_get_all($source);
		$this->useCache = true;
		$this->code = '';

		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->getToken($token);
			if (T_DEFAULT == $id || T_CASE == $id || T_SWITCH == $id) {
				break;
			}
			$this->appendCode($text);
		}

		prev($this->tkns);
		$switchLevel = 0;
		$switchCurlyCount = [];
		$switchCurlyCount[$switchLevel] = 0;
		$touchedColon = false;
		$touchedVariableObjOpDollar = false;

		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->getToken($token);
			$this->ptr = $index;
			$this->cache = [];
			switch ($id) {
				case T_VARIABLE:
				case T_OBJECT_OPERATOR:
				case ST_DOLLAR:
					$touchedVariableObjOpDollar = true;
					$this->appendCode($text);
					break;

				case ST_QUOTE:
					$this->appendCode($text);
					$this->printUntilTheEndOfString();
					break;

				case T_SWITCH:
					++$switchLevel;
					$switchCurlyCount[$switchLevel] = 0;
					$touchedColon = false;
					$this->appendCode($text);
					break;

				case ST_CURLY_OPEN:
					$this->appendCode($text);
					if ($touchedVariableObjOpDollar) {
						$touchedVariableObjOpDollar = false;
						$this->printCurlyBlock();
						break;
					}
					++$switchCurlyCount[$switchLevel];
					break;

				case ST_CURLY_CLOSE:
					--$switchCurlyCount[$switchLevel];
					if (0 === $switchCurlyCount[$switchLevel] && $switchLevel > 0) {
						--$switchLevel;
					}
					$this->appendCode($this->getIndent($switchLevel) . $text);
					break;

				case T_DEFAULT:
				case T_CASE:
					$touchedColon = false;
					$this->appendCode($text);
					break;

				case ST_COLON:
					$touchedColon = true;
					$this->appendCode($text);
					break;

				default:
					$touchedVariableObjOpDollar = false;
					$hasLn = $this->hasLn($text);
					if ($hasLn) {
						$isNextCaseOrDefault = $this->rightUsefulTokenIs([T_CASE, T_DEFAULT]);
						if ($touchedColon && T_COMMENT == $id && $isNextCaseOrDefault) {
							$this->appendCode($text);
							break;
						} elseif ($touchedColon && T_COMMENT == $id && !$isNextCaseOrDefault) {
							$this->appendCode($this->getIndent($switchLevel) . $text);
							if (!$this->rightTokenIs([ST_CURLY_CLOSE, T_COMMENT, T_DOC_COMMENT])) {
								$this->appendCode($this->getIndent($switchLevel));
							}
							break;
						} elseif (!$isNextCaseOrDefault && !$this->rightTokenIs([ST_CURLY_CLOSE, T_COMMENT, T_DOC_COMMENT])) {
							$this->appendCode($text . $this->getIndent($switchLevel));
							break;
						}
					}
					$this->appendCode($text);
					break;
			}
		}
		return $this->code;
	}
}