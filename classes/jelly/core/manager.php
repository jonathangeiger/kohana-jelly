<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Jelly_Manager acts as both a query builder and iterable result set.
 *
 * @package  Jelly
 */
abstract class Jelly_Core_Manager extends Jelly_Core_Query implements Iterator
{	
	/**
	 * @var  mixed  The current result set
	 */
	protected $_result = NULL;
	
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
	 * Converts MySQL Results to Cached Results, since MySQL resources are not serializable.
	 *
	 * @return  array
	 */
	public function __sleep()
	{
		if (is_object($this->_result) AND ! $this->_result instanceof Database_Result_Cached)
		{
			$this->_result = new Database_Result_Cached($this->_result->as_array(), '');
		}

		return array_keys(get_object_vars($this));
	}
	
	/**
	 * Executes the query as a SELECT statement.
	 * 
	 * If the query was explicitly limited to 1, the result 
	 * will be returned directly.
	 * 
	 * Once a SELECT is executed it cannot be re-executed.
	 *
	 * @return mixed
	 */
	public function select()
	{
		if ($this->_result === NULL)
		{
			$this->_result = parent::select();
		}
		
		if ($this->_limit === 1)
		{
			return $this->current();
		}
		
		return $this;
	}
	
	/**
	 * Counts the current query builder.
	 * 
	 * If the query has already been selected this will 
	 * return the count of the result set.
	 *
	 * @return  int  The number of rows that match the query
	 */
	public function count()
	{
		if ($this->_result)
		{
			return $this->_indexed() ? count($this->_result) : $this->_result->count();
		}
		
		return parent::count();
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
		if ($this->_result === NULL)
		{
			$this->select();
		}
		
		if ($this->_result instanceof Traversable)
		{
			if (count($this->_index) === $this->_result->count())
			{
				// We've already indexed by iterating, just copy it on over
				$this->_result = $this->_original = $this->_index;
				return TRUE;
			}
			
			return FALSE;
		}
		
		return TRUE;
	}
	
	/**
	 * Checks if the result is indexable, based on whether or not
	 * the model exists and has a primary key.
	 * 
	 * @return boolean
	 */
	protected function _indexable()
	{
		$meta = $this->meta();
		
		return ($meta->model AND $meta->primary_key);
	}
	
	/**
	 * Returns the id of the record passed
	 */
	protected function _id($model)
	{
		$meta = $this->meta();
		
		if (is_string($model) OR is_numeric($model))
		{
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
	 * @param   array $values
	 * @return  Jelly_Model|array
	 */
	protected function _load($model)
	{
		// We're getting back an array from the 
		// result but returning models if this is NULL
		if ($this->_as_object !== NULL)
		{
			return $model;
		}
		
		// Assumed to be a known primary key
		if (is_string($model) OR is_numeric($model))
		{
			return Jelly::factory($this->_model, $model);
		}
		// Assumed to be an array of a data from a known model
		else if (is_array($model))
		{
			return Jelly::factory($this->_model)->load_values((array)$model);
		}

		return $model;
	}
}
