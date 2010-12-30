<?php

namespace GhtLib;

class Logger {

	private $dateformat = "Y-m-d-h";
	private $filename = "log.txt";
	private $path;
	private $handle;	
	private $log = array();

	function __construct($filename = null, $path = null) {
		if ($filename) {
			$this->filename = $filename;
		}

		if ($path) {
			$this->path = $path;
		} else {
			$this->path = dirname(__FILE__);
		}
	}

	public function init() {
		$this->handle = fopen($this->path.'/'.$this->filename, 'a');
	}

	public function close() {
		fclose($this->handle);
	}

	public function log($msg, $level = 'i', $write = true) {
		$level = ($level == 'i' ? 'INFO' : 'ERROR');
		$this->log[count($this->log)] = date($this->dateformat)." [".$level."] ".$msg;
		if ($write) {
			$this->write();
		}
	}

	public function write() {
		foreach ($this->log as $line) {
				fwrite($this->handle, $line);
				fwrite($this->handle, PHP_EOL);
		}
		$this->log = array();
	}
}
