<?php
final class RefactorPass extends FormatterPass {
	private $from;
	private $to;
	public function __construct($from, $to) {
		$this->setFrom($from);
		$this->setTo($to);
	}
	private function setFrom($from) {
		$tkns = token_get_all('<?php ' . $from);
		array_shift($tkns);
		$tkns = array_map(function ($v) {
			return $this->getToken($v);
		}, $tkns);
		$this->from = $tkns;
		return $this;
	}
	private function getFrom() {
		return $this->from;
	}
	private function setTo($to) {
		$tkns = token_get_all('<?php ' . $to);
		array_shift($tkns);
		$tkns = array_map(function ($v) {
			return $this->getToken($v);
		}, $tkns);
		$this->to = $tkns;
		return $this;
	}
	private function getTo() {
		return $this->to;
	}
	public function candidate($source, $foundTokens) {
		return true;
	}
	public function format($source) {
		$from = $this->getFrom();
		$fromSize = sizeof($from);
		$fromStr = implode('', array_map(function ($v) {
			return $v[1];
		}, $from));
		$to = $this->getTo();
		$toStr = implode('', array_map(function ($v) {
			return $v[1];
		}, $to));

		$this->tkns = token_get_all($source);
		$this->code = '';
		$skipCall = null;
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->getToken($token);
			$this->ptr = $index;

			if ($id == $from[0][0]) {
				$match = true;
				$buffer = $text;
				for ($i = 1; $i < $fromSize; ++$i) {
					list($index, $token) = each($this->tkns);
					$this->ptr = $index;
					list($id, $text) = $this->getToken($token);
					$buffer .= $text;
					if ('/*skipUntil' == substr($from[$i][1], 0, 11)) {
						$skipCall = $from[$i][1];
						$stopText = strtolower(trim(str_replace('skipUntil:', '', substr($text, 2, -2))));
						++$i;
						while (list($index, $token) = each($this->tkns)) {
							$this->ptr = $index;
							list($id, $text) = $this->getToken($token);
							$buffer .= $text;
							if ($id == $from[$i][0]) {
								$tmpI = $i;
								$tmpPtr = $this->ptr;
								$sMatch = true;
								for ($tmpI; $tmpI < $fromSize; ++$tmpI, ++$tmpPtr) {
									if ($from[$tmpI][0] != $this->tkns[$tmpPtr][0]) {
										$sMatch = false;
										break;
									}
								}
								if ($sMatch) {
									break;
								}
								continue;
							}
							if (strtolower($text) == $stopText) {
								$match = false;
								break 2;
							}
						}
						continue;
					}
					if ($id != $from[$i][0]) {
						$match = false;
						break;
					}
				}
				if ($match) {
					$buffer = $this->calculateBuffer($fromStr, $toStr, $skipCall, $buffer);
				}

				$this->appendCode($buffer);
				continue;
			}

			$this->appendCode($text);
		}
		return $this->code;
	}

	public function calculateBuffer($fromStr, $toStr, $skipCall, $buffer) {
		if (strpos($toStr, '/*skip*/')) {
			return str_replace(explode($skipCall, $fromStr), explode('/*skip*/', $toStr), $buffer);
		}
		return str_replace($fromStr, $toStr, $buffer);
	}
}