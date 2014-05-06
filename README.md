php.tools
=========

## Build statuses
- Master: [![Build Status](https://travis-ci.org/dericofilho/php.tools.svg?branch=master)](https://travis-ci.org/dericofilho/php.tools)

## Requirements
- Git
- PHP >= 5.5.0 to run the formatter. Note that the formatter can parse even a PHP file version 4 in case needed.
- Optionally, if Exuberant Ctags, vendored PHPUnit and phpDocumentos are available, the functions for unit testing, code coverage and document generation are enabled.

## Usage

```
    /bin/sh ./php.tools [watch [command]]
	ctags - generate ctags
	test - execute PHPUnit
	cover - execute PHPUnit with cover output
	doc - execute phpDocumentor
	watch ctags - execute PHPUnit, but keeps watching for file changes to trigger ctags generator
	watch test - execute PHPUnit, but keeps watching for file changes to trigger the test automatically
	watch cover - execute PHPUnit with cover output, but keeps watching for file changes to trigger the test automatically
	watch doc - execute phpDocumentor, but keeps watching for file changes to trigger the generation automatically
	fmt [filename] - format filename according to project formatting rules
	fmt all - format all files according to project formatting rules
	fmt clean - remove all backup files - *~
	watch fmt [all|filename] - watch for changes and format according to project formatting rules
```

