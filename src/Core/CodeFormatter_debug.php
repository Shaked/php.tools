<?php
/**
 * @codeCoverageIgnore
 */
final class CodeFormatter extends BaseCodeFormatter {
	public function afterPass($source, $className) {
		echo get_class($className), PHP_EOL;
		echo $source, PHP_EOL;
		echo get_class($className), PHP_EOL;
		echo '----', PHP_EOL;
		readline();
	}
}
