<?php
/**
 * @codeCoverageIgnore
 */
final class CodeFormatter {

	private $shortcircuit = [
		'ReindentAndAlignObjOps' => 'ReindentObjOps',
		'ReindentObjOps' => 'ReindentAndAlignObjOps',
		'AllmanStyleBraces' => 'PSR2CurlyOpenNextLine',
	];

	private $passes = [
		'RTrim' => false,
		'WordWrap' => false,

		'ConvertOpenTagWithEcho' => false,
		'RestoreComments' => false,
		'UpgradeToPreg' => false,
		'DocBlockToComment' => false,
		'LongArray' => false,

		'StripExtraCommaInArray' => false,
		'NoSpaceAfterPHPDocBlocks' => false,
		'RemoveUseLeadingSlash' => false,
		'OrderMethod' => false,
		'ShortArray' => false,
		'MergeElseIf' => false,
		'AutoPreincrement' => false,
		'MildAutoPreincrement' => false,

		'CakePHPStyle' => false,

		'StripNewlineAfterClassOpen' => false,
		'StripNewlineAfterCurlyOpen' => false,
		'EliminateDuplicatedEmptyLines' => false,
		'AlignEqualsByConsecutiveBlocks' => false,
		'SortUseNameSpace' => false,
		'NonDocBlockMinorCleanUp' => false,
		'SpaceAroundExclamationMark' => false,
		'NoSpaceBetweenFunctionAndBracket' => false,
		'TightConcat' => false,

		'PSR2IndentWithSpace' => false,

		'AllmanStyleBraces' => false,
		'LaravelAllmanStyleBraces' => false,
		'NamespaceMergeWithOpenTag' => false,
		'MergeNamespaceWithOpenTag' => false,

		'LeftAlignComment' => false,

		'PSR2AlignObjOp' => false,
		'PSR2EmptyFunction' => false,
		'PSR2SingleEmptyLineAndStripClosingTag' => false,
		'PSR2ModifierVisibilityStaticOrder' => false,
		'PSR2IndentWithSpace' => false,
		'PSR2CurlyOpenNextLine' => false,
		'PSR2LnAfterNamespace' => false,
		'PSR2KeywordsLowerCase' => false,

		'PSR1MethodNames' => false,
		'PSR1ClassNames' => false,

		'PSR1ClassConstants' => false,
		'PSR1BOMMark' => false,
		'PSR1OpenTags' => false,

		'EliminateDuplicatedEmptyLines' => false,
		'IndentTernaryConditions' => false,
		'Reindent' => false,
		'ReindentAndAlignObjOps' => false,
		'ReindentObjOps' => false,

		'AlignDoubleSlashComments' => false,
		'AlignTypehint' => false,
		'AlignDoubleArrow' => false,
		'AlignEquals' => false,

		'ReindentIfColonBlocks' => false,
		'ReindentLoopColonBlocks' => false,
		'ReindentColonBlocks' => false,

		'ResizeSpaces' => false,
		'StripExtraCommaInList' => false,
		'YodaComparisons' => false,

		'MergeDoubleArrowAndArray' => false,
		'MergeCurlyCloseAndDoWhile' => false,
		'MergeParenCloseWithCurlyOpen' => false,
		'NormalizeLnAndLtrimLines' => false,
		'ExtraCommaInArray' => false,
		'SmartLnAfterCurlyOpen' => false,
		'AddMissingCurlyBraces' => false,
		'OrderUseClauses' => false,
		'AutoImportPass' => false,
		'ConstructorPass' => false,
		'SettersAndGettersPass' => false,
		'NormalizeIsNotEquals' => false,
		'RemoveIncludeParentheses' => false,
		'TwoCommandsInSameLine' => false,

		'SpaceBetweenMethods' => false,
		'GeneratePHPDoc' => false,
		'ReturnNull' => false,
		'AddMissingParentheses' => false,
		'WrongConstructorName' => false,
		'JoinToImplode' => false,
		'EncapsulateNamespaces' => false,
		'PrettyPrintDocBlocks' => false,
		'StrictBehavior' => false,
		'StrictComparison' => false,
		'ReplaceIsNull' => false,
		'DoubleToSingleQuote' => false,
		'LeftWordWrap' => false,
		'ClassToSelf' => false,
		'ClassToStatic' => false,
	];

	public function __construct() {
		$this->passes['AddMissingCurlyBraces'] = new AddMissingCurlyBraces();
		$this->passes['EliminateDuplicatedEmptyLines'] = new EliminateDuplicatedEmptyLines();
		$this->passes['ExtraCommaInArray'] = new ExtraCommaInArray();
		$this->passes['LeftAlignComment'] = new LeftAlignComment();
		$this->passes['MergeCurlyCloseAndDoWhile'] = new MergeCurlyCloseAndDoWhile();
		$this->passes['MergeDoubleArrowAndArray'] = new MergeDoubleArrowAndArray();
		$this->passes['MergeParenCloseWithCurlyOpen'] = new MergeParenCloseWithCurlyOpen();
		$this->passes['NormalizeIsNotEquals'] = new NormalizeIsNotEquals();
		$this->passes['NormalizeLnAndLtrimLines'] = new NormalizeLnAndLtrimLines();
		$this->passes['OrderUseClauses'] = new OrderUseClauses();
		$this->passes['Reindent'] = new Reindent();
		$this->passes['ReindentColonBlocks'] = new ReindentColonBlocks();
		$this->passes['ReindentIfColonBlocks'] = new ReindentIfColonBlocks();
		$this->passes['ReindentLoopColonBlocks'] = new ReindentLoopColonBlocks();
		$this->passes['ReindentAndAlignObjOps'] = new ReindentAndAlignObjOps();
		$this->passes['RemoveIncludeParentheses'] = new RemoveIncludeParentheses();
		$this->passes['ResizeSpaces'] = new ResizeSpaces();
		$this->passes['RTrim'] = new RTrim();
		$this->passes['StripExtraCommaInList'] = new StripExtraCommaInList();
		$this->passes['TwoCommandsInSameLine'] = new TwoCommandsInSameLine();
	}

	public function enablePass($pass) {
		$args = func_get_args();
		if (!isset($args[1])) {
			$args[1] = null;
		}
		$this->passes[$pass] = new $pass($args[1]);

		$scPass = &$this->shortcircuit[$pass];
		if (isset($scPass)) {
			$this->disablePass($scPass);
		}
	}

	public function disablePass($pass) {
		$this->passes[$pass] = null;
	}

	public function getPassesNames() {
		return array_keys(array_filter($this->passes));
	}

	public function formatCode($source = '') {
		$passes = array_map(
			function ($pass) {
				return clone $pass;
			},
			array_filter($this->passes)
		);
		$foundTokens = [];
		$commentStack = [];
		$tkns = token_get_all($source);
		foreach ($tkns as $token) {
			list($id, $text) = $this->getToken($token);
			$foundTokens[$id] = $id;
			if (T_COMMENT == $id) {
				$commentStack[] = [$id, $text];
			}
		}
		while (($pass = array_pop($passes))) {
			if ($pass->candidate($source, $foundTokens)) {
				if (isset($pass->commentStack)) {
					$pass->commentStack = $commentStack;
				}
				$source = $pass->format($source);
			}
		}
		return $source;
	}

	protected function getToken($token) {
		$ret = [$token, $token];
		if (isset($token[1])) {
			$ret = $token;
		}
		return $ret;
	}
}
