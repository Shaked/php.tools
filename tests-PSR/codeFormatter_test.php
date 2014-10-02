<?php
# Copyright (c) 2014, Carlos C
# All rights reserved.
#
# Redistribution and use in source and binary forms, with or without modification, are permitted provided that the following conditions are met:
#
# 1. Redistributions of source code must retain the above copyright notice, this list of conditions and the following disclaimer.
#
# 2. Redistributions in binary form must reproduce the above copyright notice, this list of conditions and the following disclaimer in the documentation and/or other materials provided with the distribution.
#
# 3. Neither the name of the copyright holder nor the names of its contributors may be used to endorse or promote products derived from this software without specific prior written permission.
#
# THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT HOLDER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
$start = microtime(true);
$testEnv = true;
include (realpath(__DIR__ . "/../codeFormatter.src.php"));
$cases = glob(__DIR__ . "/*.in");
echo 'Running tests...', PHP_EOL;
$brokenTests = [];
foreach ($cases as $caseIn) {
	$fmt = new CodeFormatter();
	$fmt->addPass(new TwoCommandsInSameLine());
	$fmt->addPass(new AddMissingCurlyBraces());
	$fmt->addPass(new NormalizeLnAndLtrimLines());
	$fmt->addPass(new MergeParenCloseWithCurlyOpen());
	$fmt->addPass(new MergeCurlyCloseAndDoWhile());
	$fmt->addPass(new MergeDoubleArrowAndArray());
	$fmt->addPass(new ExtraCommaInArray());
	$fmt->addPass(new ResizeSpaces());
	$fmt->addPass(new Reindent());
	$fmt->addPass(new ReindentColonBlocks());
	$fmt->addPass(new ReindentLoopColonBlocks());
	$fmt->addPass(new ReindentIfColonBlocks());
	$fmt->addPass(new ReindentObjOps());
	$fmt->addPass(new OrderUseClauses());
	$fmt->addPass(new EliminateDuplicatedEmptyLines());
	$fmt->addPass(new AlignEquals());
	$fmt->addPass(new AlignDoubleArrow());
	PsrDecorator::decorate($fmt);
	$fmt->addPass(new LeftAlignComment());
	$fmt->addPass(new RTrim());

	$caseOut = str_replace('.in', '.out', $caseIn);
	$got = $fmt->formatCode(file_get_contents($caseIn));
	$expected = file_get_contents($caseOut);
	if ($got != $expected) {
		$brokenTests[$caseOut] = $got;
		echo '!';
	} else {
		echo '.';
	}
}
echo PHP_EOL;
echo 'Tests:', sizeof($cases) . PHP_EOL;
echo 'Broken:', sizeof($brokenTests) . PHP_EOL;
$opt = getopt('v', ['verbose']);
if (isset($opt['v']) || isset($opt['verbose'])) {
	foreach ($brokenTests as $caseOut => $test) {
		file_put_contents($caseOut . '-got', $test);
		passthru('diff -u ' . $caseOut . ' ' . $caseOut . '-got 2>&1');
		unlink($caseOut . '-got');
	}
}
echo "Took ", (microtime(true) - $start), 'us', PHP_EOL;
if (sizeof($brokenTests) > 0) {
	echo "run phpCodeFormatter_test.php -v to see the error diffs", PHP_EOL;
	exit(255);
}
exit(0);
