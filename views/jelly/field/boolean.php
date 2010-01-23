<select name="<?php echo $name ?>" id="field-<?php echo $name ?>">
	<option <?php echo ($value == TRUE) ? 'selected="selected"' : '' ?> value="<?php echo $true ?>"><?php echo $pretty_true ?></option>
	<option <?php echo ($value == FALSE) ? 'selected="selected"' : '' ?> value="<?php echo $false ?>"><?php echo $pretty_false ?></option>
</select>