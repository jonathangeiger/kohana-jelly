<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Jelly_Validate overrides Kohana's core validation class in order to add a few 
 * Jelly-specific features:
 * 
 *  * Adds filter_value(), since Jelly does not filter when validating but when 
 *    setting data.
 * 
 *  * Requires that a model be set. 
 * 
 *  * Only the data in the actual array passed will be validated. This means it is
 *    the developer's job to pass all keys they need validated.
 * 
 *  * It changes the declaration syntax to be consistent for filters, rules, 
 *    and callbacks, although the old way still works fine. Now, all filters,
 *    rules, and callbacks should be declared as such:
 * 
 *        'field' => array(
 *             // Params is entirely optional...
 *             array(callback $callback [, array $params]),
 *             array(callback $callback [, array $params]),
 *         );
 * 
 *    This means that callbacks can have parameters passed to them, and 
 *    filters can be called on objects.
 * 
 *  * It allows the :model context to be passed so that the method is called
 *    on the model being validated. This was not possible previously.
 * 
 *        'field' => array(
 *             array(array(':model', 'method'), array('arg1', 'arg2')),
 *         );
 * 
 * Since Jelly_Validate is a sort of singleton for your model (that is, all
 * instances of your model will use the same instance to validate itself, it is
 * best to make a clone if you need to change the rules, filters, or callbacks
 * for a specific instance.
 * 
 * @see     Jelly_Model::validate
 * @package Jelly
 */
abstract class Jelly_Core_Validate extends Kohana_Validate
{
	/**
	 * @var  Jelly_Model  The current model we're validating against
	 */
	protected $_model;
	
	/**
	 * Sets the unique "any field" key and creates an ArrayObject from the
	 * passed array.
	 *
	 * @param   array   array to validate
	 * @return  void
	 */
	public function __construct(array $array = array())
	{
		parent::__construct($array, ArrayObject::STD_PROP_LIST);
	}
	
	/**
	 * Copies the current filter/rule/callback to a new array.
	 *
	 *     $copy = $array->copy($model, $new_data);
	 *
	 * @param   Jelly_Model  $model
	 * @param   array        $array
	 * @return  Jelly_Validate
	 */
	public function copy(Jelly_Model $model, array $array)
	{
		// Create a copy of the current validation set
		$copy = clone $this;

		// Replace the data set
		$copy->exchangeArray($array);
		
		// Replace model
		$copy->_model = $model;

		return $copy;
	}
	
	/**
	 * Overwrites or appends filters to a field. Each filter will be executed once.
	 * All rules must be valid callbacks.
	 *
	 *     // Run trim() on all fields
	 *     $validation->filter(TRUE, 'trim');
	 *
	 * @param   string  field name
	 * @param   mixed   valid PHP callback
	 * @param   array   extra parameters for the callback
	 * @return  $this
	 */
	public function filter($field, $filter, array $params = NULL)
	{
		return $this->filters($field, array($filter, $params));
	}
	
	/**
	 * Add filters using an array.
	 *
	 * @param   string  field name
	 * @param   array   list of functions or static method name
	 * @return  $this
	 */
	public function filters($field, array $filters)
	{
		return $this->_parse_callbacks($this->_filters, $field, $filters);
	}

	/**
	 * Overwrites or appends rules to a field. Each rule will be executed once.
	 * All rules must be string names of functions method names.
	 *
	 *     // The "username" must not be empty and have a minimum length of 4
	 *     $validation->rule('username', 'not_empty')
	 *                ->rule('username', 'min_length', array(4));
	 *
	 * @param   string  field name
	 * @param   string  function or static method name
	 * @param   array   extra parameters for the callback
	 * @return  $this
	 */
	public function rule($field, $rule, array $params = NULL)
	{
		return $this->rules($field, array($rule, $params));
	}

	/**
	 * Add rules using an array.
	 *
	 * @param   string  field name
	 * @param   array   list of functions or static method name
	 * @return  $this
	 */
	public function rules($field, array $rules)
	{
		return $this->_parse_callbacks($this->_rules, $field, $rules);
	}

	/**
	 * Adds a callback to a field. Each callback will be executed only once.
	 * No extra parameters can be passed as the format for callbacks is
	 * predefined as (Validate $array, $field, array $errors).
	 *
	 *     // The "username" must be checked with a custom method
	 *     $validation->callback('username', array($this, 'check_username'));
	 *
	 * To add a callback to every field already set, use TRUE for the field name.
	 *
	 * @param   string  field name
	 * @param   mixed   callback to add
	 * @return  $this
	 */
	public function callback($field, $callback, array $params = NULL)
	{
		return $this->callbacks($field, array($callback, $params));
	}

	/**
	 * Add callbacks using an array.
	 *
	 * @param   string  field name
	 * @param   array   list of callbacks
	 * @return  $this
	 */
	public function callbacks($field, array $callbacks)
	{
		return $this->_parse_callbacks($this->_callbacks, $field, $callbacks);
	}
	
	/**
	 * Processes all filters on a specific value.
	 *
	 * @param   string       $field 
	 * @param   mixed        $value 
	 * @return  mixed
	 */
	public function filter_value($field, $value)
	{
		// Copy locally and process TRUE fields
		$fiters = $this->_filters;
		
		if (isset($filters[TRUE]))
		{
			if ( ! isset($filters[$field]))
			{
				// Initialize the filters for this field
				$filters[$field] = array();
			}

			// Append the filters
			$filters[$field] += $filters[TRUE];
		}
		
		// Don't process fields without filters
		if (empty($filters[$field]))
		{
			return $value;
		}
		
		foreach ($filters[$field] as $filter)
		{
			$callback = $filter[0];
			$params   = $filter[1];
			
			// Add the value as the first argument
			array_unshift($params, $value);
			
			// Call
			$value = $this->_call($callback, $params);
		}
		
		return $value;
	}
	
	/**
	 * Executes all validation rules and callbacks. 
	 * 
	 * This does not execute filters, since Jelly processes 
	 * filters on set and not when validating. 
	 *
	 * @return  boolean
	 */
	public function check()
	{
		if (Kohana::$profiling === TRUE)
		{
			// Start a new benchmark
			$benchmark = Profiler::start('Validation', __FUNCTION__);
		}

		// New data set
		$this->_errors = array();

		// Get a list of the expected fields
		$expected = array_keys($this);

		// Import the rules and callbacks locally
		// Only grab the keys that have been passed
		$rules     = Arr::extract($this->_rules, $expected);
		$callbacks = Arr::extract($this->_callbacks, $expected);
		
		// Process TRUE fields
		foreach ($expected as $field)
		{
			if (isset($rules[TRUE]))
			{
				if ( ! isset($rules[$field]))
				{
					// Initialize the rules for this field
					$rules[$field] = array();
				}

				// Append the rules
				$rules[$field] += $rules[TRUE];
			}

			if (isset($callbacks[TRUE]))
			{
				if ( ! isset($callbacks[$field]))
				{
					// Initialize the callbacks for this field
					$callbacks[$field] = array();
				}

				// Append the callbacks
				$callbacks[$field] += $callbacks[TRUE];
			}
		}
		
		// Execute the rules
		foreach ($rules as $field => $set)
		{
			// Get the field value
			$value = $this[$field];

			foreach ($set as $rule)
			{
				$callback = $rule[0];
				$params   = $rule[1];
				$method   = isset($callback[1]) ? $callback[1] : $callback;
				
				// If the field shouldn't be empty, but is, forgo any other rules
				if ( ! in_array($method, $this->_empty_rules) AND ! Validate::not_empty($value))
				{
					continue;
				}

				// Add the field value to the parameters
				array_unshift($params, $value);
				
				// Call and verify success
				if (FALSE === $this->_call($callback, $params))
				{
					// Remove the value from the parameters
					array_shift($params);

					// Add the rule to the errors
					$this->error($field, $method, $params);

					// This field has an error, stop executing rules
					break;
				}
			}
		}

		// Execute the callbacks
		foreach ($callbacks as $field => $set)
		{
			if (isset($this->_errors[$field]))
			{
				// Skip any field that already has an error
				continue;
			}

			foreach ($set as $_callback)
			{
				$callback = $_callback[0];
				$params   = $_callback[1];
				
				// Add the Validate object, field, and model to the beginning of the argument list
				array_unshift($params, $this, $field, $this->_model);
				
				// Call the callback
				$this->_call($callback, $params);

				if (isset($this->_errors[$field]))
				{
					// An error was added, stop processing callbacks
					break;
				}
			}
		}

		if (isset($benchmark))
		{
			// Stop benchmarking
			Profiler::stop($benchmark);
		}

		return empty($this->_errors);
	}
	
	/**
	 * Method caller.
	 * 
	 * Handles conversion of the :model context.
	 *
	 * @param   callback     $callback 
	 * @param   mixed        $params 
	 * @return  mixed
	 */
	protected function _call($callback, $params)
	{
		// Check to see if we need to replace the context
		if (is_array($callback) AND isset($callback[0]) AND $callback[0] === ':model')
		{
			$callback[0] = $this->_model;
		}
		
		return Jelly::call($callback, $params);
	}
	
	/**
	 * Generic method for adding filters, rules, or callbacks.
	 * 
	 * This method is useful because it converts all callbacks to the exact same 
	 * format and remains backwards-compatible with all of the different ways
	 * the regular old Validate class allows filters, rules, and callbacks to be called.
	 * 
	 * This also means that filter callbacks can now be set in the `array($obj, $method)`
	 * style and that callbacks can have arguments.
	 * 
	 * The preferred style, as documented elsewhere, is this:
	 * 
	 *    array(callback $callback[, array $params]); // $params is entirely optional.
	 *
	 * @param   array   $array 
	 * @param   string  $field 
	 * @param   array   $callbacks 
	 * @return  $this
	 */
	protected function _callbacks(array &$array, $field, array $callbacks)
	{
		// Ensure there is a label
		if ($field !== TRUE AND ! isset($this->_labels[$field]))
		{
			// Set the field label to the field name
			$this->_labels[$field] = preg_replace('/[^\pL]+/u', ' ', $field);
		}
		
		// Ensure we have a set to work on
		if ( ! isset($array[$field]))
		{
			$array[$field] = array();
		}
		
		// Switch to the real array we're modifying
		$array =& $array[$field];
			
		// We've alot of different types to handle here
		foreach ($callbacks as $key => $value)
		{
			if (is_numeric($key))
			{
				// Callback without arguments
				if (is_string($value) OR (is_array($value) AND isset($value[1]) AND is_string($value[1])))
				{
					$array[] = $this->_callback(array($value));
				}
				else
				{
					// Callback is in the preferred style
					$array[] = $this->_callback($value);
				}
			}
			// Callback in the style of 'field' => array('callback' => $args)
			else
			{
				$array[] = $this->_callback(array($key, $value));
			}
		}
		
		return $this;
	}
	
	/**
	 * Converts all callbacks to the following format:
	 * 
	 *     array(callback $callback, array $args);
	 *
	 * @param   mixed  $callback 
	 * @return  array
	 */
	protected function _callback($value)
	{
		// Split apart
		$callback = $value[0];
		$args     = isset($value[1]) ? $value[1] : array();
		
		if (is_string($callback))
		{
			// Check if this is a method to call in this class
			if (strpos($callback, '::') === FALSE AND is_callable(array('Jelly_Validate', $callback)))
			{
				$callback = array('Jelly_Validate', $callback);
			}
		}
		
		return array($callback, (array) $args);
	}
}
