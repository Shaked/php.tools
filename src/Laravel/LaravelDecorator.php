<?php
class LaravelDecorator {
	public static function decorate(CodeFormatter &$fmt) {
		$passes = $fmt->getPasses();

		$fmt = new CodeFormatter();

		foreach ($passes as $pass) {
			$passName = get_class($pass);
			if ('AlignEquals' == $passName || 'AlignDoubleArrow' == $passName) {
				continue;
			}
			$fmt->addPass($pass);
			if ('AddMissingCurlyBraces' == $passName) {
				$fmt->addPass(new SmartLnAfterCurlyOpen());
			}
		}

		$fmt->addPass(new NamespaceMergeWithOpenTag());
		$fmt->addPass(new AllmanStyleBraces());
		$fmt->addPass(new RTrim());
		$fmt->addPass(new TightConcat());
		$fmt->addPass(new NoSpaceBetweenFunctionAndBracket());
		$fmt->addPass(new SpaceAroundExclamationMark());
		$fmt->addPass(new NoneDocBlockMinorCleanUp());
		$fmt->addPass(new SortUseNameSpace());
		$fmt->addPass(new AlignEqualsByConsecutiveBlocks());
	}
}