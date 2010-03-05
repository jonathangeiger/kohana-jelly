<?php echo Form::select($name, array(
	$true	=> $label_true,
	$false	=> $label_false,
), $value ? $true : $false, $attributes + array('id' => 'field-'.$name)); ?>