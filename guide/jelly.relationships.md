# Jelly Relationships

Jelly supports standard 1:1, 1:many and many:many relationships through special fields.

### Defining Relationships

Relationships are defined by fields. The standard relationships in Jelly use 
`Field_BelongsTo`, `Field_HasOne`, `Field_HasMany` and `Field_ManyToMany` for this.

Since Jelly supports top-to-bottom aliasing, all relationship fields can
specify not only the column in the current table (for belongs to) and the
model which they are relating to, but also the corresponding relationship field
in the other model. This allows unambiguous relationship definitions and
therefore completely non-standard foreign key column names if necessary.

As an example here are some model definitions. Note that the other model
fields are left out of the definitions for clarity.

**An aside regarding "meta-aliases"**

Jelly's query builder implements what we call "meta-aliases". These are
references to special fields in a model, such as it's primary or foreign key.
Using these is less cumbersome, programmatically speaking, than manually
instantiating a model's meta object to get at them. Most of the defaults for
relationships uses the ':foreign_key' meta-alias, when you can set in your model.

**Here's a quick example to illustrate:**

	Jelly::select('post')->join('author')->on('post.author:foreign_key', '=', 'author.:primary_key');
	=> SELECT * FROM `posts` JOIN `authors` ON (`posts`.`author_id` = `authors`.`id`);

Notice we specify the model for `:foreign_key`. This can be done with all
meta-aliases, but is especially useful for getting at another model within the
context of another.

#### Example - defining relationships

	// Each author belongs to an editor, has many posts and approved posts,
	// has one address, and has a many to many relationship with roles
	class Model_Author extends Jelly_Model
	{
		public static function initialize($meta)
		{
			$meta->fields(array(
				'editor' => new Field_BelongsTo(
					// We can specify the foreign connection to ours
					'foreign' => 'editor.id',

					// Since BelongsTo has a column in the table, we can specify that
					// However, this would default to editor_id anyway.
					'column' => 'editor_id',
				),
				'posts' => new Field_HasMany(array(
					// If not set, this would default to post.author:foreign_key
					// And would expand in the query builder to posts.author_id
					'foreign' => 'post.author_id',
				)),
				'approved_posts' => new Field_HasMany(array(
					// Note a non-standard column can be used to make
					// multiple relationships between the same column possible
					'foreign' => 'post.approved_by',
				)),
				'address' => new Field_HasOne(array(
					// It's also possible to specify only a model.
					// This defaults to address.author:foreign_key
					'foreign' => 'address',
				)),
				'roles' => new Field_ManyToMany(array(
					// Once again, we're only specifying the model.
					// The user's foreign key is added automatically.
					'foreign' => 'role',

					// Through can be a model or table by itself
					'through' => 'author_roles',

					// Or if you need to specify the columns in the pivot table:
					'through' => array(
						'model'   => 'author_roles',
						'columns' => array('author_id', 'role_id'),
					),
				)),
			));
		}
	}

[!!] **Note**: Except for `through` in ManyToMany relationships, you should always specify a valid model. You do not need to specify a valid field in the model, however. As long as the column exists in the database, it will work.

### Accessing Relationships

Using our models defined above, we can now do:

	$user = Jelly::select('user', 1);

	// Print postcode
	echo $user->address->postal_code;

	// Get Jelly_Result of all posts
	$posts = $user->posts;

	// Get approved posts
	$approved = $user->get('posts')->where('approved', '=', 1)->execute();

### Managing Relationships

For n:1 relations, we just set them as properties

	$user = Jelly::factory('user');

	// Set by primary key value
	$user->address = 1;

	// Set by model instance
	$user->address = Jelly::select('address', 1);

	// Remove relationship (assuming this is allowed by your validation rules)
	$user->address = NULL;

	// Saves change to relationship to database
	$user->save();

For n:many relations we use `add()` or `remove()`:

	// Add single post by primary key value
	$user->add('posts', 1);

	// Add post by assigning an instance
	$user->add('posts', $post);

	// Add multiple relations with a mixture of primary key values and instances.
	$user->add('posts', array(1, 2, $post));

	// Takes the same args as add()
	$user->remove('posts', 1);

Adding relations which already exist and deleting ones that don't has no effect and won't cause errors.

[!!] **Note**: Currently `save()` saves only the changes to the *relationship* and not to the actual models themselves. This means you cannot assign non-saved instances as relationships.