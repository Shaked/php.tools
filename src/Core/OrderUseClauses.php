<?php
final class OrderUseClauses extends FormatterPass {
	const OPENER_PLACEHOLDER = "<?php /*\x2 ORDERBY \x3*/";
	public function candidate($source, $foundTokens) {
		if (isset($foundTokens[T_USE])) {
			return true;
		}

		return false;
	}
	private function singleNamespace($source) {
		$tokens = token_get_all($source);
		$useStack = [];
		$newTokens = [];
		$nextTokens = [];
		$touchedTUse = false;
		while (list(, $popToken) = each($tokens)) {
			$nextTokens[] = $popToken;
			while (($token = array_shift($nextTokens))) {
				list($id, $text) = $this->getToken($token);
				if (T_USE === $id) {
					$touchedTUse = true;
					$useItem = $text;
					while (list(, $token) = each($tokens)) {
						list($id, $text) = $this->getToken($token);
						if (ST_SEMI_COLON === $id) {
							$useItem .= $text;
							break;
						} elseif (ST_COMMA === $id) {
							$useItem .= ST_SEMI_COLON;
							$nextTokens[] = [T_WHITESPACE, $this->newLine];
							$nextTokens[] = [T_USE, 'use'];
							break;
						} else {
							$useItem .= $text;
						}
					}
					$useStack[] = trim($useItem);
					$token = new SurrogateToken();
				}
				if ($touchedTUse &&
					T_FINAL === $id ||
					T_ABSTRACT === $id ||
					T_INTERFACE === $id ||
					T_CLASS === $id ||
					T_FUNCTION === $id ||
					T_TRAIT === $id ||
					T_VARIABLE === $id ||
					T_WHILE === $id ||
					T_FOR === $id ||
					T_FOREACH === $id ||
					T_IF === $id
				) {
					if (sizeof($useStack) > 0) {
						$newTokens[] = $this->newLine;
						$newTokens[] = $this->newLine;
					}
					$newTokens[] = $token;
					break 2;
				} elseif ($touchedTUse && (T_DOC_COMMENT === $id || T_COMMENT === $id)) {
					if (sizeof($useStack) > 0) {
						$newTokens[] = $this->newLine;
					}
					$newTokens[] = $token;
					break 2;
				}
				$newTokens[] = $token;
			}
		}
		if (empty($useStack)) {
			return $source;
		}
		natcasesort($useStack);
		$aliasList = [];
		$aliasCount = [];
		foreach ($useStack as $use) {
			if (false !== stripos($use, ' as ')) {
				$alias = substr(strstr($use, ' as '), strlen(' as '), -1);
			} else {
				$alias = basename(str_replace('\\', '/', trim(substr($use, strlen('use'), -1))));
			}
			$alias = str_replace(ST_SEMI_COLON, '', strtolower($alias));
			$aliasList[$alias] = trim(strtolower($use));
			$aliasCount[$alias] = 0;
		}

		$return = '';
		foreach ($newTokens as $idx => $token) {
			if ($token instanceof SurrogateToken) {
				$return .= array_shift($useStack);
			} elseif (T_WHITESPACE == $token[0] && isset($newTokens[$idx - 1], $newTokens[$idx + 1]) && $newTokens[$idx - 1] instanceof SurrogateToken && $newTokens[$idx + 1] instanceof SurrogateToken) {
				$return .= $this->newLine;
				continue;
			} else {
				list($id, $text) = $this->getToken($token);
				$lower_text = strtolower($text);
				if (T_STRING === $id && isset($aliasList[$lower_text])) {
					++$aliasCount[$lower_text];
				} elseif (T_DOC_COMMENT === $id) {
					foreach ($aliasList as $alias => $use) {
						if (false !== stripos($text, $alias)) {
							++$aliasCount[$alias];
						}
					}
				}
				$return .= $text;
			}
		}

		while (list(, $token) = each($tokens)) {
			list($id, $text) = $this->getToken($token);
			$lower_text = strtolower($text);
			if (T_STRING === $id && isset($aliasList[$lower_text])) {
				++$aliasCount[$lower_text];
			} elseif (T_DOC_COMMENT === $id) {
				foreach ($aliasList as $alias => $use) {
					if (false !== stripos($text, $alias)) {
						++$aliasCount[$alias];
					}
				}
			}
			$return .= $text;
		}

		$unusedImport = array_keys(
			array_filter(
				$aliasCount, function ($v) {
					return 0 === $v;
				}
			)
		);
		foreach ($unusedImport as $v) {
			$return = str_ireplace($aliasList[$v] . $this->newLine, null, $return);
		}

		return $return;
	}
	public function format($source = '') {
		$namespaceCount = 0;
		$tokens = token_get_all($source);
		$touchedTUse = false;
		while (list(, $token) = each($tokens)) {
			list($id, $text) = $this->getToken($token);
			if (T_USE === $id) {
				$touchedTUse = true;
			}
			if (T_NAMESPACE == $id) {
				++$namespaceCount;
			}
		}
		if ($namespaceCount <= 1 && $touchedTUse) {
			return $this->singleNamespace($source);
		}

		$return = '';
		reset($tokens);
		while (list($index, $token) = each($tokens)) {
			list($id, $text) = $this->getToken($token);
			$this->ptr = $index;
			switch ($id) {
				case T_NAMESPACE:
					$return .= $text;
					$touchedTUse = false;
					while (list($index, $token) = each($tokens)) {
						list($id, $text) = $this->getToken($token);
						$this->ptr = $index;
						$return .= $text;
						if (ST_CURLY_OPEN == $id || ST_SEMI_COLON == $id) {
							break;
						}
					}
					if (ST_CURLY_OPEN === $id) {
						$namespaceBlock = '';
						$curlyCount = 1;
						while (list($index, $token) = each($tokens)) {
							list($id, $text) = $this->getToken($token);
							$this->ptr = $index;
							$namespaceBlock .= $text;

							if (T_USE === $id) {
								$touchedTUse = true;
							}

							if (ST_CURLY_OPEN == $id) {
								++$curlyCount;
							} elseif (ST_CURLY_CLOSE == $id) {
								--$curlyCount;
							}

							if (0 == $curlyCount) {
								break;
							}
						}
					} elseif (ST_SEMI_COLON === $id) {
						$namespaceBlock = '';
						while (list($index, $token) = each($tokens)) {
							list($id, $text) = $this->getToken($token);
							$this->ptr = $index;

							if (T_USE === $id) {
								$touchedTUse = true;
							}

							if (T_NAMESPACE == $id) {
								prev($tokens);
								break;
							}

							$namespaceBlock .= $text;
						}
					}

					$return .= str_replace(
						self::OPENER_PLACEHOLDER,
						'',
						$this->singleNamespace(self::OPENER_PLACEHOLDER . $namespaceBlock)
					);

					break;
				default:
					$return .= $text;
			}
		}

		return $return;
	}
}
