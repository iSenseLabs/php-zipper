<?php

class iProgress {
    private $task_name = 'isense';
    private $max_value = 100;
    private $current_value = 0;
    private $messages = array();
    private $last_message = '';
    private $message_history_count = 20;
    private $session_status = 'opened';
    private $abortCalled = false;
	private $progress_file = '';
	private $state = array();
	private $fp;
	private $data = array();

    public function __construct($task = 'isense', $messageHistoryCount = 20) {
        $this->task_name = $task;
		$this->progress_file = dirname(__FILE__) . DIRECTORY_SEPARATOR . $task . '.iprogress';
		$this->fp = fopen($this->progress_file, 'c+');
		
		$this->loadState();
		
        $this->message_history_count = ($messageHistoryCount != 20) ? $messageHistoryCount : (!empty($this->state['history_count']) ? $this->state['history_count'] : $messageHistoryCount);
        $this->max_value = !empty($this->state['max']) ? $this->state['max'] : 100;
        $this->current_value = !empty($this->state['current']) ? $this->state['current'] : 0;
        $this->messages = !empty($this->state['messages']) ? $this->state['messages'] : array();
        $this->last_message = !empty($this->state['last_message']) ? $this->state['last_message'] : '';
        $this->abortCalled = !empty($this->state['abort']) ? $this->state['abort'] : false;
        $this->data = !empty($this->state['data']) ? json_decode($this->state['data'], true) : array();
    }
	
	public function __destruct() {
		fclose($this->fp);
	}

    public function abort() { $this->sync(); $this->abortCalled = true; $this->saveState(); }
    public function abortCalled() { $this->sync(); return $this->abortCalled; }

    public function setMax($max) { $this->sync(); $this->max_value = $max; $this->saveState(); }
    public function getMax() { $this->sync(); return $this->max_value; }

    public function setProgress($progress) { $this->sync(); $this->current_value = $progress; $this->saveState(); }
    public function getProgress($sync = true) { if ($sync) $this->sync(); return $this->current_value; }
    
    public function setData($key, $value) { $this->sync(); $this->data[$key] = $value; $this->saveState(); }
    public function getData($key) { $this->sync(); return isset($this->data[$key]) ? $this->data[$key] : NULL; }

    public function addMsg($msg) {
        $this->sync();
        if (empty($this->messages[$this->current_value])) { $this->messages[$this->current_value] = array(); }
        $this->messages[$this->current_value][] = $msg;
        $this->last_message = $msg;
        if ($this->countMessages() > $this->message_history_count) { $this->truncMessages(); }
        $this->saveState();
    }
    public function getMessages() { $this->sync(); return $this->messages; }
    public function getLastMessage() { $this->sync(); return $this->last_message; }

    public function iterateWith($value) { $this->sync(); $this->current_value += $value; $this->saveState(); }
    public function getProgressPercent() { $this->sync(); return (($this->max_value == $this->current_value) || $this->max_value == 0) ? 100 : (int)(($this->current_value/$this->max_value)*100); }

    public function clear() {
        $this->max_value = 100;
        $this->current_value = 0;
        $this->messages = array();
        $this->last_message = '';
        $this->abortCalled = false;
        $this->data = array();
        $this->saveState();
    }

    private function countMessages() {
        $messages_count = 0;
        foreach ($this->messages as $progress_value => $messages) {
            $messages_count += count($messages);
        }
        return $messages_count;
    }

    private function truncMessages() {
        $message_overflow = $this->countMessages() - $this->message_history_count;
        foreach($this->messages as $progress_value => $messages) {
            foreach ($messages as &$msg) {
                if ($message_overflow <= 0) break 2;
                unset($msg);
                $message_overflow--;
            }
            unset($this->messages[$progress_value]);
        }
    }

    private function saveState() {
        $this->state['max'] = $this->max_value;
        $this->state['current'] = $this->current_value;
        $this->state['messages'] = $this->messages;
        $this->state['last_message'] = $this->last_message;
        $this->state['abort'] = $this->abortCalled;
        $this->state['data'] = json_encode($this->data);
		
		if (is_resource($this->fp)) {
			flock($this->fp, LOCK_EX);
			ftruncate($this->fp, 0);
			rewind($this->fp);
			fwrite($this->fp, json_encode($this->state));
			fflush($this->fp);
			flock($this->fp, LOCK_UN);
		}
    }
	
	private function loadState() {
		if (is_resource($this->fp)) {
			flock($this->fp, LOCK_SH);
			$info = fstat($this->fp);
			if ($info['size']) {
				rewind($this->fp);
				$this->state = json_decode(fread($this->fp, $info['size']), true);
				flock($this->fp, LOCK_UN);
			} else {
				$this->state = array();
			}
		} else {
			$this->state = array();
		}
	}

    private function sync() {
		$this->loadState();
		
        if (!empty($this->state['max'])) { //if one is set, the others will also be set
            $this->max_value = $this->state['max'];
            $this->current_value = $this->state['current'];
            $this->messages = $this->state['messages'];
            $this->last_message = $this->state['last_message'];
            $this->abortCalled = $this->state['abort'];
            $this->data = json_decode($this->state['data'], true);
        }
    }
}