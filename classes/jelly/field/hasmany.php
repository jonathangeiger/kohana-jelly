<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Handles has many relationships
 *
 * @package  Jelly
 */
abstract class Jelly_Field_HasMany
extends Jelly_Field_Relationship
implements Jelly_Field_Behavior_Saveable, Jelly_Field_Behavior_Haveable, Jelly_Field_Behavior_Changeable
{
	/**
	 * A string pointing to the foreign model and (optionally, a
	 * field, column, or meta-alias).
	 *
	 * Assuming an author has_many posts and the field was named 'posts':
	 *
	 *  * '' would default to post.:author:foreign_key
	 *  * 'post' would expand to post.:author:foreign_key
	 *  * 'post.author_id' would remain untouched.
	 *
	 * The model part of this must point to a valid model, but the
	 * field part can point to anything, as long as it's a valid
	 * column in the database.
	 *
	 * @var  string
	 */
	public $foreign = '';

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
			$this->foreign = inflector::singular($column).'.'.$model.':foreign_key';
		}
		// Is it model.field?
		elseif (FALSE === strpos($this->foreign, '.'))
		{
			$this->foreign = $this->foreign.'.'.$model.':foreign_key';
		}

		// Split them apart
		$foreign = explode('.', $this->foreign);

		// Create an array from them
		$this->foreign = array(
			'model' => $foreign[0],
			'column' => $foreign[1],
		);

		parent::initialize($model, $column);
	}

	/**
	 * Converts a Database_Result, Jelly, array of ids, or an id to an array of ids
	 *
	 * @param   mixed  $value
	 * @return  array
	 */
	public function set($value)
	{
		return $this->_ids($value);
	}

	/**
	 * Returns a Jelly model that, when load()ed will return a database
	 * result of the models that this field has.
	 *
	 * @param   Jelly_Model  $model
	 * @param   mixed        $value
	 * @param   boolean      $loaded
	 * @return  Jelly
	 */
	public function get($model, $value)
	{
		if ($model->changed($this->name))
		{
			// Return a real object
			return Jelly::select($this->foreign['model'])
					->where(':primary_key', 'IN', $value);
		}
		else
		{
			return Jelly::select($this->foreign['model'])
					->where($this->foreign['column'], '=', $model->id());
		}
	}

	/**
	 * Implementation of Jelly_Field_Behavior_Saveable
	 *
	 * @param   Jelly  $model
	 * @param   mixed  $value
	 * @return  void
	 */
	public function save($model, $value, $loaded)
	{
		// Empty relations to the default value
		Jelly::update($this->foreign['model'])
			->where($this->foreign['column'], '=', $model->id())
			->set(array($this->foreign['column'] => $this->default))
			->execute();

		// Set the new relations
		if ( ! empty($value) AND is_array($value))
		{
			// Update the ones in our list
			Jelly::update($this->foreign['model'])
				->where(':primary_key', 'IN', $value)
				->set(array($this->foreign['column'] => $model->id()))
				->execute();
		}
	}

	/**
	 * Implementation of Jelly_Field_Behavior_Haveable
	 *
	 * @param   Jelly  $model
	 * @param   array  $ids
	 * @return  void
	 */
	public function has($model, $ids)
	{
		return (bool) Jelly::select($this->foreign['model'])
			->where($this->foreign['column'], '=', $model->id())
			->where(':primary_key', 'IN', $ids)
			->count();
	}

	/**
	 * Provides the input with the ids variable. An array of
	 * all the ID's in the foreign model that this record owns.
	 *
	 * @param   string  $prefix
	 * @param   string  $data
	 * @return  void
	 */
	public function input($prefix = 'jelly/field', $data = array())
	{
		// Kind of a wart here, but since HasOne extends this, we don't always want to iterate
		if ($data['value'] instanceof Iterator)
		{
			$data['ids'] = array();

			// Grab the IDS
			foreach ($data['value'] as $model)
			{
				$data['ids'][] = $model->id();
			}
		}

		return parent::input($prefix, $data);
	}
}
