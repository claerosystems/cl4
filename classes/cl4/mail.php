<?php defined('SYSPATH') or die('No direct access allowed.');

if ( ! Kohana::load(Kohana::find_file('vendor', 'phpmailer/class.phpmailer'))) {
	throw new Kohana_Exception('Unable to find PHPMailer. Ensure it\'s in a vendor folder');
}

class cl4_Mail extends PHPMailer {
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
		'model' => 'user',
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
	*   Constructor, sets up smtp
	*
	*   @param      array       $options    Options for the object
	*           ['from'] => the email from which all emails will come from, if not sent then will use SITE::$emailFrom if it's set
	*           ['from_name'] => the name from which the email will come from (attached to the email address), if not sent then will use SITE::$emailFromName if it's set
	*           ['log_email'] => the email address to send emails to while in dev, if not sent then will use SITE::$logEmail if it's set
	*           ['model'] => Table where the email address for a person (likely a user) is stored; default user
	*           ['email_field'] => The field where the email address is stored, default username
	*           ['first_name_field'] => Field that contains the person's first name; default first_name
	*           ['last_name_field'] => Field that contains the person's last namel; default last_name
	* 			[char_set] => The character set for the emails
	*/
    public function __construct($config = 'default', $options = array()) {
		$default_options = Kohana::config('cl4mail');

		$options += $default_options[$config];

		parent::__construct($options['phpmailer_throw_exceptions']);

		$this->debug = (bool) $options['debug'];

		$phpmailer_loc = str_replace('class.phpmailer.php', 'language/', Kohana::find_file('vendor', 'phpmailer/class.phpmailer'));
		$this->SetLanguage($options['language'], $phpmailer_loc);

		// set the character set for the email
		if ( ! empty($options['char_set'])) $this->CharSet = $options['char_set'];

		// set the from email, name and log email (used in dev)
		if ( ! empty($options['from'])) $this->From = $options['from'];
		if ( ! empty($options['from_name'])) $this->FromName = $options['from_name'];
		if ( ! empty($options['log_email'])) $this->log_email = $options['log_email'];

		// set the values of the user table where the user's email and name can be retrieved from
		if ( ! empty($options['model'])) $this->user_table['model'] = $options['model'];
		if ( ! empty($options['email_field'])) $this->user_table['email_field'] = $options['email_field'];
		if ( ! empty($options['first_name_field'])) $this->user_table['first_name_field'] = $options['first_name_field'];
		if ( ! empty($options['last_name_field'])) $this->user_table['last_name_field'] = $options['last_name_field'];

		if (empty($options['mailer']) || $options['mailer'] == 'sendmail') {
			$this->IsMail();

		} else if ($options['mailer'] == 'smtp') {
			$this->IsSMTP();
			if ( ! empty($options['smtp']['host'])) $this->Host = $options['smtp']['host'];
				// if the username is not set or empty, then don't login
				if ( ! empty($options['smtp']['username'])) {
					$this->SMTPAuth = true;
					$this->Username = $options['smtp']['username'];
				}
				if ( ! empty($options['smtp']['password'])) $this->Password = $options['smtp']['password'];
				if ( ! empty($options['smtp']['port'])) $this->Port = $options['smtp']['port'];
			}
		} // function

	/**
	*   Adds a user based on their user_id
	*   Debug checking is done in AddAddress and AddBCC
	*
	*   @param      int     $user_id     The user id
	*
	*   @return     bool        true if the user was found and added, false if they couldn't be found
	*/
	public function add_user($user_id) {
		$user = ORM::factory('user', $user_id);

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
	} // function

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
	} // function AddBCC

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
	} // function AddAddress

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
	} // function AddCC

	/**
	*   Adds multiple email addresses from a string or array to the email
	*   Emails and names in a string can be separated by commans or semi colons
	*
	*   @param  string/array    $addresses      Addresses to add to email, can be a string separated by commas or semi colons or an array
	*   @param  string/array    $names          Names for email addresses formatted the same way as the email address; if only a string with no separators the same name will be used on all the emails
	*/
	public function add_multiple_addresses($addresses, $names = '') {
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

		try {
			foreach ($addressArray as $key => $address) {
				if (is_array($namesArray)) {
					$add_status = $this->AddAddress(trim($address), isset($namesArray[$key]) ? $namesArray[$key] : '');
				} else {
					$add_status = $this->AddAddress(trim($address), $namesArray);
				}
			} // foreach
		} catch (phpmailerException $e) {
			throw $e;
		}

		return $add_status;
	} // function add_multiple_addresses

} // class cl4_Mail