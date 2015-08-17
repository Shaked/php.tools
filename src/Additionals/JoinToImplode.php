<?php
final class JoinToImplode extends AliasToMaster {
	protected static $aliasList = [
		'join' => 'implode',
	];

	/**
	 * @codeCoverageIgnore
	 */
	public function getDescription() {
		return 'Replace implode() alias (join() -> implode()).';
	}

	/**
	 * @codeCoverageIgnore
	 */
	public function getExample() {
		return <<<'EOT'
<?php
$a = join(',', $arr);

$a = implode(',', $arr);
?>
EOT;
	}
}
