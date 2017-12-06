<?php
//Weil das Plugin DebugKit sonst bugt
define('FULL_BASE_URL', 'http://localhost');
if (!defined('DS')) {
	define('DS', DIRECTORY_SEPARATOR);
}

if (!defined('ROOT')) {
	define('ROOT', dirname("."));
}

/**
 * The actual directory name for the "app".
 *
 */
if (!defined('APP_DIR')) {
	define('APP_DIR', 'app');
}

// define('CAKE_CORE_INCLUDE_PATH', ROOT . DS . 'lib');

if (!defined('WEBROOT_DIR')) {
	define('WEBROOT_DIR', 'app/webroot');
}
if (!defined('WWW_ROOT')) {
	define('WWW_ROOT', dirname(__FILE__) . DS . "app" . DS . "webroot");
}

// for built-in server
if (php_sapi_name() === 'cli-server') {
	if ($_SERVER['REQUEST_URI'] !== '/' && file_exists(WWW_ROOT . $_SERVER['PHP_SELF'])) {
		return false;
	}
	$_SERVER['PHP_SELF'] = '/' . basename(__FILE__);
}

if (!defined('CAKE_CORE_INCLUDE_PATH')) {
	if (function_exists('ini_set')) {
		ini_set('include_path', ROOT . DS . 'lib' . PATH_SEPARATOR . ini_get('include_path'));
	}
	if (!include 'Cake' . DS . 'bootstrap.php') {
		$failed = true;
	}
} else {
	if (!include CAKE_CORE_INCLUDE_PATH . DS . 'Cake' . DS . 'bootstrap.php') {
		$failed = true;
	}
}
if (!empty($failed)) {
	trigger_error("CakePHP core could not be found. Check the value of CAKE_CORE_INCLUDE_PATH in APP/webroot/index.php. It should point to the directory containing your " . DS . "cake core directory and your " . DS . "vendors root directory.", E_USER_ERROR);
}

echo "\nincluding AutoController ...";
require_once 'app/Controller/AutoController.php';
echo "\ncreating AutoController ...";
$autoController = new AutoController();
$autoController->response = new CakeResponse();
echo "\nsending mails ...\n";
$autoController->index('PlanController', 'sendMissingShiftMails', 'Plan-unvollständig-Mail');
exit;
?>