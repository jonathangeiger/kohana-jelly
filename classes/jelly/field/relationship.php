<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Declares a relationship field, which has special characteristics in the mode.
 *
 * @package jelly
 */
interface Jelly_Field_Relationship
{	
	public function has($model, $ids);
}
