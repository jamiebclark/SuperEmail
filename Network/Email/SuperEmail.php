<?php
/**
 * Extends existing CakeEmail to allow an additional format, "copy", that will re-format
 * an existing HTML template into an automated text format
 *
 **/
App::uses('EmailFormat', 'SuperEmail.Lib');
App::uses('CakeEmail', 'Network/Email');

class SuperEmail extends CakeEmail {
	public $name = 'SuperEmail';
	
	//Basic helpers to be included with each email
	protected $defaultHelpers = array(
		'Html', 
		'Layout.DisplayText', 
		'SuperEmail.Email' => array()
	);
	public $helpers = array();
	
	protected $_View;
	protected $copyHtmlFormat = false;
	
	private $_defaultHelpersSet = false;
	
	/*
	public function __construct($config = null) {
		parent::__construct($config);
		//Makes sure helpers is called
		if (empty($config['helpers'])) {
			$this->setHelpers();
		}
	}
	*/
	
/**
 * Adds a new type of email template, "both", that converts the html to basic text using the Layout.DisplayText helper
 *
 * @param string $template The name of the template file, found in "Elements/both"
 * @param string $layout The name of the layout. Defaults to "default"
 *
 * @return bool True on success
 **/
	public function templateBoth($template, $layout = false) {
		$template = 'Emails' . DS . 'both' . DS . $template;
		$View = $this->_getView();
		$content = $View->element($template);

		$this->emailFormat('both');
		return $this->setEmailContent($content, null, $layout);
	}

/**
 * Extends existing helpers function to also include default helpers
 *
 * @param array|string|null $helpers List of helpers to include
 *
 * @return bool True on success
 **/
	public function setHelpers($helpers = null) {
		//Parses a string of multiple helpers
		if (!empty($helpers) && !is_array($helpers)) {
			preg_match('/[a-zA-Z_0-9]+/', $helpers, $helpers);
		}

		if (!$this->_defaultHelpersSet) {
			$helpers = $this->_getDefaultHelpers($helpers);
			$this->_defaultHelpersSet = true;
		}
		if (!empty($helpers)) {
			$this->helpers($helpers);
		}
		return $this;
	}
	
/**
 * Extends existing send function to make sure default helpers have been set before sending
 *
 **/
	public function send($content = null) {
		$this->setHelpers();
		return parent::send($content);
	}
	
/**
 * Adds a new format type, "copy", that copies the existing HTML and converts it to text rendering
 *
 * @param string $format
 * @return string|CakeEmail
 **/
	public function emailFormat($format = null) {
		if ($format == 'copy') {
			$this->copyHtmlFormat = true;
			$format = 'html';
		}
		return parent::emailFormat($format);
	}

	public function setEmailContent($content, $altContent = null, $layout = false) {
		if (empty($altContent)) {
			$altContent = $content;
		}
		$this->template('SuperEmail.content', $layout);
		$this->viewVars(compact('content', 'altContent'));
		return $this;
	}
	
/**
 * Build and set all the view properties needed to render the templated emails.
 * If there is no template set, the $content will be returned in a hash
 * of the text content types for the email.
 *
 * @param string $content The content passed in from send() in most cases.
 * @return array The rendered content with html and text keys.
 */
	protected function _renderTemplates2($content) {
		$rendered = parent::_renderTemplates($content);
		if (isset($rendered['html'])) {
			$rendered['html'] = EmailFormat::html($rendered['html']);
		}
		if ($this->copyHtmlFormat && isset($rendered['html']) && empty($rendered['text'])) {
			$this->_emailFormat = 'both';

			$View = $this->_getView();

			$layout = 'blank';
			list($templatePlugin, $template) = pluginSplit($this->_template);
			if ($templatePlugin) {
				$View->plugin = $templatePlugin;
			}
			if ($this->_theme) {
				$View->theme = $this->_theme;
			}
			
			$View->set('content', $content);
			$View->hasRendered = false;
			$View->layoutPath = 'Emails' . DS . 'html';
			$View->viewPath = $View->layoutPath;
			
			$render = $View->render($template);
			$render = EmailFormat::text(str_replace(array("\r\n", "\r"), "\n", $render));
			$rendered['text'] = $this->_encodeString($render, $this->charset);
		}
		return $rendered;
	}

/**
 * Sets the current email layout
 *
 * @param string|bool Name of the layout
 * @return bool True on success, false on fail
 **/
	public function layout($layout = false) {
		return $this->template(null, $layout);
	}

	public function getHelper($helper) {
		return $this->_loadHelper($helper);
	}
	
	/*
	public function parseHelpers($helpers = array()) {
		if (!is_array($helpers)) {
			preg_match('/[a-zA-Z_0-9]+/', $helpers, $helpers);
		}
		if (!empty($helpers)) {
			foreach ($helpers as $helper) {
				if ($helper != 'SuperEmail.Email') {
					//Also makes sure to add the helper to the Email helper
					$this->defaultHelpers['SuperEmail.Email']['helpers'][] = $helper;
				}
			}
			return $this->helpers($helpers);
		}
	}
	*/
	
	//Adds a helper to the View
	private function _loadHelper($helper) {
		$View = $this->_getView();
		App::uses($helper, 'Helper');
		return $View->loadHelper($helper);
	}
	
	private function _getView() {
		if (empty($this->_View)) {
			$this->_setView();
		}
		return $this->_View;
	}
	
	private function _setView() {
		$Controller = new AppController();

		$viewClass = $this->_viewRender;
		if ($viewClass !== 'View') {
			list($plugin, $viewClass) = pluginSplit($viewClass, true);
			$viewClass .= 'View';
			App::uses($viewClass, $plugin . 'View');
		}
		
		$this->_View = new $viewClass($Controller);
		$this->_View->viewVars = $this->_viewVars;
		$this->_View->helpers = $this->_helpers;
		$this->_View->loadHelpers();
		
		return $this->_View;	
	}
	
	private function _getDefaultHelpers($helpers = array()) {
		return array_merge((array) $this->defaultHelpers, (array) $this->helpers, (array) $helpers);
	}
}