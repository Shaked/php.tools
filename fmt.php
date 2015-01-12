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
	// The MIT License (MIT)
//
// Copyright (c) 2014 Carlos Cirello
//
// Permission is hereby granted, free of charge, to any person obtaining a copy
// of this software and associated documentation files (the "Software"), to deal
// in the Software without restriction, including without limitation the rights
// to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
// copies of the Software, and to permit persons to whom the Software is
// furnished to do so, subject to the following conditions:
//
// The above copyright notice and this permission notice shall be included in all
// copies or substantial portions of the Software.
//
// THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
// IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
// FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
// AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
// LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
// OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
// SOFTWARE.

define('PHP_INT_LENGTH', strlen(sprintf("%u", PHP_INT_MAX)));
function cofunc(callable$fn) {
	$pid = pcntl_fork();
	if (-1 == $pid) {
		trigger_error('could not fork', E_ERROR);
	} elseif ($pid) {
		// I am the parent
	} else {
		$params = [];
		if (func_num_args() > 1) {
			$params = array_slice(func_get_args(), 1);
		}
		call_user_func_array($fn, $params);
		die();
	}
}

class CSP_Channel {
	private $ipc;
	private $ipc_fn;
	private $key;
	private $closed = false;
	private $msg_count = 0;
	public function __construct() {
		$this->ipc_fn = tempnam(sys_get_temp_dir(), 'csp.' . uniqid('chn', true));
		$this->key = ftok($this->ipc_fn, 'A');
		$this->ipc = msg_get_queue($this->key, 0666);
		msg_set_queue($this->ipc, $cfg = [
			'msg_qbytes' => (1 * PHP_INT_LENGTH),
		]);

	}
	public function close() {
		$this->closed = true;
		do {
			$this->out();
			--$this->msg_count;
		} while ($this->msg_count >= 0);
		msg_remove_queue($this->ipc);
		file_exists($this->ipc_fn) && @unlink($this->ipc_fn);
	}
	public function in($msg) {
		if ($this->closed || !msg_queue_exists($this->key)) {
			return;
		}
		++$this->msg_count;
		$shm = new Message();
		$shm->store($msg);
		@msg_send($this->ipc, 1, $shm->key(), false);
	}
	public function out() {
		if ($this->closed || !msg_queue_exists($this->key)) {
			return;
		}
		$msgtype = null;
		$ipcmsg = null;
		$error = null;
		msg_receive($this->ipc, 1, $msgtype, (1 * PHP_INT_LENGTH) + 1, $ipcmsg, false, $error);
		--$this->msg_count;
		$shm = new Message($ipcmsg);
		$ret = $shm->fetch();
		return $ret;
	}
}
class Message {
	private $key;
	private $shm;
	public function __construct($key = null) {
		if (null === $key) {
			$key = ftok(tempnam(sys_get_temp_dir(), 'csp.' . uniqid('shm', true)), 'C');
		}
		$this->shm = shm_attach($key);
		if (false === $this->shm) {
			trigger_error('Unable to attach shared memory segment for channel', E_ERROR);
		}
		$this->key = $key;
	}
	public function store($msg) {
		shm_put_var($this->shm, 1, $msg);
		shm_detach($this->shm);
	}
	public function key() {
		return sprintf('%0' . PHP_INT_LENGTH . 'd', (int) $this->key);
	}
	public function fetch() {
		$ret = shm_get_var($this->shm, 1);
		$this->destroy();
		return $ret;

	}
	public function destroy() {
		if (shm_has_var($this->shm, 1)) {
			shm_remove_var($this->shm, 1);
		}
		shm_remove($this->shm);
	}
}

function make_channel() {
	return new CSP_Channel();
}
;
}
$enable_cache = false;
if (class_exists('SQLite3')) {
	$enable_cache = true;
	/**
 * @codeCoverageIgnore
 */
class Cache {
	const DEFAULT_CACHE_FILENAME = '.php.tools.cache';

	private $db;

	public function __construct($filename) {
		$startDbCreation = false;
		if (is_dir($filename)) {
			$filename = realpath($filename) . DIRECTORY_SEPARATOR . self::DEFAULT_CACHE_FILENAME;
		}
		if (!file_exists($filename)) {
			$startDbCreation = true;
		}

		$this->setDb(new SQLite3($filename));
		$this->db->busyTimeout(1000);
		if ($startDbCreation) {
			$this->create_db();
		}
	}

	public function __destruct() {
		$this->db->close();
	}

	public function create_db() {
		$this->db->exec('CREATE TABLE cache (target TEXT, filename TEXT, hash TEXT, unique(target, filename));');
	}

	public function upsert($target, $filename, $content) {
		$hash = $this->calculateHash($content);
		$this->db->exec('REPLACE INTO cache VALUES ("' . SQLite3::escapeString($target) . '","' . SQLite3::escapeString($filename) . '", "' . SQLite3::escapeString($hash) . '")');
	}

	public function is_changed($target, $filename) {
		$row = $this->db->querySingle('SELECT hash FROM cache WHERE target = "' . SQLite3::escapeString($target) . '" AND filename = "' . SQLite3::escapeString($filename) . '"', true);
		$content = file_get_contents($filename);
		if (empty($row)) {
			return $content;
		}
		if ($this->calculateHash($content) != $row['hash']) {
			return $content;
		}
		return false;
	}

	private function setDb($db) {
		$this->db = $db;
	}

	private function calculateHash($content) {
		return sprintf('%u', crc32($content));
	}
}
;
}
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
define("ST_AT", "@");
define("ST_BRACKET_CLOSE", "]");
define("ST_BRACKET_OPEN", "[");
define("ST_COLON", ":");
define("ST_COMMA", ",");
define("ST_CONCAT", ".");
define("ST_CURLY_CLOSE", "}");
define("ST_CURLY_OPEN", "{");
define("ST_DIVIDE", "/");
define("ST_DOLLAR", "$");
define("ST_EQUAL", "=");
define("ST_EXCLAMATION", "!");
define("ST_IS_GREATER", ">");
define("ST_IS_SMALLER", "<");
define("ST_MINUS", "-");
define("ST_MODULUS", "%");
define("ST_PARENTHESES_CLOSE", ")");
define("ST_PARENTHESES_OPEN", "(");
define("ST_PLUS", "+");
define("ST_QUESTION", "?");
define("ST_QUOTE", '"');
define("ST_REFERENCE", "&");
define("ST_SEMI_COLON", ";");
define("ST_TIMES", "*");
define("ST_BITWISE_OR", "|");
define("ST_BITWISE_XOR", "^");
if (!defined("T_POW")) {
	define("T_POW", "**");
}
if (!defined("T_POW_EQUAL")) {
	define("T_POW_EQUAL", "**=");
}
if (!defined("T_YIELD")) {
	define("T_YIELD", "yield");
}
if (!defined("T_FINALLY")) {
	define("T_FINALLY", "finally");
}
;
abstract class FormatterPass {
	protected $indentChar = "\t";
	protected $newLine = "\n";
	protected $indent = 0;
	protected $code = '';
	protected $ptr = 0;
	protected $tkns = [];
	protected $useCache = false;
	protected $cache = [];
	protected $ignoreFutileTokens = [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT];

	protected function appendCode($code = "") {
		$this->code .= $code;
	}

	private function calculateCacheKey($direction, $ignoreList, $token) {
		return $direction . "\x2" . implode('', $ignoreList) . "\x2" . (is_array($token) ? implode("\x2", $token) : $token);
	}

	abstract public function candidate($source, $foundTokens);
	abstract public function format($source);

	protected function getToken($token) {
		if (isset($token[1])) {
			return $token;
		} else {
			return [$token, $token];
		}
	}

	protected function getCrlf($true = true) {
		return $true ? $this->newLine : "";
	}

	protected function getCrlfIndent() {
		return $this->getCrlf() . $this->getIndent();
	}

	protected function getIndent($increment = 0) {
		return str_repeat($this->indentChar, $this->indent + $increment);
	}

	protected function getSpace($true = true) {
		return $true ? " " : "";
	}

	protected function hasLn($text) {
		return (false !== strpos($text, $this->newLine));
	}

	protected function hasLnAfter() {
		$id = null;
		$text = null;
		list($id, $text) = $this->inspectToken();
		return T_WHITESPACE === $id && $this->hasLn($text);
	}

	protected function hasLnBefore() {
		$id = null;
		$text = null;
		list($id, $text) = $this->inspectToken(-1);
		return T_WHITESPACE === $id && $this->hasLn($text);
	}

	protected function hasLnLeftToken() {
		list($id, $text) = $this->getToken($this->leftToken());
		return $this->hasLn($text);
	}

	protected function hasLnRightToken() {
		list($id, $text) = $this->getToken($this->rightToken());
		return $this->hasLn($text);
	}

	protected function inspectToken($delta = 1) {
		if (!isset($this->tkns[$this->ptr + $delta])) {
			return [null, null];
		}
		return $this->getToken($this->tkns[$this->ptr + $delta]);
	}

	protected function leftToken($ignoreList = [], $idx = false) {
		$i = $this->leftTokenIdx($ignoreList);

		return $this->tkns[$i];
	}

	protected function leftTokenIdx($ignoreList = []) {
		$ignoreList = $this->resolveIgnoreList($ignoreList);

		$i = $this->walkLeft($this->tkns, $this->ptr, $ignoreList);

		return $i;
	}

	protected function leftTokenIs($token, $ignoreList = []) {
		return $this->tokenIs('left', $token, $ignoreList);
	}

	protected function leftTokenSubsetIsAtIdx($tkns, $idx, $token, $ignoreList = []) {
		$ignoreList = $this->resolveIgnoreList($ignoreList);

		$idx = $this->walkLeft($tkns, $idx, $ignoreList);

		return $this->resolveTokenMatch($tkns, $idx, $token);
	}

	protected function leftUsefulToken() {
		return $this->leftToken($this->ignoreFutileTokens);
	}

	protected function leftUsefulTokenIdx() {
		return $this->leftTokenIdx($this->ignoreFutileTokens);
	}

	protected function leftUsefulTokenIs($token) {
		return $this->leftTokenIs($token, $this->ignoreFutileTokens);
	}

	protected function printAndStopAt($tknids) {
		if (is_scalar($tknids)) {
			$tknids = [$tknids];
		}
		$tknids = array_flip($tknids);
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->getToken($token);
			$this->ptr = $index;
			$this->cache = [];
			if (isset($tknids[$id])) {
				return [$id, $text];
			}
			$this->appendCode($text);
		}
	}

	protected function printBlock($start, $end) {
		$count = 1;
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->getToken($token);
			$this->ptr = $index;
			$this->cache = [];
			$this->appendCode($text);

			if ($start == $id) {
				++$count;
			}
			if ($end == $id) {
				--$count;
			}
			if (0 == $count) {
				break;
			}
		}
	}

	protected function printCurlyBlock() {
		$count = 1;
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->getToken($token);
			$this->ptr = $index;
			$this->cache = [];
			$this->appendCode($text);

			if (ST_CURLY_OPEN == $id) {
				++$count;
			}
			if (T_CURLY_OPEN == $id) {
				++$count;
			}
			if (T_DOLLAR_OPEN_CURLY_BRACES == $id) {
				++$count;
			}
			if (ST_CURLY_CLOSE == $id) {
				--$count;
			}
			if (0 == $count) {
				break;
			}
		}
	}

	protected function printUntil($tknid) {
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->getToken($token);
			$this->ptr = $index;
			$this->cache = [];
			$this->appendCode($text);
			if ($tknid == $id) {
				break;
			}
		}
	}

	protected function printUntilAny($tknids) {
		$tknids = array_flip($tknids);
		$whitespaceNewLine = false;
		if (isset($tknids[$this->newLine])) {
			$whitespaceNewLine = true;
		}
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->getToken($token);
			$this->ptr = $index;
			$this->cache = [];
			$this->appendCode($text);
			if ($whitespaceNewLine && T_WHITESPACE == $id && $this->hasLn($text)) {
				break;
			}
			if (isset($tknids[$id])) {
				break;
			}
		}
		return $id;
	}

	protected function printUntilTheEndOfString() {
		$this->printUntil(ST_QUOTE);
	}

	protected function render($tkns = null) {
		if (null == $tkns) {
			$tkns = $this->tkns;
		}

		$tkns = array_filter($tkns);
		$str = '';
		foreach ($tkns as $token) {
			list($id, $text) = $this->getToken($token);
			$str .= $text;
		}
		return $str;
	}

	protected function renderLight($tkns = null) {
		if (null == $tkns) {
			$tkns = $this->tkns;
		}
		$str = '';
		foreach ($tkns as $token) {
			$str .= $token[1];
		}
		return $str;
	}

	private function resolveIgnoreList($ignoreList = []) {
		if (empty($ignoreList)) {
			$ignoreList[T_WHITESPACE] = true;
		} else {
			$ignoreList = array_flip($ignoreList);
		}
		return $ignoreList;
	}

	private function resolveTokenMatch($tkns, $idx, $token) {
		if (!isset($tkns[$idx])) {
			return false;
		}

		$foundToken = $tkns[$idx];
		if ($foundToken === $token) {
			return true;
		} elseif (is_array($token) && isset($foundToken[1]) && in_array($foundToken[0], $token)) {
			return true;
		} elseif (is_array($token) && !isset($foundToken[1]) && in_array($foundToken, $token)) {
			return true;
		} elseif (isset($foundToken[1]) && $foundToken[0] == $token) {
			return true;
		}

		return false;
	}

	protected function rightToken($ignoreList = []) {
		$i = $this->rightTokenIdx($ignoreList);

		return $this->tkns[$i];
	}

	protected function rightTokenIdx($ignoreList = []) {
		$ignoreList = $this->resolveIgnoreList($ignoreList);

		$i = $this->walkRight($this->tkns, $this->ptr, $ignoreList);

		return $i;
	}

	protected function rightTokenIs($token, $ignoreList = []) {
		return $this->tokenIs('right', $token, $ignoreList);
	}

	protected function rightTokenSubsetIsAtIdx($tkns, $idx, $token, $ignoreList = []) {
		$ignoreList = $this->resolveIgnoreList($ignoreList);

		$idx = $this->walkRight($tkns, $idx, $ignoreList);

		return $this->resolveTokenMatch($tkns, $idx, $token);
	}

	protected function rightUsefulToken() {
		return $this->rightToken($this->ignoreFutileTokens);
	}

	// protected function rightUsefulTokenIdx($idx = false) {
	// 	return $this->rightTokenIdx($this->ignoreFutileTokens);
	// }

	protected function rightUsefulTokenIs($token) {
		return $this->rightTokenIs($token, $this->ignoreFutileTokens);
	}

	protected function rtrimAndAppendCode($code = "") {
		$this->code = rtrim($this->code) . $code;
	}

	protected function scanAndReplace(&$tkns, &$ptr, $start, $end, $call, $look_for) {
		$look_for = array_flip($look_for);
		$placeholder = '<?php' . ' /*\x2 PHPOPEN \x3*/';
		$tmp = '';
		$tknCount = 1;
		$foundPotentialTokens = false;
		while (list($ptr, $token) = each($tkns)) {
			list($id, $text) = $this->getToken($token);
			if (isset($look_for[$id])) {
				$foundPotentialTokens = true;
			}
			if ($start == $id) {
				++$tknCount;
			}
			if ($end == $id) {
				--$tknCount;
			}
			$tkns[$ptr] = null;
			if (0 == $tknCount) {
				break;
			}
			$tmp .= $text;
		}
		if ($foundPotentialTokens) {
			return $start . str_replace($placeholder, '', $this->{$call}($placeholder . $tmp)) . $end;
		}
		return $start . $tmp . $end;

	}

	protected function setIndent($increment) {
		$this->indent += $increment;
		if ($this->indent < 0) {
			$this->indent = 0;
		}
	}

	protected function siblings($tkns, $ptr) {
		$ignoreList = $this->resolveIgnoreList([T_WHITESPACE]);
		$left = $this->walkLeft($tkns, $ptr, $ignoreList);
		$right = $this->walkRight($tkns, $ptr, $ignoreList);
		return [$left, $right];
	}

	protected function substrCountTrailing($haystack, $needle) {
		return strlen(rtrim($haystack, " \t")) - strlen(rtrim($haystack, " \t" . $needle));
	}

	protected function tokenIs($direction, $token, $ignoreList = []) {
		if ('left' != $direction) {
			$direction = 'right';
		}
		if (!$this->useCache) {
			return $this->{$direction . 'tokenSubsetIsAtIdx'}($this->tkns, $this->ptr, $token, $ignoreList);
		}

		$key = $this->calculateCacheKey($direction, $ignoreList, $token);
		if (isset($this->cache[$key])) {
			return $this->cache[$key];
		}

		$ret = $this->{$direction . 'tokenSubsetIsAtIdx'}($this->tkns, $this->ptr, $token, $ignoreList);
		$this->cache[$key] = $ret;

		return $ret;
	}

	protected function walkAndAccumulateUntil(&$tkns, $tknid) {
		$ret = '';
		while (list($index, $token) = each($tkns)) {
			list($id, $text) = $this->getToken($token);
			$this->ptr = $index;
			$ret .= $text;
			if ($tknid == $id) {
				break;
			}
		}
		return $ret;
	}

	private function walkLeft($tkns, $idx, $ignoreList) {
		$i = $idx;
		while (--$i >= 0 && isset($tkns[$i][1]) && isset($ignoreList[$tkns[$i][0]]));
		return $i;
	}

	private function walkRight($tkns, $idx, $ignoreList) {
		$i = $idx;
		$tknsSize = sizeof($tkns) - 1;
		while (++$i < $tknsSize && isset($tkns[$i][1]) && isset($ignoreList[$tkns[$i][0]]));
		return $i;
	}

	protected function walkUntil($tknid) {
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->getToken($token);
			$this->ptr = $index;
			if ($id == $tknid) {
				return [$id, $text];
			}
		}
	}
}
;
abstract class AdditionalPass extends FormatterPass {
	abstract public function getDescription();
	abstract public function getExample();
}
;
/**
 * @codeCoverageIgnore
 */
final class CodeFormatter {
	private $passes = [];
	public function addPass(FormatterPass $pass) {
		array_unshift($this->passes, $pass);
	}
	public function removePass($passName) {
		$idx = [];
		foreach ($this->passes as $k => $pass) {
			if (get_class($pass) == $passName) {
				$idx[] = $k;
			}
		}
		foreach ($idx as $k) {
			unset($this->passes[$k]);
		}
		$this->passes = array_values($this->passes);
	}
	public function getPassesNames() {
		return array_map(function ($v) {
			return get_class($v);
		}, $this->passes);
	}
	public function formatCode($source = '') {
		$passes = array_map(
			function ($pass) {
				return clone $pass;
			},
			$this->passes
		);
		$foundTokens = [];
		$tkns = token_get_all($source);
		foreach ($tkns as $token) {
			list($id, $text) = $this->getToken($token);
			$foundTokens[$id] = $id;
		}
		while (($pass = array_pop($passes))) {
			if ($pass->candidate($source, $foundTokens)) {
				$source = $pass->format($source);
			}
		}
		return $source;
	}

	protected function getToken($token) {
		if (isset($token[1])) {
			return $token;
		} else {
			return [$token, $token];
		}
	}
};

final class AddMissingCurlyBraces extends FormatterPass {
	public function candidate($source, $foundTokens) {
		return true;
	}
	public function format($source) {
		list($tmp, $changed) = $this->addBraces($source);
		while ($changed) {
			list($source, $changed) = $this->addBraces($tmp);
			if ($source === $tmp) {
				break;
			}
			$tmp = $source;
		}
		return $source;
	}
	private function addBraces($source) {
		$this->tkns = token_get_all($source);
		$this->code = '';
		$this->useCache = true;
		$changed = false;
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->getToken($token);
			$this->ptr = $index;
			$this->cache = [];
			switch ($id) {
				case T_WHILE:
				case T_FOREACH:
				case T_FOR:
					$this->appendCode($text);
					$parenCount = null;
					while (list($index, $token) = each($this->tkns)) {
						list($id, $text) = $this->getToken($token);
						$this->ptr = $index;
						$this->cache = [];
						if (ST_PARENTHESES_OPEN === $id) {
							++$parenCount;
						} elseif (ST_PARENTHESES_CLOSE === $id) {
							--$parenCount;
						}
						$this->appendCode($text);
						if (0 === $parenCount && !$this->rightTokenIs([T_COMMENT, T_DOC_COMMENT])) {
							break;
						}
					}
					if (!$this->rightTokenIs([ST_CURLY_OPEN, ST_COLON, ST_SEMI_COLON])) {
						$whileInNextToken = $this->rightTokenIs([T_WHILE, T_DO]);
						$ignoreCount = 0;
						if (!$this->leftTokenIs([T_COMMENT, T_DOC_COMMENT])) {
							$this->rtrimAndAppendCode($this->newLine . '{');
						}
						while (list($index, $token) = each($this->tkns)) {
							list($id, $text) = $this->getToken($token);
							$this->ptr = $index;
							$this->cache = [];

							if (ST_QUOTE == $id) {
								$this->appendCode($text);
								$this->printUntilTheEndOfString();
								continue;
							}

							if (ST_PARENTHESES_OPEN === $id || ST_CURLY_OPEN === $id || ST_BRACKET_OPEN === $id) {
								++$ignoreCount;
							} elseif (ST_PARENTHESES_CLOSE === $id || ST_CURLY_CLOSE === $id || ST_BRACKET_CLOSE === $id) {
								--$ignoreCount;
							}
							$this->appendCode($text);
							if (ST_SEMI_COLON != $id && $this->rightTokenIs(T_CLOSE_TAG)) {
								$this->appendCode(ST_SEMI_COLON);
								break;
							}
							if ($ignoreCount <= 0 && !($this->rightTokenIs([ST_CURLY_CLOSE, ST_SEMI_COLON, T_OBJECT_OPERATOR, ST_PARENTHESES_OPEN, ST_EQUAL]) || ($whileInNextToken && $this->rightTokenIs([T_WHILE]))) && (ST_CURLY_CLOSE === $id || ST_SEMI_COLON === $id || T_ELSE === $id || T_ELSEIF === $id)) {
								break;
							}
						}
						$this->appendCode($this->getCrlfIndent() . '}' . $this->getCrlfIndent());
						$changed = true;
						break 2;
					}
					break;
				case T_IF:
				case T_ELSEIF:
					$this->appendCode($text);
					$parenCount = null;
					while (list($index, $token) = each($this->tkns)) {
						list($id, $text) = $this->getToken($token);
						$this->ptr = $index;
						$this->cache = [];
						if (ST_PARENTHESES_OPEN === $id) {
							++$parenCount;
						} elseif (ST_PARENTHESES_CLOSE === $id) {
							--$parenCount;
						}
						$this->appendCode($text);
						if (0 === $parenCount && !$this->rightTokenIs([T_COMMENT, T_DOC_COMMENT])) {
							break;
						}
					}
					if (!$this->rightTokenIs([ST_CURLY_OPEN, ST_COLON])) {
						$whileInNextToken = $this->rightTokenIs([T_WHILE, T_DO]);
						$ignoreCount = 0;
						if (!$this->leftTokenIs([T_COMMENT, T_DOC_COMMENT])) {
							$this->rtrimAndAppendCode($this->newLine . '{');
						}
						while (list($index, $token) = each($this->tkns)) {
							list($id, $text) = $this->getToken($token);
							$this->ptr = $index;
							$this->cache = [];

							if (ST_QUOTE == $id) {
								$this->appendCode($text);
								$this->printUntilTheEndOfString();
								continue;
							}
							if (ST_PARENTHESES_OPEN === $id || ST_CURLY_OPEN === $id || ST_BRACKET_OPEN === $id) {
								++$ignoreCount;
							} elseif (ST_PARENTHESES_CLOSE === $id || ST_CURLY_CLOSE === $id || ST_BRACKET_CLOSE === $id) {
								--$ignoreCount;
							}
							$this->appendCode($text);
							if (T_INLINE_HTML == $id && !$this->rightTokenIs(T_OPEN_TAG)) {
								$this->appendCode('<?php');
							}
							if ($ignoreCount <= 0 && !($this->rightTokenIs([ST_CURLY_CLOSE, ST_SEMI_COLON, T_OBJECT_OPERATOR, ST_PARENTHESES_OPEN]) || ($whileInNextToken && $this->rightTokenIs([T_WHILE]))) && (ST_CURLY_CLOSE === $id || ST_SEMI_COLON === $id || T_ELSE === $id || T_ELSEIF === $id)) {
								break;
							}
						}
						$this->appendCode($this->getCrlfIndent() . '}' . $this->getCrlfIndent());
						$changed = true;
						break 2;
					}
					break;
				case T_ELSE:
					$this->appendCode($text);
					if (!$this->rightTokenIs([ST_CURLY_OPEN, ST_COLON, T_IF])) {
						$whileInNextToken = $this->rightTokenIs([T_WHILE, T_DO]);
						$ignoreCount = 0;
						$this->rtrimAndAppendCode('{');
						while (list($index, $token) = each($this->tkns)) {
							list($id, $text) = $this->getToken($token);
							$this->ptr = $index;
							$this->cache = [];

							if (ST_QUOTE == $id) {
								$this->appendCode($text);
								$this->printUntilTheEndOfString();
								continue;
							}

							if (ST_PARENTHESES_OPEN === $id || ST_CURLY_OPEN === $id || ST_BRACKET_OPEN === $id) {
								++$ignoreCount;
							} elseif (ST_PARENTHESES_CLOSE === $id || ST_CURLY_CLOSE === $id || ST_BRACKET_CLOSE === $id) {
								--$ignoreCount;
							}
							$this->appendCode($text);
							if (T_INLINE_HTML == $id && !$this->rightTokenIs(T_OPEN_TAG)) {
								$this->appendCode('<?php');
							}
							if ($ignoreCount <= 0 && !($this->rightTokenIs([ST_CURLY_CLOSE, ST_SEMI_COLON, T_OBJECT_OPERATOR, ST_PARENTHESES_OPEN]) || ($whileInNextToken && $this->rightTokenIs([T_WHILE]))) && (ST_CURLY_CLOSE === $id || ST_SEMI_COLON === $id || T_ELSE === $id || T_ELSEIF === $id)) {
								break;
							}
						}
						$this->appendCode($this->getCrlfIndent() . '}' . $this->getCrlfIndent());
						$changed = true;
						break 2;
					}
					break;
				default:
					$this->appendCode($text);
					break;
			}
		}
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->getToken($token);
			$this->ptr = $index;
			$this->appendCode($text);
		}

		return [$this->code, $changed];
	}
}
;
/**
 * @codeCoverageIgnore
 */
final class AutoImportPass extends FormatterPass {
	const OPENER_PLACEHOLDER = "<?php /*\x2 AUTOIMPORTNS \x3*/";
	const AUTOIMPORT_PLACEHOLDER = "/*\x2 AUTOIMPORT \x3*/";
	private $oracle = null;

	public function __construct($oracleFn) {
		$this->oracle = new SQLite3($oracleFn);
	}

	public function candidate($source, $foundTokens) {
		return true;
	}

	private function usedAliasList($source) {
		$tokens = token_get_all($source);
		$useStack = [];
		$newTokens = [];
		$nextTokens = [];
		$touchedNamespace = false;
		while (list(, $popToken) = each($tokens)) {
			$nextTokens[] = $popToken;
			while (($token = array_shift($nextTokens))) {
				list($id, $text) = $this->getToken($token);
				if (T_NAMESPACE == $id) {
					$touchedNamespace = true;
				}
				if (T_USE === $id) {
					$useItem = $text;
					while (list(, $token) = each($tokens)) {
						list($id, $text) = $this->getToken($token);
						if (ST_SEMI_COLON === $id) {
							$useItem .= $text;
							break;
						} elseif (ST_COMMA === $id) {
							$useItem .= ST_SEMI_COLON . $this->newLine;
							$nextTokens[] = [T_USE, 'use'];
							break;
						} else {
							$useItem .= $text;
						}
					}
					$useStack[] = $useItem;
					$token = new SurrogateToken();
				}
				if (T_FINAL === $id || T_ABSTRACT === $id || T_INTERFACE === $id || T_CLASS === $id || T_FUNCTION === $id || T_TRAIT === $id || T_VARIABLE === $id) {
					if (sizeof($useStack) > 0) {
						$newTokens[] = $this->newLine;
						$newTokens[] = $this->newLine;
					}
					$newTokens[] = $token;
					break 2;
				} elseif ($touchedNamespace && (T_DOC_COMMENT === $id || T_COMMENT === $id)) {
					if (sizeof($useStack) > 0) {
						$newTokens[] = $this->newLine;
					}
					$newTokens[] = $token;
					break 2;
				}
				$newTokens[] = $token;
			}
		}

		natcasesort($useStack);
		$aliasList = [];
		$aliasCount = [];
		foreach ($useStack as $use) {
			if (false !== stripos($use, ' as ')) {
				$alias = substr(strstr($use, ' as '), strlen(' as '), -1);
			} else {
				$alias = basename(str_replace('\\', '/', trim(substr($use, strlen('use'), -1))));
			}
			$alias = strtolower($alias);
			$aliasList[$alias] = strtolower($use);
			$aliasCount[$alias] = 0;
		}
		foreach ($newTokens as $token) {
			if (!($token instanceof SurrogateToken)) {
				list($id, $text) = $this->getToken($token);
				$lower_text = strtolower($text);
				if (T_STRING === $id && isset($aliasList[$lower_text])) {
					++$aliasCount[$lower_text];
				}
			}
		}

		while (list($index, $token) = each($tokens)) {
			list($id, $text) = $this->getToken($token);
			$lower_text = strtolower($text);
			if (T_STRING === $id && isset($aliasList[$lower_text]) && ($this->leftTokenSubsetIsAtIdx($tokens, $index, T_NEW) || $this->rightTokenSubsetIsAtIdx($tokens, $index, T_DOUBLE_COLON))) {
				++$aliasCount[$lower_text];
			} elseif (T_DOC_COMMENT === $id) {
				foreach ($aliasList as $alias => $use) {
					if (false !== stripos($text, $alias)) {
						++$aliasCount[$alias];
					}
				}
			}
		}
		return $aliasCount;
	}

	private function singleNamespace($source) {
		$classList = [];
		$results = $this->oracle->query("SELECT class FROM classes ORDER BY class");
		while (($row = $results->fetchArray())) {
			$className = $row['class'];
			$classNameParts = explode('\\', $className);
			$baseClassName = '';
			while (($cnp = array_pop($classNameParts))) {
				$baseClassName = $cnp . $baseClassName;
				$classList[strtolower($baseClassName)][ltrim(str_replace('\\\\', '\\', '\\' . $className) . ' as ' . $baseClassName, '\\')] = ltrim(str_replace('\\\\', '\\', '\\' . $className) . ' as ' . $baseClassName, '\\');
			}
		}

		$tokens = token_get_all($source);
		$aliasCount = [];
		$namespaceName = '';
		while (list($index, $token) = each($tokens)) {
			list($id, $text) = $this->getToken($token);
			if (T_NAMESPACE == $id) {
				while (list($index, $token) = each($tokens)) {
					list($id, $text) = $this->getToken($token);
					if (T_NS_SEPARATOR == $id || T_STRING == $id) {
						$namespaceName .= $text;
					}
					if (ST_SEMI_COLON == $id || ST_CURLY_OPEN == $id) {
						break;
					}
				}
			}
			if (T_USE == $id || T_NAMESPACE == $id || T_FUNCTION == $id || T_DOUBLE_COLON == $id || T_OBJECT_OPERATOR == $id) {
				while (list($index, $token) = each($tokens)) {
					list($id, $text) = $this->getToken($token);
					if (ST_SEMI_COLON == $id || ST_PARENTHESES_OPEN == $id || ST_CURLY_OPEN == $id) {
						break;
					}
				}
			}
			if (T_CLASS == $id) {
				while (list($index, $token) = each($tokens)) {
					list($id, $text) = $this->getToken($token);
					if (T_EXTENDS == $id || T_IMPLEMENTS == $id || ST_CURLY_OPEN == $id) {
						break;
					}
				}
			}

			$lower_text = strtolower($text);
			if (T_STRING === $id && ($this->leftTokenSubsetIsAtIdx($tokens, $index, T_NEW) || $this->rightTokenSubsetIsAtIdx($tokens, $index, T_DOUBLE_COLON))) {
				if (!isset($aliasCount[$lower_text])) {
					$aliasCount[$lower_text] = 0;
				}
				++$aliasCount[$lower_text];
			}
		}
		$autoImportCandidates = array_intersect_key($classList, $aliasCount);

		$tokens = token_get_all($source);
		$touchedNamespace = false;
		$touchedFunction = false;
		$return = '';
		while (list(, $token) = each($tokens)) {
			list($id, $text) = $this->getToken($token);

			if (T_NAMESPACE == $id) {
				$touchedNamespace = true;
			}
			if (T_FUNCTION == $id) {
				$touchedFunction = true;
			}
			if (!$touchedFunction && $touchedNamespace && (T_FINAL == $id || T_STATIC == $id || T_USE == $id || T_CLASS == $id || T_INTERFACE == $id || T_TRAIT == $id)) {
				$return .= self::AUTOIMPORT_PLACEHOLDER . $this->newLine;
				$return .= $text;

				break;
			}
			$return .= $text;
		}
		while (list(, $token) = each($tokens)) {
			list($id, $text) = $this->getToken($token);
			$return .= $text;
		}

		$usedAlias = $this->usedAliasList($source);
		$replacement = '';
		foreach ($autoImportCandidates as $alias => $candidates) {
			if (isset($usedAlias[$alias])) {
				continue;
			}
			usort($candidates, function ($a, $b) use ($namespaceName) {
				return similar_text($a, $namespaceName) < similar_text($b, $namespaceName);
			});
			$replacement .= 'use ' . implode(';' . $this->newLine . '//use ', $candidates) . ';' . $this->newLine;
		}

		$return = str_replace(self::AUTOIMPORT_PLACEHOLDER . $this->newLine, $replacement, $return);
		return $return;
	}
	public function format($source = '') {
		$namespaceCount = 0;
		$tokens = token_get_all($source);
		while (list(, $token) = each($tokens)) {
			list($id, $text) = $this->getToken($token);
			if (T_NAMESPACE == $id) {
				++$namespaceCount;
			}
		}
		if ($namespaceCount <= 1) {
			return $this->singleNamespace($source);
		}

		$return = '';
		reset($tokens);
		while (list($index, $token) = each($tokens)) {
			list($id, $text) = $this->getToken($token);
			$this->ptr = $index;
			switch ($id) {
				case T_NAMESPACE:
					$return .= $text;
					while (list($index, $token) = each($tokens)) {
						list($id, $text) = $this->getToken($token);
						$this->ptr = $index;
						$return .= $text;
						if (ST_CURLY_OPEN == $id) {
							break;
						}
					}
					$namespaceBlock = '';
					$curlyCount = 1;
					while (list($index, $token) = each($tokens)) {
						list($id, $text) = $this->getToken($token);
						$this->ptr = $index;
						$namespaceBlock .= $text;
						if (ST_CURLY_OPEN == $id) {
							++$curlyCount;
						} elseif (ST_CURLY_CLOSE == $id) {
							--$curlyCount;
						}

						if (0 == $curlyCount) {
							break;
						}
					}
					$return .= str_replace(
						self::OPENER_PLACEHOLDER,
						'',
						$this->singleNamespace(self::OPENER_PLACEHOLDER . $namespaceBlock)
					);
					break;
				default:
					$return .= $text;
			}
		}

		return $return;
	}
};
final class ConstructorPass extends FormatterPass {
	const TYPE_CAMEL_CASE = 'camel';
	const TYPE_SNAKE_CASE = 'snake';
	const TYPE_GOLANG = 'golang';

	public function __construct($type = self::TYPE_CAMEL_CASE) {
		if (self::TYPE_CAMEL_CASE == $type || self::TYPE_SNAKE_CASE == $type || self::TYPE_GOLANG == $type) {
			$this->type = $type;
		} else {
			$this->type = self::TYPE_CAMEL_CASE;
		}
	}

	public function candidate($source, $foundTokens) {
		if (isset($foundTokens[T_CLASS])) {
			return true;
		}
		return false;
	}

	public function format($source) {
		$this->tkns = token_get_all($source);
		$this->code = '';
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->getToken($token);
			$this->ptr = $index;
			switch ($id) {
				case T_CLASS:
					$attributes = [];
					$functionList = [];
					$touchedVisibility = false;
					$touchedFunction = false;
					$curlyCount = null;
					$this->appendCode($text);
					while (list($index, $token) = each($this->tkns)) {
						list($id, $text) = $this->getToken($token);
						$this->ptr = $index;
						if (ST_CURLY_OPEN == $id) {
							++$curlyCount;
						}
						if (ST_CURLY_CLOSE == $id) {
							--$curlyCount;
						}
						if (0 === $curlyCount) {
							break;
						}
						$this->appendCode($text);
						if (T_PUBLIC == $id) {
							$touchedVisibility = T_PUBLIC;
						} elseif (T_PRIVATE == $id) {
							$touchedVisibility = T_PRIVATE;
						} elseif (T_PROTECTED == $id) {
							$touchedVisibility = T_PROTECTED;
						}
						if (
							T_VARIABLE == $id &&
							(
								T_PUBLIC == $touchedVisibility ||
								T_PRIVATE == $touchedVisibility ||
								T_PROTECTED == $touchedVisibility
							)
						) {
							$attributes[] = $text;
							$touchedVisibility = null;
						} elseif (T_FUNCTION == $id) {
							$touchedFunction = true;
						} elseif ($touchedFunction && T_STRING == $id) {
							$functionList[] = $text;
							$touchedVisibility = null;
							$touchedFunction = false;
						}
					}
					$functionList = array_combine($functionList, $functionList);
					if (!isset($functionList['__construct'])) {
						$this->appendCode('function __construct(' . implode(', ', $attributes) . '){' . $this->newLine);
						foreach ($attributes as $var) {
							$this->appendCode($this->generate($var));
						}
						$this->appendCode('}' . $this->newLine);
					}

					$this->appendCode($text);
					break;
				default:
					$this->appendCode($text);
					break;
			}
		}
		return $this->code;
	}

	private function generate($var) {
		switch ($this->type) {
			case self::TYPE_SNAKE_CASE:
				$ret = $this->generateSnakeCase($var);
				break;
			case self::TYPE_GOLANG:
				$ret = $this->generateGolang($var);
				break;
			case self::TYPE_CAMEL_CASE:
			default:
				$ret = $this->generateCamelCase($var);
				break;
		}
		return $ret;
	}
	private function generateCamelCase($var) {
		$str = '$this->set' . ucfirst(str_replace('$', '', $var)) . '(' . $var . ');' . $this->newLine;
		return $str;
	}
	private function generateSnakeCase($var) {
		$str = '$this->set_' . (str_replace('$', '', $var)) . '(' . $var . ');' . $this->newLine;
		return $str;
	}
	private function generateGolang($var) {
		$str = '$this->Set' . ucfirst(str_replace('$', '', $var)) . '(' . $var . ');' . $this->newLine;
		return $str;
	}
};
final class EliminateDuplicatedEmptyLines extends FormatterPass {
	const EMPTY_LINE = "\x2 EMPTYLINE \x3";

	public function candidate($source, $foundTokens) {
		return true;
	}

	public function format($source) {
		$this->tkns = token_get_all($source);
		$this->code = '';
		$parenCount = 0;
		$bracketCount = 0;
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->getToken($token);
			$this->ptr = $index;
			switch ($id) {
				case T_WHITESPACE:
					$text = str_replace($this->newLine, self::EMPTY_LINE . $this->newLine, $text);
					$this->appendCode($text);
					break;
				default:
					$this->appendCode($text);
					break;
			}
		}

		$lines = explode($this->newLine, $this->code);
		$emptyLines = [];
		$blockCount = 0;

		foreach ($lines as $idx => $line) {
			if (trim($line) === self::EMPTY_LINE) {
				$emptyLines[$blockCount][] = $idx;
			} else {
				++$blockCount;
				$emptyLines[$blockCount] = [];
			}
		}

		foreach ($emptyLines as $group) {
			array_pop($group);
			foreach ($group as $lineNumber) {
				unset($lines[$lineNumber]);
			}
		}

		$this->code = str_replace(self::EMPTY_LINE, '', implode($this->newLine, $lines));

		list($id, $text) = $this->getToken(array_pop($this->tkns));
		if (T_WHITESPACE === $id && '' === trim($text)) {
			$this->code = rtrim($this->code) . $this->newLine;
		}

		return $this->code;
	}
};
final class ExtraCommaInArray extends FormatterPass {
	const ST_SHORT_ARRAY_OPEN = 'SHORT_ARRAY_OPEN';
	const EMPTY_ARRAY = 'ST_EMPTY_ARRAY';

	public function candidate($source, $foundTokens) {
		return true;
	}

	public function format($source) {
		$this->tkns = token_get_all($source);

		$contextStack = [];
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->getToken($token);
			$this->ptr = $index;
			switch ($id) {
				case ST_BRACKET_OPEN:
					if (!$this->leftTokenIs([ST_BRACKET_CLOSE, T_STRING, T_VARIABLE, T_ARRAY_CAST])) {
						$contextStack[] = self::ST_SHORT_ARRAY_OPEN;
					} else {
						$contextStack[] = ST_BRACKET_OPEN;
					}
					break;
				case ST_BRACKET_CLOSE:
					if (isset($contextStack[0]) && !$this->leftTokenIs(ST_BRACKET_OPEN)) {
						if (self::ST_SHORT_ARRAY_OPEN == end($contextStack) && ($this->hasLnLeftToken() || $this->hasLnBefore()) && !$this->leftUsefulTokenIs(ST_COMMA)) {
							$prevTokenIdx = $this->leftUsefulTokenIdx();
							list($tknId, $tknText) = $this->getToken($this->tkns[$prevTokenIdx]);
							if (T_END_HEREDOC != $tknId && ST_BRACKET_OPEN != $tknId) {
								$this->tkns[$prevTokenIdx] = [$tknId, $tknText . ','];
							}
						} elseif (self::ST_SHORT_ARRAY_OPEN == end($contextStack) && !($this->hasLnLeftToken() || $this->hasLnBefore()) && $this->leftUsefulTokenIs(ST_COMMA)) {
							$prevTokenIdx = $this->leftUsefulTokenIdx();
							list($tknId, $tknText) = $this->getToken($this->tkns[$prevTokenIdx]);
							$this->tkns[$prevTokenIdx] = [$tknId, rtrim($tknText, ',')];
						}
						array_pop($contextStack);
					}
					break;
				case T_STRING:
					if ($this->rightTokenIs(ST_PARENTHESES_OPEN)) {
						$contextStack[] = T_STRING;
					}
					break;
				case T_ARRAY:
					if ($this->rightTokenIs(ST_PARENTHESES_OPEN)) {
						$contextStack[] = T_ARRAY;
					}
					break;
				case ST_PARENTHESES_OPEN:
					if (isset($contextStack[0]) && T_ARRAY == end($contextStack) && $this->rightTokenIs(ST_PARENTHESES_CLOSE)) {
						array_pop($contextStack);
						$contextStack[] = self::EMPTY_ARRAY;
					} elseif (!$this->leftTokenIs([T_ARRAY, T_STRING])) {
						$contextStack[] = ST_PARENTHESES_OPEN;
					}
					break;
				case ST_PARENTHESES_CLOSE:
					if (isset($contextStack[0])) {
						if (T_ARRAY == end($contextStack) && ($this->hasLnLeftToken() || $this->hasLnBefore()) && !$this->leftUsefulTokenIs(ST_COMMA)) {
							$prevTokenIdx = $this->leftUsefulTokenIdx();
							list($tknId, $tknText) = $this->getToken($this->tkns[$prevTokenIdx]);
							if (T_END_HEREDOC != $tknId && ST_PARENTHESES_OPEN != $tknId) {
								$this->tkns[$prevTokenIdx] = [$tknId, $tknText . ','];
							}
						} elseif (T_ARRAY == end($contextStack) && !($this->hasLnLeftToken() || $this->hasLnBefore()) && $this->leftUsefulTokenIs(ST_COMMA)) {
							$prevTokenIdx = $this->leftUsefulTokenIdx();
							list($tknId, $tknText) = $this->getToken($this->tkns[$prevTokenIdx]);
							$this->tkns[$prevTokenIdx] = [$tknId, rtrim($tknText, ',')];
						}
						array_pop($contextStack);
					}
					break;
			}
			$this->tkns[$this->ptr] = [$id, $text];
		}
		return $this->renderLight();
	}
};
final class LeftAlignComment extends FormatterPass {
	const NON_INDENTABLE_COMMENT = "/*\x2 COMMENT \x3*/";
	public function candidate($source, $foundTokens) {
		return true;
	}
	public function format($source) {
		$this->tkns = token_get_all($source);
		$this->code = '';
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->getToken($token);
			$this->ptr = $index;
			if (self::NON_INDENTABLE_COMMENT === $text) {
				continue;
			}
			switch ($id) {
				case T_COMMENT:
				case T_DOC_COMMENT:
					list(, $prevText) = $this->inspectToken(-1);
					if (self::NON_INDENTABLE_COMMENT === $prevText) {
						// Benchmark me
						// $new_text = '';
						// $tok = strtok($text, $this->new_line);
						// while (false !== $tok) {
						// 	$v = ltrim($tok);
						// 	if ('*' === substr($v, 0, 1)) {
						// 		$v = ' ' . $v;
						// 	}
						// 	$new_text .= $v;
						// 	if (substr($v, -2, 2) != '*/') {
						// 		$new_text .= $this->new_line;
						// 	}
						// 	$tok = strtok($this->new_line);
						// }
						// $this->append_code($new_text);
						$lines = explode($this->newLine, $text);
						$lines = array_map(function ($v) {
							$v = ltrim($v);
							if ('*' === substr($v, 0, 1)) {
								$v = ' ' . $v;
							}
							return $v;
						}, $lines);
						$this->appendCode(implode($this->newLine, $lines));
						break;
					}
				case T_WHITESPACE:
					list(, $nextText) = $this->inspectToken(1);
					if (self::NON_INDENTABLE_COMMENT === $nextText && substr_count($text, "\n") >= 2) {
						$text = substr($text, 0, strrpos($text, "\n") + 1);
						$this->appendCode($text);
						break;
					} elseif (self::NON_INDENTABLE_COMMENT === $nextText && substr_count($text, "\n") === 1) {
						$text = substr($text, 0, strrpos($text, "\n") + 1);
						$this->appendCode($text);
						break;
					}
				default:
					$this->appendCode($text);
					break;
			}
		}
		return $this->code;
	}
}
;
final class MergeCurlyCloseAndDoWhile extends FormatterPass {
	public function candidate($source, $foundTokens) {
		if (isset($foundTokens[T_WHILE])) {
			return true;
		}

		return false;
	}
	public function format($source) {
		$this->tkns = token_get_all($source);
		$this->code = '';
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->getToken($token);
			$this->ptr = $index;
			switch ($id) {
				case T_WHILE:
					$str = $text;
					list($pt_id, $pt_text) = $this->getToken($this->leftToken());
					while (list($index, $token) = each($this->tkns)) {
						list($id, $text) = $this->getToken($token);
						$this->ptr = $index;
						$str .= $text;
						if (
							ST_CURLY_OPEN == $id ||
							ST_COLON == $id ||
							(ST_SEMI_COLON == $id && (ST_SEMI_COLON == $pt_id || ST_CURLY_OPEN == $pt_id || T_COMMENT == $pt_id || T_DOC_COMMENT == $pt_id))
						) {
							$this->appendCode($str);
							break;
						} elseif (ST_SEMI_COLON == $id && !(ST_SEMI_COLON == $pt_id || ST_CURLY_OPEN == $pt_id || T_COMMENT == $pt_id || T_DOC_COMMENT == $pt_id)) {
							$this->rtrimAndAppendCode($str);
							break;
						}
					}
					break;
				default:
					$this->appendCode($text);
					break;
			}
		}
		return $this->code;
	}
}
;
final class MergeDoubleArrowAndArray extends FormatterPass {
	public function candidate($source, $foundTokens) {
		if (isset($foundTokens[T_ARRAY])) {
			return true;
		}

		return false;
	}
	public function format($source) {
		$this->tkns = token_get_all($source);
		$this->code = '';
		$inDoWhileContext = 0;
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->getToken($token);
			$this->ptr = $index;
			switch ($id) {
				case T_ARRAY:
					if ($this->leftTokenIs([T_DOUBLE_ARROW])) {
						--$inDoWhileContext;
						$this->rtrimAndAppendCode($text);
						break;
					}
				default:
					$this->appendCode($text);
					break;
			}
		}
		return $this->code;
	}
};
final class MergeParenCloseWithCurlyOpen extends FormatterPass {
	public function candidate($source, $foundTokens) {
		if (isset($foundTokens[ST_CURLY_OPEN]) || isset($foundTokens[T_ELSE]) || isset($foundTokens[T_ELSEIF])) {
			return true;
		}

		return false;
	}

	public function format($source) {
		$this->tkns = token_get_all($source);
		$this->code = '';

		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->getToken($token);
			$this->ptr = $index;
			switch ($id) {
				case ST_CURLY_OPEN:
					if ($this->leftTokenIs([T_ELSE, T_STRING, ST_PARENTHESES_CLOSE])) {
						$this->rtrimAndAppendCode($text);
					} else {
						$this->appendCode($text);
					}
					break;
				case T_ELSE:
				case T_ELSEIF:
					if ($this->leftTokenIs(ST_CURLY_CLOSE)) {
						$this->rtrimAndAppendCode($text);
					} else {
						$this->appendCode($text);
					}
					break;
				default:
					$this->appendCode($text);
					break;
			}
		}
		return $this->code;
	}
}
;
final class NormalizeIsNotEquals extends FormatterPass {
	public function candidate($source, $foundTokens) {
		if (isset($foundTokens[T_IS_NOT_EQUAL])) {
			return true;
		}

		return false;
	}
	public function format($source) {
		$this->tkns = token_get_all($source);
		$this->code = '';

		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->getToken($token);
			$this->ptr = $index;
			switch ($id) {
				case T_IS_NOT_EQUAL:
					$this->appendCode(str_replace('<>', '!=', $text) . $this->getSpace());
					break;
				default:
					$this->appendCode($text);
					break;
			}
		}

		return $this->code;
	}
}
;
final class NormalizeLnAndLtrimLines extends FormatterPass {
	public function candidate($source, $foundTokens) {
		return true;
	}
	public function format($source) {
		$source = str_replace(["\r\n", "\n\r", "\r", "\n"], $this->newLine, $source);
		$source = preg_replace('/\h+$/mu', '', $source);

		$this->tkns = token_get_all($source);
		$this->code = '';
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->getToken($token);
			$this->ptr = $index;
			switch ($id) {
				case ST_QUOTE:
					$this->appendCode($text);
					$this->printUntilTheEndOfString();
					break;
				case T_START_HEREDOC:
					$this->appendCode($text);
					$this->printUntil(T_END_HEREDOC);
					break;
				case T_COMMENT:
				case T_DOC_COMMENT:
					list($prevId, $prevText) = $this->inspectToken(-1);

					if (T_WHITESPACE === $prevId && ("\n" === $prevText || "\n\n" == substr($prevText, -2, 2))) {
						$this->appendCode(LeftAlignComment::NON_INDENTABLE_COMMENT);
					}

					$lines = explode($this->newLine, $text);
					$newText = '';
					foreach ($lines as $v) {
						$v = ltrim($v);
						if ('*' === substr($v, 0, 1)) {
							$v = ' ' . $v;
						}
						$newText .= $this->newLine . $v;
					}

					$this->appendCode(ltrim($newText));
					break;
				case T_CONSTANT_ENCAPSED_STRING:
					$this->appendCode($text);
					break;
				default:
					if ($this->hasLn($text)) {
						$trailingNewLine = $this->substrCountTrailing($text, $this->newLine);
						if ($trailingNewLine > 0) {
							$text = trim($text) . str_repeat($this->newLine, $trailingNewLine);
						}
					}
					$this->appendCode($text);
					break;
			}
		}

		return $this->code;
	}
}
;
final class OrderUseClauses extends FormatterPass {
	const OPENER_PLACEHOLDER = "<?php /*\x2 ORDERBY \x3*/";
	public function candidate($source, $foundTokens) {
		if (isset($foundTokens[T_USE])) {
			return true;
		}

		return false;
	}
	private function singleNamespace($source) {
		$tokens = token_get_all($source);
		$useStack = [];
		$newTokens = [];
		$nextTokens = [];
		$touchedTUse = false;
		while (list(, $popToken) = each($tokens)) {
			$nextTokens[] = $popToken;
			while (($token = array_shift($nextTokens))) {
				list($id, $text) = $this->getToken($token);
				if (T_USE === $id) {
					$touchedTUse = true;
					$useItem = $text;
					while (list(, $token) = each($tokens)) {
						list($id, $text) = $this->getToken($token);
						if (ST_SEMI_COLON === $id) {
							$useItem .= $text;
							break;
						} elseif (ST_COMMA === $id) {
							$useItem .= ST_SEMI_COLON;
							$nextTokens[] = [T_WHITESPACE, $this->newLine];
							$nextTokens[] = [T_USE, 'use'];
							break;
						} else {
							$useItem .= $text;
						}
					}
					$useStack[] = trim($useItem);
					$token = new SurrogateToken();
				}
				if ($touchedTUse &&
					T_FINAL === $id ||
					T_ABSTRACT === $id ||
					T_INTERFACE === $id ||
					T_CLASS === $id ||
					T_FUNCTION === $id ||
					T_TRAIT === $id ||
					T_VARIABLE === $id ||
					T_WHILE === $id ||
					T_FOR === $id ||
					T_FOREACH === $id ||
					T_IF === $id
				) {
					if (sizeof($useStack) > 0) {
						$newTokens[] = $this->newLine;
						$newTokens[] = $this->newLine;
					}
					$newTokens[] = $token;
					break 2;
				} elseif ($touchedTUse && (T_DOC_COMMENT === $id || T_COMMENT === $id)) {
					if (sizeof($useStack) > 0) {
						$newTokens[] = $this->newLine;
					}
					$newTokens[] = $token;
					break 2;
				}
				$newTokens[] = $token;
			}
		}
		if (empty($useStack)) {
			return $source;
		}
		natcasesort($useStack);
		$aliasList = [];
		$aliasCount = [];
		foreach ($useStack as $use) {
			if (false !== stripos($use, ' as ')) {
				$alias = substr(strstr($use, ' as '), strlen(' as '), -1);
			} else {
				$alias = basename(str_replace('\\', '/', trim(substr($use, strlen('use'), -1))));
			}
			$alias = str_replace(ST_SEMI_COLON, '', strtolower($alias));
			$aliasList[$alias] = trim(strtolower($use));
			$aliasCount[$alias] = 0;
		}

		$return = '';
		foreach ($newTokens as $idx => $token) {
			if ($token instanceof SurrogateToken) {
				$return .= array_shift($useStack);
			} elseif (T_WHITESPACE == $token[0] && isset($newTokens[$idx - 1], $newTokens[$idx + 1]) && $newTokens[$idx - 1] instanceof SurrogateToken && $newTokens[$idx + 1] instanceof SurrogateToken) {
				$return .= $this->newLine;
				continue;
			} else {
				list($id, $text) = $this->getToken($token);
				$lower_text = strtolower($text);
				if (T_STRING === $id && isset($aliasList[$lower_text])) {
					++$aliasCount[$lower_text];
				} elseif (T_DOC_COMMENT === $id) {
					foreach ($aliasList as $alias => $use) {
						if (false !== stripos($text, $alias)) {
							++$aliasCount[$alias];
						}
					}
				}
				$return .= $text;
			}
		}

		while (list(, $token) = each($tokens)) {
			list($id, $text) = $this->getToken($token);
			$lower_text = strtolower($text);
			if (T_STRING === $id && isset($aliasList[$lower_text])) {
				++$aliasCount[$lower_text];
			} elseif (T_DOC_COMMENT === $id) {
				foreach ($aliasList as $alias => $use) {
					if (false !== stripos($text, $alias)) {
						++$aliasCount[$alias];
					}
				}
			}
			$return .= $text;
		}

		$unusedImport = array_keys(
			array_filter(
				$aliasCount, function ($v) {
					return 0 === $v;
				}
			)
		);
		foreach ($unusedImport as $v) {
			$return = str_ireplace($aliasList[$v] . $this->newLine, null, $return);
		}

		return $return;
	}
	public function format($source = '') {
		$namespaceCount = 0;
		$tokens = token_get_all($source);
		$touchedTUse = false;
		while (list(, $token) = each($tokens)) {
			list($id, $text) = $this->getToken($token);
			if (T_USE === $id) {
				$touchedTUse = true;
			}
			if (T_NAMESPACE == $id) {
				++$namespaceCount;
			}
		}
		if ($namespaceCount <= 1 && $touchedTUse) {
			return $this->singleNamespace($source);
		}

		$return = '';
		reset($tokens);
		while (list($index, $token) = each($tokens)) {
			list($id, $text) = $this->getToken($token);
			$this->ptr = $index;
			switch ($id) {
				case T_NAMESPACE:
					$return .= $text;
					$touchedTUse = false;
					while (list($index, $token) = each($tokens)) {
						list($id, $text) = $this->getToken($token);
						$this->ptr = $index;
						$return .= $text;
						if (ST_CURLY_OPEN == $id || ST_SEMI_COLON == $id) {
							break;
						}
					}
					if (ST_CURLY_OPEN === $id) {
						$namespaceBlock = '';
						$curlyCount = 1;
						while (list($index, $token) = each($tokens)) {
							list($id, $text) = $this->getToken($token);
							$this->ptr = $index;
							$namespaceBlock .= $text;

							if (T_USE === $id) {
								$touchedTUse = true;
							}

							if (ST_CURLY_OPEN == $id) {
								++$curlyCount;
							} elseif (ST_CURLY_CLOSE == $id) {
								--$curlyCount;
							}

							if (0 == $curlyCount) {
								break;
							}
						}
					} elseif (ST_SEMI_COLON === $id) {
						$namespaceBlock = '';
						while (list($index, $token) = each($tokens)) {
							list($id, $text) = $this->getToken($token);
							$this->ptr = $index;

							if (T_USE === $id) {
								$touchedTUse = true;
							}

							if (T_NAMESPACE == $id) {
								prev($tokens);
								break;
							}

							$namespaceBlock .= $text;
						}
					}

					$return .= str_replace(
						self::OPENER_PLACEHOLDER,
						'',
						$this->singleNamespace(self::OPENER_PLACEHOLDER . $namespaceBlock)
					);

					break;
				default:
					$return .= $text;
			}
		}

		return $return;
	}

}
;
final class Reindent extends FormatterPass {
	public function candidate($source, $foundTokens) {
		return true;
	}
	public function format($source) {
		$this->tkns = token_get_all($source);
		$this->code = '';
		$this->useCache = true;
		$foundStack = [];
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->getToken($token);
			$this->ptr = $index;
			$this->cache = [];

			if (
				(
					T_WHITESPACE === $id ||
					(T_COMMENT === $id && '//' == substr($text, 0, 2))
				) && $this->hasLn($text)
			) {
				$bottomFoundStack = end($foundStack);
				if (isset($bottomFoundStack['implicit']) && $bottomFoundStack['implicit']) {
					$idx = sizeof($foundStack) - 1;
					$foundStack[$idx]['implicit'] = false;
					$this->setIndent(+1);
				}
			}
			switch ($id) {
				case ST_QUOTE:
					$this->appendCode($text);
					$this->printUntilTheEndOfString();
					break;
				case T_CLOSE_TAG:
					$this->appendCode($text);
					$this->printUntil(T_OPEN_TAG);
					break;
				case T_START_HEREDOC:
					$this->appendCode(rtrim($text) . $this->getCrlf());
					break;
				case T_CONSTANT_ENCAPSED_STRING:
				case T_ENCAPSED_AND_WHITESPACE:
				case T_STRING_VARNAME:
				case T_NUM_STRING:
					$this->appendCode($text);
					break;
				case T_DOLLAR_OPEN_CURLY_BRACES:
				case T_CURLY_OPEN:
				case ST_CURLY_OPEN:
				case ST_PARENTHESES_OPEN:
				case ST_BRACKET_OPEN:
					$indentToken = [
						'id' => $id,
						'implicit' => true,
					];
					$this->appendCode($text);
					if ($this->hasLnAfter()) {
						$indentToken['implicit'] = false;
						$this->setIndent(+1);
					}
					$foundStack[] = $indentToken;
					break;
				case ST_CURLY_CLOSE:
				case ST_PARENTHESES_CLOSE:
				case ST_BRACKET_CLOSE:
					$poppedID = array_pop($foundStack);
					if (false === $poppedID['implicit']) {
						$this->setIndent(-1);
					}
					$this->appendCode($text);
					break;

				case T_DOC_COMMENT:
					$text = str_replace($this->newLine, $this->newLine . $this->getIndent(), $text);
					$this->appendCode($text);
					break;
				default:
					$hasLn = ($this->hasLn($text));
					if ($hasLn) {
						$isNextCurlyParenBracketClose = $this->rightTokenIs([ST_CURLY_CLOSE, ST_PARENTHESES_CLOSE, ST_BRACKET_CLOSE]);
						if (!$isNextCurlyParenBracketClose) {
							$text = str_replace($this->newLine, $this->newLine . $this->getIndent(), $text);
						} elseif ($isNextCurlyParenBracketClose) {
							$this->setIndent(-1);
							$text = str_replace($this->newLine, $this->newLine . $this->getIndent(), $text);
							$this->setIndent(+1);
						}
					}
					$this->appendCode($text);
					break;
			}
		}
		return $this->code;
	}

}
;
final class ReindentColonBlocks extends FormatterPass {
	public function candidate($source, $foundTokens) {
		return true;
	}
	public function format($source) {
		$this->tkns = token_get_all($source);
		$this->useCache = true;
		$this->code = '';

		$foundColon = false;
		foreach ($this->tkns as $token) {
			list($id, $text) = $this->getToken($token);
			if (T_DEFAULT == $id || T_CASE == $id || T_SWITCH == $id) {
				$foundColon = true;
				break;
			}
			$this->appendCode($text);
		}
		if (!$foundColon) {
			return $source;
		}

		prev($this->tkns);
		$switchLevel = 0;
		$switchCurlyCount = [];
		$switchCurlyCount[$switchLevel] = 0;
		$isNextCaseOrDefault = false;
		$touchedColon = false;
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->getToken($token);
			$this->ptr = $index;
			$this->cache = [];
			switch ($id) {
				case ST_QUOTE:
					$this->appendCode($text);
					$this->printUntilTheEndOfString();
					break;

				case T_SWITCH:
					++$switchLevel;
					$switchCurlyCount[$switchLevel] = 0;
					$touchedColon = false;
					$this->appendCode($text);
					break;

				case ST_CURLY_OPEN:
					$this->appendCode($text);
					if ($this->leftTokenIs([T_VARIABLE, T_OBJECT_OPERATOR, ST_DOLLAR])) {
						$this->printCurlyBlock();
						break;
					}
					++$switchCurlyCount[$switchLevel];
					break;

				case ST_CURLY_CLOSE:
					--$switchCurlyCount[$switchLevel];
					if (0 === $switchCurlyCount[$switchLevel] && $switchLevel > 0) {
						--$switchLevel;
					}
					$this->appendCode($this->getIndent($switchLevel) . $text);
					break;

				case T_DEFAULT:
				case T_CASE:
					$touchedColon = false;
					$this->appendCode($text);
					break;

				case ST_COLON:
					$touchedColon = true;
					$this->appendCode($text);
					break;

				default:
					$hasLn = $this->hasLn($text);
					if ($hasLn) {
						$isNextCaseOrDefault = $this->rightUsefulTokenIs([T_CASE, T_DEFAULT]);
						if ($touchedColon && T_COMMENT == $id && $isNextCaseOrDefault) {
							$this->appendCode($text);
						} elseif ($touchedColon && T_COMMENT == $id && !$isNextCaseOrDefault) {
							$this->appendCode($this->getIndent($switchLevel) . $text);
							if (!$this->rightTokenIs([ST_CURLY_CLOSE, T_COMMENT, T_DOC_COMMENT])) {
								$this->appendCode($this->getIndent($switchLevel));
							}
						} elseif (!$isNextCaseOrDefault && !$this->rightTokenIs([ST_CURLY_CLOSE, T_COMMENT, T_DOC_COMMENT])) {
							$this->appendCode($text . $this->getIndent($switchLevel));
						} else {
							$this->appendCode($text);
						}
					} else {
						$this->appendCode($text);
					}
					break;
			}
		}
		return $this->code;
	}
};
final class ReindentIfColonBlocks extends FormatterPass {
	public function candidate($source, $foundTokens) {
		return true;
	}
	public function format($source) {
		$this->tkns = token_get_all($source);
		$foundColon = false;
		foreach ($this->tkns as $token) {
			list($id, $text) = $this->getToken($token);
			if (ST_COLON == trim($text)) {
				$foundColon = true;
				break;
			}
		}
		if (!$foundColon) {
			return $source;
		}
		reset($this->tkns);
		$this->code = '';

		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->getToken($token);
			$this->ptr = $index;
			switch ($id) {
				case T_ENDIF:
					$this->setIndent(-1);
					$this->appendCode($text);
					break;
				case T_ELSE:
				case T_ELSEIF:
					$this->setIndent(-1);
				case T_IF:
					$this->appendCode($text);
					while (list($index, $token) = each($this->tkns)) {
						list($id, $text) = $this->getToken($token);
						$this->ptr = $index;
						$this->appendCode($text);
						if (ST_PARENTHESES_OPEN === $id) {
							$parenCount = 1;
							while (list($index, $token) = each($this->tkns)) {
								list($id, $text) = $this->getToken($token);
								$this->ptr = $index;
								$this->appendCode($text);
								if (ST_PARENTHESES_OPEN === $id) {
									++$parenCount;
								}
								if (ST_PARENTHESES_CLOSE === $id) {
									--$parenCount;
								}
								if (0 == $parenCount) {
									break;
								}
							}
						} elseif (ST_CURLY_OPEN === $id) {
							break;
						} elseif (ST_COLON === $id && !$this->rightTokenIs([T_CLOSE_TAG])) {
							$this->setIndent(+1);
							break;
						} elseif (ST_COLON === $id) {
							break;
						}
					}
					break;
				default:
					$hasLn = $this->hasLn($text);
					if ($hasLn && !$this->rightTokenIs([T_ENDIF, T_ELSE, T_ELSEIF])) {
						$text = str_replace($this->newLine, $this->newLine . $this->getIndent(), $text);
					} elseif ($hasLn && $this->rightTokenIs([T_ENDIF, T_ELSE, T_ELSEIF])) {
						$this->setIndent(-1);
						$text = str_replace($this->newLine, $this->newLine . $this->getIndent(), $text);
						$this->setIndent(+1);
					}
					$this->appendCode($text);
					break;
			}
		}
		return $this->code;
	}
};
final class ReindentLoopColonBlocks extends FormatterPass {
	public function candidate($source, $foundTokens) {
		return true;
	}

	public function format($source) {
		$tkns = token_get_all($source);
		$foundEndwhile = false;
		$foundEndforeach = false;
		$foundEndfor = false;
		foreach ($tkns as $token) {
			list($id, $text) = $this->getToken($token);
			if (!$foundEndwhile && T_ENDWHILE == $id) {
				$source = $this->formatWhileBlocks($source);
				$foundEndwhile = true;
			} elseif (!$foundEndforeach && T_ENDFOREACH == $id) {
				$source = $this->formatForeachBlocks($source);
				$foundEndforeach = true;
			} elseif (!$foundEndfor && T_ENDFOR == $id) {
				$source = $this->formatForBlocks($source);
				$foundEndfor = true;
			} elseif ($foundEndwhile && $foundEndforeach && $foundEndfor) {
				break;
			}
		}
		return $source;
	}

	private function formatBlocks($source, $open_token, $close_token) {
		$this->tkns = token_get_all($source);
		$this->code = '';

		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->getToken($token);
			$this->ptr = $index;
			switch ($id) {
				case $close_token:
					$this->setIndent(-1);
					$this->appendCode($text);
					break;
				case $open_token:
					$this->appendCode($text);
					while (list($index, $token) = each($this->tkns)) {
						list($id, $text) = $this->getToken($token);
						$this->ptr = $index;
						$this->appendCode($text);
						if (ST_CURLY_OPEN === $id) {
							break;
						} elseif (ST_COLON === $id && !$this->rightTokenIs([T_CLOSE_TAG])) {
							$this->setIndent(+1);
							break;
						} elseif (ST_COLON === $id) {
							break;
						}
					}
					break;
				default:
					if ($this->hasLn($text) && !$this->rightTokenIs([$close_token])) {
						$text = str_replace($this->newLine, $this->newLine . $this->getIndent(), $text);
					} elseif ($this->hasLn($text) && $this->rightTokenIs([$close_token])) {
						$this->setIndent(-1);
						$text = str_replace($this->newLine, $this->newLine . $this->getIndent(), $text);
						$this->setIndent(+1);
					}
					$this->appendCode($text);
					break;
			}
		}
		return $this->code;
	}
	private function formatForBlocks($source) {
		return $this->formatBlocks($source, T_FOR, T_ENDFOR);
	}
	private function formatForeachBlocks($source) {
		return $this->formatBlocks($source, T_FOREACH, T_ENDFOREACH);
	}
	private function formatWhileBlocks($source) {
		$this->tkns = token_get_all($source);
		$this->code = '';

		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->getToken($token);
			$this->ptr = $index;
			switch ($id) {
				case T_ENDWHILE:
					$this->setIndent(-1);
					$this->appendCode($text);
					break;
				case T_WHILE:
					$this->appendCode($text);
					while (list($index, $token) = each($this->tkns)) {
						list($id, $text) = $this->getToken($token);
						$this->ptr = $index;
						$this->appendCode($text);
						if (ST_CURLY_OPEN === $id) {
							break;
						} elseif (ST_SEMI_COLON === $id) {
							break;
						} elseif (ST_COLON === $id) {
							$this->setIndent(+1);
							break;
						}
					}
					break;
				default:
					if ($this->hasLn($text) && !$this->rightTokenIs([T_ENDWHILE])) {
						$text = str_replace($this->newLine, $this->newLine . $this->getIndent(), $text);
					} elseif ($this->hasLn($text) && $this->rightTokenIs([T_ENDWHILE])) {
						$this->setIndent(-1);
						$text = str_replace($this->newLine, $this->newLine . $this->getIndent(), $text);
						$this->setIndent(+1);
					}
					$this->appendCode($text);
					break;
			}
		}
		return $this->code;
	}
};
final class ReindentObjOps extends FormatterPass {
	const ALIGNABLE_OBJOP = "\x2 OBJOP%d.%d.%d \x3";

	const ALIGN_WITH_INDENT = 1;
	const ALIGN_WITH_SPACES = 2;

	public function candidate($source, $foundTokens) {
		return true;
	}

	public function format($source) {
		$this->tkns = token_get_all($source);
		$this->code = '';

		$levelCounter = 0;
		$levelEntranceCounter = [];
		$contextCounter = [];
		$touchCounter = [];
		$alignType = [];
		$printedPlaceholder = [];
		$maxContextCounter = [];

		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->getToken($token);
			$this->ptr = $index;
			switch ($id) {
				case T_WHILE:
				case T_IF:
				case T_FOR:
				case T_FOREACH:
				case T_SWITCH:
					$this->appendCode($text);
					$this->printUntil(ST_PARENTHESES_OPEN);
					$this->printBlock(ST_PARENTHESES_OPEN, ST_PARENTHESES_CLOSE);
					break;

				case T_NEW:
					$this->appendCode($text);
					if ($this->leftUsefulTokenIs(ST_PARENTHESES_OPEN)) {
						$foundToken = $this->printUntilAny([ST_PARENTHESES_OPEN, ST_PARENTHESES_CLOSE, ST_COMMA]);
						if (ST_PARENTHESES_OPEN == $foundToken) {
							$this->incrementCounters($levelCounter, $levelEntranceCounter, $contextCounter, $maxContextCounter, $touchCounter, $alignType, $printedPlaceholder);
							$this->printBlock(ST_PARENTHESES_OPEN, ST_PARENTHESES_CLOSE);
							$this->printUntilAny([ST_PARENTHESES_CLOSE, ST_COMMA]);
						}
					}
					break;

				case T_FUNCTION:
					$this->appendCode($text);
					if (!$this->rightUsefulTokenIs(T_STRING)) {
						// $this->increment_counters($level_counter, $level_entrance_counter, $context_counter, $max_context_counter, $touch_counter, $align_type, $printed_placeholder);
						$this->printUntil(ST_PARENTHESES_OPEN);
						$this->printBlock(ST_PARENTHESES_OPEN, ST_PARENTHESES_CLOSE);
						$this->printUntil(ST_CURLY_OPEN);
						$this->printCurlyBlock();
					}
					break;

				case T_VARIABLE:
				case T_STRING:
					$this->appendCode($text);
					if (!isset($levelEntranceCounter[$levelCounter])) {
						$levelEntranceCounter[$levelCounter] = 0;
					}
					if (!isset($contextCounter[$levelCounter][$levelEntranceCounter[$levelCounter]])) {
						$contextCounter[$levelCounter][$levelEntranceCounter[$levelCounter]] = 0;
						$maxContextCounter[$levelCounter][$levelEntranceCounter[$levelCounter]] = 0;
						$touchCounter[$levelCounter][$levelEntranceCounter[$levelCounter]] = 0;
						$alignType[$levelCounter][$levelEntranceCounter[$levelCounter]] = 0;
						$printedPlaceholder[$levelCounter][$levelEntranceCounter[$levelCounter]][$contextCounter[$levelCounter][$levelEntranceCounter[$levelCounter]]] = 0;
					}
					break;

				case ST_PARENTHESES_OPEN:
				case ST_BRACKET_OPEN:
					$this->incrementCounters($levelCounter, $levelEntranceCounter, $contextCounter, $maxContextCounter, $touchCounter, $alignType, $printedPlaceholder);
					$this->appendCode($text);
					break;

				case ST_PARENTHESES_CLOSE:
				case ST_BRACKET_CLOSE:
					--$levelCounter;
					$this->appendCode($text);
					break;

				case T_OBJECT_OPERATOR:
					if (0 == $touchCounter[$levelCounter][$levelEntranceCounter[$levelCounter]]) {
						++$touchCounter[$levelCounter][$levelEntranceCounter[$levelCounter]];
						if ($this->hasLnBefore()) {
							$alignType[$levelCounter][$levelEntranceCounter[$levelCounter]] = self::ALIGN_WITH_INDENT;
							$this->appendCode($this->getIndent(+1) . $text);
							$foundToken = $this->printUntilAny([ST_PARENTHESES_OPEN, ST_SEMI_COLON, $this->newLine]);
							if (ST_SEMI_COLON == $foundToken) {
								$this->incrementCounters($levelCounter, $levelEntranceCounter, $contextCounter, $maxContextCounter, $touchCounter, $alignType, $printedPlaceholder);
							} elseif (ST_PARENTHESES_OPEN == $foundToken) {
								$this->incrementCounters($levelCounter, $levelEntranceCounter, $contextCounter, $maxContextCounter, $touchCounter, $alignType, $printedPlaceholder);
								$this->indent_parentheses_content();
							}
						} else {
							$alignType[$levelCounter][$levelEntranceCounter[$levelCounter]] = self::ALIGN_WITH_SPACES;
							if (!isset($printedPlaceholder[$levelCounter][$levelEntranceCounter[$levelCounter]][$contextCounter[$levelCounter][$levelEntranceCounter[$levelCounter]]])) {
								$printedPlaceholder[$levelCounter][$levelEntranceCounter[$levelCounter]][$contextCounter[$levelCounter][$levelEntranceCounter[$levelCounter]]] = 0;
							}
							++$printedPlaceholder[$levelCounter][$levelEntranceCounter[$levelCounter]][$contextCounter[$levelCounter][$levelEntranceCounter[$levelCounter]]];
							$placeholder = sprintf(
								self::ALIGNABLE_OBJOP,
								$levelCounter,
								$levelEntranceCounter[$levelCounter],
								$contextCounter[$levelCounter][$levelEntranceCounter[$levelCounter]]
							);
							$this->appendCode($placeholder . $text);
							$foundToken = $this->printUntilAny([ST_PARENTHESES_OPEN, ST_SEMI_COLON, $this->newLine]);
							if (ST_SEMI_COLON == $foundToken) {
								$this->incrementCounters($levelCounter, $levelEntranceCounter, $contextCounter, $maxContextCounter, $touchCounter, $alignType, $printedPlaceholder);
							} elseif (ST_PARENTHESES_OPEN == $foundToken) {
								$this->incrementCounters($levelCounter, $levelEntranceCounter, $contextCounter, $maxContextCounter, $touchCounter, $alignType, $printedPlaceholder);
								$this->injectPlaceholderParenthesesContent($placeholder);
							}
						}
					} elseif ($this->hasLnBefore() || $this->hasLnLeftToken()) {
						++$touchCounter[$levelCounter][$levelEntranceCounter[$levelCounter]];
						if (self::ALIGN_WITH_SPACES == $alignType[$levelCounter][$levelEntranceCounter[$levelCounter]]) {
							++$printedPlaceholder[$levelCounter][$levelEntranceCounter[$levelCounter]][$contextCounter[$levelCounter][$levelEntranceCounter[$levelCounter]]];
							$placeholder = sprintf(
								self::ALIGNABLE_OBJOP,
								$levelCounter,
								$levelEntranceCounter[$levelCounter],
								$contextCounter[$levelCounter][$levelEntranceCounter[$levelCounter]]
							);
							$this->appendCode($placeholder . $text);
							$foundToken = $this->printUntilAny([ST_PARENTHESES_OPEN, ST_SEMI_COLON, $this->newLine]);
							if (ST_SEMI_COLON == $foundToken) {
								$this->incrementCounters($levelCounter, $levelEntranceCounter, $contextCounter, $maxContextCounter, $touchCounter, $alignType, $printedPlaceholder);
							} elseif (ST_PARENTHESES_OPEN == $foundToken) {
								$this->incrementCounters($levelCounter, $levelEntranceCounter, $contextCounter, $maxContextCounter, $touchCounter, $alignType, $printedPlaceholder);
								$this->injectPlaceholderParenthesesContent($placeholder);
							}
						} else {
							$this->appendCode($this->getIndent(+1) . $text);
							$foundToken = $this->printUntilAny([ST_PARENTHESES_OPEN, ST_SEMI_COLON, $this->newLine]);
							if (ST_SEMI_COLON == $foundToken) {
								$this->incrementCounters($levelCounter, $levelEntranceCounter, $contextCounter, $maxContextCounter, $touchCounter, $alignType, $printedPlaceholder);
							} elseif (ST_PARENTHESES_OPEN == $foundToken) {
								$this->incrementCounters($levelCounter, $levelEntranceCounter, $contextCounter, $maxContextCounter, $touchCounter, $alignType, $printedPlaceholder);
								$this->indent_parentheses_content();
							}
						}
					} else {
						$this->appendCode($text);
					}
					break;

				case T_COMMENT:
				case T_DOC_COMMENT:
					if (
						isset($alignType[$levelCounter]) &&
						isset($levelEntranceCounter[$levelCounter]) &&
						isset($alignType[$levelCounter][$levelEntranceCounter[$levelCounter]]) &&
						($this->hasLnBefore() || $this->hasLnLeftToken())
					) {
						if (self::ALIGN_WITH_SPACES == $alignType[$levelCounter][$levelEntranceCounter[$levelCounter]]) {
							++$printedPlaceholder[$levelCounter][$levelEntranceCounter[$levelCounter]][$contextCounter[$levelCounter][$levelEntranceCounter[$levelCounter]]];
							$this->appendCode(
								sprintf(
									self::ALIGNABLE_OBJOP,
									$levelCounter,
									$levelEntranceCounter[$levelCounter],
									$contextCounter[$levelCounter][$levelEntranceCounter[$levelCounter]]
								)
							);
						} elseif (self::ALIGN_WITH_INDENT == $alignType[$levelCounter][$levelEntranceCounter[$levelCounter]]) {
							$this->appendCode($this->getIndent(+1));
						}
					}
					$this->appendCode($text);
					break;

				case ST_COMMA:
				case ST_SEMI_COLON:
					if (!isset($levelEntranceCounter[$levelCounter])) {
						$levelEntranceCounter[$levelCounter] = 0;
					}
					++$levelEntranceCounter[$levelCounter];
					$this->appendCode($text);
					break;

				default:
					$this->appendCode($text);
					break;
			}
		}
		$orig_code = $this->code;
		foreach ($maxContextCounter as $level => $entrances) {
			foreach ($entrances as $entrance => $context) {
				for ($j = 0; $j <= $context; ++$j) {
					if (!isset($printedPlaceholder[$level][$entrance][$j])) {
						continue;
					}
					if (0 === $printedPlaceholder[$level][$entrance][$j]) {
						continue;
					}

					$placeholder = sprintf(self::ALIGNABLE_OBJOP, $level, $entrance, $j);
					if (1 === $printedPlaceholder[$level][$entrance][$j]) {
						$this->code = str_replace($placeholder, '', $this->code);
						continue;
					}

					$lines = explode($this->newLine, $this->code);
					$linesWithObjop = [];
					$blockCount = 0;

					foreach ($lines as $idx => $line) {
						if (false !== strpos($line, $placeholder)) {
							$linesWithObjop[] = $idx;
						}
					}

					$farthest = 0;
					foreach ($linesWithObjop as $idx) {
						$farthest = max($farthest, strpos($lines[$idx], $placeholder . '->'));
					}
					foreach ($linesWithObjop as $idx) {
						$line = $lines[$idx];
						$current = strpos($line, $placeholder);
						$delta = abs($farthest - $current);
						if ($delta > 0) {
							$line = str_replace($placeholder, str_repeat(' ', $delta) . $placeholder, $line);
							$lines[$idx] = $line;
						}
					}

					$this->code = str_replace($placeholder, '', implode($this->newLine, $lines));
				}
			}
		}
		return $this->code;
	}

	private function indent_parentheses_content() {
		$count = 0;
		$i = $this->ptr;
		$sizeof_tokens = sizeof($this->tkns);
		for ($i = $this->ptr; $i < $sizeof_tokens; ++$i) {
			$token = &$this->tkns[$i];
			list($id, $text) = $this->getToken($token);
			if (T_WHITESPACE == $id && $this->hasLn($text)) {
				$token[1] = $text . $this->getIndent(+1);
				continue;
			}
			if (ST_PARENTHESES_OPEN == $id) {
				++$count;
			}
			if (ST_PARENTHESES_CLOSE == $id) {
				--$count;
			}
			if (0 == $count) {
				break;
			}
		}
	}

	private function injectPlaceholderParenthesesContent($placeholder) {
		$count = 0;
		$i = $this->ptr;
		$sizeof_tokens = sizeof($this->tkns);
		for ($i = $this->ptr; $i < $sizeof_tokens; ++$i) {
			$token = &$this->tkns[$i];
			list($id, $text) = $this->getToken($token);
			if (T_WHITESPACE == $id && $this->hasLn($text)) {
				$token[1] = str_replace($this->newLine, $this->newLine . $placeholder, $text);
				continue;
			}
			if (ST_PARENTHESES_OPEN == $id) {
				++$count;
			}
			if (ST_PARENTHESES_CLOSE == $id) {
				--$count;
			}
			if (0 == $count) {
				break;
			}
		}
	}

	private function incrementCounters(
		&$levelCounter,
		&$levelEntranceCounter,
		&$contextCounter,
		&$maxContextCounter,
		&$touchCounter,
		&$alignType,
		&$printedPlaceholder
	) {
		++$levelCounter;
		if (!isset($levelEntranceCounter[$levelCounter])) {
			$levelEntranceCounter[$levelCounter] = 0;
		}
		++$levelEntranceCounter[$levelCounter];
		if (!isset($contextCounter[$levelCounter][$levelEntranceCounter[$levelCounter]])) {
			$contextCounter[$levelCounter][$levelEntranceCounter[$levelCounter]] = 0;
			$maxContextCounter[$levelCounter][$levelEntranceCounter[$levelCounter]] = 0;
			$touchCounter[$levelCounter][$levelEntranceCounter[$levelCounter]] = 0;
			$alignType[$levelCounter][$levelEntranceCounter[$levelCounter]] = 0;
			$printedPlaceholder[$levelCounter][$levelEntranceCounter[$levelCounter]][$contextCounter[$levelCounter][$levelEntranceCounter[$levelCounter]]] = 0;
		}
		++$contextCounter[$levelCounter][$levelEntranceCounter[$levelCounter]];
		$maxContextCounter[$levelCounter][$levelEntranceCounter[$levelCounter]] = max($maxContextCounter[$levelCounter][$levelEntranceCounter[$levelCounter]], $contextCounter[$levelCounter][$levelEntranceCounter[$levelCounter]]);

	}
}
;
final class RemoveIncludeParentheses extends FormatterPass {
	public function candidate($source, $foundTokens) {
		if (isset($foundTokens[T_INCLUDE]) || isset($foundTokens[T_REQUIRE]) || isset($foundTokens[T_INCLUDE_ONCE]) || isset($foundTokens[T_REQUIRE_ONCE])) {
			return true;
		}

		return false;
	}
	public function format($source) {
		$this->tkns = token_get_all($source);
		$this->code = '';
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->getToken($token);
			$this->ptr = $index;
			switch ($id) {
				case ST_PARENTHESES_OPEN:
					$this->appendCode($text);
					$this->printBlock(ST_PARENTHESES_OPEN, ST_PARENTHESES_CLOSE);
					break;
				case T_INCLUDE:
				case T_REQUIRE:
				case T_INCLUDE_ONCE:
				case T_REQUIRE_ONCE:
					$this->appendCode($text . $this->getSpace());

					if (!$this->rightTokenIs(ST_PARENTHESES_OPEN)) {
						break;
					}
					$this->walkUntil(ST_PARENTHESES_OPEN);
					$count = 1;
					while (list($index, $token) = each($this->tkns)) {
						list($id, $text) = $this->getToken($token);
						$this->ptr = $index;
						$this->cache = [];

						if (ST_PARENTHESES_OPEN == $id) {
							++$count;
						}
						if (ST_PARENTHESES_CLOSE == $id) {
							--$count;
						}
						if (0 == $count) {
							break;
						}
						$this->appendCode($text);
					}
					break;
				default:
					$this->appendCode($text);
					break;
			}
		}

		return $this->code;
	}
}
;
final class ResizeSpaces extends FormatterPass {
	public function candidate($source, $foundTokens) {
		return true;
	}
	private function filterWhitespaces($source) {
		$tkns = token_get_all($source);

		$new_tkns = [];
		foreach ($tkns as $idx => $token) {
			if (T_WHITESPACE === $token[0] && !$this->hasLn($token[1])) {
				continue;
			}
			$new_tkns[] = $token;
		}

		return $new_tkns;
	}

	public function format($source) {
		$this->tkns = $this->filterWhitespaces($source);
		$this->code = '';
		$this->useCache = true;

		$inTernaryOperator = false;
		$shortTernaryOperator = false;
		$touchedFunction = false;
		$touchedUse = false;

		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->getToken($token);
			$this->ptr = $index;
			$this->cache = [];
			switch ($id) {
				case T_START_HEREDOC:
					$this->appendCode($text);
					$this->printUntil(ST_SEMI_COLON);
					break;

				case T_CALLABLE:
					$this->appendCode($text . $this->getSpace());
					break;

				case '+':
				case '-':
					if (
						$this->leftUsefulTokenIs([T_LNUMBER, T_DNUMBER, T_VARIABLE, ST_PARENTHESES_CLOSE, T_STRING, T_ARRAY, T_ARRAY_CAST, T_BOOL_CAST, T_DOUBLE_CAST, T_INT_CAST, T_OBJECT_CAST, T_STRING_CAST, T_UNSET_CAST, ST_BRACKET_CLOSE])
						&&
						$this->rightUsefulTokenIs([T_LNUMBER, T_DNUMBER, T_VARIABLE, ST_PARENTHESES_OPEN, ST_PARENTHESES_CLOSE, T_STRING, T_ARRAY, T_ARRAY_CAST, T_BOOL_CAST, T_DOUBLE_CAST, T_INT_CAST, T_OBJECT_CAST, T_STRING_CAST, T_UNSET_CAST, ST_BRACKET_CLOSE])
					) {
						$this->appendCode($this->getSpace() . $text . $this->getSpace());
					} else {
						$this->appendCode($text);
					}
					break;
				case '*':
					list($prevId, $prevText) = $this->inspectToken(-1);
					list($nextId, $nextText) = $this->inspectToken(+1);
					if (
						T_WHITESPACE === $prevId &&
						T_WHITESPACE !== $nextId
					) {
						$this->appendCode($text . $this->getSpace());
					} elseif (
						T_WHITESPACE !== $prevId &&
						T_WHITESPACE === $nextId
					) {
						$this->appendCode($this->getSpace() . $text);
					} elseif (
						T_WHITESPACE !== $prevId &&
						T_WHITESPACE !== $nextId
					) {
						$this->appendCode($this->getSpace() . $text . $this->getSpace());
					} else {
						$this->appendCode($text);
					}
					break;

				case '%':
				case '/':
				case T_POW:

				case ST_QUESTION:
				case ST_CONCAT:
					if (ST_QUESTION == $id) {
						$inTernaryOperator = true;
						$shortTernaryOperator = $this->rightTokenIs(ST_COLON);
					}
					list($prevId, $prevText) = $this->inspectToken(-1);
					list($nextId, $nextText) = $this->inspectToken(+1);
					if (
						T_WHITESPACE === $prevId &&
						T_WHITESPACE !== $nextId
					) {
						$this->appendCode($text . $this->getSpace(!$this->rightTokenIs(ST_COLON)));
						break;
					} elseif (
						T_WHITESPACE !== $prevId &&
						T_WHITESPACE === $nextId
					) {
						$this->appendCode($this->getSpace() . $text);
						break;
					} elseif (
						T_WHITESPACE !== $prevId &&
						T_WHITESPACE !== $nextId
					) {
						$this->appendCode($this->getSpace() . $text . $this->getSpace(!$this->rightTokenIs(ST_COLON)));
						break;
					}
				case ST_COLON:
					list($prevId, $prevText) = $this->inspectToken(-1);
					list($nextId, $nextText) = $this->inspectToken(+1);

					if (
						$this->rightUsefulTokenIs(T_CLOSE_TAG) &&
						(
							T_WHITESPACE != $nextId
							||
							(T_WHITESPACE == $nextId && !$this->hasLn($nextText))
						)
					) {
						$this->appendCode($text . $this->getSpace());
					} elseif (
						$inTernaryOperator &&
						T_WHITESPACE === $prevId &&
						T_WHITESPACE !== $nextId
					) {
						$this->appendCode($text . $this->getSpace());
						$inTernaryOperator = false;
					} elseif (
						$inTernaryOperator &&
						T_WHITESPACE !== $prevId &&
						T_WHITESPACE === $nextId
					) {
						$this->appendCode($this->getSpace(!$shortTernaryOperator) . $text);
						$inTernaryOperator = false;
					} elseif (
						$inTernaryOperator &&
						T_WHITESPACE !== $prevId &&
						T_WHITESPACE !== $nextId
					) {
						$this->appendCode($this->getSpace(!$shortTernaryOperator) . $text . $this->getSpace());
						$inTernaryOperator = false;
					} else {
						$this->appendCode($text);
					}
					break;

				case T_PRINT:
					$this->appendCode($text . $this->getSpace(!$this->rightTokenIs([ST_PARENTHESES_OPEN])));
					break;
				case T_ARRAY:
					if ($this->rightTokenIs([T_VARIABLE, ST_REFERENCE])) {
						$this->appendCode($text . $this->getSpace());
						break;
					} elseif ($this->rightTokenIs(ST_PARENTHESES_OPEN)) {
						$this->appendCode($text);
						break;
					}
				case T_STRING:
					if ($this->rightTokenIs([T_VARIABLE, T_DOUBLE_ARROW])) {
						$this->appendCode($text . $this->getSpace());
						break;
					} else {
						$this->appendCode($text);
						break;
					}
				case ST_CURLY_OPEN:
					$touchedFunction = false;
					if (!$touchedUse && $this->leftUsefulTokenIs([T_VARIABLE, T_STRING]) && $this->rightUsefulTokenIs([T_VARIABLE, T_STRING])) {
						$this->appendCode($text);
						break;
					} elseif (!$this->hasLnLeftToken() && $this->leftUsefulTokenIs([T_STRING, T_DO, T_FINALLY, ST_PARENTHESES_CLOSE])) {
						$this->rtrimAndAppendCode($this->getSpace() . $text);
						break;
					} elseif ($this->rightTokenIs(ST_CURLY_CLOSE) || ($this->rightTokenIs([T_VARIABLE]) && $this->leftTokenIs([T_OBJECT_OPERATOR, ST_DOLLAR]))) {
						$this->appendCode($text);
						break;
					} elseif ($this->rightTokenIs([T_VARIABLE, T_INC, T_DEC])) {
						$this->appendCode($text . $this->getSpace());
						break;
					} else {
						$this->appendCode($text);
						break;
					}

				case ST_SEMI_COLON:
					$touchedUse = false;
					if ($this->rightTokenIs([T_VARIABLE, T_INC, T_DEC, T_LNUMBER, T_DNUMBER, T_COMMENT, T_DOC_COMMENT])) {
						$this->appendCode($text . $this->getSpace());
						break;
					}
				case ST_PARENTHESES_OPEN:
					if (!$this->hasLnLeftToken() && $this->leftUsefulTokenIs([T_WHILE, T_CATCH])) {
						$this->rtrimAndAppendCode($this->getSpace() . $text);
					} else {
						$this->appendCode($text);
					}
					break;
				case ST_PARENTHESES_CLOSE:
					$this->appendCode($text);
					break;
				case T_USE:
					if ($this->leftTokenIs(ST_PARENTHESES_CLOSE)) {
						$this->appendCode($this->getSpace() . $text . $this->getSpace());
					} else {
						$this->appendCode($text . $this->getSpace());
					}
					$touchedUse = true;
					break;
				case T_RETURN:
				case T_YIELD:
				case T_ECHO:
				case T_NAMESPACE:
				case T_VAR:
				case T_NEW:
				case T_CONST:
				case T_FINAL:
				case T_CASE:
				case T_BREAK:
					$this->appendCode($text . $this->getSpace(!$this->rightTokenIs(ST_SEMI_COLON)));
					break;
				case T_WHILE:
					if ($this->leftTokenIs(ST_CURLY_CLOSE) && !$this->hasLnBefore()) {
						$this->appendCode($this->getSpace() . $text . $this->getSpace());
						break;
					}
				case T_DOUBLE_ARROW:
					if (T_DOUBLE_ARROW == $id && $this->leftTokenIs([T_CONSTANT_ENCAPSED_STRING, T_STRING, T_VARIABLE, T_LNUMBER, T_DNUMBER, ST_PARENTHESES_CLOSE, ST_BRACKET_CLOSE, ST_CURLY_CLOSE, ST_QUOTE])) {
						$this->rtrimAndAppendCode($this->getSpace() . $text . $this->getSpace());
						break;
					}
				case T_STATIC:
					$this->appendCode($text . $this->getSpace(!$this->rightTokenIs([ST_SEMI_COLON, T_DOUBLE_COLON, ST_PARENTHESES_OPEN])));
					break;
				case T_FUNCTION:
					$touchedFunction = true;
					$this->appendCode($text . $this->getSpace(!$this->rightTokenIs(ST_SEMI_COLON)));
					break;
				case T_PUBLIC:
				case T_PRIVATE:
				case T_PROTECTED:
				case T_TRAIT:
				case T_INTERFACE:
				case T_THROW:
				case T_GLOBAL:
				case T_ABSTRACT:
				case T_INCLUDE:
				case T_REQUIRE:
				case T_INCLUDE_ONCE:
				case T_REQUIRE_ONCE:
				case T_DECLARE:
				case T_IF:
				case T_FOR:
				case T_FOREACH:
				case T_SWITCH:
				case T_TRY:
				case ST_COMMA:
				case T_CLONE:
				case T_CONTINUE:
					$this->appendCode($text . $this->getSpace(!$this->rightTokenIs(ST_SEMI_COLON)));
					break;
				case T_CLASS:
					$this->appendCode($text . $this->getSpace(!$this->rightTokenIs(ST_SEMI_COLON) && !$this->leftTokenIs([T_DOUBLE_COLON])));
					break;
				case T_EXTENDS:
				case T_IMPLEMENTS:
				case T_INSTANCEOF:
				case T_INSTEADOF:
				case T_AS:
					$this->appendCode($this->getSpace() . $text . $this->getSpace());
					break;
				case T_LOGICAL_AND:
				case T_LOGICAL_OR:
				case T_LOGICAL_XOR:
				case T_AND_EQUAL:
				case T_BOOLEAN_AND:
				case T_BOOLEAN_OR:
				case T_CONCAT_EQUAL:
				case T_DIV_EQUAL:
				case T_IS_EQUAL:
				case T_IS_GREATER_OR_EQUAL:
				case T_IS_IDENTICAL:
				case T_IS_NOT_EQUAL:
				case T_IS_NOT_IDENTICAL:
				case T_IS_SMALLER_OR_EQUAL:
				case T_MINUS_EQUAL:
				case T_MOD_EQUAL:
				case T_MUL_EQUAL:
				case T_OR_EQUAL:
				case T_PLUS_EQUAL:
				case T_SL:
				case T_SL_EQUAL:
				case T_SR:
				case T_SR_EQUAL:
				case T_XOR_EQUAL:
				case ST_IS_GREATER:
				case ST_IS_SMALLER:
				case ST_EQUAL:
					$this->appendCode($this->getSpace(!$this->hasLnBefore()) . $text . $this->getSpace());
					break;
				case T_CATCH:
				case T_FINALLY:
					if ($this->hasLnLeftToken()) {
						$this->appendCode($this->getSpace() . $text . $this->getSpace());
					} else {
						$this->rtrimAndAppendCode($this->getSpace() . $text . $this->getSpace());
					}
					break;
				case T_ELSEIF:
					if (!$this->leftTokenIs(ST_CURLY_CLOSE)) {
						$this->appendCode($text . $this->getSpace());
					} else {
						$this->appendCode($this->getSpace() . $text . $this->getSpace());
					}
					break;
				case T_ELSE:
					if (!$this->leftUsefulTokenIs(ST_CURLY_CLOSE)) {
						$this->appendCode($text);
					} else {
						$this->appendCode($this->getSpace(!$this->leftTokenIs([T_COMMENT, T_DOC_COMMENT])) . $text . $this->getSpace());
					}
					break;
				case T_ARRAY_CAST:
				case T_BOOL_CAST:
				case T_DOUBLE_CAST:
				case T_INT_CAST:
				case T_OBJECT_CAST:
				case T_STRING_CAST:
				case T_UNSET_CAST:
				case T_GOTO:
					$this->appendCode(str_replace([' ', "\t"], '', $text) . $this->getSpace());
					break;
				case ST_REFERENCE:
					$spaceBefore = !$this->leftUsefulTokenIs([ST_EQUAL, ST_PARENTHESES_OPEN, T_AS, T_DOUBLE_ARROW, ST_COMMA]) && !$this->leftUsefulTokenIs([T_ARRAY, T_FUNCTION]);
					$spaceAfter = !$touchedFunction && !$this->leftUsefulTokenIs([ST_EQUAL, ST_PARENTHESES_OPEN, T_AS, T_DOUBLE_ARROW, ST_COMMA]);
					$this->appendCode($this->getSpace($spaceBefore) . $text . $this->getSpace($spaceAfter));
					break;
				default:
					$this->appendCode($text);
					break;
			}
		}

		return $this->code;
	}
}
;
final class RTrim extends FormatterPass {
	public function candidate($source, $foundTokens) {
		return true;
	}
	public function format($source) {
		return preg_replace('/\h+$/mu', '', $source);
	}
};
final class SettersAndGettersPass extends FormatterPass {
	const TYPE_CAMEL_CASE = 'camel';
	const TYPE_SNAKE_CASE = 'snake';
	const TYPE_GOLANG = 'golang';
	const PLACEHOLDER = "/*SETTERSANDGETTERSPLACEHOLDER%s\x3*/";
	const PLACEHOLDER_REGEX = '/(;\n\/\*SETTERSANDGETTERSPLACEHOLDER).*(\*\/)/';

	public function __construct($type = self::TYPE_CAMEL_CASE) {
		if (self::TYPE_CAMEL_CASE == $type || self::TYPE_SNAKE_CASE == $type || self::TYPE_GOLANG == $type) {
			$this->type = $type;
		} else {
			$this->type = self::TYPE_CAMEL_CASE;
		}
	}
	public function candidate($source, $foundTokens) {
		if (isset($foundTokens[T_CLASS])) {
			return true;
		}

		return false;
	}
	public function format($source) {
		$this->tkns = token_get_all($source);
		$this->code = '';
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->getToken($token);
			$this->ptr = $index;
			switch ($id) {
				case T_CLASS:
					$attributes = [
						'private' => [],
						'public' => [],
						'protected' => [],
					];
					$functionList = [];
					$touchedVisibility = false;
					$touchedFunction = false;
					$curlyCount = null;
					$this->appendCode($text);
					while (list($index, $token) = each($this->tkns)) {
						list($id, $text) = $this->getToken($token);
						$this->ptr = $index;
						if (ST_CURLY_OPEN == $id) {
							++$curlyCount;
						}
						if (ST_CURLY_CLOSE == $id) {
							--$curlyCount;
						}
						if (0 === $curlyCount) {
							break;
						}
						$this->appendCode($text);
						if (T_PUBLIC == $id) {
							$touchedVisibility = T_PUBLIC;
						} elseif (T_PRIVATE == $id) {
							$touchedVisibility = T_PRIVATE;
						} elseif (T_PROTECTED == $id) {
							$touchedVisibility = T_PROTECTED;
						}
						if (T_VARIABLE == $id && T_PUBLIC == $touchedVisibility) {
							$attributes['public'][] = $text;
							$touchedVisibility = null;
							$this->appendCode(';' . $this->newLine . sprintf(self::PLACEHOLDER, $text));
							each($this->tkns);
						} elseif (T_VARIABLE == $id && T_PRIVATE == $touchedVisibility) {
							$attributes['private'][] = $text;
							$touchedVisibility = null;
							$this->appendCode(';' . $this->newLine . sprintf(self::PLACEHOLDER, $text));
							each($this->tkns);
						} elseif (T_VARIABLE == $id && T_PROTECTED == $touchedVisibility) {
							$attributes['protected'][] = $text;
							$touchedVisibility = null;
							$this->appendCode(';' . $this->newLine . sprintf(self::PLACEHOLDER, $text));
							each($this->tkns);
						} elseif (T_FUNCTION == $id) {
							$touchedFunction = true;
						} elseif ($touchedFunction && T_STRING == $id) {
							$functionList[] = $text;
							$touchedVisibility = null;
							$touchedFunction = false;
						}
					}
					$functionList = array_combine($functionList, $functionList);
					$append = false;
					foreach ($attributes as $visibility => $variables) {
						foreach ($variables as $var) {
							$str = $this->generate($visibility, $var);
							foreach ($functionList as $k => $v) {
								if (false !== stripos($str, $v)) {
									unset($functionList[$k]);
									$append = true;
									continue 2;
								}
							}
							if ($append) {
								$this->appendCode($str);
							} else {
								$this->code = str_replace(sprintf(self::PLACEHOLDER, $var), $str, $this->code);
							}
						}
					}

					$this->appendCode($text);
					break;
				default:
					$this->appendCode($text);
					break;
			}
		}
		$this->code = preg_replace(self::PLACEHOLDER_REGEX, ';', $this->code);
		return $this->code;
	}

	private function generate($visibility, $var) {
		switch ($this->type) {
			case self::TYPE_SNAKE_CASE:
				$ret = $this->generateSnakeCase($visibility, $var);
				break;
			case self::TYPE_GOLANG:
				$ret = $this->generateGolang($visibility, $var);
				break;
			case self::TYPE_CAMEL_CASE:
			default:
				$ret = $this->generateCamelCase($visibility, $var);
				break;
		}
		return $ret;
	}
	private function generateCamelCase($visibility, $var) {
		$str = $this->newLine . $visibility . ' function set' . ucfirst(str_replace('$', '', $var)) . '(' . $var . '){' . $this->newLine . '$this->' . str_replace('$', '', $var) . ' = ' . $var . ';' . $this->newLine . '}' . $this->newLine . $this->newLine;
		$str .= $visibility . ' function get' . ucfirst(str_replace('$', '', $var)) . '(){' . $this->newLine . 'return $this->' . str_replace('$', '', $var) . ';' . $this->newLine . '}' . $this->newLine;
		return $str;
	}
	private function generateSnakeCase($visibility, $var) {
		$str = $this->newLine . $visibility . ' function set_' . (str_replace('$', '', $var)) . '(' . $var . '){' . $this->newLine . '$this->' . str_replace('$', '', $var) . ' = ' . $var . ';' . $this->newLine . '}' . $this->newLine . $this->newLine;
		$str .= $visibility . ' function get_' . (str_replace('$', '', $var)) . '(){' . $this->newLine . 'return $this->' . str_replace('$', '', $var) . ';' . $this->newLine . '}' . $this->newLine;
		return $str;
	}
	private function generateGolang($visibility, $var) {
		$str = $this->newLine . $visibility . ' function Set' . ucfirst(str_replace('$', '', $var)) . '(' . $var . '){' . $this->newLine . '$this->' . str_replace('$', '', $var) . ' = ' . $var . ';' . $this->newLine . '}' . $this->newLine . $this->newLine;
		$str .= $visibility . ' function ' . ucfirst(str_replace('$', '', $var)) . '(){' . $this->newLine . 'return $this->' . str_replace('$', '', $var) . ';' . $this->newLine . '}' . $this->newLine;
		return $str;
	}
};
final class SurrogateToken {
}
;
final class TwoCommandsInSameLine extends FormatterPass {
	public function candidate($source, $foundTokens) {
		return true;
	}
	public function format($source) {
		$this->tkns = token_get_all($source);
		$this->code = '';
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->getToken($token);
			$this->ptr = $index;

			switch ($id) {
				case ST_SEMI_COLON:
					if ($this->leftTokenIs(ST_SEMI_COLON)) {
						break;
					}
					$this->appendCode($text);
					if (!$this->hasLnAfter() && $this->rightTokenIs([T_VARIABLE, T_STRING, T_CONTINUE, T_BREAK, T_ECHO, T_PRINT])) {
						$this->appendCode($this->newLine);
					}
					break;

				case ST_PARENTHESES_OPEN:
					$this->appendCode($text);
					$this->printBlock(ST_PARENTHESES_OPEN, ST_PARENTHESES_CLOSE);
					break;
				default:
					$this->appendCode($text);
					break;

			}
		}
		return $this->code;
	}
}
;

final class PSR1BOMMark extends FormatterPass {
	public function candidate($source, $foundTokens) {
		return true;
	}
	public function format($source) {
		$bom = "\xef\xbb\xbf";
		if (substr($source, 0, 3) === $bom) {
			return substr($source, 3);
		}
		return $source;
	}
}
;
final class PSR1ClassConstants extends FormatterPass {
	public function candidate($source, $foundTokens) {
		if (isset($foundTokens[T_CONST]) || isset($foundTokens[T_STRING])) {
			return true;
		}

		return false;
	}
	public function format($source) {
		$this->tkns = token_get_all($source);
		$this->code = '';
		$ucConst = false;
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->getToken($token);
			$this->ptr = $index;
			switch ($id) {
				case T_CONST:
					$ucConst = true;
					$this->appendCode($text);
					break;
				case T_STRING:
					if ($ucConst) {
						$text = strtoupper($text);
						$ucConst = false;
					}
					$this->appendCode($text);
					break;
				default:
					$this->appendCode($text);
					break;
			}
		}
		return $this->code;
	}
};
final class PSR1ClassNames extends FormatterPass {
	public function candidate($source, $foundTokens) {
		if (isset($foundTokens[T_CLASS]) || isset($foundTokens[T_STRING])) {
			return true;
		}

		return false;
	}
	public function format($source) {
		$this->tkns = token_get_all($source);
		$this->code = '';
		$foundClass = false;
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->getToken($token);
			$this->ptr = $index;
			switch ($id) {
				case T_CLASS:
					$foundClass = true;
					$this->appendCode($text);
					break;
				case T_STRING:
					if ($foundClass) {
						$count = 0;
						$tmp = ucwords(str_replace(['-', '_'], ' ', strtolower($text), $count));
						if ($count > 0) {
							$text = str_replace(' ', '', $tmp);
						}
						$this->appendCode($text);

						$foundClass = false;
						break;
					}
				default:
					$this->appendCode($text);
					break;
			}
		}
		return $this->code;
	}
}
;
final class PSR1MethodNames extends FormatterPass {
	public function candidate($source, $foundTokens) {
		if (isset($foundTokens[T_FUNCTION]) || isset($foundTokens[T_STRING]) || isset($foundTokens[ST_PARENTHESES_OPEN])) {
			return true;
		}

		return false;
	}
	public function format($source) {
		$this->tkns = token_get_all($source);
		$this->code = '';
		$foundMethod = false;
		$methodReplaceList = [];
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->getToken($token);
			$this->ptr = $index;
			switch ($id) {
				case T_FUNCTION:
					$foundMethod = true;
					$this->appendCode($text);
					break;
				case T_STRING:
					if ($foundMethod) {
						$count = 0;
						$orig_text = $text;
						$tmp = ucwords(str_replace(['-', '_'], ' ', strtolower($text), $count));
						if ($count > 0 && '' !== trim($tmp) && '_' !== substr($text, 0, 1)) {
							$text = lcfirst(str_replace(' ', '', $tmp));
						}

						$methodReplaceList[$orig_text] = $text;
						$this->appendCode($text);

						$foundMethod = false;
						break;
					}
				case ST_PARENTHESES_OPEN:
					$foundMethod = false;
				default:
					$this->appendCode($text);
					break;
			}
		}

		$this->tkns = token_get_all($this->code);
		$this->code = '';
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->getToken($token);
			$this->ptr = $index;
			switch ($id) {
				case T_STRING:
					if (isset($methodReplaceList[$text]) && $this->rightUsefulTokenIs(ST_PARENTHESES_OPEN)) {

						$this->appendCode($methodReplaceList[$text]);
						break;
					}

				default:
					$this->appendCode($text);
					break;
			}
		}
		return $this->code;
	}
}
;
final class PSR1OpenTags extends FormatterPass {
	public function candidate($source, $foundTokens) {
		return true;
	}
	public function format($source) {
		$this->tkns = token_get_all($source);
		$this->code = '';
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->getToken($token);
			$this->ptr = $index;
			switch ($id) {
				case T_OPEN_TAG:
					if ('<?php' !== $text) {
						$this->appendCode('<?php' . $this->newLine);
						break;
					}
				default:
					$this->appendCode($text);
					break;
			}
		}
		return $this->code;
	}
}
;
final class PSR2AlignObjOp extends FormatterPass {
	const ALIGNABLE_TOKEN = "\x2 OBJOP%d \x3";
	public function candidate($source, $foundTokens) {
		if (isset($foundTokens[ST_SEMI_COLON]) || isset($foundTokens[T_ARRAY]) || isset($foundTokens[T_DOUBLE_ARROW]) || isset($foundTokens[T_OBJECT_OPERATOR])) {
			return true;
		}

		return false;
	}
	public function format($source) {
		$this->tkns = token_get_all($source);
		$this->code = '';
		$contextCounter = 0;
		$contextMetaCount = [];
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->getToken($token);
			$this->ptr = $index;
			switch ($id) {
				case ST_SEMI_COLON:
				case T_ARRAY:
				case T_DOUBLE_ARROW:
					++$contextCounter;
					$this->appendCode($text);
					break;

				case T_OBJECT_OPERATOR:
					if (!isset($contextMetaCount[$contextCounter])) {
						$contextMetaCount[$contextCounter] = 0;
					}
					if ($this->hasLnBefore() || 0 == $contextMetaCount[$contextCounter]) {
						$this->appendCode(sprintf(self::ALIGNABLE_TOKEN, $contextCounter) . $text);
						++$contextMetaCount[$contextCounter];
						break;
					}
				default:
					$this->appendCode($text);
					break;
			}
		}

		for ($j = 0; $j <= $contextCounter; ++$j) {
			$placeholder = sprintf(self::ALIGNABLE_TOKEN, $j);
			if (false === strpos($this->code, $placeholder)) {
				continue;
			}
			if (1 === substr_count($this->code, $placeholder)) {
				$this->code = str_replace($placeholder, '', $this->code);
				continue;
			}

			$lines = explode($this->newLine, $this->code);
			$linesWithObjop = [];
			$blockCount = 0;

			foreach ($lines as $idx => $line) {
				if (false !== strpos($line, $placeholder)) {
					$linesWithObjop[$blockCount][] = $idx;
				} else {
					++$blockCount;
					$linesWithObjop[$blockCount] = [];
				}
			}

			foreach ($linesWithObjop as $group) {
				$first_line = reset($group);
				$position_at_first_line = strpos($lines[$first_line], $placeholder);

				foreach ($group as $idx) {
					if ($idx == $first_line) {
						continue;
					}
					$line = ltrim($lines[$idx]);
					$line = str_replace($placeholder, str_repeat(' ', $position_at_first_line) . $placeholder, $line);
					$lines[$idx] = $line;
				}
			}

			$this->code = str_replace($placeholder, '', implode($this->newLine, $lines));
		}
		return $this->code;
	}
}
;
final class PSR2CurlyOpenNextLine extends FormatterPass {
	public function candidate($source, $foundTokens) {
		return true;
	}
	public function format($source) {
		$this->indentChar = '    ';
		$this->tkns = token_get_all($source);
		$this->code = '';

		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->getToken($token);
			$this->ptr = $index;
			switch ($id) {
				case T_START_HEREDOC:
					$this->appendCode($text);
					$this->printUntil(T_END_HEREDOC);
					break;
				case ST_QUOTE:
					$this->appendCode($text);
					$this->printUntilTheEndOfString();
					break;
				case T_INTERFACE:
				case T_TRAIT:
				case T_CLASS:
					$this->appendCode($text);
					while (list($index, $token) = each($this->tkns)) {
						list($id, $text) = $this->getToken($token);
						$this->ptr = $index;
						if (ST_CURLY_OPEN === $id) {
							$this->appendCode($this->getCrlfIndent());
							prev($this->tkns);
							break;
						} else {
							$this->appendCode($text);
						}
					}
					break;
				case T_FUNCTION:
					if (!$this->leftTokenIs([T_DOUBLE_ARROW, T_RETURN, ST_EQUAL, ST_PARENTHESES_OPEN, ST_COMMA]) && $this->rightUsefulTokenIs(T_STRING)) {
						$this->appendCode($text);
						$touchedLn = false;
						while (list($index, $token) = each($this->tkns)) {
							list($id, $text) = $this->getToken($token);
							$this->ptr = $index;
							if (T_WHITESPACE == $id && $this->hasLn($text)) {
								$touchedLn = true;
							}
							if (ST_CURLY_OPEN === $id && !$touchedLn) {
								$this->appendCode($this->getCrlfIndent());
								prev($this->tkns);
								break;
							} elseif (ST_CURLY_OPEN === $id) {
								prev($this->tkns);
								break;
							} else {
								$this->appendCode($text);
							}
						}
						break;
					} else {
						$this->appendCode($text);
					}
					break;
				case ST_CURLY_OPEN:
					$this->appendCode($text);
					$this->setIndent(+1);
					break;
				case ST_CURLY_CLOSE:
					$this->setIndent(-1);
					$this->appendCode($text);
					break;
				default:
					$this->appendCode($text);
					break;
			}
		}
		return $this->code;
	}
};
final class PSR2IndentWithSpace extends FormatterPass {
	private $size = 4;

	public function __construct($size = null) {
		if ($size > 0) {
			$this->size = $size;
		}
	}
	public function candidate($source, $foundTokens) {
		return true;
	}
	public function format($source) {
		$indent_spaces = str_repeat(' ', (int) $this->size);
		$this->tkns = token_get_all($source);
		$this->code = '';
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->getToken($token);
			$this->ptr = $index;
			switch ($id) {
				case T_COMMENT:
				case T_DOC_COMMENT:
				case T_WHITESPACE:
					$this->appendCode(str_replace($this->indentChar, $indent_spaces, $text));
					break;
				default:
					$this->appendCode($text);
					break;
			}
		}
		return $this->code;
	}
};
final class PSR2KeywordsLowerCase extends FormatterPass {
	public function candidate($source, $foundTokens) {
		return true;
	}
	public function format($source) {
		$this->tkns = token_get_all($source);
		$this->code = '';
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->getToken($token);
			$this->ptr = $index;
			switch ($id) {
				case T_ABSTRACT:
				case T_ARRAY:
				case T_ARRAY_CAST:
				case T_AS:
				case T_BOOL_CAST:
				case T_BREAK:
				case T_CASE:
				case T_CATCH:
				case T_CLASS:
				case T_CLONE:
				case T_CONST:
				case T_CONTINUE:
				case T_DECLARE:
				case T_DEFAULT:
				case T_DO:
				case T_DOUBLE_CAST:
				case T_ECHO:
				case T_ELSE:
				case T_ELSEIF:
				case T_EMPTY:
				case T_ENDDECLARE:
				case T_ENDFOR:
				case T_ENDFOREACH:
				case T_ENDIF:
				case T_ENDSWITCH:
				case T_ENDWHILE:
				case T_EVAL:
				case T_EXIT:
				case T_EXTENDS:
				case T_FINAL:
				case T_FINALLY:
				case T_FOR:
				case T_FOREACH:
				case T_FUNCTION:
				case T_GLOBAL:
				case T_GOTO:
				case T_IF:
				case T_IMPLEMENTS:
				case T_INCLUDE:
				case T_INCLUDE_ONCE:
				case T_INSTANCEOF:
				case T_INSTEADOF:
				case T_INT_CAST:
				case T_INTERFACE:
				case T_ISSET:
				case T_LIST:
				case T_LOGICAL_AND:
				case T_LOGICAL_OR:
				case T_LOGICAL_XOR:
				case T_NAMESPACE:
				case T_NEW:
				case T_OBJECT_CAST:
				case T_PRINT:
				case T_PRIVATE:
				case T_PUBLIC:
				case T_PROTECTED:
				case T_REQUIRE:
				case T_REQUIRE_ONCE:
				case T_RETURN:
				case T_STATIC:
				case T_STRING_CAST:
				case T_SWITCH:
				case T_THROW:
				case T_TRAIT:
				case T_TRY:
				case T_UNSET:
				case T_UNSET_CAST:
				case T_USE:
				case T_VAR:
				case T_WHILE:
				case T_XOR_EQUAL:
				case T_YIELD:
					$this->appendCode(strtolower($text));
					break;
				default:
					$lc_text = strtolower($text);
					if (
						!$this->leftUsefulTokenIs([
							T_NS_SEPARATOR, T_AS, T_CLASS, T_EXTENDS, T_IMPLEMENTS, T_INSTANCEOF, T_INTERFACE, T_NEW, T_NS_SEPARATOR, T_PAAMAYIM_NEKUDOTAYIM, T_USE, T_TRAIT, T_INSTEADOF, T_CONST,
						]) &&
						!$this->rightUsefulTokenIs([
							T_NS_SEPARATOR, T_AS, T_CLASS, T_EXTENDS, T_IMPLEMENTS, T_INSTANCEOF, T_INTERFACE, T_NEW, T_NS_SEPARATOR, T_PAAMAYIM_NEKUDOTAYIM, T_USE, T_TRAIT, T_INSTEADOF, T_CONST,
						]) &&
						('true' === $lc_text || 'false' === $lc_text || 'null' === $lc_text)) {
						$text = $lc_text;
					}
					$this->appendCode($text);
					break;
			}
		}
		return $this->code;
	}
};
final class PSR2LnAfterNamespace extends FormatterPass {
	public function candidate($source, $foundTokens) {
		if (isset($foundTokens[T_NAMESPACE])) {
			return true;
		}

		return false;
	}
	public function format($source) {
		$this->tkns = token_get_all($source);
		$this->code = '';

		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->getToken($token);
			$this->ptr = $index;
			switch ($id) {
				case T_NAMESPACE:
					$this->appendCode($this->getCrlf($this->leftTokenIs(ST_CURLY_CLOSE)) . $text);
					while (list($index, $token) = each($this->tkns)) {
						list($id, $text) = $this->getToken($token);
						$this->ptr = $index;
						if (ST_SEMI_COLON === $id) {
							$this->appendCode($text);
							list(, $text) = $this->inspectToken();
							if (1 === substr_count($text, $this->newLine)) {
								$this->appendCode($this->newLine);
							}
							break;
						} elseif (ST_CURLY_OPEN === $id) {
							$this->appendCode($text);
							break;
						} else {
							$this->appendCode($text);
						}
					}
					break;
				default:
					$this->appendCode($text);
					break;
			}
		}

		return $this->code;
	}
};
final class PSR2ModifierVisibilityStaticOrder extends FormatterPass {
	public function candidate($source, $foundTokens) {
		return true;
	}
	public function format($source) {
		$this->tkns = token_get_all($source);
		$this->code = '';

		$found = [];
		$visibility = null;
		$finalOrAbstract = null;
		$static = null;
		$skipWhitespaces = false;
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->getToken($token);
			$this->ptr = $index;
			switch ($id) {
				case T_START_HEREDOC:
					$this->appendCode($text);
					$this->printUntil(T_END_HEREDOC);
					break;
				case ST_QUOTE:
					$this->appendCode($text);
					$this->printUntilTheEndOfString();
					break;
				case T_CLASS:
					$found[] = T_CLASS;
					$this->appendCode($text);
					break;
				case T_INTERFACE:
					$found[] = T_INTERFACE;
					$this->appendCode($text);
					break;
				case T_TRAIT:
					$found[] = T_TRAIT;
					$this->appendCode($text);
					break;
				case ST_CURLY_OPEN:
				case ST_PARENTHESES_OPEN:
					$found[] = $text;
					$this->appendCode($text);
					break;
				case ST_CURLY_CLOSE:
				case ST_PARENTHESES_CLOSE:
					array_pop($found);
					if (1 === sizeof($found)) {
						array_pop($found);
					}
					$this->appendCode($text);
					break;
				case T_WHITESPACE:
					if (!$skipWhitespaces) {
						$this->appendCode($text);
					}
					break;
				case T_PUBLIC:
				case T_PRIVATE:
				case T_PROTECTED:
					$visibility = $text;
					$skipWhitespaces = true;
					break;
				case T_FINAL:
				case T_ABSTRACT:
					if (!$this->rightTokenIs([T_CLASS])) {
						$finalOrAbstract = $text;
						$skipWhitespaces = true;
					} else {
						$this->appendCode($text);
					}
					break;
				case T_STATIC:
					if (!is_null($visibility)) {
						$static = $text;
						$skipWhitespaces = true;
					} elseif (!$this->rightTokenIs([T_VARIABLE, T_DOUBLE_COLON]) && !$this->leftTokenIs([T_NEW])) {
						$static = $text;
						$skipWhitespaces = true;
					} else {
						$this->appendCode($text);
					}
					break;
				case T_VARIABLE:
					if (
						null !== $visibility ||
						null !== $finalOrAbstract ||
						null !== $static
					) {
						null !== $finalOrAbstract && $this->appendCode($finalOrAbstract . $this->getSpace());
						null !== $visibility && $this->appendCode($visibility . $this->getSpace());
						null !== $static && $this->appendCode($static . $this->getSpace());
						$finalOrAbstract = null;
						$visibility = null;
						$static = null;
						$skipWhitespaces = false;
					}
					$this->appendCode($text);
					break;
				case T_FUNCTION:
					$has_found_class_or_interface = isset($found[0]) && (T_CLASS === $found[0] || T_INTERFACE === $found[0] || T_TRAIT === $found[0]) && $this->rightUsefulTokenIs(T_STRING);
					if (isset($found[0]) && $has_found_class_or_interface && null !== $finalOrAbstract) {
						$this->appendCode($finalOrAbstract . $this->getSpace());
					}
					if (isset($found[0]) && $has_found_class_or_interface && null !== $visibility) {
						$this->appendCode($visibility . $this->getSpace());
					} elseif (
						isset($found[0]) && $has_found_class_or_interface &&
						!$this->leftTokenIs([T_DOUBLE_ARROW, T_RETURN, ST_EQUAL, ST_COMMA, ST_PARENTHESES_OPEN])
					) {
						$this->appendCode('public' . $this->getSpace());
					}
					if (isset($found[0]) && $has_found_class_or_interface && null !== $static) {
						$this->appendCode($static . $this->getSpace());
					}
					$this->appendCode($text);
					if ('abstract' == strtolower($finalOrAbstract)) {
						$this->printUntil(ST_SEMI_COLON);
					} else {
						$this->printUntil(ST_CURLY_OPEN);
						$this->printCurlyBlock();
					}
					$finalOrAbstract = null;
					$visibility = null;
					$static = null;
					$skipWhitespaces = false;
					break;
				default:
					$this->appendCode($text);
					break;
			}
		}
		return $this->code;
	}
};
final class PSR2SingleEmptyLineAndStripClosingTag extends FormatterPass {
	public function candidate($source, $foundTokens) {
		return true;
	}
	public function format($source) {
		$this->tkns = token_get_all($source);
		$tokenCount = count($this->tkns) - 1;
		while (list($index, $token) = each($this->tkns)) {
			list($id, ) = $this->getToken($token);
			$this->ptr = $index;
			if (T_INLINE_HTML == $id && $this->ptr != $tokenCount) {
				return $source;
			}
		}

		list($id, $text) = $this->getToken(end($this->tkns));
		$this->ptr = key($this->tkns);

		if (T_CLOSE_TAG == $id) {
			unset($this->tkns[$this->ptr]);
		} elseif (T_INLINE_HTML == $id && '' == trim($text) && $this->leftTokenIs(T_CLOSE_TAG)) {
			unset($this->tkns[$this->ptr]);
			$ptr = $this->leftTokenIdx([]);
			unset($this->tkns[$ptr]);
		}

		return rtrim($this->render()) . $this->newLine;
	}
}
;
class PsrDecorator {
	public static function PSR1(CodeFormatter $fmt) {
		$fmt->addPass(new PSR1OpenTags());
		$fmt->addPass(new PSR1BOMMark());
		$fmt->addPass(new PSR1ClassConstants());
	}

	public static function PSR1Naming(CodeFormatter $fmt) {
		$fmt->addPass(new PSR1ClassNames());
		$fmt->addPass(new PSR1MethodNames());
	}

	public static function PSR2(CodeFormatter $fmt) {
		$fmt->addPass(new PSR2KeywordsLowerCase());
		$fmt->addPass(new PSR2IndentWithSpace());
		$fmt->addPass(new PSR2LnAfterNamespace());
		$fmt->addPass(new PSR2CurlyOpenNextLine());
		$fmt->addPass(new PSR2ModifierVisibilityStaticOrder());
		$fmt->addPass(new PSR2SingleEmptyLineAndStripClosingTag());
	}

	public static function decorate(CodeFormatter $fmt) {
		self::PSR1($fmt);
		self::PSR1Naming($fmt);
		self::PSR2($fmt);
	}
};

class AddMissingParentheses extends AdditionalPass {
	public function candidate($source, $foundTokens) {
		if (isset($foundTokens[T_NEW])) {
			return true;
		}

		return false;
	}
	public function format($source) {
		$this->tkns = token_get_all($source);
		$this->code = '';
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->getToken($token);
			$this->ptr = $index;
			switch ($id) {
				case T_NEW:
					$this->appendCode($text);
					list($foundId, $foundText) = $this->printAndStopAt([ST_PARENTHESES_OPEN, T_COMMENT, T_DOC_COMMENT, ST_SEMI_COLON]);
					if (ST_PARENTHESES_OPEN != $foundId) {
						$this->appendCode('()' . $foundText);
					}
					break;
				default:
					$this->appendCode($text);
			}
		}

		return $this->code;
	}

	/**
	 * @codeCoverageIgnore
	 */
	public function getDescription() {
		return 'Add extra parentheses in new instantiations.';
	}

	/**
	 * @codeCoverageIgnore
	 */
	public function getExample() {
		return <<<'EOT'
<?php
$a = new SomeClass;

$a = new SomeClass();
?>
EOT;
	}
}
;
final class AlignDoubleArrow extends AdditionalPass {
	const ALIGNABLE_EQUAL = "\x2 EQUAL%d.%d.%d \x3"; // level.levelentracecounter.counter
	public function candidate($source, $foundTokens) {
		return true;
	}
	public function format($source) {
		$this->tkns = token_get_all($source);
		$this->code = '';

		$levelCounter = 0;
		$levelEntranceCounter = [];
		$contextCounter = [];
		$maxContextCounter = [];

		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->getToken($token);
			$this->ptr = $index;
			switch ($id) {
				case ST_COMMA:
					if (!$this->hasLnAfter() && !$this->hasLnRightToken()) {
						if (!isset($levelEntranceCounter[$levelCounter])) {
							$levelEntranceCounter[$levelCounter] = 0;
						}
						if (!isset($contextCounter[$levelCounter][$levelEntranceCounter[$levelCounter]])) {
							$contextCounter[$levelCounter][$levelEntranceCounter[$levelCounter]] = 0;
							$maxContextCounter[$levelCounter][$levelEntranceCounter[$levelCounter]] = 0;
						}
						++$contextCounter[$levelCounter][$levelEntranceCounter[$levelCounter]];
						$maxContextCounter[$levelCounter][$levelEntranceCounter[$levelCounter]] = max($maxContextCounter[$levelCounter][$levelEntranceCounter[$levelCounter]], $contextCounter[$levelCounter][$levelEntranceCounter[$levelCounter]]);
					} elseif ($contextCounter[$levelCounter][$levelEntranceCounter[$levelCounter]] > 1) {
						$contextCounter[$levelCounter][$levelEntranceCounter[$levelCounter]] = 1;
					}
					$this->appendCode($text);
					break;

				case T_DOUBLE_ARROW:
					$this->appendCode(
						sprintf(
							self::ALIGNABLE_EQUAL,
							$levelCounter,
							$levelEntranceCounter[$levelCounter],
							$contextCounter[$levelCounter][$levelEntranceCounter[$levelCounter]]
						) . $text
					);
					break;

				case ST_PARENTHESES_OPEN:
				case ST_BRACKET_OPEN:
					++$levelCounter;
					if (!isset($levelEntranceCounter[$levelCounter])) {
						$levelEntranceCounter[$levelCounter] = 0;
					}
					++$levelEntranceCounter[$levelCounter];
					if (!isset($contextCounter[$levelCounter][$levelEntranceCounter[$levelCounter]])) {
						$contextCounter[$levelCounter][$levelEntranceCounter[$levelCounter]] = 0;
						$maxContextCounter[$levelCounter][$levelEntranceCounter[$levelCounter]] = 0;
					}
					++$contextCounter[$levelCounter][$levelEntranceCounter[$levelCounter]];
					$maxContextCounter[$levelCounter][$levelEntranceCounter[$levelCounter]] = max($maxContextCounter[$levelCounter][$levelEntranceCounter[$levelCounter]], $contextCounter[$levelCounter][$levelEntranceCounter[$levelCounter]]);

					$this->appendCode($text);
					break;

				case ST_PARENTHESES_CLOSE:
				case ST_BRACKET_CLOSE:
					--$levelCounter;
					$this->appendCode($text);
					break;

				default:
					$this->appendCode($text);
					break;
			}
		}

		foreach ($maxContextCounter as $level => $entrances) {
			foreach ($entrances as $entrance => $context) {
				for ($j = 0; $j <= $context; ++$j) {
					$placeholder = sprintf(self::ALIGNABLE_EQUAL, $level, $entrance, $j);
					if (false === strpos($this->code, $placeholder)) {
						continue;
					}
					if (1 === substr_count($this->code, $placeholder)) {
						$this->code = str_replace($placeholder, '', $this->code);
						continue;
					}

					$lines = explode($this->newLine, $this->code);
					$linesWithObjop = [];
					$blockCount = 0;

					foreach ($lines as $idx => $line) {
						if (false !== strpos($line, $placeholder)) {
							$linesWithObjop[] = $idx;
						}
					}

					$farthest = 0;
					foreach ($linesWithObjop as $idx) {
						$farthest = max($farthest, strpos($lines[$idx], $placeholder));
					}
					foreach ($linesWithObjop as $idx) {
						$line = $lines[$idx];
						$current = strpos($line, $placeholder);
						$delta = abs($farthest - $current);
						if ($delta > 0) {
							$line = str_replace($placeholder, str_repeat(' ', $delta) . $placeholder, $line);
							$lines[$idx] = $line;
						}
					}

					$this->code = str_replace($placeholder, '', implode($this->newLine, $lines));
				}
			}
		}

		return $this->code;
	}

	/**
	 * @codeCoverageIgnore
	 */
	public function getDescription() {
		return 'Vertically align T_DOUBLE_ARROW (=>).';
	}

	/**
	 * @codeCoverageIgnore
	 */
	public function getExample() {
		return <<<'EOT'
<?php
$a = [
	1 => 1,
	22 => 22,
	333 => 333,
];

$a = [
	1   => 1,
	22  => 22,
	333 => 333,
];
?>
EOT;
	}
}
;
final class AlignEquals extends AdditionalPass {
	const ALIGNABLE_EQUAL = "\x2 EQUAL%d \x3";
	public function candidate($source, $foundTokens) {
		return true;
	}
	public function format($source) {
		$this->tkns = token_get_all($source);
		$this->code = '';
		$parenCount = 0;
		$bracketCount = 0;
		$contextCounter = 0;
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->getToken($token);
			$this->ptr = $index;
			switch ($id) {
				case T_FUNCTION:
					++$contextCounter;
					$this->appendCode($text);
					break;
				case ST_PARENTHESES_OPEN:
					++$parenCount;
					$this->appendCode($text);
					break;
				case ST_PARENTHESES_CLOSE:
					--$parenCount;
					$this->appendCode($text);
					break;
				case ST_BRACKET_OPEN:
					++$bracketCount;
					$this->appendCode($text);
					break;
				case ST_BRACKET_CLOSE:
					--$bracketCount;
					$this->appendCode($text);
					break;
				case ST_EQUAL:
					if (!$parenCount && !$bracketCount) {
						$this->appendCode(sprintf(self::ALIGNABLE_EQUAL, $contextCounter) . $text);
						break;
					}

				default:
					$this->appendCode($text);
					break;
			}
		}

		for ($j = 0; $j <= $contextCounter; ++$j) {
			$placeholder = sprintf(self::ALIGNABLE_EQUAL, $j);
			if (false === strpos($this->code, $placeholder)) {
				continue;
			}
			if (1 === substr_count($this->code, $placeholder)) {
				$this->code = str_replace($placeholder, '', $this->code);
				continue;
			}

			$lines = explode($this->newLine, $this->code);
			$linesWithObjop = [];
			$blockCount = 0;

			foreach ($lines as $idx => $line) {
				if (false !== strpos($line, $placeholder)) {
					$linesWithObjop[$blockCount][] = $idx;
				} else {
					++$blockCount;
					$linesWithObjop[$blockCount] = [];
				}
			}

			$i = 0;
			foreach ($linesWithObjop as $group) {
				++$i;
				$farthest = 0;
				foreach ($group as $idx) {
					$farthest = max($farthest, strpos($lines[$idx], $placeholder));
				}
				foreach ($group as $idx) {
					$line = $lines[$idx];
					$current = strpos($line, $placeholder);
					$delta = abs($farthest - $current);
					if ($delta > 0) {
						$line = str_replace($placeholder, str_repeat(' ', $delta) . $placeholder, $line);
						$lines[$idx] = $line;
					}
				}
			}

			$this->code = str_replace($placeholder, '', implode($this->newLine, $lines));
		}

		return $this->code;
	}

	/**
	 * @codeCoverageIgnore
	 */
	public function getDescription() {
		return 'Vertically align "=".';
	}

	/**
	 * @codeCoverageIgnore
	 */
	public function getExample() {
		return <<<'EOT'
<?php
$a = 1;
$bb = 22;
$ccc = 333;

$a   = 1;
$bb  = 22;
$ccc = 333;

?>
EOT;
	}
};
class AutoPreincrement extends AdditionalPass {
	protected $candidateTokens = [T_INC, T_DEC];
	protected $checkAgainstConcat = false;
	const CHAIN_VARIABLE = 'CHAIN_VARIABLE';
	const CHAIN_LITERAL = 'CHAIN_LITERAL';
	const CHAIN_FUNC = 'CHAIN_FUNC';
	const CHAIN_STRING = 'CHAIN_STRING';
	const PARENTHESES_BLOCK = 'PARENTHESES_BLOCK';
	public function candidate($source, $foundTokens) {
		if (isset($foundTokens[T_INC]) || isset($foundTokens[T_DEC])) {
			return true;
		}

		return false;
	}
	public function format($source) {
		return $this->swap($source);
	}
	protected function swap($source) {
		$tkns = $this->aggregateVariables($source);
		$touchedConcat = false;
		while (list($ptr, $token) = each($tkns)) {
			list($id, $text) = $this->getToken($token);
			switch ($id) {
				case ST_CONCAT:
					$touchedConcat = true;
					break;
				case T_INC:
				case T_DEC:
					$prevToken = $tkns[$ptr - 1];
					list($prevId, ) = $prevToken;
					if (
						(
							!$this->checkAgainstConcat
							||
							($this->checkAgainstConcat && !$touchedConcat)
						) &&
						(T_VARIABLE == $prevId || self::CHAIN_VARIABLE == $prevId)
					) {
						list($tkns[$ptr], $tkns[$ptr - 1]) = [$tkns[$ptr - 1], $tkns[$ptr]];
						break;
					}
					$touchedConcat = false;
			}
		}
		return $this->render($tkns);
	}

	private function aggregateVariables($source) {
		$tkns = token_get_all($source);
		reset($tkns);
		while (list($ptr, $token) = each($tkns)) {
			list($id, $text) = $this->getToken($token);

			if (ST_PARENTHESES_OPEN == $id) {
				$initialPtr = $ptr;
				$tmp = $this->scanAndReplace($tkns, $ptr, ST_PARENTHESES_OPEN, ST_PARENTHESES_CLOSE, 'swap', $this->candidateTokens);
				$tkns[$initialPtr] = [self::PARENTHESES_BLOCK, $tmp];
				continue;
			}
			if (ST_QUOTE == $id) {
				$stack = $text;
				$initialPtr = $ptr;
				while (list($ptr, $token) = each($tkns)) {
					list($id, $text) = $this->getToken($token);
					$stack .= $text;
					$tkns[$ptr] = null;
					if (ST_QUOTE == $id) {
						break;
					}
				}

				$tkns[$initialPtr] = [self::CHAIN_STRING, $stack];
				continue;
			}

			if (ST_DOLLAR == $id) {
				$initialIndex = $ptr;
				$tkns[$ptr] = null;
				$stack = '';
				do {
					list($ptr, $token) = each($tkns);
					list($id, $text) = $this->getToken($token);
					$tkns[$ptr] = null;
					$stack .= $text;
				} while (ST_CURLY_OPEN != $id);
				$stack = $this->scanAndReplace($tkns, $ptr, ST_CURLY_OPEN, ST_CURLY_CLOSE, 'swap', $this->candidateTokens);
				$tkns[$initialIndex] = [self::CHAIN_VARIABLE, '$' . $stack];
			}

			if (T_STRING == $id || T_VARIABLE == $id || T_NS_SEPARATOR == $id) {
				$initialIndex = $ptr;
				$stack = $text;
				$touchedVariable = false;
				if (T_VARIABLE == $id) {
					$touchedVariable = true;
				}
				if (!$this->rightTokenSubsetIsAtIdx(
					$tkns,
					$ptr,
					[T_STRING, T_VARIABLE, T_NS_SEPARATOR, T_OBJECT_OPERATOR, T_DOUBLE_COLON, ST_CURLY_OPEN, ST_PARENTHESES_OPEN, ST_BRACKET_OPEN]
				)) {
					continue;
				}

				while (list($ptr, $token) = each($tkns)) {
					list($id, $text) = $this->getToken($token);
					$tkns[$ptr] = null;
					if (ST_CURLY_OPEN == $id) {
						$text = $this->scanAndReplace($tkns, $ptr, ST_CURLY_OPEN, ST_CURLY_CLOSE, 'swap', $this->candidateTokens);
					} elseif (ST_BRACKET_OPEN == $id) {
						$text = $this->scanAndReplace($tkns, $ptr, ST_BRACKET_OPEN, ST_BRACKET_CLOSE, 'swap', $this->candidateTokens);
					} elseif (ST_PARENTHESES_OPEN == $id) {
						$text = $this->scanAndReplace($tkns, $ptr, ST_PARENTHESES_OPEN, ST_PARENTHESES_CLOSE, 'swap', $this->candidateTokens);
					}

					$stack .= $text;

					if (!$touchedVariable && T_VARIABLE == $id) {
						$touchedVariable = true;
					}

					if (
						!$this->rightTokenSubsetIsAtIdx(
							$tkns,
							$ptr,
							[T_STRING, T_VARIABLE, T_NS_SEPARATOR, T_OBJECT_OPERATOR, T_DOUBLE_COLON, ST_CURLY_OPEN, ST_PARENTHESES_OPEN, ST_BRACKET_OPEN]
						)
					) {
						break;
					}
				}
				if (substr(trim($stack), -1, 1) == ST_PARENTHESES_CLOSE) {
					$tkns[$initialIndex] = [self::CHAIN_FUNC, $stack];
				} elseif ($touchedVariable) {
					$tkns[$initialIndex] = [self::CHAIN_VARIABLE, $stack];
				} else {
					$tkns[$initialIndex] = [self::CHAIN_LITERAL, $stack];
				}
			}
		}
		$tkns = array_values(array_filter($tkns));
		return $tkns;
	}

	/**
	 * @codeCoverageIgnore
	 */
	public function getDescription() {
		return 'Automatically convert postincrement to preincrement.';
	}
	/**
	 * @codeCoverageIgnore
	 */
	public function getExample() {
		return <<<'EOT'
<?php
$a++;
$b--;

++$a;
--$b;
?>
EOT;
	}
};
class CakePHPStyle extends AdditionalPass {
	private $foundTokens;

	public function candidate($source, $foundTokens) {
		$this->foundTokens = $foundTokens;
		return true;
	}

	public function format($source) {
		$fmt = new PSR2ModifierVisibilityStaticOrder();
		if ($fmt->candidate($source, $this->foundTokens)) {
			$source = $fmt->format($source);
		}
		$fmt = new MergeElseIf();
		if ($fmt->candidate($source, $this->foundTokens)) {
			$source = $fmt->format($source);
		}
		$source = $this->addUnderscoresBeforeName($source);
		$source = $this->removeSpaceAfterCasts($source);
		$source = $this->mergeEqualsWithReference($source);
		$source = $this->resizeSpaces($source);
		return $source;
	}
	private function resizeSpaces($source) {
		$this->tkns = token_get_all($source);
		$this->code = '';
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->getToken($token);
			$this->ptr = $index;
			switch ($id) {
				case T_COMMENT:
				case T_DOC_COMMENT:
					if (!$this->hasLnBefore() && $this->leftTokenIs(ST_CURLY_OPEN)) {
						$this->rtrimAndAppendCode($this->getSpace() . $text);
						break;
					} elseif ($this->rightUsefulTokenIs(T_CONSTANT_ENCAPSED_STRING)) {
						$this->appendCode($text . $this->getSpace());
						break;
					}
					$this->appendCode($text);
					break;
				case T_CLOSE_TAG:
					if (!$this->hasLnBefore()) {
						$this->rtrimAndAppendCode($this->getSpace() . $text);
						break;
					}
					$this->appendCode($text);
					break;
				default:
					$this->appendCode($text);
					break;
			}
		}
		return $this->code;
	}
	private function mergeEqualsWithReference($source) {
		$this->tkns = token_get_all($source);
		$this->code = '';
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->getToken($token);
			$this->ptr = $index;
			switch ($id) {
				case ST_REFERENCE:
					if ($this->leftUsefulTokenIs(ST_EQUAL)) {
						$this->rtrimAndAppendCode($text . $this->getSpace());
						break;
					}
				default:
					$this->appendCode($text);
			}
		}
		return $this->code;
	}
	private function removeSpaceAfterCasts($source) {
		$this->tkns = token_get_all($source);
		$this->code = '';
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->getToken($token);
			$this->ptr = $index;
			switch ($id) {
				case T_ARRAY_CAST:
				case T_BOOL_CAST:
				case T_DOUBLE_CAST:
				case T_INT_CAST:
				case T_OBJECT_CAST:
				case T_STRING_CAST:
				case T_UNSET_CAST:
				case T_STRING:
				case T_VARIABLE:
				case ST_PARENTHESES_OPEN:
					if (
						$this->leftUsefulTokenIs([
							T_ARRAY_CAST,
							T_BOOL_CAST,
							T_DOUBLE_CAST,
							T_INT_CAST,
							T_OBJECT_CAST,
							T_STRING_CAST,
							T_UNSET_CAST,
						])
					) {
						$this->rtrimAndAppendCode($text);
						break;
					}
				default:
					$this->appendCode($text);
					break;
			}
		}
		return $this->code;
	}
	private function addUnderscoresBeforeName($source) {
		$this->tkns = token_get_all($source);
		$this->code = '';
		$level_touched = null;
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->getToken($token);
			$this->ptr = $index;
			switch ($id) {
				case T_PUBLIC:
				case T_PRIVATE:
				case T_PROTECTED:
					$level_touched = $id;
					$this->appendCode($text);
					break;

				case T_VARIABLE:
					if (null !== $level_touched && $this->leftUsefulTokenIs([T_PUBLIC, T_PROTECTED, T_PRIVATE, T_STATIC])) {
						$text = str_replace('$_', '$', $text);
						$text = str_replace('$_', '$', $text);
						if (T_PROTECTED == $level_touched) {
							$text = str_replace('$', '$_', $text);
						} elseif (T_PRIVATE == $level_touched) {
							$text = str_replace('$', '$__', $text);
						}
					}
					$this->appendCode($text);
					$level_touched = null;
					break;
				case T_STRING:
					if (
						null !== $level_touched &&
						$this->leftUsefulTokenIs(T_FUNCTION) &&
						'_' != $text &&
						'__' != $text &&
						'__construct' != $text &&
						'__destruct' != $text &&
						'__call' != $text &&
						'__callStatic' != $text &&
						'__get' != $text &&
						'__set' != $text &&
						'__isset' != $text &&
						'__unset' != $text &&
						'__sleep' != $text &&
						'__wakeup' != $text &&
						'__toString' != $text &&
						'__invoke' != $text &&
						'__set_state' != $text &&
						'__clone' != $text &&
						' __debugInfo' != $text
					) {
						if (substr($text, 0, 2) == '__') {
							$text = substr($text, 2);
						}
						if (substr($text, 0, 1) == '_') {
							$text = substr($text, 1);
						}
						if (T_PROTECTED == $level_touched) {
							$text = '_' . $text;
						} elseif (T_PRIVATE == $level_touched) {
							$text = '__' . $text;
						}
					}
					$this->appendCode($text);
					$level_touched = null;
					break;
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
		return 'Applies CakePHP Coding Style';
	}

	/**
	 * @codeCoverageIgnore
	 */
	public function getExample() {
		return <<<'EOT'
<?php
namespace A;

class A {
	private $__a;
	protected $_b;
	public $c;

	public function b() {
		if($a) {
			noop();
		} else {
			noop();
		}
	}

	protected function _c() {
		if($a) {
			noop();
		} else {
			noop();
		}
	}
}
?>
EOT;
	}
}
;
class EncapsulateNamespaces extends AdditionalPass {
	public function candidate($source, $foundTokens) {
		if (isset($foundTokens[T_NAMESPACE])) {
			return true;
		}

		return false;
	}
	public function format($source) {
		$this->tkns = token_get_all($source);
		$this->code = '';
		$in_namespace_context = false;
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->getToken($token);
			$this->ptr = $index;
			switch ($id) {
				case T_NAMESPACE:
					$this->appendCode($text);
					list($foundId, $foundText) = $this->printAndStopAt([ST_CURLY_OPEN, ST_SEMI_COLON]);
					if (ST_CURLY_OPEN == $foundId) {
						$this->appendCode($foundText);
						$this->printCurlyBlock();
					} elseif (ST_SEMI_COLON == $foundId) {
						$in_namespace_context = true;
						$this->appendCode(ST_CURLY_OPEN);
						list($foundId, $foundText) = $this->printAndStopAt([T_NAMESPACE, T_CLOSE_TAG]);
						if (T_CLOSE_TAG == $foundId) {
							return $source;
						}
						$this->appendCode($this->getCrlf() . ST_CURLY_CLOSE . $this->getCrlf());
						prev($this->tkns);
						continue;
					}
					break;
				default:
					$this->appendCode($text);
			}
		}

		return $this->code;
	}

	/**
	 * @codeCoverageIgnore
	 */
	public function getDescription() {
		return 'Encapsulate namespaces with curly braces';
	}

	/**
	 * @codeCoverageIgnore
	 */
	public function getExample() {
		return <<<'EOT'
<?php
namespace NS1;
class A {
}
?>
to
<?php
namespace NS1 {
	class A {
	}
}
?>
EOT;
	}
}
;
final class GeneratePHPDoc extends AdditionalPass {
	public function candidate($source, $foundTokens) {
		return true;
	}
	public function format($source) {
		$this->tkns = token_get_all($source);
		$this->code = '';
		$touchedVisibility = false;
		$touchedDocComment = false;
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->getToken($token);
			$this->ptr = $index;
			switch ($id) {
				case T_DOC_COMMENT:
					$touchedDocComment = true;
				case T_FINAL:
				case T_ABSTRACT:
				case T_PUBLIC:
				case T_PROTECTED:
				case T_PRIVATE:
				case T_STATIC:
					if (!$this->leftTokenIs([T_FINAL, T_PUBLIC, T_PROTECTED, T_PRIVATE, T_STATIC, T_ABSTRACT])) {
						$touchedVisibility = true;
						$visibilityIdx = $this->ptr;
					}
				case T_FUNCTION:
					if ($touchedDocComment) {
						$touchedDocComment = false;
						break;
					}
					if (!$touchedVisibility) {
						$origIdx = $this->ptr;
					} else {
						$origIdx = $visibilityIdx;
					}
					list($ntId, $ntText) = $this->getToken($this->rightToken());
					if (T_STRING != $ntId) {
						$this->appendCode($text);
						break;
					}
					$this->walkUntil(ST_PARENTHESES_OPEN);
					$paramStack = [];
					$tmp = ['type' => '', 'name' => ''];
					$count = 1;
					while (list($index, $token) = each($this->tkns)) {
						list($id, $text) = $this->getToken($token);
						$this->ptr = $index;

						if (ST_PARENTHESES_OPEN == $id) {
							++$count;
						}
						if (ST_PARENTHESES_CLOSE == $id) {
							--$count;
						}
						if (0 == $count) {
							break;
						}
						if (T_STRING == $id || T_NS_SEPARATOR == $id) {
							$tmp['type'] .= $text;
							continue;
						}
						if (T_VARIABLE == $id) {
							if ($this->rightTokenIs([ST_EQUAL]) && $this->walkUntil(ST_EQUAL) && $this->rightTokenIs([T_ARRAY])) {
								$tmp['type'] = 'array';
							}
							$tmp['name'] = $text;
							$paramStack[] = $tmp;
							$tmp = ['type' => '', 'name' => ''];
							continue;
						}
					}

					$returnStack = '';
					if (!$this->leftUsefulTokenIs(ST_SEMI_COLON)) {
						$this->walkUntil(ST_CURLY_OPEN);
						$count = 1;
						while (list($index, $token) = each($this->tkns)) {
							list($id, $text) = $this->getToken($token);
							$this->ptr = $index;

							if (ST_CURLY_OPEN == $id) {
								++$count;
							}
							if (ST_CURLY_CLOSE == $id) {
								--$count;
							}
							if (0 == $count) {
								break;
							}
							if (T_RETURN == $id) {
								if ($this->rightTokenIs([T_DNUMBER])) {
									$returnStack = 'float';
								} elseif ($this->rightTokenIs([T_LNUMBER])) {
									$returnStack = 'int';
								} elseif ($this->rightTokenIs([T_VARIABLE])) {
									$returnStack = 'mixed';
								} elseif ($this->rightTokenIs([ST_SEMI_COLON])) {
									$returnStack = 'null';
								}
							}
						}
					}

					$func_token = &$this->tkns[$origIdx];
					$func_token[1] = $this->renderDocBlock($paramStack, $returnStack) . $func_token[1];
					$touchedVisibility = false;
			}
		}

		return implode('', array_map(function ($token) {
			list(, $text) = $this->getToken($token);
			return $text;
		}, $this->tkns));
	}

	private function renderDocBlock(array $paramStack, $returnStack) {
		if (empty($paramStack) && empty($returnStack)) {
			return '';
		}
		$str = '/**' . $this->newLine;
		foreach ($paramStack as $param) {
			$str .= rtrim(' * @param ' . $param['type']) . ' ' . $param['name'] . $this->newLine;
		}
		if (!empty($returnStack)) {
			$str .= ' * @return ' . $returnStack . $this->newLine;
		}
		$str .= ' */' . $this->newLine;
		return $str;
	}

	/**
	 * @codeCoverageIgnore
	 */
	public function getDescription() {
		return 'Automatically generates PHPDoc blocks';
	}

	/**
	 * @codeCoverageIgnore
	 */
	public function getExample() {
		return <<<'EOT'
<?php
class A {
	function a(Someclass $a) {
		return 1;
	}
}
?>
to
<?php
class A {
	/**
	 * @param Someclass $a
	 * @return int
	 */
	function a(Someclass $a) {
		return 1;
	}
}
?>
EOT;
	}
}
;
class JoinToImplode extends AdditionalPass {
	public function candidate($source, $foundTokens) {
		$this->tkns = token_get_all($source);
		$this->code = '';

		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->getToken($token);
			$this->ptr = $index;
			switch ($id) {
				case T_STRING:
					if (strtolower($text) == 'join') {
						prev($this->tkns);
						return true;
					}
			}
			$this->appendCode($text);
		}
		return false;
	}
	public function format($source) {
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->getToken($token);
			$this->ptr = $index;
			if (T_STRING == $id && strtolower($text) == 'join' && !($this->leftUsefulTokenIs([T_NEW, T_NS_SEPARATOR, T_STRING, T_DOUBLE_COLON, T_OBJECT_OPERATOR, T_FUNCTION]) || $this->rightUsefulTokenIs([T_NS_SEPARATOR, T_DOUBLE_COLON]))) {
				$this->appendCode('implode');
				continue;
			}
			$this->appendCode($text);
		}

		return $this->code;
	}

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
;
class LaravelStyle extends AdditionalPass {
	private $foundTokens;
	public function candidate($source, $foundTokens) {
		$this->foundTokens = $foundTokens;
		return true;
	}

	public function format($source) {
		$source = $this->namespaceMergeWithOpenTag($source);
		$source = $this->allmanStyleBraces($source);
		$source = (new RTrim())->format($source);

		$fmt = new TightConcat();
		if ($fmt->candidate($source, $this->foundTokens)) {
			$source = $fmt->format($source);
		}
		$fmt = new NormalizeLnAndLtrimLines();
		if ($fmt->candidate($source, $this->foundTokens)) {
			$source = $fmt->format($source);
		}
		$fmt = new Reindent();
		if ($fmt->candidate($source, $this->foundTokens)) {
			$source = $fmt->format($source);
		}
		$fmt = new LeftAlignComment();
		if ($fmt->candidate($source, $this->foundTokens)) {
			$source = $fmt->format($source);
		}

		$source = (new RTrim())->format($source);
		return $source;
	}

	private function namespaceMergeWithOpenTag($source) {
		$this->tkns = token_get_all($source);
		$this->code = '';
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->getToken($token);
			$this->ptr = $index;
			switch ($id) {
				case T_NAMESPACE:
					if ($this->leftTokenIs(T_OPEN_TAG)) {
						$this->rtrimAndAppendCode($this->getSpace() . $text);
						break;
					}
				default:
					$this->appendCode($text);
			}
		}

		return $this->code;
	}

	private function allmanStyleBraces($source) {
		$this->tkns = token_get_all($source);
		$this->code = '';
		$max_detected_indent = 0;
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->getToken($token);
			$this->ptr = $index;
			switch ($id) {
				case ST_CURLY_OPEN:
					if ($this->leftUsefulTokenIs([ST_PARENTHESES_CLOSE, T_ELSE, T_FINALLY])) {
						list($prevId, $prevText) = $this->getToken($this->leftToken());
						if (!$this->hasLn($prevText)) {
							$this->appendCode($this->getCrlf());
						}
					}
					$this->appendCode($text);
					break;

				case T_ELSE:
				case T_ELSEIF:
				case T_FINALLY:
					list($prevId, $prevText) = $this->getToken($this->leftToken());
					if (!$this->hasLn($prevText) && T_OPEN_TAG != $prevId) {
						$this->appendCode($this->getCrlf());
					}
					$this->appendCode($text);
					break;
				case T_CATCH:
					if (' ' == substr($this->code, -1, 1)) {
						$this->code = substr($this->code, 0, -1);
					}
					list($prevId, $prevText) = $this->getToken($this->leftToken());
					if (!$this->hasLn($prevText)) {
						$this->appendCode($this->getCrlf());
					}
					$this->appendCode($text);
					break;
				default:
					$this->appendCode($text);
			}
		}

		return $this->code;
	}

	/**
	 * @codeCoverageIgnore
	 */
	public function getDescription() {
		return 'Applies Laravel Coding Style';
	}

	/**
	 * @codeCoverageIgnore
	 */
	public function getExample() {
		return <<<'EOT'
<?php namespace A;

class A {
	function b()
	{
		if($a)
		{
			noop();
		}
		else
		{
			noop();
		}
	}

}
?>
EOT;
	}
}
;
/**
 * From PHP-CS-Fixer
 */
class MergeElseIf extends AdditionalPass {
	public function candidate($source, $foundTokens) {
		if (isset($foundTokens[T_ELSE])) {
			return true;
		}

		return false;
	}
	public function format($source) {
		$this->tkns = token_get_all($source);
		$this->code = '';
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->getToken($token);
			$this->ptr = $index;
			switch ($id) {
				case T_IF:
					if ($this->leftTokenIs([T_ELSE]) && !$this->leftTokenIs([T_OPEN_TAG, T_OPEN_TAG_WITH_ECHO])) {
						$this->rtrimAndAppendCode($text);
						break;
					}
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
		return 'Merge if with else. ';
	}

	/**
	 * @codeCoverageIgnore
	 */
	public function getExample() {
		return <<<'EOT'
<?php
if($a){

} else if($b) {

}

if($a){

} elseif($b) {

}
?>
EOT;
	}
}
;
class MergeNamespaceWithOpenTag extends AdditionalPass {
	public function candidate($source, $foundTokens) {
		if (isset($foundTokens[T_NAMESPACE])) {
			return true;
		}

		return false;
	}
	public function format($source) {
		$this->tkns = token_get_all($source);
		$this->code = '';
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->getToken($token);
			$this->ptr = $index;
			switch ($id) {
				case T_NAMESPACE:
					if ($this->leftTokenIs(T_OPEN_TAG)) {
						$this->rtrimAndAppendCode($this->newLine . $text);
						break 2;
					}

				default:
					$this->appendCode($text);
					break;
			}
		}
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->getToken($token);
			$this->appendCode($text);
		}
		return $this->code;
	}
	/**
	 * @codeCoverageIgnore
	 */
	public function getDescription() {
		return 'Ensure there is no more than one linebreak before namespace';
	}

	/**
	 * @codeCoverageIgnore
	 */
	public function getExample() {
		return <<<'EOT'
<?php

namespace A;
?>
to
<?php
namespace A;
?>
EOT;
	}
}
;
class MildAutoPreincrement extends AutoPreincrement {
	protected $candidateTokens = [];
	protected $checkAgainstConcat = true;
};
class NoSpaceAfterPHPDocBlocks extends FormatterPass {
	public function candidate($source, $foundTokens) {
		if (isset($foundTokens[T_DOC_COMMENT])) {
			return true;
		}

		return false;
	}
	public function format($source) {
		$this->tkns = token_get_all($source);
		$this->code = '';
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->getToken($token);
			$this->ptr = $index;
			switch ($id) {
				case T_WHITESPACE:
					if ($this->hasLn($text) && $this->leftTokenIs(T_DOC_COMMENT)) {
						$text = substr(strrchr($text, 10), 0);
						$this->appendCode($text);
						break;
					}
				default:
					$this->appendCode($text);
			}
		}

		return $this->code;
	}

	/**
	 * @codeCoverageIgnore
	 */
	public function getDescription() {
		return 'Remove empty lines after PHPDoc blocks.';
	}

	/**
	 * @codeCoverageIgnore
	 */
	public function getExample() {
		return <<<'EOT'
<?php
/**
 * @param int $myInt
 */

function a($myInt){
}

/**
 * @param int $myInt
 */
function a($myInt){
}
?>
EOT;
	}
};
final class OrderMethod extends AdditionalPass {
	const OPENER_PLACEHOLDER = "<?php /*\x2 ORDERMETHOD \x3*/";
	const METHOD_REPLACEMENT_PLACEHOLDER = "\x2 METHODPLACEHOLDER \x3";

	public function orderMethods($source) {
		$tokens = token_get_all($source);
		$return = '';
		$functionList = [];
		while (list($index, $token) = each($tokens)) {
			list($id, $text) = $this->getToken($token);
			$this->ptr = $index;
			switch ($id) {
				case T_ABSTRACT:
				case T_STATIC:
				case T_PRIVATE:
				case T_PROTECTED:
				case T_PUBLIC:
					$stack = $text;
					$curlyCount = null;
					$touchedMethod = false;
					$functionName = '';
					while (list($index, $token) = each($tokens)) {
						list($id, $text) = $this->getToken($token);
						$this->ptr = $index;

						$stack .= $text;
						if (T_FUNCTION == $id) {
							$touchedMethod = true;
						}
						if (T_VARIABLE == $id && !$touchedMethod) {
							break;
						}
						if (T_STRING == $id && $touchedMethod && empty($functionName)) {
							$functionName = $text;
						}

						if (null === $curlyCount && ST_SEMI_COLON == $id) {
							break;
						}

						if (ST_CURLY_OPEN == $id) {
							++$curlyCount;
						}
						if (ST_CURLY_CLOSE == $id) {
							--$curlyCount;
						}
						if (0 === $curlyCount) {
							break;
						}
					}
					if (!$touchedMethod) {
						$return .= $stack;
					} else {
						$functionList[$functionName] = $stack;
						$return .= self::METHOD_REPLACEMENT_PLACEHOLDER;
					}
					break;
				default:
					$return .= $text;
					break;
			}
		}
		ksort($functionList);
		foreach ($functionList as $functionBody) {
			$return = preg_replace('/' . self::METHOD_REPLACEMENT_PLACEHOLDER . '/', $functionBody, $return, 1);
		}
		return $return;
	}

	public function candidate($source, $foundTokens) {
		return true;
	}

	public function format($source) {
		$this->tkns = token_get_all($source);
		$return = '';
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->getToken($token);
			$this->ptr = $index;
			switch ($id) {
				case T_CLASS:
					$return .= $text;
					while (list($index, $token) = each($this->tkns)) {
						list($id, $text) = $this->getToken($token);
						$this->ptr = $index;
						$return .= $text;
						if (ST_CURLY_OPEN == $id) {
							break;
						}
					}
					$classBlock = '';
					$curlyCount = 1;
					while (list($index, $token) = each($this->tkns)) {
						list($id, $text) = $this->getToken($token);
						$this->ptr = $index;
						$classBlock .= $text;
						if (ST_CURLY_OPEN == $id) {
							++$curlyCount;
						} elseif (ST_CURLY_CLOSE == $id) {
							--$curlyCount;
						}

						if (0 == $curlyCount) {
							break;
						}
					}
					$return .= str_replace(
						self::OPENER_PLACEHOLDER,
						'',
						$this->orderMethods(self::OPENER_PLACEHOLDER . $classBlock)
					);
					$this->appendCode($return);
					break;
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
		return 'Sort methods within class in alphabetic order.';
	}

	/**
	 * @codeCoverageIgnore
	 */
	public function getExample() {
		return <<<'EOT'
<?php
class A {
	function b(){}
	function c(){}
	function a(){}
}
?>
to
<?php
class A {
	function a(){}
	function b(){}
	function c(){}
}
?>
EOT;
	}
}
;
class PrettyPrintDocBlocks extends AdditionalPass {
	public function candidate($source, $foundTokens) {
		if (isset($foundTokens[T_DOC_COMMENT])) {
			return true;
		}

		return false;
	}
	public function format($source) {
		$this->tkns = token_get_all($source);
		$this->code = '';
		$touchedNamespace = false;
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->getToken($token);
			$this->ptr = $index;
			if (T_DOC_COMMENT == $id) {
				$text = $this->prettify($text);
			}
			$this->appendCode($text);
		}

		return $this->code;
	}

	private function prettify($docBlock) {
		$isUTF8 = $this->isUTF8($docBlock);

		if ($isUTF8) {
			$docBlock = utf8_decode($docBlock);
		}

		$groups = [
			'@deprecated' => 1,
			'@link' => 1,
			'@see' => 1,
			'@since' => 1,

			'@author' => 2,
			'@copyright' => 2,
			'@license' => 2,

			'@package' => 3,
			'@subpackage' => 3,

			'@param' => 4,
			'@throws' => 4,
			'@return' => 4,
		];
		$weights = [
			'@package' => 1,
			'@subpackage' => 2,
			'@author' => 3,
			'@copyright' => 4,
			'@license' => 5,
			'@deprecated' => 6,
			'@link' => 7,
			'@see' => 8,
			'@since' => 9,
			'@param' => 10,
			'@throws' => 11,
			'@return' => 12,
		];
		$weightsLen = [
			'@package' => strlen('@package'),
			'@subpackage' => strlen('@subpackage'),
			'@author' => strlen('@author'),
			'@copyright' => strlen('@copyright'),
			'@license' => strlen('@license'),
			'@deprecated' => strlen('@deprecated'),
			'@link' => strlen('@link'),
			'@see' => strlen('@see'),
			'@since' => strlen('@since'),
			'@param' => strlen('@param'),
			'@throws' => strlen('@throws'),
			'@return' => strlen('@return'),
		];

		// Strip envelope
		$docBlock = trim(str_replace(['/**', '*/'], '', $docBlock));
		$lines = explode($this->newLine, $docBlock);
		$newText = '';
		foreach ($lines as $idx => $v) {
			$v = ltrim($v);
			if ('* ' === substr($v, 0, 2)) {
				$v = substr($v, 2);
			}
			if ('*' === substr($v, 0, 1)) {
				$v = substr($v, 1);
			}
			$lines[$idx] = $v . ':' . $idx;
		}

		// Sort lines
		usort($lines, function ($a, $b) use ($weights, $weightsLen) {
			$weightA = 0;
			foreach ($weights as $pattern => $weight) {
				if (strtolower(substr(ltrim($a), 0, $weightsLen[$pattern])) == $pattern) {
					$weightA = $weight;
					break;
				}
			}

			$weightB = 0;
			foreach ($weights as $pattern => $weight) {
				if (strtolower(substr(ltrim($b), 0, $weightsLen[$pattern])) == $pattern) {
					$weightB = $weight;
					break;
				}
			}

			if ($weightA == $weightB) {
				$weightA = substr(strrchr($a, ":"), 1);
				$weightB = substr(strrchr($b, ":"), 1);
			}
			return $weightA - $weightB;
		});

		// Align tags
		$patterns = [
			'@param' => strlen('@param'),
			'@throws' => strlen('@throws'),
			'@return' => strlen('@return'),
			'@var' => strlen('@var'),
			'@type' => strlen('@type'),
		];
		$alignableIdx = [];
		$maxColumn = [];

		foreach ($lines as $idx => $line) {
			foreach ($patterns as $pattern => $len) {
				if (strtolower(substr(ltrim($line), 0, $len)) == $pattern) {
					$words = explode(' ', $line);
					$i = 0;
					foreach ($words as $word) {
						if (!trim($word)) {
							continue;
						}
						$maxColumn[$i] = isset($maxColumn[$i]) ? max($maxColumn[$i], strlen($word)) : strlen($word);
						if (2 == $i) {
							break;
						}
						++$i;
					}
				}
			}
		}

		foreach ($lines as $idx => $line) {
			foreach ($patterns as $pattern => $len) {
				if (strtolower(substr(ltrim($line), 0, $len)) == $pattern) {
					$words = explode(' ', $line);
					$currentLine = '';
					$pad = 0;
					foreach ($maxColumn as $rightMost) {
						while ((list(, $word) = each($words))) {
							if (trim($word)) {
								break;
							}
						}

						$currentLine .= $word;
						$pad += $rightMost + 1;
						$currentLine = str_pad($currentLine, $pad);
					}

					while ((list(, $word) = each($words))) {
						$currentLine .= $word . ' ';
					}
					$lines[$idx] = rtrim($currentLine);
				}
			}
		}

		// Space lines
		$lastGroup = null;
		foreach ($lines as $idx => $line) {
			if ('@' == substr(ltrim($line), 0, 1)) {
				$tag = strtolower(substr($line, 0, strpos($line, ' ')));
				if (isset($groups[$tag]) && $groups[$tag] != $lastGroup) {
					$lines[$idx] = (null !== $lastGroup ? $this->newLine . ' * ' : '') . $line;
					$lastGroup = $groups[$tag];
				}
			}
		}

		// Output
		$docBlock = '/**' . $this->newLine;
		foreach ($lines as $line) {
			$docBlock .= ' * ' . substr(rtrim($line), 0, strrpos($line, ':')) . $this->newLine;
		}
		$docBlock .= ' */';

		if ($isUTF8) {
			$docBlock = utf8_encode($docBlock);
		}

		return $docBlock;
	}

	private function isUTF8($usStr) {
		return (utf8_encode(utf8_decode($usStr)) == $usStr);
	}

	/**
	 * @codeCoverageIgnore
	 */
	public function getDescription() {
		return 'Prettify Doc Blocks';
	}

	/**
	 * @codeCoverageIgnore
	 */
	public function getExample() {
		return <<<'EOT'
<?php
/**
 * some description.
 * @param array $b
 * @param LongTypeName $c
 */
function A(array $b, LongTypeName $c) {
}
?>

to
<?php
/**
 * some description.
 * @param array        $b
 * @param LongTypeName $c
 */
function A(array $b, LongTypeName $c) {
}
?>
EOT;
	}
};
class RemoveUseLeadingSlash extends AdditionalPass {
	public function candidate($source, $foundTokens) {
		if (isset($foundTokens[T_NAMESPACE]) || isset($foundTokens[T_TRAIT]) || isset($foundTokens[T_CLASS]) || isset($foundTokens[T_FUNCTION]) || isset($foundTokens[T_NS_SEPARATOR])) {
			return true;
		}

		return false;
	}
	public function format($source) {
		$this->tkns = token_get_all($source);
		$this->code = '';
		$lastTouchedToken = null;
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->getToken($token);
			$this->ptr = $index;
			switch ($id) {
				case T_NAMESPACE:
				case T_TRAIT:
				case T_CLASS:
				case T_FUNCTION:
					$lastTouchedToken = $id;
				case T_NS_SEPARATOR:
					if (T_NAMESPACE == $lastTouchedToken && $this->leftTokenIs([T_USE])) {
						continue;
					}
				default:
					$this->appendCode($text);
			}
		}

		return $this->code;
	}

	/**
	 * @codeCoverageIgnore
	 */
	public function getDescription() {
		return 'Remove leading slash in T_USE imports.';
	}

	/**
	 * @codeCoverageIgnore
	 */
	public function getExample() {
		return <<<'EOT'
<?php
namespace NS1;
use \B;
use \D;

new B();
new D();
?>
to
<?php
namespace NS1;
use B;
use D;

new B();
new D();
?>
EOT;
	}
}
;
class ReturnNull extends AdditionalPass {
	public function candidate($source, $foundTokens) {
		if (isset($foundTokens[T_RETURN])) {
			return true;
		}

		return false;
	}
	public function format($source) {
		$this->tkns = token_get_all($source);
		$this->code = '';
		$this->useCache = true;
		$touchedReturn = false;
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->getToken($token);
			$this->ptr = $index;
			$this->cache = [];

			if (ST_PARENTHESES_OPEN == $id && $this->leftTokenIs([T_RETURN])) {
				$parenCount = 1;
				$touchedAnotherValidToken = false;
				$stack = $text;
				while (list($index, $token) = each($this->tkns)) {
					list($id, $text) = $this->getToken($token);
					$this->ptr = $index;
					$this->cache = [];
					if (ST_PARENTHESES_OPEN == $id) {
						++$parenCount;
					}
					if (ST_PARENTHESES_CLOSE == $id) {
						--$parenCount;
					}
					$stack .= $text;
					if (0 == $parenCount) {
						break;
					}
					if (
						!(
							(T_STRING == $id && strtolower($text) == 'null') ||
							ST_PARENTHESES_OPEN == $id ||
							ST_PARENTHESES_CLOSE == $id
						)
					) {
						$touchedAnotherValidToken = true;
					}
				}
				if ($touchedAnotherValidToken) {
					$this->appendCode($stack);
				}
				continue;
			}
			if (T_STRING == $id && strtolower($text) == 'null') {
				list($prevId, ) = $this->leftUsefulToken();
				list($nextId, ) = $this->rightUsefulToken();
				if (T_RETURN == $prevId && ST_SEMI_COLON == $nextId) {
					continue;
				}
			}

			$this->appendCode($text);
		}

		return $this->code;
	}

	/**
	 * @codeCoverageIgnore
	 */
	public function getDescription() {
		return 'Simplify empty returns.';
	}

	/**
	 * @codeCoverageIgnore
	 */
	public function getExample() {
		return <<<'EOT'
<?php
function a(){
	return null;
}
?>
to
<?php
function a(){
	return;
}
?>
EOT;
	}
}
;
/**
 * From PHP-CS-Fixer
 */
class ShortArray extends AdditionalPass {
	const FOUND_ARRAY = 'array';
	const FOUND_PARENTHESES = 'paren';
	public function candidate($source, $foundTokens) {
		if (isset($foundTokens[T_ARRAY])) {
			return true;
		}

		return false;
	}
	public function format($source) {
		$this->tkns = token_get_all($source);
		$this->code = '';
		$found_paren = [];
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->getToken($token);
			$this->ptr = $index;
			switch ($id) {
				case T_ARRAY:
					if ($this->rightTokenIs([ST_PARENTHESES_OPEN])) {
						$found_paren[] = self::FOUND_ARRAY;
						$this->printAndStopAt(ST_PARENTHESES_OPEN);
						$this->appendCode(ST_BRACKET_OPEN);
						break;
					}
				case ST_PARENTHESES_OPEN:
					$found_paren[] = self::FOUND_PARENTHESES;
					$this->appendCode($text);
					break;

				case ST_PARENTHESES_CLOSE:
					$popToken = array_pop($found_paren);
					if (self::FOUND_ARRAY == $popToken) {
						$this->appendCode(ST_BRACKET_CLOSE);
						break;
					}
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
		return 'Convert old array into new array. (array() -> [])';
	}

	/**
	 * @codeCoverageIgnore
	 */
	public function getExample() {
		return <<<'EOT'
<?php
echo array();
?>
to
<?php
echo [];
?>
EOT;
	}
}
;
final class SmartLnAfterCurlyOpen extends AdditionalPass {
	public function candidate($source, $foundTokens) {
		if (isset($foundTokens[ST_CURLY_OPEN])) {
			return true;
		}

		return false;
	}
	public function format($source) {
		$this->tkns = token_get_all($source);
		$this->code = '';
		$curlyCount = 0;
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->getToken($token);
			$this->ptr = $index;
			switch ($id) {
				case ST_CURLY_OPEN:
					$this->appendCode($text);
					$curlyCount = 1;
					$stack = '';
					$foundLineBreak = false;
					$hasLnAfter = $this->hasLnAfter();
					while (list($index, $token) = each($this->tkns)) {
						list($id, $text) = $this->getToken($token);
						$this->ptr = $index;
						$stack .= $text;
						if (T_START_HEREDOC == $id) {
							$stack .= $this->walkAndAccumulateUntil($this->tkns, T_END_HEREDOC);
							continue;
						}
						if (ST_QUOTE == $id) {
							$stack .= $this->walkAndAccumulateUntil($this->tkns, ST_QUOTE);
							continue;
						}
						if (ST_CURLY_OPEN == $id) {
							++$curlyCount;
						}
						if (ST_CURLY_CLOSE == $id) {
							--$curlyCount;
						}
						if (T_WHITESPACE === $id && $this->hasLn($text)) {
							$foundLineBreak = true;
							break;
						}
						if (0 == $curlyCount) {
							break;
						}
					}
					if ($foundLineBreak && !$hasLnAfter) {
						$this->appendCode($this->newLine);
					}
					$this->appendCode($stack);
					break;
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
		return 'Add line break when implicit curly block is added.';
	}

	/**
	 * @codeCoverageIgnore
	 */
	public function getExample() {
		return <<<'EOT'
<?php
if($a) echo array();
?>
to
<?php
if($a) {
	echo array();
}
?>
EOT;
	}
}
;
class SpaceBetweenMethods extends AdditionalPass {
	public function candidate($source, $foundTokens) {
		if (isset($foundTokens[T_FUNCTION])) {
			return true;
		}

		return false;
	}

	public function format($source) {
		$this->tkns = token_get_all($source);
		$this->code = '';
		$lastTouchedToken = null;
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->getToken($token);
			$this->ptr = $index;
			switch ($id) {
				case T_FUNCTION:
					$this->appendCode($text);
					$this->printUntil(ST_CURLY_OPEN);
					$this->printCurlyBlock();
					if (!$this->rightTokenIs([ST_CURLY_CLOSE, ST_SEMI_COLON, ST_COMMA, ST_PARENTHESES_CLOSE])) {
						$this->appendCode($this->getCrlf());
					}
					break;
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
		return 'Put space between methods.';
	}

	/**
	 * @codeCoverageIgnore
	 */
	public function getExample() {
		return <<<'EOT'
<?php
class A {
	function b(){

	}
	function c(){

	}
}
?>
to
<?php
class A {
	function b(){

	}

	function c(){

	}

}
?>
EOT;
	}
}
;
final class StripExtraCommaInArray extends AdditionalPass {
	const ST_SHORT_ARRAY_OPEN = 'SHORT_ARRAY_OPEN';
	const EMPTY_ARRAY = 'ST_EMPTY_ARRAY';

	public function candidate($source, $foundTokens) {
		return true;
	}

	public function format($source) {
		$this->tkns = token_get_all($source);

		$contextStack = [];
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->getToken($token);
			$this->ptr = $index;
			switch ($id) {
				case ST_BRACKET_OPEN:
					if (!$this->leftTokenIs([ST_BRACKET_CLOSE, T_STRING, T_VARIABLE, T_ARRAY_CAST])) {
						$contextStack[] = self::ST_SHORT_ARRAY_OPEN;
					} else {
						$contextStack[] = ST_BRACKET_OPEN;
					}
					break;
				case ST_BRACKET_CLOSE:
					if (isset($contextStack[0]) && !$this->leftTokenIs(ST_BRACKET_OPEN)) {
						if (self::ST_SHORT_ARRAY_OPEN == end($contextStack) && $this->leftUsefulTokenIs(ST_COMMA)) {
							$prevTokenIdx = $this->leftUsefulTokenIdx();
							$this->tkns[$prevTokenIdx] = null;
						}
						array_pop($contextStack);
					}
					break;
				case T_STRING:
					if ($this->rightTokenIs(ST_PARENTHESES_OPEN)) {
						$contextStack[] = T_STRING;
					}
					break;
				case T_ARRAY:
					if ($this->rightTokenIs(ST_PARENTHESES_OPEN)) {
						$contextStack[] = T_ARRAY;
					}
					break;
				case ST_PARENTHESES_OPEN:
					if (isset($contextStack[0]) && T_ARRAY == end($contextStack) && $this->rightTokenIs(ST_PARENTHESES_CLOSE)) {
						array_pop($contextStack);
						$contextStack[] = self::EMPTY_ARRAY;
					} elseif (!$this->leftTokenIs([T_ARRAY, T_STRING])) {
						$contextStack[] = ST_PARENTHESES_OPEN;
					}
					break;
				case ST_PARENTHESES_CLOSE:
					if (isset($contextStack[0])) {
						if (T_ARRAY == end($contextStack) && $this->leftUsefulTokenIs(ST_COMMA)) {
							$prevTokenIdx = $this->leftUsefulTokenIdx();
							$this->tkns[$prevTokenIdx] = null;
						}
						array_pop($contextStack);
					}
					break;
			}
			$this->tkns[$this->ptr] = [$id, $text];
		}
		return $this->renderLight();
	}

	/**
	 * @codeCoverageIgnore
	 */
	public function getDescription() {
		return 'Remove trailing commas within array blocks';
	}

	/**
	 * @codeCoverageIgnore
	 */
	public function getExample() {
		return <<<'EOT'
<?php
// From
$a = [$a, $b, ];
$b = array($b, $c, );

// To
$a = [$a, $b];
$b = array($b, $c);
?>
EOT;
	}
};
/**
 * From PHP-CS-Fixer
 */
class StripNewlineAfterClassOpen extends AdditionalPass {
	public function candidate($source, $foundTokens) {
		if (isset($foundTokens[T_CLASS]) || isset($foundTokens[T_TRAIT])) {
			return true;
		}

		return false;
	}
	public function format($source) {
		$this->tkns = token_get_all($source);
		$this->code = '';
		$whitespaces = " \t";
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->getToken($token);
			$this->ptr = $index;
			switch ($id) {
				case T_TRAIT:
				case T_CLASS:
					if ($this->leftUsefulTokenIs(T_DOUBLE_COLON)) {
						$this->appendCode($text);
						break;
					}
					$this->appendCode($text);
					$this->printUntil(ST_CURLY_OPEN);
					list(, $text) = $this->printAndStopAt(T_WHITESPACE);
					if ($this->hasLn($text)) {
						$text = substr(strrchr($text, 10), 0);
					}
					$this->appendCode($text);
					break;

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
		return 'Strip empty lines after class opening curly brace.';
	}

	/**
	 * @codeCoverageIgnore
	 */
	public function getExample() {
		return <<<'EOT'
<?php
// From
class A {

	protected $a;
}
// To
class A {
	protected $a;
}
?>
EOT;
	}
};
class StripNewlineAfterCurlyOpen extends AdditionalPass {
	public function candidate($source, $foundTokens) {
		if (isset($foundTokens[ST_CURLY_OPEN])) {
			return true;
		}

		return false;
	}
	public function format($source) {
		$this->tkns = token_get_all($source);
		$this->code = '';
		$whitespaces = " \t";
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->getToken($token);
			$this->ptr = $index;
			switch ($id) {
				case ST_CURLY_OPEN:
					$this->appendCode($text);
					list(, $text) = $this->printAndStopAt(T_WHITESPACE);
					if ($this->hasLn($text)) {
						$text = substr(strrchr($text, 10), 0);
					}
					$this->appendCode($text);
					break;

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
		return 'Strip empty lines after opening curly brace.';
	}

	/**
	 * @codeCoverageIgnore
	 */
	public function getExample() {
		return <<<'EOT'
<?php
// From
for ($a = 0; $a < 10; $a++){

	if($a){

		// do something
	}
}
// To
for ($a = 0; $a < 10; $a++){
	if($a){
		// do something
	}
}
?>
EOT;
	}
};
class TightConcat extends AdditionalPass {
	public function candidate($source, $foundTokens) {
		if (isset($foundTokens[ST_CONCAT])) {
			return true;
		}

		return false;
	}
	public function format($source) {
		$this->tkns = token_get_all($source);
		$this->code = '';
		$whitespaces = " \t";
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->getToken($token);
			$this->ptr = $index;
			switch ($id) {
				case ST_CONCAT:
					if (!$this->leftTokenIs([T_LNUMBER, T_DNUMBER])) {
						$this->code = rtrim($this->code, $whitespaces);
					}
					if (!$this->rightTokenIs([T_LNUMBER, T_DNUMBER])) {
						each($this->tkns);
					}
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
		return 'Ensure string concatenation does not have spaces, except when close to numbers.';
	}

	/**
	 * @codeCoverageIgnore
	 */
	public function getExample() {
		return <<<'EOT'
<?php
// From
$a = 'a' . 'b';
$a = 'a' . 1 . 'b';
// To
$a = 'a'.'b';
$a = 'a'. 1 .'b';
?>
EOT;
	}
};
class WrongConstructorName extends AdditionalPass {
	public function candidate($source, $foundTokens) {
		if (isset($foundTokens[T_NAMESPACE]) || isset($foundTokens[T_CLASS])) {
			return true;
		}

		return false;
	}
	public function format($source) {
		$this->tkns = token_get_all($source);
		$this->code = '';
		$touchedNamespace = false;
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->getToken($token);
			$this->ptr = $index;
			switch ($id) {
				case T_NAMESPACE:
					$touchedNamespace = true;
					$this->appendCode($text);
					break;
				case T_CLASS:
					$this->appendCode($text);
					if ($this->leftUsefulTokenIs([T_DOUBLE_COLON])) {
						break;
					}
					if ($touchedNamespace) {
						break;
					}
					$classLocalName = '';
					while (list($index, $token) = each($this->tkns)) {
						list($id, $text) = $this->getToken($token);
						$this->ptr = $index;
						$this->appendCode($text);
						if (T_STRING == $id) {
							$classLocalName = strtolower($text);
						}
						if (T_EXTENDS == $id || T_IMPLEMENTS == $id || ST_CURLY_OPEN == $id) {
							break;
						}
					}
					$count = 1;
					while (list($index, $token) = each($this->tkns)) {
						list($id, $text) = $this->getToken($token);
						$this->ptr = $index;

						if (T_STRING == $id && $this->leftUsefulTokenIs([T_FUNCTION]) && strtolower($text) == $classLocalName) {
							$text = '__construct';
						}
						$this->appendCode($text);

						if (ST_CURLY_OPEN == $id) {
							++$count;
						}
						if (ST_CURLY_CLOSE == $id) {
							--$count;
						}
						if (0 == $count) {
							break;
						}
					}
					break;
				default:
					$this->appendCode($text);
			}
		}

		return $this->code;
	}

	/**
	 * @codeCoverageIgnore
	 */
	public function getDescription() {
		return 'Update old constructor names into new ones. http://php.net/manual/en/language.oop5.decon.php';
	}

	/**
	 * @codeCoverageIgnore
	 */
	public function getExample() {
		return <<<'EOT'
<?php
class A {
	function A(){

	}
}
?>
to
<?php
class A {
	function __construct(){

	}
}
?>
EOT;
	}
};
final class YodaComparisons extends AdditionalPass {
	const CHAIN_VARIABLE = 'CHAIN_VARIABLE';
	const CHAIN_LITERAL = 'CHAIN_LITERAL';
	const CHAIN_FUNC = 'CHAIN_FUNC';
	const CHAIN_STRING = 'CHAIN_STRING';
	const PARENTHESES_BLOCK = 'PARENTHESES_BLOCK';
	public function candidate($source, $foundTokens) {
		return true;
	}
	public function format($source) {
		return $this->yodise($source);
	}
	protected function yodise($source) {
		$tkns = $this->aggregateVariables($source);
		while (list($ptr, $token) = each($tkns)) {
			if (is_null($token)) {
				continue;
			}
			list($id, $text) = $this->getToken($token);
			switch ($id) {
				case T_IS_EQUAL:
				case T_IS_IDENTICAL:
				case T_IS_NOT_EQUAL:
				case T_IS_NOT_IDENTICAL:
					list($left, $right) = $this->siblings($tkns, $ptr);
					list($leftId, $leftText) = $tkns[$left];
					list($rightId, $rightText) = $tkns[$right];
					if ($leftId == $rightId) {
						continue;
					}

					$leftPureVariable = $this->isPureVariable($leftId);
					for ($leftmost = $left; $leftmost >= 0; --$leftmost) {
						list($leftScanId, $leftScanText) = $this->getToken($tkns[$leftmost]);
						if ($this->isLowerPrecedence($leftScanId)) {
							++$leftmost;
							break;
						}
						$leftPureVariable &= $this->isPureVariable($leftScanId);
					}

					$rightPureVariable = $this->isPureVariable($rightId);
					for ($rightmost = $right; $rightmost < sizeof($tkns) - 1; ++$rightmost) {
						list($rightScanId, $rightScanText) = $this->getToken($tkns[$rightmost]);
						if ($this->isLowerPrecedence($rightScanId)) {
							--$rightmost;
							break;
						}
						$rightPureVariable &= $this->isPureVariable($rightScanId);
					}

					if ($leftPureVariable && !$rightPureVariable) {
						$origLeftTokens = $leftTokens = implode('', array_map(function ($token) {
							return isset($token[1]) ? $token[1] : $token;
						}, array_slice($tkns, $leftmost, $left - $leftmost + 1)));
						$origRightTokens = $rightTokens = implode('', array_map(function ($token) {
							return isset($token[1]) ? $token[1] : $token;
						}, array_slice($tkns, $right, $rightmost - $right + 1)));

						$leftTokens = (substr($origRightTokens, 0, 1) == ' ' ? ' ' : '') . trim($leftTokens) . (substr($origRightTokens, -1, 1) == ' ' ? ' ' : '');
						$rightTokens = (substr($origLeftTokens, 0, 1) == ' ' ? ' ' : '') . trim($rightTokens) . (substr($origLeftTokens, -1, 1) == ' ' ? ' ' : '');

						$tkns[$leftmost] = ['REPLACED', $rightTokens];
						$tkns[$right] = ['REPLACED', $leftTokens];

						if ($leftmost != $left) {
							for ($i = $leftmost + 1; $i <= $left; ++$i) {
								$tkns[$i] = null;
							}
						}
						if ($rightmost != $right) {
							for ($i = $right + 1; $i <= $rightmost; ++$i) {
								$tkns[$i] = null;
							}
						}
					}
			}
		}
		return $this->render($tkns);
	}

	private function isPureVariable($id) {
		return self::CHAIN_VARIABLE == $id || T_VARIABLE == $id || T_INC == $id || T_DEC == $id || ST_EXCLAMATION == $id || T_COMMENT == $id || T_DOC_COMMENT == $id || T_WHITESPACE == $id;
	}
	private function isLowerPrecedence($id) {
		switch ($id) {
			case ST_REFERENCE:
			case ST_BITWISE_XOR:
			case ST_BITWISE_OR:
			case T_BOOLEAN_AND:
			case T_BOOLEAN_OR:
			case ST_QUESTION:
			case ST_COLON:
			case ST_EQUAL:
			case T_PLUS_EQUAL:
			case T_MINUS_EQUAL:
			case T_MUL_EQUAL:
			case T_POW_EQUAL:
			case T_DIV_EQUAL:
			case T_CONCAT_EQUAL:
			case T_MOD_EQUAL:
			case T_AND_EQUAL:
			case T_OR_EQUAL:
			case T_XOR_EQUAL:
			case T_SL_EQUAL:
			case T_SR_EQUAL:
			case T_DOUBLE_ARROW:
			case T_LOGICAL_AND:
			case T_LOGICAL_XOR:
			case T_LOGICAL_OR:
			case ST_COMMA:
			case ST_SEMI_COLON:
			case T_RETURN:
			case T_THROW:
			case T_GOTO:
			case T_CASE:
			case T_COMMENT:
			case T_DOC_COMMENT:
			case T_OPEN_TAG:
				return true;
		}
		return false;
	}

	private function aggregateVariables($source) {
		$tkns = token_get_all($source);
		while (list($ptr, $token) = each($tkns)) {
			list($id, $text) = $this->getToken($token);

			if (ST_PARENTHESES_OPEN == $id) {
				$initialPtr = $ptr;
				$tmp = $this->scanAndReplace($tkns, $ptr, ST_PARENTHESES_OPEN, ST_PARENTHESES_CLOSE, 'yodise', [T_IS_EQUAL, T_IS_IDENTICAL, T_IS_NOT_EQUAL, T_IS_NOT_IDENTICAL]);
				$tkns[$initialPtr] = [self::PARENTHESES_BLOCK, $tmp];
				continue;
			}
			if (ST_QUOTE == $id) {
				$stack = $text;
				$initialPtr = $ptr;
				while (list($ptr, $token) = each($tkns)) {
					list($id, $text) = $this->getToken($token);
					$stack .= $text;
					$tkns[$ptr] = null;
					if (ST_QUOTE == $id) {
						break;
					}
				}

				$tkns[$initialPtr] = [self::CHAIN_STRING, $stack];
				continue;
			}

			if (T_STRING == $id || T_VARIABLE == $id || T_NS_SEPARATOR == $id) {
				$initialIndex = $ptr;
				$stack = $text;
				$touchedVariable = false;
				if (T_VARIABLE == $id) {
					$touchedVariable = true;
				}
				if (!$this->rightTokenSubsetIsAtIdx(
					$tkns,
					$ptr,
					[T_STRING, T_VARIABLE, T_NS_SEPARATOR, T_OBJECT_OPERATOR, T_DOUBLE_COLON, ST_CURLY_OPEN, ST_PARENTHESES_OPEN, ST_BRACKET_OPEN]
				)) {
					continue;
				}
				while (list($ptr, $token) = each($tkns)) {
					list($id, $text) = $this->getToken($token);
					$tkns[$ptr] = null;
					if (ST_CURLY_OPEN == $id) {
						$text = $this->scanAndReplace($tkns, $ptr, ST_CURLY_OPEN, ST_CURLY_CLOSE, 'yodise', [T_IS_EQUAL, T_IS_IDENTICAL, T_IS_NOT_EQUAL, T_IS_NOT_IDENTICAL]);
					} elseif (ST_BRACKET_OPEN == $id) {
						$text = $this->scanAndReplace($tkns, $ptr, ST_BRACKET_OPEN, ST_BRACKET_CLOSE, 'yodise', [T_IS_EQUAL, T_IS_IDENTICAL, T_IS_NOT_EQUAL, T_IS_NOT_IDENTICAL]);
					} elseif (ST_PARENTHESES_OPEN == $id) {
						$text = $this->scanAndReplace($tkns, $ptr, ST_PARENTHESES_OPEN, ST_PARENTHESES_CLOSE, 'yodise', [T_IS_EQUAL, T_IS_IDENTICAL, T_IS_NOT_EQUAL, T_IS_NOT_IDENTICAL]);
					}

					$stack .= $text;

					if (!$touchedVariable && T_VARIABLE == $id) {
						$touchedVariable = true;
					}

					if (
						!$this->rightTokenSubsetIsAtIdx(
							$tkns,
							$ptr,
							[T_STRING, T_VARIABLE, T_NS_SEPARATOR, T_OBJECT_OPERATOR, T_DOUBLE_COLON, ST_CURLY_OPEN, ST_PARENTHESES_OPEN, ST_BRACKET_OPEN]
						)
					) {
						break;
					}
				}
				if (substr(trim($stack), -1, 1) == ST_PARENTHESES_CLOSE) {
					$tkns[$initialIndex] = [self::CHAIN_FUNC, $stack];
				} elseif ($touchedVariable) {
					$tkns[$initialIndex] = [self::CHAIN_VARIABLE, $stack];
				} else {
					$tkns[$initialIndex] = [self::CHAIN_LITERAL, $stack];
				}
			}
		}
		$tkns = array_values(array_filter($tkns));
		return $tkns;
	}

	/**
	 * @codeCoverageIgnore
	 */
	public function getDescription() {
		return 'Execute Yoda Comparisons.';
	}

	/**
	 * @codeCoverageIgnore
	 */
	public function getExample() {
		return <<<'EOT'
<?php
if($a == 1){

}
?>
to
<?php
if(1 == $a){

}
?>
EOT;
	}
};

function extractFromArgv($argv, $item) {
	return array_values(
		array_filter($argv,
			function ($v) use ($item) {
				return substr($v, 0, strlen('--' . $item)) !== '--' . $item;
			}
		)
	);
}

function extractFromArgvShort($argv, $item) {
	return array_values(
		array_filter($argv,
			function ($v) use ($item) {
				return substr($v, 0, strlen('-' . $item)) !== '-' . $item;
			}
		)
	);
}

function lint($file) {
	$output = null;
	$ret = null;
	exec('php -l ' . escapeshellarg($file), $output, $ret);
	return 0 == $ret;
}
if (!isset($in_phar)) {
	$in_phar = false;
}
if (!isset($testEnv)) {
	function show_help($argv, $enable_cache, $in_phar) {
		echo 'Usage: ' . $argv[0] . ' [-hv] [-o=FILENAME] [--config=FILENAME] ' . ($enable_cache ? '[--cache[=FILENAME]] ' : '') . '[--setters_and_getters=type] [--constructor=type] [--psr] [--psr1] [--psr1-naming] [--psr2] [--indent_with_space=SIZE] [--enable_auto_align] [--visibility_order] <target>', PHP_EOL;
		$options = [
			'--cache[=FILENAME]' => 'cache file. Default: ',
			'--cakephp' => 'Apply CakePHP coding style',
			'--config=FILENAME' => 'configuration file. Default: .php.tools.ini',
			'--constructor=type' => 'analyse classes for attributes and generate constructor - camel, snake, golang',
			'--enable_auto_align' => 'disable auto align of ST_EQUAL and T_DOUBLE_ARROW',
			'--exclude=pass1,passN' => 'disable specific passes',
			'--ignore=PATTERN1,PATTERN2' => 'ignore file names whose names contain any PATTERN-N',
			'--indent_with_space=SIZE' => 'use spaces instead of tabs for indentation. Default 4',
			'--laravel' => 'Apply Laravel coding style',
			'--lint-before' => 'lint files before pretty printing (PHP must be declared in %PATH%/$PATH)',
			'--list' => 'list possible transformations',
			'--no-backup' => 'no backup file (original.php~)',
			'--passes=pass1,passN' => 'call specific compiler pass',
			'--prepasses=pass1,passN' => 'call specific compiler pass, before the rest of stack',
			'--profile=NAME' => 'use one of profiles present in configuration file',
			'--psr' => 'activate PSR1 and PSR2 styles',
			'--psr1' => 'activate PSR1 style',
			'--psr1-naming' => 'activate PSR1 style - Section 3 and 4.3 - Class and method names case.',
			'--psr2' => 'activate PSR2 style',
			'--setters_and_getters=type' => 'analyse classes for attributes and generate setters and getters - camel, snake, golang',
			'--smart_linebreak_after_curly' => 'convert multistatement blocks into multiline blocks',
			'--visibility_order' => 'fixes visibiliy order for method in classes. PSR-2 4.2',
			'--yoda' => 'yoda-style comparisons',
			'-h, --help' => 'this help message',
			'-o=file' => 'output the formatted code to "file"',
			'-v' => 'verbose',
		];
		if ($in_phar) {
			$options['--selfupdate'] = 'self-update fmt.phar from Github';
		}
		if (!$enable_cache) {
			unset($options['--cache[=FILENAME]']);
		} else {
			$options['--cache[=FILENAME]'] .= (Cache::DEFAULT_CACHE_FILENAME);
		}
		ksort($options);
		$maxLen = max(array_map(function ($v) {
			return strlen($v);
		}, array_keys($options)));
		foreach ($options as $k => $v) {
			echo '  ', str_pad($k, $maxLen), '  ', $v, PHP_EOL;
		}
		echo PHP_EOL, 'If - is blank, it reads from stdin', PHP_EOL;
		die();
	}
	$getopt_long_options = [
		'cache::',
		'cakephp',
		'config:',
		'constructor:',
		'enable_auto_align',
		'exclude:',
		'help',
		'help-pass:',
		'ignore:',
		'indent_with_space::',
		'laravel',
		'lint-before',
		'list',
		'no-backup',
		'oracleDB::',
		'passes:',
		'prepasses:',
		'profile:',
		'psr',
		'psr1',
		'psr1-naming',
		'psr2',
		'setters_and_getters:',
		'smart_linebreak_after_curly',
		'visibility_order',
		'yoda',
	];
	if ($in_phar) {
		$getopt_long_options[] = 'selfupdate';
	}
	if (!$enable_cache) {
		unset($getopt_long_options['cache::']);
	}
	$opts = getopt(
		'ihvo:',
		$getopt_long_options
	);
	if (isset($opts['selfupdate'])) {
		$opts = [
			'http' => [
				'method' => "GET",
				'header' => "User-agent: php.tools fmt.phar selfupdate\r\n",
			],
		];

		$context = stream_context_create($opts);

		// current release
		$releases = json_decode(file_get_contents('https://api.github.com/repos/dericofilho/php.tools/tags', false, $context), true);
		$commit = json_decode(file_get_contents($releases[0]['commit']['url'], false, $context), true);
		$files = json_decode(file_get_contents($commit['commit']['tree']['url'], false, $context), true);
		foreach ($files['tree'] as $file) {
			if ('fmt.phar' == $file['path']) {
				$phar_file = base64_decode(json_decode(file_get_contents($file['url'], false, $context), true)['content']);
			}
			if ('fmt.phar.sha1' == $file['path']) {
				$phar_sha1 = base64_decode(json_decode(file_get_contents($file['url'], false, $context), true)['content']);
			}
		}
		if (!isset($phar_sha1) || !isset($phar_file)) {
			fwrite(STDERR, 'Could not autoupdate - not release found' . PHP_EOL);
			exit(255);
		}
		if ($in_phar) {
			if (!file_exists($argv[0])) {
				$argv[0] = dirname(Phar::running(false)) . DIRECTORY_SEPARATOR . $argv[0];
			}
		}
		if (sha1_file($argv[0]) != $phar_sha1) {
			copy($argv[0], $argv[0] . "~");
			file_put_contents($argv[0], $phar_file);
			chmod($argv[0], 0777 & ~umask());
			fwrite(STDERR, 'Updated successfully' . PHP_EOL);
		} else {
			fwrite(STDERR, 'Up-to-date!' . PHP_EOL);
		}
		exit(0);
	}
	if (isset($opts['config'])) {
		$argv = extractFromArgv($argv, 'config');
		if (!file_exists($opts['config']) || !is_file($opts['config'])) {
			fwrite(STDERR, 'Custom configuration not file found' . PHP_EOL);
			exit(255);
		}
		$ini_opts = parse_ini_file($opts['config'], true);
		if (!empty($ini_opts)) {
			$opts = $ini_opts;
		}
	} elseif (file_exists('.php.tools.ini') && is_file('.php.tools.ini')) {
		fwrite(STDERR, 'Configuration file found' . PHP_EOL);
		$ini_opts = parse_ini_file('.php.tools.ini', true);
		if (isset($opts['profile'])) {
			$argv = extractFromArgv($argv, 'profile');
			$profile = &$ini_opts[$opts['profile']];
			if (isset($profile)) {
				$ini_opts = $profile;
			}
		}
		$opts = array_merge($ini_opts, $opts);
	}
	if (isset($opts['h']) || isset($opts['help'])) {
		show_help($argv, $enable_cache, $in_phar);
	}

	if (isset($opts['help-pass'])) {
		$optPass = $opts['help-pass'];
		if (class_exists($optPass)) {
			$pass = new $optPass();
			echo $argv[0], ': "', $optPass, '" - ', $pass->getDescription(), PHP_EOL, PHP_EOL;
			echo 'Example:', PHP_EOL, $pass->getExample(), PHP_EOL;
		}
		die();
	}

	if (isset($opts['list'])) {
		echo 'Usage: ', $argv[0], ' --help-pass=PASSNAME', PHP_EOL;
		$classes = get_declared_classes();
		foreach ($classes as $className) {
			if (is_subclass_of($className, 'AdditionalPass')) {
				echo "\t- ", $className, PHP_EOL;
			}
		}
		die();
	}

	$cache = null;
	$cache_fn = null;
	if ($enable_cache && isset($opts['cache'])) {
		$argv = extractFromArgv($argv, 'cache');
		$cache_fn = $opts['cache'];
		$cache = new Cache($cache_fn);
		fwrite(STDERR, 'Using cache ...' . PHP_EOL);
	}
	$backup = true;
	if (isset($opts['no-backup'])) {
		$argv = extractFromArgv($argv, 'no-backup');
		$backup = false;
	}

	$ignore_list = null;
	if (isset($opts['ignore'])) {
		$argv = extractFromArgv($argv, 'ignore');
		$ignore_list = array_map(function ($v) {
			return trim($v);
		}, explode(',', $opts['ignore']));
	}

	$lintBefore = false;
	if (isset($opts['lint-before'])) {
		$argv = extractFromArgv($argv, 'lint-before');
		$lintBefore = true;
	}

	$fmt = new CodeFormatter();
	if (isset($opts['prepasses'])) {
		$optPasses = array_map(function ($v) {
			return trim($v);
		}, explode(',', $opts['prepasses']));
		foreach ($optPasses as $optPass) {
			if (class_exists($optPass)) {
				$fmt->addPass(new $optPass());
			} elseif (is_file('Additionals/' . $optPass . '.php')) {
				include 'Additionals/' . $optPass . '.php';
				$fmt->addPass(new $optPass());
			}
		}
		$argv = extractFromArgv($argv, 'prepasses');
	}
	$fmt->addPass(new TwoCommandsInSameLine());
	$fmt->addPass(new RemoveIncludeParentheses());
	$fmt->addPass(new NormalizeIsNotEquals());
	if (isset($opts['setters_and_getters'])) {
		$argv = extractFromArgv($argv, 'setters_and_getters');
		$fmt->addPass(new SettersAndGettersPass($opts['setters_and_getters']));
	}
	if (isset($opts['constructor'])) {
		$argv = extractFromArgv($argv, 'constructor');
		$fmt->addPass(new ConstructorPass($opts['constructor']));
	}
	if (isset($opts['oracleDB'])) {
		$argv = extractFromArgv($argv, 'oracleDB');
		$fmt->addPass(new AutoImportPass($opts['oracleDB']));
	}

	$fmt->addPass(new OrderUseClauses());
	$fmt->addPass(new AddMissingCurlyBraces());
	if (isset($opts['smart_linebreak_after_curly'])) {
		$fmt->addPass(new SmartLnAfterCurlyOpen());
		$argv = extractFromArgv($argv, 'smart_linebreak_after_curly');
	}
	$fmt->addPass(new ExtraCommaInArray());
	$fmt->addPass(new NormalizeLnAndLtrimLines());
	$fmt->addPass(new MergeParenCloseWithCurlyOpen());
	$fmt->addPass(new MergeCurlyCloseAndDoWhile());
	$fmt->addPass(new MergeDoubleArrowAndArray());

	if (isset($opts['yoda'])) {
		$fmt->addPass(new YodaComparisons());
		$argv = extractFromArgv($argv, 'yoda');
	}

	$fmt->addPass(new ResizeSpaces());
	$fmt->addPass(new ReindentColonBlocks());
	$fmt->addPass(new ReindentLoopColonBlocks());
	$fmt->addPass(new ReindentIfColonBlocks());

	if (isset($opts['enable_auto_align'])) {
		$fmt->addPass(new AlignEquals());
		$fmt->addPass(new AlignDoubleArrow());
		$argv = extractFromArgv($argv, 'enable_auto_align');
	}

	$fmt->addPass(new ReindentObjOps());
	$fmt->addPass(new Reindent());
	$fmt->addPass(new EliminateDuplicatedEmptyLines());

	if (isset($opts['indent_with_space'])) {
		$fmt->addPass(new PSR2IndentWithSpace($opts['indent_with_space']));
		$argv = extractFromArgv($argv, 'indent_with_space');
	}
	if (isset($opts['psr'])) {
		PsrDecorator::decorate($fmt);
		$argv = extractFromArgv($argv, 'psr');
	}
	if (isset($opts['psr1'])) {
		PsrDecorator::PSR1($fmt);
		$argv = extractFromArgv($argv, 'psr1');
	}
	if (isset($opts['psr1-naming'])) {
		PsrDecorator::PSR1Naming($fmt);
		$argv = extractFromArgv($argv, 'psr1-naming');
	}
	if (isset($opts['psr2'])) {
		PsrDecorator::PSR2($fmt);
		$argv = extractFromArgv($argv, 'psr2');
	}
	if ((isset($opts['psr1']) || isset($opts['psr2']) || isset($opts['psr'])) && isset($opts['enable_auto_align'])) {
		$fmt->addPass(new PSR2AlignObjOp());
	}

	if (isset($opts['visibility_order'])) {
		$fmt->addPass(new PSR2ModifierVisibilityStaticOrder());
		$argv = extractFromArgv($argv, 'visibility_order');
	}
	$fmt->addPass(new LeftAlignComment());
	$fmt->addPass(new RTrim());

	if (isset($opts['passes'])) {
		$optPasses = array_map(function ($v) {
			return trim($v);
		}, explode(',', $opts['passes']));
		foreach ($optPasses as $optPass) {
			if (class_exists($optPass)) {
				$fmt->addPass(new $optPass());
			} elseif (is_file('Additionals/' . $optPass . '.php')) {
				include 'Additionals/' . $optPass . '.php';
				$fmt->addPass(new $optPass());
			}
		}
		$argv = extractFromArgv($argv, 'passes');
	}

	if (isset($opts['laravel'])) {
		$fmt->addPass(new LaravelStyle());
		$argv = extractFromArgv($argv, 'laravel');
	}

	if (isset($opts['cakephp'])) {
		$fmt->addPass(new CakePHPStyle());
		$argv = extractFromArgv($argv, 'cakephp');
	}

	if (isset($opts['exclude'])) {
		$passesNames = explode(',', $opts['exclude']);
		foreach ($passesNames as $passName) {
			$fmt->removePass(trim($passName));
		}
		$argv = extractFromArgv($argv, 'exclude');
	}

	if (isset($opts['v'])) {
		$argv = extractFromArgvShort($argv, 'v');
		fwrite(STDERR, 'Used passes: ' . implode(', ', $fmt->getPassesNames()) . PHP_EOL);
	}

	if (isset($opts['i'])) {
		echo 'php.tools fmt.php interactive mode.', PHP_EOL;
		echo 'no <?php is necessary', PHP_EOL;
		echo 'type a lone "." to finish input.', PHP_EOL;
		echo 'type "quit" to finish.', PHP_EOL;
		while (true) {
			$str = '';
			do {
				$line = readline('> ');
				$str .= $line;
			} while (!('.' == $line || 'quit' == $line));
			if ('quit' == $line) {
				exit(0);
			}
			readline_add_history(substr($str, 0, -1));
			echo $fmt->formatCode('<?php ' . substr($str, 0, -1)), PHP_EOL;
		}
	} elseif (isset($opts['o'])) {
		$argv = extractFromArgvShort($argv, 'o');
		if ('-' == $opts['o'] && '-' == $argv[1]) {
			echo $fmt->formatCode(file_get_contents('php://stdin'));
			exit(0);
		}
		if ($in_phar) {
			if (!file_exists($argv[1])) {
				$argv[1] = dirname(Phar::running(false)) . DIRECTORY_SEPARATOR . $argv[1];
			}
		}
		if ('-' == $opts['o']) {
			echo $fmt->formatCode(file_get_contents($argv[1]));
			exit(0);
		}
		if (!is_file($argv[1])) {
			fwrite(STDERR, "File not found: " . $argv[1] . PHP_EOL);
			exit(255);
		}
		$argv = array_values($argv);
		file_put_contents($opts['o'], $fmt->formatCode(file_get_contents($argv[1])));
	} elseif (isset($argv[1])) {
		if ('-' == $argv[1]) {
			echo $fmt->formatCode(file_get_contents('php://stdin'));
			exit(0);
		}
		$file_not_found = false;
		$start = microtime(true);
		fwrite(STDERR, 'Formatting ...' . PHP_EOL);
		$missing_files = [];
		$file_count = 0;

		$cache_hit_count = 0;
		$workers = 4;

		for ($j = 1; $j < $argc; ++$j) {
			$arg = &$argv[$j];
			if (!isset($arg)) {
				continue;
			}
			if ($in_phar) {
				if (!file_exists($arg)) {
					$arg = dirname(Phar::running(false)) . DIRECTORY_SEPARATOR . $arg;
				}
			}
			if (is_file($arg)) {
				$file = $arg;
				if ($lintBefore && !lint($file)) {
					fwrite(STDERR, 'Error lint:' . $file . PHP_EOL);
					continue;
				}
				++$file_count;
				fwrite(STDERR, '.');
				file_put_contents($file . '-tmp', $fmt->formatCode(file_get_contents($file)));
				rename($file . '-tmp', $file);
			} elseif (is_dir($arg)) {
				fwrite(STDERR, $arg . PHP_EOL);
				$target_dir = $arg;
				$dir = new RecursiveDirectoryIterator($target_dir);
				$it = new RecursiveIteratorIterator($dir);
				$files = new RegexIterator($it, '/^.+\.php$/i', RecursiveRegexIterator::GET_MATCH);

				if ($concurrent) {

					$chn = make_channel();
					$chn_done = make_channel();
					if ($concurrent) {
						fwrite(STDERR, 'Starting ' . $workers . ' workers ...' . PHP_EOL);
					}
					for ($i = 0; $i < $workers; ++$i) {
						cofunc(function ($fmt, $backup, $cache_fn, $chn, $chn_done, $lintBefore, $id) {
							$cache = null;
							if (null !== $cache_fn) {
								$cache = new Cache($cache_fn);
							}
							$cache_hit_count = 0;
							$cache_miss_count = 0;
							while (true) {
								$msg = $chn->out();
								if (null === $msg) {
									break;
								}
								$target_dir = $msg['target_dir'];
								$file = $msg['file'];
								if (empty($file)) {
									continue;
								}
								if ($lintBefore && !lint($file)) {
									fwrite(STDERR, 'Error lint:' . $file . PHP_EOL);
									continue;
								}
								if (null !== $cache) {
									$content = $cache->is_changed($target_dir, $file);
									if (!$content) {
										++$cache_hit_count;
										continue;
									}
								} else {
									$content = file_get_contents($file);
								}
								++$cache_miss_count;
								$fmtCode = $fmt->formatCode($content);
								if (null !== $cache) {
									$cache->upsert($target_dir, $file, $fmtCode);
								}
								file_put_contents($file . '-tmp', $fmtCode);
								$backup && rename($file, $file . '~');
								rename($file . '-tmp', $file);
							}
							$chn_done->in([$cache_hit_count, $cache_miss_count]);
						}, $fmt, $backup, $cache_fn, $chn, $chn_done, $lintBefore, $i);
					}
				}
				foreach ($files as $file) {
					$file = $file[0];
					if (null !== $ignore_list) {
						foreach ($ignore_list as $pattern) {
							if (false !== strpos($file, $pattern)) {
								continue 2;
							}
						}
					}

					++$file_count;
					if ($concurrent) {
						$chn->in([
							'target_dir' => $target_dir,
							'file' => $file,
						]);
					} else {
						if (0 == ($file_count % 20)) {
							fwrite(STDERR, ' ' . $file_count . PHP_EOL);
						}
						if (null !== $cache) {
							$content = $cache->is_changed($target_dir, $file);
							if (!$content) {
								++$file_count;
								++$cache_hit_count;
								continue;
							}
						} else {
							$content = file_get_contents($file);
						}
						if ($lintBefore && !lint($file)) {
							fwrite(STDERR, 'Error lint:' . $file . PHP_EOL);
							continue;
						}
						$fmtCode = $fmt->formatCode($content);
						fwrite(STDERR, '.');
						if (null !== $cache) {
							$cache->upsert($target_dir, $file, $fmtCode);
						}
						file_put_contents($file . '-tmp', $fmtCode);
						$backup && rename($file, $file . '~');
						rename($file . '-tmp', $file);
					}

				}
				if ($concurrent) {
					for ($i = 0; $i < $workers; ++$i) {
						$chn->in(null);
					}
					for ($i = 0; $i < $workers; ++$i) {
						list($cache_hit, $cache_miss) = $chn_done->out();
						$cache_hit_count += $cache_hit;
					}
					$chn_done->close();
					$chn->close();
				}
				continue;
			} elseif (!is_file($arg)) {
				$file_not_found = true;
				$missing_files[] = $arg;
				fwrite(STDERR, '!');
			}
			if (0 == ($file_count % 20)) {
				fwrite(STDERR, ' ' . $file_count . PHP_EOL);
			}
		}
		fwrite(STDERR, PHP_EOL);
		if (null !== $cache) {
			fwrite(STDERR, ' ' . $cache_hit_count . ' files untouched (cache hit)' . PHP_EOL);
		}
		fwrite(STDERR, ' ' . $file_count . ' files total' . PHP_EOL);
		fwrite(STDERR, 'Took ' . round(microtime(true) - $start, 2) . 's' . PHP_EOL);
		if (sizeof($missing_files)) {
			fwrite(STDERR, "Files not found: " . PHP_EOL);
			foreach ($missing_files as $file) {
				fwrite(STDERR, "\t - " . $file . PHP_EOL);
			}
		}

		if ($file_not_found) {
			exit(255);
		}
	} else {
		show_help($argv, $enable_cache, $in_phar);
	}
	exit(0);
}
