<?php
final class MildAutoPreincrement extends AutoPreincrement {
	/**
	 * @codeCoverageIgnore
	 */
	public function getDescription() {
		return 'Automatically convert postincrement to preincrement. (Deprecated pass. Use AutoPreincrement instead).';
	}
}