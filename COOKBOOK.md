Cookbook - Making a new Fixer for php.tools
===========================================

If you want to customize php.tools pretty printer (fmt.phar), follow this document.

## Background

In order to customize fmt.phar, you need to understand it is a multipass transcompiler which takes valid PHP code and pretty print valid PHP code, making all transformations at typed tokens level.

All you need to do is to open a pull request with your change. As long as you add tests to your customization, and all other tests pass, your contribution shall be readily accepted. It will be reformatted and optimized later in the merge process.

Try to get acquainted with the `FormatterPass` class, which holds all the calls necessary to develop your customization.

## Assumptions

* Forked dericofilho/php.tools into your own Github Account.
* Cloned your forked repository locally.
* Installed php 5.6 or newer

## Step by step

For this cookbook, we are going to customize fmt.phar in way that removes all comments of the code that are preceded by ';' (semicolon).

We are calling it `RemoveComments` (class name).

### Step 1 - Creating files

Create a new file in
`php.tools/src/Additionals/RemoveComments.php`.
Put this content inside:
```php
<?php
class RemoveComments extends AdditionalPass {
	public function candidate($source, $foundTokens) {
		return false;
	}

	public function format($source) {
		return $source;
	}

	/**
	 * @codeCoverageIgnore
	 */
	public function getDescription() {
		return 'Remove comments preceded by semicolon.';
	}

	/**
	 * @codeCoverageIgnore
	 */
	public function getExample() {
		return <<<'EOT'
<?php
// This comment is ok
$a = new SomeClass; // this comment not

// This comment is ok
$a = new SomeClass;
?>
EOT;
	}
}
```

Now let us create the test files at
`php.tools/src/tests/352-remove-comments.in` and `php.tools/src/tests/352-remove-comments.in`. The number `352` should be replaced with the largest number of tests available within the tests folder.

Tests files ending with `.in` are input and `.out` are the expected output for that
particular test.

Thus, `php.tools/src/tests/352-remove-comments.in`:
```php
<?php
//passes:RemoveComments

// This comment is ok
$a = new SomeClass; // this comment not
```

And `php.tools/src/tests/352-remove-comments.out`:
```php
<?php
//passes:RemoveComments

// This comment is ok
$a = new SomeClass;
```

The first line `//passes:RemoveComments` is meant to tell the testing suite that a particular pass should be executed additionally to the standard Core.

#### Basic interface

The methods `getDescription()` and `getExample()` are meant to be used for help options in CLI application. They both expect strings in return, and should not be part of coverage analysis, hence the presence of `@codeCoverageIgnore`.

The method `candidate($source, $foundTokens)` returns a boolean value and it is executed before the whole transformation takes place. It is meant to tell the formatter whether this pass should be executed. In our case if no comments are present, then we do not need to execute it. You can inspect both the raw `$source` or the hashmap of `$foundTokens`. Thus:

```php
	public function candidate($source, $foundTokens) {
		if (isset($foundTokens[T_COMMENT])) {
			return true;
		}

		return false;
	}
```

The method `format($source)` takes raw `$source` code and allows you to act on it.

In the php.tools, passes work by iterating through pieces of codes (each being a Token), and inspecting what exists before that point in code  and making a decision of adding code, modifying, deleting or ignoring tokens

In our case, we want to find all comments, and iterate through each one of them check if they are preceded by a semicolon symbol.

Be sure to get acquainted with PHP default [list of parser
tokens](http://php.net/manual/en/tokens.php), and php.tools special token list at `src/Core/constants.php`.

### Step 2 - Implementation

Thus, `php.tools/src/Additionals/RemoveComments.php` becomes:
```php
<?php
class RemoveComments extends AdditionalPass {
	public function candidate($source, $foundTokens) {
		return false;
	}

	public function format($source) {
		// Convert $source into PHP token stream.
		$this->tkns = token_get_all($source);

		// Where the resulting code is stored.
		$this->code = '';

		// Iterate through each one of tokens in the stream
		while (list($index, $token) = each($this->tkns)) {

			// This extracts token representation. For typed tokens
			// it is a number which can be inspected with token_name(),
			// and the content of the token.
			// For untyped tokens, the content is returned twice.
			list($id, $text) = $this->getToken($token);

			// This is the pointer of traversal. Used for protected calls
			// in FormatterPass to check context and make decisions
			$this->ptr = $index;


			switch ($id) {
				// Effectively acts only on top of T_COMMENTS
				case T_COMMENT:
					// those comments whose left (previous) token
					// is a semi colon (ST_SEMI_COLON)
					if(!$this->leftTokenIs(ST_SEMI_COLON)){
						$this->appendCode($text);
					}
					break;

				// Otherwise just add the token output into $code
				default:
					$this->appendCode($text);
					break;
			}
		}

		return $this->code;
	}

	/**
	 * @codeCoverageIgnore
	 */
	public function getDescription() {
		return 'Remove comments preceded by semicolon.';
	}

	/**
	 * @codeCoverageIgnore
	 */
	public function getExample() {
		return <<<'EOT'
<?php
// This comment is ok
$a = new SomeClass; // this comment not

// This comment is ok
$a = new SomeClass;
?>
EOT;
	}
}
```

### Step 3 - Integrating the new pass.

Now that you have drafted the new pass, you need to declare this new pass in few places.

#### src/fmt.src.php

The building process serializes all source files into fmt.php. Thus, you need to manually include the new file into fmt.src.php. Around the lines:

`src/fmt.src.php`
```php
	require 'Additionals/PSR2EmptyFunction.php';
	require 'Additionals/PSR2MultilineFunctionParams.php';
	require 'Additionals/RemoveComments.php'; // Note how the alphabetical order is preserved
	require 'Additionals/RemoveUseLeadingSlash.php';
	require 'Additionals/ReplaceBooleanAndOr.php';
	require 'Additionals/ReplaceIsNull.php';
```

#### Core/BaseCodeFormatter.php

Sometimes, one pass must be executed after or before others. In BaseCodeFormatter.php you can define both when it is executed, and whether the execution of this fixer should disable the execution of another. Because we want to prevent the conservation of comments, as at least one of them might be removed, we want to shortcircuit RemoveComments with RestoreComments.

In `core/BaseCodeFormatter.php`, for shortcircuitting:
```php
	private $shortcircuit = [
		'ReindentAndAlignObjOps' => 'ReindentObjOps',
		'ReindentObjOps' => 'ReindentAndAlignObjOps',
		'AllmanStyleBraces' => 'PSR2CurlyOpenNextLine',
		'AlignGroupDoubleArrow' => 'AlignDoubleArrow',
		'AlignDoubleArrow' => 'AlignGroupDoubleArrow',
		'RemoveComments' => 'RestoreComments', // So RemoveComments disable RestoreComments
		'RestoreComments' => 'RemoveComments', // and vice-versa
	];
```

In `core/BaseCodeFormatter.php`, for execution order, we want it to live closer to the same point of RestoreComments:
```php
	private $passes = [
		// ...
		'AlignPHPCode' => false,
		'ConvertOpenTagWithEcho' => false,
		'RestoreComments' => false,
		'RemoveComments' => false, // Note that this hashmap is read from end to start. Thus RemoveComments is executed _before_ AlignPHPCode
		'UpgradeToPreg' => false,
		'DocBlockToComment' => false,
		'LongArray' => false,
		// ...
	];
```


### Step 4 - Test, Format, Build, Commit, PR.

Note that so far, we have not coded adhering to php.tools coding style, i.e., K&R indentation with tabs. For every commit you make, you must use fmt.phar to fix itself. Thus, on the command line call:

`$ php fmt.src.php Core/ Additionals/ PSR/ Laravel/ fmt.src.php refactor.src.php`

This will fix all the coding style mistakes. Now you need to test your changes.

`$ php test.php -v`

If all tests passes, you are ready to build the phar file.

`$ php build.php`

After the final build, you are ready to commit. Do it.

Now, go to Github and open a Pull Request. If your code have tests, it is adherent to coding style, and does not break any other tests, the incoming PR should be accepted readily.

