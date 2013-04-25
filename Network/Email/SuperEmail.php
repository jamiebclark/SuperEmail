<?php
/**
 * Extends existing CakeEmail to allow an additional format, "copy", that will re-format
 * an existing HTML template into an automated text format
 *
 **/
App::uses('EmailFormat', 'SuperEmail.Lib');
App::uses('CakeEmail', 'Network/Email');

class SuperEmail extends CakeEmail {
	private $copyHtmlFormat = false;
	
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
	
/**
 * Build and set all the view properties needed to render the templated emails.
 * If there is no template set, the $content will be returned in a hash
 * of the text content types for the email.
 *
 * @param string $content The content passed in from send() in most cases.
 * @return array The rendered content with html and text keys.
 */
	protected function _renderTemplates($content) {
		$rendered = parent::_renderTemplates($content);
		if (isset($rendered['html'])) {
			$rendered['html'] = EmailFormat::html($rendered['html']);
		}
		if ($this->copyHtmlFormat && isset($rendered['html']) && empty($rendered['text'])) {
			$this->_emailFormat = 'both';
			$viewClass = $this->_viewRender;
			if ($viewClass !== 'View') {
				list($plugin, $viewClass) = pluginSplit($viewClass, true);
				$viewClass .= 'View';
				App::uses($viewClass, $plugin . 'View');
			}

			$View = new $viewClass(null);
			$View->viewVars = $this->_viewVars;
			$View->helpers = $this->_helpers;

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
			$View->viewPath = $View->layoutPath = 'Emails' . DS . 'html';
			$render = $View->render($template);
			$render = EmailFormat::text(str_replace(array("\r\n", "\r"), "\n", $render));
			$rendered['text'] = $this->_encodeString($render, $this->charset);
		}
		return $rendered;
	}
}