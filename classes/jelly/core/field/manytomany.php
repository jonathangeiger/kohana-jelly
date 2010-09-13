<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Handles many to many relationships.
 * 
 * With many-to-many relationships there is a "through" table that
 * connects the two models. 
 *
 * @package  Jelly
 */
abstract class Jelly_Core_Field_ManyToMany extends Jelly_Field implements Jelly_Field_Supports_AddRemove, Jelly_Field_Supports_Has
{
	/**
	 * @var  boolean  False, since this field does not map directly to a column
	 */
	public $in_db = FALSE;
	
	/**
	 * @var  boolean  Null values are not allowed since an empty array expresses no relationships
	 */
	public $allow_null = FALSE;
	
	/**
	 * @var  array  Default is an empty array
	 */
	public $default = array();
	
	/**
	 * @var  string  A string containing the name of the model and (optionally) 
	 *               the field of the model we're connecting.
	 */
	public $foreign = '';
	
	/**
	 * @var mixed  A string or array that references the through table and 
	 *             fields we're using to connect the two models.
	 */
	public $through = '';
	
	/**
	 * @var  boolean  Empty values are converted by default
	 */
	public $convert_empty = TRUE;
	
	/**
	 * @var  int  Empty values are converted to array(), not NULL
	 */
	public $empty_value = array();
	
	/**
	 * Sets up foreign and through properly.
	 *
	 * @param   string  $model
	 * @param   string  $column
	 * @return  void
	 */
	public function initialize($model, $column)
	{
		// Default to the name of the column
		if (empty($this->foreign))
		{
			$foreign_model = inflector::singular($column);
			$this->foreign = $foreign_model.'.'.$foreign_model.':primary_key';
		}
		// Is it model.field?
		elseif (is_string($this->foreign) AND FALSE === strpos($this->foreign, '.'))
		{
			$this->foreign = $this->foreign.'.'.$this->foreign.':primary_key';
		}

		// Create an array from them for easier access
		if ( ! is_array($this->foreign))
		{
			$this->foreign = array_combine(array('model', 'field'), explode('.', $this->foreign));
		}	

		// Create the default through connection
		if (empty($this->through) OR is_string($this->through))
		{
			if (empty($this->through))
			{
				// Find the join table based on the two model names pluralized,
				// sorted alphabetically and with an underscore separating them
				$through = array(
					inflector::plural($this->foreign['model']),
					inflector::plural($model)
				);

				sort($through);
				$this->through = implode('_', $through);
			}

			$this->through = array(
				'model' => $this->through,
				'fields' => array(
					inflector::singular($model).':foreign_key',
					inflector::singular($this->foreign['model']).':foreign_key',
				)
			);
		}

		parent::initialize($model, $column);
	}

	/**
	 * Sets a value on the field.
	 *
	 * @param   mixed  $value
	 * @return  array
	 */
	public function set($value)
	{
		list($value, $return) = $this->_default($value);
		
		if ( ! $return)
		{
			$value = $this->_ids($value);
		}
		
		return $value;
	}

	/**
	 * Returns a Jelly_Builder that can be selected, updated, or deleted.
	 *
	 * @param   Jelly_Model  $model
	 * @param   mixed        $value
	 * @return  Jelly_Builder
	 */
	public function get($model, $value)
	{
		// If the value hasn't changed, we need to pull from the database
		if ( ! $model->changed($this->name))
		{
			$value = $this->_in($model);
		}

		return Jelly::query($this->foreign['model'])
		            ->where($this->foreign['field'], 'IN', $value);
	}

	/**
	 * Implementation for Jelly_Field_Supports_Save.
	 *
	 * @param   Jelly_Model  $model
	 * @param   mixed        $value
	 * @param   boolean      $key
	 * @return  void
	 */
	public function save($model, $value, $loaded)
	{
		// Don't do anything on insert when we don't have anything
		if ( ! $loaded AND empty($value)) return;
		
		// Find all current records so that we can calculate what's changed
		$in = ($loaded) ? $this->_in($model, TRUE) : array();

		// Find old relationships that must be deleted
		if ($old = array_diff($in, (array)$value))
		{
			Jelly::query($this->through['model'])
			     ->where($this->through['fields'][0], '=', $model->id())
			     ->where($this->through['fields'][1], 'IN', $old)
			     ->delete($model->meta()->db());
		}

		// Find new relationships that must be inserted
		if ($new = array_diff((array)$value, $in))
		{
			foreach ($new as $new_id)
			{
				Jelly::query($this->through['model'])
					 ->columns($this->through['fields'])
					 ->values(array($model->id(), $new_id))
					 ->insert($model->meta()->db());
			}
		}
	}

	/**
	 * Implementation of Jelly_Field_Supports_Has.
	 *
	 * @param   Jelly_Model  $model
	 * @param   mixed        $models
	 * @return  boolean
	 */
	public function has($model, $models)
	{
		// If the value hasn't changed, we need to pull from the database
		if ( ! $model->changed($this->name))
		{
			$in = $this->_in($model, TRUE);
		}
		else
		{
			$in = $this->_ids($model->__get($this->name));
		}
		
		$ids = $this->_ids($models);

		foreach ($ids as $id)
		{
			if ( ! in_array($id, $in))
			{
				return FALSE;
			}
		}

		return TRUE;
	}
	
	/**
	 * Returns either an array or unexecuted query to find
	 * which columns the model is "in" in the join table
	 *
	 * @param   Jelly    $model
	 * @param   boolean  $as_array
	 * @return  mixed
	 */
	protected function _in($model, $as_array = FALSE)
	{
		$result = Jelly::query($this->through['model'])
		               ->select_column($this->through['fields'][1], 'in')
		               ->where($this->through['fields'][0], '=', $model->id())
		               ->type(Database::SELECT);

		if ($as_array)
		{
			$result = $result->select($model->meta()->db())
			                 ->as_array(NULL, 'in');
		}

		return $result;
	}
}
