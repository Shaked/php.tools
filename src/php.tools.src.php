#!/usr/bin/env php
<?php
# Copyright (c) 2014, Carlos C
# All rights reserved.
#
# Redistribution and use in source and binary forms, with or without modification, are permitted provided that the following conditions are met:
#
# 1. Redistributions of source code must retain the above copyright notice, this list of conditions and the following disclaimer.
#
# 2. Redistributions in binary form must reproduce the above copyright notice, this list of conditions and the following disclaimer in the documentation and/or other materials provided with the distribution.
#
# 3. Neither the name of the copyright holder nor the names of its contributors may be used to endorse or promote products derived from this software without specific prior written permission.
#
# THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT HOLDER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.

$concurrent = function_exists('pcntl_fork');
if ($concurrent) {
	require 'vendor/dericofilho/csp/csp.php';
}

if (version_compare(phpversion(), '5.5.0', '<')) {
	fwrite(STDERR, "PHP needs to be a minimum version of PHP 5.5.0\n");
	exit(255);
}

list(, $ok) = which('git');
if (!$ok) {
	fwrite(STDERR, "This tool needs Git to work." . PHP_EOL);
	fwrite(STDERR, "Please, install git using:" . PHP_EOL);
	fwrite(STDERR, "  sudo yum install git" . PHP_EOL);
	fwrite(STDERR, "or" . PHP_EOL);
	fwrite(STDERR, "  sudo apt-get install git" . PHP_EOL);
	exit(255);
}

$DIRS = [];
foreach (new DirectoryIterator('.') as $fileInfo) {
	if ($fileInfo->isDot() || !$fileInfo->isDir() || preg_match('/cover|vendor|web|fixtures|docs|^\./', $fileInfo->getFilename())) {
		continue;
	}
	$DIRS[] = $fileInfo->getFilename();
}

define('FMTWORKERS', 4);

$clocBin = false;
list($bin, $ok) = which('cloc');
if ($ok) {
	$clocBin = $bin;
}
list($bin, $ok) = which('cloc.pl');
if ($ok) {
	$clocBin = $bin;
}
list($bin, $ok) = which('./cloc');
if ($ok) {
	$clocBin = $bin;
}
list($bin, $ok) = which('./cloc.pl');
if ($ok) {
	$clocBin = $bin;
}

$ctagsBin = false;
list($bin, $ok) = which('ctags');
if ($ok) {
	$ctagsVersion = [];
	exec($bin . ' --version', $ctagsVersion);
	if (false !== strpos(implode('', $ctagsVersion), 'Exuberant')) {
		$ctagsBin = $bin;
	}
}

$phpunitBin = false;
list($bin, $ok) = which('vendor/bin/phpunit');
if ($ok) {
	$phpunitBin = $bin;
}

$fmtBin = false;
if (file_exists('fmt.php')) {
	$fmtBin = 'fmt.php';
} elseif (file_exists('vendor/bin/fmt.php')) {
	$fmtBin = 'vendor/bin/fmt.php';
}

$phpdocBin = false;
if (file_exists('vendor/bin/phpdoc.php')) {
	$phpdocBin = 'vendor/bin/phpdoc.php';
}

$execute = function () {
	GLOBAL $clocBin, $ctagsBin, $phpunitBin, $fmtBin, $phpdocBin, $argv;

	echo 'php.tools [command]' . PHP_EOL;
	echo '	lint - run lint on changed files' . PHP_EOL;
	echo '	lint all - run lint on all files' . PHP_EOL;

	if ($ctagsBin) {
		echo '	ctags - generate ctags' . PHP_EOL;
	}

	if ($phpunitBin) {
		echo '	test - execute PHPUnit' . PHP_EOL;
	}

	if ($phpunitBin) {
		echo '	cover - execute PHPUnit with cover output' . PHP_EOL;
	}

	if ($phpdocBin) {
		echo '	doc - execute phpDocumentor' . PHP_EOL;
	}

	if ($clocBin) {
		echo '	cloc - execute script to count lines of code' . PHP_EOL;
	}

	if ($ctagsBin) {
		echo '	watch ctags - execute PHPUnit, but keeps watching for file changes to trigger ctags generator' . PHP_EOL;
	}

	if ($phpunitBin) {
		echo '	watch test - execute PHPUnit, but keeps watching for file changes to trigger the test automatically' . PHP_EOL;
	}

	if ($phpunitBin) {
		echo '	watch cover - execute PHPUnit with cover output, but keeps watching for file changes to trigger the test automatically' . PHP_EOL;
	}

	if ($phpdocBin) {
		echo '	watch doc - execute phpDocumentor, but keeps watching for file changes to trigger the generation automatically' . PHP_EOL;
	}

	if ($clocBin) {
		echo '	watch doc - execute script to count lines of code, but keeps watching for file changes to trigger the count automatically' . PHP_EOL;
	}

	if ($fmtBin) {
		echo '	fmt [filename] - format filename according to project formatting rules' . PHP_EOL;
	}

	if ($fmtBin) {
		echo '	fmt all - format all files according to project formatting rules' . PHP_EOL;
	}

	if ($fmtBin) {
		echo '	fmt clean - remove all backup files - *~' . PHP_EOL;
	}

	if ($fmtBin) {
		echo '	watch fmt [all|filename] - watch for changes and format according to project formatting rules' . PHP_EOL;
	}

	if (!$clocBin) {
		echo '' . PHP_EOL;
		echo '	download cloc from http://cloc.sourceforge.net/' . PHP_EOL;
	}

	if (!$phpunitBin) {
		echo '' . PHP_EOL;
		echo '	add phpunit to composer.json:' . PHP_EOL;
		echo '	"phpunit/phpunit": "4.0.*"' . PHP_EOL;
	}

	if (!$phpdocBin) {
		echo '' . PHP_EOL;
		echo '	add phpdoc to composer.json:' . PHP_EOL;
		echo '	"phpdocumentor/phpdocumentor": "2.4.*"' . PHP_EOL;
	}

	if (!$ctagsBin) {
		echo '' . PHP_EOL;
		echo '	install exuberant ctags:' . PHP_EOL;
		echo '	http://ctags.sourceforge.net/' . PHP_EOL;
	}

	exit(0);
};

function updatePhpunitXml($DIRS) {
	$phpunit_xml_template = "
	<phpunit
	         xmlns:xsi=\"http://www.w3.org/2001/XMLSchema-instance\"
	         xsi:noNamespaceSchemaLocation=\"http://schema.phpunit.de/3.7/phpunit.xsd\"
	         colors=\"true\"
	         verbose=\"false\">
	         <testsuite>
	";
	foreach ($DIRS as $i) {
		$phpunit_xml_template .= "<directory suffix=\"_test.php\">" . $i . "</directory>";
	}
	$phpunit_xml_template .= "</testsuite>";

	$phpunit_xml_template .= "<filter>";
	$phpunit_xml_template .= "<whitelist processUncoveredFilesFromWhitelist=\"true\" >";
	foreach ($DIRS as $i) {
		$phpunit_xml_template .= "<directory suffix=\".php\">$i</directory>";
	}
	$phpunit_xml_template .= "<exclude>";
	foreach ($DIRS as $i) {
		$phpunit_xml_template .= "<directory suffix=\"_test.php\">$i</directory>";
	}
	$phpunit_xml_template .= "</exclude>";
	$phpunit_xml_template .= "</whitelist>";
	$phpunit_xml_template .= "</filter></phpunit>";

	file_put_contents('phpunit.xml', $phpunit_xml_template);
}

$WATCH = "";
if (isset($argv[1]) && "watch" == $argv[1]) {
	$WATCH = "watch";
	array_shift($argv);
}

if ($clocBin && isset($argv[1]) && "cloc" == $argv[1]) {
	$execute = function () {
		GLOBAL $clocBin, $DIRS;
		$src = "nice -n 20 $clocBin " . implode(" ",
			array_map(function ($v) {
				return escapeshellarg($v);
			}, $DIRS)
		);
		passthru($src);
	};
}

if ($ctagsBin && isset($argv[1]) && "ctags" == $argv[1]) {
	$execute = function () {
		GLOBAL $ctagsBin, $DIRS;

		$dirs = implode(' ', array_map(function ($v) {
			return escapeshellarg($v);
		}, $DIRS));
		passthru("nice -n 20 ctags --PHP-kinds=+cf \
			--regex-PHP='/define\(\"([^ ]*)\"/\1/d/' \
			--regex-PHP=\"/define\('.([^ ]*)'/\1/d/\" \
			--regex-PHP='/const ([^ ]*)/\1/d/' \
			--regex-PHP='/trait ([^ ]*)/\1/c/' \
			--regex-PHP='/final class ([^ ]*)/\1/c/' \
			--regex-PHP='/final abstract class ([^ ]*)/\1/c/' \
			--regex-PHP='/abstract class ([^ ]*)/\1/c/' \
			--regex-PHP='/interface ([^ ]*)/\1/c/' \
			--regex-PHP='/(public |static |abstract |protected |private |final public )+function ([^ (]*)/\2/f/' -R -f .tags-new \
			" . $dirs
		);
		rename('.tags-new', '.tags');
	};
}

if ($phpunitBin && isset($argv[1]) && "test" == $argv[1]) {
	updatePhpunitXml($DIRS);
	$execute = function () use ($phpunitBin) {
		$argv = $GLOBALS['argv'];
		$TEST = "";
		unset($argv[0]);
		if (isset($argv[1])) {
			$argv[1] = trim($argv[1]);
			$argv[1] = str_replace('.php', '', trim($argv[1]));
			if (strpos($argv[1], '/') !== false) {
				$TEST = $argv[1] . "Test " . $argv[1] . "_test.php";
			} elseif (strpos($argv[1], "\\") !== false) {
				$FN = str_replace("\\", '/', $argv[1]);
				$TEST = $argv[1] . "Test " . $FN . "_test.php";
			}
			array_shift($argv);
			array_shift($argv);
		}
		passthru($phpunitBin . ' ' . $TEST . ' ' . implode(' ', $argv));
	};
}

if ($phpunitBin && isset($argv[1]) && "cover" == $argv[1]) {
	updatePhpunitXml($DIRS);
	$execute = function () use ($phpunitBin) {
		$argv = $GLOBALS['argv'];
		$TEST = "";
		unset($argv[0]);
		if (isset($argv[1])) {
			$argv[1] = trim($argv[1]);
			$argv[1] = str_replace('.php', '', trim($argv[1]));
			if (strpos($argv[1], '/') !== false) {
				$TEST = $argv[1] . "Test " . $argv[1] . "_test.php";
			} elseif (strpos($argv[1], "\\") !== false) {
				$FN = str_replace("\\", '/', $argv[1]);
				$TEST = $argv[1] . "Test " . $FN . "_test.php";
			}
			array_shift($argv);
			array_shift($argv);
		}
		passthru($phpunitBin . ' --coverage-text --coverage-html=cover/ --coverage-clover=clover.xml --log-junit=junit.xml ' . $TEST . ' ' . implode(' ', $argv));
	};
}

if ($phpdocBin && isset($argv[1]) && "doc" == $argv[1]) {
	$execute = function () use ($phpdocBin) {
		GLOBAL $DIRS;
		$NEW_DIRS = "";
		foreach ($DIRS as $i) {
			$NEW_DIRS = "-d " . escapeshellarg($i) . " " . $NEW_DIRS;
		}
		passthru("php " . $phpdocBin . " " . $NEW_DIRS . " -t docs/");
	};
}

if (isset($argv[1]) && "lint" == $argv[1]) {
	if (!isset($argv[2])) {
		$execute = function () {
			GLOBAL $concurrent;

			$files = [];
			exec('git status -s | grep -i "\.php$" | awk -F \' \' \'{ print $2 }\'', $files);
			echo "Differential linting...";
			if ($concurrent) {
				list($chn, $chnDone) = concurrentExec('php -l %s');
				echo "Running lint...", PHP_EOL;
				foreach ($files as $file) {
					$chn->in($file);
				}
				stopExec($chn, $chnDone);
			} else {
				foreach ($files as $file) {
					passthru('php -l ' . $file);
				}
			}
		};
	} elseif ("all" == $argv[2]) {
		$execute = function () {
			GLOBAL $concurrent;

			$directory = new RecursiveDirectoryIterator('.');
			$iterator = new RecursiveIteratorIterator($directory);
			$regex = new RegexIterator($iterator, '/^.+\.php$/i', RecursiveRegexIterator::GET_MATCH);

			echo "Full linting...", PHP_EOL;
			if ($concurrent) {
				list($chn, $chnDone) = concurrentExec('php -l %s');
				echo "Running lint...", PHP_EOL;
				foreach ($regex as $file) {
					$file = $file[0];
					$chn->in($file);
				}
				stopExec($chn, $chnDone);
			} else {
				foreach ($regex as $file) {
					$file = $file[0];
					passthru('php -l ' . $file);
				}
			}
		};
	}
}

if ($fmtBin && isset($argv[1]) && "fmt" == $argv[1]) {
	if (!isset($argv[2])) {
		$execute = function () use ($fmtBin) {
			list($chn, $chnDone) = concurrentExec('php ' . $fmtBin . ' --lint-before %s');
			$files = [];
			exec("git status -s | grep -i \"\.php$\" | awk -F ' ' '{ print $2 } '", $files);
			foreach ($files as $file) {
				$chn->in($file);
			}
			stopExec($chn, $chnDone);
		};
	} elseif ("clean" == $argv[2]) {
		$execute = function () use ($fmtBin) {
			$directory = new RecursiveDirectoryIterator('.');
			$iterator = new RecursiveIteratorIterator($directory);
			$regex = new RegexIterator($iterator, '/^.+~$/i', RecursiveRegexIterator::GET_MATCH);
			foreach ($regex as $file) {
				$file = $file[0];
				echo $file, PHP_EOL;
				unlink($file);
			}
		};
	} elseif ("all" == $argv[2]) {
		$execute = function () use ($fmtBin) {
			passthru('php ' . $fmtBin . ' --lint-before .');
		};
	} else {
		$line = $argv[2];
		$execute = function () use ($fmtBin, $line) {
			passthru('php ' . $fmtBin . ' --lint-before ' . $line);
		};
	}
}

array_shift($argv);
if (!empty($WATCH)) {
	$currentTime = 0;
	while (true) {
		foreach ($DIRS as $dir) {
			$directoryIterator = new RecursiveDirectoryIterator($dir);
			$iterator = new RecursiveIteratorIterator($directoryIterator);
			foreach ($iterator as $file) {
				if ($file->getMTime() > $currentTime) {
					echo 'Running ...', PHP_EOL;
					call_user_func($execute);
					$currentTime = time();
					break 2;
				}
			}
		}
	}
} else {
	call_user_func($execute);
}

function which($cmd) {
	$output = [];
	$retcode = 0;
	$which = exec('which ' . escapeshellarg($cmd), $output, $retcode);
	return [$which, 0 == $retcode];
}

function concurrentExec($cmd) {
	$chn = make_channel();
	$chnDone = make_channel();
	echo "Starting " . FMTWORKERS . "...", PHP_EOL;
	for ($i = 0; $i < FMTWORKERS; ++$i) {
		cofunc(function ($chn, $chnDone, $cmd, $i) {
			while (true) {
				$str = $chn->out();
				if (null === $str) {
					break;
				}
				passthru(sprintf($cmd, $str) . ' | while read line; do echo "' . ($i + 1) . ' $line"; done');
			}
			$chnDone->in('OK');
		}, $chn, $chnDone, $cmd, $i);
	}

	cofunc(function ($chn, $chnDone) {

	}, $chn, $chnDone);

	return [$chn, $chnDone];
}

function stopExec($chn, $chnDone) {
	for ($i = 0; $i < FMTWORKERS; ++$i) {
		$chn->in(null);
	}
	for ($i = 0; $i < FMTWORKERS; ++$i) {
		$chnDone->out();
	}
	$chn->close();
	$chnDone->close();
}