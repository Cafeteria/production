<?php

App::uses('Controller', 'Controller');

/**
 * Login Controller
 */
class LoginController extends AppController {
	
	public $components = array('Session');
	
	public function index() {		
		
		//Wenn bereits eingeloggt, dann Umleitung
		if ($this->Auth->loggedIn()) return $this->redirect(array('controller' => 'plan', 'action' => 'index'));
		
		if ($this->request->is('post') || $this->request->is('put')) {
			if ($this->Auth->login()) {
				if ($this->Auth->user('leave_date') != null) {
					//Der Benutzeraccount wurde deaktiviert
					$this->Session->setFlash('Ihre Logindaten sind zwar korrekt, allerdings wurde Ihr Zugang deaktiviert.<br/>Sollten Sie wieder in der Cafeteria arbeiten wollen, wenden Sie sich bitte an das <a href="mailto:humboldt-cafeteria@versanet.de">Cafeteria-Team</a>.','alert-box',array('class' => 'alert alert-block'));
					$this->logout(false);
				}
				debug($this->request->data);
// 				return $this->redirect($this->Auth->redirectUrl());
				return $this->redirect('/contacts');
			}
			$this->Session->setFlash('Login fehlgeschlagen!','alert-box',array('class' => 'alert-error'));
		}
	}
	
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
