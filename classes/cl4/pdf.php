<?php defined('SYSPATH') or die ('No direct script access.');

// include all the necessary files
try {
	Kohana::load(Kohana::find_file('vendor', 'tcpdf/config/lang/eng'));
	Kohana::load(Kohana::find_file('vendor', 'tcpdf/tcpdf'));
	Kohana::load(Kohana::find_file('vendor', 'tcpdf/fpdi2tcpdf_bridge'));
	Kohana::load(Kohana::find_file('vendor', 'tcpdf/fpdi'));
} catch (Exception $e) {
	// produce error for user
	if (Kohana::$errors) {
		echo 'Unable to find TCPDF and related files. Ensure it\'s in a vendor folder and doesn\'t have any errors';
	} else {
		echo 'There was a problem generating the PDF. Please contact the system administrator.';
	}
	// throw and then catch an exception so an error is logged and then throw the exception again
	try {
		throw new Kohana_Exception('Unable to find TCPDF and related files. Ensure it\'s in a vendor folder and doesn\'t have any errors');
	} catch (Exception $e) {
		cl4::exception_handler($e);
		throw $e;
	}
} // try

/**
*   This file contains the ClaeroTcpdf class used with TCPDF to add additional PDF functionality
*   Extends TCPDF
*   Some functionality overrides the default functionality
*
*   @author     Claero Systems <craig.nakamoto@claero.com> / XM Media Inc <dhein@xmmedia.net>
*   @copyright  Claero Systems / XM Media Inc  2004-2010
*   @version    $Id: class-claero_tcpdf.php 786 2010-07-20 05:35:45Z dhein $
*/
class cl4_PDF extends FPDI {
    /**
    *   If the PDF creation has been rolled back
    *   Used in KeepTogether()
    *   @var    bool
    */
    protected $rolledBackFlag = false;

    /**
    *   The number of times the KeepTogether() has been run during the current loop
    *   Reset after each loop is complete
    *   Used in KeepTogether()
    *   @var    int
    */
    protected $runCount = 0;

    /**
    *   The widths of the headers used for a table
    *   @var    array
    */
    protected $headerWidths;

    /**
    *   If the last row was shaded/highlighted
    *   @var    bool
    */
    protected $lastRowHighlight;

    /**
    *   Array of remembered X,Y coords
    *   @var    array
    */
    protected $rememberedXY = array();

    /**
    *   Prepares the objects and sets some defaults (mostly copied from the examples on TCPDFs website)
    *   Also reduces the number of parameteres that need to be passed on creating the objects to only the ones that are commonly used
    *   If you need to set others, extend the object and replace the constructor
    *   Uses default unit of mm
    *
    *   @param  string  $orientation    The page orientation
    *   @param  string  $format         The page size/layout/format
    *
    *   @return ClaeroTcpdf
    */
    public function __construct($orientation = 'P', $format = 'LETTER') {
        global $l; // $l is the language array from the lang config file

        // call the parent constructor with additional parameters
        parent::__construct($orientation, 'mm', $format, true, 'UTF-8', false); // the 4th parameter should be true, but there seems to be some issues with a library called pcre that isn't configured correctly on centos

        $this->SetCreator(PDF_CREATOR);
        $this->SetAuthor(LONG_NAME);

        // the default header data
        $this->SetHeaderData('', '', LONG_NAME, '');
        $this->setHeaderFont(array('helvetica', '', 10));
        $this->setFooterFont(array('helvetica', '', 10));

        //set some language-dependent strings
        $this->setLanguageArray($l);

        // set some margins
        $this->SetMargins(15, 17);
        $this->SetHeaderMargin(10);
        $this->SetFooterMargin(15);

        // set the auto page break at 17 mm from the bottom of the page
        $this->SetAutoPageBreak(true, 17);

        // sets the font, fill, text colour and draw colour to the default
        $this->SetDefaultFontFill();
    } // function __construct

    /**
    *   Sets the default font for the PDF
    *   Use instead of calling something like $pdf->SetFont('helvetica', '', 10); every time
    */
    public function SetDefaultFontFill() {
        $this->SetFont('helvetica', '', 9);
        $this->SetFillColor(255, 255, 255);
        $this->SetTextColor(0);
        $this->SetDrawColor(0, 0, 0);
    } // function SetDefaultFontFill

    /**
    *   Sets the default line style to a width of 0.25 solid black line
    */
    public function SetDefaultLineStyle() {
        $this->SetLineStyle(array('width' => 0.25, 'cap' => 0, 'join' => 0, 'dash' => 0, 'phase' => 0, 'color' => array(0, 0, 0)));
    } // function SetDefaultLineStyle

    /**
    *   This method is a version of the default one in TCPDF
    *   This method is used to render the page header.
    *   It is automatically called by AddPage() and can be overwritten in your own inherited class.
    */
    public function Header() {
        $ormargins = $this->getOriginalMargins();
        $headerfont = $this->getHeaderFont();
        $headerdata = $this->getHeaderData();
        if (($headerdata['logo']) && ($headerdata['logo'] != K_BLANK_IMAGE)) {
            $this->Image(K_PATH_IMAGES.$headerdata['logo'], $this->GetX(), $this->getHeaderMargin(), $headerdata['logo_width']);
            $imgy = $this->getImageRBY();
        } else {
            $imgy = $this->GetY();
        }
        $cell_height = round(($this->getCellHeightRatio() * $headerfont[2]) / $this->getScaleFactor(), 2);
        // set starting margin for text data cell
        if ($this->getRTL()) {
            $header_x = $ormargins['right'] + ($headerdata['logo_width'] * 1.1);
        } else {
            $header_x = $ormargins['left'] + ($headerdata['logo_width'] * 1.1);
        }
        $this->SetTextColor(0, 0, 0);
        // header title
        $this->SetFont($headerfont[0], 'B', $headerfont[2] + 1);
        $this->SetX($header_x);
        $this->Cell(0, $cell_height, $headerdata['title'], 0, 1, '', 0, '', 0);
        // header string
        $this->SetFont($headerfont[0], $headerfont[1], $headerfont[2]);
        $this->SetX($header_x);
        if ($headerdata['string']) $this->MultiCell(0, $cell_height, $headerdata['string'], 0, '', 0, 1, '', '', true, 0, false);
        // print an ending header line
        $this->SetLineStyle(array('width' => 0.25 / $this->getScaleFactor(), 'cap' => 'butt', 'join' => 'miter', 'dash' => 0, 'color' => array(0, 0, 0)));
        $this->SetY((2.835 / $this->getScaleFactor()) + max($imgy, $this->GetY()));
        if ($this->getRTL()) {
            $this->SetX($ormargins['right']);
        } else {
            $this->SetX($ormargins['left']);
        }
        $this->Cell(0, 0, '', 'T', 0, 'C');
    } // function Header

    /**
    *   This method is a version of the default one in TCPDF
    *   This method is used to render the page footer.
    *   It is automatically called by AddPage() and cam be overwritten in your own inherited class.
    */
    public function Footer() {
        $cur_y = $this->GetY();
        $ormargins = $this->getOriginalMargins();
        $this->SetTextColor(0, 0, 0);
        //set style for cell border
        $line_width = 0.25 / $this->getScaleFactor();
        $this->SetLineStyle(array('width' => $line_width, 'cap' => 'butt', 'join' => 'miter', 'dash' => 0, 'color' => array(0, 0, 0)));
        //print document barcode
        $barcode = $this->getBarcode();
        if (!empty($barcode)) {
            $this->Ln($line_width);
            $barcode_width = round(($this->getPageWidth() - $ormargins['left'] - $ormargins['right'])/3);
            $this->write1DBarcode($barcode, 'C128B', $this->GetX(), $cur_y + $line_width, $barcode_width, (($this->getFooterMargin() / 3) - $line_width), 0.3, '', '');
        }
        if (empty($this->pagegroups)) {
            $pagenumtxt = 'Page '.$this->getAliasNumPage().' / '.$this->getAliasNbPages();
        } else {
            $pagenumtxt = 'Page  '.$this->getPageNumGroupAlias().' / '.$this->getPageGroupAlias();
        }
        $this->SetY($cur_y);
        //Print page number
        if ($this->getRTL()) {
            $this->SetX($ormargins['right']);
            $this->Cell(0, 8, $pagenumtxt, 'T', 0, 'L');
        } else {
            $this->SetX($ormargins['left']);
            $this->Cell(0, 8, $pagenumtxt, 'T', 0, 'R');
        }
    } // function Footer

    /**
    *   When used in a while statement, this method will keep the PDF content within the while statement on 1 page if possible
    *   If will first start a transaction, returning false the first time around
    *   Next time it will check to see if it's on the same page; if not, it will rollback the transaction and add a page; if it is on the same page, it will commit the transaction
    *   Next time it will commit the transaction because the content has to be added and may not fit on 1 page, but it will start on it's own page
    *
    *   @return     bool    false if content is not on 1 page, true if the content is on 1 page or will not fit on 1 page and has been added anyway
    */
    function KeepTogether() {
        ++$this->runCount;

        if ($this->runCount > 1) { // we have moved to the next page
            if ($this->rolledBackFlag || $this->getPage() == $this->start_transaction_page) { // the content is longer than 1 page, so just add
                $this->commitTransaction();
                $this->runCount = 0;
                $this->rolledBackFlag = false;
                return true;
            }

            $this->rolledBackFlag = true;
            $this->rollbackTransaction($this);
            if ($this->tMargin != $this->GetY()) $this->AddPage(); // we are already at the top of a page, so we don't want to add a page so we don't end up with a blank page
            $this->startTransaction();
            return false;

        } else if (!$this->rolledBackFlag) { // no rollback has happened and therefore no content has been added, so return false so the content will be added
            $this->startTransaction();
            return false;

        } else { // no roll back needed
            $this->commitTransaction();
            $this->runCount = 0;
            $this->rolledBackFlag = false;
            return true;
        }
    } // function KeepTogether()

    /**
    *   Overrides the commitTransaction() of TCPDF and sets the start_transaction_page property to 0 again as the default one doesn't do that
    */
    public function commitTransaction() {
        parent::commitTransaction();
        $this->start_transaction_page = 0;
    } // function commitTransaction

    /**
    *   Adds column headings for a table
    *
    *   @param  array   $headings   The column headings as values in an array
    *   @param  array   $colWidths  The widths of the columns as values in an array in any format Cell() or MultiCell() will accept in the their width parameters
    *   @param  array   $options    Array of options
    *       font_size => the font size to use; 0 is default which will default to the default font size of the PDF
    *       colspans => array of column spans where the key is the column number and the value is the number of columns to span
    *       fill_colour => the grey shade to apply to the background of the cells (default 200)
    *       row_height => the height of the cells (default 7)
    *       end_ln => if a line should be added at the end of the row (default true); this can allow for 2 column headings to be put beside each other
    */
    public function AddTableHeadings($headings, $colWidths, $options = array()) {
        $possibleOptions = array(
            'font_size' => 0,
            'colspans' => array(),
            'fill_colour' => 200,
            'row_height' => 7,
            'end_ln' => true,
        );
        $options = SetFunctionOptions($options, $possibleOptions);

        // Colors, line width and bold font
        $this->SetDefaultFontFill();
        $this->SetFillColor($options['fill_colour'], $options['fill_colour'], $options['fill_colour']);
        $this->SetLineWidth(0.25 / $this->getScaleFactor());
        $this->SetFont('', 'B', $options['font_size']);

        $this->headerWidths = $colWidths;
        $headerCount = count($headings);
        foreach ($options['colspans'] as $count) {
            $headerCount += $count - 1;
        }

        for ($i = 0; $i < $headerCount; $i++) {
            $origI = $i;
            if (isset($options['colspans'][$i])) {
                $colWidth = 0;
                for ($y = $i; $y <= ($i + $options['colspans'][$i] - 1); $y++) {
                    $colWidth += $colWidths[$y];
                }
                $i = $y - 1;

            } else {
                $colWidth = $colWidths[$i];
            }

            $this->Cell($colWidth, $options['row_height'], $headings[$origI], 1, 0, 'C', 1);
        }

        if ($options['end_ln']) $this->Ln();
        $this->SetDefaultFontFill();
    } // function AddTableHeadings

    /**
    *   Adds a row of data based on the widths of the headers as set in AddTableHeadings()
    *
    *   @param  array   $data       The row data where the values of the array is the data
    *   @param  array   $options    Array of options
    *       cell_align => the alignment of the cells as accepted by Cell() and MultiCell() (default all L)
    *       max_row_height => the maximum height of any row; if set to null (default) the function will use MultiCell() and calculate the maximum height of a cell on the row and use that for the entire row
    *       use_multicells => when true, MultiCell() will be used; if false, Cell() will be used; MultiCell() will allow wrapping (defuault true)
    */
    public function AddTableRow($data, $options = array()) {
        $possibleOptions = array(
            'cell_align' => array(),
            'max_row_height' => null,
            'use_multicells' => true, // if set to false, all text will be added with cells & therefore no wrapping will be done
        );
        $options = SetFunctionOptions($options, $possibleOptions);

        // check to see if the cell alignment has been set; if not set to left (L)
        foreach ($this->headerWidths as $num => $width) {
            if (!isset($options['cell_align'][$num])) $options['cell_align'][$num] = 'L';
        }

        $this->SetDefaultFontFill();
        $this->SetFillColor(225, 225, 225);

        if ($this->lastRowHighlight === null) $this->lastRowHighlight = false;
        $fill = $this->lastRowHighlight;

        // calculate the highest cell in the row so we can set all of them to that height when creating them
        if ($options['max_row_height'] === null) {
            $maxRowHeight = 0;
            foreach ($this->headerWidths as $num => $width) {
                $this->startTransaction();
                if ($options['use_multicells']) {
                    // 1000 is a ridicules height figure, but we using it to get the ultimate height of the cell
                    $lineNums = $this->MultiCell($width, 1000, $data[$num], 0, $options['cell_align'][$num], 0, 0);
                } else {
                    $lineNums = $this->Cell($width, 0, $data[$num], 0, 0, $options['cell_align'][$num], 0);
                }
                $this->rollbackTransaction($this);

                $maxRowHeight = max($maxRowHeight, $this->GetRowHeight($lineNums));
            } // foreach
        } else {
            $maxRowHeight = $options['max_row_height'];
        }

        // add cells
        if ($options['use_multicells']) {
            foreach ($this->headerWidths as $num => $width) {
                $this->MultiCell($width, $maxRowHeight, $data[$num], 'LR', $options['cell_align'][$num], $fill, 0);
            } // foreach
        } else {
            foreach ($this->headerWidths as $num => $width) {
                $this->Cell($width, $maxRowHeight, $data[$num], 'LR', 0, $options['cell_align'][$num], $fill);
            } // foreach
        }

        $this->Ln();

        $this->lastRowHighlight = !$this->lastRowHighlight;
        $this->SetDefaultFontFill();
    } // function AddTableRow

    /**
    *   Adds a line at the bottom of the table based on the widths of the widths of the headers/cells
    */
    public function CloseTable() {
        $this->Cell(array_sum($this->headerWidths), 0, '', 'T');
    } // function CloseTable

    /**
    *   Gets the page width between the left and right margins
    *
    *   @return     float   The width of the page between the margins
    */
    public function GetPageMaginWidth() {
        return $this->w - $this->rMargin - $this->lMargin;
    } // function GetPageMaginWidth

    /**
    *   Gets the page height between the top and bottom margins
    *
    *   @return     float   The height of the page between the margins
    */
    public function GetPageMarginHeight() {
        return $this->h - $this->tMargin;
    } // function GetPageMarginHeight

    /**
    *   Gets the row height based on the font sizes; applying cell margins
    *
    *   @param      int     $numLines   The number of lines to get the row height for
    *
    *   @return     float   The row height
    */
    public function GetRowHeight($numLines = 1) {
        $lineHeight = ($this->GetFontSize() * $this->getCellHeightRatio());
        return ($numLines * $lineHeight) + (2 * $this->cMargin);
    } // function GetRowHeight

    /**
    *   Prints a cell (rectangular area) with optional borders, background color and character string. The upper-left corner of the cell corresponds to the current position. The text can be aligned or centered. After the call, the current position moves to the right or to the next line. It is possible to put a link on the text.<br />
    *   If automatic page breaking is enabled and the cell goes beyond the limit, a page break is done before outputting.
    *
    *   @param  float/string   $w   Cell width. If 0, the cell extends up to the right margin. This can also be a string precentage, which is used with GetPageMaginWidth() to calculate the with the cell based on a percentage of the page width
    *   @param  float   $h          Cell height. Default value: 0.
    *   @param  string  $txt        String to print. Default value: empty string.
    *   @param  mixed   $border     Indicates if borders must be drawn around the cell. The value can be either a number:<ul><li>0: no border (default)</li><li>1: frame</li></ul>or a string containing some or all of the following characters (in any order):<ul><li>L: left</li><li>T: top</li><li>R: right</li><li>B: bottom</li></ul>
    *   @param  int     $ln         Indicates where the current position should go after the call. Possible values are:<ul><li>0: to the right (or left for RTL languages)</li><li>1: to the beginning of the next line</li><li>2: below</li></ul>
    Putting 1 is equivalent to putting 0 and calling Ln() just after. Default value: 0.
    *   @param  string  $align      Allows to center or align the text. Possible values are:<ul><li>L or empty string: left align (default value)</li><li>C: center</li><li>R: right align</li><li>J: justify</li></ul>
    *   @param  int     $fill       Indicates if the cell background must be painted (1) or transparent (0). Default value: 0.
    *   @param  mixed   $link       URL or identifier returned by AddLink().
    *   @param  int     $stretch    stretch carachter mode: <ul><li>0 = disabled</li><li>1 = horizontal scaling only if necessary</li><li>2 = forced horizontal scaling</li><li>3 = character spacing only if necessary</li><li>4 = forced character spacing</li></ul>
    *   @param  boolean $ignore_min_height if true ignore automatic minimum height value.
    *   @param  string  $calign     cell vertical alignment relative to the specified Y value. Possible values are:<ul><li>T : cell top</li><li>C : center</li><li>B : cell bottom</li><li>A : font top</li><li>L : font baseline</li><li>D : font bottom</li></ul>
    *   @param  string  $valign     text vertical alignment inside the cell. Possible values are:<ul><li>T : top</li><li>C : center</li><li>B : bottom</li></ul>
    *
    *   This is a copy of the phpDoc on TCPDF::Cell()
    *
    *   @access public
    *   @since 1.0
    *   @see SetFont(), SetDrawColor(), SetFillColor(), SetTextColor(), SetLineWidth(), AddLink(), Ln(), MultiCell(), Write(), SetAutoPageBreak()
    */
    public function Cell($w, $h = 0, $txt = '', $border = 0, $ln = 0, $align = '', $fill = 0, $link = '', $stretch = 0, $ignore_min_height = false, $calign = 'T', $valign = 'M') {
        if (is_string($w) && strpos($w, '%') !== false) {
            $w = $this->CalculatePercentageWidth($w);
        }

        $border = strtoupper($border);

        parent::Cell($w, $h, $txt, $border, $ln, $align, $fill, $link, $stretch, $ignore_min_height);
    } // function Cell

    /**
    *   This method allows printing text with line breaks.
    *   They can be automatic (as soon as the text reaches the right border of the cell) or explicit (via the \n character). As many cells as necessary are output, one below the other.<br />
    *   Text can be aligned, centered or justified. The cell block can be framed and the background painted.
    *
    *   @param  float/string   $w   Width of cells. If 0, they extend up to the right margin of the page. This can also be a string precentage, which is used with GetPageMaginWidth() to calculate the with the cell based on a percentage of the page width
    *   @param  float   $h          Cell minimum height. The cell extends automatically if needed.
    *   @param  string  $txt        String to print
    *   @param  mixed   $border     Indicates if borders must be drawn around the cell block. The value can be either a number:<ul><li>0: no border (default)</li><li>1: frame</li></ul>or a string containing some or all of the following characters (in any order):<ul><li>L: left</li><li>T: top</li><li>R: right</li><li>B: bottom</li></ul>
    *   @param  string  $align      Allows to center or align the text. Possible values are:<ul><li>L or empty string: left align</li><li>C: center</li><li>R: right align</li><li>J: justification (default value when $ishtml=false)</li></ul>
    *   @param  int     $fill       Indicates if the cell background must be painted (1) or transparent (0). Default value: 0.
    *   @param  int     $ln         Indicates where the current position should go after the call. Possible values are:<ul><li>0: to the right</li><li>1: to the beginning of the next line [DEFAULT]</li><li>2: below</li></ul>
    *   @param  float   $x          x position in user units
    *   @param  float   $y          y position in user units
    *   @param  boolean $reseth     if true reset the last cell height (default true).
    *   @param  int     $stretch    stretch carachter mode: <ul><li>0 = disabled</li><li>1 = horizontal scaling only if necessary</li><li>2 = forced horizontal scaling</li><li>3 = character spacing only if necessary</li><li>4 = forced character spacing</li></ul>
    *   @param  boolean $ishtml     set to true if $txt is HTML content (default = false).
    *   @param  boolean $autopadding if true, uses internal padding and automatically adjust it to account for line width.
    *   @param  float   $maxh       maximum height. It should be >= $h and less then remaining space to the bottom of the page, or 0 for disable this feature. This feature works only when $ishtml=false.
    *   @param  string  $valign     Vertical alignment of text (requires $maxh = $h > 0). Possible values are:<ul><li>T: TOP</li><li>M: middle</li><li>B: bottom</li></ul>. This feature works only when $ishtml=false.
    *   @param  boolean $fitcell    if true attempt to fit all the text within the cell by reducing the font size.
    *
    *   @return int     Returns the number of cells or 1 for html mode.
    *
    *   This is a copy of the phpDoc on TCPDF::MultiCell()
    *
    *   @access public
    *   @since 1.3
    *   @see SetFont(), SetDrawColor(), SetFillColor(), SetTextColor(), SetLineWidth(), Cell(), Write(), SetAutoPageBreak()
    */
    public function MultiCell($w, $h, $txt, $border = 0, $align = 'J', $fill = 0, $ln = 1, $x = '', $y = '', $reseth = true, $stretch = 0, $ishtml = false, $autopadding = true, $maxh = 0, $valign = 'T', $fitcell = false) {
        if (is_string($w) && strpos($w, '%') !== false) {
            $w = $this->CalculatePercentageWidth($w);
        }
        $border = strtoupper($border);
        return parent::MultiCell($w, $h, $txt, $border, $align, $fill, $ln, $x, $y, $reseth, $stretch, $ishtml, $autopadding, $maxh);
    } // function MultiCell

    /**
    *   This method allows printing text with line breaks.
    *   They can be automatic (as soon as the text reaches the right border of the cell) or explicit (via the \n character). As many cells as necessary are output, one below the other.<br />
    *   Text can be aligned, centered or justified. The cell block can be framed and the background painted.
    *
    *   @param  float/string   $w   Width of cells. If 0, they extend up to the right margin of the page. This can also be a string precentage, which is used with GetPageMaginWidth() to calculate the with the cell based on a percentage of the page width; widths includes the padding specified by the left and right keys in the padding array
    *   @param  float   $h          Cell minimum height. The cell extends automatically if needed.
    *   @param  string  $txt        String to print
    *   @param  mixed   $border     Indicates if borders must be drawn around the cell block. The value can be either a number:<ul><li>0: no border (default)</li><li>1: frame</li></ul>or a string containing some or all of the following characters (in any order):<ul><li>L: left</li><li>T: top</li><li>R: right</li><li>B: bottom</li></ul>
    *   @param  string  $align      Allows to center or align the text. Possible values are:<ul><li>L or empty string: left align</li><li>C: center</li><li>R: right align</li><li>J: justification (default value when $ishtml=false)</li></ul>
    *   @param  int     $fill       Indicates if the cell background must be painted (1) or transparent (0). Default value: 0.
    *   @param  int     $ln         Indicates where the current position should go after the call. Possible values are:<ul><li>0: to the right</li><li>1: to the beginning of the next line [DEFAULT]</li><li>2: below</li></ul>
    *   @param  array   $padding    this can be an array to specific the padding around the multicell; the array can contain some or all of the following; the key is the side the value is the amount of padding: array('left' => #, 'right' => #, 'top' => #, 'bottom' => #); the width ($w) includes the padding
    *   @param  float   $x          x position in user units
    *   @param  float   $y          y position in user units
    *   @param  boolean $reseth     if true reset the last cell height (default true).
    *   @param  int     $stretch    stretch carachter mode: <ul><li>0 = disabled</li><li>1 = horizontal scaling only if necessary</li><li>2 = forced horizontal scaling</li><li>3 = character spacing only if necessary</li><li>4 = forced character spacing</li></ul>
    *   @param  boolean $ishtml     set to true if $txt is HTML content (default = false).
    *   @param  boolean $autopadding if true, uses internal padding and automatically adjust it to account for line width; default false
    *   @param  float   $maxh       maximum height. It should be >= $h and less then remaining space to the bottom of the page, or 0 for disable this feature. This feature works only when $ishtml=false.
    *
    *   @return int     Returns the number of cells or 1 for html mode.
    *
    *   This is a copy of the phpDoc on TCPDF::MultiCell() with some changes for the additional padding parameter
    *
    *   @access public
    *   @since 1.3
    *   @see SetFont(), SetDrawColor(), SetFillColor(), SetTextColor(), SetLineWidth(), Cell(), Write(), SetAutoPageBreak()
    */
    public function MultiCellPadded($w, $h, $txt, $border = 0, $align = 'J', $fill = 0, $ln = 1, $padding = null, $x = '', $y = '', $reseth = true, $stretch = 0, $ishtml = false, $autopadding = false, $maxh = 0) {
        if (is_array($padding)) {
            $origX = $this->GetX();
            $origY = $this->GetY();
            $startPage = $this->page;

            $padding = SetFunctionOptions($padding, array('left' => 0, 'right' => 0, 'top' => 0, 'bottom' => 0));

            $border = strtoupper($border);

            $this->SetX($this->GetX() + $padding['left']); // add the padding to the left by setting the X pos to the right
            $this->SetY($this->GetY() + $padding['top']); // add the padding to the top by setting the Y pos down

            if (is_string($w) && strpos($w, '%') !== false) {
                $w = $this->CalculatePercentageWidth($w);
            }
            $w -= $padding['right'];
        } // if padding

        $multiCellReturn = $this->MultiCell($w, $h, $txt, '', $align, $fill, $ln, $x, $y, $reseth, $stretch, $ishtml, $autopadding, $maxh); // don't pass the boarder parameter

        if (is_array($padding)) {
            $endPage = $this->page;
            $bottomY = $this->GetY();
            $rectBorderArray = array();
            foreach (str_split($border) as $borderSide) { // rectangle requires the border property as array('L' => 1, 'R' => 1...)
                $rectBorderArray[$borderSide] = 1;
            }

            // we have changed pages, so we have to draw different borders on each page
            if ($endPage > $startPage) {
                for ($page = $startPage; $page <= $endPage; ++$page) {
                    $this->setPage($page);
                    if ($page == $startPage) { // first page of cell; draw top and side borders
                        $h = $this->getPageHeight() - $origY - $this->getBreakMargin();
                        $rectBorderArrayTemp = $rectBorderArray;
                        if (isset($rectBorderArrayTemp['B'])) unset($rectBorderArrayTemp['B']);
                        $this->Rect($origX, $origY, ($this->GetX() + $w) - $origX, $h, '', $rectBorderArrayTemp); // draw a rectangle

                    } elseif ($page == $endPage) { // last page of cell; draw side and bottom borders
                        $h = $bottomY - $this->tMargin + $padding['bottom'];
                        $rectBorderArrayTemp = $rectBorderArray;
                        if (isset($rectBorderArrayTemp['T'])) unset($rectBorderArrayTemp['T']);
                        $this->Rect($origX, $this->tMargin, ($this->GetX() + $w) - $origX, $h, '', $rectBorderArrayTemp); // draw a rectangle

                    } else { // middle page of cell; draw side borders
                        $h = $this->getPageHeight() - $this->tMargin - $this->getBreakMargin();
                        $rectBorderArrayTemp = $rectBorderArray;
                        if (isset($rectBorderArrayTemp['T'])) unset($rectBorderArrayTemp['T']);
                        if (isset($rectBorderArrayTemp['B'])) unset($rectBorderArrayTemp['B']);
                        $this->Rect($origX, $this->tMargin, ($this->GetX() + $w) - $origX, $h, '', $rectBorderArrayTemp); // draw a rectangle
                    } // if
                } // for

            // still on the same page, so just draw a simple rectangle
            } else {
                $this->Rect($origX, $origY, ($this->GetX() + $w) - $origX, ($this->GetY() - $origY) + $padding['bottom'], '', $rectBorderArray); // draw a rectangle around the multicell, but add the padding around it
            }
            $this->SetY($bottomY + $padding['bottom']); // set the Y to the bottom of the rectangle
        } // if padding

        return $multiCellReturn;
    } // function MultiCellPadded

    /**
    *   Calculates the percentage width based on the page width between the margins
    *
    *   @param  float   $percentage     the percentage of the width as an integer (88 for 88%, not 0.88)
    *
    *   @return         float           the width based on the percentage of the width between the margins
    */
    public function CalculatePercentageWidth($percentage) {
        return (floatval(substr($percentage, 0, -1)) / 100) * $this->GetPageMaginWidth();
    } // function CalculatePercentageWidth

    /**
    *   Draws a horizontal line
    *
    *   @param  float   $x1     the starting point of the line
    *   @param  float   $x2     the ending point of the line
    */
    public function LineHorz($x1, $x2) {
        $this->Line($x1, $this->GetY(), $x2, $this->GetY());
    } // function LineHorz

    /**
    *   Draws a vertical line
    *
    *   @param  float   $y1     the starting point of the line
    *   @param  float   $y2     the ending point of the line
    */
    public function LineVert($y1, $y2) {
        return $this->Line($this->GetX(), $y1, $this->GetX(), $y2);
    } // function LineVert

    /**
    *   Add page if needed (from TCPDF)
    *   Checks to see if there is a page break needed based on the specified height or possibly the Y position
    *   Only makes CheckPageBreak() a public function and runs the parent checkPageBreak function
    *
    *   @param float $h Cell height. Default value: 0.
    *   @param mixed $y starting y position, leave empty for current position.
    *   @param boolean $addpage if true add a page, otherwise only return the true/false state
    *
    *   @return boolean true in case of page break, false otherwise.
    */
    public function CheckPageBreak($h = 0, $y = '', $addpage = true) {
        return parent::checkPageBreak($h, $y, $addpage);
    }

    /**
    *   Moves the current abscissa back to the left margin and sets the ordinate.
    *   If the passed value is negative, it is relative to the bottom of the page.
    *
    *   @param  float   $y          The value of the ordinate.
    *   @param  bool    $resetx     if true (default) reset the X position. (default false)
    *   @param  boolean $rtloff     if true always uses the page top-left corner as origin of axis.
    *
    *   This is a copy of the phpDoc on TCPDF::SetY() with a change regarding the $resetx var
    *
    *   @access public
    *   @since 1.0
    *   @see GetX(), GetY(), SetY(), SetXY()
    */
    public function SetY($y, $resetx = false, $rtloff = false) {
        parent::SetY($y, $resetx);
    } // function SetY

    /**
    *   Moves the pointer the amount of $yChange along the Y axis
    *
    *   @param  decimal     $yChange    The distance to move
    */
    public function MoveY($yChange) {
        $this->SetY($this->GetY() + $yChange);
    } // function MoveY

    /**
    *   Moves the pointer the amount of $xChange along the X axis
    *
    *   @param  decimal     $xChange    The distance to move
    */
    public function MoveX($xChange) {
        $this->SetX($this->GetX() + $xChange);
    } // function MoveX

    /**
    *   Gets the specific margin: left, right, top, bottom
    *
    *   @param  string  $margin     The margin name
    *
    *   @return float   The margin value as returned by getMargins()
    */
    public function GetMargin($margin) {
        $margins = $this->getMargins();
        return $margins[$margin];
    } // function GetMargin

    /**
    *   Draws a bullet/dot/filled circle
    *   Saves by time by only requiring the x, y, and radius
    *   Filled by solid black
    *
    *   @param  float   $x  The x postion of the center of the bullet
    *   @param  float   $y  The y postion of the center of the bullet
    *   @param  mixed   $r  The radius of the bullet (default 1 likely mm)
    */
    public function Bullet($x, $y, $r = 1) {
        $this->Circle($x, $y, $r, 0, 360, 'F', array(), array(0,0,0));
    } // function Bullet

    /**
    *   Adds rulers across the top and down the left hand side of the page with optional lines across the page
    *
    *   @param  bool    $fullLine   Ff set to true, it will add a dashed line every 5mm all the way across the page (default false)
    */
    public function AddDebugRulers($fullLine = false) {
        $rememberedKey = $this->RememberXY();

        $this->SetXY(0,0);
        $this->SetFontSize(8);
        $this->SetAutoPageBreak(false);

        $fullLineStyle = array('color' => array(75, 75, 75), 'dash' => 5, 'width' => 0.25);

        // first draw the horizontal ruler
        for ($i = 0; $i <= parent::getPageHeight(); $i += 5) {
            //echo $this->GetY() . HEOL;
            $this->Line(0, $this->GetY(), ($i % 10 ? 2 : 4), $this->GetY());
            if ($fullLine) {
                $this->Line(0, $this->GetY(), parent::getPageHeight(), $this->GetY(), $fullLineStyle);
                $this->SetDefaultLineStyle();
            }
            if (!($i % 10) && $i > 0) {
                $topY = $this->GetY();
                $this->SetXY(4, $this->GetY() - 2);
                $this->Cell(5, 0, $i);
                $this->SetY($topY + 5);
            } else {
                $this->SetY($this->GetY() + 5);
            }
        }

        $this->SetXY(0,0);

        // now the vertical ruler
        for ($i = 0; $i <= parent::getPageWidth(); $i += 5) {
            //echo $this->GetX() . HEOL;
            $this->Line($this->GetX(), 0, $this->GetX(), ($i % 10 ? 2 : 4));
            if ($fullLine) {
                $this->Line($this->GetX(), 0, $this->GetX(), parent::getPageHeight(), $fullLineStyle);
                $this->SetDefaultLineStyle();
            }
            if (!($i % 10) && $i > 0) {
                $leftX = $this->GetX();
                $this->SetXY($this->GetX() - 3, 4);
                $this->Cell(5, 0, $i);
                $this->SetX($leftX + 5);
            } else {
                $this->SetX($this->GetX() + 5);
            }
        }

        $this->RecallXY($rememberedKey);
        $this->SetDefaultFontFill();
        $this->SetDefaultLineStyle();
        $this->SetAutoPageBreak(true);
    } // function AddDebugRulers

    /**
    *   Remembers the current X,Y coord, returning the key of the remembered position
    *   Used with RecallXY to reset the current position to the previous position without having to do it manually
    *   The positions are remembered in $this->rememberedXY
    *
    *   @return     int     The key to pass to RecallXY() to reset the position
    */
    public function RememberXY() {
        $this->rememberedXY[] = array($this->GetX(), $this->GetY());
        return key($this->rememberedXY);
    } // function RememberXY

    /**
    *   Sets the position to the one remembered in RememberXY()
    *
    *   @param  int     $key    The key returned from RememberXY()
    */
    public function RecallXY($key) {
        $this->SetX($this->rememberedXY[$key][0]);
        $this->SetY($this->rememberedXY[$key][1]);
    } // function RecallXY
} // class cl4_PDF