<?php defined('SYSPATH') or die ('No direct script access.');

/**
*   Reads and writes CSV files
*
*   @author     Claero Systems <craig.nakamoto@claero.com> / XM Media Inc <dhein@xmmedia.net>
*   @copyright  Claero Systems / XM Media Inc  2004-2009
*
*   @see    class ClaeroError
*/
class cl4_CSV {
    /**
    *   Status of current object
    *   @var    bool
    */
    private $status = true;

    /**
    *   the current mode (read or write)
    *   @var    string
    */
    private $mode = 'write';

    /**
    *   the filepointer to the csv we are reading or writing
    *   @var    resource
    */
    private $csvFp;

    /**
    *   the path of the csv file, including the filename
    *   @var    string
    */
    private $csvFile;

    /**
    *   stores the current row number of adding or reading
    *   @var    int
    */
    private $rowNum = 0;

    /**
    *   Bool to use to determine if PHP has the escape parameter in the fgetcsv
    *   Only in PHP >=5.3.0
    *   @var    bool
    */
    private $phpHasEscapeOption = false;

    /**
    *   Prepares the object for csv creation or reading
    *
    *   @param  string  $mode       the mode to set the object up as (write or read)
    *   @param  string  $filename   the file to read or the file to write to (if null when writing, a temporary file will be created)
    *   @param  array   $options    array of options needed to perform action
    */
    public function __construct($mode = 'write', $filename = null, $options = array()) {
        if (version_compare(PHP_VERSION, '5.3.0', '>=')) $this->phpHasEscapeOption = true;

        // check to make sure we have a valid mode
        if (in_array($mode, array('read', 'write'))) {
            $this->mode = $mode;
        } else {
            $this->mode = 'write';
        } // if
        if ($this->mode == 'read') ini_set('auto_detect_line_endings', true);

        // determine the filename
        if ($filename == null) {
            if ($this->mode == 'write') {
                // we are writing a file, so generate a file name
                $tempFile = tempnam('/tmp', time() . '_');
                $this->csvFile = $tempFile . '.csv';
                unlink($tempFile); // this is required because the tempnam() function actually creates the file, but it's without an extension, so we'll delete the one without an extension
            } else {
                $this->status = false;
                trigger_error('Input Error: No filename was passed for reading', E_USER_ERROR);
            } // if
        } else {
            $this->csvFile = $filename;
        } // if

        // open the file for reading or writing
        if ($this->status) {
            if ($this->mode == 'write') {
                $this->csvFp = fopen($this->csvFile, 'w+');
            } else {
                $this->rowNum = -1;
                $this->csvFp = fopen($this->csvFile, 'r');
            } // if

            if ($this->csvFp === false) {
                $this->status = false;
                trigger_error('File System Error: There was a problem opening the file for ' . ($this->mode == 'write' ? 'writing' : 'reading') . ' ' . ($this->csvFile ? $this->csvFile : 'temp file'), E_USER_ERROR);
            } // if
        } // if
    } // function __construct

    /**
    *   Gets the status in the current object, uses $this->status
    *
    *   @return     bool        true or false on status of object
    */
    public function GetStatus() {
        return $this->status;
    } // function GetStatus

    /**
    *   Retrieves the name of the CSV file
    *
    *   @return     string      The name of the CSV file (possibly the name of the temp file)
    */
    public function GetCsvFilename() {
        return $this->csvFile;
    } // function GetCsvFilename

    /**
    *   Writes a row to the CSV
    *
    *   @param      array       $data           the array of columns to add to the csv
    *   @param      string      $delimnator     the string to put between each field
    *   @param      string      $enclosure      the string to put at the beginning and end of each field
    *
    *   @return     bool        The status of the add/the object
    *
    *   @see    fputcsv()
    */
    public function AddRow($data, $delimintor = ',', $enclosure = '"') {
        if ($this->mode != 'write') {
            trigger_error('Input Error: A write function has been requested when not in write mode', E_USER_ERROR);
        }

        if (!$this->status) {
            trigger_error('Input Error: Cannot run AddRow() as the objects status is false', E_USER_ERROR);
        }

        if (!fputcsv($this->csvFp, $data, $delimintor, $enclosure)) {
            $this->status = false;
            trigger_error('File System Error: There was a problem while adding the data row to the csv', E_USER_ERROR);
        } else {
            ++$this->rowNum;
        }

        return $this->GetStatus();
    } // function AddRow

    /**
    *   Add 1 or more blank rows to the CSV
    *
    *   @param  int     $count  The number of rows to add
    *
    *   @return     bool        The status of the add/the object
    */
    public function AddBlankRow($count = 1) {
        for ($i = 1; $i <= $count; $i ++) {
            $this->AddRow(array());
        }

        return $this->GetStatus();
    } // function AddBlankRow

    /**
    *   Closes the CSV
    *
    *   @return     bool        the status of the object
    */
    public function CloseCsv() {
        if ($this->mode != 'write') {
            trigger_error('Input Error: A write function has been requested when not in write mode', E_USER_ERROR);
        }

        if (!$this->status) {
            trigger_error('Input Error: Cannot run CloseCsv() as the objects status is false', E_USER_ERROR);
        }

        if (!fclose($this->csvFp)) {
            $this->status = false;
            trigger_error('File System Error: Failed to close CSV file pointer', E_USER_ERROR);
        }

        return $this->GetStatus();
    } // function GetCsv

    /**
    *   Creates the headers and reads the file
    *
    *   @param      string      $userFileName       The name of the file to display to the user
    *
    *   @return     bool        the status of the object
    */
    public function GetCsv($userFileName = null) {
        if ($this->mode != 'write') {
            trigger_error('Input Error: A write function has been requested when not in write mode', E_USER_ERROR);
        }

        if (!$this->status) {
            trigger_error('Input Error: Cannot run GetCsv() as the objects status is false', E_USER_ERROR);
        }

        if (!headers_sent()) {
            if (!$userFileName) $userFileName = pathinfo($this->csvFile, PATHINFO_BASENAME);

            if (!StreamFileDownload($this->csvFile, 'application/csv', $userFileName)) {
                $this->status = false;
                trigger_error('File System Error: Failed to read CSV file', E_USER_ERROR);
            }
        } else {
            $this->status = false;
            trigger_error('Input Error: The headers have already been sent sot eh download cannot be started', E_USER_ERROR);
        }

        return $this->GetStatus();
    } // function GetCsv

    /**
    *   Gets the current row number
    *   This is the row we are current reading or writing
    *
    *   @return     int         the current row number
    */
    public function GetRowNum() {
        return $this->rowNum;
    } // function GetRowNum

    /**
    *   Resets the current row number to 0
    *   Good to use when checking if the first row has the headers in it and if it doesn't then set the current row back to the start and start looping
    *
    *   @return     bool        returns the status of the rewind() function
    */
    public function ResetRowNum() {
        if ($this->mode == 'read') {
            $this->rowNum = 0;
        } else {
            $this->rowNum = -1;
        }
        return rewind($this->csvFp);
    } // function ResetRowNum

    /**
    *   Gets the next row from the CSV or false if error or end of file
    *
    *   @param      string      $delimnator     the string to put between each field
    *   @param      string      $enclosure      the string to put at the beginning and end of each field
    *   @param      string      $escape         only in PHP >5.3 the string used to escape the deliminator
    *
    *   @return     array       the row as an array or false if end of file or error
    */
    public function GetRow($delimintor = ',', $enclosure = '"', $escape = '\\') {
        if ($delimintor === false) $delimintor = ',';
        if ($enclosure === false) $enclosure = '"';
        if ($escape === false) $escape = '\\';

        if (!$this->phpHasEscapeOption) {
            if ($enclosure) {
                $return = fgetcsv($this->csvFp, 0, $delimintor, $enclosure);
            } else {
                $return = fgetcsv($this->csvFp, 0, $delimintor);
            }
        } else {
            if ($enclosure && $escape) {
                $return = fgetcsv($this->csvFp, 0, $delimintor, $enclosure, $escape);
            } else if ($enclosure) {
                $return = fgetcsv($this->csvFp, 0, $delimintor, $enclosure);
            } else {
                $return = fgetcsv($this->csvFp, 0, $delimintor);
            }
        }

        if (!$return || !is_array($return)) {
            return false;
        } else {
            ++$this->rowNum;
            return $return;
        }
    } // function GetRow
} // class cl4_CSV