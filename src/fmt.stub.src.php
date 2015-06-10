<?php

if (version_compare(phpversion(), '5.6.0', '<')) {
	fwrite(STDERR, "Support for PHP 5.5.0 is deprecated. By June 20th, php.tools will no longer support it.\n");
}

if (version_compare(phpversion(), '5.5.0', '<')) {
	fwrite(STDERR, "PHP needs to be a minimum version of PHP 5.5.0\n");
	exit(255);
}

require 'fmt.php';

__HALT_COMPILER();
