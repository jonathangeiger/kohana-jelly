<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Jelly_Collection encapsulates a Database_Result object.
 *
 * It offers a few special features that make it useful,
 * specifically the ability to add and remove models from the
 * result set.
 * 
 * The interface of Jelly_Collection is usually encapsulated
 * by Jelly_Manager.
 *
 * Jelly_Collection likes to know what model its result set is related to,
 * though it's not required. Some features may disappear, however, if
 * it doesn't know the model it's working with.
 *
 * @package  Jelly
 */
abstract class Jelly_Core_Collection implements Iterator, Countable
{	
	/**
	 * @var  mixed  The current result set
	 */
	protected $_result = NULL;
	
	/**
	 * @var  mixed  The model we're working with
	 */
	protected $_model = FALSE;
	
	/**
	 * @var  mixed  The type of data we're to return
	 */
	protected $_as_object = FALSE;
	
	/**
	 * @var  Jelly_Model  The current row 
	 */
	protected $_current = NULL;
	
	/**
	 * @var  mixed  An index that is created while iterating
	 */
	protected $_index = array();
	
	/**
	 * @var  array  The original index of keys, before addition or removals
	 */
	protected $_original = array();
	
	/**
	 * Constructor.
	 *
	 * @param  mixed  $model
	 * @param  mixed  $as_object
	 * @param  mixed  $result
	 */
	public function __construct($model, $as_object = NULL, $result = array())
	{
		$this->_model     = $model;
		$this->_as_object = $as_object;
		$this->_result    = $result;
		$this->_meta      = Jelly::meta($model);
		
		if ( ! $this->_meta)
		{
			$this->_model = NULL;
			$this->_meta  = NULL;
		}
	}
	
	/**
	 * Converts MySQL Results to Cached Results, since MySQL resources are not serializable.
	 *
	 * @return  array
	 */
	public function __sleep()
	{
		if (is_object($this->_result) AND ! $this->_result instanceof Database_Result_Cached)
		{
			if ($this->_indexable())
			{
				$this->_index();
			}
			else
			{
				$this->_result = $this->cached();
			}
		}

		return array_keys(get_object_vars($this));
	}
	
	/**
	 * Returns a string representation of the collection.
	 *
	 * @return  string
	 */
	public function __tostring()
	{
		if ($this->_model)
		{
			return get_class($this).': '.$this->meta()->class.' ('.$this->ids().')';
		}
		else
		{
			return get_class($this).'('.$this->count().')';
		}
	}
	
	/**
	 * Adds a model or models to the result set.
	 *
	 * @param   mixed  $models 
	 * @return  $this
	 */
	public function add($models)
	{
		$this->_index();
		
		if ( ! $models instanceof Traversable AND ! is_array($models))
		{
			$models = array($models);
		}
		
		foreach ($models as $model)
		{
			if ($id = $this->_id($model))
			{
				$this->_result[$id] = $model;
			}
		}
		
		return $this;
	}
	
	/**
	 * Removes a model or models from the result set.
	 * 
	 * Currently, only loaded models can be removed.
	 *
	 * @param   mixed  $models 
	 * @return  $this
	 */
	public function remove($models)
	{
		$this->_index();
		
		if ( ! $models instanceof Traversable AND ! is_array($models))
		{
			$models = array($models);
		}
		
		foreach ($models as $model)
		{
			if ($id = $this->_id($model))
			{
				unset($this->_result[$id]);
			}
		}
		
		return $this;
	}
	
	/**
	 * Returns whether or not the set contains all of the models passed.
	 * 
	 * @param   mixed  $models 
	 */
	public function contains($models)
	{
		$this->_index();
		
		if ( ! $models instanceof Traversable AND ! is_array($models))
		{
			$models = array($models);
		}
		
		foreach ($models as $model)
		{
			if ($id = $this->_id($model))
			{
				if (empty($this->_result[$id]))
				{
					return FALSE;
				}
			}
		}
		
		return TRUE;
	}
	
	/**
	 * Implementation of the Countable interface
	 * @return  int
	 */
	public function count()
	{
		return $this->_indexed() ? count($this->_result) : $this->_result->count();
	}
	
	/**
	 * Implementation of the Iterator interface
	 * @return  $this
	 */
	public function rewind()
	{
		$this->_indexed() ? reset($this->_result) : $this->_result->rewind();
		return $this;
	}

	/**
	 * Implementation of the Iterator interface
	 * @return  Jelly
	 */
    public function current()
	{
		$current = NULL;
		
		if ($this->_indexed())
		{
			$current = current($this->_result);
		}
		else
		{
			$current = $this->_result->current();
		}
		
		$this->_current = $this->_load($current);
		
		if ($this->_indexable() AND ($id = $this->_id($this->_current)))
		{
			if ( ! $this->_indexed())
			{
				// We're still indexing, so we save it to the index
				// to avoid overwriting the result.
				$this->_index[$id] = $this->_current;
			}
			else
			{
				$this->_result[$id] = $this->_current;
			}
		}
		
		return $this->_current;
	}

	/**
	 * Implementation of the Iterator interface
	 * @return  int
	 */
	public function key()
	{
		return $this->_indexed() ? key($this->_result) : $this->_result->key();
	}

	/**
	 * Implementation of the Iterator interface
	 * @return  $this
	 */
	public function next()
	{
		$this->_indexed() ? next($this->_result) : $this->_result->next();
		return $this;
	}

	/**
	 * Implementation of the Iterator interface
	 * @return  boolean
	 */
	public function valid()
	{	
		if ($this->_indexed())
		{
			if (key($this->_result) !== NULL)
			{
				return TRUE;
			}
		}
		else if ($this->_result->valid())
		{
			return TRUE;
		}
		
		return FALSE;
	}
	
	/**
	 * Returns the meta object this is working with.
	 * 
	 * @return  Jelly_Meta
	 */
	public function meta()
	{	
		return $this->_meta;
	}

	/**
	 * Creates the index of results.
	 *
	 * @param   mixed  $data 
	 * @return  $this
	 */
	protected function _index()
	{
		if ( ! $this->_indexable())
		{
			throw new Kohana_Exception('Cannot index :model', array(':model' => $this->meta()->model));
		}
		
		if ( ! $this->_indexed())
		{
			$this->_result = $this->_original = $this->_result->as_array($this->meta()->primary_key);
		}
	}
	
	/**
	 * Checks if we've indexed the result by other means
	 * and swaps them around if possible.
	 * 
	 * @return void
	 */
	protected function _indexed()
	{
		if ($this->_result instanceof Traversable)
		{
			if (count($this->_index) === $this->_result->count())
			{
				// We've already indexed by iterating, just copy it on over
				$this->_result = $this->_original = $this->_index;
				
				// If we don't set the point to the end of the 
				// array here it will be set at the beginning
				// If we're iterating when this happens we'll go over
				// the results again!
				end($this->_result);
				return TRUE;
			}
			
			return FALSE;
		}
		
		return TRUE;
	}
	
	/**
	 * Checks if the result is indexable, based on whether or not
	 * the model exists.
	 * 
	 * @return boolean
	 */
	protected function _indexable()
	{
		return (bool) $this->_model;
	}
	
	/**
	 * Returns the id of the record passed
	 */
	protected function _id($model)
	{
		$meta = $this->meta();
		
		if (is_string($model) OR is_numeric($model))
		{
			// We want to lazy load models as they're retrieved
			// so we just hold on to (what we assume) to be a primary key
			return $model;
		}
		else if (is_object($model) AND $model instanceof Jelly_Model)
		{
			if ($model->loaded())
			{
				return $model->id();
			}
		}
		else if (is_array($model) AND Arr::is_assoc($model))
		{
			if (isset($model[$meta->primary_key]))
			{
				return $model[$meta->primary_key];
			}
		}
		else if (is_object($model) AND $model instanceof StdClass)
		{
			if (isset($model->{$meta->primary_key}))
			{
				return $model->{$meta->primary_key};
			}
		}
		
		return FALSE;
	}
	
	/**
	 * Loads values into the model.
	 *
	 * @param   mixed   The row we're to return
	 * @return  Jelly_Model|array
	 */
	protected function _load($row)
	{
		// If we don't know what we're dealing with 
		// model-wise we just return the result directly
		if ( ! $this->_model OR $this->_as_object !== NULL)
		{
			return $row;
		}
		
		// Assumed to be a known primary key
		if (is_string($row) OR is_numeric($row))
		{
			return Jelly::factory($this->_model, $row);
		}
		// Assumed to be an array/object of data from a known model
		else if (is_array($row) OR is_object($row))
		{
			return Jelly::factory($this->_model)->load_values($row);
		}

		return $model;
	}
}
