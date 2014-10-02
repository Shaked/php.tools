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
include 'constants.php';
include 'FormatterPass.php';
include 'AddMissingCurlyBraces.php';
include 'AlignDoubleArrow.php';
include 'AlignEquals.php';
include 'EliminateDuplicatedEmptyLines.php';
include 'ExtraCommaInArray.php';
include 'LeftAlignComment.php';
include 'MergeCurlyCloseAndDoWhile.php';
include 'MergeDoubleArrowAndArray.php';
include 'MergeParenCloseWithCurlyOpen.php';
include 'NormalizeLnAndLtrimLines.php';
include 'OrderUseClauses.php';
include 'Refactor.php';
include 'Reindent.php';
include 'ReindentColonBlocks.php';
include 'ReindentIfColonBlocks.php';
include 'ReindentLoopColonBlocks.php';
include 'ReindentObjOps.php';
include 'ResizeSpaces.php';
include 'RTrim.php';
include 'SettersAndGettersPass.php';
include 'SurrogateToken.php';
include 'TwoCommandsInSameLine.php';
//PSR standards
include 'PSR1BOMMark.php';
include 'PSR1ClassConstants.php';
include 'PSR1ClassNames.php';
include 'PSR1MethodNames.php';
include 'PSR1OpenTags.php';
include 'PSR2CurlyOpenNextLine.php';
include 'PSR2IndentWithSpace.php';
include 'PSR2KeywordsLowerCase.php';
include 'PSR2LnAfterNamespace.php';
include 'PSR2ModifierVisibilityStaticOrder.php';
include 'PSR2SingleEmptyLineAndStripClosingTag.php';
include 'PsrDecorator.php';

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
		$start = microtime(true);
		$timings = [];
		gc_enable();
		$passes = array_map(
			function ($pass) {
				return clone $pass;
			},
			$this->passes
		);
		while (($pass = array_shift($passes))) {
			$source = $pass->format($source);
			$timings[get_class($pass)] = microtime(true);
			gc_collect_cycles();
		}
		gc_disable();
		$delta = $start;
		$total = 0;
		$nameLen = 0;
		foreach ($timings as $pass => $timestamp) {
			$total += $timestamp - $delta;
			$delta = $timestamp;
			$nameLen = max(strlen($pass), $nameLen);
		}
		$delta = $start;
		$lines = [];
		foreach ($timings as $pass => $timestamp) {
			$proportion = ($timestamp - $delta) / $total;
			$lines[] = [
				str_pad($pass, $nameLen + 1)
				. ' ' .
				str_pad(round(($proportion * 100), 2), 5, ' ', STR_PAD_LEFT)
				. '% ' .
				str_pad(
					str_repeat('|',
						round(($proportion * 50), 0)
					),
					50,
					' '
				)
				. ' ' .
				($timestamp - $delta),
				$proportion
			];
			$delta = $timestamp;
		}
		usort($lines, function ($a, $b) {
			return $a[1] < $b[1];
		});
		$this->debug && fwrite(STDERR, implode(PHP_EOL, array_map(function ($v) {return $v[0];}, $lines)) . PHP_EOL);
		$this->debug && fwrite(STDERR, 'Total: ' . $total . PHP_EOL);
		return $source;
	}
}
if (!isset($testEnv)) {
	$opts = getopt('vho:', ['timing', 'purge_empty_line', 'help', 'setters_and_getters::', 'refactor:', 'to:', 'psr', 'psr1', 'psr2', 'indent_with_space', 'disable_auto_align', 'visibility_order']);
	if (isset($opts['h']) || isset($opts['help'])) {
		echo 'Usage: ' . $argv[0] . ' [-ho] [--setters_and_getters=type] [--refactor=from --to=to] [--psr] [--psr1] [--psr2] [--indent_with_space] [--disable_auto_align] [--visibility_order] <target>', PHP_EOL;
		$options = [
			'--disable_auto_align' => 'disable auto align of ST_EQUAL and T_DOUBLE_ARROW',
			'--indent_with_space' => 'use spaces instead of tabs for indentation',
			'--psr' => 'activate PSR1 and PSR2 styles',
			'--psr1' => 'activate PSR1 style',
			'--psr2' => 'activate PSR2 style',
			'--purge_empty_line=policy' => 'purge empty lines. policies: aggressive (1 line), mild (5 lines)',
			'--refactor=from, --to=to' => 'Search for "from" and replace with "to" - context aware search and replace',
			'--setters_and_getters=type' => 'analyse classes for attributes and generate setters and getters - camel, snake, golang',
			'--visibility_order' => 'fixes visibiliy order for method in classes. PSR-2 4.2',
			'-h, --help' => 'this help message',
			'-o=file' => 'output the formatted code to "file"',
			'-v, --timing' => 'timing',
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
	if (isset($opts['refactor']) && !isset($opts['to'])) {
		fwrite(STDERR, "Refactor must have --refactor (from) and --to (to) parameters" . PHP_EOL);
		exit(255);
	}

	$debug = false;
	if (isset($opts['v']) || isset($opts['timing'])) {
		$debug = true;
		$argv = array_values(
			array_filter($argv,
				function ($v) {
					return !($v === '-v' || $v === '--timing');
				}
			)
		);
	}

	$fmt = new CodeFormatter($debug);
	$fmt->addPass(new TwoCommandsInSameLine());
	if (isset($opts['setters_and_getters'])) {
		$argv = array_values(
			array_filter($argv,
				function ($v) {
					return substr($v, 0, strlen('--setters_and_getters')) !== '--setters_and_getters';
				}
			)
		);
		$fmt->addPass(new SettersAndGettersPass($opts['setters_and_getters']));
	}
	$fmt->addPass(new OrderUseClauses());
	$fmt->addPass(new AddMissingCurlyBraces());
	$fmt->addPass(new NormalizeLnAndLtrimLines());
	$fmt->addPass(new MergeParenCloseWithCurlyOpen());
	$fmt->addPass(new MergeCurlyCloseAndDoWhile());
	$fmt->addPass(new MergeDoubleArrowAndArray());
	$fmt->addPass(new ExtraCommaInArray());
	$fmt->addPass(new ResizeSpaces());
	$fmt->addPass(new Reindent());
	$fmt->addPass(new ReindentColonBlocks());
	$fmt->addPass(new ReindentLoopColonBlocks());
	$fmt->addPass(new ReindentIfColonBlocks());
	$fmt->addPass(new ReindentObjOps());
	if (isset($opts['purge_empty_line'])) {
		$fmt->addPass(new EliminateDuplicatedEmptyLines($opts['purge_empty_line']));
		$argv = array_values(
			array_filter($argv,
				function ($v) {
					return substr($v, 0, strlen('--purge_empty_line')) !== '--purge_empty_line';
				}
			)
		);
	} else {
		$fmt->addPass(new EliminateDuplicatedEmptyLines());
	}

	if (!isset($opts['disable_auto_align'])) {
		$fmt->addPass(new AlignEquals());
		$fmt->addPass(new AlignDoubleArrow());
	} else {
		$argv = array_values(
			array_filter($argv,
				function ($v) {
					return $v !== '--disable_auto_align';
				}
			)
		);
	}
	if (isset($opts['indent_with_space'])) {
		$fmt->addPass(new PSR2IndentWithSpace());
		$argv = array_values(
			array_filter($argv,
				function ($v) {
					return $v !== '--indent_with_space';
				}
			)
		);
	}
	if (isset($opts['psr'])) {
		PsrDecorator::decorate($fmt);
		$argv = array_values(
			array_filter($argv,
				function ($v) {
					return $v !== '--psr';
				}
			)
		);
	}
	if (isset($opts['psr1'])) {
		PsrDecorator::PSR1($fmt);
		$argv = array_values(
			array_filter($argv,
				function ($v) {
					return $v !== '--psr1';
				}
			)
		);
	}
	if (isset($opts['psr2'])) {
		PsrDecorator::PSR2($fmt);
		$argv = array_values(
			array_filter($argv,
				function ($v) {
					return $v !== '--psr2';
				}
			)
		);
	}
	if (isset($opts['visibility_order'])) {
		$fmt->addPass(new PSR2ModifierVisibilityStaticOrder());
		$argv = array_values(
			array_filter($argv,
				function ($v) {
					return $v !== '--visibility_order';
				}
			)
		);
	}
	$fmt->addPass(new LeftAlignComment());
	$fmt->addPass(new RTrim());

	if (isset($opts['refactor']) && isset($opts['to'])) {
		$argv = array_values(
			array_filter($argv,
				function ($v) {
					$param_from = '--refactor';
					$param_to = '--to';
					return substr($v, 0, strlen($param_from)) !== $param_from && substr($v, 0, strlen($param_to)) !== $param_to;
				}
			)
		);
		$fmt->addPass(new Refactor($opts['refactor'], $opts['to']));
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
			file_put_contents($file . '-tmp', $fmt->formatCode(file_get_contents($file)));
			rename($file, $file . '~');
			rename($file . '-tmp', $file);
			echo PHP_EOL;
		}
	} else {
		echo $fmt->formatCode(file_get_contents('php://stdin'));
	}
}
