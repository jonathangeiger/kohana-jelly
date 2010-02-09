<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Allows a field to declare that it can be joined using with() in the model
 *
 * @package jelly
 */
interface Jelly_Field_Joinable
{	
	public function with($model, $relation, $target_path, $parent_path);
}
