<?php
if (ini_get('phar.readonly')) {
	unset($argv[0]);
	passthru($_SERVER['_'] . ' -dphar.readonly=0 build.php ' . implode(' ', $argv) . ' 2>&1');
	exit(0);
}
include 'vendor/dericofilho/csp/csp.php';
include 'Core/FormatterPass.php';
include 'version.php';

error_reporting(E_ALL);
$opt = getopt('Mmp', ['suffix:']);
$newver = '';
if (isset($opt['M'])) {
	$tmp = explode('.', VERSION);
	$newver = '<?php define("VERSION", "' . ($tmp[0] + 1) . '.0.0");';
} elseif (isset($opt['m'])) {
	$tmp = explode('.', VERSION);
	$newver = '<?php define("VERSION", "' . ($tmp[0]) . '.' . ($tmp[1] + 1) . '.0");';
} elseif (isset($opt['p'])) {
	$tmp = explode('.', VERSION);
	$newver = '<?php define("VERSION", "' . ($tmp[0]) . '.' . ($tmp[1]) . '.' . ($tmp[2] + 1) . '");';
}
if (!empty($newver)) {
	echo 'Bumping version to: ', $newver, PHP_EOL;
	file_put_contents('version.php', $newver);
}

class Build extends FormatterPass {
	private $suffix = [];
	public function __construct($suffix) {
		$this->suffix = explode(',', $suffix);
		$this->suffix[] = '';
	}

	public function candidate($source, $foundTokens) {
		return true;
	}
	public function format($source) {
		$this->tkns = token_get_all($source);
		$this->code = '';
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->getToken($token);
			$this->ptr = $index;
			switch ($id) {
				case T_REQUIRE:
					list($id, $text) = $this->walkUntil(T_CONSTANT_ENCAPSED_STRING);
					foreach ($this->suffix as $suffix) {
						$fn = str_replace(['"', "'"], '', $text);
						if (!empty($suffix)) {
							$fn = str_replace('.php', '_' . $suffix . '.php', $fn);
						}
						if (!file_exists($fn)) {
							continue;
						}
						$included = token_get_all(file_get_contents(str_replace(['"', "'"], '', $fn)));
						if (T_OPEN_TAG == $included[0][0]) {
							unset($included[0]);
						}
						while (list(, $token) = each($included)) {
							list($id, $text) = $this->getToken($token);
							$this->appendCode($text);
						}
						break 2;
					}
					break;
				default:
					$this->appendCode($text);
			}
		}
		return $this->code;
	}
}

$suffix = &$opt['suffix'];
$pass = new Build($suffix);

$chn = make_channel();
$chn_done = make_channel();
$workers = 2;
echo 'Starting ', $workers, ' workers...', PHP_EOL;
for ($i = 0; $i < $workers; ++$i) {
	cofunc(function ($pass, $chn, $chn_done) {
		while (true) {
			$target = $chn->out();
			if (empty($target)) {
				break;
			}
			echo $target, PHP_EOL;
			file_put_contents($target . '.php', $pass->format(file_get_contents($target . '.src.php')));
			if (file_exists($target . '.stub.src.php')) {
				file_put_contents($target . '.stub.php', $pass->format(file_get_contents($target . '.stub.src.php')));
			}
		}
		$chn_done->in('done');
	}, $pass, $chn, $chn_done);
}

$targets = ['fmt', 'refactor', 'php.tools'];
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

echo 'Building PHARs...';
$phars = ['fmt', 'refactor'];
foreach ($phars as $target) {
	file_put_contents($target . '.stub.php', '<?php $inPhar = true;' . "\n" . str_replace('#!/usr/bin/env php' . "\n" . '<?php', '', file_get_contents($target . '.stub.php')));
	$phar = new Phar($target . '.phar', FilesystemIterator::CURRENT_AS_FILEINFO | FilesystemIterator::KEY_AS_FILENAME, $target . '.phar');
	$phar[$target . '.stub.php'] = file_get_contents($target . '.stub.php');
	$phar->setStub('#!/usr/bin/env php' . "\n" . $phar->createDefaultStub($target . '.stub.php'));
	file_put_contents($target . '.phar.sha1', sha1_file($target . '.phar'));
	unlink($target . '.stub.php');
}
echo 'done', PHP_EOL;

$variants = ['.php' => 0755, '.phar' => 0755, '.phar.sha1' => 0444];
foreach ($targets as $target) {
	foreach ($variants as $variant => $permission) {
		if (file_exists($target . $variant)) {
			echo 'moving ', $target . $variant, ' to ..' . DIRECTORY_SEPARATOR . $target . $variant, PHP_EOL;
			rename($target . $variant, '..' . DIRECTORY_SEPARATOR . $target . $variant);
			chmod('..' . DIRECTORY_SEPARATOR . $target . $variant, $permission);
		}
	}
}
echo 'moving ', '..' . DIRECTORY_SEPARATOR . 'php.tools.php', ' to ..' . DIRECTORY_SEPARATOR . 'php.tools', PHP_EOL;
rename('..' . DIRECTORY_SEPARATOR . 'php.tools.php', '..' . DIRECTORY_SEPARATOR . 'php.tools');