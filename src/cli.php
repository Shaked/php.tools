<?php
function showHelp($argv, $enableCache, $inPhar) {
	echo 'Usage: ' . $argv[0] . ' [-hv] [-o=FILENAME] [--config=FILENAME] ' . ($enableCache ? '[--cache[=FILENAME]] ' : '') . '[options] <target>', PHP_EOL;
	$options = [
		'--cache[=FILENAME]' => 'cache file. Default: ',
		'--cakephp' => 'Apply CakePHP coding style',
		'--config=FILENAME' => 'configuration file. Default: .php.tools.ini',
		'--constructor=type' => 'analyse classes for attributes and generate constructor - camel, snake, golang',
		'--enable_auto_align' => 'disable auto align of ST_EQUAL and T_DOUBLE_ARROW',
		'--exclude=pass1,passN' => 'disable specific passes',
		'--ignore=PATTERN1,PATTERN2' => 'ignore file names whose names contain any PATTERN-N',
		'--indent_with_space=SIZE' => 'use spaces instead of tabs for indentation. Default 4',
		'--laravel' => 'Apply Laravel coding style (deprecated)',
		'--lint-before' => 'lint files before pretty printing (PHP must be declared in %PATH%/$PATH)',
		'--list' => 'list possible transformations',
		'--list-simple' => 'list possible transformations - parseable',
		'--no-backup' => 'no backup file (original.php~)',
		'--passes=pass1,passN' => 'call specific compiler pass',
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
		'-v' => 'verbose',
	];
	if ($inPhar) {
		$options['--selfupdate'] = 'self-update fmt.phar from Github';
		$options['--version'] = 'version';
	}
	$options['--cache[=FILENAME]'] .= (Cache::DEFAULT_CACHE_FILENAME);
	if (!$enableCache) {
		unset($options['--cache[=FILENAME]']);
	}
	ksort($options);
	$maxLen = max(array_map(function ($v) {
		return strlen($v);
	}, array_keys($options)));
	foreach ($options as $k => $v) {
		echo '  ', str_pad($k, $maxLen), '  ', $v, PHP_EOL;
	}

	echo PHP_EOL, 'If <target> is "-", it reads from stdin', PHP_EOL;
}
$getoptLongOptions = [
	'cache::',
	'cakephp',
	'config:',
	'constructor:',
	'enable_auto_align',
	'exclude:',
	'help',
	'help-pass:',
	'ignore:',
	'indent_with_space::',
	'laravel',
	'lint-before',
	'list',
	'list-simple',
	'no-backup',
	'oracleDB::',
	'passes:',
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
if ($inPhar) {
	$getoptLongOptions[] = 'selfupdate';
	$getoptLongOptions[] = 'version';
}
if (!$enableCache) {
	unset($getoptLongOptions['cache::']);
}
$opts = getopt(
	'ihvo:',
	$getoptLongOptions
);

if (isset($opts['list'])) {
	echo 'Usage: ', $argv[0], ' --help-pass=PASSNAME', PHP_EOL;
	$classes = get_declared_classes();
	$helpLines = [];
	foreach ($classes as $className) {
		if (is_subclass_of($className, 'AdditionalPass')) {
			$pass = new $className();
			$helpLines[] = ["\t- " . $className, $pass->getDescription()];
		}
	}
	echo tabwriter($helpLines);
	die();
}

if (isset($opts['list-simple'])) {
	$classes = get_declared_classes();
	$helpLines = [];
	foreach ($classes as $className) {
		if (is_subclass_of($className, 'AdditionalPass')) {
			$pass = new $className();
			$helpLines[] = [$className, $pass->getDescription()];
		}
	}
	echo tabwriter($helpLines);
	die();
}
if (isset($opts['selfupdate'])) {
	$opts = [
		'http' => [
			'method' => 'GET',
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
	if ($inPhar) {
		if (!file_exists($argv[0])) {
			$argv[0] = dirname(Phar::running(false)) . DIRECTORY_SEPARATOR . $argv[0];
		}
	}
	if (sha1_file($argv[0]) != $phar_sha1) {
		copy($argv[0], $argv[0] . '~');
		file_put_contents($argv[0], $phar_file);
		chmod($argv[0], 0777 & ~umask());
		fwrite(STDERR, 'Updated successfully' . PHP_EOL);
	} else {
		fwrite(STDERR, 'Up-to-date!' . PHP_EOL);
	}
	exit(0);
}
if (isset($opts['version'])) {
	if ($inPhar) {
		echo $argv[0], ' ', VERSION, PHP_EOL;
	}
	exit(0);
}
if (isset($opts['config'])) {
	$argv = extractFromArgv($argv, 'config');
	if (!file_exists($opts['config']) || !is_file($opts['config'])) {
		fwrite(STDERR, 'Custom configuration not file found' . PHP_EOL);
		exit(255);
	}
	$iniOpts = parse_ini_file($opts['config'], true);
	if (!empty($iniOpts)) {
		$opts += $iniOpts;
	}
} elseif (file_exists('.php.tools.ini') && is_file('.php.tools.ini')) {
	fwrite(STDERR, 'Configuration file found' . PHP_EOL);
	$iniOpts = parse_ini_file('.php.tools.ini', true);
	if (isset($opts['profile'])) {
		$argv = extractFromArgv($argv, 'profile');
		$profile = &$iniOpts[$opts['profile']];
		if (isset($profile)) {
			$iniOpts = $profile;
		}
	}
	$opts = array_merge($iniOpts, $opts);
}
if (isset($opts['h']) || isset($opts['help'])) {
	showHelp($argv, $enableCache, $inPhar);
	exit(0);
}

if (isset($opts['help-pass'])) {
	$optPass = $opts['help-pass'];
	if (class_exists($optPass)) {
		$pass = new $optPass();
		echo $argv[0], ': "', $optPass, '" - ', $pass->getDescription(), PHP_EOL, PHP_EOL;
		echo 'Example:', PHP_EOL, $pass->getExample(), PHP_EOL;
	}
	die();
}

$cache = null;
$cache_fn = null;
if ($enableCache && isset($opts['cache'])) {
	$argv = extractFromArgv($argv, 'cache');
	$cache_fn = $opts['cache'];
	$cache = new Cache($cache_fn);
	fwrite(STDERR, 'Using cache ...' . PHP_EOL);
}
$backup = true;
if (isset($opts['no-backup'])) {
	$argv = extractFromArgv($argv, 'no-backup');
	$backup = false;
}

$ignore_list = null;
if (isset($opts['ignore'])) {
	$argv = extractFromArgv($argv, 'ignore');
	$ignore_list = array_map(function ($v) {
		return trim($v);
	}, explode(',', $opts['ignore']));
}

$lintBefore = false;
if (isset($opts['lint-before'])) {
	$argv = extractFromArgv($argv, 'lint-before');
	$lintBefore = true;
}

$fmt = new CodeFormatter();

if (isset($opts['setters_and_getters'])) {
	$argv = extractFromArgv($argv, 'setters_and_getters');
	$fmt->enablePass('SettersAndGettersPass', $opts['setters_and_getters']);
}
if (isset($opts['constructor'])) {
	$argv = extractFromArgv($argv, 'constructor');
	$fmt->enablePass('ConstructorPass', $opts['constructor']);
}
if (isset($opts['oracleDB'])) {
	$argv = extractFromArgv($argv, 'oracleDB');
	$fmt->enablePass('AutoImportPass', $opts['oracleDB']);
}

if (isset($opts['smart_linebreak_after_curly'])) {
	$fmt->enablePass('SmartLnAfterCurlyOpen');
	$argv = extractFromArgv($argv, 'smart_linebreak_after_curly');
}

if (isset($opts['yoda'])) {
	$fmt->enablePass('YodaComparisons');
	$argv = extractFromArgv($argv, 'yoda');
}

if (isset($opts['enable_auto_align'])) {
	$fmt->enablePass('AlignEquals');
	$fmt->enablePass('AlignDoubleArrow');
	$argv = extractFromArgv($argv, 'enable_auto_align');
}

if (isset($opts['psr']) && !isset($opts['laravel'])) {
	PsrDecorator::decorate($fmt);
	$argv = extractFromArgv($argv, 'psr');
}
if (isset($opts['psr1']) && !isset($opts['laravel'])) {
	PsrDecorator::PSR1($fmt);
	$argv = extractFromArgv($argv, 'psr1');
}
if (isset($opts['psr1-naming']) && !isset($opts['laravel'])) {
	PsrDecorator::PSR1Naming($fmt);
	$argv = extractFromArgv($argv, 'psr1-naming');
}
if (isset($opts['psr2']) && !isset($opts['laravel'])) {
	PsrDecorator::PSR2($fmt);
	$argv = extractFromArgv($argv, 'psr2');
}
if (isset($opts['indent_with_space']) && !isset($opts['laravel'])) {
	$fmt->enablePass('PSR2IndentWithSpace', $opts['indent_with_space']);
	$argv = extractFromArgv($argv, 'indent_with_space');
}
if ((isset($opts['psr1']) || isset($opts['psr2']) || isset($opts['psr'])) && isset($opts['enable_auto_align']) && !isset($opts['laravel'])) {
	$fmt->enablePass('PSR2AlignObjOp');
}

if (isset($opts['visibility_order'])) {
	$fmt->enablePass('PSR2ModifierVisibilityStaticOrder');
	$argv = extractFromArgv($argv, 'visibility_order');
}

if (isset($opts['passes'])) {
	$optPasses = array_map(function ($v) {
		return trim($v);
	}, explode(',', $opts['passes']));
	foreach ($optPasses as $optPass) {
		if (class_exists($optPass)) {
			$fmt->enablePass($optPass);
		}
	}
	$argv = extractFromArgv($argv, 'passes');
}

if (isset($opts['laravel'])) {
	fwrite(STDERR, 'Laravel support is deprecated, as of Laravel 5.1 they will adhere to PSR2 standard' . PHP_EOL);
	fwrite(STDERR, 'See: https://laravel-news.com/2015/02/laravel-5-1/' . PHP_EOL);

	LaravelDecorator::decorate($fmt);
	$argv = extractFromArgv($argv, 'laravel');
	$argv = extractFromArgv($argv, 'psr');
	$argv = extractFromArgv($argv, 'psr1');
	$argv = extractFromArgv($argv, 'psr1-naming');
	$argv = extractFromArgv($argv, 'psr2');
	$argv = extractFromArgv($argv, 'indent_with_space');
}

if (isset($opts['cakephp'])) {
	$fmt->enablePass('CakePHPStyle');
	$argv = extractFromArgv($argv, 'cakephp');
}

if (isset($opts['exclude'])) {
	$passesNames = explode(',', $opts['exclude']);
	foreach ($passesNames as $passName) {
		$fmt->disablePass(trim($passName));
	}
	$argv = extractFromArgv($argv, 'exclude');
}

if (isset($opts['v'])) {
	$argv = extractFromArgvShort($argv, 'v');
	fwrite(STDERR, 'Used passes: ' . implode(', ', $fmt->getPassesNames()) . PHP_EOL);
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
	$argv = extractFromArgvShort($argv, 'o');
	if ('-' == $opts['o'] && '-' == $argv[1]) {
		echo $fmt->formatCode(file_get_contents('php://stdin'));
		exit(0);
	}
	if ($inPhar) {
		if (!file_exists($argv[1])) {
			$argv[1] = dirname(Phar::running(false)) . DIRECTORY_SEPARATOR . $argv[1];
		}
	}
	if ('-' == $opts['o']) {
		echo $fmt->formatCode(file_get_contents($argv[1]));
		exit(0);
	}
	if (!is_file($argv[1])) {
		fwrite(STDERR, 'File not found: ' . $argv[1] . PHP_EOL);
		exit(255);
	}
	$argv = array_values($argv);
	file_put_contents($opts['o'], $fmt->formatCode(file_get_contents($argv[1])));
} elseif (isset($argv[1])) {
	if ('-' == $argv[1]) {
		echo $fmt->formatCode(file_get_contents('php://stdin'));
		exit(0);
	}
	$fileNotFound = false;
	$start = microtime(true);
	fwrite(STDERR, 'Formatting ...' . PHP_EOL);
	$missingFiles = [];
	$fileCount = 0;

	$cacheHitCount = 0;
	$workers = 4;

	$hasFnSeparator = false;

	for ($j = 1; $j < $argc; ++$j) {
		$arg = &$argv[$j];
		if (!isset($arg)) {
			continue;
		}
		if ('--' == $arg) {
			$hasFnSeparator = true;
			continue;
		}
		if ($inPhar) {
			if (!file_exists($arg)) {
				$arg = getcwd() . DIRECTORY_SEPARATOR . $arg;
			}
		}
		if (is_file($arg)) {
			$file = $arg;
			if ($lintBefore && !lint($file)) {
				fwrite(STDERR, 'Error lint:' . $file . PHP_EOL);
				continue;
			}
			++$fileCount;
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
					cofunc(function ($fmt, $backup, $cache_fn, $chn, $chn_done, $lintBefore) {
						$cache = null;
						if (null !== $cache_fn) {
							$cache = new Cache($cache_fn);
						}
						$cacheHitCount = 0;
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
							if ($lintBefore && !lint($file)) {
								fwrite(STDERR, 'Error lint:' . $file . PHP_EOL);
								continue;
							}
							if (null !== $cache) {
								$content = $cache->is_changed($target_dir, $file);
								if (false === $content) {
									++$cacheHitCount;
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
						$chn_done->in([$cacheHitCount, $cache_miss_count]);
					}, $fmt, $backup, $cache_fn, $chn, $chn_done, $lintBefore);
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

				++$fileCount;
				if ($concurrent) {
					$chn->in([
						'target_dir' => $target_dir,
						'file' => $file,
					]);
				} else {
					if (0 == ($fileCount % 20)) {
						fwrite(STDERR, ' ' . $fileCount . PHP_EOL);
					}
					if (null !== $cache) {
						$content = $cache->is_changed($target_dir, $file);
						if (false === $content) {
							++$fileCount;
							++$cacheHitCount;
							continue;
						}
					} else {
						$content = file_get_contents($file);
					}
					if ($lintBefore && !lint($file)) {
						fwrite(STDERR, 'Error lint:' . $file . PHP_EOL);
						continue;
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
					$cacheHitCount += $cache_hit;
				}
				$chn_done->close();
				$chn->close();
			}
			continue;
		} elseif (
			!is_file($arg) &&
			('--' != substr($arg, 0, 2) || $hasFnSeparator)
		) {
			$fileNotFound = true;
			$missingFiles[] = $arg;
			fwrite(STDERR, '!');
		}
		if (0 == ($fileCount % 20)) {
			fwrite(STDERR, ' ' . $fileCount . PHP_EOL);
		}
	}
	fwrite(STDERR, PHP_EOL);
	if (null !== $cache) {
		fwrite(STDERR, ' ' . $cacheHitCount . ' files untouched (cache hit)' . PHP_EOL);
	}
	fwrite(STDERR, ' ' . $fileCount . ' files total' . PHP_EOL);
	fwrite(STDERR, 'Took ' . round(microtime(true) - $start, 2) . 's' . PHP_EOL);
	if (sizeof($missingFiles)) {
		fwrite(STDERR, 'Files not found: ' . PHP_EOL);
		foreach ($missingFiles as $file) {
			fwrite(STDERR, "\t - " . $file . PHP_EOL);
		}
	}

	if ($fileNotFound) {
		exit(255);
	}
} else {
	showHelp($argv, $enableCache, $inPhar);
}
exit(0);
