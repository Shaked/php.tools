php.tools
=========

## Build statuses
- Master: [![Build Status](https://travis-ci.org/dericofilho/php.tools.svg?branch=master)](https://travis-ci.org/dericofilho/php.tools)
- Coverage [![Coverage Status](https://coveralls.io/repos/dericofilho/php.tools/badge.png)](https://coveralls.io/r/dericofilho/php.tools)

## Requirements
- Git
- PHP >= 5.5.0 to run the formatter. Note that the formatter can parse even a PHP file version 4 in case needed.
- Optionally, if Exuberant Ctags, vendored PHPUnit and phpDocumentos are available, the functions for unit testing, code coverage and document generation are enabled.

## Plugins

* [Sublime Text 2/3](https://github.com/dericofilho/sublime-phpfmt)
* [Vim](https://github.com/dericofilho/vim-phpfmt)
* [PHPStorm](https://github.com/dericofilho/php.tools/blob/master/PHPStorm.md)

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
