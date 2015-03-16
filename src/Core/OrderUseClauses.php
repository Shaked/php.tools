<?php
final class OrderUseClauses extends FormatterPass {
	const SPLIT_COMMA = true;
	const REMOVE_UNUSED = true;
	const STRIP_BLANK_LINES = true;
	const BLANK_LINE_AFTER_USE_BLOCK = true;
	const TRAIT_BLOCK_OPEN = 'TRAIT_BLOCK_OPEN';
	const OPENER_PLACEHOLDER = "<?php /*\x2 ORDERBY \x3*/";

	private $sortFunction = null;

	public function __construct(callable $sortFunction = null) {
		$this->sortFunction = $sortFunction;
		if (null == $sortFunction) {
			$this->sortFunction = function ($useStack) {
				natcasesort($useStack);
				return $useStack;
			};
		}
	}

	public function candidate($source, $foundTokens) {
		if (isset($foundTokens[T_USE])) {
			return true;
		}

		return false;
	}

	private function sortUseClauses($source, $splitComma, $removeUnused, $stripBlankLines, $blanklineAfterUseBlock) {
		$tokens = token_get_all($source);
		$newTokens = [];
		$useStack = [0 => []];
		$foundComma = false;
		$touchedTUse = false;

		$groupCount = 0;

		$stopTokens = [ST_SEMI_COLON, ST_CURLY_OPEN];
		if ($splitComma) {
			$stopTokens[] = ST_COMMA;
		}

		while (list($index, $token) = each($tokens)) {
			list($id, $text) = $this->getToken($token);

			if ((T_TRAIT === $id || T_CLASS === $id) && !$this->leftTokenSubsetIsAtIdx($tokens, $index, [T_DOUBLE_COLON], $this->ignoreFutileTokens)) {
				$newTokens[] = $token;
				while (list($index, $token) = each($tokens)) {
					list($id, $text) = $this->getToken($token);
					$newTokens[] = $token;
				}
				break;
			}

			if (
				!$stripBlankLines &&
				(
					T_WHITESPACE === $id
					||
					(T_COMMENT === $id && '/' == $text[2])
				) && substr_count($text, $this->newLine) >= 2
			) {
				$groupCount++;
				$useStack[$groupCount] = [];
				$newTokens[] = $token;
				continue;
			}

			if (T_USE === $id && $this->rightTokenSubsetIsAtIdx($tokens, $index, [ST_PARENTHESES_OPEN], $this->ignoreFutileTokens)) {
				$newTokens[] = $token;
				continue;
			}

			if (T_USE === $id || $foundComma) {
				list($useTokens, $foundToken) = $this->walkAndAccumulateStopAtAny($tokens, $stopTokens);

				if (ST_SEMI_COLON == $foundToken) {
					$touchedTUse = true;
					$useStack[$groupCount][] = 'use ' . ltrim($useTokens) . ';';
					$newTokens[] = new SurrogateToken();
					next($tokens);

					$foundComma = false;

				} elseif ($splitComma && ST_COMMA == $foundToken) {
					$touchedTUse = true;
					$useStack[$groupCount][] = 'use ' . ltrim($useTokens) . ';';
					$newTokens[] = new SurrogateToken();
					$newTokens[] = [T_WHITESPACE, $this->newLine . $this->newLine];

					$foundComma = true;

				} elseif (ST_CURLY_OPEN == $foundToken) {
					$newTokens[] = $foundToken;

					$foundComma = false;

				}
				continue;
			}

			$newTokens[] = $token;
		}

		if (empty($useStack[0])) {
			return $source;
		}
		foreach ($useStack as $group => $useClauses) {
			$useStack[$group] = call_user_func($this->sortFunction, $useClauses);
		}
		$useStack = call_user_func_array('array_merge', $useStack);

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
				if ($blanklineAfterUseBlock && !isset($useStack[0])) {
					$return .= $this->newLine;
				}
			} elseif (T_WHITESPACE == $token[0] && isset($newTokens[$idx - 1], $newTokens[$idx + 1]) && $newTokens[$idx - 1] instanceof SurrogateToken && $newTokens[$idx + 1] instanceof SurrogateToken) {
				if ($stripBlankLines) {
					$return .= $this->newLine;
				} else {
					$return .= $token[1];
				}
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

		$unusedImport = [];
		if ($removeUnused) {
			$unusedImport = array_keys(
				array_filter(
					$aliasCount, function ($v) {
						return 0 === $v;
					}
				)
			);
		}

		foreach ($unusedImport as $v) {
			$return = str_ireplace($aliasList[$v] . $this->newLine, null, $return);
		}

		return $return;
	}
	public function format($source = '') {
		$source = $this->sortWithinNamespaces($source);
		$source = $this->sortWithinClasses($source);

		return $source;
	}

	private function sortWithinClasses($source) {
		$tokens = token_get_all($source);
		$return = '';
		while (list($index, $token) = each($tokens)) {
			list($id, $text) = $this->getToken($token);
			$this->ptr = $index;
			switch ($id) {
				case T_TRAIT:
				case T_CLASS:
					$return .= $text;
					$touchedTUse = false;
					$return .= $this->walkAndAccumulateStopAt($tokens, ST_CURLY_OPEN);

					$classBlock = '';
					$curlyCount = 0;
					while (list($index, $token) = each($tokens)) {
						list($id, $text) = $this->getToken($token);
						$this->ptr = $index;
						$classBlock .= $text;

						if (T_USE === $id) {
							$touchedTUse = true;
						}

						if (ST_CURLY_OPEN == $id || T_CURLY_OPEN == $id || T_DOLLAR_OPEN_CURLY_BRACES == $id) {
							++$curlyCount;
						} elseif (ST_CURLY_CLOSE == $id) {
							--$curlyCount;
						}

						if (0 == $curlyCount) {
							break;
						}
					}

					if (!$touchedTUse) {
						$return .= $classBlock;
						break;
					}

					$return .= str_replace(
						self::OPENER_PLACEHOLDER,
						'',
						$this->sortUseClauses(self::OPENER_PLACEHOLDER . $classBlock, !self::SPLIT_COMMA, !self::REMOVE_UNUSED, !self::STRIP_BLANK_LINES, !self::BLANK_LINE_AFTER_USE_BLOCK)
					);

					break;
				default:
					$return .= $text;
			}
		}

		return $return;
	}

	private function sortWithinNamespaces($source) {
		$classRelatedCount = 0;
		$namespaceCount = 0;
		$tokens = token_get_all($source);
		$touchedTUse = false;
		while (list(, $token) = each($tokens)) {
			list($id, $text) = $this->getToken($token);
			if (T_USE === $id) {
				$touchedTUse = true;
			}
			if (
				T_CLASS == $id ||
				T_INTERFACE == $id
			) {
				++$classRelatedCount;
			}
			if (T_NAMESPACE == $id && !$this->rightUsefulTokenIs(T_NS_SEPARATOR)) {
				++$namespaceCount;
			}
		}

		if ($namespaceCount <= 1 && $touchedTUse) {
			return $this->sortUseClauses($source, self::SPLIT_COMMA, self::REMOVE_UNUSED, self::STRIP_BLANK_LINES, self::BLANK_LINE_AFTER_USE_BLOCK && $classRelatedCount > 0);
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

							if (T_NAMESPACE == $id && !$this->rightUsefulTokenIs(T_NS_SEPARATOR)) {
								prev($tokens);
								break;
							}

							$namespaceBlock .= $text;
						}
					}

					$return .= str_replace(
						self::OPENER_PLACEHOLDER,
						'',
						$this->sortUseClauses(self::OPENER_PLACEHOLDER . $namespaceBlock, self::SPLIT_COMMA, self::REMOVE_UNUSED, self::STRIP_BLANK_LINES, self::BLANK_LINE_AFTER_USE_BLOCK)
					);

					break;
				default:
					$return .= $text;
			}
		}

		return $return;
	}
}
