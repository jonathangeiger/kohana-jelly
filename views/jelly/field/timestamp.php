<?php echo Form::input($name, ( ! isset($default) && ! isset($value)) ? '' : date($pretty_format, $value), $attributes + array('id' => 'field-'.$name));
