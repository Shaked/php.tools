#!/usr/bin/env php
<?php
//Copyright (c) 2014, Carlos C
//All rights reserved.
//
//Redistribution and use in source and binary forms, with or without modification, are permitted provided that the following conditions are met:
//
//1. Redistributions of source code must retain the above copyright notice, this list of conditions and the following disclaimer.
//
//2. Redistributions in binary form must reproduce the above copyright notice, this list of conditions and the following disclaimer in the documentation and/or other materials provided with the distribution.
//
//3. Neither the name of the copyright holder nor the names of its contributors may be used to endorse or promote products derived from this software without specific prior written permission.
//
//THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT HOLDER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
$concurrent = function_exists('pcntl_fork');
if ($concurrent) {
	require 'vendor/dericofilho/csp/csp.php';
}
$enableCache = false;
if (class_exists('SQLite3')) {
	$enableCache = true;
	require 'Core/Cache.php';
} else {
	require 'Core/Cache_dummy.php';
}

require 'version.php';
require 'helpers.php';
require 'selfupdate.php';

require 'Core/constants.php';
require 'Core/FormatterPass.php';
require 'Additionals/AdditionalPass.php';
require 'Core/BaseCodeFormatter.php';
if (1 == getenv('FMTDEBUG') || 'step' == getenv('FMTDEBUG')) {
	require 'Core/CodeFormatter_debug.php';
} else {
	require 'Core/CodeFormatter.php';
}

require 'Core/AddMissingCurlyBraces.php';
require 'Core/AutoImport.php';
require 'Core/ConstructorPass.php';
require 'Core/EliminateDuplicatedEmptyLines.php';
require 'Core/ExtraCommaInArray.php';
require 'Core/LeftAlignComment.php';
require 'Core/MergeCurlyCloseAndDoWhile.php';
require 'Core/MergeDoubleArrowAndArray.php';
require 'Core/MergeParenCloseWithCurlyOpen.php';
require 'Core/NormalizeIsNotEquals.php';
require 'Core/NormalizeLnAndLtrimLines.php';
require 'Core/OrderUseClauses.php';
require 'Core/Reindent.php';
require 'Core/ReindentColonBlocks.php';
require 'Core/ReindentIfColonBlocks.php';
require 'Core/ReindentAndAlignObjOps.php';
require 'Core/ReindentLoopColonBlocks.php';
require 'Core/ReindentObjOps.php';
require 'Core/RemoveIncludeParentheses.php';
require 'Core/ResizeSpaces.php';
require 'Core/RestoreComments.php';
require 'Core/RTrim.php';
require 'Core/SettersAndGettersPass.php';
require 'Core/SplitCurlyCloseAndTokens.php';
require 'Core/StripExtraCommaInList.php';
require 'Core/SurrogateToken.php';
require 'Core/TwoCommandsInSameLine.php';

require 'PSR/PSR1BOMMark.php';
require 'PSR/PSR1ClassConstants.php';
require 'PSR/PSR1ClassNames.php';
require 'PSR/PSR1MethodNames.php';
require 'PSR/PSR1OpenTags.php';
require 'PSR/PSR2AlignObjOp.php';
require 'PSR/PSR2CurlyOpenNextLine.php';
require 'PSR/PSR2IndentWithSpace.php';
require 'PSR/PSR2KeywordsLowerCase.php';
require 'PSR/PSR2LnAfterNamespace.php';
require 'PSR/PSR2ModifierVisibilityStaticOrder.php';
require 'PSR/PSR2SingleEmptyLineAndStripClosingTag.php';
require 'PSR/PsrDecorator.php';

require 'Additionals/AddMissingParentheses.php';
require 'Additionals/AliasToMaster.php';
require 'Additionals/AlignDoubleArrow.php';
require 'Additionals/AlignDoubleSlashComments.php';
require 'Additionals/AlignEquals.php';
require 'Additionals/AlignPHPCode.php';
require 'Additionals/AlignTypehint.php';
require 'Additionals/AllmanStyleBraces.php';
require 'Additionals/AutoPreincrement.php';
require 'Additionals/CakePHPStyle.php';
require 'Additionals/ClassToSelf.php';
require 'Additionals/ClassToStatic.php';
require 'Additionals/ConvertOpenTagWithEcho.php';
require 'Additionals/DocBlockToComment.php';
require 'Additionals/DoubleToSingleQuote.php';
require 'Additionals/EncapsulateNamespaces.php';
require 'Additionals/GeneratePHPDoc.php';
require 'Additionals/IndentTernaryConditions.php';
require 'Additionals/JoinToImplode.php';
require 'Additionals/LeftWordWrap.php';
require 'Additionals/LongArray.php';
require 'Additionals/MergeElseIf.php';
require 'Additionals/MergeNamespaceWithOpenTag.php';
require 'Additionals/MildAutoPreincrement.php';
require 'Additionals/NoSpaceAfterPHPDocBlocks.php';
require 'Additionals/OrderMethod.php';
require 'Additionals/PrettyPrintDocBlocks.php';
require 'Additionals/PSR2EmptyFunction.php';
require 'Additionals/RemoveUseLeadingSlash.php';
require 'Additionals/ReplaceBooleanAndOr.php';
require 'Additionals/ReplaceIsNull.php';
require 'Additionals/ReturnNull.php';
require 'Additionals/ShortArray.php';
require 'Additionals/SmartLnAfterCurlyOpen.php';
require 'Additionals/SpaceBetweenMethods.php';
require 'Additionals/StrictBehavior.php';
require 'Additionals/StrictComparison.php';
require 'Additionals/StripExtraCommaInArray.php';
require 'Additionals/StripNewlineAfterClassOpen.php';
require 'Additionals/StripNewlineAfterCurlyOpen.php';
require 'Additionals/TightConcat.php';
require 'Additionals/UpgradeToPreg.php';
require 'Additionals/WordWrap.php';
require 'Additionals/WrongConstructorName.php';
require 'Additionals/YodaComparisons.php';

require 'Laravel/AlignEqualsByConsecutiveBlocks.php';
require 'Laravel/LaravelAllmanStyleBraces.php';
require 'Laravel/LaravelDecorator.php';
require 'Laravel/NamespaceMergeWithOpenTag.php';
require 'Laravel/NoneDocBlockMinorCleanUp.php';
require 'Laravel/NoSpaceBetweenFunctionAndBracket.php';
require 'Laravel/SortUseNamespace.php';
require 'Laravel/SpaceAroundExclamationMark.php';

if (!isset($inPhar)) {
	$inPhar = false;
}
if (!isset($testEnv)) {
	require 'cli.php';
}