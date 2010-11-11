<?php defined('SYSPATH') OR die('No direct access allowed.');

/**
*   This file contains the ClaeroMeta class used for modifying (add/delete) the meta data
*
*   @author     Claero Systems <craig.nakamoto@claero.com> / XM Media Inc <dhein@xmmedia.net>
*   @copyright  Claero Systems / XM Media Inc  2004-2009
*   @version    $Id: class-claero_meta.php 741 2010-03-30 05:58:40Z dhein $
*/

//$libLoc = str_replace('/class-claero_meta.php', '', __FILE__);
//require_once($libLoc . '/claero_config.php');
//require_once($libLoc . '/common.php');
//require_once($libLoc . '/class-claero.php');
//require_once($libLoc . '/class-claero_db.php');
//require_once($libLoc . '/class-claero_error.php');

/**
*   Does the addition, removed and some editing of the meta data
*
*   @author     Claero Systems <craig.nakamoto@claero.com> / XM Media Inc <dhein@xmmedia.net>
*   @copyright  Claero Systems / XM Media Inc  2004-2009
*/
class Claero_Meta extends Claero_Base {
    /**
    *   Default field length (size)
    *   @var    int
    */
    private $size = 30;

    /**
    *   Flag as to whether or not to add the foreign key data
    *   @var    bool
    */
    private $addForeign = true;

    /**
    *   The number of records added to the meta table
    *   @var    int
    */
    private $added = 0;

    /**
    *   The number of records removed from the meta table
    *   @var    int
    */
    private $removed = 0;

    /**
    *   The number of records added to the foreign key table
    *   @var    int
    */
    private $foreignAdded = 0;

    /**
    *   The number of record updated
    *   @var    int
    */
    private $updated = 0;

    /**
    *   Contains the SQL statements run to add the meta data
    *   @var    array
    */
    private $metaQueries = array();

    /**
    *   Contains the SQL statements run to add the foreign key data
    *   @var    array
    */
    private $foreignQueries = array();

    /**
    *   Prepares object setting ClaerDb and other properties of object
    *
    *   @param  array   $options    array of options for object
    *       claero_db => ClaerDb object
    *       default_size => the default size to add new fields with (default 30)
    *       add_foreign => set to false to turn off adding foregin key data (default true)
    *       reorder_meta => allows for the possibility of disabling the meta table order (default: true)
    */
    public function __construct($options = array()) {
        parent::__construct($options);

        $this->SetObjectOptions($options, array('reorder_meta' => true));
        $this->SetObjectOptions($options, array('default_size' => null), false);

        if (isset($options['add_foreign'])) $this->addForeign = (bool) $options['add_foreign'];
    } // function __construct

    /**
    *   Returns an HTML formatted string of the queries run to add meta and foreign key data since the object was constructed
    *
    *   @return     string      HTML string of queries (line breaks between queries)
    */
    public function GetSqlStatments() {
        $returnHtml = '';
        foreach ($this->metaQueries as $sql) {
            $returnHtml .= $sql . ';' . HEOL;
        }
        foreach ($this->foreignQueries as $sql) {
            $returnHtml .= $sql . ';' . HEOL;
        }
        return $returnHtml;
    } // function GetSqlStatments

    /**
    *   Gets an HTML formatted string of what was done
    *
    *   @param      string      $tableName      Name of table being changed
    *
    *   @return     string      HTML string of changes
    */
    private function GetRecordStatus($tableName) {
        return HEOL . $this->GetStrongForRecordStatus($this->added) . 'Added ' . $this->added . ' record(s) to ' . CLAERO_META_TABLE . ' for table "' . $tableName . '".' . $this->GetStrongForRecordStatus($this->added, true) . HEOL
            . $this->GetStrongForRecordStatus($this->foreignAdded) . 'Added ' . $this->foreignAdded . ' record(s) to ' . CLAERO_FOREIGN_TABLE . ' for table "' . $tableName . '".' . $this->GetStrongForRecordStatus($this->foreignAdded, true) . HEOL
            . $this->GetStrongForRecordStatus($this->removed) . 'Removed ' . $this->removed . ' record(s) from ' . CLAERO_META_TABLE . ' for table "' . $tableName . '".' . $this->GetStrongForRecordStatus($this->removed, true) . HEOL
            . $this->GetStrongForRecordStatus($this->updated) . 'Updated ' . $this->updated . ' record from ' . CLAERO_META_TABLE . ' for table "' . $tableName . '"' . $this->GetStrongForRecordStatus($this->updated, true) . '.</p>';
    } // function GetRecordStatus

    /**
    *   Determines if bold should be returned so it can be displayed for the result of GetRecordStatus()
    *   If the count is > 0, then <strong> is rreturn
    *
    *   @param  int     $count  The number of records added/affected
    *   @param  bool    $close  If the close table should be returned
    *
    *   @return string  The <strong> or </strong> tag
    */
    private function GetStrongForRecordStatus($count, $close = false) {
        return ($count > 0 ? (!$close ? '<strong>' : '</strong>') : '');
    } // function GetStrongForRecordStatus

    /**
    *   Re-orders the meta table disk data by table name, column name, display order
    *
    *   @return     string      HTML string of result for display
    */
    private function OrderMeta() {
        $returnHtml = '';

        if ($this->options['reorder_meta']) {
            $orderSql = "ALTER TABLE `" . CLAERO_META_TABLE . "` ORDER BY table_name, display_order, column_name";
            $orderQuery = $this->claeroDb->Query($orderSql);
            if ($orderQuery === false) {
                trigger_error('Query Failed: Failed to re-order meta table data: ' . $orderSql);
                $returnHtml .= '<p>Failed to re-order meta table data.</p>';
            } else {
                $returnHtml .= '<p>Meta table re-order successful: ' . $this->claeroDb->Info() . '</p>';
            }
        }

        return $returnHtml;
    } // function OrderMeta

    /**
    *   Adds the meta data if there is no meta data for the current table
    *   Get queries from GetSqlStatments()
    *
    *   @param      string      $tableName      Name of table to add
    *
    *   @return     string      HTML for output
    */
    public function AddTable($tableName) {
        $returnHtml = '';

        $tableName = $this->claeroDb->EscapeString($tableName);

        // first check to see if there is already meta data for the table
        $findSql = "SELECT id FROM `" . CLAERO_META_TABLE . "` WHERE table_name = '" . $tableName . "' AND column_name != '' LIMIT 1";
        $findQuery = $this->claeroDb->Query($findSql);
        if ($findQuery === false) {
            // query failed
            trigger_error('Query Failed: Failed to determine if records already exist for table being added to meta table: ' . $findSql);
        } else if ($findQuery->NumRows() > 0) {
            // there is already meta data
            $returnHtml .= '<p>There is already data for this table in the ' . CLAERO_META_TABLE . ' table. Use update instead.</p>';
        } else {
            // get the columns names
            $columnSql = "DESCRIBE `" . $tableName . "`";
            $columnQuery = $this->claeroDb->Query($columnSql);
            if ($columnQuery === false) {
                // query failed
                trigger_error('Query Failed: Failed to DESCRIBE table: ' . $columnSql);
            } else if ($columnQuery->NumRows() == 0) {
                // no columns found (unlikely)
                $returnHtml .= '<p>No columns found in ' . $tableName . '.</p>';
            } else {
                $returnHtml .= '<p>Found ' . $columnQuery->NumRows() . ' columns.';

                $i = 0;
                while ($columnQuery->FetchInto($column)) {
                    $this->AddField($tableName, $column, $i);
                    ++$i;
                } // while

                $returnHtml .= $this->GetRecordStatus($tableName);
                $returnHtml .= $this->OrderMeta();
            } // if count > 0
        } // if check for existing

        return $returnHtml;
    } // function AddTable

    /**
    *   Updates the meta data for a table
    *
    *   @param      string      $tableName      Table to update
    *
    *   @return     string      HTML string for display
    */
    public function UpdateTable($tableName) {
        $returnHtml = '';

        $tableName = $this->claeroDb->EscapeString($tableName);

        $findSql = "SELECT column_name FROM `" . CLAERO_META_TABLE . "` WHERE table_name = '" . $tableName . "' AND column_name != ''";
        $findQuery = $this->claeroDb->Query($findSql);
        if ($findQuery === false) {
            // query failed
            trigger_error('Query Failed:  Failed to find existing meta data for table: ' . $findSql);
        } else if ($findQuery->NumRows() == 0) {
            // no rows found in meta, should be using add
            $returnHtml .= '<p>There are no existing meta records for table "' . $tableName . '". Use add instead.</p>';
        } else {
            $existing = $findQuery->GetAllRows();

            $columnSql = "DESCRIBE `" . $tableName . "`";
            $columnQuery = $this->claeroDb->Query($columnSql);
            if ($columnQuery === false) {
                // query failed
                trigger_error('Query Failed: Failed to DESCRIBE table: ' . $columnSql);
            } else if ($columnQuery->NumRows() == 0) {
                // no columns found (unlikely)
                $returnHtml .= '<p>No columns found in ' . $tableName . '.</p>';
            } else {
                $returnHtml .= '<p>Found ' . $columnQuery->NumRows() . ' columns.';

                $i = count($existing);
                while ($columnQuery->FetchInto($column)) {
                    $field = $column['Field'];
                    // check to see if the field exists in the $existing array
                    $keyExisting = array_search($field, $existing);
                    if ($keyExisting !== false) {
                        // exists in both, so remove it from existing
                        unset($existing[$keyExisting]);
                    } else {
                        // doesn't exist, so add the field
                        ++$i;
                        $this->AddField($tableName, $column, $i);
                    }
                } // while

                if (count($existing) > 0) {
                    // we have more than one row still existing in $existing array, therefore we must have rows that have been removed
                    foreach ($existing as $field) {
                        $returnHtml .= $this->RemoveField($tableName, $field);
                    } // foreach
                } // if

                $returnHtml .= $this->GetRecordStatus($tableName);
                $returnHtml .= $this->OrderMeta();
            } // if
        } // if

        return $returnHtml;
    } // function UpdateTable

    /**
    *   Removes all meta records from meta table
    *   Get queries from GetSqlStatments()
    *
    *   @param      string      $tableName      Table to remove
    *
    *   @return     string      HTML string of results
    */
    public function DeleteTable($tableName) {
        $returnHtml = '<p>';

        $deleteSql = "DELETE FROM `" . CLAERO_META_TABLE . "` WHERE table_name = '" . $this->claeroDb->EscapeString($tableName) . "'";
        $deleteQuery = $this->claeroDb->Query($deleteSql);
        if ($deleteQuery === false || $deleteQuery === 0) {
            if ($deleteQuery === false) trigger_error('Query Failed: Failed to remove table from meta table.');
            $returnHtml .= 'Failed to remove meta records for table "' . $tableName . '".' . HEOL;
        } else {
            $this->removed += $deleteQuery;
            $this->metaQueries[] = $deleteSql;
        }

        $returnHtml .= $this->GetRecordStatus($tableName);
        $returnHtml .= $this->OrderMeta();

        return $returnHtml;
    } // function DeleteTable

    /**
    *   Removes the field specified by table name and column from meta table
    *
    *   @param      string      $tableName      Name of table in which the column existed
    *   @param      string      $field          Name of field to remove
    *
    *   @return     string      Text to display to user
    */
    private function RemoveField($tableName, $field) {
        $returnHtml = '';

        $deleteSql = "DELETE FROM `" . CLAERO_META_TABLE . "`
            WHERE table_name = '" . $this->claeroDb->EscapeString($tableName) . "' AND column_name = '" . $this->claeroDb->EscapeString($field) . "' LIMIT 1";
        $deleteQuery = $this->claeroDb->Query($deleteSql);
        if ($deleteQuery === false || $deleteQuery === 0) {
            if ($deleteQuery === false) trigger_error('Query Failed: Failed to remove record from meta table.');
            $returnHtml .= 'Failed to remove meta records for table "' . $tableName . '" and column "' . $field . '".' . HEOL;
        } else {
            ++$this->removed;
            $this->metaQueries[] = $deleteSql;
        }

        return $returnHtml;
    } // function RemoveField

    /**
    *   Adds a field to the meta table and possibly the foreign key table
    *   Receives the array from DESCRIBE
    *
    *   @param      string      $tableName  Name of table that column exists in (MUST BE ALREADY ESCAPED)
    *   @param      array       $column     1 row from a DESCRIBE query
    *   @param      int         $i          The display order (default 0)
    *
    *   @todo       add char/float/decimals/int to automatic detection
    */
    private function AddField($tableName, $column, $i = 0) {
        $foreignKey = false;
        $field = $this->claeroDb->EscapeString($column['Field']);
        $columnLabel = $this->claeroDb->EscapeString(ucwords(str_replace('_', ' ', $field)));
        $type = $column['Type'];

        $insertSql = "INSERT INTO `" . CLAERO_META_TABLE . "`
            (table_name, column_name, label, search_flag, edit_flag, display_flag, view_flag, required_flag, form_type,
            source_table, id_field, name_field, form_value, field_size, max_length, min_width, display_order)
            VALUES ";
        $foreignSql = "INSERT INTO `" . CLAERO_FOREIGN_TABLE . "` (name, table_name, column_name, foreign_table, foreign_column, delete_foreign_flag) VALUES ";

        switch ($field) {
            case 'id' :
                $insertSql .= " ('{$tableName}', '{$field}', '{$columnLabel}', 0, 1, 0, 0, 0, 'hidden',
                    '', '', '', '', '', '', '', {$i});";
                break;

            case 'search_flag':
            case 'edit_flag':
            case 'display_flag':
                $insertSql .= " ('{$tableName}', '{$field}', '{$columnLabel}', 1, 1, 1, 1, 0, 'checkbox',
                    '', '', '', '', '', '', '', {$i});";
                break;

            case 'expiry_date' :
            case CLAERO_EDIT_EXPIRY_COLUMN :
                $insertSql .= " ('{$tableName}', '{$field}', '{$columnLabel}', 0, 0, 0, 0, 0, 'datetime',
                    '', '', '', '', '', '', '', {$i});";
                break;

            case 'password':
                $insertSql .= " ('{$tableName}', '{$field}', '{$columnLabel}', 0, 1, 0, 0, 0, 'password',
                    '', '', '', '', '{$this->size}', '64', '', {$i});";
                break;

            default:
                $searchFlag = $editFlag = $displayFlag = $viewFlag = 1;
                // get the last _ and then from there to the end of the string
                $lastFieldPart = substr($field, strrpos($field, "_"));

                // determine the type of field for the meta data
                $fieldType = 'text';
                if ($lastFieldPart == '_flag' || $type == 'tinyint(1)') $fieldType = 'checkbox';
                if ($type == 'text') $fieldType = 'text_area';
                if ($type == 'date') $fieldType = 'date';
                if ($type == 'datetime' || $type == 'timestamp') $fieldType = 'datetime';
                if (strpos($field, 'filename') !== false && strpos($field, 'orig') === false) {
                    $fieldType = 'file';
                    $searchFlag = 0;
                }
                if (strpos($field, 'original_filename') !== false) {
                    $editFlag = $displayFlag = $viewFlag = 0;
                }

                // determine the length of the db field
                $maxLength = '';

                if ($fieldType == 'text' && (substr($type, 0, 7) == 'varchar' || substr($type, 0, 7) == 'tinyint')) {
                    $maxLength = substr($type, 8, -1);
                } else if ($fieldType == 'text' && substr($type, 0, 8) == 'smallint') {
                    $maxLength = substr($type, 9, -1);
                } else if ($fieldType == 'text' && substr($type, 0, 9) == 'mediumint') {
                    $maxLength = substr($type, 10, -1);
                } else if ($fieldType == 'text_area') {
                    $maxLength = 4;
                }

                $size = $this->size;
                if (substr($type, 0, 7) == 'tinyint' || substr($type, 0, 8) == 'smallint' || substr($type, 0, 9) == 'mediumint') {
                    $size = $maxLength;
                }

                if ($size > $maxLength && $fieldType != 'text_area') $size = $maxLength;

                if ($lastFieldPart == "_id") {
                    // because the last part of the field is _id, add a select with a foreign key record
                    $colNameWOId = strtolower(substr($field, 0, -3)); // remove the last 3 characters to get the name of the field
                    $columnLabel = substr($columnLabel, 0, -3); // remove " Id" from label

                    // try to determine if the related table has a date_expired column (column name based on CLAERO_EDIT_EXPIRY_COLUMN)
                    // if if does, add WHERE (date_expired = 0 OR date_expired > NOW()) to the SQL
                    $expireSql = '';
                    $displayOrderSql = 'name';
                    $showTableSql = "SHOW TABLES LIKE '{$colNameWOId}'";
                    $showTableQuery = $this->claeroDb->Query($showTableSql);
                    if ($showTableQuery === false) {
                        trigger_error('Query Error: Failed to check for related table ' . $showTableSql, E_USER_ERROR);
                    } else if ($showTableQuery->NumRows() > 0) {
                        // foreign table exists
                        // see if it has a date_expired column
                        $descSql = "DESCRIBE `{$colNameWOId}` '" . CLAERO_EDIT_EXPIRY_COLUMN . "'";
                        $descQuery = $this->claeroDb->Query($descSql);
                        if ($descQuery === false) {
                            trigger_error('Query Error: Failed to check for date expired column in related table ' . $descSql, E_USER_ERROR);
                        } else if ($descQuery->NumRows() > 0) {
                            $expireSql = " WHERE (" . CLAERO_EDIT_EXPIRY_COLUMN . " = 0 OR " . CLAERO_EDIT_EXPIRY_COLUMN . " > NOW())";
                        }

                        // see if we have a display_order column
                        $descSql = "DESCRIBE `{$colNameWOId}` '" . CLAERO_EDIT_DISPLAY_COLUMN . "'";
                        $descQuery = $this->claeroDb->Query($descSql);
                        if ($descQuery === false) {
                            trigger_error('Query Error: Failed to check for display order column in related table ' . $descSql, E_USER_ERROR);
                        } else if ($descQuery->NumRows() > 0) {
                            $displayOrderSql = CLAERO_EDIT_DISPLAY_COLUMN . ", name";
                        }
                    }

                    $insertSql .= " ('{$tableName}', '{$field}', '{$columnLabel}', 1, 1, 1, 1, 0, 'select',
                        'SELECT id, name FROM {$colNameWOId}{$expireSql} ORDER BY {$displayOrderSql}', 'id', 'name', '', '', '', '', {$i});";
                    // prepare foreign key query
                    $foreignKey = true;
                    $foreignSql .= " ('{$tableName} - {$field}', '{$tableName}', '{$field}', '{$colNameWOId}', 'id', 0);";

                } else if (in_array($fieldType, array('checkbox', 'date', 'datetime'))) {
                    if ($fieldType == 'checkbox' && strpos($columnLabel, 'Flag') > 1) $columnLabel = substr($columnLabel, 0, -5); // remove " Flag" from label
                    // these types do not have size or max length
                    $insertSql .= " ('{$tableName}', '{$field}', '{$columnLabel}', 1, 1, 1, 1, 0, '{$fieldType}',
                        '', '', '', '', '', '', '', {$i});";

                } else {
                    $insertSql .= " ('{$tableName}', '{$field}', '{$columnLabel}', {$searchFlag}, {$editFlag}, {$displayFlag}, {$viewFlag}, 0, '{$fieldType}',
                        '', '', '', '', '{$size}', '{$maxLength}', '', {$i});";
                } // if
                break;
        }

        if ($this->claeroDb->Query($insertSql) > 0) {
            ++$this->added;
            $this->metaQueries[] = $insertSql;
        } else {
            trigger_error('Query Failed: Failed to add record to ' . CLAERO_META_TABLE . ' for table "' . $tableName . '" field "' . $field . '": ' . $insertSql);
        }
        if ($foreignKey && $this->addForeign) {
            if ($this->claeroDb->Query($foreignSql) > 0) {
                ++$this->foreignAdded;
                $this->foreignQueries[] = $foreignSql;
            } else {
                trigger_error('Query Failed: Failed to add a record to ' . CLAERO_FOREIGN_TABLE . ' for table "' . $tableName . '" field "' . $field . '": ' . $foreignSql);
            }
        }
    } // function AddField

    public function ReorderTable($tableName) {
        $returnHtml = '';

        $tableName = $this->claeroDb->EscapeString($tableName);

        $columnSql = "DESCRIBE `" . $tableName . "`";
        $columnQuery = $this->claeroDb->Query($columnSql);
        if ($columnQuery === false) {
            // query failed
            trigger_error('Query Failed: Failed to DESCRIBE table: ' . $columnSql);
        } else if ($columnQuery->NumRows() == 0) {
            // no columns found (unlikely)
            $returnHtml .= '<p>No columns found in ' . $tableName . '.</p>';
        } else {
            $returnHtml .= '<p>Found ' . $columnQuery->NumRows() . ' columns.';

            $i = 0;
            while ($columnQuery->FetchInto($column)) {
                $updateSql = "UPDATE `" . CLAERO_META_TABLE . "` SET display_order = {$i} WHERE table_name = '" . $tableName . "' AND column_name = '" . $this->claeroDb->EscapeString($column['Field']) . "' LIMIT 1";
                $updateQuery = $this->claeroDb->Query($updateSql);
                if ($updateQuery === false) {
                    trigger_error('Query Error: Failed to change order for table ' . $tableName . ' and column ' . $column['Field'] . ' ' . $this->claeroDb->GetLastQuery(), E_USER_ERROR);
                } else {
                    if ($updateQuery > 0) ++$this->updated;
                    $this->metaQueries[] = $updateSql;
                }

                ++$i;
            }
        }

        $returnHtml .= $this->GetRecordStatus($tableName);

        return $returnHtml;
    } // function ReorderTable
} // class ClaeroMeta