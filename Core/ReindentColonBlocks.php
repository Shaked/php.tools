<?php
final class ReindentColonBlocks extends FormatterPass {
	public function candidate($source, $foundTokens) {
		return true;
	}
	public function format($source) {
		$this->tkns = token_get_all($source);
		$this->useCache = true;
		$this->code = '';

		$found_colon = false;
		foreach ($this->tkns as $token) {
			list($id, $text) = $this->getToken($token);
			if (T_DEFAULT == $id || T_CASE == $id || T_SWITCH == $id) {
				$found_colon = true;
				break;
			}
			$this->appendCode($text);
		}
		if (!$found_colon) {
			return $source;
		}

		prev($this->tkns);
		$switch_level = 0;
		$switch_curly_count = [];
		$switch_curly_count[$switch_level] = 0;
		$is_next_case_or_default = false;
		$touched_colon = false;
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->getToken($token);
			$this->ptr = $index;
			$this->cache = [];
			switch ($id) {
				case ST_QUOTE:
					$this->appendCode($text);
					$this->printUntilTheEndOfString();
					break;

				case T_SWITCH:
					++$switch_level;
					$switch_curly_count[$switch_level] = 0;
					$touched_colon = false;
					$this->appendCode($text);
					break;

				case ST_CURLY_OPEN:
					$this->appendCode($text);
					if ($this->leftTokenIs([T_VARIABLE, T_OBJECT_OPERATOR, ST_DOLLAR])) {
						$this->printCurlyBlock();
						break;
					}
					++$switch_curly_count[$switch_level];
					break;

				case ST_CURLY_CLOSE:
					--$switch_curly_count[$switch_level];
					if (0 === $switch_curly_count[$switch_level] && $switch_level > 0) {
						--$switch_level;
					}
					$this->appendCode($this->getIndent($switch_level) . $text);
					break;

				case T_DEFAULT:
				case T_CASE:
					$touched_colon = false;
					$this->appendCode($text);
					break;

				case ST_COLON:
					$touched_colon = true;
					$this->appendCode($text);
					break;

				default:
					$has_ln = $this->hasLn($text);
					if ($has_ln) {
						$is_next_case_or_default = $this->rightUsefulTokenIs([T_CASE, T_DEFAULT]);
						if ($touched_colon && T_COMMENT == $id && $is_next_case_or_default) {
							$this->appendCode($text);
						} elseif ($touched_colon && T_COMMENT == $id && !$is_next_case_or_default) {
							$this->appendCode($this->getIndent($switch_level) . $text);
							if (!$this->rightTokenIs([ST_CURLY_CLOSE, T_COMMENT, T_DOC_COMMENT])) {
								$this->appendCode($this->getIndent($switch_level));
							}
						} elseif (!$is_next_case_or_default && !$this->rightTokenIs([ST_CURLY_CLOSE, T_COMMENT, T_DOC_COMMENT])) {
							$this->appendCode($text . $this->getIndent($switch_level));
						} else {
							$this->appendCode($text);
						}
					} else {
						$this->appendCode($text);
					}
					break;
			}
		}
		return $this->code;
	}
}