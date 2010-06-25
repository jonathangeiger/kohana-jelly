<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Handles many to many relationships
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
	 * @var  array  The default to set on foreign fields when removing the relationship
	 */
	public $foreign_default = 0;
	
	/**
	 * This is expected to contain an assoc. array containing the key
	 * 'model', and the key 'columns'
	 *
	 * If they do not exist, they will be filled in with sensible defaults
	 * derived from the field's name and from the values in 'foreign'
	 *
	 * 'model' => 'a model or table to use as the join table'
	 *
	 * If 'model' is empty it is set to the pluralized names of the
	 * two model's names combined alphabetically with an underscore.
	 *
	 * 'columns' => array('column for this model', 'column for foreign model')
	 *
	 * 'columns' must be set in the order they appear above.
	 *
	 * @var  array
	 */
	public $through = NULL;

	/**
	 * This is expected to contain an assoc. array containing the key
	 * 'model', and the key 'column'
	 *
	 * If they do not exist, they will be filled in with sensible defaults
	 * derived from the field's name .
	 *
	 * 'model' => 'a model to use as the foreign association'
	 *
	 * If 'model' is empty it is set to the singularized name of the field.
	 *
	 * 'column' => 'the column (or alias) that is the foreign model's primary key'
	 *
	 * If 'column' is empty, it is set to 'id'
	 *
	 * @var  array
	 */
	public $foreign = NULL;

	/**
	 * Overrides the initialize to automatically provide the column name
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
		elseif (FALSE === strpos($this->foreign, '.'))
		{
			$this->foreign = $this->foreign.'.'.$this->foreign.':primary_key';
		}

		// Split them apart
		$foreign = explode('.', $this->foreign);

		// Create an array from them
		$this->foreign = array(
			'model' => $foreign[0],
			'column' => $foreign[1],
		);

		// We can work with nothing passed or just a model
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

				// Sort
				sort($through);

				// Bring them back together
				$this->through = implode('_', $through);
			}

			$this->through = array(
				'model' => $this->through,
				'columns' => array(
					inflector::singular($model).':foreign_key',
					inflector::singular($this->foreign['model']).':foreign_key',
				)
			);
		}

		parent::initialize($model, $column);
	}

	/**
	 * Returns an array of ids.
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
		            ->where($this->foreign['column'], 'IN', $value);
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
			     ->where($this->through['columns'][0], '=', $model->id())
			     ->where($this->through['columns'][1], 'IN', $old)
			     ->delete($model->meta()->db());
		}

		// Find new relationships that must be inserted
		if ($new = array_diff((array)$value, $in))
		{
			foreach ($new as $new_id)
			{
				Jelly::query($this->through['model'])
					 ->columns($this->through['columns'])
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
		$in  = $this->_in($model, TRUE);
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
		               ->select_column($this->through['columns'][1])
		               ->where($this->through['columns'][0], '=', $key)
		               ->type(Database::SELECT);

		if ($as_array)
		{
			$result = $result->select($model->meta()->db())
			                 ->as_array(NULL, $this->through['columns'][1]);
		}

		return $result;
	}
}
