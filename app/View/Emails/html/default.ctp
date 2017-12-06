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
 * @package       app.View.Emails.html
 * @since         CakePHP(tm) v 0.10.0.1076
 * @license       http://www.opensource.org/licenses/mit-license.php MIT License
 */
?>
<?php $content = explode("\n", $content); ?>
<h1>Cafeteria der Humboldt-Oberschule</h1>
<h2><i>Eine Nachricht für Sie</i></h2>
<table>
  <tr>
    <td>Von:</td>
    <td><?php echo $senderName . " (". $senderMail .")";?></td>
  </tr>
  <tr>
  	<td>Betreff:</td>
  	<td><?php echo $subject;?></td>
  </tr>
</table>
<br>
<?php 
foreach ($content as $line):
	echo '<p> ' . $line . "</p>\n";
endforeach;
?>
<br>
<?php 
if (isset($allowReply) && $allowReply)
	echo "<p>Wenn Sie auf diese E-Mail antworten, so wird sie ausschließlich dem Absender zugestellt.</p>";
else
	echo "<p>Diese E-Mail wurde automatisch generiert. Bitte antworten Sie nicht darauf.</p>"	
?>
