<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Handles belongs to relationships.
 *
 * @package  Jelly
 */
abstract class Jelly_Core_Field_BelongsTo extends Jelly_Field_Relation implements Jelly_Field_Supports_With
{
	/**
	 * @var  boolean  Defaults belongs_to's to in the database
	 */
	public $in_db = TRUE;
	
	/**
	 * @var  int  Default to 0 for no relationship
	 */
	public $default = 0;
	
	/**
	 * @var  boolean  Null values are not allowed, 0 represents no record
	 */
	public $allow_null = FALSE;
	
	/**
	 * @var  boolean  Empty values are converted to the default
	 */
	public $convert_empty = TRUE;
	
	/**
	 * @var  int  Empty values are converted to 0, not NULL
	 */
	public $empty_value = 0;

	/**
	 * @var  string  A string pointing to the foreign model and (optionally, a
	 *               field, column, or meta-alias).
	 */
	public $foreign = '';
	
	/**
	 * Provides sensible defaults for the foreign model.
	 */
	public function initialize($model, $column)
	{
		if (empty($this->foreign))
		{
			$this->foreign = $column.'.:primary_key';
		}
		elseif (FALSE === strpos($this->foreign, '.'))
		{
			$this->foreign = $this->foreign.'.:primary_key';
		}

		$this->foreign = array_combine(array('model', 'field'), explode('.', $this->foreign));

		if (empty($this->column))
		{
			$this->column = $column.'_id';
		}

		parent::initialize($model, $column);
	}

	/**
	 * Returns Jelly_Model's directly. All other values
	 * are used as the primary key for returning a new
	 * Jelly_Model.
	 *
	 * @return Jelly_Model
	 */
	public function value($model, $value)
	{
		if ($value instanceof Jelly_Model)
		{
			return $value;
		}
		
		return Jelly::query($this->foreign['model'])
			->where($this->foreign['model'].'.'.$this->foreign['field'], '=', $value)
			->limit(1)
			->select();
	}
	
	/**
	 * Returns the primary key of value passed.
	 */
	public function save($model, $value)
	{
		return $this->_id($value);
	}
	
	/**
	 * Compares not by model instances but primary keys
	 * of said models.
	 */
	public function changed($model, $value)
	{
		return $this->_id($value) !== $this->_id($model->original($this->name));
	}
	
	/**
	 * Implementation of Jelly_Field_Behavior_Joinable
	 * 
	 * @TODO Fix!
	 *
	 * @param   Jelly_Builder  $builder
	 * @return  void
	 */
	public function with($builder)
	{
		$builder->join(':'.$this->name, 'LEFT')->on($this->model.'.'.$this->name, '=', ':'.$this->name.'.'.$this->foreign['field']);
	}
}
