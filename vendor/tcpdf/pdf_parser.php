<?php
// +---------------------------------------------------------------------+
// | FPDI PDF-Parser v.1.0.3                                             |
// | Copyright (c) 2009-2010 Setasign - Jan Slabon                       |
// +---------------------------------------------------------------------+
// | This source file is subject to the                                  |
// |    "FPDI PDF-Parser Commercial Developer License Agreement"         |
// | that is bundled with this package in the file                       |
// |    "FPDI-PDF-Parser-License.pdf"                                    |
// +---------------------------------------------------------------------+
// | Homepage: http://www.setasign.de                                    |
// | E-mail: support@setasign.de                                         |
// +---------------------------------------------------------------------+

if (!defined ('PDF_TYPE_NULL'))
    define ('PDF_TYPE_NULL', 0);
if (!defined ('PDF_TYPE_NUMERIC'))
    define ('PDF_TYPE_NUMERIC', 1);
if (!defined ('PDF_TYPE_TOKEN'))
    define ('PDF_TYPE_TOKEN', 2);
if (!defined ('PDF_TYPE_HEX'))
    define ('PDF_TYPE_HEX', 3);
if (!defined ('PDF_TYPE_STRING'))
    define ('PDF_TYPE_STRING', 4);
if (!defined ('PDF_TYPE_DICTIONARY'))
    define ('PDF_TYPE_DICTIONARY', 5);
if (!defined ('PDF_TYPE_ARRAY'))
    define ('PDF_TYPE_ARRAY', 6);
if (!defined ('PDF_TYPE_OBJDEC'))
    define ('PDF_TYPE_OBJDEC', 7);
if (!defined ('PDF_TYPE_OBJREF'))
    define ('PDF_TYPE_OBJREF', 8);
if (!defined ('PDF_TYPE_OBJECT'))
    define ('PDF_TYPE_OBJECT', 9);
if (!defined ('PDF_TYPE_STREAM'))
    define ('PDF_TYPE_STREAM', 10);
if (!defined ('PDF_TYPE_BOOLEAN'))
    define ('PDF_TYPE_BOOLEAN', 11);
if (!defined ('PDF_TYPE_REAL'))
    define ('PDF_TYPE_REAL', 12);

require_once('pdf_context.php');

class pdf_parser {
	
	/**
     * Filename
     * @var string
     */
    var $filename;
    
    /**
     * File resource
     * @var resource
     */
    var $f;
    
    /**
     * PDF Context
     * @var object pdf_context-Instance
     */
    var $c;
    
    /**
     * xref-Data
     * @var array
     */
    var $xref;

    /**
     * root-Object
     * @var array
     */
    var $root;
    
    /**
     * PDF Version
     * @var array
     */
    var $pdfVersion = '1.3';
    
    /**
     * For reading encrypted documents and xref/objectstreams are in use
     *
     * @var boolean
     */
    var $readPlain = true;
    
    /**
     * Cache for opened object streams
     *
     * @var array
     */
    var $_objStreamCache = array();
    
    /**
     * Constructor
     *
     * @param string $filename  Source-Filename
     */
	function pdf_parser($filename) {
        $this->filename = $filename;
        
        $this->f = @fopen($this->filename, 'rb');
        
        if (!$this->f)
            $this->error(sprintf('Cannot open %s !', $filename));
        
        $this->getPDFVersion();
        
        $this->c = new pdf_context($this->f);

        $this->xref = array();
        $this->pdf_read_xref($this->xref, $this->pdf_find_xref());
        
        if (count($this->xref) == 0) {
            $this->error('Unable to find xref table.');
        }
        
        // Check for Encryption
        $this->getEncryption();
        
        // Read root
        $this->pdf_read_root();
    }
    
    /**
     * Close the opened file
     */
    function closeFile() {
    	if (isset($this->f) && is_resource($this->f)) {
    	    fclose($this->f);
    		unset($this->f);
    	}	
    }
    
    /**
     * Print Error and die
     *
     * @param string $msg  Error-Message
     */
    function error($msg) {
    	die('<b>PDF-Parser Error:</b> ' . $msg);	
    }
    
    /**
     * Check Trailer for Encryption
     */
    function getEncryption() {
        if (isset($this->xref['trailer'][1]['/Encrypt'])) {
            $this->error('File is encrypted!');
        }
    }
    
	/**
     * Find/Return /Root
     *
     * @return array
     */
    function pdf_find_root() {
        if ($this->xref['trailer'][1]['/Root'][0] != PDF_TYPE_OBJREF) {
            $this->error('Wrong Type of Root-Element! Must be an indirect reference');
        }
        
        return $this->xref['trailer'][1]['/Root'];
    }

    /**
     * Read the /Root
     */
    function pdf_read_root() {
        // read root
        $this->root = $this->pdf_resolve_object($this->c, $this->pdf_find_root());
    }
    
    /**
     * Get PDF-Version
     *
     * And reset the PDF Version used in FPDI if needed
     */
    function getPDFVersion() {
        fseek($this->f, 0);
        preg_match('/\d\.\d/', fread($this->f, 16), $m);
        if (isset($m[0]))
            $this->pdfVersion = $m[0];
        return $this->pdfVersion;
    }
    
    /**
     * Find the xref-Table
     */
    function pdf_find_xref() {
        $toRead = 1500;
                
        $stat = fseek ($this->f, -$toRead, SEEK_END);
        if ($stat === -1) {
            fseek ($this->f, 0);
        }
       	$data = fread($this->f, $toRead);
       	
        $pos = strlen($data) - strpos(strrev($data), strrev('startxref')); 
        $data = substr($data, $pos);
        
        if (!preg_match('/\s*(\d+).*$/s', $data, $matches)) {
            $this->error('Unable to find pointer to xref table');
    	}

    	return (int) $matches[1];
    }

    function pdf_read_xref(&$result, $offset) {
        $o_pos = $offset-min(20, $offset);
    	fseek($this->f, $o_pos); // set some bytes backwards to fetch errorious docs
                
        $data = fread($this->f, 100);
        
        $xrefPos = strrpos($data, 'xref');
        
        if ($xrefPos === false) {
            // Could be a xref-Stream
            // Set to the real pointer
            fseek($this->f, $offset);
            $c = new pdf_context($this->f);
            $xrefStreamObjDec = $this->pdf_read_value($c);
            
            if (is_array($xrefStreamObjDec) && isset($xrefStreamObjDec[0]) && $xrefStreamObjDec[0] == PDF_TYPE_OBJDEC) {
                
                if (!isset($result['xref_location'])) {
                    $result['xref_location'] = $offset;
                    $result['max_object'] = 0;
            	}
            	
            	$this->xref['xref'][$xrefStreamObjDec[1]] = array($xrefStreamObjDec[2] => $offset);
            	
                $xrefStream = $this->pdf_resolve_object($c, array(PDF_TYPE_OBJREF, $xrefStreamObjDec[1], $xrefStreamObjDec[2]));
                
                if (isset($xrefStream[1][1]['/Type']) && $xrefStream[1][1]['/Type'][1] == '/XRef') {
                    $filters = array();
        
                    if (isset($xrefStream[1][1]['/Filter'])) {
                        $_filter = $xrefStream[1][1]['/Filter'];
            
                        if ($_filter[0] == PDF_TYPE_TOKEN) {
                            $filters[] = $_filter;
                        } else if ($_filter[0] == PDF_TYPE_ARRAY) {
                            $filters = $_filter[1];
                        }
                        $_filter = null;
                        unset($_filter);
                    }
                    
                    $xrefStreamData = $xrefStream[2][1];
                    
                    foreach ($filters AS $_filter) {
                        switch ($_filter[1]) {
                            case '/FlateDecode':
                                if (function_exists('gzuncompress')) {
                                    $xrefStreamData = (strlen($xrefStreamData) > 0) ? @gzuncompress($xrefStreamData) : '';                        
                                } else {
                                    $this->error(sprintf('To handle %s filter, please compile php with zlib support.', $_filter[1]));
                                }
                                if ($xrefStreamData === false) {
                                    $this->error('Error while decompressing stream.');
                                }
                            break;
                            case '/LZWDecode':
                                include_once('filters/FilterLZW_FPDI.php');
                                $decoder = new FilterLZW_FPDI($this->fpdi);
                                $xrefStreamData = $decoder->decode($xrefStreamData);
                                break;
                            case '/ASCII85Decode':
                                include_once('filters/FilterASCII85_FPDI.php');
                                $decoder = new FilterASCII85_FPDI($this->fpdi);
                                $xrefStreamData = $decoder->decode($xrefStreamData);
                                break;
                            case null:
                                // no filter
                                break;
                            default:
                                $this->error('Unsupported Filter: %s', $_filter[1]);
                        }
                    }
                    $filters = null;
                    unset($filters);
                    
                    if (isset($xrefStream[1][1]['/DecodeParms']) && isset($xrefStream[1][1]['/DecodeParms'][1]['/Predictor'])) {
                        require_once('filters/FilterPredictor_FPDI.php');
                        $decoder = new FilterPredictor_FPDI($this->fpdi);
                        
                        if (isset($xrefStream[1][1]['/DecodeParms'][1]['/Columns'])) {
                            $xrefStreamData = $decoder->decode($xrefStreamData, $xrefStream[1][1]['/DecodeParms'][1]['/Predictor'][1], $xrefStream[1][1]['/DecodeParms'][1]['/Columns'][1]);
                        } else {
                            $xrefStreamData = $decoder->decode($xrefStreamData, $xrefStream[1][1]['/DecodeParms'][1]['/Predictor'][1]);                        
                        }
                    }
                    
                    if (isset($xrefStream[1][1]['/Index']))
                        $sections = count($xrefStream[1][1]['/Index'][1])/2; 
                    else 
                        $sections = 1;
                        
                    $entryFieldSize = array(
                        $xrefStream[1][1]['/W'][1][0][1],
                        $xrefStream[1][1]['/W'][1][1][1],
                        $xrefStream[1][1]['/W'][1][2][1]
                    );
                    
                    $entrySize = array_sum($entryFieldSize);
                    $offset = 0;
                    
                    if (!isset($result['xref'])) {
                        $result['xref'] = array();
                    } 
                    
                    $result['_isXrefStream'] = true;
                        
                    for ($count = 0; $count < $sections; $count++) {
                        $size = $xrefStream[1][1]['/Size'][1];
                        if (isset($xrefStream[1][1]['/Index'])) {  
                            $objNum  = $xrefStream[1][1]['/Index'][1][$count*2][1];  
                            $entries = $xrefStream[1][1]['/Index'][1][$count*2 + 1][1];
                        } else {
                            $objNum  = 0;
                            $entries = $size;
                        }  
                        
                        if ($size > $result['max_object'])
                            $result['max_object'] = $size;
                        
                        for ($entry = 0; $entry < $entries; $entry++) {
                            $fields = array(1, 0, 0);
                            if ($entryFieldSize[0] > 0) {
                                if ($entryFieldSize[0] == 1) {   
                                    $fields[0] = ord($xrefStreamData[$offset++]);  
                                } else {  
                                    $fields[0] = 0;
                                    for ($k = 0; $k < $entryFieldSize[0]; $k++) {
                                        $fields[0] = ($fields[0] << 8) + (ord($xrefStreamData[$offset++]) & 0xff);
                                    }
                                }
                            }

                            for ($i = 1; $i < 3; $i++) {
                                if ($entryFieldSize[$i] > 0) {
                                    if ($entryFieldSize[$i] == 1) {   
                                        $fields[$i] = ord($xrefStreamData[$offset++]);  
                                    } else {  
                                        $fields[$i] = 0;
                                        for ($k = 0; $k < $entryFieldSize[$i]; $k++) {
                                            $fields[$i] = ($fields[$i] << 8) + (ord($xrefStreamData[$offset++]) & 0xff);
                                        }
                                    }
                                }
                            }
                            
                            switch ($fields[0]) {
                                case 0: // free
                                    // 1 = object no bzw. $objNum 
                                    // 2 = gen
                                    if (!isset($result['xref'][$objNum]))
                                        $result['xref'][$objNum] = array();
                                        
                                    if (!array_key_exists($gen = $fields[2], $result['xref'][$objNum])) {
                                        $result['xref'][$objNum][$gen] = null;
                                    } 
                                    break;
                                case 1: // normal entry
                                    // 1 = offset 
                                    // 2 = gen
                                    if (!isset($result['xref'][$objNum]))
                                        $result['xref'][$objNum] = array();
                                    
                                    if (!array_key_exists($gen = $fields[2], $result['xref'][$objNum])) {
                                        $result['xref'][$objNum][$gen] = $fields[1];
                                    }
                                    break;
                                case 2: // entry in an object stream
                                    // 1 = stream object number
                                    // 2 = index within the stream object
                                    if (!isset($result['xref'][$objNum]))
                                        $result['xref'][$objNum] = array();
                                    
                                    if (!isset($result['objStreams'][$objNum]) && (!isset($result['xref'][$objNum]) || !array_key_exists($gen = $fields[2], $result['xref'][$objNum]))) {
                            	    	$result['objStreams'][$objNum] = array(
                                            $fields[1],
                                            $fields[2]
                            	    	);
                            	    	
                            	    	$result['objStreamObjects'][$fields[1]] = 1;
                                    } 
                                    break;
                            }
                            
                            $objNum++;
                        }
                    }
                    
                    if (!isset($result['trailer'])) {
                        $result['trailer'] = array(PDF_TYPE_DICTIONARY, array());
                        $allowed = array('/Size', '/Root', '/Encrypt', '/Info', '/ID');
                        for ($i = 0, $n = count($allowed); $i < $n; $i++) {
                            if (isset($xrefStream[1][1][$allowed[$i]])) {
                                $result['trailer'][1][$allowed[$i]] = $xrefStream[1][1][$allowed[$i]];
                            }
                        }
                    }
                    
                    if (isset($xrefStream[1][1]['/Prev'])) {
                        $this->pdf_read_xref($result, $xrefStream[1][1]['/Prev'][1]);
                    }
                    
                    return true;
                }
            }
            
            $this->error('Unable to find xref table.');
        }
        
        if (!isset($result['xref_location'])) {
            $result['xref_location'] = $o_pos+$xrefPos;
            $result['max_object'] = 0;
    	}

    	$cylces = -1;
        $bytesPerCycle = 100;
        
    	fseek($this->f, $o_pos = $o_pos+$xrefPos+4); // set the handle direct after the "xref"-keyword
        $data = fread($this->f, $bytesPerCycle);
        
        while (($trailerPos = strpos($data, 'trailer', max($bytesPerCycle*$cylces++, 0))) === false && !feof($this->f)) {
            $data .= fread($this->f, $bytesPerCycle);
        }
        
        if ($trailerPos === false) {
            $this->error('Trailer keyword not found after xref table');
        }
        
        $data = substr($data, 0, $trailerPos);
        
        // get Line-Ending
        preg_match_all("/(\r\n|\n|\r)/", substr($data, 0, 100), $m); // get linebreaks in the first 100 bytes

        $differentLineEndings = count(array_unique($m[0]));
        if ($differentLineEndings > 1) {
            $lines = preg_split("/(\r\n|\n|\r)/", $data, -1, PREG_SPLIT_NO_EMPTY);
        } else {
            $lines = explode($m[0][1], $data);
        }
        
        $data = $differentLineEndings = $m = null;
        unset($data, $differentLineEndings, $m);
        
        $linesCount = count($lines);
        
        $start = 1;
        
        for ($i = 0; $i < $linesCount; $i++) {
            $line = trim($lines[$i]);
            if ($line) {
                $pieces = explode(' ', $line);
                
                $c = count($pieces);
                switch($c) {
                    case 2:
                        $start = (int)$pieces[0];
                        $end   = $start+(int)$pieces[1];
                        if ($end > $result['max_object'])
                            $result['max_object'] = $end;
                        break;
                    case 3:
                        if (!isset($result['xref'][$start]))
                            $result['xref'][$start] = array();
                        
                        if (!array_key_exists($gen = (int) $pieces[1], $result['xref'][$start])) {
                	        $result['xref'][$start][$gen] = $pieces[2] == 'n' ? (int) $pieces[0] : null;
                	    }
                        $start++;
                        break;
                    default:
                        $this->error(sprintf('Unexpected data in xref table (%s)', join(' ', $pieces)));
                }
            }
        }
        
        $lines = $pieces = $line = $start = $end = $gen = null;
        unset($lines, $pieces, $line, $start, $end, $gen);
        
        fseek($this->f, $o_pos+$trailerPos+7);
        
        $c = new pdf_context($this->f);
	    $trailer = $this->pdf_read_value($c);
	    
	    $c = null;
	    unset($c);
	    
	    if (!isset($result['trailer'])) {
            $result['trailer'] = $trailer;          
	    }
	    
	    if (isset($trailer[1]['/Prev'])) {
	    	$this->pdf_read_xref($result, $trailer[1]['/Prev'][1]);
	    } 
	    
	    $trailer = null;
	    unset($trailer);
        
        return true;
    }
    

    /**
     * Reads an Value
     *
     * @param object $c pdf_context
     * @param string $token a Token
     * @return mixed
     */
    function pdf_read_value(&$c, $token = null) {
    	if (is_null($token)) {
    	    $token = $this->pdf_read_token($c);
    	}
    	
        if ($token === false) {
    	    return false;
    	}

       	switch ($token) {
            case	'<':
    			// This is a hex string.
    			// Read the value, then the terminator

                $pos = $c->offset;

    			while(1) {

                    $match = strpos ($c->buffer, '>', $pos);
				
    				// If you can't find it, try
    				// reading more data from the stream

    				if ($match === false) {
    					if (!$c->increase_length()) {
    						return false;
    					} else {
                        	continue;
                    	}
    				}

    				$result = substr ($c->buffer, $c->offset, $match - $c->offset);
    				$c->offset = $match+1;
    				
    				return array (PDF_TYPE_HEX, $result);
                }
                
                break;
    		case	'<<':
    			// This is a dictionary.

    			$result = array();

    			// Recurse into this function until we reach
    			// the end of the dictionary.
    			while (($key = $this->pdf_read_token($c)) !== '>>') {
    				if ($key === false) {
    					return false;
    				}
					
    				if (($value = $this->pdf_read_value($c)) === false) {
    					return false;
    				}
    				
    				// Catch missing value
    				if ($value[0] == PDF_TYPE_TOKEN && $value[1] == '>>') {
    				    $result[$key] = array(PDF_TYPE_NULL);
    				    break;
    				}
    				
                    $result[$key] = $value;
    			}
    			
    			return array (PDF_TYPE_DICTIONARY, $result);

    		case	'[':
    			// This is an array.

    			$result = array();

    			// Recurse into this function until we reach
    			// the end of the array.
    			while (($token = $this->pdf_read_token($c)) !== ']') {
                    if ($token === false) {
    					return false;
    				}
					
    				if (($value = $this->pdf_read_value($c, $token)) === false) {
                        return false;
    				}
					
    				$result[] = $value;
    			}
    			
                return array (PDF_TYPE_ARRAY, $result);
            
    		case	'('		:
                // This is a string
                $pos = $c->offset;
                
                $openBrackets = 1;
    			do {
                    for (; $openBrackets != 0 && $pos < $c->length; $pos++) {
                        switch (ord($c->buffer[$pos])) {
                            case 0x28: // '('
                                $openBrackets++;
                                break;
                            case 0x29: // ')'
                                $openBrackets--;
                                break;
                            case 0x5C: // backslash
                                $pos++;
                        }
                    }
    			} while($openBrackets != 0 && $c->increase_length());
    			
    			$result = substr($c->buffer, $c->offset, $pos - $c->offset - 1);
    			$c->offset = $pos;
    			
    			return array (PDF_TYPE_STRING, $result);
            
            case 'stream':
            	$o_pos = ftell($c->file)-strlen($c->buffer);
		        $o_offset = $c->offset;
		        
		        $c->reset($startpos = $o_pos + $o_offset);
		        
		        $e = 0; // ensure line breaks in front of the stream
		        if ($c->buffer[0] == chr(10) || $c->buffer[0] == chr(13))
		        	$e++;
		        if ($c->buffer[1] == chr(10) && $c->buffer[0] != chr(10))
		        	$e++;
		        
	        	if ($this->actual_obj[1][1]['/Length'][0] == PDF_TYPE_OBJREF) {
		        	$tmp_c = new pdf_context($this->f);
		        	$tmp_length = $this->pdf_resolve_object($tmp_c,$this->actual_obj[1][1]['/Length']);
		        	$length = $tmp_length[1][1];
		        } else {
		        	$length = $this->actual_obj[1][1]['/Length'][1];	
		        }
		        
		        if ($length > 0) {
    		        $c->reset($startpos+$e,$length);
    		        $v = $c->buffer;
		        } else {
		            $v = '';   
		        }
		        
		        $c->reset($startpos+$e+$length); 
		        $endstream = $this->pdf_read_token($c);
		        
		        if ($endstream != 'endstream') {
		            $c->reset($startpos+$e+$length+9); // 9 = strlen("endstream")
		            // We don't throw an error here because the next
		            // round trip will start at a new offset
		        }
		        
		        return array(PDF_TYPE_STREAM, $v);
		        
	        default	:
            	if (is_numeric ($token)) {
                    // A numeric token. Make sure that
    				// it is not part of something else.
    				if (($tok2 = $this->pdf_read_token ($c)) !== false) {
                        if (is_numeric ($tok2)) {

    						// Two numeric tokens in a row.
    						// In this case, we're probably in
    						// front of either an object reference
    						// or an object specification.
    						// Determine the case and return the data
    						if (($tok3 = $this->pdf_read_token ($c)) !== false) {
                                switch ($tok3) {
    								case	'obj'	:
                                        return array (PDF_TYPE_OBJDEC, (int) $token, (int) $tok2);
    								case	'R'		:
    									return array (PDF_TYPE_OBJREF, (int) $token, (int) $tok2);
    							}
    							// If we get to this point, that numeric value up
    							// there was just a numeric value. Push the extra
    							// tokens back into the stack and return the value.
    							array_push ($c->stack, $tok3);
    						}
    					}

    					array_push ($c->stack, $tok2);
    				}

    				if ($token === (string)((int)$token))
        				return array (PDF_TYPE_NUMERIC, (int)$token);
    				else 
    					return array (PDF_TYPE_REAL, (float)$token);
    			} else if ($token == 'true' || $token == 'false') {
                    return array (PDF_TYPE_BOOLEAN, $token == 'true');
    			} else if ($token == 'null') {
    			   return array (PDF_TYPE_NULL);
    			} else {
                    // Just a token. Return it.
    				return array (PDF_TYPE_TOKEN, $token);
    			}
         }
    }
    
    /**
     * Resolve an object
     *
     * @param object $c pdf_context
     * @param array $obj_spec The object-data
     * @param boolean $encapsulate Must set to true, cause the parsing and fpdi use this method only without this para
     */
    function pdf_resolve_object(&$c, $obj_spec, $encapsulate = true) {
        // Exit if we get invalid data
    	if (!is_array($obj_spec)) {
    	    $ret = false;
    	    return $ret;
    	}

    	if ($obj_spec[0] == PDF_TYPE_OBJREF) {
    	    
    	    // This is a reference, resolve it
    		if (isset($this->xref['xref'][$obj_spec[1]][$obj_spec[2]])) {
                
    		    // Save current file position
    			// This is needed if you want to resolve
    			// references while you're reading another object
    			// (e.g.: if you need to determine the length
    			// of a stream)

    			$old_pos = ftell($c->file);

    			// Reposition the file pointer and
    			// load the object header.
				
    			$c->reset($this->xref['xref'][$obj_spec[1]][$obj_spec[2]]);

    			$header = $this->pdf_read_value($c);
    			
                if ($header[0] != PDF_TYPE_OBJDEC || $header[1] != $obj_spec[1] || $header[2] != $obj_spec[2]) {
                	$toSearchFor = $obj_spec[1].' '.$obj_spec[2].' obj';
    				if (preg_match('/'.$toSearchFor.'/', $c->buffer)) {
    					$c->offset = strpos($c->buffer, $toSearchFor) + strlen($toSearchFor);
    					// reset stack
        				$c->stack = array();
    				} else {
        				$this->error("Unable to find object ({$obj_spec[1]}, {$obj_spec[2]}) at expected location");
    				}
                }

    			// If we're being asked to store all the information
    			// about the object, we add the object ID and generation
    			// number for later use
    			$result = array();
				$this->actual_obj =& $result;
    			if ($encapsulate) {
    				$result = array (
    					PDF_TYPE_OBJECT,
    					'obj' => $obj_spec[1],
    					'gen' => $obj_spec[2]
    				);
    			} 

    			// Now simply read the object data until
    			// we encounter an end-of-object marker
    			while(1) {
                    $value = $this->pdf_read_value($c);
                    if ($value === false || count($result) > 4) {
					    // in this case the parser coudn't find an endobj so we break here
						break;
    				}

    				if ($value[0] == PDF_TYPE_TOKEN && $value[1] === 'endobj') {
    					break;
    				}

                    $result[] = $value;
    			}

    			$c->reset($old_pos);

                if (isset($result[2][0]) && $result[2][0] == PDF_TYPE_STREAM) {
                    $result[0] = PDF_TYPE_STREAM;
                }

    		} else if (isset($this->xref['objStreams'][$obj_spec[1]])) {
    		    
    		    $this->actual_obj =& $result;
    			if ($encapsulate) {
    				$result = array (
    					PDF_TYPE_OBJECT,
    					'obj' => $obj_spec[1],
    					'gen' => $obj_spec[2]
    				);
    			} else {
    				$result = array();
    			}
    			
    			$streamObjId = $this->xref['objStreams'][$obj_spec[1]][0];
    			
    			if (!isset($this->_objStreamCache[(string)$streamObjId])) {
    			    $objStreamRef = array(PDF_TYPE_OBJREF, $streamObjId, 0);
    			    $oReadPlain = $this->readPlain;
    			    $this->readPlain = true;
        		    $objStream = $this->pdf_resolve_object($c, $objStreamRef);
        		    $this->readPlain = $oReadPlain;
                        
                    $firstOffset = $objStream[1][1]['/First'][1];
                    $objectCount = $objStream[1][1]['/N'][1];
        		    
                    $filters = array();
                    if (isset($objStream[1][1]['/Filter'])) {
                        $_filter = $objStream[1][1]['/Filter'];
            
                        if ($_filter[0] == PDF_TYPE_TOKEN) {
                            $filters[] = $_filter;
                        } else if ($_filter[0] == PDF_TYPE_ARRAY) {
                            $filters = $_filter[1];
                        }
                        $_filter = null;
                        unset($_filter);
                    }
                    
                    $stream = $objStream[2][1];
                    
                    foreach ($filters AS $_filter) {
                        switch ($_filter[1]) {
                            case '/FlateDecode':
                                if (function_exists('gzuncompress')) {
                                    $stream = (strlen($stream) > 0) ? @gzuncompress($stream) : '';                        
                                } else {
                                    $this->error(sprintf('To handle %s filter, please compile php with zlib support.',$_filter[1]));
                                }
                                if ($stream === false) {
                                    $this->error('Error while decompressing stream.');
                                }
                                break;
                            case '/LZWDecode':
                                include_once('filters/FilterLZW_FPDI.php');
                                $decoder = new FilterLZW_FPDI($this->fpdi);
                                $stream = $decoder->decode($stream);
                                break;
                            case '/ASCII85Decode':
                                include_once('filters/FilterASCII85_FPDI.php');
                                $decoder = new FilterASCII85_FPDI($this->fpdi);
                                $stream = $decoder->decode($stream);
                                break;
                            case null:
                                // nothing to do
                                break;
                            default:
                                $this->error('Unsupported Filter: %s', $_filter[1]);
                        }
                    }
                    $filters = null;
                    unset($filters);
                    
                    $stream .= ' ';
                    
                    $sc = new pdf_context($stream);
                    
                    for ($i = 0; $i < $objectCount; $i++) {
                        $objNo = $this->pdf_read_token($sc);
                        $offset = $this->pdf_read_token($sc);
                        $objectPos[$objNo] = $firstOffset+$offset;
                    }
                    
                    $this->_objStreamCache[(string)$streamObjId] = array(
                        'sc' => $sc,
                        'objectPos' => $objectPos
                    );
    			} else {
    			    extract($this->_objStreamCache[(string)$streamObjId]);
    			}
    			
    			$sc->reset();
    			$sc->offset = $objectPos[$obj_spec[1]];
    			$oReadPlain = $this->readPlain;
    			$this->readPlain = false;
                $result[1] = $this->pdf_read_value($sc);
                $this->readPlain = $oReadPlain;
                    
                if (count($this->_objStreamCache) > 2) {
                    reset($this->_objStreamCache);
                    $k = key($this->_objStreamCache);
                    unset($this->_objStreamCache[$k]);
                }
            } else {
                $this->actual_obj =& $result;
    			if ($encapsulate) {
    				$result = array (
    					PDF_TYPE_OBJECT,
    					'obj' => $obj_spec[1],
    					'gen' => $obj_spec[2],
    					array(PDF_TYPE_NULL)
    				);
    			} else {
    				$result = array(PDF_TYPE_NULL);
    			}
            }
    		
            return $result;
    	} else {
    		return $obj_spec;
    	}
    }

    
    
    /**
     * Reads a token from the file
     *
     * @param object $c pdf_context
     * @return mixed
     */
    function pdf_read_token(&$c)
    {
    	// If there is a token available
    	// on the stack, pop it out and
    	// return it.

    	if (count($c->stack)) {
    		return array_pop($c->stack);
    	}

    	// Strip away any whitespace

    	do {
    		if (!$c->ensure_content()) {
    			return false;
    		}
    		$c->offset += strspn($c->buffer, " \n\r\t", $c->offset);
    	} while ($c->offset >= $c->length - 1);

    	// Get the first character in the stream

    	$char = $c->buffer[$c->offset++];

    	switch ($char) {

    		case '[':
    		case ']':
    		case '(':
    		case ')':

    			// This is either an array or literal string
    			// delimiter, Return it

    			return $char;

    		case '<':
    		case '>':

    			// This could either be a hex string or
    			// dictionary delimiter. Determine the
    			// appropriate case and return the token

    			if ($c->buffer[$c->offset] == $char) {
    				if (!$c->ensure_content()) {
    					return false;
    				}
    				$c->offset++;
    				return $char . $char;
    			} else {
    				return $char;
    			}

            case '%':
			    
                // This is a comment - jump over it!
			    
                $pos = $c->offset;
                while(1) {
    			    $match = preg_match("/(\r\n|\r|\n)/", $c->buffer, $m, PREG_OFFSET_CAPTURE, $pos);
                    if ($match === 0) {
    					if (!$c->increase_length()) {
    						return false;
    					} else {
                        	continue;
                    	}
    				}

    				$c->offset = $m[0][1]+strlen($m[0][0]);
    				
    				return $this->pdf_read_token($c);
                }
                
    		default:

    			// This is "another" type of token (probably
    			// a dictionary entry or a numeric value)
    			// Find the end and return it.

    			if (!$c->ensure_content()) {
    				return false;
    			}

    			while(1) {

    				// Determine the length of the token

    				$pos = strcspn($c->buffer, " %[]<>()\r\n\t/", $c->offset);

    				if ($c->offset + $pos <= $c->length - 1) {
    					break;
    				} else {
    					// If the script reaches this point,
    					// the token may span beyond the end
    					// of the current buffer. Therefore,
    					// we increase the size of the buffer
    					// and try again--just to be safe.

    					$c->increase_length();
    				}
    			}

    			$result = substr($c->buffer, $c->offset - 1, $pos + 1);
                
    			$c->offset += $pos;
    			return $result;
    	}
    }
}