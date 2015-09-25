php.tools
=========

## Build statuses
- Master: [![Build Status](https://travis-ci.org/phpfmt/php.tools.svg?branch=master)](https://travis-ci.org/phpfmt/php.tools)

[![Throughput Graph](https://graphs.waffle.io/phpfmt/php.tools/throughput.svg)](https://waffle.io/phpfmt/php.tools/metrics)

## Requirements
- Git
- PHP >= 5.6.0 to run the formatter. Note that the formatter can parse even a PHP file version 4 in case needed.
- Optionally, if Exuberant Ctags, vendored PHPUnit and phpDocumentos are available, the functions for unit testing, code coverage and document generation are enabled.

## Plugins

* [Sublime Text 2/3](https://github.com/phpfmt/sublime-phpfmt)
* [Vim](https://github.com/phpfmt/vim-phpfmt)
* [PHPStorm](https://github.com/phpfmt/php.tools/blob/master/PHPStorm.md)
* [Atom](https://github.com/Dgame/atom-php-fmt)

## Usage

```ShellSession
$ php fmt.phar filename.php

$ php fmt.phar --help
Usage: fmt.phar [-hv] [-o=FILENAME] [--config=FILENAME] [--cache[=FILENAME]] [options] <target>
  --cache[=FILENAME]                cache file. Default: .php.tools.cache
  --cakephp                         Apply CakePHP coding style
  --config=FILENAME                 configuration file. Default: .php.tools.ini
  --constructor=type                analyse classes for attributes and generate constructor - camel, snake, golang
  --enable_auto_align               disable auto align of ST_EQUAL and T_DOUBLE_ARROW
  --exclude=pass1,passN,...         disable specific passes
  --help-pass                       show specific information for one pass
  --ignore=PATTERN-1,PATTERN-N,...  ignore file names whose names contain any PATTERN-N
  --indent_with_space=SIZE          use spaces instead of tabs for indentation. Default 4
  --laravel                         Apply Laravel coding style (deprecated)
  --lint-before                     lint files before pretty printing (PHP must be declared in %PATH%/$PATH)
  --list                            list possible transformations
  --list-simple                     list possible transformations - greppable
  --no-backup                       no backup file (original.php~)
  --passes=pass1,passN,...          call specific compiler pass
  --profile=NAME                    use one of profiles present in configuration file
  --psr                             activate PSR1 and PSR2 styles
  --psr1                            activate PSR1 style
  --psr1-naming                     activate PSR1 style - Section 3 and 4.3 - Class and method names case.
  --psr2                            activate PSR2 style
  --selfupdate                      self-update fmt.phar from Github
  --setters_and_getters=type        analyse classes for attributes and generate setters and getters - camel, snake, golang
  --smart_linebreak_after_curly     convert multistatement blocks into multiline blocks
  --version                         version
  --visibility_order                fixes visibiliy order for method in classes - PSR-2 4.2
  --yoda                            yoda-style comparisons
  -h, --help                        this help message
  -o=-                              output the formatted code to standard output
  -o=file                           output the formatted code to "file"
  -v                                verbose

If <target> is "-", it reads from stdin
```

# What does the Code Formatter do?

### K&R configuration
<table>
<tr>
<td>Before</td>
<td>After</td>
</tr>
<tr>
<td>
<pre><code>&lt;?php
for($i = 0; $i &lt; 10; $i++)
{
if($i%2==0)
echo "Flipflop";
}
</code></pre>
</td>
<td>
<pre><code>&lt;?php
for ($i = 0; $i &lt; 10; $i++) {
	if ($i%2 == 0) {
		echo "Flipflop";
	}
}
</code></pre>
</td>
</tr>
<tr>
<td>
<pre><code>&lt;?php
$a = 10;
$otherVar = 20;
$third = 30;
</code></pre>
</td>
<td>
<pre><code>&lt;?php
$a        = 10;
$otherVar = 20;
$third    = 30;
</code></pre>
<i>This can be disabled with the option "disable_auto_align"</i>
</td>
</tr>
<tr>
<td>
<pre><code>&lt;?php
namespace NS\Something;
use \OtherNS\C;
use \OtherNS\B;
use \OtherNS\A;
use \OtherNS\D;

$a = new A();
$b = new C();
$d = new D();
</code></pre>
</td>
<td>
<pre><code>&lt;?php
namespace NS\Something;

use \OtherNS\A;
use \OtherNS\C;
use \OtherNS\D;

$a = new A();
$b = new C();
$d = new D();
</code></pre>
<i>note how it sorts the use clauses, and removes unused ones</i>
</td>
</tr>
</table>

### PSR configuration
<table>
<tr>
<td>Before</td>
<td>After</td>
</tr>
<tr>
<td>
<pre><code>&lt;?php
for($i = 0; $i &lt; 10; $i++)
{
if($i%2==0)
echo "Flipflop";
}
</code></pre>
</td>
<td>
<pre><code>&lt;?php
for ($i = 0; $i &lt; 10; $i++) {
    if ($i%2 == 0) {
        echo "Flipflop";
    }
}
</code></pre>
<i>Note the identation of 4 spaces.</i>
</td>
</tr>
<tr>
<td>
<pre><code>&lt;?php
class A {
function a(){
return 10;
}
}
</code></pre>
</td>
<td>
<pre><code>&lt;?php
class A
{
    public function a()
    {
        return 10;
    }
}
</code></pre>
<i>Note the braces position, and the visibility adjustment in the method a().</i>
</td>
</tr>
<tr>
<td>
<pre><code>&lt;?php
namespace NS\Something;
use \OtherNS\C;
use \OtherNS\B;
use \OtherNS\A;
use \OtherNS\D;

$a = new A();
$b = new C();
$d = new D();
</code></pre>
</td>
<td>
<pre><code>&lt;?php
namespace NS\Something;

use \OtherNS\A;
use \OtherNS\C;
use \OtherNS\D;

$a = new A();
$b = new C();
$d = new D();
</code></pre>
<i>note how it sorts the use clauses, and removes unused ones</i>
</td>
</tr>
</table>
