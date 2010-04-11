<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Handles belongs to relationships
 *
 * @package  Jelly
 */
abstract class Jelly_Field_BelongsTo extends Jelly_Field_Relationship implements Jelly_Field_Behavior_Joinable
{
	/**
	 * @var  boolean  Defaults belongs_to's to in the database
	 */
	public $in_db = TRUE;

	/**
	 * A string pointing to the foreign model and (optionally, a
	 * field, column, or meta-alias).
	 *
	 * Assuming an author belongs to a role named 'role':
	 *
	 *  * '' would default to role.:primary_key
	 *  * 'role' would expand to role.:primary_key
	 *  * 'role.id' would remain untouched.
	 *
	 * The model part of this must point to a valid model, but the
	 * field part can point to anything, as long as it's a valid
	 * column in the database.
	 *
	 * @var  string
	 */
	public $foreign = '';

	/**
	 * Automatically sets foreign to sensible defaults
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
			$this->foreign = $column.'.:primary_key';
		}
		// Is it model.field?
		elseif (FALSE === strpos($this->foreign, '.'))
		{
			$this->foreign = $this->foreign.'.:primary_key';
		}

		// Split them apart
		$foreign = explode('.', $this->foreign);

		// Create an array from them
		$this->foreign = array(
			'model' => $foreign[0],
			'column' => $foreign[1],
		);

		// Default to the foreign model's primary key
		if (empty($this->column))
		{
			$this->column = $this->foreign['model'].'_id';
		}

		// Column is set and won't be overridden
		parent::initialize($model, $column);
	}

	/**
	 * Returns the primary key of the model passed.
	 *
	 * @param   mixed  $value
	 * @return  mixed
	 */
	public function set($value)
	{
		if (is_object($value))
		{
			$value = $value->id();
		}

		// Convert NULL values to the default
		if ($value === NULL)
		{
			return $this->default;
		}

		return (is_numeric($value)) ? (int) $value : (string) $value;
	}

	/**
	 * Returns the jelly model that this model belongs to
	 *
	 * @param   Jelly_Model  $model
	 * @param   mixed        $value
	 * @return  Jelly_Builder
	 */
	public function get($model, $value)
	{
		return Jelly::select($this->foreign['model'])
				->where($this->foreign['column'], '=', $value)
				->limit(1);
	}

	/**
	 * Implementation of Jelly_Field_Behavior_Joinable
	 *
	 * @param   Jelly_Builder  $builder
	 * @return  void
	 */
	public function with($builder)
	{
		$join_col1 = $this->model.'.'.$this->column;
		// We use this field's alias rather than the foreign model so the join alias can be resolved
		$join_col2 = $this->name.'.'.$this->foreign['column'];

		$builder
			->join(array($this->foreign['model'], Jelly::join_alias($this)), 'LEFT')
			->on($join_col1, '=', $join_col2);
	}
}
