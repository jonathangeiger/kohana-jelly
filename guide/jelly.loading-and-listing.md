# Finding Records

Each model has a `Jelly_Builder` attached to it that is used for all query
operations. Models can choose to use the stock `Jelly_Builder` or to override it.

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
	// A Model_Post instance is returned directly
	$post = Jelly::query('post', 1)->select();
	
	// We don't have to iterate since a model is returned directly
	if ($post->loaded())
	{
		echo "Post #{$post->id} is loaded";
	}
	
	
### Conditions

Rather than defining conditions using SQL fragments we chain methods named similarly to SQL. This is where Kohana's Database Query Builder will seem very familiar.

	// Find all published posts, ordered by publish date
	$posts = Jelly::query('post')
	              ->where('status', '=', 'published')
	              ->order_by('publish_date', 'ASC')
	              ->select();

If you want to load many records, you end your query building with the
`execute()` method, which returns a `Jelly_Collection`. A `Jelly_Collection` contains a
collection of records that, when iterated over returns individual models for
you to work with.

[!!] **Note**: A `Jelly_Collection` has the same API as a `Database_Result`, except it returns Jelly models

##### Example - Finding many records

	// Find every single post
	$posts = Jelly::select('post')->execute();

	foreach ($posts as $post)
	{
		echo $post->name;
	}

[!!] **Note**: Whenever you `limit()` a query to 1, `execute()` returns the model instance directly, instead of returning a `Jelly_Collection`

##### *So what's the difference between load() and execute()*

There is a small, but significant difference between load() and execute(). load() implicitly limits the query to 1, and just returns a model directly. load() also accepts an optional unique_key to find the record by.

    // load() is essentially shorthand for the following:
    Jelly::select('post')->where(':unique_key', '=', $value)->limit(1)->execute();
    
Additionally, load() is only useful for SELECTs. It will have no effect on any other query types.

### Counting Records

At any time during a query builder chain, you can call the `count()` method to
find out how many records will be returned.

	$total_posts = Jelly::select('post')->where('published', '=', 1)->count();

### The Query Builder

If you're familiar with Kohana's query builder you already know how to use Jelly's.

	// Find all active posts
	$posts = Jelly::select('post')->where('published', '=', 1)->execute();

	// Load posts with their author in one query
	// This is possible since there is only 1 author for each post
	$posts = Jelly::select('post')->with('author')->execute();

[!!] **Note**: See [Extending the query builder](jelly.extending-builder) for more advanced building options

### Next [Creating, updating and deleting records](jelly.cud)

