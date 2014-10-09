<?php

class iProgress {
    private $task_name = 'isense';
    private $max_value = 100;
    private $current_value = 0;
    private $messages = array();
    private $last_message = '';
    private $auto_close_session = true;
    private $message_history_count = 20;
    private $session_status = 'opened';
    private $abortCalled = false;

    public function __construct($task = 'isense', $autoCloseSession = true, $messageHistoryCount = 20) {
        if (!session_id()) {
            $this->session_status = 'closed';
            $this->openSession();
        }

        $this->task_name = $task;
        $this->auto_close_session = $autoCloseSession;
        $this->message_history_count = ($messageHistoryCount != 20) ? $messageHistoryCount : (!empty($_SESSION['iprogress_history_count'][$this->task_name]) ? $_SESSION['iprogress_history_count'][$this->task_name] : $messageHistoryCount);
        $this->max_value = !empty($_SESSION['iprogress_max'][$this->task_name]) ? $_SESSION['iprogress_max'][$this->task_name] : 100;
        $this->current_value = !empty($_SESSION['iprogress_current'][$this->task_name]) ? $_SESSION['iprogress_current'][$this->task_name] : 0;
        $this->messages = !empty($_SESSION['iprogress_messages'][$this->task_name]) ? $_SESSION['iprogress_messages'][$this->task_name] : array();
        $this->last_message = !empty($_SESSION['iprogress_last_message'][$this->task_name]) ? $_SESSION['iprogress_last_message'][$this->task_name] : '';
        $this->abortCalled = !empty($_SESSION['iprogress_abort'][$this->task_name]) ? $_SESSION['iprogress_abort'][$this->task_name] : false;

        if ($this->auto_close_session) $this->closeSession();
    }

    public function abort() { $this->abortCalled = true; $this->updateSession(); }
    public function abortCalled() { $this->sync(); return $this->abortCalled; }

    public function setMax($max) { $this->max_value = $max; $this->updateSession(); }
    public function getMax() { $this->sync(); return $this->max_value; }

    public function setProgress($progress) { $this->current_value = $progress; $this->updateSession(); }
    public function getProgress($sync = true) { if ($sync) $this->sync(); return $this->current_value; }

    public function addMsg($msg) {
        $this->sync();
        if (empty($this->messages[$this->current_value])) { $this->messages[$this->current_value] = array(); }
        $this->messages[$this->current_value][] = $msg;
        $this->last_message = $msg;
        if ($this->countMessages() > $this->message_history_count) { $this->truncMessages(); }
        $this->updateSession();
    }
    public function getMessages() { $this->sync(); return $this->messages; }
    public function getLastMessage() { $this->sync(); return $this->last_message; }

    public function iterateWith($value) { $this->sync(); $this->current_value += $value; $this->updateSession(); }
    public function getProgressPercent() { $this->sync(); return (($this->max_value == $this->current_value) || $this->max_value == 0) ? 100 : (int)(($this->current_value/$this->max_value)*100); }

    public function clear() {
        $this->max_value = 100;
        $this->current_value = 0;
        $this->messages = array();
        $this->last_message = '';
        $this->abortCalled = false;
        $this->updateSession();
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

    private function openSession() {
        if ($this->session_status == 'closed') {
            session_start();
            $this->session_status = 'opened';
        }
    }

    private function closeSession() {
        session_write_close();
        $this->session_status = 'closed';
    }

    private function updateSession() {
        $this->openSession();

        $_SESSION['iprogress_max'][$this->task_name] = $this->max_value;
        $_SESSION['iprogress_current'][$this->task_name] = $this->current_value;
        $_SESSION['iprogress_messages'][$this->task_name] = $this->messages;
        $_SESSION['iprogress_last_message'][$this->task_name] = $this->last_message;
        $_SESSION['iprogress_abort'][$this->task_name] = $this->abortCalled;

        if ($this->auto_close_session) $this->closeSession();
    }

    private function sync() {
        $this->openSession();

        if (!empty($_SESSION['iprogress_max'][$this->task_name])) { //if this one is set, the others will also be set
            $this->max_value = $_SESSION['iprogress_max'][$this->task_name];
            $this->current_value = $_SESSION['iprogress_current'][$this->task_name];
            $this->messages = $_SESSION['iprogress_messages'][$this->task_name];
            $this->last_message = $_SESSION['iprogress_last_message'][$this->task_name];
            $this->abortCalled = $_SESSION['iprogress_abort'][$this->task_name];
        }

        if ($this->auto_close_session) $this->closeSession();
    }
}