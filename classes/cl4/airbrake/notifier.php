<?php
/**
 * Airbrake notifier class.
 * Used within cl4_Exception to notify Airbrake of an error.
 * This is very similar to Andrew Hutchings notifier: https://github.com/ahutchings/kohana-hoptoad
 *
 * @package    cl4
 * @category   Exception
 * @author     Claero Systems
 * @copyright  (c) 2010 Claero Systems
 */
class cl4_Airbrake_Notifier {
	/**
	 * @var  string  The API key for the application.
	 */
	public static $api_key;

	/**
	 * @var  string  The Airbrake endpoint, where the XML data/notification will be sent.
	 */
	public static $endpoint = 'https://airbrake.io/notifier_api/v2/notices';

	/**
	 * @var  string  The version of the Airbrak API being used
	 */
	protected static $api_version = '2.0';

	/**
	 * @var  string  The version number of the cl4 notifier client submitting the request.
	 */
	protected static $notifier_version = 'v1';

	/**
	 * @var  string  The name of the notifier client submitting the request.
	 */
	protected static $notifier_name = 'cl4-airbrake-notifier';

	/**
	 * @var  string  A URL at which more information can be obtained concerning the notifier client.
	 */
	protected static $notifier_url = 'https://github.com/claerosystems/cl4';

	/**
	 * @var  Airbrake_Notifier  The Airbrake instance.
	 */
	protected static $_instance;

	/**
	 * Singleton pattern
	 *
	 * @return  Airbrake_Notifier
	 */
	public static function instance() {
		if ( ! isset(Airbrake_Notifier::$_instance)) {
			// Create a new session instance
			Airbrake_Notifier::$_instance = new Airbrake_Notifier();
		}

		return Airbrake_Notifier::$_instance;
	}

	/**
	 * Sets the exception.
	 *
	 * @return  Airbrake_Notifier
	 */
	public function exception($e) {
		$this->_exception = $e;

		return $this;
	}

	/**
	 * Sends the notification XML to Hoptoad.
	 *
	 * @return  void
	 */
	public function notify() {
		$request = Request::factory(self::$endpoint)
			->method('POST')
			->headers('Content-Type', 'text/xml; charset=utf-8');

		// if HTTPS, then disable verifying the SSL certificate
		// @todo this doesn't seem right, Airbrake's SSL certificate should be valid
		if (strpos(self::$endpoint, 'https://') !== FALSE) {
			$request->client()
				->options(CURLOPT_SSL_VERIFYPEER, 0)
				->options(CURLOPT_SSL_VERIFYHOST, 0);
		}

		$request->body($this->build_notice())
			->execute();
	} // function notify

	/**
	 * Renders the XML notice.
	 *
	 * @return  string
	 */
	public function build_notice() {
		$xml = new SimpleXMLElement('<notice />');
		$xml->addAttribute('version', self::$api_version);

		// Add api-key
		$xml->addChild('api-key', self::$api_key);

		// Build notifier subelement
		$notifier = $xml->addChild('notifier');
		$notifier->addChild('name', self::$notifier_name);
		$notifier->addChild('version', self::$notifier_version);
		$notifier->addChild('url', self::$notifier_url);

		// Build error subelement
		$error = $xml->addChild('error');
		$error->addChild('class', get_class($this->_exception));
		$error->addChild('message', $this->_exception->getMessage());
		$this->add_xml_backtrace($error);

		// Build request subelement
		$request = $xml->addChild('request');
		$request->addChild('url', Request::detect_uri());
		$request->addChild('component', Request::current()->controller());
		$request->addChild('action', Request::current()->action());

		if (isset($_REQUEST)) $this->add_xml_vars($request, 'params', $_REQUEST);
		if (isset($_SESSION)) $this->add_xml_vars($request, 'session', $_SESSION);

		if (isset($_SERVER)) {
			$cgi_data = (isset($_ENV) AND ! empty($_ENV))
				? array_merge($_SERVER, $_ENV)
				: $_SERVER;

			$this->add_xml_vars($request, 'cgi-data', $cgi_data);
		}

		// Build server-environment subelement
		$server = $xml->addChild('server-environment');
		$server->addChild('project-root', DOCROOT);
		$server->addChild('environment-name', $this->environment_name());

		return $xml->asXML();
	} // function build_notice

	/**
	 * Add a Airbrake backtrace to the XML.
	 *
	 * @author  Rich Cavanaugh
	 * @return  void
	 */
	public function add_xml_backtrace($parent) {
		$backtrace = $parent->addChild('backtrace');
		$line_node = $backtrace->addChild('line');
		$line_node->addAttribute('file', $this->_exception->getFile());
		$line_node->addAttribute('number', $this->_exception->getLine());

		foreach ($this->_exception->getTrace() as $entry)
		{
			if (isset($entry['class']) AND $entry['class'] === 'Airbrake_Notifier')
				continue;

			$line_node = $backtrace->addChild('line');
			$line_node->addAttribute('file', Arr::get($entry, 'file', 'unknown'));
			$line_node->addAttribute('number', Arr::get($entry, 'line', 'unknown'));
			$line_node->addAttribute('method', $entry['function']);
		}
	} // function add_xml_backtrace

	/**
	 * Add a Hoptoad var block to the XML.
	 *
	 * @author  Rich Cavanaugh
	 * @author  Andrew Hutchings
	 * @return  void
	 */
	public function add_xml_vars($parent, $key, $source) {
		if (empty($source)) return;

		// If the key exists in the parent return it, otherwise create it
		$node = $parent->xpath("$key") ? $parent->$key : $parent->addChild($key);

		foreach ($source as $key => $val) {
			if (is_array($val)) {
				foreach ($val as $key1 => $val1) {
					$this->addXmlVars($parent, $node->getName(), array($key.'['.$key1.']' => $val1));
				}
			} else {
				$var_node = $node->addChild('var', $val);
				$var_node->addAttribute('key', $key);
			}
		}
	} // function add_xml_vars

	/**
	 * Returns a string representation of the environment name.
	 *
	 * @return  string
	 */
	public function environment_name() {
		// Find all constants in the Kohana class
		$reflection = new ReflectionClass('Kohana');
		$constants  = $reflection->getConstants();

		// Return the constant name for the current environment
		return array_search(Kohana::$environment, $constants, TRUE);
	}
} // class