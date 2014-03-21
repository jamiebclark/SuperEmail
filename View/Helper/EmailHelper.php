<?php
App::uses('EmailFormat', 'SuperEmail.Lib');

class EmailHelper extends AppHelper {
	public $name = 'Email';
	
	public $helpers = array(
		'Html',
		'Layout.DisplayText',
	);
	
	const PHP_TAG_REG = '@<\?php(.+?)\?>@is';
	
	function __construct($View, $settings = array()) {
		if (!empty($settings['helpers'])) {
			$this->helpers = array_merge($this->helpers, (array) $settings['helpers']);
		}
		parent::__construct($View, $settings);
	}
	
	public function beforeRender($viewFile) {
		$this->Html->css('SuperEmail.style', null, array('inline' => false));
		return parent::beforeRender($viewFile);
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
		
		if (Param::keyCheck($options, 'phpPreview', true)) {
			$text = $this->phpPreview($text);
		}
		
		/*
		//Parse PHP
		if (Param::keyCheck($options, 'php')) {
			extract($this->viewVars);
			ob_start();
			eval("?>$text<?php");
			$text = ob_get_clean();
		}
		*/
		return $text;
	}
	
	function image($url, $options = array()) {
		//Makes sure the image has the aboslute path
		if (substr($url, 0, 1) != '/') {
			$url = Router::url('/img/' . $url, true);
		}
		if (!empty($options['url'])) {
			$options['url'] = EmailFormat::url($options['url']);
		}
		if (empty($options['style'])) {
			$options['style'] = '';
		}
		$options['style'] .= 'border:0;margin:0;padding:0;';
		if (!empty($options['width'])) {
			$options['style'] .= "width:{$options['width']}px;";
		}	
		return $this->Html->image($url, $options);
	}
	
	function link($title, $url, $options = array(), $confirm = null) {
		$url = EmailFormat::url($url);
		return $this->Html->link($title, $url, $options, $confirm);
	}

	public function phpPreview($body) {
		if (preg_match_all(self::PHP_TAG_REG, $body, $matches)) {
			$replace = array();
			foreach ($matches[0] as $k => $match) {
				$code = $matches[0][$k];
				$code = preg_replace('/<br[\s\/]*>/', '', $code);
				$replace[$match] = $this->Html->div('phpcode', highlight_string($code, true));
			}
			$body = str_replace(array_keys($replace), $replace, $body);
		}
		return $body;	
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
		return EmailFormat::htmlToText($text);
	}
	
	//Formats text for being displayed in Plain-text emails, but then re-formats to be displayed in an HTML page
	function textHtml($text) {
		return sprintf('<div class="emaildisplay emaildisplay-text"><code>%s</code></div>', nl2br($this->text($text)));
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