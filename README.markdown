# EasyDB

DB is another database abstraction layer class.  It makes interacting with a database simple and easy.  It handles connections automatically so all you have to do is worry about running queries and using the built in functions.

## Configuration

There are a few configuration settings that will need to be adjusted for individual setups.  These settings are kept in php [definitions](http://php.net/define) at the start of the file.

The most important settings to get you started are the connection settings and are at the very start of the file.  Adjust these to suit your environment.

	define('DB_HOST','localhost');
	define('DB_USER','your_username');
	define('DB_PASS','your_password');
	define('DB_NAME','database_name');

Other settings are discussed in the wiki for this project.

## Security

All methods that take a data array and a where array automically escape the data contained in those arrays with the [mysql_string_real_escape()](http://php.net/mysql_string_real_escape) function.

Multiple queries cannot be executed at the same time (unless the configuration settings are adjusted).  This prevents queries like:

	SELECT * FROM employees WHERE name = '0'; TRUNCATE employees;

If you have neglected to check user-given data.

If the DELETE_SAFETY and UPDATE_SAFETY configuration options are set then and empty where definition will throw and error.  This prevents deleting or updating an entire table.

## Usage

### Initialization

The class is setup in the following way:

	require_once 'class.easydb.php';
	$db = new EasyDB;

### Getting data

To select data from the table you can use three methods.  getRows(), getRow() or read().

	$where = array(
	   'fname' => 'Mary'
	);

	// Return an array of objects (each object being a row)
	$rs = $db->getRows('employees', $where);
	$rs = $db->read('SELECT * FROM employees WHERE name = \'Jack\';');

	// Return a single object (read: row)
	$employee = $db->getRow('employees', $where);

These methods return either and array of objects (read: rows) or a single object (row) for getRow().  Note that to return specific fields (e.g. only 'fname'), the read() method has to be used.  Queries in the read method do not get sanitized before execution.

### Inserting data

Data is inserted using the insert() method specifying the table name and an associative array of data to be inserted.  The keys in this associative array must match with the fields in the table.

	$insert = array(
	    'fname'  => 'Jackk',
	    'lname'  => 'Herer',
	    'emp_id' => 420
	);

	$id = $db->insert('employees', $insert);

This escapes all the data in the array and inserts it into the employees table.  It returns the value of [mysql_insert_id()](http://php.net/mysql_insert_id).

### Updating data

You can update data in much the same way, but adding an extra array containing the where argument (the where data is sanitized as well).

	$update = array(
	    'fname' => 'Jack'
	);

	$where = array(
	    'emp_id' => 420
	);

	$db->update('employees',$update,$where);

A boolean value is returned depending on whether the query failed or not.  If UPDATE_SAFETY is set in the configuration, and empty where statement throws and error disalllowing you to update the 'fname' or all rows to 'Jack'.  Read more in the wiki.

### Deleting data

Deleting data is more of the same.

	$where = array(
	    'emp_id' => 50
	);

	$db->delete('employees', $where);

Again the return is a boolean value and there is a DELETE_SAFETY setting.

### Checking for existing data

You can check for existing data with the find() method.  However your tables id field needs to be called 'id' not 'employee_id' or 'user_id' for instance.

	$where = (
	    'fname' => 'Jack',
	    'lname' => 'Herer'
	);

	$id = $db->find('employees', $where);

	if ($id) {
		echo 'Jack is already in the database under ID ' . $id;
	}

## Contact

Problems, comments, and suggestions all welcome at: [hamstar@telescum.co.nz](mailto:hamstar@telescum.co.nz)
