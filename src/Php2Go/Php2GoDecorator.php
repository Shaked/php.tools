<?php
final class Php2GoDecorator {

	public static function decorate(CodeFormatter $fmt) {
		$fmt->enablePass('PSR2ModifierVisibilityStaticOrder');
		$fmt->enablePass('TranslateNativeCalls');
		$fmt->enablePass('UpdateVisibility');
		$fmt->enablePass('ExtractMethods');
	}

}