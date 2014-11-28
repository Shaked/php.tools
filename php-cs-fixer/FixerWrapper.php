<?php
abstract class FixerWrapper extends FormatterPass {

	public function fromCode($content) {
		$tmp = token_get_all($content);
		$ret = [];
		foreach ($tmp as $token) {
			$ret[] = new Token($token);
		}
		return $ret;
	}

	public function findGivenKind($tkns, $possibleKind) {
		$elements = [];
		$possibleKinds = is_array($possibleKind) ? $possibleKind : [$possibleKind];
		foreach ($possibleKinds as $kind) {
			$elements[$kind] = [];
		}
		foreach ($tkns as $index => $token) {
			if ($token->isGivenKind($possibleKinds)) {
				$elements[$token->getId()][$index] = $token;
			}
		}
		return is_array($possibleKind) ? $elements : $elements[$possibleKind];
	}

	public function render($tokens = null) {
		$ret = '';
		foreach ($tokens as $token) {
			$ret .= $token->getContent();
		}
		return $ret;
	}

	public function getPrevNonWhitespace($tkns, $idx) {
		return $this->walk_left($tkns, $idx, [T_WHITESPACE => T_WHITESPACE]);
	}
}