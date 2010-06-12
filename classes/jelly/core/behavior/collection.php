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
abstract class Jelly_Core_Behavior_Collection
{
	/**
	 * @var  array  All callbacks to search for
	 */
	protected static $_allowed = array
	(
		// Meta callbacks
		'after_initialize', 
		
		// Builder callbacks
		'before_builder_select', 'after_builder_select', 
		'before_builder_insert', 'after_builder_insert', 
		'before_builder_update', 'after_builder_update', 
		'before_builder_delete', 'after_builder_delete', 
		
		// Model callbacks
		'before_model_validate', 
		'before_model_save', 'after_model_save', 
		'before_model_delete', 'after_model_delete'
	);
	
	/**
	 * @var  array  All custom behavior methods
	 */
	protected $_methods = array();
	
	/**
	 * @var  array  All custom callback methods
	 */
	protected $_callbacks = array();
	
	/**
	 * @var  array    Flag for picking up model and builder callbacks, since 
	 *                we want to wait for an instantiated model to check.
	 *                Reflection is way too slow.
	 */
	protected $_callbacks_discovered = array();
	
	/**
	 * Sets up behaviors and documents their custom methods.
	 *
	 * @param  array       $behaviors 
	 * @param  Jelly_Meta  $model
	 */
	public function __construct($behaviors)
	{	
		// Process all behaviors
		foreach ($behaviors as $name => $behavior)
		{
			$methods = get_class_methods($behavior);
			
			// Register any public methods from the behaviour
			foreach ($methods as $method)
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
				
				// Check if the method is a callback
				if (in_array($method, Jelly_Behavior_Collection::$_allowed))
				{
					$this->_callbacks[$method][] = $behavior;
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
			
			array_unshift($args, $sender);
			return $this->_call($object, $method, $args);
		}
		
		throw new Kohana_Exception('Invalid behavior method :method called on class :class',
							array(':method' => $method, ':class' => get_class($sender)));
	}
	
	/**
	 * Called just after the model's initialize method has been run.
	 * 
	 * This gives the behavior a chance to override any part
	 * of the model's meta object, add fields, etc.
	 * 
	 * There is no 'before_initialize' because we cannot know 
	 * what behaviors have been added to the meta object before
	 * initialization.
	 *
	 * @param   Jelly_Meta  $meta 
	 * @return  void
	 */
	public function after_initialize(Jelly_Meta $meta)
	{
		$this->_trigger(__FUNCTION__, $meta, array(), array('discover' => FALSE));
	}
	
	/**
	 * Called just before executing a SELECT.
	 *
	 * @param   Jelly_Builder  $query 
	 * @return  void
	 */
	public function before_builder_select(Jelly_Builder $query)
	{
		$this->_trigger(__FUNCTION__, $query);
	}
	
	/**
	 * Called just after executing a SELECT.
	 *
	 * @param   Jelly_Builder     $query 
	 * @param   Jelly_Collection  $result
	 * @return  void
	 */
	public function after_builder_select(Jelly_Builder $query, Jelly_Collection $result)
	{
		$this->_trigger(__FUNCTION__, $query, array($result));
	}
	
	/**
	 * Called just before executing an INSERT.
	 *
	 * @param   Jelly_Builder  $query 
	 * @return  void
	 */
	public function before_builder_insert(Jelly_Builder $query)
	{
		$this->_trigger(__FUNCTION__, $query);
	}
	
	/**
	 * Called just after executing an INSERT.
	 *
	 * @param   Jelly_Builder  $query 
	 * @param   array          $result
	 * @return  void
	 */
	public function after_builder_insert(Jelly_Builder $query, $result)
	{
		$this->_trigger(__FUNCTION__, $query, array($result));
	}
	
	/**
	 * Called just before executing an UPDATE.
	 *
	 * @param   Jelly_Builder  $query 
	 * @return  void
	 */
	public function before_builder_update(Jelly_Builder $query)
	{
		$this->_trigger(__FUNCTION__, $query);
	}
	
	/**
	 * Called just after executing an UPDATE.
	 *
	 * @param   Jelly_Builder  $query 
	 * @param   int            $result
	 * @return  void
	 */
	public function after_builder_update(Jelly_Builder $query, $result)
	{
		$this->_trigger(__FUNCTION__, $query, array($result));
	}
	
	/**
	 * Called just before executing an DELETE.
	 * 
	 * The callback can return a value other than NULL to 
	 * stop the delete from occurring.
	 *
	 * @param   Jelly_Builder  $query 
	 * @return  void
	 */
	public function before_builder_delete(Jelly_Builder $query)
	{
		return $this->_trigger(__FUNCTION__, $query, array(), array('allow_break' => TRUE));
	}
	
	/**
	 * Called just after executing a DELETE.
	 *
	 * @param   Jelly_Builder  $query 
	 * @param   int            $result
	 * @return  void
	 */
	public function after_builder_delete(Jelly_Builder $query, $result)
	{
		$this->_trigger(__FUNCTION__, $query, array($result));
	}
	
	/**
	 * Called before validating when the data is in its raw form
	 * in the model. Fields have not had a chance to process
	 * it with their save() method.
	 *
	 * @param   Jelly_Model  $model 
	 * @param   Validate     $data
	 * @return  void
	 */
	public function before_model_validate(Jelly_Model $model, Validate $data) 
	{
		$this->_trigger(__FUNCTION__, $model, array($data));
	}
	
	/**
	 * Called before saving, giving the behavior a chance
	 * to modify data before it's saved.
	 * 
	 * $key is the primary key the model is about to be 
	 * saved to. If it is NULL, it's safe to assume that
	 * the record is about to be inserted, otherwise it's
	 * an update.
	 * 
	 * Return FALSE to cancel the save and any further 
	 * processing by behaviors.
	 *
	 * @param   Jelly_Model  $model 
	 * @param   mixed        $key
	 * @return  boolean
	 */
	public function before_model_save(Jelly_Model $model, $key)
	{
		return $this->_trigger(__FUNCTION__, $model, array($key), array('allow_break' => TRUE));
	}
	
	/**
	 * Called after saving, giving the behavior a chance
	 * to modify data after it's saved.
	 *
	 * @param   Jelly_Model  $model 
	 * @return  void
	 */
	public function after_model_save(Jelly_Model $model)
	{
		$this->_trigger(__FUNCTION__, $model);
	}
	
	/**
	 * Called whenever a model is deleted.
	 * 
	 * $key is the primary key that is about to be 
	 * deleted. 
	 * 
	 * Return FALSE to cancel the delete and any 
	 * further processing by behaviors.
	 *
	 * @param   Jelly_Model  $model 
	 * @param   mixed        $key
	 */
	public function before_model_delete(Jelly_Model $model, $key) 
	{
		return $this->_trigger(__FUNCTION__, $model, array($key), array('allow_break' => TRUE));
	}
	
	/**
	 * Called after deletion but before the model is cleared of its contents.
	 * 
	 * $result is a boolean that indicates whether or not the model was 
	 * actually deleted.
	 *
	 * @param   Jelly_Model  $model 
	 * @param   mixed        $key
	 * @param   boolean      $result
	 * @return  void
	 */
	public function after_model_delete(Jelly_Model $model, $key, $result)
	{
		$this->_trigger(__FUNCTION__, $model, $key, $result);
	}
	
	/**
	 * Triggers a callback to avoid duplicating a bunch of code.
	 *
	 * @param  string  $method 
	 * @param  mixed   $sender 
	 * @param  array   $args 
	 * @param  array   $options
	 * @return mixed
	 */
	protected function _trigger($method, $sender, $args = array(), $options = array())
	{
		// Do we need to discover callbacks?
		if ( ! isset($options['discover']) OR ! empty($options['discover']))
		{
			$this->_discover_callbacks($sender, get_class($sender));
		}
		
		// Track the return value
		$return = NULL;
		
		// Ensure we have something to work with
		if (empty($this->_callbacks[$method])) return;
		
		// Loop through our callbacks
		foreach ($this->_callbacks[$method] as $key => $callback)
		{
			// Prevent callbacks being called on objects that shouldn't receive them
			if (is_string($callback) AND $sender instanceof $callback)
			{
				$return = $this->_call($sender, $method, $args);
			}
			else if ($callback instanceof Jelly_Behavior)
			{
				// We have to make a copy of args so we don't 
				// continue array_unshifting it on the next iteration
				// Object references will be preserved.
				$args_copy = $args;
				array_unshift($args_copy, $sender);
				
				// Call the method with the copy of the args
				$return = $this->_call($callback, $method, $args_copy);
			}
			
			// Ensure we can continue execution
			if (!empty($options['allow_break']) AND $return instanceof Jelly_Behavior_Result AND $return->break)
			{
				return $return->value;
			}
		}
	}
	
	/**
	 * Method caller.
	 *
	 * @param   object  $object 
	 * @param   string  $method 
	 * @param   array   $args 
	 * @return  mixed
	 */
	protected function _call($object, $method, $args)
	{
		return Jelly::call(array($object, $method), $args);
	}
	
	/**
	 * Discovers callbacks for models and builders.
	 * 
	 * We have to wait until we're passed a valid object, since
	 * we don't want to go instantiating one just to get the 
	 * whole inheritance tree and also since  Reflection is way 
	 * too slow.
	 *
	 * @param   mixed   $object 
	 * @param   string  $type 
	 * @return  void
	 */
	protected function _discover_callbacks($object, $type)
	{
		if (!isset($this->_callbacks_discovered[$type]))
		{
			$methods = array_intersect(get_class_methods($object), Jelly_Behavior_Collection::$_allowed);
			
			foreach ($methods as $method)
			{
				// Adding the class of the object allows _trigger()
				// to compare the actual senders class with what we
				// discovered here. For example, if someone put the
				// before_select() callback in a model, it would be
				// erroneously discovered here, but not actually called
				// down the line since the sender would be an instance
				// of a Jelly_Builder and not the class we'd expect. 
				$this->_callbacks[$method][] = get_class($object);
			}
			
			// Don't need to do this again
			$this->_callbacks_discovered[$type] = TRUE;
		}
	}
}
