# Getting Started

**Jelly is currently in a pre-1.0 state and under heavy development. Please
bear in mind that the API is not guaranteed to be stable until a 1.0 release.
Please report any bugs or feature requests you have in the module, or
documentation to
[github](http://github.com/jonathangeiger/kohana-jelly/issues).**

Jelly is built around a hybrid of ActiveRecord and DataMapper patterns. Jelly
model instances follow the active record pattern but actually do as little as
possible and are as small as possible. All loading and listing of models is
achieved through a natural extension of Kohana's own query builder which
automatically handles model and field aliasing as well as relationship
handling.

The basic operations needed to work with Jelly are:

1.  [Defining models](jelly.defining-models)
2.  [Loading and listing records](jelly.loading-and-listing)
3.  [Creating, updating and deleting records](jelly.cud)
4.  [Accessing and managing relationships](jelly.relationships)

## More Advanced Use

Jelly is incredibly flexible with almost all aspects of it's behavior
transparently extendable. The guides below give an overview of some more
advanced usage.

1.  [Extending the query builder](jelly.extending-builder)
2.  [Defining custom fields](jelly.extending-field)