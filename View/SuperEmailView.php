<?php
App::uses('SuperEmailView', 'View');
/**
 * Extends the basic view in order to use Layouts stored within the SuperEmail Plugin folder
 *
 **/
class SuperEmailView extends View {
	public function __construct(Controller $controller = null) {
		parent::__construct($controller);
		$this->layoutPath = APP . 'Plugin' . DS . 'SuperEmail' . DS . 'View' . DS . 'Layouts';
	}
}