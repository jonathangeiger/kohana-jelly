<select name="<?php echo $name ?>[]" id="field-<?php echo $name ?>" multiple="multiple">
	<?php foreach(Jelly::select($foreign['model'])->execute() as $related): ?>
		<?php if (in_array($related->id(), $ids)): ?>
			<option value="<?php echo $related->id() ?>" selected='selected'><?php echo $related->name()?></option>
		<?php else: ?>
			<option value="<?php echo $related->id() ?>"><?php echo $related->name()?></option>
		<?php endif; ?>
	<?php endforeach; ?>
</select>