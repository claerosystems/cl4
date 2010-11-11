<?php defined('SYSPATH') or die('No direct access allowed.');

class Claero_Exception_File extends Kohana_Exception {
	const NO_FILES_RECEIVED = 1;
	const FILE_NOT_SET = 2;
	const PHP_FILE_UPLOAD_ERROR = 3;
	const NOT_UPLOADED_FILE = 4;
	const DESTINATION_FILE_EXISTS = 5;
	const MOVE_UPLOADED_FILE_FAILED = 6;
	const FILE_DOES_NOT_EXIST = 7;
	const MOVE_FILE_FAILED = 8;
	const COPY_FILE_FAILED = 9;
	const DELETE_FILE_FAILED = 10;
	const IS_NOT_REGULAR_FILE = 11;
	const DOWNLOAD_HEADERS_CANT_BE_SENT = 12;
	const ID_COPY_FAILED = 13;
	const EXTENSION_NOT_ALLOWED = 14;
	const MIME_NOT_ALLOWED = 15;
	const DESTINATION_FOLDER_DOESNT_EXIST = 16;
	const DESTINATION_MKDIR_FAILED = 17;
} // class