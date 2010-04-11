<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Handles many to many relationships
 *
 * @package  Jelly
 */
abstract class Jelly_Field_ManyToMany
extends Jelly_Field_Relationship
implements Jelly_Field_Behavior_Saveable, Jelly_Field_Behavior_Haveable, Jelly_Field_Behavior_Changeable
{
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
	 * Converts a Database_Result, Jelly, array of ids, or id to an array of ids
	 *
	 * @param   mixed  $value
	 * @return  array
	 */
	public function set($value)
	{
		return $this->_ids($value);
	}

	/**
	 * Returns a pre-built Jelly model ready to be loaded
	 *
	 * @param   Jelly_Model  $model
	 * @param   mixed        $value
	 * @param   boolean      $loaded
	 * @return  void
	 */
	public function get($model, $value)
	{
		// If the value hasn't changed, we need to pull from the database
		if ($model->changed($this->name))
		{
			return Jelly::select($this->foreign['model'])
					->where($this->foreign['column'], 'IN', $value);
		}

		$join_col1 = $this->through['model'].'.'.$this->through['columns'][1];
		$join_col2 = $this->foreign['model'].'.'.$this->foreign['column'];
		$where_col = $this->through['model'].'.'.$this->through['columns'][0];

		return Jelly::select($this->foreign['model'])
					->join($this->through['model'])
					->on($join_col1, '=', $join_col2)
					->where($where_col, '=', $model->id());
	}

	/**
	 * Implementation for Jelly_Field_Behavior_Saveable.
	 *
	 * @param   Jelly  $model
	 * @param   mixed  $value
	 * @return  void
	 */
	public function save($model, $value, $loaded)
	{
		// Find all current records so that we can calculate what's changed
		$in = ($loaded) ? $this->_in($model, TRUE) : array();

		// Find old relationships that must be deleted
		if ($old = array_diff($in, (array)$value))
		{
			Jelly::delete($this->through['model'])
				->where($this->through['columns'][0], '=', $model->id())
				->where($this->through['columns'][1], 'IN', $old)
				->execute(Jelly::meta($model)->db());
		}

		// Find new relationships that must be inserted
		if ($new = array_diff((array)$value, $in))
		{
			foreach ($new as $new_id)
			{
				Jelly::insert($this->through['model'])
					 ->columns($this->through['columns'])
					 ->values(array($model->id(), $new_id))
					 ->execute(Jelly::meta($model)->db());
			}
		}
	}

	/**
	 * Implementation of Jelly_Field_Behavior_Haveable
	 *
	 * @param   Jelly  $model
	 * @param   array  $ids
	 * @return  boolean
	 */
	public function has($model, $ids)
	{
		$in = $this->_in($model, TRUE);

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
	 * Adds the "ids" variable to the view data
	 *
	 * @param   string  $prefix
	 * @param   array   $data
	 * @return  View
	 */
	public function input($prefix = 'jelly/field', $data = array())
	{
		$data['ids'] = array();

		foreach ($data['value'] as $model)
		{
			$data['ids'][] = $model->id();
		}

		return parent::input($prefix, $data);
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
		$result = Jelly::select($this->through['model'])
				->select($this->through['columns'][1])
				->where($this->through['columns'][0], '=', $model->id());

		if ($as_array)
		{
			$result = $result
						->execute(Jelly::meta($model)->db())
						->as_array(NULL, $this->through['columns'][1]);
		}

		return $result;
	}
}
