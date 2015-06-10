<?php
//Copyright (c) 2014, Carlos C
//All rights reserved.
//
//Redistribution and use in source and binary forms, with or without modification, are permitted provided that the following conditions are met:
//
//1. Redistributions of source code must retain the above copyright notice, this list of conditions and the following disclaimer.
//
//2. Redistributions in binary form must reproduce the above copyright notice, this list of conditions and the following disclaimer in the documentation and/or other materials provided with the distribution.
//
//3. Neither the name of the copyright holder nor the names of its contributors may be used to endorse or promote products derived from this software without specific prior written permission.
//
//THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT HOLDER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
namespace {
require 'Core/constants.php';
require 'Core/FormatterPass.php';
require 'Core/RefactorPass.php';

final class CodeFormatter {
	private $passes = [];
	private $debug = false;
	public function __construct($debug = false) {
		$this->debug = (bool) $debug;
	}
	public function addPass(FormatterPass $pass) {
		$this->passes[] = $pass;
	}

	public function formatCode($source = '') {
		gc_enable();
		$passes = array_map(
			function ($pass) {
				return clone $pass;
			},
			$this->passes
		);
		while (($pass = array_shift($passes))) {
			$source = $pass->format($source);
			gc_collect_cycles();
		}
		gc_disable();
		return $source;
	}
}
if (!isset($testEnv)) {
	$opts = getopt('ho:', ['from:', 'to:', 'help']);
	if (isset($opts['h']) || isset($opts['help'])) {
		echo 'Usage: ' . $argv[0] . ' [-ho] [--from=from --to=to] <target>', PHP_EOL;
		$options = [
			'--from=from, --to=to' => 'Search for "from" and replace with "to" - context aware search and replace',
			'-h, --help' => 'this help message',
			'-o=file' => 'output the formatted code to "file"',
		];
		$maxLen = max(array_map(function ($v) {
			return strlen($v);
		}, array_keys($options)));
		foreach ($options as $k => $v) {
			echo '  ', str_pad($k, $maxLen), '  ', $v, PHP_EOL;
		}
		echo PHP_EOL, 'If <target> is blank, it reads from stdin', PHP_EOL;
		die();
	}
	if (isset($opts['from']) && !isset($opts['to'])) {
		fwrite(STDERR, 'Refactor must have --from and --to parameters' . PHP_EOL);
		exit(255);
	}

	$debug = false;

	$fmt = new CodeFormatter($debug);

	if (isset($opts['from']) && isset($opts['to'])) {
		$argv = array_values(
			array_filter($argv,
				function ($v) {
					$param_from = '--from';
					$param_to = '--to';
					return substr($v, 0, strlen($param_from)) !== $param_from && substr($v, 0, strlen($param_to)) !== $param_to;
				}
			)
		);
		$fmt->addPass(new RefactorPass($opts['from'], $opts['to']));
	}

	if (isset($opts['o'])) {
		unset($argv[1]);
		unset($argv[2]);
		$argv = array_values($argv);
		file_put_contents($opts['o'], $fmt->formatCode(file_get_contents($argv[1])));
	} elseif (isset($argv[1]) && is_file($argv[1])) {
		echo $fmt->formatCode(file_get_contents($argv[1]));
	} elseif (isset($argv[1]) && is_dir($argv[1])) {
		$dir = new RecursiveDirectoryIterator($argv[1]);
		$it = new RecursiveIteratorIterator($dir);
		$files = new RegexIterator($it, '/^.+\.php$/i', RecursiveRegexIterator::GET_MATCH);
		foreach ($files as $file) {
			$file = $file[0];
			echo $file;
			$orig_code = file_get_contents($file);
			$new_code = $fmt->formatCode($orig_code);
			if ($orig_code != $new_code) {
				file_put_contents($file . '-tmp', $new_code);
				rename($file, $file . '~');
				rename($file . '-tmp', $file);
			}
			echo PHP_EOL;
		}
	} else {
		echo $fmt->formatCode(file_get_contents('php://stdin'));
	}
}

}