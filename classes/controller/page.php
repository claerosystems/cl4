<?php defined('SYSPATH') or die('No direct script access.');

class Controller_Page extends Controller_Base {
	// load a web page in to the default template from file or database
	// special case for overview ages of web site 'sections'
	public function action_index() {
		$this->page = Request::instance()->param('page');
		$this->section = Request::instance()->param('section');

		// get the page from the static templates or database
		try {
			$this->template->body_html .= $this->get_static_template();
		} catch (Kohana_View_Exception $e) {
			$this->action_404();
			Claero::exception_handler($e);
		} catch (Exception $e) {
			Claero::exception_handler($e);
		}
	} // function action_index

	/**
	* Returns the view for the page specified, looking in appropriate language dir
	*
	* @param mixed $page
	*/
	protected function get_static_template() {
		try {
			$locale = (empty($this->locale) ? $this->allowed_languages[0] : $this->locale);

			$page = '';
			if ( ! empty($this->section)) $page .= $this->section . '/';
			$page .= $this->page;

			return View::factory('pages/' . $locale . '/' . $page);
		} catch (Exeception $e) {
			throw $e;
		}
	} // function get_static_template
} // class Controller_Page