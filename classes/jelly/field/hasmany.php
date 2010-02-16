<?php defined('SYSPATH') or die('No direct script access.');

class Jelly_Field_HasMany 
extends Jelly_Field_Relationship 
implements Jelly_Field_Interface_Saveable, Jelly_Field_Interface_Haveable, Jelly_Field_Interface_Changeable
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
	 * @author Jonathan Geiger
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
	 * @author Jonathan Geiger
	 */
	public function set($value)
	{
		// Can be set in only one go
		$return = array();
		
		// Handle Database Results
		if ($value instanceof Iterator || is_array($value))
		{
			foreach($value as $row)
			{
				if (is_object($row))
				{
					$return[] = $row->id();
				}
				else
				{
					$return[] = $row;
				}
			}
		}
		// And individual models
		else if (is_object($value))
		{
			$return = array($value->id());
		}
		// And everything else
		else
		{
			$return = (array)$value;
		}
		
		return $return;
	}
	
	/**
	 * Returns a Jelly model that, when load()ed will return a database 
	 * result of the models that this field has.
	 *
	 * @param  string $model 
	 * @param  string $value 
	 * @return Jelly
	 * @author Jonathan Geiger
	 */
	public function get($model, $value)
	{
		// Return a real object
		return Jelly::factory($this->foreign['model'])
				->where($this->foreign['column'], '=', $model->id());
	}
	
	/**
	 * Implementation of Jelly_Field_Interface_Saveable
	 *
	 * @param   Jelly $model 
	 * @param   mixed $value
	 * @return  void
	 * @author  Jonathan Geiger
	 */
	public function save($model, $value)
	{
		$foreign = Jelly::Factory($this->foreign['model']);
		
		// Empty relations to the default value
		$foreign
			->where($this->foreign['column'], '=', $model->id())
			->execute(Database::UPDATE, array(
				$this->foreign['column'] => $this->default
			));
						
		// Set the new relations
		if (!empty($value) && is_array($value))
		{			
			// Update the ones in our list
			$foreign
				->end()
				->where(Jelly_Meta::get($foreign, 'primary_key'), 'IN', $value)
				->execute(Database::UPDATE, array(
					$this->foreign['column'] => $model->id()
				));
		}
		
		return $value;
	}
	
	/**
	 * Implementation of Jelly_Field_Interface_Haveable
	 *
	 * @param  Jelly $model 
	 * @param  array $ids 
	 * @return void
	 * @author Jonathan Geiger
	 */
	public function has($model, $ids)
	{
		$foreign = Jelly::factory($this->foreign['model']);
		return (bool) $foreign
			->select(array('COUNT("*")', 'records_found'))
			->where($this->foreign['column'], '=', $model->id())
			->where($foreign->meta()->primary_key, 'IN', $ids)
			->execute()
			->get('records_found');
	}
	
	/**
	* Provides the input with the ids variable. An array of
	* all the ID's in the foreign model that this record owns.
	*
	* @param string $prefix
	* @param string $data
	* @return void
	* @author Jonathan Geiger
	*/
	public function input($prefix = 'jelly/field', $data = array())
	{
		// Kind of a wart here, but since HasOne extends this, we don't always want to iterate
		if ($data['value'] instanceof Database_Result)
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
