<?php
class ContactsController extends AppController {
	public $uses = array('User', 'Plan', 'Column');
	public $helpers = array('Js');
	public $components = array('Paginator','Session');

	public $paginate = array(
			'fields' => array('User.lname','User.fname','User.tel1','User.tel2','User.mail','User.mo','User.di','User.mi','User.do','User.fr','User.leave_date'),
			'limit' => 25,
			'conditions' => array('User.leave_date' => null, 'User.admin != ' => 2),
			'order' => array('User.lname' => 'asc'),
	);

	public function index() {
		$this->User->recursive = -1;
		$entryCount = $this->User->find('count', array('recursive' => -1));
		$this->paginate['maxLimit'] = $entryCount;
		$this->paginate['limit'] = $entryCount;
		$this->paginate['recursive'] = -1;
		$this->Paginator->settings = $this->paginate;
		$results = $this->Paginator->paginate();

		foreach ($results as &$result) {
			//Hier wird die CSS-Klasse f�r die jeweiligen Tage in das Array eingebunden
			$result['User']['mo'] = $this->addStyle($result['User']['mo']);
			$result['User']['di'] = $this->addStyle($result['User']['di']);
			$result['User']['mi'] = $this->addStyle($result['User']['mi']);
			$result['User']['do'] = $this->addStyle($result['User']['do']);
			$result['User']['fr'] = $this->addStyle($result['User']['fr']);
		}
		$this->set('users',$results);
		
		$actionArray = array(
			'sendMail' => array('text' => 'E-Mail verschicken','params' => array('controller' => 'contacts','action' => 'mail')),
			'print' => array('text' => 'Druckversion anzeigen', 'params' => array('controller' => 'contacts', 'action' => 'printversion'))
		);
		$this->set('actions', $actionArray);
	}
	
	public function printversion() {
		$this->layout = "print";
		$this->index();
	}
	
	public function only($date=-1) {
		$token = explode("-", $date);
		if (count($token) != 3 || !checkdate($token[1], $token[2], $token[0]) || strlen($token[0]) != 4 || strlen($token[1]) != 2 || strlen($token[2]) != 2) {
			//Ungültiges Datum
			//-> nur normal Anzeigen, ohne Einschränkungen
			return $this->redirect(array('action' => 'index'));
		} else if (date('N', strtotime($date)) >= 6) { 
			return $this->redirect(array('action' => 'index'));
		} else {
			$missingShifts = $this->Plan->getMissingShifts($date);
			if ($missingShifts == array() || $missingShifts == null)
				return $this->redirect(array('action' => 'index'));
			
			$shift1Needed = false;
			$shift2Needed = false;
			foreach ($missingShifts as $key => $missingShift) {
				$column = $this->Column->find('first', array('recursive' => -1, 'conditions' => array('Column.id' => $key)));
				if ($column['Column']['type'] == 1) {
					$shift1Needed = true;
					$shift2Needed = true;
				}
				
				if ($shift1Needed && $shift2Needed)  break;
				
				if (array_key_exists(1, $missingShift))
					$shift1Needed = true;

				if (array_key_exists(2, $missingShift))
					$shift2Needed = true;
			}
			
			//auf Deutsch umstellen
			setlocale (LC_TIME, 'de_DE@euro', 'de_DE', 'de', 'ge', 'de_DE.utf8');
			
			$conditions = array("User.leave_date" => null);
			$dow = strtolower(strftime('%a', strtotime($date)));
			if ($shift1Needed && $shift2Needed) {
				//Fehlt ein ganzer Dienst, kann jeder angezeigt werden, der dort arbeitet
				$conditions['User.'.$dow.' !='] = "N";
			} else if ($shift1Needed) {
				//bei Char und Varchar ignoriert mysql Leerzeichen am Ende
				//und "1" wird in Cake als Zahl interpretiert, was Probleme mit SQL verursacht
				//'1 ' wird in Cake als String interpretiert, für mysql bedeutet es aber nur '1'
				//(http://dev.mysql.com/doc/refman/5.1/de/char.html)
				$conditions['User.'.$dow] = array("1 ", "H", "G");
			} else if ($shift2Needed) {
				//selbe Argumentation
				$conditions['User.'.$dow] = array("2 ", "H", "G");
			}		
		}
		
		$this->paginate['conditions'] = $conditions;
		//Es sollen alle Einträge auf einer Seite angezeigt werden -> Anzahl ermitteln
		if ($shift1Needed && $shift2Needed) {
			//Alle außer N werden rausgesucht
			$entryCount = $this->User->find('count', array('recursive' => -1, 'conditions' => array('User.leave_date' => null, 'User.'.$dow.' !=' => 'N')));
		} else {
			//weiter oben ermittelte Schichten werden rausgesucht
			$entryCount = $this->User->find('count', array('recursive' => -1, 'conditions' => array('User.leave_date' => null, 'User.'.$dow => $conditions['User.'.$dow])));
		}
		
		$this->paginate['maxLimit'] = $entryCount;
		$this->paginate['limit'] = $entryCount;
		$this->paginate['recursive'] = -1;
		$this->Paginator->settings = $this->paginate;
// 		debug($this->Paginator->settings);
		$results = $this->Paginator->paginate();
		foreach ($results as &$result) {
			//Hier wird die CSS-Klasse für die jeweiligen Tage in das Array eingebunden
			$result['User']['shift'] = $this->addStyle($result['User'][strtolower(strftime('%a', strtotime($date)))]);
		}
		$this->set('users', $results);
		$this->set('dow', strftime('%A', strtotime($date)));
	}

	/**
	 * Die Zellen der Tabelle in den Spalte [mo,di,mi,do,fr] sollen verschiedenfarbig unterlegt sein,
	 * je nachdem wie die betreffende Person am entsprechenden Wochentag Zeit hat. Diese Methode erwartet
	 * ein Ergebnis aus der Datenbank und gibt ein array zur�ck, welches neben dem Ergebnis auch eine CSS-Klasse enth�lt,
	 * die in die View eingebettet werden kann.
	 * @param Ergebnis aus der Datebank, welches einem Wert des enums entspricht $day
	 */
	private function addStyle($dayValue = null) {
		if (strcmp($dayValue,"G") == 0) {
			return array('value' => $dayValue,'class' => 'tdsuccess');
		} else if (strcmp($dayValue,"H") == 0 || strcmp($dayValue,"1") == 0 || strcmp($dayValue,"2") == 0) {
			return array('value' => $dayValue,'class' => 'tdwarning');
		} else if (strcmp($dayValue,"N") == 0) {
			return array('value' => $dayValue,'class' => 'tderror');
		}
	}
	
	/**
	 * Erwartet ein GET-Parameter und setzt den Wert des Adressfeldes entsprechend, für den eigentlichen
	 * E-Mail Versand ist eine andere Methode zuständig.
	 * @param String $to = Der Empfänger, der im Adressfeld angezeigt werden soll
	 */
	public function mail($data = null) {
		if ($this->request->is('get')) {
			$this->set('receiver',$data);
		}
		if ($this->request->is('post')) {
			$this->set('receiver',$this->request->data['Mail']['mailTo']);
			$receivers = $this->User->validateReceivers($this->request->data);
			
			//Prüfe ob alle E-Mail-Adresse valide sind:
			$invalidData = array();
			$validReceivers = array();
			foreach ($receivers as $receiver) {
				if (!$receiver['valid']) array_push($invalidData, $receiver['input']);
					else array_push($validReceivers, $receiver['mail']);
			}
			
			//Wenn alle EMail-Adressen gültig sind -> Fahre fort
			if ($invalidData == array()) {
				//Alle Empfänger sind gültig. Jetzt wird die E-Mail generiert
				$sender = $this->User->findById($this->Auth->user('id'));
				$senderName = $sender['User']['username'];
				$senderMail = $sender['User']['mail'];
								
				$errorReceivers = array();
				foreach ($validReceivers as $receiver) {
					$EMail = new CakeEmail();
                    // $EMail->from(array('Humboldt Cafeteria ['.$senderName.']'));

					try
					{
						$EMail->to($receiver);		
						$EMail->subject($this->request->data['Mail']['subject']);
						$EMail->config('shuttle');
						$EMail->template('default');
						$EMail->replyTo($senderMail == '' ? 'noreply@example.com' : $senderMail);
						$EMail->emailFormat('html');
						$EMail->viewVars(array(
								'senderName' => $senderName,
								'senderMail' => ($senderMail == '') ? 'keine E-Mail angegeben' : $senderMail,
								'content' => $this->request->data['Mail']['content'],
								'subject' => $this->request->data['Mail']['subject'],
								'allowReply' => true
								));
		   
						if ($EMail->send()) {
							$this->Session->setFlash('Die E-Mail wurde erfolgreich abgeschickt.<br /><br />Absender: '.$senderMail.' (Benutzername: '.$senderName.')','alert-box',array('class' => 'alert alert-success'));
							$this->redirect(array('action' => 'index'));
						} else {
							$this->Session->setFlash('Es ist ein Fehler aufgetreten.<br /><br /><Absender: '.$senderMail.' (Benutzername: '.$senderName.')','alert-box',array('class' => 'alert-error'));
						}
					}
					catch(SocketException $e)
					{
						array_push($errorReceivers, array("empfaenger" => $receiver,"fehler" => $e->getMessage()));
					}
				}
				if(!empty($errorReceivers)) {
				    $errorReceiversString = "";
				    foreach($errorReceivers as $er) {
                        $errorReceiversString .= $er["empfaenger"].' ('.$er["fehler"].')';    
				    }
					//$errorReceiversString = implode(", ",$errorReceivers);
					$this->Session->setFlash('Beim Senden an folgenden Empfänger ist ein Fehler aufgetreten:<br /><br />'.$errorReceiversString.'<br /><br />Absender: '.$senderMail.' (Benutzername: '.$senderName.')', 'alert-box', array('class' => 'alert-error'));
				}
			} else {
				//Gib in einer Fehlermeldung aus, welche Adressen/Benutzernamen fehlerhaft sind
				$invalidData = implode(',', $invalidData);
				$string =  "Die Nachricht konnte nicht gesendet werden, da folgende Empfänger keine Mitarbeiter der Cafeteria sind: " . $invalidData;
				$this->Session->setFlash($string, 'alert-box', array('class' => 'alert alert-block alert-error'));
			}
		}
		
		$actions = array(
				'back' => array('text' => 'Zur Telefonliste','params' => array('controller' => 'contacts','action' => 'index'))
		);
		
		//Nur für Admins sichtbar:
		if ($this->isAdmin()) {
			$actions['mailToAll'] = array('text' => 'Rundmail verschicken', 'params' => array('controller' => 'mail', 'action' => 'index'));
		}
		
		$this->set('actions', $actions);
	}

	public function isAuthorized($user) {
		//Jeder Benutzer darf die Kontaktliste aufrufen (auch die eingeschränkte)
		//Jeder Benutzer darf die Mailfunktion nutzen
		//Jeder Benutzer darf die Druckversion anschauen
		return true;
	}
}
?>
