<?php
App::uses('AppController', 'Controller');
App::uses('AutoController', 'Controller');
App::uses('ICal', 'Model');
/**
 * Der SpecialdatesController stellt die Möglichkeit bereit, die Ferien für das nächste Jahr automatisch zu importieren
 */
class SpecialdatesController extends AppController {

	public $components = array('Paginator');

	/**
	 * Hierbei handelt es sich um eine Funktion, die über die URL nicht aufgerufen werden kann.
	 * Stattdessen wird sie vom AutoController aufgerufen, wenn die Ferien automatisch importiert werden sollen
	 * Dabei werden Daten, die bereits als Specialdate markiert sind, übersprungen.
	 *
	 * @author aloeser
	 * @return void
	 */
	public function importVacations() {
	  try {
		$importyear = date('Y')+1;
		//$sourceURL = 'http://www.schulferien.org/iCal/Ferien/icals/Ferien_Berlin_'.$importyear.'.ics';
		//$sourceURL = 'http://www.schulferien.org/media/ical/deutschland/ferien_berlin_'.$importyear.'.ics';
		$downloadkey = 'ybcJG7nmJxoSMqrq02EB8pkWhWgvU142L4ShS9TAyzQ7CRqvHNUph6E18L_6ke6Lb-GA9sa5Pe63cB1eeNgnZ4UrevRFabbyHSoeHzY8iMw';
		$sourceURL = 'http://www.schulferien.org/media/ical/deutschland/ferien_berlin_'.$importyear.'.ics?k='.$downloadkey;
		
		
		$icalreader = new ICal($sourceURL);

		
		$events = $icalreader->events();
		if ($events == array()) {
			//Die angegebene Datei existiert nicht
			throw new Exception();
		}

		$tmpSpecialdates = $this->Specialdate->find('all', array('recursive' => -1, 'conditions' => array('Specialdate.date LIKE ' => $importyear.'-__-__')));
		$specialdates = array();
		foreach ($tmpSpecialdates as $tmpSpecialdate) {
			array_push($specialdates, $tmpSpecialdate['Specialdate']['date']);
		}
		
		foreach ($events as $event) {
			$timeBegin = $this->icalToUnixtime($event['DTSTART']);
			$timeEnd = $this->icalToUnixtime($event['DTEND']);
			
			$datetime = new Datetime(date('Y-m-d', $timeBegin));
			for ($i = $timeBegin;$i <= $timeEnd;$i+=DAY) {
				
				$dateString = $datetime->format('Y-m-d');
				if (!in_array($dateString, $specialdates)) {
					//Datum existiert noch nicht
					if (!($datetime->format('N') >= 6)) {
						//Kein Wochenende -> Specialdate eintragen, um das Datum zu deaktivieren
						$this->Specialdate->create();
						$this->Specialdate->save(array('Specialdate' => array('date' => $dateString)));
					}
				} else {
					//Datum existiert bereits
				}
				
				$datetime->modify("+1 day");
			}
		}
		AutoController::saveLog('Ferienimport', 0, 'SpecialdatesController', 'importVacations');
	  } catch (Exception $e) {
	  	AutoController::saveLog('Ferienimport', 3, 'SpecialdatesController', 'importVacations');
	  }
	}
	
	/**
	 * Wandelt eine Datumsangabe im iCal-Format ins Unix-Format um
	 * 
	 * @param ical - eine Datumsangabe im Ical-Format
	 * @author aloeser
	 * @return number
	 */
	private function icalToUnixtime($ical) {
		$year = substr($ical, 0, 4);
		$month = substr($ical, 4, 2);
		$day = substr($ical, 6, 2);
		return strtotime($year."-".$month."-".$day);
	}
	
	public function isAuthorized($user) {
		if ($this->action == "importVacations") {
			//Diese Methode ist für das Beschaffen und Einfügen der Ferientermine
			//des nächsten Jahres zuständig
			//->Niemand darf diese Funkion über die URL aufrufen
			//Stattdessen muss ROOT_PERMISSION definiert und true sein
			return defined("ROOT_PERMISSION") && ROOT_PERMISSION === true;
		}
		
		return parent::isAuthorized($user);
	}
}
