# Jelly's Other Features

Jelly, being rather flexible in its ways, has a lot of features that aren't 
immediately apparent by the documentation alone. Here, we attempt to expose
many of these more esoteric features.

  * [Aliasing](#aliasing)
  * [Complex Queries](#complex-queries)
  * [Optimizing Results](#optimizing-results)
  * [Unmapped columns](#unmapped-columns)
  * [Fields referencing the same column](#dual-fields)
  * [Field aliases](#field-aliases)
  * [In-table relationships](#in-table-relationships)
  
<h3 id="aliasing">Aliasing</h3>

Every part of Jelly implements table and column aliasing. This means that any
place where you'd normally enter a table or column in your database you can
instead enter a model or field and Jelly will automatically map the model
or field back to the table or column they represent. 

The other nice aspect of this feature is that you aren't *required* to
reference everything by its model or field name. If there's a place you
want to reference a column or table directly, do it! Jelly is smart enough
to not go messing with things it doesn't know about.

Literally every part of Jelly implements this feature. If you're wondering
whether or not a particular feature supports it, the answer is *yes*.

    class Model_Post extends Jelly
    {
        public static function initialize(Jelly_Meta $meta)
        {
            $meta->table = 'Posts';
            $meta->fields += array(
                // Passing a string for the constructor of the field
                // is shorthand for specifying the column the field
                // references in the database.
                'id' => new Field_Primary('PostId'),
                
                // This is the long-hand method, if you need to set
                // other properties in the field as well
                'name' => new Field_String(array(
                    'column' => 'PostName'
                ))
            );
        }
        
        $post = Jelly::factory('post')
                ->where('id', '=', 1)
                ->where('name' => 'Jon')
                ->load();
        
        // This is the SQL that statement generates:
        // SELECT * FROM `Posts` WHERE `PostId` = 1 AND `PostName` = 'Jon';
    }
  
<h3 id="complex-queries">Complex Queries</h3>

Jelly's implementation of Kohana's excellent query builder means that you can
build complex queries entirely with your model. All query builder statements
are automatically aliased to their actual column, so you get to enjoy the 
benefits of working in a model, without the constraints!

**Example - SELECTing**

    $result = Jelly::factory('post')
                 ->select('id', 'name')
                 ->execute();

**Example - DELETEing**

    Jelly::factory('post')
        ->where('name', '=', 'Jon')
        ->execute(Database::DELETE);

**Example - UPDATEing and INSERTing**

    Jelly::factory('post')
        ->where('author', '=', 'Jonathan')
        ->execute(Database::UPDATE, array('author' => 'Paul'));

    Jelly::factory('post')
        ->execute(Database::INSERT, array('author' => 'Paul'));

**Example - Custom JOINs**

    Jelly::factory('post')
        ->select('post.*', 'author.*')
        ->join('author', 'LEFT')
        ->on('post.author', '=', 'author.id')
        ->execute();
        
[!!] For more information, you may want to read up on the [execute](jelly.model#jelly-execute) method

<h3 id="optimizing-results">Optimizing Results</h3>

Say you have a particular table where you could end up retrieving thousands or
millions of records and you're worried about performance. What are your options?

1. Jelly's `execute()` method allows you to specify the class you want results
   to come as. By default, they come back as Jelly's, but if you want, you can
   manually specify to use an array or `stdClass`. In informal tests on
   retrieving 14,000 records, returning each record as a Jelly took about 2.75
   seconds, while records retrieved as a `stdClass` took about 250 milliseconds!
   Each method used about the same amount of memory. 

   Keep in mind, however, that you have lost all of the features of Jelly when
   results are returned as a `stdClass`, however this is generally acceptable for
   large records.

    **Example** - Returning results as a stdClass

		// Oh no! There's 1 million records in this table!
		$result = Jelly::factory('huge_table')
					->where('some-aliased-column', '=', 'foo')
					->execute(Database::SELECT, 'stdClass');
 		 
1. So what happens when you need a bit of a performance boost but you still 
   need Jelly? Take a look at the following code sample for an idea. 
   Retrieving 14,000 records as a Jelly took about 2.75 seconds, while using
   this method shaved a second off of the load time. Not as good as the first
   method, but still better.

	**Example** - Injecting arrays into Jelly

		// Create our model instance
		$model = Jelly::factory('huge_table');
	
		// Oh no! There's 1 million records in this table!
		$result = Jelly::factory('huge-table')
					->where('some-aliased-column', '=', 'foo')
					->execute(Database::SELECT, FALSE); // Passing false returns as array
				
		foreach ($result as $row)
		{
			// Passing TRUE for the first arg indicates this is a database result
			// and the columns need to be aliased.
			// Passing TRUE for the third arg indicates that the data is 
			// "original" (from the database, or not changed).
			$model->set($row, TRUE, TRUE);
		
			// Do stuff with $model as if it were a jelly object
		
			// When you're done, reset the model back to an empty state
			$model->reset();
		}
   
<h3 id="unmapped-columns">Unmapped Columns</h3>

Jelly offers access to so-called "unmapped" columns, which are essentially
columns in your database that aren't referenced by a field. You access these
columns by their actual name.

    // Even though this isn't a field, we can still reference it
    $post->category_id;
    
[!!] **Note:** While unmapped columns can be set, they are never used for saving data.

<h3 id="dual-fields">Fields referencing the same column</h3>

Say that you want the features of unmapped columns but still want to alias
them. If multiple fields reference the same column, Jelly is smart enough to
make sure they all access the same data.

    class Model_Post extends Jelly
    {
        public static function initialize(Jelly_Meta $meta)
        {
            $meta->fields += array(
                'id' => new Field_Primary,
                // The belongsTo field will guess (correctly) that the actual 
                // column it references it category_id
                'category' => new Field_BelongsTo
                'category_id' => new Field_Integer,
            );
        }
    }

    $post = new Model_Post(1);

    // This returns a new Jelly object 
    $post->category;

    // This returns an integer
    $post->category_id;
    
[!!] **Note:** If you choose to represent the same column with two fields, you should always pick one field to use as the canonical column for setting data. Jelly isn't smart enough to know which field to use for data if something is set on both of them. Although this won't necessarily break, it will cause unexpected results.

<h3 id="field-aliases">Field aliases</h3>

Similar to the aforementioned topics, fields can also be aliased to one other.
For example, say you have a `name` field that you also want to be able to
reference as `username`:

    class Model_Post extends Jelly
    {
        public static function initialize(Jelly_Meta $meta)
        {
            $meta->fields += array(
                'id' => new Field_Primary,
                'name' => new Field_String,
                
                // Notice how the value is equal to the field it references
                'username' => 'name'
            );
        }
    }

    $post = new Model_Post(1);

    // These two are equivalent
    $post->name;
    $post->username;
    
Internally, jelly maps `username` to the `name` field. You can do
literally anything you can with the `username` field that you can with 
the `name` field. This includes getting, setting, saving, joining,
and anything else you can think of. Even using `with()` works:

    class Model_Post extends Jelly
    {
        public static function initialize(Jelly_Meta $meta)
        {
            $meta->fields += array(
                'id' => new Field_Primary,
                'name' => new Field_String,
                'author' => new Field_BelongsTo,
                '_author' => 'author'
            );
        }
    }

    $post = new Model_Post;

    // This works
    $post->with('_author')->load();
    
[!!] **Note**: Field aliases do not show up in your `$model->meta()->fields` list.

<h3 id="in-table-relationships">In-Table Relationships</h3>

Say you have a table of categories that you want to be hierarchal. Each 
category can act as a parent or child to another category. Jelly, supports
this natively:

    class Model_Category extends Jelly
    {
        public static function initialize(Jelly_Meta $meta)
        {
            $meta->fields += array(
                'id' => new Field_Primary,
                'parent' => new Field_BelongsTo(array(
                    'foreign' => array(
                        'model' => 'category',
                    ),
                    'column' => 'parent_id'
                )),
                'children' => new Field_HasMany(array(
                    'foreign' => array(
                        'model' => 'category',
                        'column' => 'parent'
                    ),
                ))
            );
        }
    }
    
You can then access your parent and children relationships as such:

    $category = new Model_Category(1);
    
    if ($category->get('children')->count())
    {
        foreach ($category->children as $child)
        {
            foreach($child->children as $grandchild)
            {
                // and so on and so forth
            }
        }
    }
    
    if ($category->get('parent')->count())
    {
        foreach ($category->parent as $parent)
        {
            foreach($parent->parent as $grandparent)
            {
                // and so on and so forth
            }
        }
    }


