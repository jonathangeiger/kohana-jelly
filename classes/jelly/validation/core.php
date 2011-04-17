<?php defined('SYSPATH') or die('No direct script access.');
/**
 * Jelly validation
 * @package Jelly
 * @author Roman Shamritskiy
 * @abstract
 * @see Kohana_Validation
 * @todo Реализовать обработку фильтров
 */
abstract class Jelly_Validation_Core extends Validation
{
    /**
     * Filters map
     * @var array
     */
    protected $_filters = array();
    
    
    /**
     * Creates a new Validation instance.
     *
     * @param   array   array to use for validation
     * @return  Jelly_Validation
     */
    public static function factory(array $array)
    {
        return new Jelly_Validation($array);
    }
    
    /**
     * Add filter to field
     * 
     * @example
     * 		// Run trim() on all fields<br>
     * 		$validation->filter(TRUE, 'trim');
     * 
     * @param string $field Field name
     * @param mixed $filter Valid PHP callback
     * @param array $params Extra parameters for the filter
     * @return $this
     */
    public function filter($field, $filter, array $params = NULL)
    {     
        // Store the filter and params for this rule
        $this->_filters[$field][$filter] = (array) $params;
     
        return $this;
    }
    
    /**
     * Add filters using an array.
     * @param string $field Filter name
     * @param array $filters List of functions or static method name
     */
    public function filters($field, array $filters)
    {
        foreach ($filters as $filter => $params)
        {
            $this->filter($field, $filter, $params);
        }
     
        return $this;
    }
}