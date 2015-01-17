<?php
require 'Core/constants.php';
require 'Core/FormatterPass.php';
require 'Additionals/AdditionalPass.php';
require 'Core/CodeFormatter.php';

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
require 'Core/ReindentLoopColonBlocks.php';
require 'Core/ReindentObjOps.php';
require 'Core/RemoveIncludeParentheses.php';
require 'Core/ResizeSpaces.php';
require 'Core/RTrim.php';
require 'Core/SettersAndGettersPass.php';
require 'Core/SurrogateToken.php';
require 'Core/TwoCommandsInSameLine.php';

include 'Core/Tree.php';

$code = '<?php
if($a):
	$a = 0;
	if($a):
		$b = 0;
	endif;
endif;

if($a){
	if($b){
		if($c > 10){
			$a;
		}



		$a = ${"b{$foo}"}->{"a{$bar->{\'string\'}()}d"}();
		while($c){
			noop();      // OK!
		}

		if(
		$c > 10 && // Terra
			$d < 10
		){
			$a;
		}
	}
}

for($a = 0; $a < 10; $a++){
	switch($a){
		case 1:
		if($a){
			noop();
		}
		break;

		case 2:
			noop();
		break;
	}
}

if($a)
	noop3();
';

$fmt = new CodeFormatter();

$fmt->addPass(new TwoCommandsInSameLine());
$fmt->addPass(new RemoveIncludeParentheses());
$fmt->addPass(new NormalizeIsNotEquals());
$fmt->addPass(new OrderUseClauses());
$fmt->addPass(new AddMissingCurlyBraces());
$fmt->addPass(new ExtraCommaInArray());
$fmt->addPass(new NormalizeLnAndLtrimLines());
$fmt->addPass(new MergeParenCloseWithCurlyOpen());
$fmt->addPass(new MergeCurlyCloseAndDoWhile());
$fmt->addPass(new MergeDoubleArrowAndArray());
$fmt->addPass(new ResizeSpaces());
$fmt->addPass(new Tree());
$fmt->addPass(new ReindentColonBlocks());
$fmt->addPass(new ReindentLoopColonBlocks());
$fmt->addPass(new ReindentIfColonBlocks());
$fmt->addPass(new ReindentObjOps());
$fmt->addPass(new EliminateDuplicatedEmptyLines());
$fmt->addPass(new LeftAlignComment());
$fmt->addPass(new RTrim());

echo $fmt->formatCode($code);
