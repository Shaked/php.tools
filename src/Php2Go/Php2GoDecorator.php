<?php
final class Php2GoDecorator {
	public static function decorate(CodeFormatter $fmt) {
		$fmt->enablePass('TranslateNativeCalls');
		$fmt->enablePass('UpdateVisibility');
	}
}