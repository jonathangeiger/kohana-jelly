<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Handles file uploads.
 *
 * Since this field is ultimately just a varchar in the database, it 
 * doesn't really make sense to put rules like Upload::valid or Upload::type
 * on the validation object; if you ever want to NULL out the field, the validation
 * will fail!
 * 
 * As such, these 
 *
 * @package  Jelly
 */
abstract class Jelly_Core_Field_File extends Jelly_Field
{
	/**
	 * @var  boolean  Whether or not to delete the old file when a new file is added
	 */
	public $delete_old_file = TRUE;
	
	/**
	 * @var  string  The path to save the file in
	 */
	public $path = NULL;
	
	/**
	 * @var  array  Valid types for the file
	 */
	public $types = array();
	
	/**
	 * Ensures there is a path for saving set
	 *
	 * @param  array  $options
	 */
	public function __construct($options = array())
	{
		parent::__construct($options);
		
		$this->path = $this->_check_path($this->path);
		
		// Add a callback to save the file when validating
		$this->callbacks[] = array(array($this, '_upload'), array(':validate', ':model', ':field'));
	}

	/**
	 * Logic to deal with uploading the image file and generating thumbnails according to
	 * what has been specified in the $thumbnails array.
	 *
	 * @param   Jelly_Validator  $model
	 * @param   Jelly_Model      $model
	 * @param   string           $field
	 * @return  string|NULL
	 */
	public function _upload(Jelly_Validator $array, $model, $field)
	{
		if ($array->errors())
		{
			// Don't bother uploading
			return;
		}
		
		// Get the image from the array
		$file = $array[$field];

		if ( ! Upload::valid($file) OR ! Upload::not_empty($file))
		{
			// No need to do anything right now
			return;
		}
		
		// Check to see if it's a valid type
		if ($this->types AND ! Upload::type($file, $this->types))
		{
			$array->error($field, 'Upload::type');
		}
		
		// Sanitize the filename
		$file['name'] = preg_replace('/[^a-z0-9-\.]/', '-', strtolower($file['name']));

		// Strip multiple dashes
		$file['name'] = preg_replace('/-{2,}/', '-', $file['name']);
		
		// Upload a file?
		if (FALSE !== ($filename = Upload::save($file, NULL, $this->path)))
		{
			// Chop off the original path
			$value = str_replace($this->path, '', $filename);

			// Ensure we have no leading slash
			if (is_string($value))
			{
				$value = trim($value, '/');
			}
			
			// Garbage collect
			$this->_delete_old_file($model->original($this->name), $this->path);
			
			// Set the new filename on the model
			$array[$field] = $value;
		}
		else
		{
			$array->error($field, 'Upload::save');
		}
	}
	
	/**
	 * Checks that a given path exists and is writable and that it has a trailing slash.
	 *
	 * (pulled out into a method so that it can be reused easily by image subclass)
	 *
	 * @param  $path
	 * @return string The path - making sure it has a trailing slash
	 */
	protected function _check_path($path)
	{
		// Normalize the path
		$path = realpath(str_replace('\\', '/', $path));
		
		// Ensure we have a trailing slash
		if (!empty($path) AND is_writable($path))
		{
			$path = rtrim($path, '/').'/';
		}
		else
		{
			throw new Kohana_Exception(get_class($this).' must have a `path` property set that points to a writable directory');
		}
		
		return $path;
	}
	
	/**
	 * Deletes the previously used file if necessary.
	 *
	 * @param   string $filename 
	 * @param   string $path 
	 * @return  void
	 */
	protected function _delete_old_file($filename, $path)
	{
		 // Delete the old file if we need to
		if ($this->delete_old_file AND $filename != $this->default)
		{
			$path = $path.$filename;
			
			if (file_exists($path)) 
			{
				unlink($path);
			}
		}
	}
}
