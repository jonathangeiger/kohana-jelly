<?php defined('SYSPATH') or die('No direct script access.');

abstract class Jelly_Core_Behavior_Translatable implements Jelly_Behavior_Interface
{
	/**
	 * @var  string  The model this is attached to
	 */
	protected $_model;
	
	/**
	 * @var  string  The name of this behavior
	 */
	protected $_name;
	
	/**
	 * @var  string  The default language for the model
	 */
	protected $_lang = 'en-us';
	
	/**
	 * @var  string  The table to save translations to
	 */
	protected $_table = NULL;
	
	/**
	 * @var  array  The fields that are to be translated
	 */
	protected $_fields = array();
	
	/**
	 * @var  boolean  Whether or not the behavior is disabled on this model.
	 */
	protected $_disabled = FALSE;
	
	/**
	 * Constructor.
	 *
	 * @param   array   $params 
	 */
	public function __construct($params = array())
	{
		foreach ($params as $key => $param)
		{
			$this->{'_'.$key} = $param;
		}
	}
	
	/**
	 * Initialize.
	 *
	 * @param   string   $model 
	 * @param   string   $name 
	 */
	public function initialize($model, $name)
	{
		$this->_model = $model;
		$this->_name  = $name;
		
		// Ensure we have fields to translate
		if (empty($this->_fields))
		{
			throw new Kohana_Exception(':class requires the `fields` property be set', array(
				':class' => get_class($this)));
		}
		
		// Convert the language to a usable format
		$this->_filter_lang($this->_lang);
	}
	
	/**
	 * Sets a default table if necessary.
	 *
	 * @param   Jelly_Meta  $meta 
	 * @return  void
	 */
	public function after_meta_finalize(Jelly_Meta $meta)
	{
		// Set the table we want to use if we haven't yet
		if ($this->_table === NULL)
		{
			$this->_table = $meta->table().'_i10n';
		}
	}
	
	/**
	 * Sets and gets the locale for the current model.
	 * 
	 * Pass NULL for $lang to set the locale back to the default.
	 *
	 * @param   Jelly_Model    $model 
	 * @param   mixed          $lang
	 * @return  $model|string
	 */
	public function model_lang(Jelly_Model $model, $lang = FALSE)
	{
		// Return the current locale
		if ($lang === FALSE)
		{
			return $this->_current_lang($model);
		}
		
		// Set the locale back to the default
		if ($lang === TRUE)
		{
			unset($model->{$this->_name.'_lang'});
			return $model;
		}
		
		// If we've made it here we're changing languages. We need to save
		// the current language and then swap the new one into the model.
		$current_lang = $this->_current_lang($model);
		$new_lang     = $this->_filter_lang($lang);
		$values       = $model->get($this->_name);
		
		// Get the values from the database for the current locale
		if ( ! isset($values[$new_lang]) AND $model->loaded())
		{
			$values[$new_lang] = array();
			$query = Jelly::query($this->_table);
			$meta  = $model->meta();
			
			foreach ($this->_fields as $field)
			{
				// Since this is a table, we have to alias manually
				$query->select_column($this->_table.'.'.$meta->field($field)->column, $field);
			}
			
			// Finish the query
			$result = $query->where($this->_model.':foreign_key', '=', $model->id())
			                ->where('lang', '=', $lang)
			                ->limit(1)
			                ->select();
			
			$values[$new_lang] = array_merge($values[$new_lang], $result);
		}
		
		// Swap our new and current locale values
		foreach ($this->_fields as $field)
		{
			// Save current values into their locale
			$values[$current_lang][$field] = $model->get($field);
			
			// Copy the defaults to the new locale
			if ( ! isset($values[$new_lang]))
			{
				$values[$new_lang][$field] = $values[$this->_lang][$field];
			}
		}
		
		// Re-set our translation properties
		$model->set($this->_name.'_lang', $new_lang);
		$model->set($this->_name, $values);
		
		// Copy the new language data over to the main model
		$model->set($values[$new_lang]);
		
		return $model;
	}
	
	/**
	 * Copies the default locale into the validate array.
	 *
	 * @param Jelly_Model $model 
	 * @param Jelly_Validator $validate 
	 * @return void
	 * @author Tiger Advertising
	 */
	public function before_model_validate(Jelly_Model $model, Jelly_Validator $validate)
	{
		if ($this->_current_lang($model) !== $this->_lang)
		{
			// Swap the default locale's data back in
			foreach ($this->_fields as $field)
			{
				if (isset($validate[$field]))
				{
					$validate[$field] = $model->get($this->_key(''));
				}
			}
		}
	}
	
	/**
	 * Saves any associated translation data.
	 *
	 * @param   Jelly_Model $model 
	 * @param   mixed       $key 
	 * @return  void 
	 */
	public function after_model_save(Jelly_Model $model, $key)
	{
		foreach ($this->_fields as $field)
		{
			
		}
	}
	
	/**
	 * Deletes all associated translations for the record.
	 *
	 * @param  Jelly_Model $model 
	 * @param  mixed       $key 
	 * @return void
	 */
	public function after_model_delete(Jelly_Model $model, $key)
	{
		Jelly::query($this->_table)
		     ->where($this->_model.':foreign_key', '=', $key)
		     ->delete();
	}
	
	/**
	 * Returns the current language for a model.
	 *
	 * @param    Jelly_Model   $model 
	 * @return   string
	 */
	protected function _current_lang(Jelly_Model $model)
	{
		return isset($model->{$this->_name.'_lang'}) ? $model->{$this->_name.'_lang'} : $this->_lang;
	}
	
	/**
	 * Filters a locale to the accepted format.
	 *
	 * @param   string  $lang 
	 * @return  string
	 */
	protected function _filter_lang($lang)
	{
		return strtolower(str_replace(array(' ', '_'), '-', $lang));
	}
}