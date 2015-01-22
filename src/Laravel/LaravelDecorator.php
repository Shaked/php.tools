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

		// // Vetoed because it used Regex to modify PHP code,
		// // with no consideration about context book-keeping.
		// // Therefore, this is not safe as it does not
		// // distinguish between PHP code and inline strings.
		$fmt->addPass(new SortUseNameSpace());

		// // Vetoed because it used Regex to modify PHP code,
		// // with no consideration about context book-keeping.
		// // Therefore, this is not safe as it does not
		// // distinguish between PHP code and inline strings.
		$fmt->addPass(new AlignEqualsByConsecutiveBlocks());
	}
}