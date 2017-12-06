<?php
App::uses('AppController', 'Controller');
/**
 * Der PlanController übernimmt sämtliche Interaktionen, die den Plan betreffen.
 * Weiterhin stellt er Funktionen zur Verfügung, die zur dynamischen Darstellung des Plans wichtig sind.
 * Damit ist die Veränderung des Plans durch JavaScript gemeint.
 * 
 * @author aloeser
 */
class PlanController extends AppController {
	
 	public $uses = array('Specialdate','User', 'Column', 'Plan', 'Comment', 'ColumnsUser', 'Changelog');
	public $helpers = array('Js','Time');
	public $components = array('Paginator','Session','RequestHandler');

	/**
	 * Überprüft, ob das Datum $date vollständig belegt ist.
	 * Hierbei handelt es sich um eine Funktion, die zur korrekten Manipulation des Plans durch JavaScript notwendig ist.
	 * Wurde etwas im Plan ein- oder ausgetragen, muss der entsprechende Tag erneut auf Vollständigkeit geprüft werden.
	 * 
	 * @param date - das zu prüfende Datum
	 * @author aloeser
	 * @return void
	 */
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
	
	/**
	 * Diese Funktion gibt alle aktiven Benutzer zurück, die den String $name im Benutzernamen haben.
	 * Diese Funktion wird im Adminmodus verwendet, um eine Liste möglicher Benutzer anzuzeigen.
	 * Das Ergebnis wird durch echo json_encode() an JavaScript übergeben.
	 * 
	 * @param name
	 * @param date
	 * @param halfshift
	 * @author aloeser
	 * @return void
	 */
	public function availableUsers($name='', $date=-1, $halfshift=-1) {
		if ($this->RequestHandler->isAjax()) {
			$this->autoRender = $this->layout = false;
			Configure::write('debug', '0');
			
			if (!$this->check_date($date)) {
				echo "false";
				exit;
			}
			
			if (!in_array($halfshift, array(1, 2, 3))) {
				echo "false";
				exit;
			}
			
			$weekend = date('N', strtotime($date)) >= 6;
			//auf Deutsch umstellen
			setlocale (LC_TIME, 'de_DE@euro', 'de_DE', 'de', 'ge', 'de_DE.utf8');
			$dow = strtolower(strftime('%a', strtotime($date)));
			
			$userList = $this->User->find('all', array('recursive' => -1, 'conditions' => array('User.username LIKE ' => '%'.$name.'%', 'User.leave_date' => null, 'User.admin !=' => 2)));
			
			
			$users = array();
			foreach ($userList as $user) {
				if ($weekend)
					$users[$user['User']['username']] = true;
				else
					$users[$user['User']['username']] = ($halfshift == 3) ? $user['User'][$dow] != 'N': in_array($user['User'][$dow], array($halfshift, 'H', 'G'));
			}
			
			echo json_encode($users);
			exit;
		}
		
		return $this->redirect(array('controller' => 'plan', 'action' => 'index'));
	}
	
	/**
	 * Stellt die notwendigen Daten bereit, um den Plan für den Monat $month im Jahr $year anzuzeigen.
	 * Hier werden alle Daten ermittelt und hinzugefügt, die nicht direkt im Plan stehen.
	 * Dazu zählen unter anderem:
	 * <ul>
	 * <li>Specialdates</li>
	 * <li>Benutzernamen</li>
	 * <li>die Spalten des Plans</li>
	 * <li>Wochentage und Wochenende</li>
	 * </ul>
	 * 
	 * Sollte $year oder $month ungültig oder nicht gesetzt sein, so wird immer der aktuelle Monat angezeigt.
	 *  
	 * @param year - das gewählte Jahr, vierstellig
	 * @param month - der gewählte Monat, zweistellig
	 * @author aloeser
	 * @return void
	 */
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
		$columns = $this->Column->find('all', array('recursive' => -1, 'order' => array('Column.type' => 'ASC', 'Column.order' => 'ASC')));
		$this->set('columns', $columns);
	
		$results = $this->Plan->getPlanData($month, $year);
	
		//Specialdates markieren
		$specialdates = $this->Specialdate->find('all', array('recursive' => -1, 'conditions' => array('Specialdate.date LIKE ' => $year.'-'.$month.'-__')));
		foreach ($specialdates as $specialdate) {
			$results[$specialdate['Specialdate']['date']]['specialdate'] = null;
		}
	
		//Zuordnung Id -> Benutzername herstellen
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
		$this->set('userColumnCount', count($userColumns));
		
		//auf Deutsch umstellen
		setlocale (LC_TIME, 'de_DE@euro', 'de_DE', 'de', 'ge', 'de_DE.utf8');
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

		
		$actions = array();
		if ($this->isAdmin()) {
			$actions['print'] = array('text' => 'Druckversion anzeigen', 'params' => array('controller' => 'plan','action' => 'printversion', $year, $month));
			$actions['admin'] = array('text' => 'Adminmodus', 'htmlattributes' => array('id' => 'adminLinkAnchor'));
		}	
		
		//Daten für Navigationslinks setzen
		$datetime = new DateTime($year."-".$month."-14");
		$this->set('headingDate', $datetime->format('m/Y'));
		
		$datetime->modify("-1 month");
		$actions['prev'] = array('text' => 'Vorheriger Monat', 'params' => array('controller' => 'plan', 'action' => 'index', $datetime->format('Y'), $datetime->format('m')));
		$datetime->modify("+2 months");
		$actions['next'] = array('text' => 'Nächster Monat', 'params' => array('controller' => 'plan', 'action' => 'index', $datetime->format('Y'), $datetime->format('m')));
		$this->set('actions', $actions);
	}
	
	/**
	 * old() liefert die zur Darstellung des Plans notwendigen Daten.
	 * Im Wesentlichen tut diese Funktion das selbe wie index(), allerdings ist sie noch auf das alte Schema des Plans ausgerichtet und sollte daher nicht mehr benutzt werden.
	 * Die alte Version des Plans wird nur soweit unterstützt, wie sie bis zum 19. März 2014 bereits implementiert war und ist deshalb nicht abrufbar.
	 * 
	 * @deprecated
	 * @param year - das gewählte Jahr, vierstellig
	 * @param month - der gewählte Monat, zweistellig
	 * @author aloeser
	 * @return void
	 */
	public function old($year=-1, $month=-1) {
		return $this->redirect(array('controller' => 'plan', 'action' => 'index', $year, $month));
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

	/**
	 * Zeigt die Druckversion des Plans an
	 * 
	 * @param year - das gewählte Jahr, vierstellig
	 * @param month - der gewählte Monat, zweistellig
	 * @see PlanController::index()
	 * @author aloeser
	 * @return void
	 */
	public function printversion($year=-1, $month=-1) {
		$this->layout = "print";
		$this->index($year, $month);
	}
	
	/**
	 * Sendet eine Notfallmail an alle aktiven Nutzer außer denjenigen mit der ID $exceptOf,
	 * die am $date in der $halfshift $shiftname Zeit hätten, um kurzfristig einzuspringen.
	 * 
	 * @param date - gibt an, an welchem Tag ein Dienst kurzfristig freigeworden ist
	 * @param halfshift - gibt die Halbschicht an, in der der Dienst freigeworden ist
	 * @param exceptOf - die Benutzerid, die keine E-Mail erhalten soll, weil sie sich ausgetragen hat
	 * @param shiftname - Name des Dienstes
	 */
	private function sendEmergencyMail($date, $halfshift, $exceptOf, $shiftname) {
		//auf Deutsch umstellen
		setlocale (LC_TIME, 'de_DE@euro', 'de_DE', 'de', 'ge', 'de_DE.utf8');
		$dow = strtolower(strftime('%a', strtotime($date)));
		
		$conditionArray = array(
				'User.leave_date' => null,
				'User.admin != ' => 2,
				'User.id != ' => $exceptOf,
				'User.mail != ' => ''
		);
		
		if ($dow == "sa" || $dow == "so") {
			
		} else {
			if ($halfshift == 3) {
				$conditionsArray['User.'.$dow] = array('1 ', '2 ', 'H', 'G');				
			} else {
				$conditionsArray['User.'.$dow] = array($halfshift.' ', 'H', 'G');	
			}
		}		
		
		$receivingUsers = $this->User->find('all', array('recursive' => -1, 'conditions' => $conditionArray));
		
		$senderMail = 'cafeteria-humboldtschule@web.de';
		$senderName = 'Humboldt Cafeteria';
		$mailContent = "Hallo,<br />
				leider ist die ".$shiftname." für ".strftime('%A', strtotime($date)).", den ".date('d.m.Y', strtotime($date)).", kurzfristig frei geworden.
				Falls Ihr Zeit habt, springt bitte ein.
				
				Mit freundlichen Grüßen
				Das Cafeteria-Team
				";
		
		$EMail = new CakeEmail();
		$EMail->from(array($senderMail => $senderName));
		$EMail->subject("Humboldt-Cafeteria - Dienst kurzfristig frei geworden");
		$EMail->config('shuttle');
		$EMail->template('default');
		$EMail->emailFormat('html');
		$EMail->viewVars(array(
				'senderName' => $senderName,
				'senderMail' => $senderMail,
				'content' => $mailContent,
				'subject' => "Humboldt-Cafeteria - nächste Woche unvollständig",
				'allowReply' => false
		));
		
		$bcc = array();
		foreach ($receivingUsers as $receivingUser) {
			array_push($bcc, $receivingUser['User']['mail']);
		}
		
		$EMail->bcc($bcc);
		$EMail->send();
	}
	
	/**
	 * Überprüft, ob ein String einem Datum in Format 'Y-m-d' entspricht (syntaktisch und semantisch).
	 * Durch den Parameter $mustBeInFuture kann angegeben werden, ob das Datum in der Zukunft liegen muss.
	 * 
	 * @param date - das zu überprüfende Datum
	 * @param mustBeInFuture - gibt an, ob das Datum >= das aktuelle Datum sein muss
	 * @author aloeser
	 * @return string|boolean
	 */
	private function check_date($date=-1, $mustBeInFuture=true) {
		$token = explode("-", $date);
		if (count($token) != 3 || !checkdate($token[1], $token[2], $token[0]) || strlen($token[0]) != 4 || strlen($token[1]) != 2 || strlen($token	[2]) != 2) {
			//Ungültiges Datum
			//-> nur normal Anzeigen, ohne Einschränkungen
			return "Ungültige Werte.";
		} 
		if ($mustBeInFuture && strtotime($date)+DAY < time()) 
			return "Datum bereits vorbei.";

		return true;
	}

	/**
	 * Diese Methode ist für das Speichern und Löschen von Benutzereinträgen im Plan zuständig.
	 * Dabei erfolgt sämtliche Autorisierung innerhalb dieser Methode.
	 * Das "Umschalten" zwischen halben und ganzen Diensten, wenn sich ein Nutzer ein- bzw. austrägt und die andere Halbschicht auch belegt hat, wird von dieser Funktion automatisch übernommen.
	 * 
	 * Ist $username nicht gesetzt bzw. '', so wird versucht, den Benutzereintrag an der durch die anderen Parameter beschriebenen Stelle zu löschen.
	 * 
	 * 
	 * @param date - das Datum, für das der Benutzer eingetragen wird
	 * @param columnid - die Spalten-ID, in die der Benutzer eingetragen wird
	 * @param halfshift - die Halbschicht, in die sich eingetragen wird
	 * @param username - der Name des einzutragenden Benutzers
	 * @author aloeser
	 * @return void
	 */
	public function saveUserEntry($date=-1, $columnid=-1, $halfshift=-1, $username="") {		
		if ($this->RequestHandler->isAjax()) {
			$this->autoRender = $this->layout =  false;
			Configure::write('debug','0');
		}
		
		if ($this->check_date($date) !== true || !$this->Column->exists($columnid) || !in_array($halfshift, array(1, 2, 3))) {
			echo "500";
			exit;
		}
		
		$column = $this->Column->find('first', array('recursive' => -1, 'conditions' => array('Column.id' => $columnid)));
		if ($column['Column']['type'] != 2) {
			echo "500";
			exit;
		}
		
		if ($column['Column']['req_admin'] && !(AuthComponent::user('id') && AuthComponent::user('admin'))) {
			echo "403";
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
				echo "403";
				exit;
			}
			//Existiert der angegebene Benutzer?
			if ($this->User->find('count', array('recursive' => -1, 'conditions' => array('User.username' => $username))) != 1) {
				echo "404";
				exit;
			} else {
				$userdata = $this->User->find('first', array('recursive' => -1, 'conditions' => array('User.username' => $username)));
			}
		} else {

			//Benutzerschicht soll gelöscht werden
			$aColumnsUser = $this->ColumnsUser->find('first', array('recursive' => -1, 'conditions' => array('ColumnsUser.date' => $date, 'ColumnsUser.column_id' => $columnid, 'ColumnsUser.half_shift' => array($halfshift, 3))));
			
			if (count($aColumnsUser) != 1) {
				echo "404";// "Dienst bereits leer.";
				//evtl. "Spielereien", muss nicht unbedingt gleich Fehler anzeigen
				exit;
			}
			
			if (!(((AuthComponent::user('id') && AuthComponent::user('admin')) || (AuthComponent::user('id') == $aColumnsUser['ColumnsUser']['user_id']) ))) {
				echo "403";
				exit;
			}
			
			if (count($aColumnsUser) == 1 && $halfshift != 3 && $aColumnsUser['ColumnsUser']['half_shift'] == 3) {
				$savearray = array(
					'ColumnsUser' => array(
						'id' => $aColumnsUser['ColumnsUser']['id'],
						'half_shift' => (3-$halfshift)
					)
				);

				$this->ColumnsUser->create();
				if ($this->ColumnsUser->save($savearray)) {
					$cheatMode = true;
				}
				
			}
			
			if (isset($cheatMode) || $this->ColumnsUser->delete($aColumnsUser['ColumnsUser']['id'])) {
				//Erfolgreich->Eintragen in Changelog
				$userinfo = $this->User->find('first', array('recursive' => -1, 'conditions' => array('User.id' => $aColumnsUser['ColumnsUser']['user_id'])));
				$changelogArray = array(
						'Changelog'	=> array(
								'for_date' => $date,
								'change_date' => date('Y-m-d H:i:s'),
								'value_before' => $userinfo['User']['username'],
								'value_after' => "",
								'column_name' => $column['Column']['name'].( ($halfshift==3) ? "" : "_".$halfshift),
								'user_did' => AuthComponent::user('username')
						)
				);
				$this->Changelog->save($changelogArray);
				
				//Bei zu kurzer Differenz zum aktuellen Datum Notfallmail verschicken
				if (strtotime($date)-time()-7*DAY < 0  && strtotime($date)-strtotime(date("Y-m-d")) > 0){
					$tmpCols = $this->Column->find('all', array('recursive' => -1, 'conditions' => array('obligated' => 1, 'type' => 2), 'order' => array('order' => 'ASC')));
					$i = 1;
					$columnIsObligated = false;
					foreach ($tmpCols as $tmpCol) {
						if ($tmpCol['Column']['id'] == $columnid) {
							$columnIsObligated = true;
							break;
						}
						$i++;
					}
					if ($columnIsObligated) $this->sendEmergencyMail($date, $halfshift, $aColumnsUser['ColumnsUser']['user_id'], ($halfshift == 1) ? " Frühschicht ".$column['Column']['name'] : " Spätschicht ".$i);
				}//&& date("Y-m-d", strtotime($date)) != date("Y-m-d", strotime(date("Y-m-d")))
				
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
		if (count($columnsUsers) == 1) {
			//Es gibt bereits Einträge, die sich mit diesem überschneiden würden
			//Diese dürfen nur mit Adminrechten überschrieben werden
			if (AuthComponent::user('id') && AuthComponent::user('admin')) {
				//Benutzer hat Adminrechte->Bisherige Einträge löschen
				$columnsUser = $columnsUsers[0];
				if ($columnsUser['ColumnsUser']['half_shift'] == 3) {
					//Jemand hat einen ganzen Dienst eingetragen
					//->Auf den anderen halben Dienst herabsetzen
					$savearray = array();
					$savearray['ColumnsUser']['id'] = $columnsUser['ColumnsUser']['id'];
					$savearray['ColumnsUser']['half_shift'] = (3-$halfshift);
					$this->ColumnsUser->create();
					$this->ColumnsUser->save($savearray);
				} else {
					//Vorheriger Nutzer hat nur einen halben Dienst belegt
					//->Löschen
					$this->ColumnsUser->delete($columnsUser['ColumnsUser']['id']);
				}
				
				//Benutzernamen für den Changelogeintrag speichern
				$userinfo = $this->User->find('first', array('recursive' => -1, 'conditions' => array('User.id' => $columnsUser['ColumnsUser']['user_id'])));
				$userBefore = (isset($userinfo['User']['username'])) ? $userinfo['User']['username'] : "";
			} else {
				//Benutzer hat keine Adminrechte->Fehler
				echo "403";
				exit;
			}
		} else if (count($columnsUsers) > 0) {
			//Es soll mehr als ein Eintrag überschrieben werden
			//Das ist nach dem neuen Planschema nicht möglich, da Halbschichten immer getrennt werden
			//->Fehler
			echo "404";
			exit; 
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
							'change_date' => date('Y-m-d H:i:s'),
							'value_before' => (isset($userBefore)) ? $userBefore : "",
							'value_after' => $username,
							'column_name' => $column['Column']['name'].( ($halfshift==3) ? "" : "_".$halfshift),
							'user_did' => AuthComponent::user('username')
					)
			);
			$this->Changelog->create();
			$this->Changelog->save($changelogArray);
			echo "200"; //Alles okay
			exit;
		} else {
			echo "500";
			exit;
		}
		
	}

	/**
	 * Diese Methode ist für das Speichern und Löschen von Texteinträgen im Plan zuständig.
	 * Dabei erfolgt sämtliche Autorisierung innerhalb dieser Methode.
	 *
	 * Ist $message = '', so wird versucht, den Texteintrag an der durch die anderen Parameter beschriebenen Stelle zu löschen.
	 *
	 * @param date - das Datum, für das der Benutzer eingetragen wird
	 * @param columnid - die Spalten-ID, in die der Benutzer eingetragen wird
	 * @param message - der einzutragende Text
	 * @author aloeser
	 * @return void
	 */
	public function saveTextEntry($date=-1, $columnid=-1, $message="") {

		if ($this->check_date($date) !== true || !$this->Column->exists($columnid)) {			
			echo "500";
			exit;
		}

		$column = $this->Column->find('first', array('recursive' => -1, 'conditions' => array('Column.id' => $columnid)));
		if ($column['Column']['type'] != 1) {
			echo "500";
			exit;
		}

		if ($column['Column']['req_admin'] && !(AuthComponent::user('id') && AuthComponent::user('admin'))) {
			echo "403";
			exit;
		}

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
								'change_date' => date('Y-m-d H:i:s'),
								'value_before' => $data['Comment']['message'],
								'value_after' => "",
								'column_name' => $column['Column']['name'],
								'user_did' => AuthComponent::user('username')
						)
					);
					$this->Changelog->create();
					$this->Changelog->save($changelogArray);
					echo "210";
					exit;
				} else {
					echo "500";
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
								'change_date' => date('Y-m-d H:i:s'),
								'value_before' => ($data == array()) ? "" : $data['Comment']['message'],
								'value_after' => $message,
								'column_name' => $column['Column']['name'],
								'user_did' => AuthComponent::user('username')
						)
				);
				
				$this->Changelog->save($changelogArray);
				echo "200";
				exit;
			} else {
				echo "500";
				exit;
			}
		}
	}
	

	/**
	 * Markiert ein Datum als Specialdate bzw. hebt dessen Markierung auf.
	 * 
	 * @param date - das Datum
	 * @author aloeser
	 * @return void
	 */
	public function saveSpecialdate($date=-1) {	

		if ($this->check_date($date) !== true) {
			echo "500";
			exit;
		}

		if ($this->Specialdate->exists($date)) {
			//Datum ist bereits Specialdate -> löschen
			if ($this->Specialdate->delete($date)) {
				echo "210";
				exit;
			} else {
				echo "510";
				exit;
			}
		} else {
			//Datum ist noch nicht eingetragen -> eintragen
			$savearray['Specialdate']['date'] = $date;
			if ($this->Specialdate->save($savearray)) {
				echo "200";
				exit;
			} else {
				echo "500";
				exit;
			}
		}		
	}
	
	/**
	 * Hierbei handelt es sich um eine Funktion, die über die URL nicht aufgerufen werden kann.
	 * Stattdessen wird sie vom AutoController aufgerufen, wenn die automatisierten Plan-unvollständig-Rundmails verschickt werden sollen.
	 * Diese Rundmails sind auf jeden Benutzer spezifisch zugeschnitten und enthalten nur Dienste, an denen der Benutzer auch einspringen könnte.
	 * Benutzer, die in der nächsten Woche gar nicht einspringen könnten, würden dementsprechend gar keine Mail erhalten.
	 *
	 * @author aloeser
	 * @return void
	 */
	public function sendMissingShiftMails(){
		$this->response = new CakeResponse();
	  try {
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
		
		$users = $this->User->find("all", array("recursive" => -1, "conditions" => array("User.mail != " => "", "User.leave_date" => null, 'User.admin != ' => 2)));

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

                    // immer eine Mail an Herrn Wagner senden
                    if($user['User']['id']==148) $workableUsers[$user['User']['id']][$date][$columnId] = 1;
				}
			}
		}
		
		$usersArray = array();
		foreach ($users as $user) {
			$usersArray[$user['User']['id']] = $user['User'];
		}
		
		$halfshifts = array (
			-1 => '',
			1 => 'Frühschicht ',
			2 => 'Spätschicht ',
		);
		foreach ($tmpColumns as $tmpColumn) {
			$columnsArray[$tmpColumn['Column']['id']]["name"] = $tmpColumn['Column']['name'];
		}
		$tmpCols = $this->Column->find('all', array('recursive' => -1, 'conditions' => array('obligated' => 1, 'type' => 2), 'order' => array('order' => 'ASC')));
		$i = 1;
		foreach ($tmpCols as $tmpCol) {
			$columnsArray[$tmpCol['Column']['id']]['pos'] = $i++;
		}
		

		$senderMail = 'cafeteria-humboldtschule@web.de';
		$senderName = 'Humboldt Cafeteria';
		
        Debugger::log($workableUsers);

		foreach ($workableUsers as $userid => $dates) {			
			$mailContent = "Hallo ".$usersArray[$userid]['fname']." ".$usersArray[$userid]['lname'].",<br />";
			$mailContent .= "leider sind zur Zeit noch nicht alle Dienste für die nächste Woche in der Humboldt-Cafeteria belegt.<br /><br />Es fehlen:<br />";
			foreach ($dates as $date => $columns) {
				$mailContent .= "<br />";
				$mailContent .= $data[$date]["dow"].", ".date('d. m. Y', strtotime($date));
				$mailContent .= "<ul>";
				foreach ($columns as $columnid => $halfshift) {
					if ($halfshift == 1 || $halfshift == 3) $mailContent .= "<li>Frühschicht ".$columnsArray[$columnid]["name"]."</li>";
					if ($halfshift == 2 || $halfshift == 3) $mailContent .= "<li>Spätschicht ".$columnsArray[$columnid]["pos"]."</li>";
					if ($halfshift == -1) $mailContent .= "<li>".$columnsArray[$columnid]["name"]."</li>";					
				}
				$mailContent .= "</ul>";
			}
			
			$EMail = new CakeEmail();
			$EMail->from(array($senderMail => $senderName));
			$EMail->to($usersArray[$userid]['mail']);
			$EMail->subject("Humboldt-Cafeteria - nächste Woche unvollständig");
			$EMail->config('shuttle');
			$EMail->template('default');
			$EMail->emailFormat('html');
			$EMail->viewVars(array(
					'senderName' => $senderName,
					'senderMail' => $senderMail,
					'content' => $mailContent,
					'subject' => "Humboldt-Cafeteria - nächste Woche unvollständig",
					'allowReply' => false
			));
            
            Debugger::log($EMail);
			Debugger::log($EMail->send());
		}
		
		AutoController::saveLog('Plan-unvollständig-Mail', 0, 'PlanController', 'sendMissingShiftMails');
	  } catch (Exception $e) {
	  	AutoController::saveLog('Plan-unvollständig-Mail', 3, 'PlanController', 'sendMissingShiftMails');
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
		
		if ($this->action == "availableUsers") {
			//Nur Administratoren kriegen eine Liste von Benutzern vorgesetzt, die sie eintragen können
			return parent::isAuthorized($user);
		}
		
		if ($this->action == "saveSpecialdate") {
			//Nur Administratoren dürfen Specialdates eintragen
			return parent::isAuthorized($user);
		}
		
		//Alle angemeldeten Benutzer dürfen den Plan einsehen und sich ein- und austragen und ein Datum auf Vollständigkeit prüfen
		return true;
	}
}
?>
