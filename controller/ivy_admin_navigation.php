<?php
/**
 * CMS module for IVY3.0.
 *
 * Content Management Module for IVY3.0
 *
 * @author James Randell <james.randell@hotmail.co.uk>
 * @version 0.1
 * @package Navigation
 */
 
class Ivy_Admin_Navigation_Controller extends ivy_admin {


	public function index ()
	{	
		$nav[100]['title'] = 'Show me';
		$nav[100]['action'] = 'results';
		$nav[100]['menu'] = 'context';
		$nav[101]['title'] = 'Exidsadsadsasting sites';
		$nav[101]['action'] = 'index';
		$nav[101]['menu'] = 'context';
	
		
		if (isset($_GET['site'])) {
			foreach ($nav as $key => $data) {
				$nav[$key]['query'] = 'site=' . $_GET['site'];
				
			}
		}
		$this->navigation->insert($nav);

	}
	
	
	public function rebuild ()
	{

		if (isset($_POST['submit'])) {
			$methodArray = $this->fileMethods();		
		
			$a['tableSpec']['pk'] = array('SITEID');
			$form = new Ivy_Database('ivy_navigation', $a);
			unset($a);
		
			$form->delete(array('SITEID'=>SITE));
			
			foreach ($methodArray as $controller => $data) {
				foreach ($data as $action => $stub) {
					$form->insert( $this->data($controller, $action) );
				}			
			}
			$this->stylesheet = 'default';
			$this->display->addText('<h2>Complete!</h2>');
		} else {			
			$this->display->addText('<p>This will remove <u>ALL</u> navigation for this application from the database and insert a new.</p>');
			$this->display->addText('<p>Any changes you have made to the navigation structure will be lost, are you sure you wish to continue?</p>');
		}		
	}
	
	/**
	  * Contains an array of change data when inserting nav items
	  *
	  * @access private
	  */
	private function data ($controller, $action)
	{
		$array['CONTROLLER'] = $controller;
		$array['ACTION'] = $action;
		$array['SITEID'] = SITE;
		$array['TITLE'] = (($action == 'index') ? 'Home' : ucfirst($action));
		$array['MENU'] = (($action == 'index') ? 'default' : 'none');
		$array['RANK'] = 10;
		$array['DATECREATED'] = time ();
		
		return $array;
	}
	
	/**
	  * Updates the databse with inserts and deletes based on the file
	  *
	  * Gethers method info from all controllers.
	  * Inserts any missing methods to the database.
	  * Deletes any extra methods that don't exist in the file.
	  * Inserts any missing content items that match file methods to the database.
	  * Delete any axtra content items that dont exits as methdos in the file.
	  *
	  * @access public
	  */
	public function update ()
	{

		
		$a['tableSpec']['pk'] = array('SITEID','CONTROLLER','ACTION');
		$form = new Ivy_Database('ivy_navigation', $a);	
		$cms = new Ivy_Database('ivy_cms', $a);
		unset($a);
		
		$fileArray = $this->fileMethods();
		$dbArray = $this->dbMethods();
		$contentArray = $this->dbContent();

		/* loop through the file array for extra methods and content that don't exist in the db and insert them */
		foreach ($fileArray as $controller => $data) {
			foreach ($data as $action => $stub) {
				if (!isset($dbArray[$controller][$action])) {
					$form->insert( $this->data($controller, $action) );
				}
				if (!isset($contentArray[$controller][$action])) {
					$cms->insert( $this->data($controller, $action) );
				}
			}
		}
		/* loop through the db array for extra methods that don't exist in the file and delete them */
		foreach ($dbArray as $controller => $data) {
			foreach ($data as $action => $stub) {
				if (!isset($fileArray[$controller][$action])) {
					$form->delete( array(	'SITEID'		=>	SITE,
											'CONTROLLER'=>	$controller,
											'ACTION'	=>	$action
										)
								);
				}
			}
		}
		/* loop through hte content array and remove items that don't exist in the file anymore */
		foreach ($contentArray as $controller => $data) {
			foreach ($data as $action => $stub) {
				if (!isset($fileArray[$controller][$action])) {
					$cms->delete( array(	'SITEID'	=>	SITE,
											'CONTROLLER'=>	$controller,
											'ACTION'	=>	$action
										)
								);
				}
			}
		}
		
		
		$this->display->addText('<p><em>Complete!</em></p>');
	}
	
	
public function results ()
{

		
	$form = new Ivy_Database('ivy_navigation');
		
	$form->order = 'CONTROLLER, ACTION DESC';
	$form->select("SITEID = '" . $_GET['site'] . "'");
		
	$this->display->addResult($form->id);
	$this->display->special('default', array('s'=>'site=' . $_GET['site'] . '&s'));
}
	
	public function detail ($get)
	{

		
		$s = $get['s'];
		
		$a['fieldSpec']['RANK']['options']['where'] = "GROUPNAME = '" . $get['site'] . "'";
		$form = new Ivy_Database('ivy_navigation', $a);
		unset($a);
		
		if ($form->update() !== FALSE) {
			$this->redirect('results&site=' . $get['site']);
		}
		
		$form->select("NAVIGATIONID = '$s'");
		
		$this->display->addForm($form->id);
		
		
	}

	public function deletesystem ()
	{
		$this->stylesheet = 'detail';
		
		// here we make the sysid the PK so it works on all items under that id.
		$a['tableSpec']['pk'] = array('SITEID');
		$form = new Ivy_Database('ivy_navigation', $a);
		$array['SITEID'] = SITE;
		$form->delete($array);
		
	
	}
	
	/**
	  * Loops through all controllers retrieving the methods that can be called via the URL
	  *
	  * Looks at the controller directroy under the current application, uses Reflection to 
	  * find the public and non-inherited methods (URL and application specific ones only please!)
	  *
	  * @access private
	  * @return array		$methodArray	multidimensional array of classes and their methods.
	  */
	private function fileMethods ()
	{
		(array) $methodArray = array (); // stores the list of class / methods
		
		(array) $fileArray = Ivy_File::readDir(SITEPATH . '/site/' . SITE . '/include/controller/');
		
		foreach ($fileArray as $id => $file) {
			
			$a = explode('.', $file);
			if ($a[1] == 'php') {
				$file = $a[0];
				
	
				require SITEPATH . '/site/' . SITE . '/include/controller/' . $file . '.php';
				$class = new ReflectionClass($file . '_Controller');
				
				foreach ($class->getMethods() as $methodID => $data) {
					$method = new ReflectionMethod($file . '_Controller', $data->name);
					$declaringClassArray = (array) $method->getDeclaringClass();
					if($method->isPublic() && $declaringClassArray['name'] != 'Ivy_Controller') {
						$methodArray[$file][$data->name] = 1;
					}
					unset($method);
				}
				unset($class);
			}
		}
		return $methodArray;
	}
	
	
	private function dbMethods ()
	{
		$array = array ();
		
		$a['tableSpec']['pk'] = array('SITEID');
		$form = new Ivy_Database('ivy_navigation', $a);
		unset($a);
		
		$form->select("SITEID = '" . SITE . "'");
		
		foreach ($form->data as $row => $data) {
			$array[ $data['CONTROLLER'] ][ $data['ACTION'] ] = 1;
		}
		return $array;
	}
	
	private function dbContent ()
	{
		$array = array ();
		
		$a['tableSpec']['pk'] = array('SITEID');
		$form = new Ivy_Database('ivy_cms', $a);
		unset($a);
		
		$form->select("SITEID = '" . SITE . "'", array('CONTROLLER','ACTION'));
		
		foreach ($form->data as $row => $data) {
			$array[ $data['CONTROLLER'] ][ $data['ACTION'] ] = 1;
		}
		return $array;
	}
	
	public function content ()
	{
		$this->globalstylesheet ='ivy_extended';
	}
	
	
	public function contentCreate ()
	{
		$this->stylesheet = 'form';
		
		$cms = new Ivy_Database('ivy_cms');
		$form = new Ivy_Database('ivy_cms_link');
		
		$this->display->addForm($cms->id);
		
		if (isset($_POST['submit'])) {
			$_POST['SITEID'] = SITE;
			$cms->insert($_POST);
		}
	}
	
}
?>