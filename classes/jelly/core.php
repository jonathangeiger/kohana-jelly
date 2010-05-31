<?php defined('SYSPATH') or die('No direct script access.');

/**
 * This core class is the main interface to all
 * models, builders, and meta data.
 */
abstract class Jelly_Core
{
	/**
	 * @var  string  The prefix to use for all model's class names
	 *               This can be overridden to allow you to place
	 *               models and builders in a different location.
	 */
	protected static $_model_prefix = 'model_';
	
        /**
	 * @var  string  This prefix to use for all model's field classes
	 *               This can be overridden to allow you to place
	 *               field classes in a different location.
	 */
	protected static $_field_prefix = 'field_';
        
	/**
	 * @var  array  Contains all of the meta classes related to models
	 */
	protected static $_models = array();

	/**
	 * Factory for instantiating models.
	 *
	 * If $values is passed and it is an array, it will be
	 * applied to the model as if it were a database result.
	 * The model is then considered to be loaded.
	 *
	 * @param   mixed  $model
	 * @param   mixed  $key
	 * @return  Jelly
	 */
	public static function factory($model, $values = NULL)
	{
		$class = Jelly::class_name($model);

		return new $class($values);
	}

	/**
	 * Factory for instantiating fields.
	 *
	 * @param   string $type
	 * @param   mixed  $options
	 * @return  mixed
	 */
	public static function field($type, $options = NULL)
	{
		$field = Jelly::$_field_prefix.$type;
                
		return new $field($options);	
	}
        
	/**
	 * Returns a query builder that can be used for selecting records.
	 *
	 * If $key is passed, the key will be passed to unique_key(), the result
	 * will be limited to 1, and the record will be returned directly.
	 *
	 * In essence, passing a $key is analogous to:
	 *
	 *     Jelly::select($model)->load($key);
	 *
	 * Which itself is a shortcut for:
	 *
	 *     Jelly::select($model)
	 *          ->where(':unique_key', '=', $key)
	 *          ->limit(1)
	 *          ->execute();
	 *
	 * @param   string  $model
	 * @param   mixed   $cond
	 * @return  Jelly_Builder
	 */
	public static function select($model, $key = NULL)
	{
		$builder = Jelly::builder($model, Database::SELECT);

		if ($key)
		{
			return $builder->load($key);
		}

		return $builder;
	}

	/**
	 * Returns a query builder that can be used for inserting record(s).
	 *
	 * Generally, you will only want to use Models directly for creating
	 * new records, since this method doesn't support validation or
	 * relations, but it is still here to complete the API.
	 *
	 * @param   string  $model
	 * @return  Jelly_Builder
	 */
	public static function insert($model)
	{
		return Jelly::builder($model, Database::INSERT);
	}

	/**
	 * Returns a query builder that can be used for updating many records.
	 *
	 * Similar to Jelly::insert(), you will generally want to use Models for
	 * updating an individual record, but this method is useful for updating
	 * columns on multiple rows all at once.
	 *
	 * @param   string  $model
	 * @return  Jelly_Builder
	 */
	public static function update($model)
	{
		return Jelly::builder($model, Database::UPDATE);
	}

	/**
	 * Returns a query builder that can be used for deleting many records.
	 *
	 * While you will generally want to use models for deleting single records,
	 * this method remains useful for deleting multiple rows all at once.
	 *
	 * @param  string $model
	 * @return Jelly_Builder
	 */
	public static function delete($model)
	{
		return Jelly::builder($model, Database::DELETE);
	}

	/**
	 * Gets a particular set of metadata about a model. If the model
	 * isn't registered, it will attempt to register it.
	 *
	 * FALSE is returned on failure.
	 *
	 * @param   string|Jelly_Model  $model
	 * @return  Jelly_Meta
	 */
	public static function meta($model)
	{
		$model = Jelly::model_name($model);

		if ( ! isset(Jelly::$_models[$model]))
		{
			if ( ! Jelly::register($model))
			{
				return FALSE;
			}
		}

		return Jelly::$_models[$model];
	}

	/**
	 * Automatically loads a model, if it exists,
	 * into the meta table.
	 *
	 * Models are not required to register
	 * themselves; it happens automatically.
	 *
	 * @param   string  $model
	 * @return  boolean
	 */
	public static function register($model)
	{
		$class = Jelly::class_name($model);
		$model = Jelly::model_name($model);

		// Don't re-initialize!
		if (isset(Jelly::$_models[$model]))
		{
			return TRUE;
		}

		 // Can we find the class?
		if (class_exists($class))
		{
			// Prevent accidentally trying to load ORM or Sprig models
			if ( ! is_subclass_of($class, "Jelly_Model"))
			{
				return FALSE;
			}
		}
		else
		{
			return FALSE;
		}

		// Load it into the registry
		Jelly::$_models[$model] = $meta = new Jelly_Meta($model);

		// Let the intialize() method override defaults.
		call_user_func(array($class, 'initialize'), $meta);

		// Finalize the changes
		$meta->finalize($model);

		return TRUE;
	}

	/**
	 * Returns the class name of a model
	 *
	 * @param   string|Jelly_Model  $model
	 * @return  string
	 */
	public static function class_name($model)
	{
		if ($model instanceof Jelly_Model)
		{
			return strtolower(get_class($model));
		}
		else
		{
			return strtolower(Jelly::$_model_prefix.$model);
		}
	}

	/**
	 * Returns the model name of a class
	 *
	 * @param   string|Jelly_Model  $model
	 * @return  string
	 */
	public static function model_name($model)
	{
		if ($model instanceof Jelly_Model)
		{
			$model = get_class($model);
		}

		$prefix_length = strlen(Jelly::$_model_prefix);

		// Compare the first parts of the names and chomp if they're the same
		if (strtolower(substr($model, 0, $prefix_length)) === strtolower(Jelly::$_model_prefix))
		{
			$model = substr($model, $prefix_length);
		}

		return strtolower($model);
	}

	/**
	 * Returns the actual column name for a field, field alias, or meta-alias.
	 *
	 * $field must be in the format of "model.field". Supply $value if
	 * you want the unique_key meta-alias to work properly.
	 *
	 * An array is returned containing the table and column keys. If the model's meta is found,
	 * but the field can't be found, 'column' will contain the field name passed.
	 *
	 * Returns FALSE on failure.
	 *
	 * @param   string  $field
	 * @return  array
	 */
	public static function alias($field, $value = NULL)
	{
		if (FALSE !== strpos($field, '.'))
		{
			list($model, $field) = explode('.', $field);
		}
		else
		{
			$model = NULL;
		}

		// We should at least return something now
		$table = $model;
		$column = $field;

		// Hopefully we can find a meta object by now
		$meta = Jelly::meta($model);

		// Check for a meta-alias first
		if (FALSE !== strpos($field, ':'))
		{
			$field = $column = Jelly::meta_alias($meta, $field, $value);
		}

		if ($meta)
		{
			$table = $meta->table();

			// Alias the field
			if ($field = $meta->fields($field))
			{
				$column = $field->column;
			}
		}

		return array(
			'table' => $table,
			'column' => $column,
		);
	}

	/**
	 * Resolves meta-aliases
	 *
	 * @param   mixed   $meta
	 * @param   string  $field
	 * @param   mixed   $value
	 * @return  string
	 */
	public static function meta_alias($meta, $field, $value = NULL)
	{
		// Allow passing the model name
		if (is_string($meta) OR $meta instanceof Jelly_Model)
		{
			$meta = Jelly::meta($meta);
		}

		// Check for a model operator
		if (substr($field, 0, 1) !== ':')
		{
			list($model, $field) = explode(':', $field);

			// Append the : back onto $field, it's key for recognizing the alias below
			$field = ':'.$field;

			// We should be able to find a valid meta object here
			if (FALSE == ($meta = Jelly::meta($model)))
			{
				throw new Kohana_Exception('Meta data for :model was not found while trying to resolve :field', array(
					':model' => $model,
					':field' => $field));
			}
		}

		switch ($field)
		{
			case ':primary_key':
				$field = $meta->primary_key();
				break;
			case ':name_key':
				$field = $meta->name_key();
				break;
			case ':foreign_key':
				$field = $meta->foreign_key();
				break;
			case ':unique_key':
				$field = Jelly::builder($meta->model())->unique_key($value);
				break;
			default:
				throw new Kohana_Exception('Unknown meta alias :field', array(
					':field' => $field));
		}

		return $field;
	}

	/**
	 * Aliases a Joinable column base on the field's alias
	 *
	 * Required to allow joins to the same table with different aliases in one query.
	 *
	 * If a field object is passed, it's (hopefully unique) join alias is returned.
	 *
	 * If a sting is passed, it is converted back to a model identifier if it is a valid join alias format
	 * or FALSE is returned otherwise. This allows for correctly aliasing fields that have a join alias
	 * rather than a model identifier.
	 *
	 * @param   Jelly_Field | string  Field to alias or alias to convert back to model.field
	 * @return  string | FALSE
	 */
	public static function join_alias($field)
	{
		if ($field instanceof Jelly_Field)
		{
			// Return join alias for field
			// Join alias is the foreign model name with the aliased name from the field
			return '_'.$field->foreign['model'].':'.$field->name;
		}

		// If this is a join alias
		if (substr($field, 0, 1) === '_')
		{
			list($model, $field) = explode(':', substr($field, 1), 2);

			// This is a valid join alias, return the model it aliases
			return $model;
		}

		// Don't know what this is, just return it
		return FALSE;
	}

	/**
	 * Returns the prefix to use for all models and builders.
	 *
	 * @return  string
	 */
	public static function model_prefix()
	{
		return Jelly::$_model_prefix;
	}

	/**
	 * Returns the builder class to use for the specified model
	 *
	 * @param   string  $model
	 * @param   int     $type
	 * @return  string
	 */
	protected static function builder($model, $type = NULL)
	{
		$builder = 'Jelly_Builder';

		if ($meta = Jelly::meta($model))
		{
			if ($meta->builder())
			{
				$builder = $meta->builder();
			}
		}

		return new $builder($model, $type);
	}
}
