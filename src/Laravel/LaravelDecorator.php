<?php
class LaravelDecorator {
	public static function decorate(CodeFormatter &$fmt) {
		$fmt->removePass('AlignEquals');
		$fmt->removePass('AlignDoubleArrow');
		$fmt->addPass(new NamespaceMergeWithOpenTag());
		$fmt->addPass(new AllmanStyleBraces());
		$fmt->addPass(new RTrim());
		$fmt->addPass(new TightConcat());
		$fmt->addPass(new NoSpaceBetweenFunctionAndBracket());
		$fmt->addPass(new SpaceAroundExclamationMark());
		$fmt->addPass(new NoneDocBlockMinorCleanUp());
		$fmt->addPass(new SortUseNameSpace());
		$fmt->addPass(new AlignEqualsByConsecutiveBlocks());
		$fmt->addPass(new EliminateDuplicatedEmptyLines());
	}
}