<?php namespace Phi; class InputStream implements \Iterator {

# Properties #

public $filename = 'php://input';
public $filetype = null;
public $supportedTypes = [
  'text/csv',
  'application/x-ndjson'
];
public $useCsvHeaders = false;
public $fh;
public $position = -1;
public $headers = null;
public $row = null;


# Constructor #

function __construct ($opts=null) {
  # Override default options.
  if (is_array($opts)) {
    if (array_key_exists('filename', $opts)) {
      $this->filename = $opts['filename'];
    }
    if (array_key_exists('filetype', $opts)) {
      $this->filetype = $opts['filetype'];
    }
    if (array_key_exists('useCsvHeaders', $opts)) {
      $this->useCsvHeaders = $opts['useCsvHeaders'];
    }
  }
  # Check if file (other than input stream) exists.
  if (!($this->filename==='php://input' || file_exists($this->filename))) {
    throw new \Exception('File not found.');
  }
  # Check if file type is supported.
  if (!$this->filetype) {
    $phi = \Phi::instance();
    $this->filetype = strtolower($phi->request->headers('Content-Type'));
  }
  if (!($this->filetype && in_array($this->filetype, $this->supportedTypes))) {
    throw new \Exception('Unsupported or unspecified file type.');
  }
  # Copy php://input to temp file; use temp file for reading.
  if ($this->filename === 'php://input') {
    $this->fh = fopen('php://temp', 'w+');
    fwrite($this->fh, file_get_contents('php://input'));
    fseek($this->fh, 0);
  }
  # Open normal file for reading.
  else {
    $this->fh = fopen($this->filename, 'r');
  }
}

function __destruct () {
  fclose($this->fh);
}


# Iterator Interface Methods #

function rewind() {
  fseek($this->fh, 0);
  $this->position = -1;
}

function next() {
  // ++$this->position;
}

function valid() {
  ++$this->position;
  switch ($this->filetype) {
    case 'text/csv':
      $this->readCsv();
      break;
    case 'application/x-ndjson':
      $this->readNdjson();
      break;
  }
  return is_array($this->row);
}

function current() {
  return $this->row;
}

function key() {
  return $this->position;
}


# Readers #

public function read () {
  if ($this->valid()) {
    return $this->row;
  } else {
    return false;
  }
}

private function readCsv () {
  if ($this->position === 0 && $this->useCsvHeaders) {
    $this->headers = fgetcsv($this->fh);
  }
  $data = fgetcsv($this->fh);
  if ($data === false) {
    $this->row = false;
  } else {
    if ($this->useCsvHeaders) {
      $this->row = array_combine($this->headers, $data);
    } else {
      $this->row = $data;
    }
  }
  return $this->row;
}


private function readNdjson () {
  $string = fgets($this->fh);
  if ($string === false) {
    $this->row = false;
  } else {
    try {
      $this->row = json_decode($string, true);
    } catch (\Exception $e) {
      $this->row = [null];
    }
  }
  return $this->row;
}


} ?>