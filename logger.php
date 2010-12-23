<?php

namespace GhtLib;

class Logger {

	private $dateformat = "Y-m-d-h";
	private $filename = "log.txt";
	private $path;
	private $handle;	

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

	public function log($msg, $level = 'i') {
		$level = ($level == 'i' ? 'INFO' : 'ERROR');
		$str = date($this->dateformat)." [".$level."] ".$msg;
		fwrite($this->handle, $str.'\n');
	}
}
