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
		$from_size = sizeof($from);
		$from_str = implode('', array_map(function ($v) {
			return $v[1];
		}, $from));
		$to = $this->getTo();
		$to_str = implode('', array_map(function ($v) {
			return $v[1];
		}, $to));

		$this->tkns = token_get_all($source);
		$this->code = '';
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->getToken($token);
			$this->ptr = $index;

			if ($id == $from[0][0]) {
				$match = true;
				$buffer = $text;
				for ($i = 1; $i < $from_size; ++$i) {
					list($index, $token) = each($this->tkns);
					$this->ptr = $index;
					list($id, $text) = $this->getToken($token);
					$buffer .= $text;
					if ('/*skipUntil' == substr($from[$i][1], 0, 11)) {
						$skip_call = $from[$i][1];
						$stop_text = strtolower(trim(str_replace('skipUntil:', '', substr($text, 2, -2))));
						++$i;
						while (list($index, $token) = each($this->tkns)) {
							$this->ptr = $index;
							list($id, $text) = $this->getToken($token);
							$buffer .= $text;
							if ($id == $from[$i][0]) {
								$tmp_i = $i;
								$tmp_ptr = $this->ptr;
								$s_match = true;
								for ($tmp_i; $tmp_i < $from_size; ++$tmp_i, ++$tmp_ptr) {
									if ($from[$tmp_i][0] != $this->tkns[$tmp_ptr][0]) {
										$s_match = false;
										break;
									}
								}
								if ($s_match) {
									break;
								} else {
									continue;
								}
							}
							if (strtolower($text) == $stop_text) {
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
					if (strpos($to_str, '/*skip*/')) {
						$buffer = str_replace(explode($skip_call, $from_str), explode('/*skip*/', $to_str), $buffer);
					} else {
						$buffer = str_replace($from_str, $to_str, $buffer);
					}
				}

				$this->appendCode($buffer);
			} else {
				$this->appendCode($text);
			}
		}
		return $this->code;
	}
}