<?php

/**
*   Used with FPDI to stream the PDFs instead of loading them directly from files
*/
class VarStream {
    private $_pos;
    private $_stream;
    private $_cDataIdx;

    static protected $_data = array();
    static protected $_dataIdx = 0;

    static function createReference($var) {
        $idx = self::$_dataIdx++;
        self::$_data[$idx] =& $var;
        return __CLASS__.'://'.$idx;
    }

    public function stream_open($path, $mode, $options, &$opened_path) {
        $url = parse_url($path);
        $cDataIdx = $url["host"];
        if (!isset(self::$_data[$cDataIdx]))
            return false;
        $this->_stream = &self::$_data[$cDataIdx];
        $this->_pos = 0;
        if (!is_string($this->_stream)) return false;
        $this->_cDataIdx = $cDataIdx;
        return true;
    }

    public function stream_read($count) {
        $ret = substr($this->_stream, $this->_pos, $count);
        $this->_pos += strlen($ret);
        return $ret;
    }

    public function stream_write($data){
        $l=strlen($data);
        $this->_stream =
            substr($this->_stream, 0, $this->_pos) .
            $data .
            substr($this->_stream, $this->_pos += $l);
        return $l;
    }

    public function stream_tell() {
        return $this->_pos;
    }

    public function stream_eof() {
        return $this->_pos >= strlen($this->_stream);
    }

    public function stream_seek($offset, $whence) {
        $l=strlen($this->_stream);
        switch ($whence) {
            case SEEK_SET: $newPos = $offset; break;
            case SEEK_CUR: $newPos = $this->_pos + $offset; break;
            case SEEK_END: $newPos = $l + $offset; break;
            default: return false;
        }
        $ret = ($newPos >=0 && $newPos <=$l);
        if ($ret) $this->_pos=$newPos;
        return $ret;
    }

    public function stream_close() {
        unset(self::$_data[$this->_cDataIdx]);
    }

    public function url_stat ($path, $flags) {
        $url = parse_url($path);
        $dataIdx = $url["host"];
        if (!isset(self::$_data[$dataIdx]))
            return false;

        $size = strlen(self::$_data[$dataIdx]);
        return array(
            7 => $size,
            'size' => $size
        );
    }
}

stream_wrapper_register('VarStream', 'VarStream') or die('Failed to register protocol VarStream://');