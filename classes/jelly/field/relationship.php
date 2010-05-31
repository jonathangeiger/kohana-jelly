<?php defined('SYSPATH') or die('No direct script access.');

/**
 * An abstract class that is useful for identifying which
 * fields that are relationships.
 *
 * @package  Jelly
 * @author   Jonathan Geiger
 */
abstract class Jelly_Field_Relationship extends Jelly_Field
{
	/**
	 * Most relationship are in fact not part of the model's table
	 *
	 * @var  string
	 */
	public $in_db = FALSE;

	/**
	 * Generally contains details of the field's relationship
	 *
	 * @var  string
	 */
	public $foreign = array();

	/**
	 * Displays a selection of models to relate to
	 *
	 * @param   string  $prefix  The prefix to put before the filename to be rendered
	 * @return  View
	 **/
	public function input($prefix = 'jelly/field', $data = array())
	{
		if ( ! isset($data['options']))
		{
			$data['options'] = Jelly::select($this->foreign['model'])
				->execute()
				->as_array(':primary_key', ':name_key');
		}

		return parent::input($prefix, $data);
	}
}
