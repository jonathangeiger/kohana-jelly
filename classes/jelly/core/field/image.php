<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Handles image uploads and optionally creates thumbnails of different sizes from the uploaded image
 * (as specified by the $thumbnails array).
 *
 * Each thumbnail is specified as an array with the following properties: path, resize, crop, and driver.
 * 
 *  * **path** is the only required property. It must point to a valid, writable directory.
 *  * **resize** is the arguments to pass to Image->resize(). See the documentation for that method for more info.
 *  * **crop** is the arguments to pass to Image->crop(). See the documentation for that method for more info.
 *
 * For example:
 *
 *     "thumbnails" => array (
 *         // 1st thumbnail
 *         array(
 *             'path'   => DOCROOT.'upload/images/my_thumbs/', // where to save the thumbnails
 *             'resize' => array(500, 500, Image::AUTO),       // width, height, resize type
 *             'crop'   => array(100, 100, NULL, NULL),        // width, height, offset_x, offset_y
 *             'driver' => 'ImageMagick',                      // NULL defaults to Image::$default_driver
 *         ),
 *         // 2nd thumbnail
 *         array(
 *             // ...
 *         ),
 *     )
 *
 * @see      Image::resize
 * @see      Image::crop
 * @author   Kelvin Luck
 * @package  Jelly
 */
abstract class Jelly_Core_Field_Image extends Jelly_Core_Field_File
{
	protected static $defaults = array(
		// The path to save to
		'path'   => NULL, 
		 // An array to pass to resize(). e.g. array($width, $height, Image::AUTO)
		'resize' => NULL,
		// An array to pass to crop(). e.g. array($width, $height, $offset_x, $offset_y)
		'crop'   => NULL,
		// The driver to use, defaults to Image::$default_driver
		'driver' => NULL,
	);
	
	/**
	 * @var  array  Specifications for all of the thumbnails that should be automatically generated when a new image is uploaded.
	 *  
	 */
	public $thumbnails = array();
	
	/**
	 * @var  array  Allowed file types
	 */
	public $types = array('jpg', 'gif', 'png', 'jpeg');

	/**
	 * Ensures there we have validation rules restricting file types to valid image filetypes and
	 * that the paths for any thumbnails exist and are writable
	 *
	 * @param  array  $options
	 */
	public function __construct($options = array())
	{
		parent::__construct($options);

		// Check that all thumbnail directories are writable...
		foreach ($this->thumbnails as $key => $thumbnail) 
		{
			// Merge defaults to prevent array access errors down the line
			$thumbnail += Jelly_Field_Image::$defaults;
			
			// Ensure the path is normalized and writable
			$thumbnail['path'] = $this->_check_path($thumbnail['path']);
			
			// Merge back in
			$this->thumbnails[$key] = $thumbnail;
		}
	}

	/**
	 * Logic to deal with uploading the image file and generating thumbnails according to
	 * what has been specified in the $thumbnails array.
	 *
	 * @param   Jelly  $model
	 * @param   mixed  $value
	 * @param   bool   $loaded
	 * @return  string|NULL
	 */
	public function _upload(Jelly_Validator $array, $model, $field)
	{
		// Save the original untouched
		if ( ! parent::_upload($array, $model, $field))
		{
			return;
		}
		
		// Has our source file changed?
		if ($model->changed($field))
		{
			$filename = $array[$field];
			$source   = $this->path.$filename;
			
			foreach ($this->thumbnails as $thumbnail)
			{
				$dest = $thumbnail['path'].$filename;
				
				// Delete old file if necessary
				$this->_delete_old_file($model->original($field), $thumbnail['path']);
				
				// Let the Image class do its thing
				$image = Image::factory($source, $thumbnail['driver'] ? $thumbnail['driver'] : Image::$default_driver);
				
				// This little bit of craziness allows us to call resize 
				// and crop in the order specifed by the config array
				foreach ($thumbnail as $method => $args)
				{
					if (($method === 'resize' OR $method === 'crop') AND $args)
					{
						call_user_func_array(array($image, $method), $args);
					}
				}
				
				// Save
				$image->save($dest);
			}
		}
	}
}
