<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Jelly_Collection encapsulates a Database_Result object. It has the exact same API.
 *
 * It offers a few special features that make it useful:
 *
 *  * Only one model is instantiated for the whole result set, which
 *    is significantly faster in terms of performance.
 *  * It is easily extensible, so things like polymorphism and
 *    recursive result sets can be easily implemented.
 *
 * Jelly_Collection likes to know what model its result set is related to,
 * though it's not required. Some features may disappear, however, if
 * it doesn't know the model it's working with.
 *
 * @package  Jelly
 */
class Jelly_Collection_Core implements Iterator, Countable, SeekableIterator, ArrayAccess
{
	/**
	 * @var  Jelly  The current model we're placing results into
	 *
	 */
	protected $_model = NULL;

	/**
	 * @var  mixed  The current result set
	 */
	protected $_result = NULL;

	/**
	 * Tracks a database result
	 *
	 * @param  mixed  $model
	 * @param  mixed  $result
	 */
	public function __construct($model, $result)
	{
		if ($model)
		{
			// Convert to a model
			$model = Jelly::class_name($model);

			// Instantiate the model, which we'll continually
			// fill with values when iterating
			$this->_model = new $model;
		}

		$this->_result = $result;
	}

	/**
	 * Converts MySQL Results to Cached Results, since MySQL resources are not serializable.
	 *
	 * @return  array
	 */
	public function __sleep()
	{
		if ($this->_result instanceof Database_MySQL_Result)
		{
			$this->_result = new Database_Result_Cached($this->_result->as_array(), '');
		}

		return array_keys(get_object_vars($this));
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
		$model = Jelly::model_name($this->_model);

		foreach (array('key', 'value') as $var)
		{
			// Only alias meta-aliases
			if ($$var && FALSE !== strpos($$var, ':'))
			{
				$$var = Jelly::meta_alias($model, $$var, NULL);
			}
		}

		return $this->_result->as_array($key, $value);
	}

	/**
	 * Implementation of the Iterator interface
	 * @return  $this
	 */
	public function rewind()
	{
		$this->_result->rewind();
		return $this;
	}

	/**
	 * Implementation of the Iterator interface
	 * @return  Jelly
	 */
    public function current($object = TRUE)
	{
		// Database_Result causes errors if you call current()
		// on an object with no results, so we check first.
		if ($this->_result->count())
		{
			$result = $this->_result->current();
		}
		else
		{
			$result = array();
		}

		return $this->_load($result, $object);
	}

	/**
	 * Implementation of the Iterator interface
	 * @return  int
	 */
	public function key()
	{
		return $this->_result->key();
	}

	/**
	 * Implementation of the Iterator interface
	 * @return  $this
	 */
	public function next()
	{
		$this->_result->next();
		return $this;
	}

	/**
	 * Implementation of the Iterator interface
	 * @return  boolean
	 */
	public function valid()
	{
		return $this->_result->valid();;
	}

	/**
	 * Implementation of the Countable interface
	 * @return  boolean
	 */
	public function count()
	{
		return $this->_result->count();;
	}

	/**
	 * Implementation of SeekableIterator
	 *
	 * @param   mixed  $offset
	 * @return  boolean
	 */
	public function seek($offset)
	{
		return $this->_result->seek($offset);
	}

	/**
	 * ArrayAccess: offsetExists
	 */
	public function offsetExists($offset)
	{
		return $this->_result->offsetExists($offset);
	}

	/**
	 * ArrayAccess: offsetGet
	 */
	public function offsetGet($offset, $object = TRUE)
	{
		return $this->_load($this->_result->offsetGet($offset), $object);
	}

	/**
	 * ArrayAccess: offsetSet
	 *
	 * @throws  Kohana_Exception
	 */
	final public function offsetSet($offset, $value)
	{
		throw new Kohana_Exception('Jelly results are read-only');
	}

	/**
	 * ArrayAccess: offsetUnset
	 *
	 * @throws  Kohana_Exception
	 */
	final public function offsetUnset($offset)
	{
		throw new Kohana_Exception('Jelly results are read-only');
	}

	/**
	 * Loads values into the model.
	 *
	 * @param   array $values
	 * @return  Jelly_Model|array
	 */
	protected function _load($values, $object)
	{
		if ($this->_model AND $object)
		{
			$model = clone $this->_model;

			// Don't return models when we don't have one
			return ($values)
			        ? $model->load_values($values)
			        : $model->clear();
		}

		return $values;
	}
}
