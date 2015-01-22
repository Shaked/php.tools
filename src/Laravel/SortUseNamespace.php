<?php
class SortUseNameSpace extends AdditionalPass {
	public function candidate($source, $foundTokens) {
		if (isset($foundTokens[T_USE])) {
			return true;
		}

		return false;
	}

	public function format($source) {
		// assumption: core parser stack the T_USE line consecutively,
		// will not process T_USE line(s) besides the header/top file section.
		$lines = explode("\n", $source);
		$t_use = [];
		$seen = false;
		$min = 1000;
		$max = -1000;
		$prev = -1;
		foreach ($lines as $index => $line) {
			// some fail-safe to prevent core parser misbehave,
			if ($seen and (preg_match('/^use\s/i', $line)) and (($prev + 1) == $index)) {
				$max = max($index, $max);
				$prev = $index;
			}
			if (preg_match('/^use\s/i', $line)) {
				if (false == $seen) {
					$seen = true;
					$min = min($index, $min);
					$prev = $index;
				}
			}
		}
		$t_use = array_splice($lines, $min, ($max - $min + 1));
		$t_use = $this->sortByLength($t_use);

		$head = array_splice($lines, 0, $min);
		$lines = array_merge($head, $t_use, $lines);

		return implode("\n", $lines);
	}

	private function sortByLength($inArray) {
		$outArray = [];
		// prepend strlen in front, then sort, then remove prepend, done.
		foreach ($inArray as $line) {
			$prepend = strlen($line) . " $line"; // use ' ' + 'use' as delimit later on
			array_push($outArray, $prepend);
		}
		sort($outArray);
		$cleaned = [];
		foreach ($outArray as $line) {
			$unprepend = preg_replace('/^\d+ /', '', $line);
			array_push($cleaned, $unprepend);
		}
		return $cleaned;
	}

	/**
	 * @codeCoverageIgnore
	 */
	public function getDescription() {
		return 'Simple sorting of T_USE to follow Laravel Framework';
	}

	/**
	 * @codeCoverageIgnore
	 */
	public function getExample() {
		return <<<'EOT'
<?php

namespace Illuminate\Foundation;

use Closure;
use Illuminate\Config\FileEnvironmentVariablesLoader;
use Illuminate\Config\FileLoader;
use Illuminate\Container\Container;
use Illuminate\Events\EventServiceProvider;
use Illuminate\Exception\ExceptionServiceProvider;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\RoutingServiceProvider;
use Illuminate\Support\Contracts\ResponsePreparerInterface;
use Illuminate\Support\Facades\Facade;
use Stack\Builder;
use Symfony\Component\Debug\Exception\FatalErrorException;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\TerminableInterface;
?>
to
<?php namespace Illuminate\Foundation;

use Closure;
use Stack\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Config\FileLoader;
use Illuminate\Container\Container;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Facade;
use Illuminate\Events\EventServiceProvider;
use Illuminate\Routing\RoutingServiceProvider;
use Illuminate\Exception\ExceptionServiceProvider;
use Illuminate\Config\FileEnvironmentVariablesLoader;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\TerminableInterface;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Debug\Exception\FatalErrorException;
use Illuminate\Support\Contracts\ResponsePreparerInterface;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
?>
EOT;
	}
}
