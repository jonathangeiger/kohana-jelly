# Jelly Field Types

Jelly comes with many common field types defined as objects with suitable
logic for retrieving and formatting them for the database.

### Field properties

Each field allows you to pass an array to its constructor to easily configure it. All parameters are optional.

#### Global properties

The following properties apply to nearly all fields.

**`in_db`** — Whether or not the field represents an actual column in the table.

**`default`** — A default value for the field.

**`allow_null`** — Whether or not `NULL` values can be set on the field. This defaults to `TRUE` for most fields, except for the string-based fields and relationship fields, in which case it defaults to `FALSE`.

 * If this is `FALSE`, most fields will convert the `NULL` to the field's `default` value. 
 * If this is `TRUE` the field's `default` value will be changed to `NULL` (unless you set the default value yourself).

**`convert_empty`** — If set to `TRUE` any `empty()` values passed to the field will be converted to whatever is set for `empty_value`. This also sets `allow_null` to `TRUE` if `empty_value` is `NULL`.

**`empty_value`** — This is the value that `empty()` values are converted to if `convert_empty` is `TRUE`. The default for this is `NULL`.

______________

#### in_db field properties

The following properties mostly apply to fields that actually represent a column in the table.

**`column`** — The name of the database column to use for this field. If this isn't given, the field name will be used.

**`primary`** — Whether or not the field is a primary key. The only field that
has this set to `TRUE` is `Jelly_Field_Primary`. A model can only have one primary field.

______________

#### Validation properties

The following properties are available to all of the field types and mostly relate to validation. There is a more in-depth discussion of these properties on [the validation documentation](jelly.validation).

**`unique`** — A shortcut property for validating that the field's data is unique in the database.

**`label`** — The label to use for the field when validating.

**`filters`** — Filters to apply to data before validating it.

**`rules`** — Rules to use to validate the data with.

**`callbacks`** — Callbacks to use for any custom validation after all filters and rules have been processed.

### Core fields

These fields represent an actual data type that exists in the database. Extra configurable properties will be listed below the field description.

#### `Jelly_Field_Primary`

Represents a primary key. Each model can only have one primary key.

[API documentation](api/Jelly_Field_Primary)

______________

#### `Jelly_Field_Integer`

Represents an integer. `NULL` values are allowed by default on integer fields.

[API documentation](api/Jelly_Field_Integer)

______________

#### `Jelly_Field_Float`

Represents an integer. `NULL` values are allowed by default on integer fields.

 * **`places`** — Set to an integer to automatically round the value to the proper number of places.

[API documentation](api/Jelly_Field_Integer)

______________

#### `Jelly_Field_String`

Represents a string of any length. `NULL` values are not allowed by default on this field and are simply converted to an empty string.

[API documentation](api/Jelly_Field_String)

______________

#### `Jelly_Field_Text`

Currently, this field behaves exactly the same as `Jelly_Field_String`. 

[API documentation](api/Jelly_Field_Text)

______________

#### `Jelly_Field_Boolean`

Represents a boolean. In the database, it is usually represented by a `tinyint`.

 * **`true`** — What to save `TRUE` as in the database. This defaults to 1, but you may want to have `TRUE` values saved as 'Yes', or 'TRUE'.
 * **`false`** - What to save `FALSE` as in the database.

[!!] An exception will be thrown if you try to set `convert_empty` to `TRUE` on this field. 

[API documentation](api/Jelly_Field_Boolean)

______________

#### `Jelly_Field_Enum`

Represents an enumerated list. Keep in mind that this field accepts any value passed to it, and it is not until you `validate()` the model that you will know whether or not the value is valid or not.

If you `allow_null` on this field, `NULL` will be added to the choices array if it isn't currently in it. Similarly, if `NULL` is in the choices array `allow_null` will be set to `TRUE`.

 * **`choices`** — An array of valid choices.

[API documentation](api/Jelly_Field_Enum)

______________

#### `Jelly_Field_Timestamp`

Represents a timestamp. This field always returns its value as a UNIX timestamp, however you can choose to save it as any type of value you'd like by setting the `format` property.

 * **`format`** — By default, this field is saved as a UNIX timestamp, however you can set this to any valid `date()` format and it will be converted to that format when saving.
 * **`auto_now_create`** — If TRUE, the value will save `now()` whenever INSERTing.
 * **`auto_now_update`** — If TRUE, the field will save `now()` whenever UPDATEing.

[API documentation](api/Jelly_Field_Timestamp)

### Special fields

These fields still represent actual columns in the database but might do special things that don't necessarily equate to any SQL datatype.

#### `Jelly_Field_Slug`

Represents a slug, commonly used in URLs. Any value passed to this will be converted to a lowercase string, will have spaces, dashes, and underscores converted to dashes, and will be stripped of any non-alphanumeric characters (other than dashes).

[API documentation](api/Jelly_Field_Slug)

______________

#### `Jelly_Field_Serialized`

Represents any serialized data. Any serialized data in the database is unserialized before it's retrieved. Likewise, any data set on the field is serialized before it's saved.

[API documentation](api/Jelly_Field_Serialized)

______________

#### `Jelly_Field_Email`

Represents an email. This automatically sets a validation rule that verifies it is a valid email address.

[API documentation](api/Jelly_Field_Email)

______________

#### `Jelly_Field_Password`

Represents an password. This automatically sets a validation callback that hashes the password after it's validated. That password is hashed only when it has changed.

 * **`hash_with`** — A valid PHP callback to use for hashing the password. Defaults to `sha1`.

[API documentation](api/Jelly_Field_Password)

______________

#### `Jelly_Field_Expression`

This field is a rather abstract type that allows you to pull a database expression back on SELECTs. Simply set your `column` to any `DB::expr()`. 

For example, if you always wanted the field to return a concatenation of two columns in the database, you can do this:
 
	'field' => new Jelly_Field_Expression('array(
	      'column' => DB::expr("CONCAT(`first_name`, ' ', `last_name`)")
	))
 
[!!] Keep in mind that aliasing breaks down in Database_Expressions.

[API documentation](api/Jelly_Field_Expression)

______________

#### `Jelly_Field_File`

Represents a file upload. Pass a valid file upload to this and it will be saved automatically in the location you specify. 

In the database, the filename is saved, which you can use in your application logic.

You must be careful not to pass `NULL` or some other value to this field if you do not want the current filename to be overwritten.

 * **`path`** — This must point to a valid, writable directory to save the file to.
 * **`delete_old_file`** — Whether or not to delete the old file when a new one is successfully uploaded. Defaults to `FALSE`.
 * **`types`** — Valid file extensions that the file may have.

[API documentation](api/Jelly_Field_File)

#### `Jelly_Field_Image`

Represents an image upload. This behaves almost exactly the same as `Jelly_Field_File` except it allows an unlimited number of resized images to be created from the original.

Here is an example illustrating the `thumbnails` property. All properties are optional:

	new Jelly_Field_Image(array(
		// ...set your other properties...
		'thumbnails' => array (
			// 1st thumbnail
			array(
				// where to save the thumbnail
				'path'   => DOCROOT.'upload/images/my_thumbs/', 
				// width, height, resize type
				'resize' => array(500, 500, Image::AUTO),       
				// width, height, offset_x, offset_y
				'crop'   => array(100, 100, NULL, NULL),        
				// NULL defaults to Image::$default_driver
				'driver' => 'ImageMagick',                      
			),
			// 2nd thumbnail
			array(
				// ...
			),
		)
	));
	
[!!] The crop and resize steps will be performed in the order you specify them in the array

 * **`path`** — This must point to a valid, writable directory to save the original image to.
 * **`delete_old_file`** — Whether or not to delete the old files when a new image is successfully uploaded. Defaults to `FALSE`.
 * **`types`** — Valid file extensions that the file may have. Defaults to allowing JPEGs, GIFs, and PNGs.

[API documentation](api/Jelly_Field_Image)

### Relationship fields

These fields have been [documented elsewhere](jelly.relationships) since they encompass a large portion of Jelly's functionality.

______________

## Custom fields

Since field objects in Jelly manage almost every aspect of setting and getting
data for that model property, you can achieve powerful and custom model
behavior by [extending the basic field types](jelly.extending-field).