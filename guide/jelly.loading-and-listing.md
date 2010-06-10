# Finding Records

Each model has a `Jelly_Builder` attached to it that is used for all query
operations. Models can choose to use the stock `Jelly_Builder` or to [extend Jelly_Builder](jelly.extending-builder) to add custom builder methods to their models.

### Finding records

Jelly has methods which allow you to grab a single record by key or a
collection of records matching conditions. The interface is very similar to
Kohana's Database Query Builder.

	// Find all posts. A Jelly_Collection of Model_Post's is returned.
	$posts = Jelly::query('post')->select();
	
	// Iterate over our posts to do stuff with them
	foreach($posts as $post)
	{
		if ($post->loaded())
		{
			echo "Post #{$post->id} is loaded";
		}
	}
	
	// Find the post with a unique_key of 1.
	$post = Jelly::query('post', 1)->select();
	
	// We don't have to iterate since a model is returned directly
	if ($post->loaded())
	{
		echo "Post #{$post->id} is loaded";
	}

To execute the query you end your query building with the `select()` method,
which returns a `Jelly_Collection`. A `Jelly_Collection` contains a collection
of records that, when iterated over returns individual models for you to work
with.

[!!] **Note**: Whenever you `limit()` a query to 1, `select()` returns the model instance directly, instead of returning a `Jelly_Collection`

### Conditions

Rather than defining conditions using SQL fragments we chain methods named similarly to SQL. This is where Kohana's Database Query Builder will seem very familiar.

	// Find all published posts, ordered by publish date
	$posts = Jelly::query('post')
	              ->where('status', '=', 'published')
	              ->order_by('publish_date', 'ASC')
	              ->select();

### Counting Records

At any time during a query builder chain, you can call the `count()` method to
find out how many records will be returned.

	$total_posts = Jelly::select('post')->where('published', '=', 1)->count();

### Next [Creating, updating and deleting records](jelly.cud)

