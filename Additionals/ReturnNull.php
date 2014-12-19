<?php
class ReturnNull extends AdditionalPass {
	public function candidate($source, $foundTokens) {
		if (isset($foundTokens[T_RETURN])) {
			return true;
		}

		return false;
	}
	public function format($source) {
		$this->tkns = token_get_all($source);
		$this->code = '';
		$this->useCache = true;
		$touched_return = false;
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->getToken($token);
			$this->ptr = $index;
			$this->cache = [];

			if (ST_PARENTHESES_OPEN == $id && $this->leftTokenIs([T_RETURN])) {
				$paren_count = 1;
				$touched_another_valid_token = false;
				$stack = $text;
				while (list($index, $token) = each($this->tkns)) {
					list($id, $text) = $this->getToken($token);
					$this->ptr = $index;
					$this->cache = [];
					if (ST_PARENTHESES_OPEN == $id) {
						++$paren_count;
					}
					if (ST_PARENTHESES_CLOSE == $id) {
						--$paren_count;
					}
					$stack .= $text;
					if (0 == $paren_count) {
						break;
					}
					if (
						!(
							(T_STRING == $id && strtolower($text) == 'null') ||
							ST_PARENTHESES_OPEN == $id ||
							ST_PARENTHESES_CLOSE == $id
						)
					) {
						$touched_another_valid_token = true;
					}
				}
				if ($touched_another_valid_token) {
					$this->appendCode($stack);
				}
				continue;
			}
			if (T_STRING == $id && strtolower($text) == 'null') {
				list($prev_id, ) = $this->leftUsefulToken();
				list($next_id, ) = $this->rightUsefulToken();
				if (T_RETURN == $prev_id && ST_SEMI_COLON == $next_id) {
					continue;
				}
			}

			$this->appendCode($text);
		}

		return $this->code;
	}

	/**
	 * @codeCoverageIgnore
	 */
	public function getDescription() {
		return 'Simplify empty returns.';
	}

	/**
	 * @codeCoverageIgnore
	 */
	public function getExample() {
		return <<<'EOT'
<?php
function a(){
	return null;
}
?>
to
<?php
function a(){
	return;
}
?>
EOT;
	}
}
