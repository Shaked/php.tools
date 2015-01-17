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

$chn = make_channel();
cofunc(function ($chn) {
	sleep(5);
	$chn->in('timeout');
}, $chn);

$chn2 = make_channel();
cofunc(function ($chn2) {
	sleep(rand(3, 6));
	$chn2->in('action taken');
}, $chn2);

$chn3 = make_channel();
cofunc(function ($chn3) {
	sleep(4);
	echo 'Got: ', $chn3->out(), PHP_EOL;
}, $chn3);

while (true) {
	select_channel([
		[$chn, function ($msg) {
			echo "Message Received 1:", print_r($msg, true), PHP_EOL;
			die();
		}],
		[$chn2, function ($msg) {
			echo "Message Received 2:", print_r($msg, true), PHP_EOL;
		}],
		[$chn3, 'Hello World', function () {
			echo "Sent HW", PHP_EOL;
		}],
	]);
}

// $chn->close();
// $chn2->close();
// $chn3->close();
