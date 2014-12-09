<?php
include 'vendor/dericofilho/csp/csp.php';
include "Core/FormatterPass.php";

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

$chn = make_channel();
$chn_done = make_channel();
$workers = 2;
echo "Starting ", $workers, " workers...", PHP_EOL;
for ($i = 0; $i < $workers; ++$i) {
	cofunc(function ($pass, $chn, $chn_done) {
		while (true) {
			$target = $chn->out();
			if (empty($target)) {
				break;
			}
			echo $target, PHP_EOL;
			file_put_contents($target . '.php', $pass->format(file_get_contents($target . '.src.php')));
			chmod($target . '.php', 0755);
		}
		$chn_done->in('done');
	}, $pass, $chn, $chn_done);
}

$targets = ['fmt', 'refactor'];
foreach ($targets as $target) {
	$chn->in($target);
}

for ($i = 0; $i < $workers; ++$i) {
	$chn->in(null);
}
for ($i = 0; $i < $workers; ++$i) {
	$chn_done->out();
}
$chn->close();
$chn_done->close();