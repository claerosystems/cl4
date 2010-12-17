<?php
// +---------------------------------------------------------------------+
// | FPDI PDF-Parser v.1.0.1                                             |
// | Copyright (c) 2009 Setasign - Jan Slabon                            |
// +---------------------------------------------------------------------+
// | This source file is subject to the                                  |
// |    "FPDI PDF-Parser Commercial Developer License Agreement"         |
// | that is bundled with this package in the file                       |
// |    "FPDI-PDF-Parser-License.pdf"                                    |
// +---------------------------------------------------------------------+
// | Homepage: http://www.setasign.de                                    |
// | E-mail: support@setasign.de                                         |
// +---------------------------------------------------------------------+

require_once('FilterPredictor.php');

class FilterPredictor_FPDI extends FilterPredictor {
    
    var $fpdi;

    function FilterPredictor_FPDI(&$fpdi) {
        $this->fpdi =& $fpdi;
    }
    
    function error($msg) {
        $this->fpdi->error($msg);
    }
}