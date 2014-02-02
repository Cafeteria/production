<?php
/**
 * Application level Controller
 *
 * This file is application-wide controller file. You can put all
 * application-wide controller-related methods here.
 *
 * CakePHP(tm) : Rapid Development Framework (http://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org)
 * @link          http://cakephp.org CakePHP(tm) Project
 * @package       app.Controller
 * @since         CakePHP(tm) v 0.2.9
 * @license       http://www.opensource.org/licenses/mit-license.php MIT License
 */

App::uses('Controller', 'Controller');

/**
 * Application Controller
 *
 * Add your application-wide methods in the class below, your controllers
 * will inherit them.
 *
 * @package		app.Controller
 * @link		http://book.cakephp.org/2.0/en/controllers.html#the-app-controller
 */
class AppController extends Controller {
	public $components = array('DebugKit.Toolbar',
		'Auth' => array(
        'authenticate' => array(
            'Form' => array(
            	//Wie der Passworthash gebildet werden soll
                'passwordHasher' => array(
                    'className' => 'Simple',
                    'hashType' => 'sha1'
                )
            )
        ),
				
		//Wenn eine Anmeldung erforderlich, aber noch nicht erfolgt ist, wird auf diese Seite umgeleitet
		'loginAction' => array('controller' => 'login', 'action' => 'index'),
				
		//Automatische Umleitung nach dem Login, falls direkt auf die Loginseite zugegriffen wurde
		//Wurde zuvor auf eine Seite zugegriffen, die einen Login erfordert, so wird die folgende Zeile ignoriert
		//und stattdessen auf diese weitergeleitet 
		'loginRedirect' => array('controller' => 'specialdates', 'action' => 'index'),
				
		//Automatische Umleitung nach dem Logout
		'logoutRedirect' => array('controller' => 'login', 'action' => 'index'),

		//Die angezeigte Fehlermeldung, wenn man eine Seite ohne Login aufruft 
        'authError' => 'F&uuml;r diese Funktion ist ein Login notwendig',
        
		//Legt fest, wo grundlegende Autorisierung stattfindet
		'authorize' => array('Controller')
    ));
	
	//TODO Security::setHash() irgendwo anders machen
   	public function beforeFilter() {
		parent::beforeFilter();
		Security::setHash('sha1');
		
	}
	
	public function isAuthorized($user) {
		//Administrator darf alles
		if (isset($user['admin']) && $user['admin'] == 1) {
			return true;
		}
		
		return false;
	}
}