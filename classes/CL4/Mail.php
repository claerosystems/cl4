<?php defined('SYSPATH') or die('No direct access allowed.');

if ( ! Kohana::load(Kohana::find_file('vendor', 'phpmailer/class.phpmailer'))) {
	throw new Kohana_Exception('Unable to find PHPMailer. Ensure it\'s in a vendor folder');
}

class CL4_Mail extends PHPMailer {
	/**
	* The default mail config to use, unless otherwise specified
	* @var  string
	*/
	public static $default = 'default';

	/**
	* If we should perform debug type actions
	* @var bool
	*/
    protected $debug = FALSE;

	/**
	* Config for adding a user to To field using a query
	* @var array
	*/
	protected $user_table = array(
		'model' => 'User',
		'email_field' => 'username',
		'first_name_field' => 'first_name',
		'last_name_field' => 'last_name',
	);

	/**
	*   The email to send emails to when in debug
	*   @var    string
	*/
	protected $log_email;

	/**
	* Constructor, sets up smtp using config
	*
	* @param string $config The config in config/cl4orm to use (defaults to Mail::$default)
	* @param      array       $options    Options for the object
	*           ['from'] => the email from which all emails will come from, if not sent then will use SITE::$emailFrom if it's set
	*           ['from_name'] => the name from which the email will come from (attached to the email address), if not sent then will use SITE::$emailFromName if it's set
	*           ['log_email'] => the email address to send emails to while in dev, if not sent then will use SITE::$logEmail if it's set
	*           ['model'] => Table where the email address for a person (likely a user) is stored; default user
	*           ['email_field'] => The field where the email address is stored, default username
	*           ['first_name_field'] => Field that contains the person's first name; default first_name
	*           ['last_name_field'] => Field that contains the person's last namel; default last_name
	* 			[char_set] => The character set for the emails
	*/
	public function __construct($config = NULL, $options = array()) {
		if ($config === NULL) {
			$config = Mail::$default;
		}

		// Set default options
		$config_options = Kohana::$config->load('cl4mail.' . $config);
		$default_options = Kohana::$config->load('cl4mail.default');
		$config_options += $default_options;
		$options += $config_options;

		// Run PHPMailer constructor
		parent::__construct($options['phpmailer_throw_exceptions']);

		// Are we in debug mode for this mailing?
		$this->debug = (bool) $options['debug'];

		// Set the language this object, does not affect the message, only affects error messages
		$phpmailer_loc = str_replace('class.phpmailer.php', 'language/', Kohana::find_file('vendor', 'phpmailer/class.phpmailer'));
		$this->SetLanguage($options['language'], $phpmailer_loc);

		// Set the character set for the email
		if ( ! empty($options['char_set'])) $this->CharSet = $options['char_set'];

		// Set the from email, name and log email (used in dev)
		if ( ! empty($options['from'])) $this->From = $options['from'];
		if ( ! empty($options['from_name'])) $this->FromName = $options['from_name'];
		if ( ! empty($options['log_email'])) $this->log_email = $options['log_email'];
		if ( ! empty($options['reply_to']['email'])) $this->AddReplyTo($options['reply_to']['email'], $options['reply_to']['name']);

		// Set the values of the user table where the user's email and name can be retrieved from
		if ( ! empty($options['user_table']['model'])) $this->user_table['model'] = $options['user_table']['model'];
		if ( ! empty($options['user_table']['email_field'])) $this->user_table['email_field'] = $options['user_table']['email_field'];
		if ( ! empty($options['user_table']['first_name_field'])) $this->user_table['first_name_field'] = $options['user_table']['first_name_field'];
		if ( ! empty($options['user_table']['last_name_field'])) $this->user_table['last_name_field'] = $options['user_table']['last_name_field'];

		// If we're using PHP's built-in mailer
		if (empty($options['mailer']) || $options['mailer'] == 'sendmail') {
			$this->IsMail();
		// If we're using an SMTP server
		} else if ($options['mailer'] == 'smtp') {
			$this->IsSMTP();

			if ( ! empty($options['smtp']['host'])) $this->Host = $options['smtp']['host'];
			if ( ! empty($options['smtp']['port'])) $this->Port = $options['smtp']['port'];
			if ( ! empty($options['smtp']['timeout'])) $this->Timeout = $options['smtp']['timeout'];

			// If using secure connection, this will be a type of security: ssl or tls
			if ( ! empty($options['smtp']['secure'])) $this->SMTPSecure = $options['smtp']['secure'];

			// if the username is not set or empty, then don't login
			if ( ! empty($options['smtp']['username'])) {
				$this->SMTPAuth = TRUE;
				$this->Username = $options['smtp']['username'];
				if ( ! empty($options['smtp']['password'])) $this->Password = $options['smtp']['password'];
			} // if
		} // if
	} // function __construct

	/**
	*   Adds a user based on their user_id
	*   Debug checking is done in AddAddress and AddBCC
	*
	*   @param      int     $user_id     The user id
	*
	*   @return     bool        true if the user was found and added, false if they couldn't be found
	*/
	public function AddUser($user_id) {
		$user = ORM::factory('User', $user_id);

		$add_status = false;

		if ($user->loaded()) {
			$email_field = $this->user_table['email_field'];
			$first_name_field = $this->user_table['first_name_field'];
			$last_name_field = $this->user_table['last_name_field'];
			$add_status = $this->AddAddress($user->$email_field, $user->$first_name_field . ' ' . $user->$last_name_field);
			if ( ! empty($this->log_email)) $this->AddBCC($this->log_email);
		} else {
			throw new phpmailerException('Unable to find user to add');
		}

		return $add_status;
	}

	/**
	*   Adds a BCC address, Calls the PHPMailer AddBCC() checking for debug first
	*
	*   @param      string      $address
	*   @param      string      $name
	*/
	public function AddBCC($address, $name = '') {
		if ($this->debug) {
			return parent::AddBCC($this->log_email, $name);
		} else {
			return parent::AddBCC($address, $name);
		}
	}

	/**
	*   Adds an address, Calls the PHPMailer AddAddress() checking for debug first
	*
	*   @param      string      $address
	*   @param      string      $name
	*/
	public function AddAddress($address, $name = '') {
		if ($this->debug) {
			return parent::AddAddress($this->log_email, $name);
		} else {
			return parent::AddAddress($address, $name);
		}
	}

	/**
	*   Adds a CC address, Calls the PHPMailer AddCCs() checking for debug first
	*
	*   @param      string      $address
	*   @param      string      $name
	*/
	public function AddCC($address, $name = '') {
		if ($this->debug) {
			return parent::AddCC($this->log_email, $name);
		} else {
			return parent::AddCC($address, $name);
		}
	}

	/**
	*   Adds multiple email addresses from a string or array to the email
	*   Emails and names in a string can be separated by commans or semi colons
	*
	*   @param  string/array    $addresses      Addresses to add to email, can be a string separated by commas or semi colons or an array
	*   @param  string/array    $names          Names for email addresses formatted the same way as the email address; if only a string with no separators the same name will be used on all the emails
	*/
	public function AddMultipleAddress($addresses, $names = '') {
		if (is_string($addresses) && (strpos($addresses, ',') !== false || strpos($addresses, ';') !== false)) {
			$addressArray = Arr::explode_on_multiple($addresses, array(',', ';'));
		} else if (is_array($addresses)) {
			$addressArray = $addresses;
		} else {
			throw new phpmailerException('The addresses received are not an array or a string');
		}

		if (is_string($names) && (strpos($names, ',') !== false || strpos($names, ';') !== false)) {
			$namesArray = Arr::explode_on_multiple($names, array(',', ';'));
		} else if (is_string($names)) {
			$namesArray = $names;
		} else if (is_array($names)) {
			$namesArray = $names;
		} else {
			throw new phpmailerException('The names received were not an array or string');
		}

		$add_status = TRUE;
		try {
			foreach ($addressArray as $key => $address) {
				if (is_array($namesArray)) {
					$_add_status = $this->AddAddress(trim($address), isset($namesArray[$key]) ? $namesArray[$key] : '');
				} else {
					$_add_status = $this->AddAddress(trim($address), $namesArray);
				}
				if ( ! $_add_status) $add_status = FALSE;
			} // foreach
		} catch (Exception $e) {
			throw $e;
		}

		return $add_status;
	} // function AddMultipleAddress

	/**
	* Add an array of addresses
	*
	* @param array $array
	* @return bool status of AddAddress()
	*/
	public function AddAddressArray(array $array) {
		$add_status = TRUE;

		try {
			foreach ($array as $email => $name) {
				$_add_status = $this->AddAddress($email, $name);
				if ( ! $_add_status) $add_status = FALSE;
			}
		} catch (Exception $e) {
			throw $e;
		}

		return $add_status;
	}

	/**
	* Adds the log address as a BCC
	*
	* @return bool status of AddAddress()
	*/
	public function AddLogBCC() {
		return $this->AddBCC($this->log_email);
	}

	// ***************************************
	// Deprecated method names that may eventually be removed.
	// ***************************************

	public function add_user($user_id) {
		return $this->AddUser($user_id);
	}

	public function add_multiple_addresses($addresses, $names = '') {
		return $this->AddMultipleAddress($addresses, $names);
	}

	public function add_address_array(array $array) {
		return $this->AddAddressArray($array);
	}

	public function add_log_bcc() {
		return $this->AddLogBCC();
	}
} // class CL4_Mail