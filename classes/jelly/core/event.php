<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Jelly_Event acts as a manager for all events bound to a model.
 * 
 * The standard events are documented in the guide. Binding and
 * triggering custom events is entirely possible.
 * 
 * @package  Jelly
 */
abstract class Jelly_Core_Event
{
	/**
	 * @var  array  The current model
	 */
	protected $_model = NULL;
	
	/**
	 * @var  array  Bound events
	 */
	protected $_events = array();
	
	/**
	 * Constructor.
	 *
	 * @param  string  $model
	 */
	public function __construct($model)
	{	
		$this->_model = $model;
	}
	
	/**
	 * Binds an event.
	 *
	 * @param   string    $event 
	 * @param   callback  $callback 
	 * @return  $this
	 */
	public function bind($event, $callback)
	{
		$this->_events[$event][] = $callback;
	}
	
	/**
	 * Triggers an event.
	 *
	 * @param   string  $event 
	 * @param   array   $params 
	 * @return  mixed
	 */
	public function trigger($event, $params = array())
	{
		if ( ! empty($this->_events[$event]))
		{
			$data = new Jelly_Event_Data(arr::merge($params, array('event' => $event)));
			
			foreach ($this->_events[$event] as $callback)
			{
				Jelly::call($callback, array($data));
				
				if ($data->stop)
				{
					break;
				}
			}
			
			return $data->return;
		}
		
		return NULL;
	}
}
