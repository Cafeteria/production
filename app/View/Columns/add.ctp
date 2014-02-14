<div class="columns form">
<?php echo $this->Form->create('Column', array(
	'inputDefaults' => array(
		'div' => 'control-group',
		'label' => array(
			'class' => 'control-label'
		)
)
)); ?>
	<fieldset>
		<legend><?php echo 'Neue Spalte anlegen'; ?></legend>
	<?php
		echo $this->Form->input('name', array('label' => 'Name'));
		echo $this->Form->input('type', array('label' => 'Typ', 'type' => 'select', 'options' => array(1 => 'Text', 2 => 'Benutzer')));
		echo $this->Form->input('obligated', array('label' => 'Belegung notwendig', 'type' => 'select', 'options' => array(0 => 'Nein', 1 => 'Ja')));
		echo $this->Form->input('req_admin', array('label' => 'Eintragen erfordert Adminrechte', 'type' => 'select', 'options' => array(0 => 'Nein', 1 => 'Ja')));
	?>
	</fieldset>
<?php echo $this->Form->end(__('Spalte anlegen')); ?>
</div>
<div class="actions">
	<h3><?php echo __('Aktionen'); ?></h3>
	<ul>
		<li><?php echo $this->Html->link(__('Spalten anzeigen'), array('action' => 'index')); ?></li>
	</ul>
</div>
