<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Jelly_Manager is a manager for queries and result sets.
 *
 * @package  Jelly
 */
abstract class Jelly_Core_Manager extends Jelly_Core_Query implements IteratorAggregate
{	
	/**
	 * @var  mixed  The current result set
	 */
	protected $_result = NULL;
	
	/**
	 * Executes the query as a SELECT statement.
	 * 
	 * Once a SELECT is executed it cannot be re-executed.
	 * 
	 * If the query is limited to 1, the result will be returned
	 * directly.
	 *
	 * @return $this|Jelly_Model
	 */
	public function select()
	{
		if ($this->_result === NULL)
		{
			$this->_result = parent::select();
		}
		
		if ($this->_limit === 1)
		{
			return $this->_result->current();
		}
		
		return $this;
	}
	
	/**
	 * Counts the current query builder.
	 * 
	 * If the query has already been selected this will 
	 * return the count of the result set.
	 *
	 * @return  int
	 */
	public function count()
	{
		if ($this->_result)
		{
			return $this->_result->count();
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
		$this->_result()->add($models);
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
		$this->_result()->remove($models);
		return $this;
	}
	
	/**
	 * Returns whether or not the set contains all of the models passed.
	 * 
	 * @param   mixed  $models 
	 */
	public function contains($models)
	{
		return $this->_result()->contains($models);
	}
	
	/**
	 * Implementation of IteratorAggregate. This allows builders to be selected
	 * without explicitly calling select.
	 * 
	 * @return  Jelly_Collection
	 */
	public function getIterator()
	{
		return $this->_result();
	}
	/**
	 * Returns the current result or executes a SELECT if we don't have one.
	 * 
	 * @return  $this
	 */
	protected function _result()
	{
		if ( ! $this->_result)
		{
			$this->select();
		}
		
		return $this->_result;
	}
}
