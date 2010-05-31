<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Handles boolean data types.
 *
 * @package  Jelly
 */
abstract class Jelly_Field_Boolean extends Jelly_Field
{
	/**
	 * @var  mixed  How TRUE is represented in the database
	 */
	public $true = 1;

	/**
	 * @var  string  How TRUE is represented to users (mainly in forms)
	 */
	public $label_true = "Yes";

	/**
	 * @var mixed How FALSE is represented in the database
	 */
	public $false = 0;

	/**
	 * @var  string  How FALSE is represented to users (mainly in forms)
	 */
	public $label_false = "No";

	/**
	 * Validates a boolean out of the value with filter_var
	 *
	 * @param   mixed  $value
	 * @return  void
	 */
	public function set($value)
	{
		return filter_var($value, FILTER_VALIDATE_BOOLEAN);
	}

	/**
	 * Returns the value as it should be represented in the database
	 *
	 * @param   Jelly_Model  $model
	 * @param   mixed        $value
	 * @param   boolean      $loaded
	 * @return  mixed
	 */
	public function save($model, $value, $loaded)
	{
		return ($value) ? $this->true : $this->false;
	}
}
