<?php
namespace {
	if (version_compare(phpversion(), '5.6.0', '<')) {
		fwrite(STDERR, "PHP needs to be a minimum version of PHP 5.6.0\n");
		exit(255);
	}
}

require 'refactor.php';

__HALT_COMPILER();
