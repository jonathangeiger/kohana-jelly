# Jelly Relationships

Jelly supports standard 1:1, 1:many and many:many relationships through special fields.

### Understanding the different relationships

The most common relationship types and the fields that represent them have been outlined below, along with a simple example.

[!!] In the examples below the properties (such as `foreign` and `through` ) specified on the field are entirely optional but have been show for clarity.

#### `Jelly_Field_BelongsTo` (1:1)

This field allows one model to belong to, or be *owned by*, another model.

For example, a post belongs to one author. In the database, the `posts` table would have an `author_id` column that contains the primary key of the author it belongs to.

	// In Model_Post::initialize()
	'author' => new Jelly_Field_BelongsTo(array(
		'column' => 'author_id',
		'foreign' => 'author',
	)),
	
**Properties**

**`column`** — This specifies the name of the column that the field represents. `BelongsTo` is different from other relationships in that
it actually represents a column in the database. Generally, this property is going to be equal to the foreign key of the model it belongs to.

	// The default, using the above example
	'column' => 'author_id'

**`foreign`** — The model that this field belongs to. You can also pass a field to use as the foreign model's primary key.

	// The default, using the above example
	'foreign' => 'author.post:primary_key',
	
**`convert_empty`** — This defaults to TRUE, unlike most other fields. Empty values are converted to the value set for `empty_value`, which defaults to `0`.

**`empty_value`** — This is the value that empty values are converted to. The default is `0`.
	
**Using this relationship**

	$post = Jelly::factory('post', 1);
	
	// Access the author's name
	echo $post->author->name;
	
	// Change the author
	$post->author = Jelly::factory('author', 1);
	$post->save();
	
	// Remove the relationship
	$post->author = NULL;
	$post->save();

________________

#### `Jelly_Field_HasMany` (1:many)

This relationship is essentially the reverse of a `belongs_to` relationship. Here, a model has many of another model.

Following the `belongs_to` example: an author has many posts. In the database,
the `posts` table would have an `author_id` column that contains the primary
key of the author that owns the post.

	// In Model_Author::initialize()
	'posts' => new Jelly_Field_HasMany(array(
		'foreign' => 'post',
	)),
	
**Properties**
	
**`foreign`** — The model that this field has many of. You can also pass a field to use as the foreign model's foreign key.

	// The default, using the above example
	'foreign' => 'post.author:foreign_key',
	
**`default`** — This works slightly differently than other fields. Default is the value that will be set on the foreign model's column when a relationship is removed. This should almost always remain 0.
	
**`convert_empty`** — This defaults to TRUE, unlike most other fields. Empty values are converted to the value set for `empty_value`, which defaults to `0`.

**`empty_value`** — This is the default value that empty values are converted to. The default is `0`.
	
**Using this relationship**

	$author = Jelly::factory('author', 1);
	
	// Access all posts
	foreach ($author->posts as $post)
	{
		echo $post->name;
	}
	
	// Retrieve only published posts
	$posts = $author->get('posts')->where('status', '=', 'published')->select();

	// Change all posts
	$author->posts = array($post1, $post2, $post3);

	// Remove the relationship
	$author->posts = NULL;
	$author->save();
	
[!!] See `add()`, `remove()`, and `has()` below for more examples
	
________________

#### `Jelly_Field_HasOne` (1:1)

This is exactly the same as `has_many` with the exception that Jelly ensures that the model can only *have* one other model, instead of many.

	// In Model_Author::initialize()
	'posts' => new Jelly_Field_HasOne(array(
		'foreign' => 'post.author:foreign_key',
	)),

**Properties**
	
**`foreign`** — The model that this field has many of. You can also pass a field to use as the foreign model's foreign key.

	// The default, using the above example
	'foreign' => 'post.author:foreign_key',

**`convert_empty`** — This defaults to TRUE, unlike most other fields. Empty values are converted to the value set for `empty_value`, which defaults to `0`.

**`empty_value`** — This is the default value that empty values are converted to. The default is `array()`.

**Using this relationship**

	$author = Jelly::factory('author', 1);

	// Access the post's name
	$author->post->name;

	// Retrieve the post only if it's published
	$author->get('post')->where('status', '=', 'published')->select();

	// Delete the post
	$author->get('post')->delete();

	// Remove the relationship
	$author->post = NULL;
	$author->save();

________________

#### `Jelly_Field_ManyToMany` (many:many)

This connects two models so that each can have many of each other.

For example, a blog post might have many tags, but a particular tag might
belong to many different blog posts.

This relationship requires a `through` table that connects the two models.

	// In Model_Post::initialize()
	'tags' => new Jelly_Field_ManyToMany(array(
		'foreign' => 'tag',
		'through' => 'posts_tags',
	)),
	
**Properties**

**`foreign`** — The model that this field has many of. You can also pass a field to use as the foreign model's primary key.

	// The default, using the above example
	'foreign' => 'tag.tag:primary_key',
	
**`through`** — The table or model to use as the connector table. You can also pass an array of fields to use for connecting the two primary keys. Unlike `foreign`, the model can actually point to a table, and does not need to point to an actual model.

	// The default, using the above example
	'through' => array(
		'model' => 'posts_tags',
		'fields' => array('post:foreign_key', 'tag:foreign_key'),
	),
	
**Using this relationship**

	$post = Jelly::factory('post', 1);

	// Access all tags
	foreach ($post->tags as $tag)
	{
		echo $tag->name;
	}

	// Delete all related tags
	$post->get('tags')->delete();
	
	// Change the tags
	$post->tags = Jelly::select('tag')->where('id', 'IN', $tags)->select();
	$post->save();

	// Remove the tags
	$post->tags = NULL;
	$post->save();
	
[!!] See `add()`, `remove()`, and `has()` below for more examples

### A general note on getting and setting relationships

#### Getting

You may have noticed in the examples above that sometimes we use the object property syntax (`$model->field`) for retrieving a relationship and other times we use `$model->get('field')`.

The difference is that the object property syntax returns the relationship already `select()`ed, whereas `get()` returns a query builder that you can work with:

	// Here, a Jelly_Collection of models is returned
	$author->posts;
	
	// Here, a Jelly_Builder is returned
	$author->get('posts');
	
With `get()` you can add extra clauses to the query before you `select()` it, or you could actually perform a `delete()` or `update()` on the entire lot, if needed.

________________

#### Setting

Relationships are very flexible in the types of data you can pass to it. For `n:1` relationships, you can pass the following:

 * **A primary key**: e.g. `$post->author = 1;`
 * **A loaded() model**: e.g. `$post->author = Jelly::factory('author', 1);`
	
For `n:many` relationships, you can pass the following:

 * **An array of primary keys**: e.g. `$author->posts = array(1, 3, 5);`
 * **An array of models**: e.g. `$author->posts = array($model1, $model2, $model3);`
 * **A query result**: e.g. `$author->posts = Jelly::query('post')->select();`

### `add()` and `remove()`

These two methods offer fine-grained control over `n:many` relationships. You can use them to add or remove individual models from a relationship.

	// Assume this author has posts 1, 5, and 7
	$author = Jelly::factory('author', 1);
	
	// Remove post 1
	$author->remove('posts', 1);
	
	// Remove post 5
	$author->remove('posts', Jelly::factory('post', 5));
	
	// Make the changes permanent
	$author->save();
	
	// Add back post 1 and 5
	$author->add('posts', Jelly::query('post')->where('id', 'IN', array(1, 5))->select());

As you can see, `add()` and `remove()` support passing all of the different types of data outlined above.

### `has()`

Has is useful to determine if a model has a relationship to a particular model or set of models. Like `add()` and `remove()`, this method only works on `n:many` relationships.

	// Assume this author has posts 1, 5, and 7
	$author = Jelly::factory('author', 1);

	// Returns TRUE
	$author->has('posts', 1);

	// Returns FALSE, since the author doesn't have post 8
	$author->has('posts', array(1, 5, 7, 8));
	
	// Returns TRUE
	$author->has('posts', Jelly::factory('post', 1));

	// Returns TRUE
	$author->has('posts', Jelly::query('post')->where('id', 'IN', array(1, 5))->select());
	
Once again, `has()` supports passing all of the different types of data outlined above.