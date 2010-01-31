<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Automatically serializes any PHP value for instertion
 *
 * @package default
 * @author Jonathan Geiger
 */
class Jelly_Field_Serialized extends Jelly_Field
{
	/**
	 * Unserializes data as soon as it comes in
	 *
	 * @param string $value 
	 * @return void
	 * @author Jonathan Geiger
	 */
	public function set($value)
	{		
	 	return @unserialize($value);
	}
	
	/**
	 * Saves the value as a serialized object
	 *
	 * @param string $loaded 
	 * @return void
	 * @author Jonathan Geiger
	 */
	public function save($model, $value)
	{
		return @serialize($value);
	}
	
} // End Sprig_Field
