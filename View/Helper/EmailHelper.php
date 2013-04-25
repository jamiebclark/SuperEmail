<?php
App::uses('EmailFormat', 'SuperEmail.Lib');
class EmailHelper extends AppHelper {
	var $name = 'Email';
	
	var $helpers = array(
		'Html',
		'Layout.DisplayText',
	);
	
	function __construct($View, $settings = array()) {
		if (!empty($settings['helpers'])) {
			$this->helpers = array_merge($this->helpers, (array) $settings['helpers']);
		}
		parent::__construct($View, $settings);
	}
	
	function display($text, $format = 'html', $options = array()) {
		$text = $this->DisplayText->text($text, $options);
		$style = Param::keyCheck($options, 'style', true, null);
		
		if (Param::keyCheck($options, 'eval', true)) {
			$text = $this->evalVars($text);
		}
		
		if ($format == 'html') {
			$text = $this->html($text, $style);
		} else if ($format == 'textHtml') {
			$text = $this->textHtml($text);
		} else {
			$text = $this->text($text);
		}
		
		return $text;
	}
	
	function image($url, $options = array()) {
		//Makes sure the image has the aboslute path
		if (substr($url, 0, 1) != '/') {
			$url = Router::url('/img/' . $url, true);
		}
		if (!empty($options['url'])) {
			$options['url'] = Router::url($options['url'], true);
		}
		if (empty($options['style'])) {
			$options['style'] = '';
		}
		$options['style'] .= 'border:0;';
	
		return $this->Html->image($url, $options);
	}
	
	function link($title, $url, $options = array(), $confirm = null) {
		$url = Router::url($url, true);
		return $this->Html->link($title, $url, $options, $confirm);
	}
	
	function evalVars($text) {
		extract($this->viewVars);
		$text = str_replace('"', '\\"', $text);
		eval('$text = "' . $text . '";');
		return $text;
	}
	
	/**
	 * Takes HTML and inserts CSS directly into the tag STYLE
	 *
	 **/
	function html($text, $style = array()) {
		return EmailFormat::html($text, $style);
	}
	
	//Formats text for being displayed in a Plain-text email.
	function text($text) {
		return EmailFormat::text($text);
	}
	
	//Formats text for being displayed in Plain-text emails, but then re-formats to be displayed in an HTML page
	function textHtml($text) {
		return $this->Html->tag('code', nl2br($this->text($text)));
	}
		
	function loadHelpers($helpers = array()) {
		if (!is_array($helpers)) {
			preg_match('/[a-zA-Z_0-9]+/', $helpers, $helpers);
		}
		if (!empty($helpers)) {
			foreach ($helpers as $helper) {
				$this->_loadHelper($helper);
			}
		}
	}
	
	function _loadHelper($helper) {
		if (empty($this->{$helper})) {
			App::uses($helper, 'Helper');
			$this->helpers[] = $helper;
			$this->{$helper} = $this->_View->loadHelper($helper);
		}
		return $this->{$helper};
	}
}