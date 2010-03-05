<?php echo Form::textarea($name, $value, $attributes + array(
	'id' => 'field-'.$name,
	'rows' => 8,
	'cols' => 40,
)); ?>