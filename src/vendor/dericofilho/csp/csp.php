<?php
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

define('PHP_INT_LENGTH', strlen(sprintf('%u', PHP_INT_MAX)));
function cofunc(callable $fn) {
	$pid = pcntl_fork();
	if (-1 == $pid) {
		trigger_error('could not fork', E_ERROR);
		return;
	}
	if ($pid) {
		return;
	}
	pcntl_signal(SIGCHLD, SIG_IGN);
	$params = [];
	if (func_num_args() > 1) {
		$params = array_slice(func_get_args(), 1);
	}
	call_user_func_array($fn, $params);
	die();
}

class CSP_Channel {
	const CLOSED = '-1';
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

	public function msg_count() {
		return $this->msg_count;
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
		$shm = new Message();
		$shm->store($msg);
		$error = 0;
		@msg_send($this->ipc, 1, $shm->key(), false, true, $error);
		++$this->msg_count;
	}

	public function non_blocking_in($msg) {
		if ($this->closed || !msg_queue_exists($this->key)) {
			return self::CLOSED;
		}
		$shm = new Message();
		$shm->store($msg);
		$error = 0;
		@msg_send($this->ipc, 1, $shm->key(), false, false, $error);
		if (MSG_EAGAIN === $error) {
			$shmAbortedMessage = new Message($shm->key());
			$shmAbortedMessage->destroy();
			return false;
		}
		++$this->msg_count;
		$first_loop = true;
		do {
			$data = msg_stat_queue($this->ipc);
			if (!$first_loop && 0 == $data['msg_qnum']) {
				break;
			}
			$first_loop = false;
		} while (true);
		return true;
	}

	public function out() {
		if ($this->closed || !msg_queue_exists($this->key)) {
			return;
		}
		$msgtype = null;
		$ipcmsg = null;
		$error = null;
		msg_receive($this->ipc, 1, $msgtype, (1 * PHP_INT_LENGTH) + 1, $ipcmsg, false, 0, $error);
		--$this->msg_count;
		$shm = new Message($ipcmsg);
		$ret = $shm->fetch();
		return $ret;
	}

	public function non_blocking_out() {
		if ($this->closed || !msg_queue_exists($this->key)) {
			return [self::CLOSED, null];
		}
		$msgtype = null;
		$ipcmsg = null;
		$error = null;
		msg_receive($this->ipc, 1, $msgtype, (1 * PHP_INT_LENGTH) + 1, $ipcmsg, false, MSG_IPC_NOWAIT, $error);
		if (MSG_ENOMSG === $error) {
			return [false, null];
		}
		--$this->msg_count;
		$shm = new Message($ipcmsg);
		$ret = $shm->fetch();
		return [true, $ret];
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

/*
$chn = &$chn;
$var = &$var;
$var2 = &$var2;

select_channel([
[$chn, $var, function () {
echo "Message Sent";
}],
[$var, $chn, function ($msg) {
echo "Message Received";
}],
['default', function () {
echo "Default";
}, $var2],
]);
 */
function select_channel(array $actions) {
	while (true) {
		foreach ($actions as $action) {
			if ('default' == $action[0]) {
				call_user_func_array($action[1]);
				break 2;
			} elseif (is_callable($action[1])) {
				$chn = &$action[0];
				$callback = &$action[1];

				list($ok, $result) = $chn->non_blocking_out();
				if (true === $ok) {
					call_user_func_array($callback, [$result]);
					break 2;
				}
			} elseif ($action[0] instanceof CSP_Channel) {
				$chn = &$action[0];
				$msg = &$action[1];
				$callback = &$action[2];
				$params = array_slice($action, 3);

				$ok = $chn->non_blocking_in($msg);
				if (CSP_Channel::CLOSED === $ok) {
					throw new Exception('Cannot send to closed channel');
				} elseif (true === $ok) {
					call_user_func($callback);
					break 2;
				}
			} else {
				throw new Exception('Invalid action for CSP select_channel');
			}
		}
	}
}
