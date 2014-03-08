<?php
/**
 *
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
 * @package       app.View.Layouts
 * @since         CakePHP(tm) v 0.10.0.1076
 * @license       http://www.opensource.org/licenses/mit-license.php MIT License
 */

$cakeDescription = __d('cake_dev', 'Humboldtgymnasium Berlin Tegel - Cafeteriaplaner');
?>
<!DOCTYPE html>
<html>
<head>
	<?php echo $this->Html->charset(); ?>	
	<title>
		<?php echo $cakeDescription ?>:
		<?php echo $title_for_layout; ?>
	</title>
	<?php
		echo $this->Html->meta('icon');

		//echo $this->Html->css('cake.generic');
		echo $this->Html->css('bootstrap');
		echo $this->Html->css('jquery-ui');
		echo $this->Html->script('jquery');
		echo $this->Html->script('jquery-ui');
		echo $this->Html->script('/CakeBootstrappifier/js/cakebootstrap');

		echo $this->fetch('meta');
		echo $this->fetch('css');
		echo $this->fetch('script');
	?>
</head>
<body style="padding-top: 60px; padding-bottom: 40px;">
	<?php echo $this->element('navbar'); ?>
	<div class="container-fluid">
		<div class="row-fluid">	
				<?php echo $this->Session->flash(); ?>
				<?php echo $this->fetch('content'); ?>

		</div>
		<hr>
		<footer>
			<p> &copy; 2014 by Die Neuinstallisierer </p>
		</footer>
	</div>
	<?php echo $this->element('sql_dump'); ?>
</body>
</html>
