<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Simple class for returning values from callbacks.
 *
 * @package  Jelly
 */
class Jelly_Behavior_Result_Core
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
	public function __construct(array $options = array())
	{
		foreach ($options as $key => $value)
		{
			$this->$key = $value;
		}
	}
}
