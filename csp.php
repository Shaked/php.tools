<?php
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
	public function __construct($key = null) {
		$this->ipc_fn = tempnam('/tmp', 'csp.' . uniqid('chn', true));
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