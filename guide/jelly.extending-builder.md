# Extending the Query Builder

One of the key factors of MVC is to keep all business logic encapsulated and
in one place. Jelly's query-based listing interface is extremely natural and
flexible, however you don't want to have to construct your listing logic in the
controller.

Jelly provides a very clean solution to this by allowing you to extend the
Jelly query builder on a per-model basis. For example, if you need to define
an 'active' user as one who has logged in in the last 3 months, you could do
something like this:

	$active_users = Jelly::select('user')->where('last_login', '>', strtotime('-3 month'))->execute();

But you don't want to put this directly in your controller as it is business
logic. Moreover, if you want to change your definition of 'active' later, you
will need to search through your code and update loads of queries.

Instead you can encapsulate this logic by creating a class named
`Model_Builder_User` that extends `Jelly_Builder`. If Jelly finds a class
named in such a way, it will always return that instead of the default
`Jelly_Builder` for any query built based on the `User` model.

You can now add any listing logic you like to your query building. You can
even change the default query builder behavior to account for quirks in a
particular model. 

Our active users example before is solved thus:

	class Model_Builder_User extends Jelly_Builder
	{
		public function active()
		{
			return $this->where('last_login', '>', strtotime('-3 month'));
		}
	}

	// Now we can do this
	$active_users = Jelly::select('user')->active()->execute();

We can also now chain our custom defined methods if necessary.

You could even take this further: say several of your models require some of
the same additional logic. In this case, you could define an abstract builder
class that extends `Jelly_Builder` and then you could extend that in turn in
your 'Model_Builder_ModelName` classes.

[!!] **Note**: Any model with no corresponding builder class will simply use the basic query building of `Jelly_Builder`.