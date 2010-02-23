<?php defined('SYSPATH') or die('No direct script access.');

class Jelly_Core_Result implements Iterator, Countable
{
	/**
	 * @var Jelly The current model we're placing results into
	 * 
	 */
	protected $_model = NULL;
	
	/**
	 * @var Jelly_Meta The meta object for the current model
	 */
	protected $_meta = NULL;
	
	/**
	 * @var mixed The current result set
	 */
	protected $_result = NULL;
	
	/**
	 * Tracks a database result
	 *
	 * @param  mixed   $model 
	 * @param  mixed  $result 
	 */
	public function __construct($model, $result)
	{
		if ($model)
		{
			// Convert to a model
			$model = Jelly_Meta::class_name($model);

			// Instantiate the model, which we'll continually
			// fill with values when iterating
			$this->_model = new $model;
			$this->_meta = Jelly_Meta::get($model);
		}
		
		$this->_result = $result;
	}
	
	/**
	 * Implementation of the Iterator interface
	 * @return $this
	 */
	public function rewind() 
	{
		$this->_result->rewind();
		return $this;
    }

	/**
	 * Implementation of the Iterator interface
	 * @return Jelly
	 */
    public function current($object = TRUE) 
	{
		if ($object)
		{
	        return $this->_model->clear()->set($this->_result->current(), TRUE, TRUE);
		}
		else
		{
			return $this->_result->current();
		}
    }

	/**
	 * Implementation of the Iterator interface
	 * @return int
	 */
    public function key() 
	{
        return $this->_result->key();
    }

	/**
	 * Implementation of the Iterator interface
	 * @return $this
	 */
    public function next() 
	{
        $this->_result->next();
		return $this;
    }

	/**
	 * Implementation of the Iterator interface
	 * @return boolean
	 */
    public function valid() 
	{
		return $this->_result->valid();;
    }

	/**
	 * Implementation of the Countable interface
	 * @return boolean
	 */
    public function count() 
	{
		return $this->_result->count();;
    }

	/**
	 * Return all of the rows in the result as an array.
	 *
	 * @param   string  column for associative keys
	 * @param   string  column for values
	 * @return  array
	 */
	public function as_array($key = NULL, $value = NULL)
	{
		if ($this->_meta)
		{
			foreach (array('key', 'value') as $var)
			{
				if ($field = $this->_meta->fields($$var))
				{
					$$var = $field->column;
				}
			}
		}
		
		return $this->_result->as_array($key, $value);
	}
}
