<?php
class ExtractMethods extends FormatterPass {

	private $functionStack = [];

	public function candidate($source, $foundTokens) {
		if (isset($foundTokens[T_FUNCTION])) {
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

			if (T_CLASS == $id) {
				$this->appendCode($text);
				$this->walkUntil(T_STRING);

				list(, $className) = $this->inspectToken(0);
				$this->appendCode(' ' . $className);

				$this->walkUntil(ST_CURLY_OPEN);
				$this->appendCode(' ' . ST_CURLY_OPEN);

				$startPtr = $this->ptr;
				$endPtr = $this->ptr;
				$this->refWalkCurlyBlock($this->tkns, $endPtr);

				// $this->extractMethodsFrom($className, $startPtr, $endPtr);
				continue;
			}

			$this->appendCode($text);
		}

		return $this->code;
	}

	private function extractMethodsFrom($className, $startPtr, $endPtr) {
		echo $className, ' ', $startPtr, ' <-> ', $endPtr;
	}
}
