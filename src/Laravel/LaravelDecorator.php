<?php
final class LaravelDecorator {
	public static function decorate(CodeFormatter &$fmt) {
		fwrite(STDERR, 'Laravel support is deprecated, as of Laravel 5.1 they will adhere to PSR2 standard' . PHP_EOL);
		fwrite(STDERR, 'See: https://laravel-news.com/2015/02/laravel-5-1/' . PHP_EOL);
		$fmt->disablePass('AlignEquals');
		$fmt->disablePass('AlignDoubleArrow');
		$fmt->enablePass('NamespaceMergeWithOpenTag');
		$fmt->enablePass('AllmanStyleBraces');
		$fmt->enablePass('RTrim');
		$fmt->enablePass('TightConcat');
		$fmt->enablePass('NoSpaceBetweenFunctionAndBracket');
		$fmt->enablePass('SpaceAroundExclamationMark');
		$fmt->enablePass('NonDocBlockMinorCleanUp');
		$fmt->enablePass('SortUseNameSpace');
		$fmt->enablePass('AlignEqualsByConsecutiveBlocks');
		$fmt->enablePass('EliminateDuplicatedEmptyLines');
	}
}