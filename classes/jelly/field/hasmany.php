<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Handles has many relationships
 *
 * @package Jelly
 */
abstract class Jelly_Field_HasMany 
extends Jelly_Field_Relationship 
implements Jelly_Field_Behavior_Saveable, Jelly_Field_Behavior_Haveable, Jelly_Field_Behavior_Changeable
{	
	/**
	 * This is expected to contain an assoc. array containing the key 
	 * 'model', and the key 'column'
	 * 
	 * If they do not exist, they will be filled in with sensible defaults 
	 * derived from the field's name.
	 * 
	 * 'model' => 'a model to use as the foreign association'
	 * 
	 * If 'model' is empty it is set to the singularized name of the field.
	 * 
	 * 'column' => 'the column (or alias) that is the foreign model's primary key'
	 * 
	 * If 'column' is empty, it is set to the name of the model plus '_id'
	 *
	 * @var array
	 */
	public $foreign = array();
	
	/**
	 * Overrides the initialize to automatically provide the column name
	 *
	 * @param  string $model 
	 * @param  string $column 
	 * @return void
	 */
	public function initialize($model, $column)
	{
		if (empty($this->foreign['model']))
		{
			$this->foreign['model'] = inflector::singular($column);
		}
		
		if (empty($this->foreign['column']))
		{
			$this->foreign['column'] = $model.'_id';
		}
		
		// Column is set and won't be overridden
		parent::initialize($model, $column);
	}
	
	/**
	 * Converts a Database_Result, Jelly, array of ids, or an id to an array of ids
	 *
	 * @param  mixed $value 
	 * @return array
	 */
	public function set($value)
	{
		return $this->_ids($value);
	}
	
	/**
	 * Returns a Jelly model that, when load()ed will return a database 
	 * result of the models that this field has.
	 *
	 * @param  string $model 
	 * @param  string $value 
	 * @return Jelly
	 */
	public function get($model, $value)
	{
		// Return a real object
		return Jelly::select($this->foreign['model'])
				->where($this->foreign['column'], '=', $model->id());
	}
	
	/**
	 * Implementation of Jelly_Field_Behavior_Saveable
	 *
	 * @param   Jelly $model 
	 * @param   mixed $value
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
	 * @param  Jelly $model 
	 * @param  array $ids 
	 * @return void
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
	* @param string $prefix
	* @param string $data
	* @return void
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
