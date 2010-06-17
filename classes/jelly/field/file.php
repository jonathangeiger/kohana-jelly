<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Handles files and file uploads.
 *
 * If a valid upload is set on the field, the upload will be saved
 * automatically to the $path set and the value of the field will
 * be the filename used.
 *
 * @package  Jelly
 */
abstract class Jelly_Field_File extends Jelly_Field
{
	/**
	 * @var  boolean  Whether or not to delete the old file when a new file is added
	 */
	public $delete_old_file = TRUE;
	
	/**
	 * Ensures there is a path for saving set
	 *
	 * @param  array  $options
	 */
	public function __construct($options = array())
	{
		parent::__construct($options);
		
		// Normalize the path
		$this->path = realpath(str_replace('\\', '/', $this->path));
		
		// Ensure we have a trailing slash
		if (!empty($this->path) AND is_writable($this->path))
		{
			$this->path = rtrim($this->path, '/').'/';
		}
		else
		{
			throw new Kohana_Exception(get_class($this).' must have a `path` property set that points to a writable directory');
		}	
	}

	/**
	 * Uploads a file if we have a valid upload
	 *
	 * @param   Jelly  $model
	 * @param   mixed  $value
	 * @param   bool   $loaded
	 * @return  string|NULL
	 */
	public function save($model, $value, $loaded)
	{
		$original = $model->get($this->name, FALSE);
		
		// Upload a file?
		if (is_array($value) AND upload::valid($value))
		{
			if (FALSE !== ($filename = upload::save($value, NULL, $this->path)))
			{
				// Chop off the original path
				$value = str_replace($this->path, '', $filename);

				// Ensure we have no leading slash
				if (is_string($value))
				{
					$value = trim($value, '/');
				}
				
				 // Delete the old file if we need to
				if ($this->delete_old_file AND $original != $this->default)
				{
					$path = $this->path.$original;
					
					if (file_exists($path)) 
					{
						unlink($path);
					}
				}
			}
			else
			{
				$value = $this->default;
			}
		}

		return $value;
	}
}
