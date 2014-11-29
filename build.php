<?php
include "FormatterPass.php";

class Build extends FormatterPass {
	public function format($source) {
		$this->tkns = SplFixedArray::fromArray(token_get_all($source));
		$this->code = '';
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->get_token($token);
			$this->ptr = $index;
			switch ($id) {
				case T_INCLUDE:
					list($id, $text) = $this->walk_until(T_CONSTANT_ENCAPSED_STRING);
					$included = token_get_all(file_get_contents(str_replace(['"', "'"], '', $text)));
					if (T_OPEN_TAG == $included[0][0]) {
						unset($included[0]);
					}
					while (list(, $token) = each($included)) {
						list($id, $text) = $this->get_token($token);
						$this->append_code($text);
					}
					break;
				default:
					$this->append_code($text);
			}
		}
		return $this->code;
	}
}

$pass = new Build();
$targets = ['fmt', 'refactor'];
foreach ($targets as $target) {
	echo $target;
	file_put_contents($target . '.php', $pass->format(file_get_contents($target . '.src.php')));
	chmod($target . '.php', 0755);
	echo PHP_EOL;
}
