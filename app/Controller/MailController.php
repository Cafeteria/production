<?php
/**
 * Der MailController bietet die Funktion, eine Rundmail zu verschicken.
 * @author aloeser
 */
class MailController extends AppController {
	public $uses = array('User');
	public $components = array('Paginator','Session');
	
	/**
	 * Wenn es sich um einen POST-Request handelt, wird eine Rundmail mit den übergebenen Daten versendet.
	 * 
	 * @author aloeser
	 * @return void
	 */
	public function index(){
		if ($this->request->is('POST')) {
			$conditions = array('User.mail !=' => '', 'User.admin != ' => 2);
			if (!$this->request->data['Mail']['sendToAll']) $conditions['User.leave_date'] = null;
			$activeUsersWithEmail = $this->User->find('all', array(
					'conditions' => $conditions
			));
			
			$receivers = array();
			
			foreach ($activeUsersWithEmail as $user) {
				array_push($receivers, $user['User']['mail']);
			}
			
			//$senderMail = 'cafeteria-humboldtschule@web.de';
			//$senderName = 'Humboldt Cafeteria';
			
			$sender = $this->User->findById($this->Auth->user('id'));
			$senderName = $sender['User']['username'];
			$senderMail = $sender['User']['mail'];			
			
			$erfolgReceivers = array();
			$errorReceivers = array();
			foreach($receivers as $receiver) {
				$EMail = new CakeEmail();
			
				try 
				{
					$EMail->from(array($senderMail => $senderName));
					$EMail->bcc($receiver);
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
							'allowReply' => $this->request->data['Mail']['allowReply']
					));
					
					if ($EMail->send()) {
						array_push($erfolgReceivers,$receiver);
						//$this->redirect(array('action' => 'index'));
					} else {
						//$this->Session->setFlash('Beim Senden ist ein Fehler aufgetreten.['.$erg.']', 'alert-box', array('class' => 'alert-error'));
					}
				}
				catch(SocketException $e)
				{
					//array_push($errorReceivers,$receiver);
					array_push($errorReceivers, array("empfaenger" => $receiver,"fehler" => $e->getMessage()));
			    	//$this->Session->setFlash('Beim Senden ist ein Fehler aufgetreten.['.$e.']', 'alert-box', array('class' => 'alert-error'));
				}
			}
			
			if(empty($errorReceivers)) {
				$erfolgReceiversString = implode(", ",$erfolgReceivers);
				$this->Session->setFlash('Die Rundmail wurde erfolgreich an alle Empfänger versand: <br /><br />'.$erfolgReceiversString, 'alert-box', array('class' => 'alert-success'));
				$this->redirect(array('action' => 'index'));		
			} else {
			    $errorReceiversString = "";
			    foreach($errorReceivers as $er) {
                    $errorReceiversString .= $er["empfaenger"].' (Fehler: '.$er["fehler"].') <br/>';    
			    }
				$erfolgReceiversString = implode(", ",$erfolgReceivers);
				//$errorReceiversString = implode(", ",$errorReceivers);
				$this->Session->setFlash('Die Rundmail wurde <b>erfolgreich</b> an folgende Empfänger versand: <br /><br />'.$erfolgReceiversString.'<br /><br />Beim Senden an folgende Empfänger ist ein <b>Fehler</b> aufgetreten:<br /><br />'.$errorReceiversString, 'alert-box', array('class' => 'alert-error'));
			}
			
			
		}
		$this->set('actions', array());
	}
	
	public function isAuthorized($user) {
		return parent::isAuthorized($user);
	}
}

?>
