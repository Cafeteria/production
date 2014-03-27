<?php
App::uses('AppController', 'Controller');
/**
 * Der ColumnsController stellt sämtliche Aktionen bereit, die etwas mit Spalten zu tun haben.
 * Dies beinhaltet das Anlegen neuer Spalten, das Editieren bestehender und das Anzeigen und Löschen existierender Spalten.
 * @author aloeser
 */
class ColumnsController extends AppController {

	public $components = array('Paginator', 'Session');

/**
 * 
 * @author aloeser
 * @return void
 */
	public function index() {
		$this->Column->recursive = 0;
		$this->set('columns', $this->Paginator->paginate());
		
		$actions = array(
		'new' => array('text' => 'Neue Spalte','params' => array('action' => 'add'))
		);
		$this->set('actions', $actions);
	}

/**
 * add() ist für das Speichern neuer Spalten zuständig.
 *
 * @return void
 * @author aloeser
 */
	public function add() {
		if ($this->request->is('post')) {
			$options = array('fields' => 'MAX(`order`) AS max');
			$maxOrder = $this->Column->find('first', $options);
			$this->request->data['Column']['order'] = $maxOrder[0]['max']+1;
			$this->Column->create();
			if ($this->Column->save($this->request->data)) {
				$this->Session->setFlash('Die Spalte wurde angelegt.', 'alert-box', array('class' => 'alert-success'));
				return $this->redirect(array('action' => 'index'));
			} else {
				$this->Session->setFlash('Die Spalte konnte nicht gespeichert werden.', 'alert-box', array('class' => 'alert-error'));
			}
		}
		
		$actionArray = array(
				'list' => array('text' => 'Spalten anzeigen', 'params' => array('action' => 'index'))
		);
		$this->set('actions', $actionArray);
	}

/**
 * Lädt die benötigten Spaltendaten (identifiziert durch die ID) und stellt sie edit.ctp zur Verfügung.
 * Handelt es sich um einen POST-Request, so wird versucht, die Spaltendaten zu aktualisieren.
 * 
 * @throws NotFoundException
 * @param id
 * @author aloeser
 * @return void
 */
	public function edit($id = null) {
		if (!$this->Column->exists($id)) {
			throw new NotFoundException('Unbekannte Spalte');
		}
		if ($this->request->is(array('post', 'put'))) {
			if ($this->Column->save($this->request->data)) {
				$this->Session->setFlash('Die Spalte wurde gespeichert.', 'alert-box', array('class' => 'alert-success'));
				return $this->redirect(array('action' => 'index'));
			} else {
				$this->Session->setFlash('Die Spalte konnte nicht gespeichert werden.', 'alert-box', array('class' => 'alert-error'));
			}
		} else {
			$options = array('conditions' => array('Column.' . $this->Column->primaryKey => $id));
			$this->request->data = $this->Column->find('first', $options);
		}
		
		$actionArray = array(
			'list' => array('text' => 'Spalten anzeigen','params' => array('controller' => 'columns','action' => 'index')),
			'add' => array('text' => 'Neue Spalte','params' => array('controller' => 'columns','action' => 'add')),
			'delete' => array('text' => 'Spalte löschen','htmlattributes' => array('onClick' => "if (confirm('Wollen Sie wirklich die Spalte \"".$this->request->data['Column']['name']."\" l\u00f6schen?')) window.location.href='../delete/".$this->request->data['Column']['id']."';event.returnValue = false; return false;"))
		);
		$this->set('actions', $actionArray);
	}

/**
 * Löscht eine Spalte (identifiziert durch die ID)
 *
 * @throws NotFoundException
 * @param id
 * @author aloeser
 * @return void
 */
	public function delete($id = null) {
		$this->Column->id = $id;
		if (!$this->Column->exists()) {
			throw new NotFoundException('Unbekannte Spalte');
		}
		$this->request->onlyAllow('post', 'delete', 'get');
		if ($this->Column->delete()) {
			$this->Session->setFlash('Die Spalte wurde gelöscht.', 'alert-box', array('class' => 'alert-success'));
		} else {
			$this->Session->setFlash('Die Spalte konnte nicht gelöscht werden.', 'alert-box', array('class' => 'alert-error'));
		}
		return $this->redirect(array('action' => 'index'));
	}
}