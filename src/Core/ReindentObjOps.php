<?php
final class ReindentObjOps extends ReindentAndAlignObjOps {
	const ALIGN_WITH_INDENT = 1;

	public function format($source) {
		$this->tkns = token_get_all($source);
		$this->code = '';

		$levelCounter = 0;
		$levelEntranceCounter = [];
		$contextCounter = [];
		$touchCounter = [];
		$alignType = [];
		$printedPlaceholder = [];
		$maxContextCounter = [];

		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->getToken($token);
			$this->ptr = $index;
			switch ($id) {
				case ST_QUOTE:
					$this->appendCode($text);
					$this->printUntilTheEndOfString();
					break;
				case T_CLOSE_TAG:
					$this->appendCode($text);
					$this->printUntil(T_OPEN_TAG);
					break;
				case T_START_HEREDOC:
					$this->appendCode($text);
					$this->printUntil(T_END_HEREDOC);
					break;

				case T_WHILE:
				case T_IF:
				case T_FOR:
				case T_FOREACH:
				case T_SWITCH:
					$this->appendCode($text);
					$this->printUntil(ST_PARENTHESES_OPEN);
					$this->printBlock(ST_PARENTHESES_OPEN, ST_PARENTHESES_CLOSE);
					break;

				case T_NEW:
					$this->appendCode($text);
					if ($this->leftUsefulTokenIs(ST_PARENTHESES_OPEN)) {
						$foundToken = $this->printUntilAny([ST_PARENTHESES_OPEN, ST_PARENTHESES_CLOSE, ST_COMMA]);
						if (ST_PARENTHESES_OPEN == $foundToken) {
							$this->incrementCounters($levelCounter, $levelEntranceCounter, $contextCounter, $maxContextCounter, $touchCounter, $alignType, $printedPlaceholder);
							$this->printBlock(ST_PARENTHESES_OPEN, ST_PARENTHESES_CLOSE);
							$this->printUntilAny([ST_PARENTHESES_CLOSE, ST_COMMA]);
						}
					}
					break;

				case T_FUNCTION:
					$this->appendCode($text);
					if (!$this->rightUsefulTokenIs(T_STRING)) {
						$this->printUntil(ST_PARENTHESES_OPEN);
						$this->printBlock(ST_PARENTHESES_OPEN, ST_PARENTHESES_CLOSE);
						$this->printUntil(ST_CURLY_OPEN);
						$this->printCurlyBlock();
					}
					break;

				case T_VARIABLE:
				case T_STRING:
					$this->appendCode($text);
					if (!isset($levelEntranceCounter[$levelCounter])) {
						$levelEntranceCounter[$levelCounter] = 0;
					}
					if (!isset($contextCounter[$levelCounter][$levelEntranceCounter[$levelCounter]])) {
						$contextCounter[$levelCounter][$levelEntranceCounter[$levelCounter]] = 0;
						$maxContextCounter[$levelCounter][$levelEntranceCounter[$levelCounter]] = 0;
						$touchCounter[$levelCounter][$levelEntranceCounter[$levelCounter]] = 0;
						$alignType[$levelCounter][$levelEntranceCounter[$levelCounter]] = 0;
						$printedPlaceholder[$levelCounter][$levelEntranceCounter[$levelCounter]][$contextCounter[$levelCounter][$levelEntranceCounter[$levelCounter]]] = 0;
					}
					break;

				case ST_PARENTHESES_OPEN:
				case ST_BRACKET_OPEN:
					$this->incrementCounters($levelCounter, $levelEntranceCounter, $contextCounter, $maxContextCounter, $touchCounter, $alignType, $printedPlaceholder);
					$this->appendCode($text);
					break;

				case ST_PARENTHESES_CLOSE:
				case ST_BRACKET_CLOSE:
					--$levelCounter;
					$this->appendCode($text);
					break;

				case T_OBJECT_OPERATOR:
					if (0 == $touchCounter[$levelCounter][$levelEntranceCounter[$levelCounter]]) {
						++$touchCounter[$levelCounter][$levelEntranceCounter[$levelCounter]];
						if ($this->hasLnBefore()) {
							$alignType[$levelCounter][$levelEntranceCounter[$levelCounter]] = self::ALIGN_WITH_INDENT;
							$this->appendCode($this->getIndent(+1) . $text);
							$foundToken = $this->printUntilAny([ST_PARENTHESES_OPEN, ST_PARENTHESES_CLOSE, ST_SEMI_COLON, $this->newLine]);
							if (ST_SEMI_COLON == $foundToken) {
								$this->incrementCounters($levelCounter, $levelEntranceCounter, $contextCounter, $maxContextCounter, $touchCounter, $alignType, $printedPlaceholder);
							} elseif (ST_PARENTHESES_OPEN == $foundToken || ST_PARENTHESES_CLOSE == $foundToken) {
								$this->incrementCounters($levelCounter, $levelEntranceCounter, $contextCounter, $maxContextCounter, $touchCounter, $alignType, $printedPlaceholder);
								$this->indentParenthesesContent();
							}
						} else {
							$this->appendCode($text);
						}
					} elseif ($this->hasLnBefore() || $this->hasLnLeftToken()) {
						++$touchCounter[$levelCounter][$levelEntranceCounter[$levelCounter]];
						$this->appendCode($this->getIndent(+1) . $text);
						$foundToken = $this->printUntilAny([ST_PARENTHESES_OPEN, ST_PARENTHESES_CLOSE, ST_SEMI_COLON, $this->newLine]);
						if (ST_SEMI_COLON == $foundToken) {
							$this->incrementCounters($levelCounter, $levelEntranceCounter, $contextCounter, $maxContextCounter, $touchCounter, $alignType, $printedPlaceholder);
						} elseif (ST_PARENTHESES_OPEN == $foundToken || ST_PARENTHESES_CLOSE == $foundToken) {
							$this->incrementCounters($levelCounter, $levelEntranceCounter, $contextCounter, $maxContextCounter, $touchCounter, $alignType, $printedPlaceholder);
							$this->indentParenthesesContent();
						}
					} else {
						$this->appendCode($text);
					}
					break;

				case T_COMMENT:
				case T_DOC_COMMENT:
					if (
						isset($alignType[$levelCounter]) &&
						isset($levelEntranceCounter[$levelCounter]) &&
						isset($alignType[$levelCounter][$levelEntranceCounter[$levelCounter]]) &&
						($this->hasLnBefore() || $this->hasLnLeftToken()) &&
						self::ALIGN_WITH_INDENT == $alignType[$levelCounter][$levelEntranceCounter[$levelCounter]]
					) {
						$this->appendCode($this->getIndent(+1));
					}
					$this->appendCode($text);
					break;

				case ST_COMMA:
				case ST_SEMI_COLON:
					if (!isset($levelEntranceCounter[$levelCounter])) {
						$levelEntranceCounter[$levelCounter] = 0;
					}
					++$levelEntranceCounter[$levelCounter];
					$this->appendCode($text);
					break;

				default:
					$this->appendCode($text);
					break;
			}
		}
		return $this->code;
	}
}
