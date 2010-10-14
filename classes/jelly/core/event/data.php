<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Jelly_Event_Data is the object passed to all events.
 * 
 * It contains a set of public properties passed to it 
 * whoever triggered the event as well as the name of 
 * the event being called.
 * 
 * Set `return` to whatever value you'd like to return,
 * though you should keep in mind that other events,
 * that come after may modify that value, so set
 * `stop` to TRUE if you want to prevent the chain from
 * continuing.
 * 
 * @package  Jelly
 */
abstract class Jelly_Core_Event_Data
{
	/**
	 * @var  string  The name of the event
	 */
	public $event = NULL;
	
	/**
	 * @var  mixed  The sender of the event
	 */
	public $sender = NULL;
	
	/**
	 * @var  args  An array of args sent to the event
	 */
	public $args = array();
	
	/**
	 * @var  string  The return value of the event
	 */
	public $return = NULL;
	
	/**
	 * @var  boolean  Whether or not to stop execution of events
	 */
	public $stop = FALSE;
	
	/**
	 * Throws all event parameters into the object as public variables
	 *
	 * @param  array  $params 
	 */
	public function __construct($params)
	{	
		foreach ($params as $param => $value)
		{
			$this->$param = $value;
		}
	}
	
	/**
	 * Stops execution of the event
	 *
	 * @return void
	 */
	public function stop()
	{
		$this->stop = TRUE;
	}
}
