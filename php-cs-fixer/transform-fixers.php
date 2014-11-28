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

include '../constants.php';
include '../FormatterPass.php';
include '../RefactorPass.php';

class RemoveComments extends FormatterPass {
	public function format($source) {
		$this->tkns = token_get_all($source);
		$this->code = '';
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->get_token($token);
			$this->ptr = $index;
			switch ($id) {
				case T_COMMENT:
				case T_DOC_COMMENT:
					continue;
				default:
					$this->append_code($text);
			}
		}

		return $this->code;
	}
}

final class CodeFormatter {
	private $passes = [];
	public function addPass(FormatterPass $pass) {
		array_unshift($this->passes, $pass);
	}

	public function formatCode($source = '') {
		$passes = array_map(
			function ($pass) {
				return clone $pass;
			},
			$this->passes
		);
		while (($pass = array_pop($passes))) {
			$source = $pass->format($source);
		}
		return $source;
	}
}

$transform = new CodeFormatter();
$transform->addPass(new RemoveComments());
$transform->addPass(new RefactorPass('namespace Symfony\CS\Fixer\Contrib;', ''));
$transform->addPass(new RefactorPass('namespace Symfony\CS\Fixer\Symfony;', ''));
$transform->addPass(new RefactorPass('namespace Symfony\CS\Tokenizer;', ''));
$transform->addPass(new RefactorPass('use Symfony\CS\AbstractFixer;', ''));
$transform->addPass(new RefactorPass('use Symfony\CS\Tokenizer\Tokens;', ''));
$transform->addPass(new RefactorPass('use Symfony\CS\Tokenizer\Token;', ''));
$transform->addPass(new RefactorPass('extends AbstractFixer', 'extends FixerWrapper'));
$transform->addPass(new RefactorPass('public function fix(\SplFileInfo $file, $content)', 'public function format($content)'));
$transform->addPass(new RefactorPass('$tokens = Tokens::fromCode($content);', '$tokens = $this->fromCode($content);'));
$transform->addPass(new RefactorPass('$tokens->findGivenKind(', '$this->findGivenKind($tokens, '));
$transform->addPass(new RefactorPass('$tokens->generateCode()', '$this->render($tokens)'));
$transform->addPass(new RefactorPass('$tokens->getPrevNonWhitespace($index);', '$this->getPrevNonWhitespace($tokens, $index);'));
$transform->addPass(new RefactorPass('$tokens->count()', 'count($tokens)'));

$start = microtime(true);
echo 'Transforming...', PHP_EOL;
$dir = new RecursiveDirectoryIterator('.');
$it = new RecursiveIteratorIterator($dir);
$files = new RegexIterator($it, '/^.+\.php$/i', RecursiveRegexIterator::GET_MATCH);
$fileCount = 0;
foreach ($files as $file) {
	$file = $file[0];
	if ('./transform-fixers.php' == $file) {
		continue;
	}
	++$fileCount;
	echo '.';
	file_put_contents($file . '-tmp', $transform->formatCode(file_get_contents($file)));
	unlink($file);
	rename($file . '-tmp', $file);
	if (0 == ($fileCount % 20)) {
		echo ' ', $fileCount, PHP_EOL;
	}
}
echo ' ', $fileCount, ' files', PHP_EOL;
echo 'Took ', ceil(microtime(true) - $start), ' seconds', PHP_EOL;