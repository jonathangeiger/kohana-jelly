<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Jelly_Validator overrides Kohana's core validation class in order to add a few 
 * Jelly-specific features.
 * 
 * @see     Jelly_Model::validate
 * @package Jelly
 */
abstract class Jelly_Core_Validator extends Validation
{
	/**
	 * @var  array  Validators added to the array
	 */
	protected $_validate = array(
		// Filters which modify a value
		'filter'   => array(),
		// Rules which verify a value
		'rule'     => array(),
		// Custom callbacks
		'callback' => array(),
	);
	
	/**
	 * @var  array  Current context
	 */
	protected $_context = array();
	
	/**
	 * @var  array  Fields that are required and will have all rules processed
	 */
	protected $_required = array();
	
	/**
	 * Add a filter to a field. Each filter will be executed once.
	 *
	 * @param   string  field name
	 * @param   mixed   valid PHP callback
	 * @param   array   extra parameters for the callback
	 * @return  $this
	 */
	public function filter($field, $callback, array $params = NULL)
	{
		return $this->_add('filter', $field, array(array($callback, $params)));
	}

	/**
	 * Adds multiple filters to a field.
	 *
	 * @param   string  field name
	 * @param   array   array of filters
	 * @return  $this
	 */
	public function filters($field, array $filters)
	{
		return $this->_add('filter', $field, $filters);
	}
	
	/**
	 * Add a rule to a field. Each rule will be executed once.
	 *
	 * @param   string  field name
	 * @param   mixed   valid PHP callback
	 * @param   array   extra parameters for the callback
	 * @return  $this
	 */
	public function rule($field, $callback, array $params = NULL)
	{
		return $this->_add('rule', $field, array(array($callback, $params)));
	}

	/**
	 * Adds multiple rules to a field.
	 *
	 * @param   string  field name
	 * @param   array   array of rules
	 * @return  $this
	 */
	public function rules($field, array $rules)
	{
		return $this->_add('rule', $field, $rules);
	}
	
	/**
	 * Add a callback to a field. Each callback will be executed once.
	 *
	 * @param   string  field name
	 * @param   mixed   valid PHP callback
	 * @param   array   extra parameters for the callback
	 * @return  $this
	 */
	public function callback($field, $callback, array $params = NULL)
	{
		return $this->_add('callback', $field, array(array($callback, $params)));
	}

	/**
	 * Adds multiple callbacks to a field.
	 *
	 * @param   string  field name
	 * @param   array   array of callbacks
	 * @return  $this
	 */
	public function callbacks($field, array $callbacks)
	{
		return $this->_add('callback', $field, $callbacks);
	}
	
	/**
	 * Add a context to the validation object. 
	 * 
	 * The context key should not have a ':' on the front of it.
	 * 
	 * Omit passing a value to return the value for the context.
	 *
	 * @param   string  context name
	 * @param   mixed   context value
	 * @return  $this
	 */
	public function context($key, $value = NULL)
	{
		// Return a value for the context, since nothing was passed for $value
		if (func_num_args() === 1)
		{
			return isset($this->_context[$key]) ? $this->_context[$key] : NULL;
		}
		
		return $this->contexts(array($key => $value));
	}

	/**
	 * Adds multiple contexts to the validation object. 
	 * 
	 * The context keys should not have a ':' on the front.
	 *
	 * @param   string  field name
	 * @param   array   array of callbacks
	 * @return  $this
	 */
	public function contexts(array $array)
	{
		foreach ($array as $key => $value)
		{
			$this->_context[$key] = $value;
		}
		
		return $this;
	}

	/**
	 * Executes all validation filters, rules, and callbacks. This should
	 * typically be called within an if/else block.
	 *
	 *     if ($validation->check())
	 *     {
	 *          // The data is valid, do something here
	 *     }
	 *
     * @param   boolean  $allow_empty
	 * @return  boolean
	 */
	public function check($allow_empty = FALSE)
	{
		if (Kohana::$profiling === TRUE)
		{
			// Start a new benchmark
			$benchmark = Profiler::start('Validation', __FUNCTION__);
		}

		// New data set
		$data = $this->_errors = array();

		// Assume nothing has been submitted
		$submitted = FALSE;

		// Only validate passed data
		$expected = array_keys($this->getArrayCopy());
		
		// Import the validators locally
		$validate = $this->_validate;

		foreach ($expected as $field)
		{
			if (isset($this[$field]))
			{
				// Some data has been submitted, continue validation
				$submitted = TRUE;

				// Use the submitted value
				$data[$field] = $this[$field];
			}
			else
			{
				// No data exists for this field
				$data[$field] = NULL;
			}
		}

		// Overload the current array with the new one
		$this->exchangeArray($data);

		if ($submitted === FALSE)
		{
			// Because no data was submitted, validation will not be forced
			return (boolean) $allow_empty;
		}
		
		// Execute all callbacks
		foreach ($validate as $type => $fields)
		{
			foreach ($fields as $field => $set)
			{	
				// Skip TRUE callbacks and errored out fields
				if ( ! in_array($field, $expected) OR $field === 1 OR isset($this->_errors[$field])) 
					continue;
					
				// Field is empty and not required; skip rules
				if ($type === 'rule')
				{
					// Is the field required?
					if ( ! isset($this->_required[TRUE]) AND ! isset($this->_required[$field]))
					{
						// It's not required, so if it's empty we skip all rules
						if ( ! Valid::not_empty($this[$field]))
						{
							continue;
						}
					}
				}
				
				// Add the TRUE callbacks to the array
				if (isset($validate[$type][TRUE]))
				{
					$set = array_merge($validate[$type][TRUE], $set);
				}
				
				// Process each callback
				foreach ($set as $callback)
				{
					// Set the current context
					$this->_current_context(array(
						'field'    => $field,
						'callback' => $callback,
						'value'    => $this[$field],
						'validate' => $this,
					));
					
					// Call
					$callback->call($this);
					
					// Any new errors? Then we're done for this field
					if (isset($this->_errors[$field]))
					{
						break;
					}
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
	 * Returns the error messages. If no file is specified, the error message
	 * will be the name of the rule that failed. When a file is specified, the
	 * message will be loaded from "field/rule", or if no rule-specific message
	 * exists, "field/default" will be used. If neither is set, the returned
	 * message will be "file/field/rule".
	 *
	 * By default all messages are translated using the default language.
	 * A string can be used as the second parameter to specified the language
	 * that the message was written in.
	 *
	 *     // Get errors from messages/forms/login.php
	 *     $errors = $validate->errors('forms/login');
	 *
	 * @uses    Kohana::message
	 * @param   string  file to load error messages from
	 * @param   mixed   translate the message
	 * @return  array
	 */
	public function errors($file = NULL, $translate = TRUE)
	{
		if ($file === NULL)
		{
			// Return the error list
			return $this->_errors;
		}

		// Create a new message list
		$messages = array();

		foreach ($this->_errors as $field => $set)
		{
			list($error, $params) = $set;

			// Get the label for this field
			$label = $this->_labels[$field];

			if ($translate)
			{
				// Translate the label
				$label = __($label);
			}

			// Add the field name to the params, everything else should be set in place by the callback
			$values = array(':field' => $label) + (array) $params;

			if ($message = Kohana::message($file, "{$field}.{$error}"))
			{
				// Found a message for this field and error
			}
			elseif ($message = Kohana::message($file, "{$field}.default"))
			{
				// Found a default message for this field
			}
			elseif ($message = Kohana::message($file, $error))
			{
				// Found a default message for this error
			}
			elseif ($message = Kohana::message('validate', $error))
			{
				// Found a default message for this error
			}
			else
			{
				// No message exists, display the path expected
				$message = "{$file}.{$field}.{$error}";
			}

			if ($translate == TRUE)
			{
				if (is_string($translate))
				{
					// Translate the message using specified language
					$message = __($message, $values, $translate);
				}
				else
				{
					// Translate the message using the default language
					$message = __($message, $values);
				}
			}
			else
			{
				// Do not translate, just replace the values
				$message = strtr($message, $values);
			}

			// Set the message for this field
			$messages[$field] = $message;
		}

		return $messages;
	}
	
	/**
	 * Generic method for adding a new validator.
	 *
	 * @param   string  the validator type
	 * @param   string  field name
	 * @param   array   an array of callbacks and params
	 * @return  $this
	 */
	protected function _add($type, $field, $callbacks)
	{
		// Ensure the validator type exists
		if ( ! isset($this->_validate[$type]))
		{
			$this->_validate[$type] = array();
		}
		
		// Ensure the validator field exists
		if ( ! isset($this->_validate[$type][$field]))
		{
			$this->_validate[$type][$field] = array();
		}
		
		// Set the field label to the field name if it doesn't exist
		if ($field !== TRUE AND ! isset($this->_labels[$field]))
		{
			$this->_labels[$field] = inflector::humanize($field);
		}
		
		// The class we'll be converting all callbacks to
		$class = 'Jelly_Validator_'.$type;
		
		// Loop through each, adding them all
		foreach ($callbacks as $key => $set)
		{	
			// Allow old style callbacks 'callback' => $params
			if (is_string($key))
			{
				$set = array($key, $set ? $set : NULL);
			}
			
			$callback = $set[0];
			$params   = isset($set[1]) ? $set[1] : NULL;
			
			// Are we supposed to convert this to a callback of this class?
			if (is_string($callback) AND is_callable(array(get_class($this), $callback)))
			{
				// Is the method one that marks the field as required?
				if (in_array($callback, $this->_empty_rules))
				{
					$this->_required[$field] = TRUE;
				}
				
				// Test to see if the method is static or not
				$method = new ReflectionMethod(get_class($this), $callback);
				
				if ($method->isStatic())
				{
					$callback = array(get_class($this), $callback);
				}
				else
				{
					$callback = array(':validate', $callback);
				}
			}
			
			// Create an object out of the callback if it isn't already one
			if ( ! $callback instanceof $class)
			{
				$callback = new $class($callback, $params);
			}

			// Append to the list
			$this->_validate[$type][$field][] = $callback;
		}
		
		return $this;
	}
	
	/**
	 * Sets the default context using the values provided.
	 * 
	 * This is a simple method to override to provide default 
	 * contexts. It is re-called every time a new callback is called
	 * on each field when check()ing.
	 *
	 * @param   array  $array 
	 * @return  NULL
	 */
	protected function _current_context($array)
	{
		$this->contexts($array);
	}
	
}
