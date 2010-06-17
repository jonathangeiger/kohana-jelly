<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Simple class for returning values from callbacks.
 *
 * @package  Jelly
 */
abstract class Jelly_Core_Behavior_Result
{
	/**
	 * @var  mixed  The value to return
	 */
	public $value = NULL;
	
	/**
	 * @var  boolean  Whether or not to stop execution
	 */
	public $break = FALSE;
	
	/**
	 * Constructor
	 *
	 * @param  array  $options 
	 */
	public function __construct($value, $break = FALSE)
	{
		$this->value = $value;
		$this->break = FALSE;
	}
}
