<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Jelly_Behavior_Collection acts as a manager for a model's behaviors.
 * 
 * The collection is responsible for passing callbacks along to behaviors
 * as well as models and builders. This allows a model or builder class
 * to override its own callback methods without having to override core
 * methods.
 * 
 * Jelly_Behavior_Collection also keeps track of custom methods attached
 * to behaviors and makes them available to models and builders. Each custom
 * method is namespaced with the behavior's name to avoid clashes. 
 * 
 * For example, consider a model with a soft_delete behavior named 
 * soft_delete in the meta  object. A custom method to permanently delete 
 * records named 'destroy' can be called as such:
 * 
 *     $model->destroy();
 * 
 * Or:
 * 
 *     $model->soft_delete_destroy();
 * 
 * If two behaviors implement the same method, the last behavior added
 * to the meta object will take precedence over all others. This is to
 * mimic PHP and Kohana (particularly Kohana's module system).
 * 
 * @package  Jelly
 */
class Jelly_Behavior_Collection_Core
{
	/**
	 * @var  string  The class this collection is attached to
	 */
	protected $_model = NULL;
	
	/**
	 * @var  array  All behaviors
	 */
	protected $_behaviors = array();
	
	/**
	 * @var  array  All custom behavior methods
	 */
	protected $_methods = array();
	
	/**
	 * Sets up behaviors and documents their custom methods.
	 *
	 * @param  array  $behaviors 
	 * @param  string $model
	 */
	public function __construct($behaviors, $model)
	{
		// Just so we know what we're dealing with
		$this->_model = $model;
		
		// Process all behaviors
		foreach ($behaviors as $name => $behaviors)
		{
			// Register any public methods from the behaviour
			foreach (get_class_methods($behavior) as $method)
			{
				if (($ns = substr($method, 0, 6)) === 'model_' 
				OR  ($ns = substr($method, 0, 8)) === 'builder_')
				{
					$method = substr($method, strlen($ns));

					// Create a normal method without the $name prefix...
					$this->_methods[$ns.$method] = 

					// ...and an alias so that we can avoid clashes if necessary
					$this->_methods[$ns.$name.'_'.$method] = 

					// and save as a callback
					array($behavior, $ns.$method);
				}
			}
		}
	}
	
	/**
	 * Calls a custom method. 
	 *
	 * @param   string  $method 
	 * @param   object  $sender
	 * @param   array   $args 
	 * @return  mixed
	 */
	public function call($method, $sender, $args)
	{
		// Is this a custom method?
		if (!empty($this->_methods[$method]))
		{
			$object = $this->_methods[$method][0];
			$method = $this->_methods[$method][1];
			$count  = count($args);

			switch ($count) 
			{
				case 0;
					return $object->{$method}($sender);
				case 1:
					return $object->{$method}($sender, $args[0]);
				case 2:
					return $object->{$method}($sender, $args[0], $args[1]);
				case 3:
					return $object->{$method}($sender, $args[0], $args[1], $args[2]);
				case 4:
					return $object->{$method}($sender, $args[0], $args[1], $args[2], $args[3]);
				case 5:
					return $object->{$method}($sender, $args[0], $args[1], $args[2], $args[3], $args[4]);
				default:
					return call_user_func_array(array($object, $method), array_unshift($sender, $args));
			}
		}
		
		throw new Kohana_Exception('Invalid behavior method :method called on class :class',
			array(':method' => $method, ':class' => get_class($sender)));
	}
	
	/**
	 * Calls all behaviors' initialize callback.
	 * 
	 * @see     Jelly_Behavior::initialize
	 * @param   Jelly_Meta  $meta 
	 * @return  void
	 */
	public function initialize(Jelly_Meta $meta)
	{
		foreach ($this->_behaviors as $behavior)
		{
			$behavior->initialize($meta);
		}
	}
	
	/**
	 * Calls all behaviors' before_select callback.
	 * 
	 * @see     Jelly_Behavior::before_select
	 * @param   Jelly_Builder  $query 
	 * @return  void
	 */
	public function before_select(Jelly_Builder $query)
	{
		foreach ($this->_behaviors as $behavior)
		{
			$behavior->before_select($query);
		}
		
		// Trigger builder callback
		$query->before_select();
	}
	
	/**
	 * Calls all behaviors' after_select callback.
	 * 
	 * @see     Jelly_Behavior::after_select
	 * @param   Jelly_Builder     $query 
	 * @param   Jelly_Collection|Jelly_Model  $result
	 * @return  void
	 */
	public function after_select(Jelly_Builder $query, $result)
	{
		foreach ($this->_behaviors as $behavior)
		{
			$behavior->before_select($query);
		}
		
		// Trigger builder callback
		$query->after_select($result);
	}
	
	/**
	 * Calls all behaviors' before_validate callback.
	 * 
	 * @see     Jelly_Behavior::before_validate
	 * @param   Jelly_Model  $model 
	 * @param   Validate     $data
	 * @return  void
	 */
	public function before_validate(Jelly_Model $model, Validate $data) 
	{
		foreach ($this->_behaviors as $behavior)
		{
			$behavior->before_validate($model, $data);
		}
		
		// Trigger model callback
		$model->before_validate($data);
	}
	
	/**
	 * Calls all behaviors' before_save callback.
	 * 
	 * @see     Jelly_Behavior::before_save
	 * @param   Jelly_Model  $model 
	 * @param   mixed        $key
	 * @return  boolean
	 */
	public function before_save(Jelly_Model $model, $key)
	{
		foreach ($this->_behaviors as $behavior)
		{
			if (FALSE === $behavior->before_save($model, $key))
			{
				return FALSE;
			}
		}
		
		// Trigger model callback
		if (FALSE === $model->before_save($key))
		{
			return FALSE;
		}
	}
	
	/**
	 * Calls all behaviors' after_save callback.
	 * 
	 * @see     Jelly_Behavior::after_save
	 * @param   Jelly_Model  $model 
	 * @return  void
	 */
	public function after_save(Jelly_Model $model)
	{
		foreach ($this->_behaviors as $behavior)
		{
			$behavior->after_save($model);
		}
		
		// Trigger model callback
		$model->after_save($key);
	}
	
	/**
	 * Calls all behaviors' before_delete callback.
	 * 
	 * @see     Jelly_Behavior::before_delete
	 * @param   Jelly_Model  $model 
	 * @param   mixed        $key
	 */
	public function before_delete(Jelly_Model $model, $key) 
	{
		foreach ($this->_behaviors as $behavior)
		{
			if (FALSE === $behavior->before_delete($model, $key))
			{
				return FALSE;
			}
		}
		
		// Trigger model callback
		if (FALSE === $model->before_delete($key))
		{
			return FALSE;
		}
	}
	
	/**
	 * Calls all behaviors' after_delete callback.
	 * 
	 * @see     Jelly_Behavior::after_delete
	 * @param   Jelly_Model   $model 
	 * @return  void
	 */
	public function after_delete(Jelly_Model $model)
	{
		foreach ($this->_behaviors as $behavior)
		{
			$behavior->after_delete($model);
		}
		
		// Trigger model callback
		$model->after_delete($key);
	}
}
