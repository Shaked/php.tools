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
include 'AutoImport.php';
include 'AutoPreincrement.php';
include 'ConstructorPass.php';
include 'EliminateDuplicatedEmptyLines.php';
include 'ExtraCommaInArray.php';
include 'LeftAlignComment.php';
include 'MergeCurlyCloseAndDoWhile.php';
include 'MergeDoubleArrowAndArray.php';
include 'MergeElseIf.php';
include 'MergeParenCloseWithCurlyOpen.php';
include 'NormalizeLnAndLtrimLines.php';
include 'OrderMethod.php';
include 'OrderUseClauses.php';
include 'Reindent.php';
include 'ReindentColonBlocks.php';
include 'ReindentIfColonBlocks.php';
include 'ReindentLoopColonBlocks.php';
include 'ReindentObjOps.php';
include 'ResizeSpaces.php';
include 'RTrim.php';
include 'SettersAndGettersPass.php';
include 'ShortArray.php';
include 'SmartLnAfterCurlyOpen.php';
include 'SurrogateToken.php';
include 'TwoCommandsInSameLine.php';
include 'YodaComparisons.php';
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
		array_unshift($this->passes, $pass);
	}

	public function formatCode($source = '') {
		$passes = array_map(
			function ($pass) {
				return clone $pass;
			},
			$this->passes
		);
		while (($pass = array_pop($passes))) {
			$source = $pass->format($source);
		}
		return $source;
	}
}
if (!isset($testEnv)) {
	$opts = getopt('vho:', ['yoda', 'smart_linebreak_after_curly', 'passes:', 'oracleDB::', 'timing', 'help', 'setters_and_getters:', 'constructor:', 'psr', 'psr1', 'psr2', 'indent_with_space', 'disable_auto_align', 'visibility_order']);
	if (isset($opts['h']) || isset($opts['help'])) {
		echo 'Usage: ' . $argv[0] . ' [-ho] [--setters_and_getters=type] [--constructor=type] [--psr] [--psr1] [--psr2] [--indent_with_space] [--disable_auto_align] [--visibility_order] <target>', PHP_EOL;
		$options = [
			'--constructor=type' => 'analyse classes for attributes and generate constructor - camel, snake, golang',
			'--disable_auto_align' => 'disable auto align of ST_EQUAL and T_DOUBLE_ARROW',
			'--indent_with_space' => 'use spaces instead of tabs for indentation',
			'--passes=pass1,passN' => 'call specific compiler pass',
			'--psr' => 'activate PSR1 and PSR2 styles',
			'--psr1' => 'activate PSR1 style',
			'--psr2' => 'activate PSR2 style',
			'--setters_and_getters=type' => 'analyse classes for attributes and generate setters and getters - camel, snake, golang',
			'--smart_linebreak_after_curly' => 'convert multistatement blocks into multiline blocks',
			'--visibility_order' => 'fixes visibiliy order for method in classes. PSR-2 4.2',
			'--yoda' => 'yoda-style comparisons',
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

	$debug = false;
	if (isset($opts['v']) || isset($opts['timing'])) {
		$debug = true;
		$argv = array_values(
			array_filter($argv,
				function ($v) {
					return !('-v' === $v || '--timing' === $v);
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
	if (isset($opts['constructor'])) {
		$argv = array_values(
			array_filter($argv,
				function ($v) {
					return substr($v, 0, strlen('--constructor')) !== '--constructor';
				}
			)
		);
		$fmt->addPass(new ConstructorPass($opts['constructor']));
	}
	if (isset($opts['oracleDB'])) {
		$argv = array_values(
			array_filter($argv,
				function ($v) {
					return substr($v, 0, strlen('--oracleDB')) !== '--oracleDB';
				}
			)
		);
		$fmt->addPass(new AutoImportPass($opts['oracleDB']));
	}

	$fmt->addPass(new OrderUseClauses());
	$fmt->addPass(new AddMissingCurlyBraces());
	if (isset($opts['smart_linebreak_after_curly'])) {
		$fmt->addPass(new SmartLnAfterCurlyOpen());
		$argv = array_values(
			array_filter($argv,
				function ($v) {
					return '--smart_linebreak_after_curly' !== $v;
				}
			)
		);
	}
	$fmt->addPass(new NormalizeLnAndLtrimLines());
	$fmt->addPass(new MergeParenCloseWithCurlyOpen());
	$fmt->addPass(new MergeCurlyCloseAndDoWhile());
	$fmt->addPass(new MergeDoubleArrowAndArray());
	$fmt->addPass(new ExtraCommaInArray());

	if (isset($opts['yoda'])) {
		$fmt->addPass(new YodaComparisons());
		$argv = array_values(
			array_filter($argv,
				function ($v) {
					return '--yoda' !== $v;
				}
			)
		);
	}

	$fmt->addPass(new ResizeSpaces());
	$fmt->addPass(new Reindent());
	$fmt->addPass(new ReindentColonBlocks());
	$fmt->addPass(new ReindentLoopColonBlocks());
	$fmt->addPass(new ReindentIfColonBlocks());
	$fmt->addPass(new ReindentObjOps());
	$fmt->addPass(new EliminateDuplicatedEmptyLines());

	if (!isset($opts['disable_auto_align'])) {
		$fmt->addPass(new AlignEquals());
		$fmt->addPass(new AlignDoubleArrow());
	} else {
		$argv = array_values(
			array_filter($argv,
				function ($v) {
					return '--disable_auto_align' !== $v;
				}
			)
		);
	}
	if (isset($opts['indent_with_space'])) {
		$fmt->addPass(new PSR2IndentWithSpace());
		$argv = array_values(
			array_filter($argv,
				function ($v) {
					return '--indent_with_space' !== $v;
				}
			)
		);
	}
	if (isset($opts['psr'])) {
		PsrDecorator::decorate($fmt);
		$argv = array_values(
			array_filter($argv,
				function ($v) {
					return '--psr' !== $v;
				}
			)
		);
	}
	if (isset($opts['psr1'])) {
		PsrDecorator::PSR1($fmt);
		$argv = array_values(
			array_filter($argv,
				function ($v) {
					return '--psr1' !== $v;
				}
			)
		);
	}
	if (isset($opts['psr2'])) {
		PsrDecorator::PSR2($fmt);
		$argv = array_values(
			array_filter($argv,
				function ($v) {
					return '--psr2' !== $v;
				}
			)
		);
	}
	if (isset($opts['visibility_order'])) {
		$fmt->addPass(new PSR2ModifierVisibilityStaticOrder());
		$argv = array_values(
			array_filter($argv,
				function ($v) {
					return '--visibility_order' !== $v;
				}
			)
		);
	}
	$fmt->addPass(new LeftAlignComment());
	$fmt->addPass(new RTrim());

	if (isset($opts['passes'])) {
		$optPasses = array_map(function ($v) {
			return trim($v);
		}, explode(',', $opts['passes']));
		foreach ($optPasses as $optPass) {
			if (class_exists($optPass)) {
				$fmt->addPass(new $optPass());
			}
		}
		$argv = array_values(
			array_filter($argv,
				function ($v) {
					return substr($v, 0, strlen('--passes')) !== '--passes';
				}
			)
		);
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
