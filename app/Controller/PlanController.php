<?php
App::uses('AppController', 'Controller');
class PlanController extends AppController {
	
 	public $uses = array('Specialdate','User', 'Column', 'Plan', 'Comment', 'ColumnsUser', 'Changelog');
	public $helpers = array('Js','Time');
	public $components = array('Paginator','Session','RequestHandler');

	public function datecomplete($date=-1) {
		if ($this->RequestHandler->isAjax()) {
			$this->autoRender = $this->layout = false;
			Configure::write('debug','0');
		

			if (!$this->check_date($date)) {
				echo "false";
				exit;
			}
			
			echo ($this->Plan->isDateComplete($date)) ? "true" : "false";
			exit;
		}
		
		return $this->redirect(array('controller' => 'plan', 'action' => 'index'));
	}
	
	public function index($year=-1, $month=-1) {
		if (!is_numeric($month) || !is_numeric($year) || strlen($month) != 2 || strlen($year) != 4) {
			//Falsches Format => wie keine Daten
			$month = -1;
			$year = -1;
		}
		
		if ($month < 1 || $month > 12 || $year < 2000) {
			//Sachlich falsche Daten => wie keine Daten
			$month = -1;
			$year = -1;
		}
		
		if ($month == -1 || $year == -1) {
			//Kein Datum vorgegeben -> aktueller Monat
			$month = date('m', time());
			$year = date('Y', time());
		}

		//Spalten übergeben
		$columns = $this->Column->find('all', array('recursive' => -1, 'order' => array('Column.order' => 'ASC')));
		$this->set('columns', $columns);
		
		$results = $this->Plan->getPlanData($month, $year);

		$specialdates = $this->Specialdate->find('all', array('recursive' => -1, 'conditions' => array('Specialdate.date LIKE ' => $year.'-'.$month.'-__')));


		foreach ($specialdates as $specialdate) {
			$results[$specialdate['Specialdate']['date']]['specialdate'] = null;
		}

		//auf Deutsch umstellen		
		setlocale (LC_TIME, 'de_DE@euro', 'de_DE', 'de', 'ge', 'de_DE.utf8');
		
		$users = $this->User->find('all', array('recursive' => -1));
		$userslist = array();
		foreach ($users as $user) {
			$userslist[$user['User']['id']] = $user['User']['username'];
		}
		
		//Benutzerspalten ermitteln
		$userColumns = array();
		foreach ($columns as $column) {
			if ($column['Column']['type'] == 2)
				array_push($userColumns, $column['Column']['id']);
		}
		
		//Wochentag(dow=DayOfWeek) und Wochenende angeben
		foreach (array_keys($results) as $date) {
			$results[$date]['dow'] = strftime('%a', strtotime($date));
			$results[$date]['weekend'] = date('N', strtotime($date)) >= 6;
			
			foreach ($userColumns as $userColumn) {
				if (isset($results[$date][$userColumn][1]['userid']))
					$results[$date][$userColumn][1]['username'] = $userslist[$results[$date][$userColumn][1]['userid']];
				
				if (isset($results[$date][$userColumn][2]['userid']))
					$results[$date][$userColumn][2]['username'] = $userslist[$results[$date][$userColumn][2]['userid']];
			}
		}

		$this->set('results', $results);
		
		//Daten für Navigationslinks setzen
		$datetime = new DateTime($year."-".$month."-14");
		$this->set('headingDate', $datetime->format('m/Y'));
		$this->set('displayingYear', $datetime->format('Y'));
		$this->set('displayingMonth', $datetime->format('m'));
		$datetime->modify("+1 month");
		$this->set('nextYear', $datetime->format("Y"));
		$this->set('nextMonth', $datetime->format("m"));
		$datetime->modify("-2 month");
		$this->set('prevYear', $datetime->format("Y"));
		$this->set('prevMonth', $datetime->format("m"));
	}

	public function printversion($year=-1, $month=-1) {
		$this->layout = "print";
		$this->index($year, $month);
	}
	
	private function sendEmergencyMail($date, $halfshift, $exceptOf, $shiftname) {
		//auf Deutsch umstellen
		setlocale (LC_TIME, 'de_DE@euro', 'de_DE', 'de', 'ge', 'de_DE.utf8');
		$dow = strtolower(strftime('%a', strtotime($date)));
		
		$conditionArray = array(
				'User.leave_date' => null,
				'User.id != ' => $exceptOf,
				'User.mail != ' => ''
		);
		
		if ($dow == "sa" || $dow == "so") {
			
		} else {
			if ($halfshift == 3) {
				$conditionsArray['User.'.$dow] = array('1 ', '2 ', 'H', 'G');				
			} else {
				$conditionsArray['User.'.$dow] = array($hilfshift.' ', 'H', 'G');	
			}
		}		
		
		$receivingUsers = $this->User->find('all', array('recursive' => -1, 'conditions' => $conditionArray));
		
		$senderMail = 'humboldt-cafeteria@versanet.de';
		$senderName = 'Humboldt Cafeteria';
		$mailContent = "Hallo,<br />
				leider ist  ".$shiftname." für ".strftime('%A', strtotime($date)).", den ".date('d. m. Y', strtotime($date)).", kurzfristig frei geworden.
				Falls Ihr Zeit habt, springt bitte ein.
				
				Mit freundlichen Grüßen
				Das Cafeteria-Team
				";
		
		$EMail = new CakeEmail();
		$EMail->from(array($senderMail => $senderName));
		$EMail->subject("Humboldt-Cafeteria - Dienst kurzfristig frei geworden");
		$EMail->config('web');
		$EMail->template('default');
		$EMail->emailFormat('html');
		$EMail->viewVars(array(
				'senderName' => $senderName,
				'senderMail' => $senderMail,
				'content' => $mailContent,
				'subject' => "Humboldt-Cafeteria - nächste Woche unvollständig"
		));
		
		$bcc = array();
		foreach ($receivingUsers as $receivingUser) {
			array_push($bcc, $receivingUser['User']['mail']);
		}
		
		$EMail->bcc($bcc);
		$EMail->send();
	}
	
	private function check_date($date=-1) {
		$token = explode("-", $date);
		if (count($token) != 3 || !checkdate($token[1], $token[2], $token[0]) || strlen($token[0]) != 4 || strlen($token[1]) != 2 || strlen($token	[2]) != 2) {
			//Ungültiges Datum
			//-> nur normal Anzeigen, ohne Einschränkungen
			return "Ungültige Werte.";
		} else if (strtotime($date)+DAY < time()) {
			return "Datum bereits vorbei.";
		}

		return true;
	}

	public function saveUserEntry($date=-1, $columnid=-1, $halfshift=-1, $username="") {
		if ($username == "") $username = "";
		
		if ($this->RequestHandler->isAjax()) {
			$this->autoRender = $this->layout =  false;
			Configure::write('debug','0');
		}
		
		if ($this->check_date($date) !== true || !$this->Column->exists($columnid) || !in_array($halfshift, array(1, 2, 3))) {
			echo "Beim Eintragen ist ein Fehler aufgetreten.";
			exit;
		}
		
		$column = $this->Column->find('first', array('recursive' => -1, 'conditions' => array('Column.id' => $columnid)));
		if ($column['Column']['type'] != 2) {
			echo "Keine Benutzerspalte.";
			exit;
		}
		
		if ($column['Column']['req_admin'] && !(AuthComponent::user('id') && AuthComponent::user('admin'))) {
			echo "Zugriff verweigert.";
			exit;
		}
		
		$aSpecialdate = $this->Specialdate->exists($date);
		if ((date('N', strtotime($date)) >= 6 && !$aSpecialdate) || (date('N', strtotime($date)) <= 5 && $aSpecialdate) ) {
			//Inaktiver Tag -> Kein Eintragen möglich
			echo "Inaktiver Tag.";
			exit;
		}
		
		if ($username != "") {
			if ($username != AuthComponent::user('username') && !(AuthComponent::user('id') && AuthComponent::user('admin'))) {
				echo "Mieser fieser Hacker!";
				exit;
			}
			//Existiert der angegebene Benutzer?
			if ($this->User->find('count', array('recursive' => -1, 'conditions' => array('User.username' => $username))) != 1) {
				echo "Benutzer nicht gefunden";
				exit;
			} else {
				$userdata = $this->User->find('first', array('recursive' => -1, 'conditions' => array('User.username' => $username)));
			}
		} else {

			//Benutzerschicht soll gelöscht werden
			$aColumnsUser = $this->ColumnsUser->find('first', array('required' => -1, 'conditions' => array('ColumnsUser.date' => $date, 'ColumnsUser.column_id' => $columnid, 'ColumnsUser.half_shift' => $halfshift)));
			if (count($aColumnsUser) != 1) {
				echo "Dienst ist bereits leer";// "Dienst bereits leer.";
				//evtl. "Spielereien", muss nicht unbedingt gleich Fehler anzeigen
				exit;
			}
			
			if (!(((AuthComponent::user('id') && AuthComponent::user('admin')) || (AuthComponent::user('id') == $aColumnsUser['ColumnsUser']['user_id']) ))) {
				echo "Keine Berechtigung zum Löschen";
				exit;
			}
			
			if ($this->ColumnsUser->delete($aColumnsUser['ColumnsUser']['id'])) {
				//Erfolgreich->Eintragen in Changelog
				$userinfo = $this->User->find('first', array('recursive' => -1, 'conditions' => array('User.id' => $aColumnsUser['ColumnsUser']['user_id'])));
				$changelogArray = array(
						'Changelog'	=> array(
								'for_date' => $date,
								'value_before' => $userinfo['User']['username'],
								'value_after' => "",
								'column_name' => $column['Column']['name'].( ($halfshift==3) ? "" : "_".$halfshift),
								'user_did' => AuthComponent::user('username')
						)
				);
				$this->Changelog->save($changelogArray);
				
				//Bei zu kurzer Differenz zum aktuellen Datum Notfallmail verschicken
				if (strtotime($date)-time()-7*DAY + DAY < 0) {
					$this->sendEmergencyMail($date, $halfshift, $aColumnsUser['ColumnsUser']['user_id'], ($halfshift == 3) ? " der ganze Dienst ".$column['Column']['name'] : "die ".$halfshift.". Schicht ".$column['Column']['name']);
				}
				
				//Abbruch, da Aufgabe erledigt ist
				echo "210";
				exit;
			} else {
				echo "510";
				exit;
			}
		}

		
		//Ab hier kann man davon ausgehen, dass der Benutzer sich eintragen möchte und nicht austragen
		if ($halfshift == 3) {
			$notAllowedShifts = array(1, 2, 3);
		} else {
			$notAllowedShifts = array($halfshift, 3);
		}
		
		$columnsUsers = $this->ColumnsUser->find('all', array('recursive' => -1, 'conditions' => array('ColumnsUser.date' => $date, 'ColumnsUser.column_id' => $columnid, 'ColumnsUser.half_shift' => $notAllowedShifts)));
		if (count($columnsUsers) > 0) {
			//Es gibt bereits Einträge, die sich mit diesem überschneiden würden
			//Diese dürfen nur mit Adminrechten überschrieben werden
			if (AuthComponent::user('id') && AuthComponent::user('admin')) {
				//Benutzer hat Adminrechte->Bisherige Einträge löschen
				foreach ($columnsUsers as $columnsUser) {
					$this->ColumnsUser->delete($columnsUser['ColumnsUser']['id']);
					//In den Changelog eintragen
					$userinfo = $this->User->find('first', array('recursive' => -1, 'conditions' => array('User.id' => $columnsUser['ColumnsUser']['user_id'])));
					$changelogArray = array(
						'Changelog' => array(
							'for_date' => $date,
							'value_before' => (isset($userinfo['User']['username'])) ? $userinfo['User']['username'] : "",
							'value_after' => "",
							'column_name' => $column['Column']['name'].( ($halfshift==3) ? "" : "_".$halfshift),
							'user_did' => AuthComponent::user('username')
						)
					);
					$this->Changelog->clear();
				}
			} else {
				//Benutzer hat keine Adminrechte->Fehler
				echo "Keine Berechtigung.";
				exit;
			}
		}

		//Wird das Skript hier noch ausgeführt, so sind alle Berechtigungen gegeben->Eintragen
		$userinfo = $this->User->find('first', array('recursive' => -1, 'conditions' => array('User.username' => $username)));
		if ($halfshift == 1 || $halfshift == 2) {
			//Der Benutzer hat nur einen halben Dienst belegt
			//Prüfen, ob der Benutzer den anderen halben Dienst früher schon belegt hat
			$columnsuserid = $this->ColumnsUser->find('first', array('recursive' => -1, 'conditions' => array('ColumnsUser.date' => $date, 'ColumnsUser.column_id' => $columnid, 'ColumnsUser.half_shift' => (3-$halfshift))));
			if (count($columnsuserid) == 1 && $columnsuserid['ColumnsUser']['user_id'] == $userinfo['User']['id']) {
				//ja
				$columnsuserid = $columnsuserid['ColumnsUser']['id'];
			} else {
				//nein
				$columnsuserid = null;
			}
		} else {
			$columnsuserid = null;
		}
		
		$savearray = array(
			'ColumnsUser' => array(
				'id' => $columnsuserid,
				'date' => $date,
				'half_shift' => ($columnsuserid == null) ? $halfshift : 3,
				'column_id' => $columnid,
				'user_id' => $userdata['User']['id']
			)
		);

		
		if ($this->ColumnsUser->save($savearray)) {
			//Erfolgreich->in Changelog eintragen			
			$changelogArray = array(
					'Changelog'	=> array(
							'for_date' => $date,
							'value_before' => "",
							'value_after' => $username,
							'column_name' => $column['Column']['name'].( ($halfshift==3) ? "" : "_".$halfshift),
							'user_did' => AuthComponent::user('username')
					)
			);
			$this->Changelog->clear();
			$this->Changelog->save($changelogArray);
			echo "200"; //Alles okay
			exit;
		} else {
			echo "Fehler beim Eintragen.";
			exit;
		}
		
	}

	public function saveTextEntry($date=-1, $columnid=-1, $message=-1) {
		if ($this->check_date($date) !== true || !$this->Column->exists($columnid) || $message === -1 || $message == null) {
			echo "Beim Eintragen ist ein Fehler aufgetreten.";
			exit;
		}
		
		$column = $this->Column->find('first', array('recursive' => -1, 'conditions' => array('Column.id' => $columnid)));
		if ($column['Column']['type'] != 1)
			echo "Beim Eintragen ist ein Fehler aufgetreten.";
			exit;
		
		if ($column['Column']['req_admin'] && !(AuthComponent::user('id') && AuthComponent::user('admin')))
			echo "Zugriff verweigert.";
			exit;
		
		$data = $this->Comment->find('first', array('recursive' => -1, 'conditions' => array('Comment.date' => $date, 'Comment.column_id' => $columnid)));
		if (trim($message) == "") {
			//Der Eintrag soll gelöscht werden			
			if ($data == array()) {
				//Eintrag existiert nicht, es wurde einfach nur mit dem Textfeld rumgespielt
				//->nichts tun
			} else {
				//Eintrag existiert und soll gelöscht werden, da "" wie kein Eintrag ist
				if ($this->Comment->delete($data['Comment']['id'])) {					
					//Erfolgreich -> In Changelog eintragen
					$changelogArray = array(
						'Changelog'	=> array(
								'for_date' => $date,
								'value_before' => $data['Comment']['message'],
								'value_after' => "",
								'column_name' => $column['Column']['name'],
								'user_did' => AuthComponent::user('username')
						)
					);
					$this->Changelog->save($changelogArray);
				} else {
					echo "Beim Eintragen ist ein Fehler aufgetreten.";
					exit;
				}
			}
		} else {
			//Der Eintrag soll gepeichert werden
			if ($data == array()) {
				//Eintrag existiert noch nicht und muss neu angelegt werden
				$savearray = array(
						'Comment' => array(
								'date' => $date,
								'column_id' => $columnid,
								'message' => $message
						)
				);
			} else {
				//Eintrag existiert bereits und muss aktualisiert werden
				$savearray = $data;
				$savearray['Comment']['message'] = $message;
			}
			
			if ($this->Comment->save($savearray)) {
				//Erfolgreich->in Changelog eintragen
				$changelogArray = array(
						'Changelog'	=> array(
								'for_date' => $date,
								'value_before' => ($data == array()) ? "" : $data['Comment']['message'],
								'value_after' => $message,
								'column_name' => $column['Column']['name'],
								'user_did' => AuthComponent::user('username')
						)
				);
				$this->Changelog->save($changelogArray);
			} else {
				echo "Beim Eintragen ist ein Fehler aufgetreten";
				exit;
			}
		}
	}

	public function savetest() {
		debug($this->saveUserEntry('2014-03-20', 2, 3, ""));
		$this->saveUserEntry('2014-03-20', 2, 3, "Löser");
	}
	
	public function saveSpecialdate($date=-1) {		
		if ($this->check_date($date) !== true) {
			return "Beim Eintragen ist ein Fehler aufgetreten.";
		}

		if ($this->Specialdate->exists($date)) {
			//Datum ist bereits Specialdate -> löschen
			if ($this->Specialdate->delete($date)) {
				//Erfolgreich
			} else {
				return "Beim Austragen ist ein Fehler aufgetreten.";
			}
		} else {
			//Datum ist noch nicht eingetragen -> eintragen
			$savearray['Specialdate']['date'] = $date;
			if ($this->Specialdate->save($savearray)) {
				//Erfolgreich
			} else {
				return "Beim Eintragen ist ein Fehler aufgetreten.";
			}
		}
		
	}
	
	public function sendMissingShiftMails(){
		$firstDate = new DateTime('now');
		$firstDate = $firstDate->modify("+".(8-date('N', time()))." days");
		$tmpColumns = $this->Column->find('all', array('recursive' => -1));
		$columns = array();
		foreach ($tmpColumns as $tmpColumn) {
			$columns[$tmpColumn['Column']['id']] = $tmpColumn['Column']['req_admin'];
		}
		
		$data = array();
		
		//auf Deutsch umstellen
		setlocale (LC_TIME, 'de_DE@euro', 'de_DE', 'de', 'ge', 'de_DE.utf8');
		for ($i = 0;$i < 7;$i++) {
			$currentDate = $firstDate->format("Y-m-d");
			$missing = $this->Plan->getMissingShifts($currentDate);
	
			$data[$currentDate]["missing"] = ($missing === array()) ? null : $missing;
			$data[$currentDate]["dow"] = strftime('%A', strtotime($currentDate));
			$data[$currentDate]["weekend"] = date('N', strtotime($currentDate)) >= 6;
			$data[$currentDate]["specialdate"] = $this->Specialdate->find("count", array("conditions" => array("Specialdate.date" => $currentDate))) === 1;
			$firstDate->modify("+1 day");
		}
		
		$users = $this->User->find("all", array("recursive" => -1, "conditions" => array("User.mail != " => "", "User.leave_date" => null)));
		
		$workableUsers = array();

		foreach ($data as $date => $info) {
			//Tage, an denen nicht gearbeitet wird, werden ignoriert
			if (($info['weekend'] && !$info['specialdate']) || (!$info['weekend'] && $info['specialdate'])) {
				continue;
			}
			
			//Es handelt sich um ein Datum, an dem gearbeitet wird
			if (!isset($info['missing'])) {
				//Alle Dienste sind bereits belegt -> ignorieren
				continue;
			}
			
			//Es handelt sich um ein Datum, an dem noch Schichten fehlen
			foreach ($info['missing'] as $columnId => $shifts) {
				//Handelt es sich um eine Benutzerspalte/Textspalte?
				if ($shifts != null) {
					$missingShiftNumeric = 0;
					if (array_key_exists(1, $shifts))
						$missingShiftNumeric += 1;
					
					if (array_key_exists(2, $shifts))
						$missingShiftNumeric += 2;
				} else {
					//Textspalte
					$missingShiftNumeric = -1;
				}
							
				foreach ($users as $user) {
					//Falls Adminrechte benötigt werden, muss der Nutzer diese auch besitzen, sonst weiter
					if ($columns[$columnId] == 1 && !$user['User']['admin']) {
						continue;
					}
					
					if ($missingShiftNumeric == -1) {
						$workableUsers[$user['User']['id']][$date][$columnId] = -1;
					} else if ($info['weekend']) {
						//Zu Wochenenden wurden keine Arbeitszeiten gespeichert
						//-> Alle Mitarbeiter werden gewählt
						$workableUsers[$user['User']['id']][$date][$columnId] = $missingShiftNumeric;
					} else if ($missingShiftNumeric == 3) {
						if ($user['User'][substr(strtolower($info['dow']), 0, 2)] != "N") {
							$workableUsers[$user['User']['id']][$date][$columnId] = 3;							
						}
					} else if ($missingShiftNumeric == 2) {
						if (in_array($user['User'][substr(strtolower($info['dow']), 0, 2)], array("2", "H", "G"))) {
							$workableUsers[$user['User']['id']][$date][$columnId] = 2;
						}
					} else if ($missingShiftNumeric == 1) {
						if (in_array($user['User'][substr(strtolower($info['dow']), 0, 2)], array("1", "H", "G"))) {
							$workableUsers[$user['User']['id']][$date][$columnId] = 1;
						}
					}
				}
			}
		}
		
		$usersArray = array();
		foreach ($users as $user) {
			$usersArray[$user['User']['id']] = $user['User'];
		}
		
		$halfshifts = array (
			-1 => '',
			1 => '1. Schicht ',
			2 => '2. Schicht ',
			3 => 'Ganzer Dienst '
		);
		foreach ($tmpColumns as $tmpColumn) {
			$columnsArray[$tmpColumn['Column']['id']] = $tmpColumn['Column']['name'];
		}
		

		$senderMail = 'humboldt-cafeteria@versanet.de';
		$senderName = 'Humboldt Cafeteria';
		
		foreach ($workableUsers as $userid => $dates) {			
			$mailContent = "Hallo ".$usersArray[$userid]['fname']." ".$usersArray[$userid]['lname'].",<br />";
			$mailContent .= "leider sind zur Zeit noch nicht alle Dienste für die nächste Woche in der Humboldt-Cafeteria belegt.<br /><br />Es fehlen:<br />";
			foreach ($dates as $date => $columns) {
				$mailContent .= "<br />";
				$mailContent .= $data[$date]["dow"].", ".date('d. m. Y', strtotime($date));
				$mailContent .= "<ul>";
				foreach ($columns as $columnid => $halfshift) {
					$mailContent .= "<li>";
					$mailContent .= $halfshifts[$halfshift].$columnsArray[$columnid];
					$mailContent .= "</li>";
					
				}
				$mailContent .= "</ul>";
			}
				
			$EMail = new CakeEmail();
			$EMail->from(array($senderMail => $senderName));
			$EMail->to($usersArray[$userid]['mail']);
			$EMail->subject("Humboldt-Cafeteria - nächste Woche unvollständig");
			$EMail->config('web');
			$EMail->template('default');
			$EMail->emailFormat('html');
			$EMail->viewVars(array(
					'senderName' => $senderName,
					'senderMail' => $senderMail,
					'content' => $mailContent,
					'subject' => "Humboldt-Cafeteria - nächste Woche unvollständig"
			));

			$EMail->send();
		}
	}

	public function isAuthorized($user) {
		if ($this->action == "sendMissingShiftMails") {
			//Diese Methode ist zuständig für das Generieren und Versenden der Mails
			//bezüglich fehlender Dienste im Plan und wird zu gegebener Zeit automatisch aufgerufen
			//->Niemand darf diese Funkion über die URL aufrufen
			//Stattdessen muss ROOT_PERMISSION definiert und true sein
			return defined("ROOT_PERMISSION") && ROOT_PERMISSION === true;
		}
		
		if ($this->action == "saveSpecialdate") {
			//Nur Administratoren dürfen Specialdates eintragen
			return parent::isAuthorized($user);
		}
		
		//Alle angemeldeten Benutzer dürfen den Plan einsehen und sich ein- und austragen
		return true;
	}
}
?>
