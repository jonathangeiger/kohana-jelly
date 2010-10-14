<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Jelly_Behavior is a simple class that all behaviors must extend
 * so that behaviors can be consistently packaged and used.
 * 
 * Behaviors are expected to bind themselves to events on the 
 * model in their initialize() method. By default, Jelly will 
 * auto-discover your events and bind them for you as long as
 * you prefix the method with either "model" or "builder".
 * 
 * For example, a method in your behavior named "model_before_save"
 * will be bound to the "model.before_save" event.
 * 
 * You can prevent this auto-discovering of methods by overloading
 * the `event()` method in this class and binding the events
 * yourself.
 * 
 * The guide explains all available events, as well as how to 
 * use custom events.
 *
 * @see      Jelly_Behavior
 * @package  Jelly
 */
abstract class Jelly_Core_Behavior
{	
	/**
	 * @var  string  The model this is attached to
	 */
	protected $_model;
	
	/**
	 * @var  string  The name of this behavior
	 */
	protected $_name;
	
	/**
	 * Constructor.
	 *
	 * @param   array   $params 
	 */
	public function __construct($params = array())
	{
		foreach ($params as $key => $param)
		{
			$this->{'_'.$key} = $param;
		}
	}
	
	/**
	 * Initialize.
	 *
	 * @param   Jelly_Event  $event
	 * @param   string       $model
	 * @param   string       $name
	 * @return  void
	 */
	public function initialize(Jelly_Event $event, $model, $name)
	{
		$this->_name  = $name;
		$this->_model = $model;
		
		$this->_discover_events($event);
	}
	
	/**
	 * Simple method for auto-discovering events on the behavior.
	 * 
	 * Only methods prefixed with either builder_ or model_
	 * will be considered. 
	 * 
	 * @param   Jelly_Event  $event
	 * @return  void
	 */
	protected function _discover_events(Jelly_Event $event)
	{
		foreach (get_class_methods($this) as $method)
		{
			if (($ns = substr($method, 0, 5)) === 'model' 
			OR  ($ns = substr($method, 0, 4)) === 'meta'
			OR  ($ns = substr($method, 0, 7)) === 'builder')
			{
				$event->bind(strtolower($ns.'.'.substr($method, strlen($ns) + 1)), array($this, $method));
			}
		}
	}
}
