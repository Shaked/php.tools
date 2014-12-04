#!/usr/bin/env php
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
$concurrent = function_exists('pcntl_fork');
if ($concurrent) {
	include 'csp.php';
}
include 'constants.php';
include 'FormatterPass.php';
include 'AdditionalPass.php';
include 'AddMissingCurlyBraces.php';
include 'AddMissingParentheses.php';
include 'AlignDoubleArrow.php';
include 'AlignEquals.php';
include 'AutoImport.php';
include 'AutoPreincrement.php';
include 'ConstructorPass.php';
include 'WrongConstructorName.php';
include 'EliminateDuplicatedEmptyLines.php';
include 'EncapsulateNamespaces.php';
include 'ExtraCommaInArray.php';
include 'GeneratePHPDoc.php';
include 'JoinToImplode.php';
include 'LeftAlignComment.php';
include 'MergeCurlyCloseAndDoWhile.php';
include 'MergeDoubleArrowAndArray.php';
include 'MergeElseIf.php';
include 'MergeParenCloseWithCurlyOpen.php';
include 'NormalizeLnAndLtrimLines.php';
include 'NormalizeIsNotEquals.php';
include 'OrderMethod.php';
include 'OrderUseClauses.php';
include 'Reindent.php';
include 'ReindentColonBlocks.php';
include 'ReindentIfColonBlocks.php';
include 'ReindentLoopColonBlocks.php';
include 'ReindentObjOps.php';
include 'RemoveUseLeadingSlash.php';
include 'RemoveIncludeParentheses.php';
include 'ResizeSpaces.php';
include 'ReturnNull.php';
include 'RTrim.php';
include 'SettersAndGettersPass.php';
include 'ShortArray.php';
include 'SmartLnAfterCurlyOpen.php';
include 'SpaceBetweenMethods.php';
include 'SurrogateToken.php';
include 'TightConcat.php';
include 'TwoCommandsInSameLine.php';
include 'YodaComparisons.php';
//PSR standards
include 'PSR1BOMMark.php';
include 'PSR1ClassConstants.php';
include 'PSR1ClassNames.php';
include 'PSR1MethodNames.php';
include 'PSR1OpenTags.php';
include 'PSR2AlignObjOp.php';
include 'PSR2CurlyOpenNextLine.php';
include 'PSR2IndentWithSpace.php';
include 'PSR2KeywordsLowerCase.php';
include 'PSR2LnAfterNamespace.php';
include 'PSR2ModifierVisibilityStaticOrder.php';
include 'PSR2SingleEmptyLineAndStripClosingTag.php';
include 'PsrDecorator.php';
//Other Styles
include 'LaravelStyle.php';
include 'CakePHPStyle.php';

include 'Cache.php';
include 'CodeFormatter.php';

function extract_from_argv($argv, $item) {
	return array_values(
		array_filter($argv,
			function ($v) use ($item) {
				return substr($v, 0, strlen('--' . $item)) !== '--' . $item;
			}
		)
	);
}

if (!isset($testEnv)) {
	function show_help($argv) {
		echo 'Usage: ' . $argv[0] . ' [-ho] [--config=FILENAME] [--cache[=FILENAME]] [--setters_and_getters=type] [--constructor=type] [--psr] [--psr1] [--psr1-naming] [--psr2] [--indent_with_space=SIZE] [--enable_auto_align] [--visibility_order] <target>', PHP_EOL;
		$options = [
			'--cache[=FILENAME]' => 'cache file. Default: ' . (Cache::DEFAULT_CACHE_FILENAME),
			'--cakephp' => 'Apply CakePHP coding style',
			'--config=FILENAME' => 'configuration file. Default: .php.tools.ini',
			'--constructor=type' => 'analyse classes for attributes and generate constructor - camel, snake, golang',
			'--enable_auto_align' => 'disable auto align of ST_EQUAL and T_DOUBLE_ARROW',
			'--ignore=PATTERN1,PATTERN2' => 'ignore file names whose names contain any PATTERN-N',
			'--indent_with_space=SIZE' => 'use spaces instead of tabs for indentation. Default 4',
			'--laravel' => 'Apply Laravel coding style',
			'--list' => 'list possible transformations',
			'--no-backup' => 'no backup file (original.php~)',
			'--passes=pass1,passN' => 'call specific compiler pass',
			'--prepasses=pass1,passN' => 'call specific compiler pass, before the rest of stack',
			'--psr' => 'activate PSR1 and PSR2 styles',
			'--psr1' => 'activate PSR1 style',
			'--psr1-naming' => 'activate PSR1 style - Section 3 and 4.3 - Class and method names case.',
			'--psr2' => 'activate PSR2 style',
			'--setters_and_getters=type' => 'analyse classes for attributes and generate setters and getters - camel, snake, golang',
			'--smart_linebreak_after_curly' => 'convert multistatement blocks into multiline blocks',
			'--visibility_order' => 'fixes visibiliy order for method in classes. PSR-2 4.2',
			'--yoda' => 'yoda-style comparisons',
			'-h, --help' => 'this help message',
			'-o=file' => 'output the formatted code to "file"',
		];
		$maxLen = max(array_map(function ($v) {
			return strlen($v);
		}, array_keys($options)));
		foreach ($options as $k => $v) {
			echo '  ', str_pad($k, $maxLen), '  ', $v, PHP_EOL;
		}
		echo PHP_EOL, 'If - is blank, it reads from stdin', PHP_EOL;
		die();
	}
	$opts = getopt(
		'iho:',
		[
			'cache::',
			'cakephp',
			'config:',
			'constructor:',
			'enable_auto_align',
			'help',
			'help-pass:',
			'ignore:',
			'indent_with_space::',
			'laravel',
			'list',
			'no-backup',
			'oracleDB::',
			'passes:',
			'prepasses:',
			'psr',
			'psr1',
			'psr1-naming',
			'psr2',
			'setters_and_getters:',
			'smart_linebreak_after_curly',
			'visibility_order',
			'yoda',
		]
	);
	if (isset($opts['config'])) {
		$argv = extract_from_argv($argv, 'config');
		if (!file_exists($opts['config']) || !is_file($opts['config'])) {
			fwrite(STDERR, 'Custom configuration not file found' . PHP_EOL);
			exit(255);
		}
		$ini_opts = parse_ini_file($opts['config']);
		if (!empty($ini_opts)) {
			$opts = $ini_opts;
		}
	} elseif (file_exists('.php.tools.ini') && is_file('.php.tools.ini')) {
		fwrite(STDERR, 'Configuration file found' . PHP_EOL);
		$opts = parse_ini_file('.php.tools.ini');

	}
	if (isset($opts['h']) || isset($opts['help'])) {
		show_help($argv);
	}

	if (isset($opts['help-pass'])) {
		$optPass = $opts['help-pass'];
		if (class_exists($optPass)) {
			$pass = new $optPass();
			echo $argv[0], ': "', $optPass, '" - ', $pass->get_description(), PHP_EOL, PHP_EOL;
			echo 'Example:', PHP_EOL, $pass->get_example(), PHP_EOL;
		}
		die();
	}

	if (isset($opts['list'])) {
		echo 'Usage: ', $argv[0], ' --help-pass=PASSNAME', PHP_EOL;
		$classes = get_declared_classes();
		foreach ($classes as $class_name) {
			if (is_subclass_of($class_name, 'AdditionalPass')) {
				echo "\t- ", $class_name, PHP_EOL;
			}
		}
		die();
	}

	$cache = null;
	if (isset($opts['cache'])) {
		$argv = extract_from_argv($argv, 'cache');
		$cache = new Cache($opts['cache']);
		fwrite(STDERR, 'Using cache ...' . PHP_EOL);
	}
	$backup = true;
	if (isset($opts['no-backup'])) {
		$argv = extract_from_argv($argv, 'no-backup');
		$backup = false;
	}

	$ignore_list = null;
	if (isset($opts['ignore'])) {
		$argv = extract_from_argv($argv, 'ignore');
		$ignore_list = array_map(function ($v) {
			return trim($v);
		}, explode(',', $opts['ignore']));
	}

	$fmt = new CodeFormatter();
	if (isset($opts['prepasses'])) {
		$optPasses = array_map(function ($v) {
			return trim($v);
		}, explode(',', $opts['prepasses']));
		foreach ($optPasses as $optPass) {
			if (class_exists($optPass)) {
				$fmt->addPass(new $optPass());
			}
		}
		$argv = extract_from_argv($argv, 'prepasses');
	}
	$fmt->addPass(new TwoCommandsInSameLine());
	$fmt->addPass(new RemoveIncludeParentheses());
	$fmt->addPass(new NormalizeIsNotEquals());
	if (isset($opts['setters_and_getters'])) {
		$argv = extract_from_argv($argv, 'setters_and_getters');
		$fmt->addPass(new SettersAndGettersPass($opts['setters_and_getters']));
	}
	if (isset($opts['constructor'])) {
		$argv = extract_from_argv($argv, 'constructor');
		$fmt->addPass(new ConstructorPass($opts['constructor']));
	}
	if (isset($opts['oracleDB'])) {
		$argv = extract_from_argv($argv, 'oracleDB');
		$fmt->addPass(new AutoImportPass($opts['oracleDB']));
	}

	$fmt->addPass(new OrderUseClauses());
	$fmt->addPass(new AddMissingCurlyBraces());
	if (isset($opts['smart_linebreak_after_curly'])) {
		$fmt->addPass(new SmartLnAfterCurlyOpen());
		$argv = extract_from_argv($argv, 'smart_linebreak_after_curly');
	}
	$fmt->addPass(new ExtraCommaInArray());
	$fmt->addPass(new NormalizeLnAndLtrimLines());
	$fmt->addPass(new MergeParenCloseWithCurlyOpen());
	$fmt->addPass(new MergeCurlyCloseAndDoWhile());
	$fmt->addPass(new MergeDoubleArrowAndArray());

	if (isset($opts['yoda'])) {
		$fmt->addPass(new YodaComparisons());
		$argv = extract_from_argv($argv, 'yoda');
	}

	$fmt->addPass(new ResizeSpaces());
	$fmt->addPass(new Reindent());
	$fmt->addPass(new ReindentColonBlocks());
	$fmt->addPass(new ReindentLoopColonBlocks());
	$fmt->addPass(new ReindentIfColonBlocks());

	if (isset($opts['enable_auto_align'])) {
		$fmt->addPass(new AlignEquals());
		$fmt->addPass(new AlignDoubleArrow());
		$argv = extract_from_argv($argv, 'enable_auto_align');
	}

	$fmt->addPass(new ReindentObjOps());
	$fmt->addPass(new EliminateDuplicatedEmptyLines());

	if (isset($opts['indent_with_space'])) {
		$fmt->addPass(new PSR2IndentWithSpace($opts['indent_with_space']));
		$argv = extract_from_argv($argv, 'indent_with_space');
	}
	if (isset($opts['psr'])) {
		PsrDecorator::decorate($fmt);
		$argv = extract_from_argv($argv, 'psr');
	}
	if (isset($opts['psr1'])) {
		PsrDecorator::PSR1($fmt);
		$argv = extract_from_argv($argv, 'psr1');
	}
	if (isset($opts['psr1-naming'])) {
		PsrDecorator::PSR1_naming($fmt);
		$argv = extract_from_argv($argv, 'psr1-naming');
	}
	if (isset($opts['psr2'])) {
		PsrDecorator::PSR2($fmt);
		$argv = extract_from_argv($argv, 'psr2');
	}
	if ((isset($opts['psr1']) || isset($opts['psr2']) || isset($opts['psr'])) && isset($opts['enable_auto_align'])) {
		$fmt->addPass(new PSR2AlignObjOp());
	}

	if (isset($opts['visibility_order'])) {
		$fmt->addPass(new PSR2ModifierVisibilityStaticOrder());
		$argv = extract_from_argv($argv, 'visibility_order');
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
		$argv = extract_from_argv($argv, 'passes');
	}

	if (isset($opts['laravel'])) {
		$fmt->addPass(new LaravelStyle());
		$argv = extract_from_argv($argv, 'laravel');
	}

	if (isset($opts['cakephp'])) {
		$fmt->addPass(new CakePHPStyle());
		$argv = extract_from_argv($argv, 'cakephp');
	}

	if (isset($opts['i'])) {
		echo 'php.tools fmt.php interactive mode.', PHP_EOL;
		echo 'no <?php is necessary', PHP_EOL;
		echo 'type a lone "." to finish input.', PHP_EOL;
		echo 'type "quit" to finish.', PHP_EOL;
		while (true) {
			$str = '';
			do {
				$line = readline('> ');
				$str .= $line;
			} while (!('.' == $line || 'quit' == $line));
			if ('quit' == $line) {
				exit(0);
			}
			readline_add_history(substr($str, 0, -1));
			echo $fmt->formatCode('<?php ' . substr($str, 0, -1)), PHP_EOL;
		}
	} elseif (isset($opts['o'])) {
		if (!is_file($argv[1])) {
			fwrite(STDERR, "File not found: " . $argv[1] . PHP_EOL);
			exit(255);
		}
		unset($argv[1]);
		unset($argv[2]);
		$argv = array_values($argv);
		file_put_contents($opts['o'], $fmt->formatCode(file_get_contents($argv[1])));
	} elseif (isset($argv[1])) {
		if ('-' == $argv[1]) {
			echo $fmt->formatCode(file_get_contents('php://stdin'));
			exit(0);
		}
		$file_not_found = false;
		$start = microtime(true);
		fwrite(STDERR, 'Formatting ...' . PHP_EOL);
		$missing_files = [];
		$file_count = 0;

		$cache_hit_count = 0;
		$workers = 2;
		if ($concurrent) {
			fwrite(STDERR, 'Starting ' . $workers . ' workers ...' . PHP_EOL);
		}
		for ($i = 1; $i < $argc; ++$i) {
			if (!isset($argv[$i])) {
				continue;
			}
			if (is_file($argv[$i])) {
				$file = $argv[$i];
				++$file_count;
				fwrite(STDERR, '.');
				file_put_contents($file . '-tmp', $fmt->formatCode(file_get_contents($file)));
				rename($file . '-tmp', $file);
			} elseif (is_dir($argv[$i])) {
				$target_dir = $argv[$i];
				$dir = new RecursiveDirectoryIterator($target_dir);
				$it = new RecursiveIteratorIterator($dir);
				$files = new RegexIterator($it, '/^.+\.php$/i', RecursiveRegexIterator::GET_MATCH);

				if ($concurrent) {

					$chn = make_channel();
					$chn_file_done = make_channel();
					$chn_done = make_channel();
					cofunc(function ($chn_file_done) {
						$count = 0;
						while (true) {
							$chn_file_done->out();
							++$count;
							fwrite(STDERR, '.');
							if ($count % 20 == 0) {
								fwrite(STDERR, PHP_EOL);
							}
						}
					}, $chn_file_done);
					for ($i = 0; $i < $workers; ++$i) {
						cofunc(function ($fmt, $backup, $cache, $chn, $chn_done, $chn_file_done) {
							$cache_hit_count = 0;
							$cache_miss_count = 0;
							while (true) {
								$msg = $chn->out();
								if ('done' == $msg) {
									break;
								}
								$target_dir = $msg['target_dir'];
								$file = $msg['file'];
								$chn_file_done->in(1);
								if (null !== $cache) {
									$content = $cache->is_changed($target_dir, $file);
									if (!$content) {
										++$cache_hit_count;
										continue;
									}
								} else {
									$content = file_get_contents($file);
								}
								++$cache_miss_count;
								$fmtCode = $fmt->formatCode($content);
								if (null !== $cache) {
									$cache->upsert($target_dir, $file, $fmtCode);
								}
								file_put_contents($file . '-tmp', $fmtCode);
								$backup && rename($file, $file . '~');
								rename($file . '-tmp', $file);
							}
							$chn_done->in([$cache_hit_count, $cache_miss_count]);
						}, $fmt, $backup, $cache, $chn, $chn_done, $chn_file_done);
					}
				}
				foreach ($files as $file) {
					$file = $file[0];
					if (null !== $ignore_list) {
						foreach ($ignore_list as $pattern) {
							if (false !== strpos($file, $pattern)) {
								continue 2;
							}
						}
					}

					++$file_count;
					if ($concurrent) {
						$chn->in([
							'target_dir' => $target_dir,
							'file' => $file,
						]);
					} else {
						if (0 == ($file_count % 20)) {
							fwrite(STDERR, ' ' . $file_count . PHP_EOL);
						}
						if (null !== $cache) {
							$content = $cache->is_changed($target_dir, $file);
							if (!$content) {
								++$file_count;
								++$cache_hit_count;
								continue;
							}
						} else {
							$content = file_get_contents($file);
						}
						$fmtCode = $fmt->formatCode($content);
						fwrite(STDERR, '.');
						if (null !== $cache) {
							$cache->upsert($target_dir, $file, $fmtCode);
						}
						file_put_contents($file . '-tmp', $fmtCode);
						$backup && rename($file, $file . '~');
						rename($file . '-tmp', $file);
					}

				}
				if ($concurrent) {
					for ($i = 0; $i < $workers; ++$i) {
						$chn->in('done');
					}
					for ($i = 0; $i < $workers; ++$i) {
						list($cache_hit, $cache_miss) = $chn_done->out();
						$cache_hit_count += $cache_hit;
					}
				}
				continue;
			} elseif (!is_file($argv[$i])) {
				$file_not_found = true;
				$missing_files[] = $argv[$i];
				fwrite(STDERR, '!');
			}
			if (0 == ($file_count % 20)) {
				fwrite(STDERR, ' ' . $file_count . PHP_EOL);
			}
		}
		fwrite(STDERR, PHP_EOL);
		if (null !== $cache) {
			fwrite(STDERR, ' ' . $cache_hit_count . ' files untouched (cache hit)' . PHP_EOL);
		}
		fwrite(STDERR, ' ' . $file_count . ' files total' . PHP_EOL);
		fwrite(STDERR, 'Took ' . round(microtime(true) - $start, 2) . 's' . PHP_EOL);
		if (sizeof($missing_files)) {
			fwrite(STDERR, "Files not found: " . PHP_EOL);
			foreach ($missing_files as $file) {
				fwrite(STDERR, "\t - " . $file . PHP_EOL);
			}
		}

		if ($file_not_found) {
			exit(255);
		}
	} else {
		show_help($argv);
	}
	exit(0);
}
