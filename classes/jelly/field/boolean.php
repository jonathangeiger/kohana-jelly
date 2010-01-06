<?php defined('SYSPATH') or die('No direct script access.');

class Jelly_Field_Boolean extends Jelly_Field
{	
	/**
	 * How TRUE is represented in the database
	 *
	 * @var string
	 */
	protected $true = 1;
	
	/**
	 * How FALSE is represented in the database
	 *
	 * @var string
	 */
	protected $false = 0;
	
	/**
	 * Validates a boolean out of the value with filter_var
	 *
	 * @param mixed $value 
	 * @return void
	 * @author Jonathan Geiger
	 */
	public function set($value)
	{
		$this->value = filter_var($value, FILTER_VALIDATE_BOOLEAN);
	}
	
	/**
	 * Returns the value as it should be represented in the database
	 *
	 * @param string $loaded 
	 * @return void
	 * @author Jonathan Geiger
	 */
	public function save($loaded)
	{
		return ($this->value) ? $this->true : $this->false;
	}
}
