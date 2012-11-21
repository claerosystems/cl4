<?php defined('SYSPATH') OR die('No direct access allowed.');
/**
 * Pagination links generator.
 *
 * @package    Claero
 * @author
 * @copyright  (c) 2010 Claero Systems
 */
class CL4_Pagination {
	// Merged configuration settings
	protected $config = array(
		'current_page'      => array('source' => 'query_string', 'key' => 'page'),
		'total_items'       => 0,
		'items_per_page'    => 10,
		'view'              => 'pagination/basic',
		'auto_hide'         => TRUE,
		'first_page_in_url' => FALSE,
	);

	// Current page number
	protected $current_page;

	// Total item count
	protected $total_items;

	// How many items to show per page
	protected $items_per_page;

	// Total page count
	protected $total_pages;

	// Item offset for the first item displayed on the current page
	protected $current_first_item;

	// Item offset for the last item displayed on the current page
	protected $current_last_item;

	// Previous page number; FALSE if the current page is the first one
	protected $previous_page;

	// Next page number; FALSE if the current page is the last one
	protected $next_page;

	// First page number; FALSE if the current page is the first one
	protected $first_page;

	// Last page number; FALSE if the current page is the last one
	protected $last_page;

	// Query offset
	protected $offset;

	// the number of items on the page
	protected $items_on_page = NULL;

	/**
	 * Creates a new Pagination object.
	 *
	 * @param   array  configuration
	 * @return  Pagination
	 */
	public static function factory(array $config = array()) {
		return new Pagination($config);
	}

	/**
	 * Creates a new Pagination object.
	 *
	 * @param   array  configuration
	 * @return  void
	 */
	public function __construct(array $config = array()) {
		// Overwrite system defaults with application defaults
		$this->config = $this->config_group() + $this->config;

		// Pagination setup
		$this->setup($config);
	}

	/**
	 * Retrieves a pagination config group from the config file. One config group can
	 * refer to another as its parent, which will be recursively loaded.
	 * Note: This is exactly the same as Kohana_Pagination::config_group() but uses Kohana v3.2's new method of loading the config
	 *
	 * @param   string  pagination config group; "default" if none given
	 * @return  array   config settings
	 */
	public function config_group($group = 'default') {
		// Load the pagination config file
		$config_file = Kohana::$config->load('pagination');

		// Initialize the $config array
		$config['group'] = (string) $group;

		// Recursively load requested config groups
		while (isset($config['group']) AND isset($config_file->$config['group'])) {
			// Temporarily store config group name
			$group = $config['group'];
			unset($config['group']);

			// Add config group values, not overwriting existing keys
			$config += $config_file->$group;
		}

		// Get rid of possible stray config group names
		unset($config['group']);

		// Return the merged config group settings
		return $config;
	} // function config_group

    /**
	 * Loads configuration settings into the object and (re)calculates pagination if needed.
	 * Allows you to update config settings after a Pagination object has been constructed.
	 * Note: this is exactly the same as Kohana_Pagination::setup(), but instead we merge the config arrays fully so that sub arrays get merged properly.
	 *
	 * @param   array   configuration
	 * @return  object  Pagination
	 */
    public function setup(array $config = array()) {
        if (isset($config['group'])) {
			// Recursively load requested config groups
			$config_group = $this->config_group($config['group']);
			$config['current_page'] += $config_group['current_page'];
			$config_group['current_page'] = $config['current_page'];
			$config += $config_group;
		}

		// Overwrite the current config settings
		$this->config = $config + $this->config;

		// Only (re)calculate pagination when needed
		if ($this->current_page === NULL || isset($config['current_page']) || isset($config['total_items']) || isset($config['items_per_page'])) {
			// Retrieve the current page number
			if ( ! empty($this->config['current_page']['page'])) {
				// The current page number has been set manually
				$this->current_page = (int) $this->config['current_page']['page'];
			} else {
				switch ($this->config['current_page']['source']) {
					case 'query_string' :
					case 'query_string2' :
						$this->current_page = (isset($_GET[$this->config['current_page']['key']]) ? (int) $_GET[$this->config['current_page']['key']] : 1);
						break;

					case 'route' :
						$this->current_page = (int) Request::current()->param($this->config['current_page']['key'], 1);
						break;
				} // switch
			} // if

			// Calculate and clean all pagination variables
			$this->total_items        = (int) max(0, $this->config['total_items']);
			$this->items_per_page     = (int) max(1, $this->config['items_per_page']);
			$this->total_pages        = (int) ceil($this->total_items / $this->items_per_page);
			$this->current_page       = (int) min(max(1, $this->current_page), max(1, $this->total_pages));
			$this->current_first_item = (int) min((($this->current_page - 1) * $this->items_per_page) + 1, $this->total_items);
			$this->current_last_item  = (int) min($this->current_first_item + $this->items_per_page - 1, $this->total_items);
			$this->previous_page      = ($this->current_page > 1) ? $this->current_page - 1 : FALSE;
			$this->next_page          = ($this->current_page < $this->total_pages) ? $this->current_page + 1 : FALSE;
			$this->first_page         = ($this->current_page === 1) ? FALSE : 1;
			$this->last_page          = ($this->current_page >= $this->total_pages) ? FALSE : $this->total_pages;
			$this->offset             = (int) (($this->current_page - 1) * $this->items_per_page);

            $this->items_on_page = $this->current_last_item - $this->current_first_item;

            // if we have more than 0 records on the page or we only have 1 item, then add 1 to the items on page because of the count starting at 0
			if ($this->items_on_page > 0 || ($this->current_last_item == $this->current_first_item && $this->items_on_page == 0 && $this->current_last_item != 0)) {
				++$this->items_on_page; // add 1
			}
		} // if

		// Chainable method
		return $this;
	} // function setup

	/**
	* Returns the number of items on the page
	*
	* @return  int
	*/
	public function get_items_on_page() {
		return $this->items_on_page;
	}

	/**
	 * Generates the full URL for a certain page.
	 * Note: This is exactly the same as Kohana_Pagination::url() except for the extra option
	 * of where to get the URL from so it's possible to exclude the query string from the current URL
	 *
	 * @param   integer  page number
	 * @return  string   page URL
	 */
	public function url($page = 1) {
		// Clean the page number
		$page = max(1, (int) $page);

		// No page number in URLs to first page
		if ($page === 1 && ! $this->config['first_page_in_url']) {
			$page = NULL;
		}

		switch ($this->config['current_page']['source']) {
			case 'query_string' :
				return URL::site(Request::current()->uri()) . URL::query(array($this->config['current_page']['key'] => $page));

			case 'query_string2' :
				return URL::site(Request::current()->route()->uri()) . URL::query(array($this->config['current_page']['key'] => $page));

			case 'route' :
				return URL::site(Request::current()->route()->uri(array($this->config['current_page']['key'] => $page))) . URL::query();
		}

		return '#';
	} // function url

	/**
	 * Checks whether the given page number exists.
	 *
	 * @param   integer  page number
	 * @return  boolean
	 * @since   3.0.7
	 */
	public function valid_page($page)
	{
		// Page number has to be a clean integer
		if ( ! Valid::digit($page))
			return FALSE;

		return $page > 0 AND $page <= $this->total_pages;
	}

	/**
	 * Renders the pagination links.
	 *
	 * @param   mixed   string of the view to use, or a Kohana_View object
	 * @return  string  pagination output (HTML)
	 */
	public function render($view = NULL)
	{
		// Automatically hide pagination whenever it is superfluous
		if ($this->config['auto_hide'] === TRUE AND $this->total_pages <= 1)
			return '';

		if ($view === NULL)
		{
			// Use the view from config
			$view = $this->config['view'];
		}

		if ( ! $view instanceof View)
		{
			// Load the view file
			$view = View::factory($view);
		}

		// Pass on the whole Pagination object
		return $view->set(get_object_vars($this))->set('page', $this)->render();
	}

	/**
	 * Renders the pagination links.
	 *
	 * @return  string  pagination output (HTML)
	 */
	public function __toString()
	{
		try
		{
			return $this->render();
		}
		catch(Exception $e)
		{
			Kohana_Exception::handler($e);
			return '';
		}
	}

	/**
	 * Returns a Pagination property.
	 *
	 * @param   string  property name
	 * @return  mixed   Pagination property; NULL if not found
	 */
	public function __get($key)
	{
		return isset($this->$key) ? $this->$key : NULL;
	}

	/**
	 * Updates a single config setting, and recalculates pagination if needed.
	 *
	 * @param   string  config key
	 * @param   mixed   config value
	 * @return  void
	 */
	public function __set($key, $value)
	{
		$this->setup(array($key => $value));
	}
} // class