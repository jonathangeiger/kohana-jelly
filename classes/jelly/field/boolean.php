<?php defined('SYSPATH') or die('No direct script access.');

class Jelly_Field_Boolean extends Jelly_Field
{	
	/**
	 * @var mixed How TRUE is represented in the database
	 */
	public $true = 1;
	
	/**
	 * @var string How TRUE is represented to users (mainly in forms)
	 */
	public $pretty_true = "Yes";
	
	/**
	 * @var stringHow FALSE is represented in the database
	 */
	public $false = 0;
	
	/**
	 * @var string How FALSE is represented to users (mainly in forms)
	 */
	public $pretty_false = "No";
	
	/**
	 * Validates a boolean out of the value with filter_var
	 *
	 * @param mixed $value 
	 * @return void
	 * @author Jonathan Geiger
	 */
	public function set($value)
	{
		return filter_var($value, FILTER_VALIDATE_BOOLEAN);
	}
	
	/**
	 * Returns the value as it should be represented in the database
	 *
	 * @param  Jelly  $model 
	 * @param  mixed  $value
	 * @return mixed
	 * @author Jonathan Geiger
	 */
	public function save($model, $value)
	{
		return ($value) ? $this->true : $this->false;
	}
}
