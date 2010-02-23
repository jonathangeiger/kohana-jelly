<?php defined('SYSPATH') or die('No direct script access.');

class Jelly_Core_Result implements Iterator
{
	/**
	 * @var Jelly The current model we're placing results into
	 * 
	 */
	protected $_model = NULL;
	
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
		// Convert to a model
		$model = Jelly_Meta::class_name($model);
		
		// Instantiate the model, which we'll continually
		// fill with values when iterating
		$this->_model = new $model;
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
    public function current() 
	{
        return $this->_model->clear()->set($this->_result->current(), TRUE, TRUE);
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
}
