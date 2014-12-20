<?php
final class AddMissingCurlyBraces extends FormatterPass {
	public function candidate($source, $foundTokens) {
		return true;
	}
	public function format($source) {
		list($tmp, $changed) = $this->addBraces($source);
		while ($changed) {
			list($source, $changed) = $this->addBraces($tmp);
			if ($source === $tmp) {
				break;
			}
			$tmp = $source;
		}
		return $source;
	}
	private function addBraces($source) {
		$this->tkns = token_get_all($source);
		$this->code = '';
		$this->useCache = true;
		$changed = false;
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->getToken($token);
			$this->ptr = $index;
			$this->cache = [];
			switch ($id) {
				case T_WHILE:
				case T_FOREACH:
				case T_FOR:
					$this->appendCode($text);
					$parenCount = null;
					while (list($index, $token) = each($this->tkns)) {
						list($id, $text) = $this->getToken($token);
						$this->ptr = $index;
						$this->cache = [];
						if (ST_PARENTHESES_OPEN === $id) {
							++$parenCount;
						} elseif (ST_PARENTHESES_CLOSE === $id) {
							--$parenCount;
						}
						$this->appendCode($text);
						if (0 === $parenCount && !$this->rightTokenIs([T_COMMENT, T_DOC_COMMENT])) {
							break;
						}
					}
					if (!$this->rightTokenIs([ST_CURLY_OPEN, ST_COLON, ST_SEMI_COLON])) {
						$whileInNextToken = $this->rightTokenIs([T_WHILE, T_DO]);
						$ignoreCount = 0;
						if (!$this->leftTokenIs([T_COMMENT, T_DOC_COMMENT])) {
							$this->rtrimAndAppendCode($this->newLine . '{');
						}
						while (list($index, $token) = each($this->tkns)) {
							list($id, $text) = $this->getToken($token);
							$this->ptr = $index;
							$this->cache = [];

							if (ST_QUOTE == $id) {
								$this->appendCode($text);
								$this->printUntilTheEndOfString();
								continue;
							}

							if (ST_PARENTHESES_OPEN === $id || ST_CURLY_OPEN === $id || ST_BRACKET_OPEN === $id) {
								++$ignoreCount;
							} elseif (ST_PARENTHESES_CLOSE === $id || ST_CURLY_CLOSE === $id || ST_BRACKET_CLOSE === $id) {
								--$ignoreCount;
							}
							$this->appendCode($text);
							if (ST_SEMI_COLON != $id && $this->rightTokenIs(T_CLOSE_TAG)) {
								$this->appendCode(ST_SEMI_COLON);
								break;
							}
							if (T_INLINE_HTML == $id && !$this->rightTokenIs(T_OPEN_TAG)) {
								$this->appendCode('<?php');
							}
							if ($ignoreCount <= 0 && !($this->rightTokenIs([ST_CURLY_CLOSE, ST_SEMI_COLON, T_OBJECT_OPERATOR, ST_PARENTHESES_OPEN, ST_EQUAL]) || ($whileInNextToken && $this->rightTokenIs([T_WHILE]))) && (ST_CURLY_CLOSE === $id || ST_SEMI_COLON === $id || T_ELSE === $id || T_ELSEIF === $id)) {
								break;
							}
						}
						$this->appendCode($this->getCrlfIndent() . '}' . $this->getCrlfIndent());
						$changed = true;
						break 2;
					}
					break;
				case T_IF:
				case T_ELSEIF:
					$this->appendCode($text);
					$parenCount = null;
					while (list($index, $token) = each($this->tkns)) {
						list($id, $text) = $this->getToken($token);
						$this->ptr = $index;
						$this->cache = [];
						if (ST_PARENTHESES_OPEN === $id) {
							++$parenCount;
						} elseif (ST_PARENTHESES_CLOSE === $id) {
							--$parenCount;
						}
						$this->appendCode($text);
						if (0 === $parenCount && !$this->rightTokenIs([T_COMMENT, T_DOC_COMMENT])) {
							break;
						}
					}
					if (!$this->rightTokenIs([ST_CURLY_OPEN, ST_COLON])) {
						$whileInNextToken = $this->rightTokenIs([T_WHILE, T_DO]);
						$ignoreCount = 0;
						if (!$this->leftTokenIs([T_COMMENT, T_DOC_COMMENT])) {
							$this->rtrimAndAppendCode($this->newLine . '{');
						}
						while (list($index, $token) = each($this->tkns)) {
							list($id, $text) = $this->getToken($token);
							$this->ptr = $index;
							$this->cache = [];

							if (ST_QUOTE == $id) {
								$this->appendCode($text);
								$this->printUntilTheEndOfString();
								continue;
							}
							if (ST_PARENTHESES_OPEN === $id || ST_CURLY_OPEN === $id || ST_BRACKET_OPEN === $id) {
								++$ignoreCount;
							} elseif (ST_PARENTHESES_CLOSE === $id || ST_CURLY_CLOSE === $id || ST_BRACKET_CLOSE === $id) {
								--$ignoreCount;
							}
							$this->appendCode($text);
							if (T_INLINE_HTML == $id && !$this->rightTokenIs(T_OPEN_TAG)) {
								$this->appendCode('<?php');
							}
							if ($ignoreCount <= 0 && !($this->rightTokenIs([ST_CURLY_CLOSE, ST_SEMI_COLON, T_OBJECT_OPERATOR, ST_PARENTHESES_OPEN]) || ($whileInNextToken && $this->rightTokenIs([T_WHILE]))) && (ST_CURLY_CLOSE === $id || ST_SEMI_COLON === $id || T_ELSE === $id || T_ELSEIF === $id)) {
								break;
							}
						}
						$this->appendCode($this->getCrlfIndent() . '}' . $this->getCrlfIndent());
						$changed = true;
						break 2;
					}
					break;
				case T_ELSE:
					$this->appendCode($text);
					if (!$this->rightTokenIs([ST_CURLY_OPEN, ST_COLON, T_IF])) {
						$whileInNextToken = $this->rightTokenIs([T_WHILE, T_DO]);
						$ignoreCount = 0;
						$this->rtrimAndAppendCode('{');
						while (list($index, $token) = each($this->tkns)) {
							list($id, $text) = $this->getToken($token);
							$this->ptr = $index;
							$this->cache = [];

							if (ST_QUOTE == $id) {
								$this->appendCode($text);
								$this->printUntilTheEndOfString();
								continue;
							}

							if (ST_PARENTHESES_OPEN === $id || ST_CURLY_OPEN === $id || ST_BRACKET_OPEN === $id) {
								++$ignoreCount;
							} elseif (ST_PARENTHESES_CLOSE === $id || ST_CURLY_CLOSE === $id || ST_BRACKET_CLOSE === $id) {
								--$ignoreCount;
							}
							$this->appendCode($text);
							if (T_INLINE_HTML == $id && !$this->rightTokenIs(T_OPEN_TAG)) {
								$this->appendCode('<?php');
							}
							if ($ignoreCount <= 0 && !($this->rightTokenIs([ST_CURLY_CLOSE, ST_SEMI_COLON, T_OBJECT_OPERATOR, ST_PARENTHESES_OPEN]) || ($whileInNextToken && $this->rightTokenIs([T_WHILE]))) && (ST_CURLY_CLOSE === $id || ST_SEMI_COLON === $id || T_ELSE === $id || T_ELSEIF === $id)) {
								break;
							}
						}
						$this->appendCode($this->getCrlfIndent() . '}' . $this->getCrlfIndent());
						$changed = true;
						break 2;
					}
					break;
				default:
					$this->appendCode($text);
					break;
			}
		}
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->getToken($token);
			$this->ptr = $index;
			$this->appendCode($text);
		}

		return [$this->code, $changed];
	}
}
