# Getting Started

This is the documentation for Jelly, an ORM for Kohana 3.0.

First off, if you're already feeling lost feel free to ask a question in [the official forums](http://dev.kohanaframework.org/projects/jelly/boards)—we're all very nice and helpful. If you feel better looking at the source, you can always [view the API documentation](http://jelly.jonathan-geiger.com/docs/api/Jelly) or [browse the source on Github](http://github.com/jonathangeiger/kohana-jelly).

## Installation

To install Jelly simply [download the latest release](http://github.com/jonathangeiger/kohana-jelly) and place it in your modules directory. After that you must edit your `application/bootstrap.php` file and modify the call to `Kohana::modules` to include the Jelly module:

	Kohana::modules(array(
	    ...
	    'database' => MODPATH.'database',
		'jelly'    => MODPATH.'jelly',
	    ...
	));
	
Notice that Jelly depends on Kohana 3.x's [database module](http://github.com/kohana/database). Make sure you install and configure that as well.

## Upgrading

If you're upgrading Jelly you may want to check out [the changelog](jelly.upgrading) to see if any API changes have occurred since you last updated.

## Basic Usage

The basic operations needed to work with Jelly are:

1.  [Defining models](jelly.defining-models)
2.  [Loading and listing records](jelly.loading-and-listing)
3.  [Creating, updating and deleting records](jelly.cud)
4.  [Accessing and managing relationships](jelly.relationships)

## More Advanced Use

Jelly is incredibly flexible with almost all aspects of its behavior
being transparently extendable. The guides below give an overview of some more
advanced usage.

1.  [Extending the query builder](jelly.extending-builder)
2.  [Defining custom fields](jelly.extending-field)