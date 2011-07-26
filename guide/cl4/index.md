# cl4

cl4 extends the existing Kohana ORM and other built-in classes to provide additional tools that facilitate the creation of dynamic websites.

Here is a quick summary of how you might want to use cl4:

* use ORM model additional features to:
  * generate flexible HTML forms from ORM models (one-line fully featured forms, including relationships, multiple record edits, select fields, date pickers, etc.)
  * generate flexible HTML tables of your data from ORM models (one-line)
  * update / insert / delete your data using the expanded model features (one line save for multiple records, many-to-many, foreign, etc.)
  * generate stand-alone admin panes from models that include a full-featured list of records with pagination, search, add, edit, etc.
* create HTML tables with the new htmltable class
* use Form to create a wide range of common form elements
* use additional cl4 helper functions that are not available in Kohana to facilitate:
  * uploading and manipulating files
  * writing and reading CSV files
  * writing and reading XML files
  * emailing
  * and more
* include the cl4auth, cl4admin, and cl4base modules to accelerate the development of a custom web application with built-in content-management and authentication

ORM is included with the Kohana 3.x install but needs to be enabled before you can use it. In your `application/bootstrap.php` file modify the call to Kohana::modules and include the ORM modules.

## Getting started

Before we use cl4, we must install and enable the modules required.

### Get the cl4 module(s)

The latest module code can be obtained from repository on github:

* [http://github.com/claerosystems/cl4](http://github.com/claerosystems/cl4)

The optional modules can also be found on github:

* [http://github.com/claerosystems/cl4auth](http://github.com/claerosystems/cl4auth) provides an authentication module based on cl4
* [http://github.com/claerosystems/cl4admin](http://github.com/claerosystems/cl4admin) provides an admin database content admin interface
* [http://github.com/claerosystems/cl4base](http://github.com/claerosystems/cl4base) provides a base web site including auth and admin

If you are using git for your project you can also add these modules as submodules within your project to facilitate upgrades:

	#add cl4 modules
	git submodule add git@github.com:claerosystems/cl4.git modules/cl4
	git submodule add git@github.com:claerosystems/cl4auth.git modules/cl4auth
	git submodule add git@github.com:claerosystems/cl4base.git modules/cl4base
	git submodule add git@github.com:claerosystems/cl4admin.git modules/cl4admin
	git submodule init

	#checkout the latest cl4
	echo "-- module > cl4" && cd ../cl4 && git checkout master && git pull && git checkout master && echo "-- module > cl4admin" && cd ../cl4admin && git checkout master && git pull && git checkout master && echo "-- module > cl4base" && cd ../cl4base && git checkout master && git pull && git checkout master && echo "-- module > cl4auth" && cd ../cl4auth && git checkout master && git pull && git checkout master

### Enable the module(s)

	Kohana::modules(array(
		...
		'cl4' => MODPATH.'cl4',
		'cl4auth' => MODPATH.'cl4auth', // optional
		'cl4admin' => MODPATH.'cl4admin', // optional
		'cl4basae' => MODPATH.'cl4base', // optional
		'database' => MODPATH.'database', // required
		'orm' => MODPATH.'orm', // required
		...
	));

[!!] The ORM and database modules are required for the cl4 module to work. Of course the database module also has to be configured to use an existing database.

You can now use the [helper classes](classes), create your [models](models), and use the [cl4 ORM](orm) and [cl4 MultiORM](multiorm) classes.

## Creating cl4 models

In order to customize the functionality of the model-based features in cl4, you can add additional information to your Kohana ORM model files.  These customizations should not impact the use of these models with the built-in ORM features.

