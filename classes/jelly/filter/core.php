<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Field Filter
 *
 * @package Jelly
 * @category Security
 * @author Roman Shamritskiy
 *
 * @abstract
 */
abstract class Jelly_Filter_Core
{
    /**
     * @var array
     */
    protected $_array = array();

    /**
     * @var array map of filters
     */
    protected $_filters = array();


    /**
     * Creates a new Jelly_Filter instance.
     *
     * @static
     * @param array $array
     * @return Jelly_Filter
     */
    public static function factory(array $array)
    {
        return new Jelly_Filter($array);
    }

    /**
     * Constructor
     * @param array $array
     */
    public function __construct(array $array)
    {
        $this->_array = $array;
    }

    /**
     * Bind field
     * @param string $name Field name
     * @param &mixed $var
     * @return $this
     */
    public function bind($name, &$var)
    {
        $this->_array[$name] =& $var;
    }
    
    /**
     * Overwrites or appends filters to a field.
     * Each filter will be executed once.
     * All rules must be valid PHP callbacks.
     *
     * @example
     *     // Run trim() on all fields
     *     $validation->filter(TRUE, 'trim');
     *
     * @param string $field Field name
     * @param callback $filter PHP Callback
     * @param array $params array of params to send with callback
     * @return $this
     */
    public function filter($field, $filter, array $params = NULL)
    {
        $this->_filters[$field][$filter] = (array) $params;
        return $this;
    }

    /**
     * Add filters using an array.
     *
     * @param string $field Field name
     * @param array $filters List of functions or static method name
     * @return $this
     */
    public function filters($field, array $filters)
    {
        foreach ($filters as $filter)
        {
            $this->filter($field, $filter[0], Arr::get($filter, 1, NULL));
        }
         
        return $this;
    }

    /**
     * Execute filters
     * @return $this
     */
    public function execute()
    {
        if (Kohana::$profiling === TRUE)
        {
            // Start a new benchmark
            $benchmark = Profiler::start('Validation', __FUNCTION__);
        }

        // Import the filters
        $filters   = $this->_filters;

        foreach ($filters as $field => &$field_filter)
        {
            if ($field === TRUE)
            {
                continue;
            }
            // Add global filters
            if (isset($filters[TRUE]))
            {
                $field_filters += $filters[TRUE];
            }
        }

        unset($filters[TRUE]);

        // Execute the filters

        foreach ($filters as $field => $set)
        {
            // Get the field value
            $value = $this->_array[$field];

            foreach ($set as $filter => $params)
            {
                // Add the field value to the parameters
                array_unshift($params, $value);

                if (strpos($filter, '::') === FALSE)
                {
                    // Use a function call
                    $function = new ReflectionFunction($filter);

                    // Call $function($this[$field], $param, ...) with Reflection
                    $value = $function->invokeArgs($params);
                }
                else
                {
                    // Split the class and method of the rule
                    list($class, $method) = explode('::', $filter, 2);

                    // Use a static method call
                    $method = new ReflectionMethod($class, $method);

                    // Call $Class::$method($this[$field], $param, ...) with Reflection
                    $value = $method->invokeArgs(NULL, $params);
                }
            }

            // Set the filtered value
            $this->_array[$field] = $value;
        }
        
        return $this;
    }
    
    /**
     * Return array :field => :value
     * @return array
     */
    public function as_array()
    {
        return (array) $this->_array;
    }
    
    /**
     * @param string $offset
     * @return bool
     */
    public function offsetExists($offset)
    {
        return array_key_exists($offset, $this->_array);
    }
}