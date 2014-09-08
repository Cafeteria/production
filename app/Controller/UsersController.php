<?php
App::uses('AppController', 'Controller');
/**
 * Users Controller
 *
 * @property User $User
 * @property PaginatorComponent $Paginator
 */
class UsersController extends AppController {

public $paginate = array(
			
			'limit' => 25,
			'order' => array('User.lname' => 'asc')
	);

	public $components = array('Paginator', 'Session');

/**
 * Stellt der View alle aktiven Benutzer zur Verfügung
 *
 * @return void
 */
	public function index() {
		if ($this->request->is("post")) {
			$activeUsers = $this->User->find('all', array('recursive' => -1, 'conditions' => array('User.leave_date' => null)));
			$activeUsersList = array();
			foreach ($activeUsers as $activeUser) {
				array_push($activeUsersList, $activeUser['User']['id']);
			}


			foreach ($this->request->data['User'] as $id => $data) {
				$tmpArray = array();
				$tmpArray['User']['id'] = $id;
				$tmpArray['User']['admin'] = $data['admin'];	
				if ($data['leave_date'] == 0){
					if (in_array($id, $activeUsersList)){
						$tmpArray['User']['leave_date'] = date('Y-m-d');
					}
				} else {
					$tmpArray['User']['leave_date'] = null;
				}

				$this->User->create();
				if ($this->User->save($tmpArray)) {
					$this->Session->setFlash('Die Änderungen wurden gespeichert.', 'alert-box', array('class' => 'alert-success'));
				} else {
					$this->Session->setFlash('Die Änderungen konnten nicht gespeichert werden.', 'alert-box', array('class' => 'alert-error'));
				}
			}
		}
		//Alle Benutzer werden angezeigt
		$this->User->recursive = -1;
		$entryCount = $this->User->find('count', array('recursive' => -1));
		$this->paginate['maxLimit'] = $entryCount;
		$this->paginate['limit'] = $entryCount;
		$this->paginate['recursive'] = -1;
		$this->Paginator->settings = $this->paginate;
		$this->set('users', $this->Paginator->paginate());
		
		$actions = array(
				'new user' => array('text' => 'Neuen Benutzer einfügen', 'params' => array('controller' => 'users', 'action' => 'add')),
				'save changes' => array('text' => 'Änderungen speichern', 'htmlattributes' => array('onClick' =>"document.forms['UserIndexForm'].submit();")),
				'reset changes' => array('text' => 'Änderungen zurücksetzen', 'params' => array('controller' => 'users', 'action' => 'index'))
		);
		$this->set('actions', $actions);
	}

	public function beforeRender() {
		$this->set('enumValues',$this->User->getEnumValues('mo'));
	}

/**
 * fügt der Datenbank einen neuen Benutzer hinzu
 *
 * @return void
 */
	//Benutzer der Datenbank hinzufügen
	public function add() {
		if ($this->request->is('post')) {
			if (is_numeric($this->request->data['User']['mo'])) $this->request->data['User']['mo'] .= ' ';
                        if (is_numeric($this->request->data['User']['di'])) $this->request->data['User']['di'] .= ' ';
                        if (is_numeric($this->request->data['User']['mi'])) $this->request->data['User']['mi'] .= ' ';
                        if (is_numeric($this->request->data['User']['do'])) $this->request->data['User']['do'] .= ' ';
                        if (is_numeric($this->request->data['User']['fr'])) $this->request->data['User']['fr'] .= ' ';

			$this->User->create();
			if ($this->User->save($this->request->data)) {
				if (isset($this->request->data['User']['mail']) && $this->request->data['User']['mail'] != '') {
					$mailContent = "Hallo ".$this->request->data['User']['fname']." ".$this->request->data['User']['lname'].",";
					$mailContent .= "<p>Sie wurden erfolgreich für den Cafeteria-Planer der Humboldt-Oberschule registriert. ";
					$mailContent .= "Sie können sich nun <a href='www.humboldtschule-berlin.de/cafeplaner/'>hier</a> anmelden.</p>";
					$mailContent .= "<p>Ihre Anmeldedaten:<br />";
					$mailContent .= "Benutzername: ".$this->request->data['User']['username']."<br />";
					$mailContent .= "Passwort: ".$this->request->data['User']['fname']."</p>";
					
					$EMail = new CakeEmail();
					$EMail->to($this->request->data['User']['mail']);
					$EMail->subject("Registrierung für den Cafeplaner der Humboldt-Oberschule");
					$EMail->config('web');
					$EMail->template('default');
					$EMail->emailFormat('html');
					$EMail->viewVars(array(
							'senderName' => 'Humboldt Cafeteria',
							'senderMail' => 'cafeteriaprojekt@web.de',
							'content' => $mailContent,
							'subject' => "Registrierung für den Cafeplaner der Humboldt-Oberschule",
							'allowReply' => false
					));
					$EMail->send();
				}
				
				$this->Session->setFlash('Der Benutzer wurde angelegt.', "alert-box", array("class" => 'alert-success'));
				return $this->redirect(array('action' => 'index'));
			} else {
				$this->Session->setFlash('Der Benutzer konnte nicht gespeichert werden. Bitte versuchen Sie es noch einmal.', "alert-box", array("class" => 'alert-error'));
			}
		}
		$columns = $this->User->Column->find('list');
		$this->set(compact('columns'));
		$this->set('actions', array('actions' => array('back' => array('text' => 'Zur Benutzerverwaltung', 'params' => array('controller' => 'users', 'action' => 'index')))));
	}

/**
 * Ändert Benutzerdaten eines existierenden Benutzers
 *
 * @throws NotFoundException
 * @param string $id
 * @return void
 */
	//Benutzerdaten verändern
	public function edit($id=-1) {
		if ($id == -1) $id = AuthComponent::user('id');
		if (!$this->User->exists($id)) {
			throw new NotFoundException('Unbekannter Benutzer');
		}
		if ($this->request->is(array('post', 'put'))) {
			if (is_numeric($this->request->data['User']['mo'])) $this->request->data['User']['mo'] .= ' ';
			if (is_numeric($this->request->data['User']['di'])) $this->request->data['User']['di'] .= ' ';
			if (is_numeric($this->request->data['User']['mi'])) $this->request->data['User']['mi'] .= ' ';
			if (is_numeric($this->request->data['User']['do'])) $this->request->data['User']['do'] .= ' ';
			if (is_numeric($this->request->data['User']['fr'])) $this->request->data['User']['fr'] .= ' ';
			
			if ($this->User->save($this->request->data)) {
				$this->Session->setFlash('Die Änderungen wurden gespeichert.', "alert-box", array("class" => 'alert-success'));
				return $this->redirect(array('action' => 'index'));
			} else {
				$this->Session->setFlash('Die Änderungen konnten nicht gespeichert werden. Bitte versuchen Sie es noch einmal.');
			}
		} else {
			$options = array('conditions' => array('User.' . $this->User->primaryKey => $id));
			$this->request->data = $this->User->find('first', $options);
		}
		$columns = $this->User->Column->find('list');
		$this->set(compact('columns'));
		
		$actionArray = array(
			'reset' => array('text' => 'Änderungen zurücksetzen', 'params' => array('controller' => 'users', 'action' => 'edit', $id))
		);
		if ($this->isAdmin()) $actionArray['list'] = array('text' => 'Zur Benutzerverwaltung', 'params' => array('controller' => 'users', 'action' => 'index'));
		$this->set('actions', $actionArray);
	}

/**
 * Löscht einen existierenden Benutzer
 *
 * @throws NotFoundException
 * @param string $id
 * @return void
 */
	//Benutzer aus der Datenbank löschen
	public function delete($id = null) {
		$this->User->id = $id;
		if (!$this->User->exists()) {
			//nur existierende Nutzer können gelöscht werden
			throw new NotFoundException('Benutzer nicht gefunden');
		}
		$this->request->onlyAllow('post', 'delete', 'get');
		if ($this->User->delete()) {
			$this->Session->setFlash('Der Benutzer wurde gelöscht', "alert-box", array("class" => 'alert-success'));
		} else {
			$this->Session->setFlash('Der Benutzer konnte nicht gelöscht werden. Bitte versuchen Sie es noch einmal.');
		}
		return $this->redirect(array('action' => 'index'));
	}


	public function isAuthorized($user) {
		if ($this->action == "edit") { 
			if (AuthComponent::user('id') && AuthComponent::user('admin')) {
				//Admins dürfen alle Daten ändern
				return true;
			} else {
				//normale Nutzer dürfen nur ihre eigenen Daten ändern
				return !isset($this->params['pass'][0]) || $this->params['pass'][0] == AuthComponent::user('id');
			}
		}

		//Das Anlegen, Anzeigen und Löschen von Benutzern steht nur Admins zur Verfügung
		return parent::isAuthorized($user);
	}
}
