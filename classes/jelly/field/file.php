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
	 * Ensures there is a path for saving set
	 *
	 * @param  array  $options
	 */
	public function __construct($options = array())
	{
		parent::__construct($options);

		// Ensure we have path to save to
		if (empty($this->path) OR !is_writable($this->path))
		{
			throw new Kohana_Exception(get_class($this).' must have a `path` property set that points to a writable directory');
		}

		// Make sure the path has a trailing slash
		$this->path = rtrim(str_replace('\\', '/', $this->path), '/').'/';
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
		// Upload a file?
		if (is_array($value) AND upload::valid($value))
		{
			if (FALSE !== ($filename = upload::save($value, NULL, $this->path)))
			{
				// Chop off the original path
				$value = str_replace(realpath($this->path).DIRECTORY_SEPARATOR, '', $filename);

				// Ensure we have no leading slash
				if (is_string($value))
				{
					$value = trim($value, DIRECTORY_SEPARATOR);
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
