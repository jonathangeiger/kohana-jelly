# Jelly Relationships

Jelly supports standard 1:1, 1:many and many:many relationships through special fields.

### Defining Relationships

Relationships are defined by fields. The standard relationships in Jelly use 
`Field_BelongsTo`, `Field_HasOne`, `Field_HasMany` and `Field_ManyToMany` for this.

Since Jelly supports top-to-bottom aliasing, all realtionship fields must specify not only the
column in the current table (for belongs to) and the model which they are relating to, but also the corresponding
reltionship field in the other model. This allows unambiguous relationship definitions and therefore 
completey non-standard foreign key column names if necessary.

As an example here are some model definitions. Note that the other model fields are left out of the definitions for clarity.

#### Example - defining relationships

	// *user* has one *address*, has many *posts* 
	// and has a many to many relationship with *roles*
    class Model_User extends Jelly_Model
    {
    	public static function initialize($meta)
    	{
    		$meta->fields(array(
    			'address' => new Field_HasOne(array(
    				'foreign' => array(
						'model'		=> 'address',	 // If not set, this defaults to singular field alias
						'column'	=> 'address_id', // This would default to 'address_id' too
					)	
    			)),
    			'posts' => new Field_HasMany(array(
    				'foreign' => array(
						'model'		=> 'post',
						'column'	=> 'author_id',	// Note a non-standard column can be used
					)	
    			)),
    			'approved_posts' => new Field_HasMany(array(
    				'foreign' => array(
						'model'		=> 'post',
						'column'	=> 'approved_by',	// Note a non-standard column can be used makes 
														// multiple relationships between the same column possible
					)	
    			)),
    			'roles' => new Field_ManyToMany(array(
    				'foreign' => array(
						'model'		=> 'role',
					),
					'through' => array(
						'model' 	=> 'roles_users',	// Can be a model or just a pivot table like here, this is again the default
						'columns'	=> array(
							'user_id',					// Must be the pivot table/through model field for THIS model
							'role_id',					// the pivot table/model field for FOREIGN model
						),
					),
    			)),
    		));
    	}
    }
    
	// *address* belongs to *user*
    class Model_Address extends Jelly_Model
    {
    	public static function initialize($meta)
    	{
    		$meta->fields(array(
    			'user' => new Field_BelongsTo(array(
    				'foreign' => array(
						'model'		=> 'user',	// If not set, this defaults to singular field alias
						'column'	=> 'id',	// This defaults to the User model Primary key column
					)	
    			)),
    		));
    	}
    }
    
	// *post* belongs to *user* (author) and belongs to *user* (approved_by)
    class Model_Post extends Jelly_Model
    {
    	public static function initialize($meta)
    	{
    		$meta->fields(array(
    			'author' => new Field_BelongsTo(array(
    				'column'  => 'author_id',	// We have an aliased and non-standard foreign key column
    				'foreign' => array(
						'model'		=> 'user',	// If not set, this defaults to singular field alias
						'column'	=> 'id',	// This defaults to the User model Primary key column
					)	
    			)),
    			'approved_by' => new Field_BelongsTo(array(
    				'column'  => 'approved_by',	// We have an aliased and non-standard foreign key column
    				'foreign' => array(
						'model'		=> 'user',	// If not set, this defaults to singular field alias
						'column'	=> 'id',	// This defaults to the User model Primary key column
					)	
    			)),
    		));
    	}
    }
    
	// *role* has a many to many relationship with *user*
    class Model_Role extends Jelly_Model
    {
    	public static function initialize($meta)
    	{
    		$meta->fields(array(
    			'users' => new Field_ManyToMany(array(
    				'foreign' => array(
						'model'		=> 'user',
					),
					'through' => array(
						'model' 	=> 'roles_users',	// Can be a model or just a pivot table like here, this is again the default
						'columns'	=> array(
							'role_id',					// Must be the pivot table/through model field for THIS model
							'user_id',					// the pivot table/model field for FOREIGN model
						),
					),
    			)),
    		));
    	}
    }


### Accessing Relationships

Using our models defined above, we can now do:

    $user = Jelly::select('user', 1);
    
    // Print postcode
    echo $user->address->postcode;
    
    // Get Jelly_Result of all posts
    $posts = $user->posts;
    
    // Get approved posts
    $approved = $user->get('posts')->where('approved', '=', 1)->execute();

### Managing Relationships

For n:1 relations, we just set them as properties

    $user = Jelly::factory('user');
    
    $user->address = 1; // Set by unique key value
    
    $user->address = Jelly::select('address', 1); // Set by model instance
    
    $user->save(); // Saves change to relationship to database
    
[!!] **Note**: Currently `save()` saves only the changes to the *relationship* and not to the actual models themselves. This means you cannot assign non-saved models as relationships.