<select name="<?php echo $name ?>" id="field-<?php echo $name ?>">
	<?php foreach($choices as $choice): ?>
		<?php if ($choice == $value): ?>
			<option value="<?php echo $choice ?>" selected="selected"><?php echo $choice ?></option>
		<?php else: ?>
			<option value="<?php echo $choice ?>"><?php echo $choice ?></option>
		<?php endif; ?>
	<?php endforeach; ?>
</select>