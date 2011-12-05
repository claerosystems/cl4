<?php
/**
 * Hoptoad base class
 *
 * @package    Hoptoad
 * @author     Andrew Hutchings
 * @copyright  (c) 2011 Andrew Hutchings
 */
class cl4_Airbrake {
    // Hoptoad Notifier API endpoint
    const ENDPOINT = 'http://airbrake.io/notifier_api/v2/notices';

    // The version of the API being used
    const API_VERSION = '2.0';

    // The version number of the notifier client submitting the request
    const NOTIFIER_VERSION = 'v0.0.5';

    // The name of the notifier client submitting the request
    const NOTIFIER_NAME = 'kohana-hoptoad';

    // A URL at which more information can be obtained concerning the notifier client
    const NOTIFIER_URL = 'https://github.com/ahutchings/kohana-hoptoad';

    public static $api_key;

    // Hoptoad instance
    protected static $_instance;

    /**
     * Singleton pattern
     *
     * @return  Airbrake2
     */
    public static function instance()
    {
        if ( ! isset(Airbrake2::$_instance))
        {
            // Load the configuration for this type
            // $config = Kohana::config('hoptoad');

            // Create a new session instance
            Airbrake2::$_instance = new Airbrake2();
        }

        return Airbrake2::$_instance;
    }

    /**
     * Sets the exception.
     *
     * @return  Airbrake2
     */
    public function exception($e)
    {
        $this->_exception = $e;

        return $this;
    }

    /**
     * Renders the XML notice.
     *
     * @return  string
     */
    public function notice()
    {
        $xml = new SimpleXMLElement('<notice />');
        $xml->addAttribute('version', self::API_VERSION);

        // Add api-key
        $xml->addChild('api-key', self::$api_key);

        // Build notifier subelement
        $notifier = $xml->addChild('notifier');
        $notifier->addChild('name', self::NOTIFIER_NAME);
        $notifier->addChild('version', self::NOTIFIER_VERSION);
        $notifier->addChild('url', self::NOTIFIER_URL);

        // Build error subelement
        $error = $xml->addChild('error');
        $error->addChild('class', get_class($this->_exception));
        $error->addChild('message', $this->_exception->getMessage());
        $this->addXmlBacktrace($error);

        // Build request subelement
        $request = $xml->addChild('request');
        $request->addChild('url', Request::detect_uri());
        $request->addChild('component', NULL);
        $request->addChild('action', NULL);

        if (isset($_REQUEST)) $this->addXmlVars($request, 'params', $_REQUEST);
        if (isset($_SESSION)) $this->addXmlVars($request, 'session', $_SESSION);

        if (isset($_SERVER))
        {
            $cgi_data = (isset($_ENV) AND ! empty($_ENV))
                ? array_merge($_SERVER, $_ENV)
                : $_SERVER;

            $this->addXmlVars($request, 'cgi-data', $cgi_data);
        }

        // Build server-environment subelement
        $server = $xml->addChild('server-environment');
        $server->addChild('project-root', DOCROOT);
        $server->addChild('environment-name', $this->environment_name());

        return $xml->asXML();
    }

    /**
     * Sends the notification XML to Hoptoad.
     *
     * @return  void
     */
    public function notify()
    {
        Request::factory(self::ENDPOINT)
            ->method('POST')
            ->headers('Content-Type', 'text/xml; charset=utf-8')
            ->body($this->notice())
            ->execute();
    }

    /**
     * Add a Hoptoad backtrace to the XML.
     *
     * @author  Rich Cavanaugh
     * @return  void
     */
    public function addXmlBacktrace($parent)
    {
        $backtrace = $parent->addChild('backtrace');
        $line_node = $backtrace->addChild('line');
        $line_node->addAttribute('file', $this->_exception->getFile());
        $line_node->addAttribute('number', $this->_exception->getLine());

        foreach ($this->_exception->getTrace() as $entry)
        {
            if (isset($entry['class']) AND $entry['class'] === 'Airbrake2')
                continue;

            $line_node = $backtrace->addChild('line');
            $line_node->addAttribute('file', Arr::get($entry, 'file', 'unknown'));
            $line_node->addAttribute('number', Arr::get($entry, 'line', 'unknown'));
            $line_node->addAttribute('method', $entry['function']);
        }
    }

    /**
     * Add a Hoptoad var block to the XML.
     *
     * @author  Rich Cavanaugh
     * @author  Andrew Hutchings
     * @return  void
     */
    public function addXmlVars($parent, $key, $source)
    {
        if (empty($source)) return;

        // If the key exists in the parent return it, otherwise create it
        $node = $parent->xpath("$key") ? $parent->$key : $parent->addChild($key);

        foreach ($source as $key => $val)
        {
            if (is_array($val))
            {
                foreach ($val as $key1 => $val1)
                {
                    $this->addXmlVars($parent, $node->getName(), array($key.'['.$key1.']' => $val1));
                }
            }
            else
            {
                $var_node = $node->addChild('var', $val);
                $var_node->addAttribute('key', $key);
            }
        }
    }

    /**
     * Returns a string representation of the environment name.
     *
     * @return  string
     */
    public function environment_name()
    {
        // Find all constants in the Kohana class
        $reflection = new ReflectionClass('Kohana');
        $constants  = $reflection->getConstants();

        // Return the constant name for the current environment
        return array_search(Kohana::$environment, $constants, TRUE);
    }
}