<?php

App::uses('AppController', 'Controller');
/**
 * Zu der Website gehören unter anderem auch einige automatisch ausgeführte Skripte.
 * Diese führen Logbuch; Speicherort der Logs ist APP.DS.'lastruns' .
 * Der AutoController hat die Funktion, die Logs in geeigneter Funktion anzuzeigen.
 * Zusätzlich wird Benutzern die Möglichkeit geboten, das entsprechende Skript manuell ausführen zu lassen.
 * @author aloeser
 */
class AutoController extends AppController {

	/**
	 * Ermittelt anhand der Dateien in APP.DS.'lastruns', welche Aktionen ausgeführt wurden
	 * und stellt die Daten der index.ctp zur Verfügung.
	 * 
	 * Sind alle Parameter gesetzt, so wird versucht, die eine gewöhnlicherweise automatisch ausgeführte Funktion manuell auszuführen.
	 * Die Funktion wird dabei durch die Parameter angegeben.
	 * 
	 * @param controller - der Name des Controller, der die Funktion enthält
	 * @param action - der Name der Funktion
	 * @param description - der Name der ausgeführten Aktion
	 * @author aloeser
	 * @return void
	 */
	public function index($controller=-1, $action=-1, $description=-1) {
		if ($controller != -1 && $action != -1 && $description != -1) {
			$this->doTask($controller, $action, $description);			
			return $this->redirect(array('controller' => 'auto', 'action' => 'index'));
		}
		
		$handle = opendir (APP.DS."lastruns");

		$filelist = array();
		while ($file = readdir ($handle)) {
			if ($file != "." && $file != "..")
				$filelist[$file] = APP."lastruns".DS.$file;
		}

		closedir($handle);

		$viewData = array();
		$tmpArray = array();		
		foreach ($filelist as $filename => $path) {
			$contents = file($path);
			$tmpArray['actionname'] = str_replace("_", " ", $filename);
			$tmpArray['success'] = str_replace("\n", "", str_replace("\r\n", "", $contents[0]));
			$tmpArray['controller'] = str_replace("\n", "", str_replace("\r\n", "", $contents[1]));
			$tmpArray['action'] = str_replace("\n", "", str_replace("\r\n", "", $contents[2]));
			$tmpArray['lastExecution'] = filemtime($path);

			array_push($viewData, $tmpArray);
		}

		$this->set('performedActions', $viewData);
		$this->set('actions', array());
	}
	
	/**
	 * Übernimmt die manuelle Ausführung der durch die Parameter angegebenen Methode. 
	 * Dafür wird die Konstante ROOT_PERMISSION als true definiert. Dies ist notwendig, um die Funktion überhaupt ausführen zu dürfen.
	 * So kann ein direktes Aufrufen über die URL verhindert werden.
	 * 
	 * doTask() loggt Fehler, wenn der Controller oder die Funktion nicht gefunden wurde.
	 * Es übernimmt NICHT das Loggen, ob die aufgerufene Methode fehlerfrei durchlaufen wurde.
	 * Diese Verantwortung liegt bei der Methode selbst.
	 * 
	 * @param controller - der Name des Controller, der die Funktion enthält
	 * @param action - der Name der Funktion
	 * @param description - der Name der ausgeführten Aktion
	 * @author aloeser
	 * @return void
	 */
	private function doTask($controllerName, $actionName, $description) {
		$cfgDebug = Configure::read('debug');
		if (App::import('Controller', str_replace('Controller', '', $controllerName))) {
			$controller = new $controllerName();
			if (method_exists($controller, $actionName)) {
				define('ROOT_PERMISSION', true);
				Configure::write('debug', 0);
				if (call_user_func(array($controller, $actionName))) {
					//alles hat funktioniert
					//muss von der aufgerufenen Methode selbst geloggt werden
				} else {
					//ein programminterner Fehler ist aufgetreten
					//muss von der aufgerufenen Methode selbst geloggt werden
				}
			} else {
				//der angegebene Controller besitzt die angegebene Methode nicht
				AutoController::saveLog($description, 2, $controllerName, $actionName);
			}
		} else {
			//der angegebene Controller wurde nicht gefunden
			AutoController::saveLog($description, 1, $controllerName, $actionName);
		}
		Configure::write('debug', $cfgDebug);
	}
	
	public function isAuthorized($user) {
		return parent::isAuthorized($user);
	}

	/**
	 * Speichert einen Logeintrag im Verzeichnis APP.DS.'lastruns' .
	 * Zu einem Logeintrag gehören folgende Informationen:
	 * <ul>
	 * <li>Name der ausgeführten Aktion - wird durch den Dateinamen dargestellt</li>
	 * <li>Zeitpunkt der letzten Ausführung - wird durch das Änderungsdatum dargestellt</li>
	 * <li>Ergebnis der Ausführung - steht in der ersten Zeile</li>
	 * <li>Name des Controllers, in dem die Funktion enthalten ist - steht in der zweiten Zeile</li>
	 * <li>Name der Funktion - steht in der dritten Zeile</li>
	 * </ul>
	 * 
	 * Vorherige Logs werden dabei überschrieben.
	 * 
	 * @param actionname - der Name der ausgeführten Aktion
	 * @param errorcode - der Fehlerstatus der Aktion
	 * @param controller - der Name des Controller, der die Funktion enthält
	 * @param action - der Name der Funktion
	 * 
	 * @author aloeser
	 * @return void
	 */
	public static function saveLog($actionname, $errorcode, $controller, $action) {
		
		$file = fopen(APP."lastruns".DS.$actionname, "w");
		
		fwrite($file, $errorcode."\r\n");
		fwrite($file, $controller."\r\n");
		fwrite($file, $action."\r\n");
		
		fclose($file);
	}
}
?>
