<?php
/**
 * @codeCoverageIgnore
 */
abstract class BaseCodeFormatter {
	private $hasAfterExecutedPass = false;

	private $hasAfterFormat = false;

	private $hasBeforeFormat = false;

	private $hasBeforePass = false;

	private $passes = [
		'StripSpaces' => false,
		'ExtractMethods' => false,
		'UpdateVisibility' => false,
		'TranslateNativeCalls' => false,

		'ReplaceBooleanAndOr' => false,
		'EliminateDuplicatedEmptyLines' => false,

		'RTrim' => false,
		'WordWrap' => false,

		'AlignPHPCode' => false,
		'ConvertOpenTagWithEcho' => false,
		'UpgradeToPreg' => false,
		'DocBlockToComment' => false,
		'LongArray' => false,

		'StripExtraCommaInArray' => false,
		'NoSpaceAfterPHPDocBlocks' => false,
		'RemoveUseLeadingSlash' => false,
		'ShortArray' => false,
		'MergeElseIf' => false,
		'AutoPreincrement' => false,
		'MildAutoPreincrement' => false,

		'CakePHPStyle' => false,

		'StripNewlineAfterClassOpen' => false,
		'StripNewlineAfterCurlyOpen' => false,

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
		'PSR2CurlyOpenNextLine' => false,
		'PSR2LnAfterNamespace' => false,
		'PSR2KeywordsLowerCase' => false,

		'PSR1MethodNames' => false,
		'PSR1ClassNames' => false,

		'PSR1ClassConstants' => false,
		'PSR1BOMMark' => false,

		'EliminateDuplicatedEmptyLines' => false,
		'IndentTernaryConditions' => false,
		'Reindent' => false,
		'ReindentAndAlignObjOps' => false,
		'ReindentObjOps' => false,

		'AlignDoubleSlashComments' => false,
		'AlignTypehint' => false,
		'AlignGroupDoubleArrow' => false,
		'AlignDoubleArrow' => false,
		'AlignEquals' => false,

		'ReindentSwitchBlocks' => false,
		'ReindentColonBlocks' => false,

		'SplitCurlyCloseAndTokens' => false,
		'ResizeSpaces' => false,

		'StripSpaceWithinControlStructures' => false,

		'StripExtraCommaInList' => false,
		'YodaComparisons' => false,

		'MergeDoubleArrowAndArray' => false,
		'MergeCurlyCloseAndDoWhile' => false,
		'MergeParenCloseWithCurlyOpen' => false,
		'NormalizeLnAndLtrimLines' => false,
		'ExtraCommaInArray' => false,
		'SmartLnAfterCurlyOpen' => false,
		'AddMissingCurlyBraces' => false,
		'OnlyOrderUseClauses' => false,
		'OrderAndRemoveUseClauses' => false,
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
		'PSR2MultilineFunctionParams' => false,
		'SpaceAroundControlStructures' => false,

		'OrderMethodAndVisibility' => false,
		'OrderMethod' => false,
		'OrganizeClass' => false,
		'AutoSemicolon' => false,
		'PSR1OpenTags' => false,
	];

	private $shortcircuit = [
		'ReindentAndAlignObjOps' => 'ReindentObjOps',
		'ReindentObjOps' => 'ReindentAndAlignObjOps',
		'AllmanStyleBraces' => 'PSR2CurlyOpenNextLine',
		'AlignGroupDoubleArrow' => 'AlignDoubleArrow',
		'AlignDoubleArrow' => 'AlignGroupDoubleArrow',
		'OnlyOrderUseClauses' => 'OrderAndRemoveUseClauses',
		'OrderAndRemoveUseClauses' => 'OnlyOrderUseClauses',
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
		$this->passes['OrderAndRemoveUseClauses'] = new OrderAndRemoveUseClauses();
		$this->passes['Reindent'] = new Reindent();
		$this->passes['ReindentColonBlocks'] = new ReindentColonBlocks();
		$this->passes['ReindentObjOps'] = new ReindentObjOps();
		$this->passes['RemoveIncludeParentheses'] = new RemoveIncludeParentheses();
		$this->passes['ResizeSpaces'] = new ResizeSpaces();
		$this->passes['RTrim'] = new RTrim();
		$this->passes['SplitCurlyCloseAndTokens'] = new SplitCurlyCloseAndTokens();
		$this->passes['StripExtraCommaInList'] = new StripExtraCommaInList();
		$this->passes['TwoCommandsInSameLine'] = new TwoCommandsInSameLine();
		$this->hasAfterExecutedPass = method_exists($this, 'afterExecutedPass');
		$this->hasAfterFormat = method_exists($this, 'afterFormat');
		$this->hasBeforePass = method_exists($this, 'beforePass');
		$this->hasBeforeFormat = method_exists($this, 'beforeFormat');
	}

	public function disablePass($pass) {
		$this->passes[$pass] = null;
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

	public function formatCode($source = '') {
		$passes = array_map(
			function ($pass) {
				return clone $pass;
			},
			array_filter($this->passes)
		);
		$foundTokens = $this->getFoundTokens($source);
		$this->hasBeforeFormat && $this->beforeFormat($source);
		while (($pass = array_pop($passes))) {
			$this->hasBeforePass && $this->beforePass($source, $pass);
			if ($pass->candidate($source, $foundTokens)) {
				$source = $pass->format($source);
				$this->hasAfterExecutedPass && $this->afterExecutedPass($source, $pass);
			}
		}
		$this->hasAfterFormat && $this->afterFormat($source);
		return $source;
	}

	public function getPassesNames() {
		return array_keys(array_filter($this->passes));
	}

	protected function getToken($token) {
		$ret = [$token, $token];
		if (isset($token[1])) {
			$ret = $token;
		}
		return $ret;
	}

	private function getFoundTokens($source) {
		$foundTokens = [];
		$tkns = token_get_all($source);
		foreach ($tkns as $token) {
			list($id, $text) = $this->getToken($token);
			$foundTokens[$id] = $id;
		}
		return $foundTokens;
	}
}
