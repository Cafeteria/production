<?php

App::uses('Controller', 'Controller');

/**
 * Der LoginController regelt das an- und abmelden vom System.
 */
class LoginController extends AppController {
	
	public $components = array('Session');
	
	/**
	 * Ist der Benutzer bereits eingeloggt, erfolgt eine Umleitung auf /plan
	 * Handelt es sich um einen POST-Request, wird versucht, den Benutzer anzumelden.
	 * 
	 * @author aloeser
	 * @return void
	 */
	public function index() {				
		//Wenn bereits eingeloggt, dann Umleitung
		if ($this->Auth->loggedIn()) return $this->redirect(array('controller' => 'plan', 'action' => 'index'));
		
		if ($this->request->is('post') || $this->request->is('put')) {
			if ($this->Auth->login()) {
				if ($this->Auth->user('leave_date') != null) {
					//Der Benutzeraccount wurde deaktiviert
					$this->Session->setFlash('Ihre Logindaten sind zwar korrekt, allerdings wurde Ihr Zugang deaktiviert.<br/>Sollten Sie wieder in der Cafeteria arbeiten wollen, wenden Sie sich bitte an das <a href="mailto:cafeteria-humboldtschule@web.de">Cafeteria-Team</a>.','alert-box',array('class' => 'alert alert-block'));
					$this->logout(false);
				}
				
				return $this->redirect($this->Auth->redirectUrl());
			}
			$this->Session->setFlash('Login fehlgeschlagen!','alert-box',array('class' => 'alert-error'));
		}
	}
	
	/**
	 * Meldet den aktuell angemeldeten Benutzer ab und löscht gegebenenfalls existierende Warnungen.
	 * 
	 * @param removeWarnings - gibt an, ob mögliche existierende Warnungen gelöscht werden sollen.
	 * @author aloeser
	 * @return void
	 */
	public function logout($removeWarnings=true) {
		if ($removeWarnings) $this->Session->delete('Message');
		return $this->redirect($this->Auth->logout());
	}
	
	
	public function beforeFilter() {		
		parent::beforeFilter();
		$this->Auth->allow('index', 'logout');
		
	}	
	
	public function isAuthorized($user) {
		if (in_array($this->request->action, array ('index', 'logout'))) {
			return true;
		}
		
		return parent::isAuthorized($user);
	}
}
