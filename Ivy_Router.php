<?php
/**
 * SVN FILE: $Id: Ivy_Router.php 19 2008-10-02 07:56:39Z shadowpaktu $
 *
 * Project Name : Project Description
 *
 * @package className
 * @subpackage subclassName
 * @author $Author: shadowpaktu $
 * @copyright $Copyright$
 * @version $Revision: 19 $
 * @lastrevision $Date: 2008-10-02 08:56:39 +0100 (Thu, 02 Oct 2008) $
 * @modifiedby $LastChangedBy: shadowpaktu $
 * @lastmodified $LastChangedDate: 2008-10-02 08:56:39 +0100 (Thu, 02 Oct 2008) $
 * @license $License$
 * @filesource $URL: https://ivy.svn.sourceforge.net/svnroot/ivy/Ivy_Router.php $
 */
 
class Ivy_Router {
	
	 /*
	 * @the registry
	 */
	private $registry;

	/**
	 * @the controller path
	 */
	private $path = 'include/controller';

	private $args = array();
	
	public $navigation;
	
	private $get = array ();
	
	private $controller = 'index';
	
	private $action = 'index';
	
	function __construct () {
		
		(array) $parserArray = array ();
		(array) $array = array ();
		(int) $ivyadmin = 0;
		
		$parserArray['db']['type'] = 'ini';
		$parserArray['output']['type'] = 'borne';
		$parserArray['output']['theme'] = 'default';
		$parserArray['system']['unixformat'] = 'j M Y, H:i';
		
		$this->registry = Ivy_Registry::getInstance();
		
		$this->getController();
		
		$parser = parse_ini_file(IVYPATH . '/config/config.ini');

		if ($parser === false) {
			die('Unable to read config file: Ivy/config/config.ini');
		}
		
		
		foreach ($parser as $key => $value) {
			$var = explode('_', $key);
			$parserArray[ $var[0] ][ $var[1] ] = $value;
		}

		$this->registry->insertSystem('config', $parserArray);
		
		if (is_dir(SITEPATH . '/site/' . SITE)) {
			require	SITEPATH . '/site/' . SITE . '/system/array.php';
			$this->registry->insertSystem('keys', $array);
		}

		define('THEME', $parserArray['output']['theme']);
		
		$this->registry->insertSystem('config', $parserArray);
		
		
		
		require	IVYPATH . '/system/array.php';			
		$this->registry->insertSystem('keys', $array);

		
		$sessionRegistry = $this->registry->selectSession(0);
		
		
		if (is_readable(SITEPATH . '/site/' . SITE . '/controller/' . $this->controller . '.php')) {
			
			$this->path = SITEPATH . '/site/' . SITE . '/controller';
		
		} else if (is_readable(SITEPATH . '/core/extension/' . $this->controller . '/controller/' . $this->controller . '.php')) {
			
			$this->path = SITEPATH . '/core/extension/' . $this->controller . '/controller';
		
		} else {
			
			header("HTTP/1.0 404 Not Found");
			echo 'controller/' . $this->controller . '.php' . '<br />';
			die ('404 Not Found');
			
		}

		
	}
	
	/**
	 *
	 * @load the controller
	 *
	 * @access public
	 * @return void
	 */
	public function loader () {
		(string) $class = $this->controller . '_Controller';
		(object) $controller = '';
		(string) $action = '';
		(array) $array = array ();
		
		require $this->path . '/' . $this->controller . '.php';
		
		/*** a new controller class instance ***/
		$controller = new $class($this->registry);

		/*** check if the action is callable ***/
		(bool) $actionExists = false;
		if (is_callable(array($controller, $this->action)) === false) {
			trigger_error('The action: "' . $this->action . '" was not found');
			$this->action = $action = 'index';
			
			
		} else {
			$action = $this->action;
			$actionExists = true;
		}
		
		/* Pass the name of the class, not a declared handler */
		if (isset($this->get)) {
			$array = $this->get;
		}
		
		/**
		 * the _permissions method is run after the __constructors but before we call 
		 * the method we want. It's used to see if the user has permissions to access
		 * the method based on the navigatin array specified in the constructor
		 */
		$controller->_permission();
		
		if ($actionExists === false) {
			trigger_error('The action: "' . $this->action . '" was not found');
			die ('404 '. $this->action . ' not Found');			
		}

		if ($controller->authorised === 0 && $action != 'logout') {
			//$this->session->authenticate(0);

			/**
			 * Changed the called action from _login to _default to better handle when a user tries to access an action they shouldn't
			 *
			 * @datemodified 	17th August, 2017
			 * @author 			James Randell <james.randell@curtisfitchglobal.com>
			 */
			$action = '_denied';
		}

		/*** run the action ***/
		$controller->$action($array);

	}


	/**
	 *
	 * @get the controller
	 *
	 * @access private
	 *
	 * @return void
	 *
	 */
	private function getController () {
		$controller = (string) '';
		$action = (string) '';
		$getArray = array ();
		$otherArray = array ();
		
		$this->action = 'index';
		
		/**
		 * new part as of April 20th 2010
		 * 
		 * checks the PATH_INFO section of the SERVER global to fingure out the controller
		 * and action, instead og $_GET variables
		 *
		 * July 2011 - edited to allow better URI mapping
		 */
		if (isset($_SERVER['QUERY_STRING'])) {
			$queryParts = explode('/', $_SERVER['QUERY_STRING']);

			/**
			 * check for a start slash and remove the empty value is there is one
			 */
			if (!$queryParts[0]) {
				array_shift($queryParts);
			}
			
			/**
			 * assign controller
			 */
			$this->controller = $_GET['controller'] = ($queryParts[0]) ? $queryParts[0] : 'index';
			array_shift($queryParts);
			
			/**
			 * assign action
			 */
			$this->action = $_GET['action'] = ($queryParts[0]) ? $queryParts[0] : 'index';
			array_shift($queryParts);
			
			
			/**
			 * we find the remaining values and assign them
			 */
			foreach ($queryParts as $key => $value) {
				if ($key === 0 && is_numeric($value)) {
					$this->s = $_GET['s'] = $value;
				}
				
				$temp = $key + 1;
				$this->{$temp} = $_GET[$temp] = $value;
				
				if ($value == 'ajax') {
					$_GET['ajax'] = 'ajax';
				}
			}
			
		} else {
			if (isset($_GET['controller']) && !empty($_GET['controller'])) {
				$this->controller = $_GET['controller'];
			}
			if (isset($_GET['action']) && !empty($_GET['action'])) {
				$this->action = $_GET['action'];
			}
		}
		
		$this->get = $_GET;
		
		$this->get['controller'] = $this->controller;
		$this->get['action'] = $this->action;
		
		
		$otherArray['dateViewed'] = time();
		$otherArray['controller'] = $this->controller;
		$otherArray['action'] = $this->action;

		$arr = $this->registry->selectSystem('other');
		
		if (isset($_SERVER['REQUEST_URI'])) {
			$otherArray['script'] = basename($_SERVER['REQUEST_URI']);
			$fullname = 'http://' . $_SERVER['SERVER_NAME'] . $_SERVER['REQUEST_URI'];
		} else {
			$fullname = $_SERVER['SCRIPT_NAME'];
		}
		
		if (!isset($arr['referer'])) {
			
			$otherArray['referer'] = $fullname;
		}
		
		if (isset($_SERVER['HTTP_REFERER'])) {
			
			$arr['referer'] = $_SERVER['HTTP_REFERER'];
			if ($fullname != $_SERVER['HTTP_REFERER']) {
				$otherArray['referer'] = $_SERVER['HTTP_REFERER'];
			} else {
				$otherArray['referer'] = $arr['referer'];
			}
		} else {
			$arr['referer'] = '';
		}

		$this->registry->insertSystem('get' , $this->get);
		$this->registry->insertSystem('other', $otherArray);
	}


}



?>