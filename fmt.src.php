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
	require 'vendor/dericofilho/csp/csp.php';
}
$enable_cache = false;
if (class_exists('SQLite3')) {
	$enable_cache = true;
	require 'Core/Cache.php';
}
require 'Core/constants.php';
require 'Core/FormatterPass.php';
require 'Additionals/AdditionalPass.php';
require 'Core/CodeFormatter.php';

require 'Core/AddMissingCurlyBraces.php';
require 'Core/AutoImport.php';
require 'Core/ConstructorPass.php';
require 'Core/EliminateDuplicatedEmptyLines.php';
require 'Core/ExtraCommaInArray.php';
require 'Core/LeftAlignComment.php';
require 'Core/MergeCurlyCloseAndDoWhile.php';
require 'Core/MergeDoubleArrowAndArray.php';
require 'Core/MergeParenCloseWithCurlyOpen.php';
require 'Core/NormalizeIsNotEquals.php';
require 'Core/NormalizeLnAndLtrimLines.php';
require 'Core/OrderUseClauses.php';
require 'Core/Reindent.php';
require 'Core/ReindentColonBlocks.php';
require 'Core/ReindentIfColonBlocks.php';
require 'Core/ReindentLoopColonBlocks.php';
require 'Core/ReindentObjOps.php';
require 'Core/RemoveIncludeParentheses.php';
require 'Core/ResizeSpaces.php';
require 'Core/RTrim.php';
require 'Core/SettersAndGettersPass.php';
require 'Core/SurrogateToken.php';
require 'Core/TwoCommandsInSameLine.php';

require 'PSR/PSR1BOMMark.php';
require 'PSR/PSR1ClassConstants.php';
require 'PSR/PSR1ClassNames.php';
require 'PSR/PSR1MethodNames.php';
require 'PSR/PSR1OpenTags.php';
require 'PSR/PSR2AlignObjOp.php';
require 'PSR/PSR2CurlyOpenNextLine.php';
require 'PSR/PSR2IndentWithSpace.php';
require 'PSR/PSR2KeywordsLowerCase.php';
require 'PSR/PSR2LnAfterNamespace.php';
require 'PSR/PSR2ModifierVisibilityStaticOrder.php';
require 'PSR/PSR2SingleEmptyLineAndStripClosingTag.php';
require 'PSR/PsrDecorator.php';

require 'Additionals/AddMissingParentheses.php';
require 'Additionals/AlignDoubleArrow.php';
require 'Additionals/AlignEquals.php';
require 'Additionals/AutoPreincrement.php';
require 'Additionals/CakePHPStyle.php';
require 'Additionals/EncapsulateNamespaces.php';
require 'Additionals/GeneratePHPDoc.php';
require 'Additionals/JoinToImplode.php';
require 'Additionals/LaravelStyle.php';
require 'Additionals/MergeElseIf.php';
require 'Additionals/MildAutoPreincrement.php';
require 'Additionals/NoSpaceAfterPHPDocBlocks.php';
require 'Additionals/OrderMethod.php';
require 'Additionals/RemoveUseLeadingSlash.php';
require 'Additionals/ReturnNull.php';
require 'Additionals/ShortArray.php';
require 'Additionals/SmartLnAfterCurlyOpen.php';
require 'Additionals/SpaceBetweenMethods.php';
require 'Additionals/TightConcat.php';
require 'Additionals/WrongConstructorName.php';
require 'Additionals/YodaComparisons.php';

function extract_from_argv($argv, $item) {
	return array_values(
		array_filter($argv,
			function ($v) use ($item) {
				return substr($v, 0, strlen('--' . $item)) !== '--' . $item;
			}
		)
	);
}

function extract_from_argv_short($argv, $item) {
	return array_values(
		array_filter($argv,
			function ($v) use ($item) {
				return substr($v, 0, strlen('-' . $item)) !== '-' . $item;
			}
		)
	);
}
if (!isset($in_phar)) {
	$in_phar = false;
}
if (!isset($testEnv)) {
	function show_help($argv, $enable_cache, $in_phar) {
		echo 'Usage: ' . $argv[0] . ' [-ho] [--config=FILENAME] ' . ($enable_cache ? '[--cache[=FILENAME]] ' : '') . '[--setters_and_getters=type] [--constructor=type] [--psr] [--psr1] [--psr1-naming] [--psr2] [--indent_with_space=SIZE] [--enable_auto_align] [--visibility_order] <target>', PHP_EOL;
		$options = [
			'--cache[=FILENAME]' => 'cache file. Default: ',
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
			'--profile=NAME' => 'use one of profiles present in configuration file',
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
		if ($in_phar) {
			$options['--selfupdate'] = 'self-update fmt.phar from Github';
		}
		if (!$enable_cache) {
			unset($options['--cache[=FILENAME]']);
		} else {
			$options['--cache[=FILENAME]'] .= (Cache::DEFAULT_CACHE_FILENAME);
		}
		ksort($options);
		$maxLen = max(array_map(function ($v) {
			return strlen($v);
		}, array_keys($options)));
		foreach ($options as $k => $v) {
			echo '  ', str_pad($k, $maxLen), '  ', $v, PHP_EOL;
		}
		echo PHP_EOL, 'If - is blank, it reads from stdin', PHP_EOL;
		die();
	}
	$getopt_long_options = [
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
		'profile:',
		'psr',
		'psr1',
		'psr1-naming',
		'psr2',
		'setters_and_getters:',
		'smart_linebreak_after_curly',
		'visibility_order',
		'yoda',
	];
	if ($in_phar) {
		$getopt_long_options[] = 'selfupdate';
	}
	if (!$enable_cache) {
		unset($getopt_long_options['cache::']);
	}
	$opts = getopt(
		'iho:',
		$getopt_long_options
	);
	if (isset($opts['selfupdate'])) {
		$opts = [
			'http' => [
				'method' => "GET",
				'header' => "User-agent: php.tools fmt.phar selfupdate\r\n",
			],
		];

		$context = stream_context_create($opts);

		// current release
		$releases = json_decode(file_get_contents('https://api.github.com/repos/dericofilho/php.tools/tags', false, $context), true);
		$commit = json_decode(file_get_contents($releases[0]['commit']['url'], false, $context), true);
		$files = json_decode(file_get_contents($commit['commit']['tree']['url'], false, $context), true);
		foreach ($files['tree'] as $file) {
			if ('fmt.phar' == $file['path']) {
				$phar_file = base64_decode(json_decode(file_get_contents($file['url'], false, $context), true)['content']);
			}
			if ('fmt.phar.sha1' == $file['path']) {
				$phar_sha1 = base64_decode(json_decode(file_get_contents($file['url'], false, $context), true)['content']);
			}
		}
		if (!isset($phar_sha1) || !isset($phar_file)) {
			fwrite(STDERR, 'Could not autoupdate - not release found' . PHP_EOL);
			exit(255);
		}
		if ($in_phar) {
			$argv[0] = dirname(Phar::running(false)) . DIRECTORY_SEPARATOR . $argv[0];
		}
		if (sha1_file($argv[0]) != $phar_sha1) {
			copy($argv[0], $argv[0] . "~");
			file_put_contents($argv[0], $phar_file);
			chmod($argv[0], 0777 & ~umask());
			fwrite(STDERR, 'Updated successfully' . PHP_EOL);
		} else {
			fwrite(STDERR, 'Up-to-date!' . PHP_EOL);
		}
		exit(0);
	}
	if (isset($opts['config'])) {
		$argv = extract_from_argv($argv, 'config');
		if (!file_exists($opts['config']) || !is_file($opts['config'])) {
			fwrite(STDERR, 'Custom configuration not file found' . PHP_EOL);
			exit(255);
		}
		$ini_opts = parse_ini_file($opts['config'], true);
		if (!empty($ini_opts)) {
			$opts = $ini_opts;
		}
	} elseif (file_exists('.php.tools.ini') && is_file('.php.tools.ini')) {
		fwrite(STDERR, 'Configuration file found' . PHP_EOL);
		$ini_opts = parse_ini_file('.php.tools.ini', true);
		if (isset($opts['profile'])) {
			$argv = extract_from_argv($argv, 'profile');
			$profile = &$ini_opts[$opts['profile']];
			if (isset($profile)) {
				$ini_opts = $profile;
			}
		}
		$opts = array_merge($ini_opts, $opts);
	}
	if (isset($opts['h']) || isset($opts['help'])) {
		show_help($argv, $enable_cache, $in_phar);
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
	$cache_fn = null;
	if ($enable_cache && isset($opts['cache'])) {
		$argv = extract_from_argv($argv, 'cache');
		$cache_fn = $opts['cache'];
		$cache = new Cache($cache_fn);
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
			} elseif (is_file('Additionals/' . $optPass . '.php')) {
				include 'Additionals/' . $optPass . '.php';
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
	$fmt->addPass(new ReindentColonBlocks());
	$fmt->addPass(new ReindentLoopColonBlocks());
	$fmt->addPass(new ReindentIfColonBlocks());

	if (isset($opts['enable_auto_align'])) {
		$fmt->addPass(new AlignEquals());
		$fmt->addPass(new AlignDoubleArrow());
		$argv = extract_from_argv($argv, 'enable_auto_align');
	}

	$fmt->addPass(new ReindentObjOps());
	$fmt->addPass(new Reindent());
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
			} elseif (is_file('Additionals/' . $optPass . '.php')) {
				include 'Additionals/' . $optPass . '.php';
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
		$argv = extract_from_argv_short($argv, 'o');
		if ('-' == $opts['o'] && '-' == $argv[1]) {
			echo $fmt->formatCode(file_get_contents('php://stdin'));
			exit(0);
		}
		if ($in_phar) {
			$argv[1] = dirname(Phar::running(false)) . DIRECTORY_SEPARATOR . $argv[1];
		}
		if ('-' == $opts['o']) {
			echo $fmt->formatCode(file_get_contents($argv[1]));
			exit(0);
		}
		if (!is_file($argv[1])) {
			fwrite(STDERR, "File not found: " . $argv[1] . PHP_EOL);
			exit(255);
		}
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
		$workers = 4;

		for ($j = 1; $j < $argc; ++$j) {
			$arg = &$argv[$j];
			if (!isset($arg)) {
				continue;
			}
			if ($in_phar) {
				$arg = dirname(Phar::running(false)) . DIRECTORY_SEPARATOR . $arg;
			}
			if (is_file($arg)) {
				$file = $arg;
				++$file_count;
				fwrite(STDERR, '.');
				file_put_contents($file . '-tmp', $fmt->formatCode(file_get_contents($file)));
				rename($file . '-tmp', $file);
			} elseif (is_dir($arg)) {
				fwrite(STDERR, $arg . PHP_EOL);
				$target_dir = $arg;
				$dir = new RecursiveDirectoryIterator($target_dir);
				$it = new RecursiveIteratorIterator($dir);
				$files = new RegexIterator($it, '/^.+\.php$/i', RecursiveRegexIterator::GET_MATCH);

				if ($concurrent) {

					$chn = make_channel();
					$chn_done = make_channel();
					if ($concurrent) {
						fwrite(STDERR, 'Starting ' . $workers . ' workers ...' . PHP_EOL);
					}
					for ($i = 0; $i < $workers; ++$i) {
						cofunc(function ($fmt, $backup, $cache_fn, $chn, $chn_done, $id) {
							$cache = null;
							if (null !== $cache_fn) {
								$cache = new Cache($cache_fn);
							}
							$cache_hit_count = 0;
							$cache_miss_count = 0;
							while (true) {
								$msg = $chn->out();
								if (null === $msg) {
									break;
								}
								$target_dir = $msg['target_dir'];
								$file = $msg['file'];
								if (empty($file)) {
									continue;
								}
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
						}, $fmt, $backup, $cache_fn, $chn, $chn_done, $i);
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
						$chn->in(null);
					}
					for ($i = 0; $i < $workers; ++$i) {
						list($cache_hit, $cache_miss) = $chn_done->out();
						$cache_hit_count += $cache_hit;
					}
					$chn_done->close();
					$chn->close();
				}
				continue;
			} elseif (!is_file($arg)) {
				$file_not_found = true;
				$missing_files[] = $arg;
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
		show_help($argv, $enable_cache, $in_phar);
	}
	exit(0);
}
