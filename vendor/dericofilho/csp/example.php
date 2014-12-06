<?php
// The MIT License (MIT)
//
// Copyright (c) 2014 Carlos Cirello
//
// Permission is hereby granted, free of charge, to any person obtaining a copy
// of this software and associated documentation files (the "Software"), to deal
// in the Software without restriction, including without limitation the rights
// to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
// copies of the Software, and to permit persons to whom the Software is
// furnished to do so, subject to the following conditions:
//
// The above copyright notice and this permission notice shall be included in all
// copies or substantial portions of the Software.
//
// THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
// IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
// FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
// AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
// LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
// OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
// SOFTWARE.

include "csp.php";

echo "Basic Test", PHP_EOL;
$chn = make_channel();

cofunc(function () use ($chn) {
	$chn->in("Hello World 1");
	$chn->in("Hello World 2");
	$chn->in("Hello World 3");
	$chn->in("Hello World 4");
	$chn->in("Hello World 5");
});
echo "Msg out 1:", print_r($chn->out(), true), PHP_EOL;
echo "Msg out 2:", print_r($chn->out(), true), PHP_EOL;
echo "Msg out 3:", print_r($chn->out(), true), PHP_EOL;

sleep(2);
echo "Msg out 4:", print_r($chn->out(), true), PHP_EOL;
echo "Msg out 5:", print_r($chn->out(), true), PHP_EOL;

$chn->close();
echo "Done", PHP_EOL;

echo "Simulating Searches", PHP_EOL;
function Web($qry) {
	usleep(rand(0, 5) * 100000);
	return 'Web Query for: ' . $qry;
}
function Web1($qry) {
	usleep(rand(0, 100) * 1000);
	return 'Web1 Query for: ' . $qry;
}
function Web2($qry) {
	usleep(rand(0, 100) * 1000);
	return 'Web2 Query for: ' . $qry;
}
function Web3($qry) {
	usleep(rand(0, 100) * 1000);
	return 'Web3 Query for: ' . $qry;
}
function Image($qry) {
	usleep(rand(0, 100) * 1000);
	return 'Image Query for: ' . $qry;
}
function Image1($qry) {
	usleep(rand(0, 100) * 1000);
	return 'Image1 Query for: ' . $qry;
}
function Image2($qry) {
	usleep(rand(0, 100) * 1000);
	return 'Image2 Query for: ' . $qry;
}
function Image3($qry) {
	usleep(rand(0, 100) * 1000);
	return 'Image3 Query for: ' . $qry;
}
function Video($qry) {
	usleep(rand(0, 100) * 1000);
	return 'Video Query for: ' . $qry;
}
function Video1($qry) {
	usleep(rand(0, 100) * 1000);
	return 'Video1 Query for: ' . $qry;
}
function Video2($qry) {
	usleep(rand(0, 100) * 1000);
	return 'Video2 Query for: ' . $qry;
}
function Video3($qry) {
	usleep(rand(0, 100) * 1000);
	return 'Video3 Query for: ' . $qry;
}

function GoogleSequential($qry) {
	$results = [];
	$results[] = Web($qry);
	$results[] = Image($qry);
	$results[] = Video($qry);
	return $results;
}
function GoogleConcurrent($qry) {
	$chn = make_channel();
	cofunc(function ($chn, $qry) {
		$chn->in(Web($qry));
	}, $chn, $qry);
	cofunc(function ($chn, $qry) {
		$chn->in(Image($qry));
	}, $chn, $qry);
	cofunc(function ($chn, $qry) {
		$chn->in(Video($qry));
	}, $chn, $qry);

	$results = [];
	for ($i = 0; $i < 3; ++$i) {
		$results[] = $chn->out();
	}
	$chn->close();
	return $results;
}
function First($qry) {
	$chn = make_channel();
	$params = array_slice(func_get_args(), 1);
	foreach ($params as $v) {
		cofunc(function ($v, $qry, $chn) {
			$result = $v($qry);
			$chn->in($result);
		}, $v, $qry, $chn);
	}
	$ret = $chn->out();
	cofunc(function ($chn, $drain_size) {
		for ($i = 0; $i < $drain_size; ++$i) {
			$chn->out();
		}
		$chn->close();
	}, $chn, sizeof($params) - 1);
	return $ret;
}
function GoogleConcurrentFirst($qry) {
	$chn = make_channel();
	cofunc(function ($chn, $qry) {
		$chn->in(First($qry, 'Web1', 'Web2', 'Web3'));
	}, $chn, $qry);
	cofunc(function ($chn, $qry) {
		$chn->in(First($qry, 'Image1', 'Image2', 'Image3'));
	}, $chn, $qry);
	cofunc(function ($chn, $qry) {
		$chn->in(First($qry, 'Video1', 'Video2', 'Video3'));
	}, $chn, $qry);

	$results = [];
	for ($i = 0; $i < 3; ++$i) {
		$results[] = $chn->out();
	}
	$chn->close();
	return $results;
}

echo "Sequential", PHP_EOL;
$start = microtime(true);
print_r(GoogleSequential('Test'));
echo microtime(true) - $start, PHP_EOL, PHP_EOL;

echo "Concurrent", PHP_EOL;
$start = microtime(true);
print_r(GoogleConcurrent('Test'));
echo microtime(true) - $start, PHP_EOL, PHP_EOL;

echo "Concurrent (dispatch many, fetch first)", PHP_EOL;
$start = microtime(true);
print_r(GoogleConcurrentFirst('Test'));
echo microtime(true) - $start, PHP_EOL, PHP_EOL;
