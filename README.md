Jelly is a nice little ORM for Kohana. It is currently in beta.

 * [View the user guide](http://jelly.jonathan-geiger.com/docs/jelly.getting-started)
 * [View the API reference](http://jelly.jonathan-geiger.com/docs/api/Jelly)
 * [Report an issue or feature request](http://github.com/jonathangeiger/kohana-jelly/issues)
 * [Run the unit tests](http://github.com/jonathangeiger/jelly-tests)
 
## Notable Features

* **Standard support for all of the common relationships** — This includes
  `belongs_to`, `has_many`, and `many_to_many`. Pretty much standard these
  days.

* **Top-to-bottom table column aliasing** – All references to database columns
  and tables are made via their aliased names and converted transparently, on
  the fly.

* **Active testing on MySQL and SQLite** — All of the Jelly unit tests work
  100% correctly on both MySQL, SQLite and PostgresSQL databases.

* **A built-in query builder** — This features is a near direct port from
  Kohana's native ORM. I find its usage much simpler than Sprig's.

* **Extensible field architecture** — All fields in a model are represented by
  a `Field_*` class, which can be easily overridden and created for custom
  needs. Additionally, fields can implement behaviors that let the model know
  it has special ways of doing things.

* **No circular references** — Fields are well-designed to prevent the
  infinite loop problem that sometimes plagues Sprig. It's even possible to
  have same-table child/parent references out of the box without intermediate
  models.