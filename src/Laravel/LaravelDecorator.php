<?php
final class LaravelDecorator {

	public static function decorate(CodeFormatter &$fmt) {
		$fmt->disablePass('AlignEquals');
		$fmt->disablePass('AlignDoubleArrow');
		$fmt->enablePass('NamespaceMergeWithOpenTag');
		$fmt->enablePass('LaravelAllmanStyleBraces');
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