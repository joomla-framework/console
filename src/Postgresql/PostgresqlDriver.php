<?php
/**
 * Part of the Joomla Framework Database Package
 *
 * @copyright  Copyright (C) 2005 - 2015 Open Source Matters, Inc. All rights reserved.
 * @license    GNU General Public License version 2 or later; see LICENSE
 */

namespace Joomla\Database\Postgresql;

use Joomla\Database\DatabaseDriver;
use Joomla\Database\DatabaseQuery;
use Joomla\Database\Exception\ConnectionFailureException;
use Joomla\Database\Exception\ExecutionFailureException;
use Joomla\Database\Exception\UnsupportedAdapterException;
use Joomla\Database\Query\LimitableInterface;
use Joomla\Database\Query\PreparableInterface;
use Psr\Log;

/**
 * PostgreSQL Database Driver
 *
 * @since  1.0
 */
class PostgresqlDriver extends DatabaseDriver
{
	/**
	 * The database driver name
	 *
	 * @var    string
	 * @since  1.0
	 */
	public $name = 'postgresql';

	/**
	 * The character(s) used to quote SQL statement names such as table names or field names,
	 * etc. The child classes should define this as necessary.  If a single character string the
	 * same character is used for both sides of the quoted name, else the first character will be
	 * used for the opening quote and the second for the closing quote.
	 *
	 * @var    string
	 * @since  1.0
	 */
	protected $nameQuote = '"';

	/**
	 * The null or zero representation of a timestamp for the database driver.  This should be
	 * defined in child classes to hold the appropriate value for the engine.
	 *
	 * @var    string
	 * @since  1.0
	 */
	protected $nullDate = '1970-01-01 00:00:00';

	/**
	 * The prepared statement.
	 *
	 * @var    resource
	 * @since  __DEPLOY_VERSION__
	 */
	protected $prepared;

	/**
	 * Contains the current query execution status
	 *
	 * @var    array
	 * @since  __DEPLOY_VERSION__
	 */
	protected $executed = false;

	/**
	 * Contains the name of the prepared query
	 *
	 * @var    string
	 * @since  __DEPLOY_VERSION__
	 */
	protected $queryName = 'query';

	/**
	 * The minimum supported database version.
	 *
	 * @var    string
	 * @since  1.0
	 */
	protected static $dbMinimum = '8.3.18';

	/**
	 * Operator used for concatenation
	 *
	 * @var    string
	 * @since  1.0
	 */
	protected $concat_operator = '||';

	/**
	 * Query object returned by getQuery
	 *
	 * @var    PostgresqlQuery
	 * @since  1.0
	 */
	protected $queryObject = null;

	/**
	 * Database object constructor
	 *
	 * @param   array  $options  List of options used to configure the connection
	 *
	 * @since	1.0
	 */
	public function __construct( $options )
	{
		$options['host'] = (isset($options['host'])) ? $options['host'] : 'localhost';
		$options['user'] = (isset($options['user'])) ? $options['user'] : '';
		$options['password'] = (isset($options['password'])) ? $options['password'] : '';
		$options['database'] = (isset($options['database'])) ? $options['database'] : '';
		$options['port'] = (isset($options['port'])) ? $options['port'] : null;

		// Finalize initialization
		parent::__construct($options);
	}

	/**
	 * Database object destructor
	 *
	 * @since   1.0
	 */
	public function __destruct()
	{
		$this->disconnect();
	}

	/**
	 * Connects to the database if needed.
	 *
	 * @return  void  Returns void if the database connected successfully.
	 *
	 * @since   1.0
	 * @throws  \RuntimeException
	 */
	public function connect()
	{
		if ($this->connection)
		{
			return;
		}

		// Make sure the postgresql extension for PHP is installed and enabled.
		if (!static::isSupported())
		{
			throw new UnsupportedAdapterException('PHP extension pg_connect is not available.');
		}

		/*
		 * pg_connect() takes the port as separate argument. Therefore, we
		 * have to extract it from the host string (if povided).
		 */

		// Check for empty port
		if (!($this->options['port']))
		{
			// Port is empty or not set via options, check for port annotation (:) in the host string
			$tmp = substr(strstr($this->options['host'], ':'), 1);

			if (!empty($tmp))
			{
				// Get the port number
				if (is_numeric($tmp))
				{
					$this->options['port'] = $tmp;
				}

				// Extract the host name
				$this->options['host'] = substr($this->options['host'], 0, strlen($this->options['host']) - (strlen($tmp) + 1));

				// This will take care of the following notation: ":5432"
				if ($this->options['host'] == '')
				{
					$this->options['host'] = 'localhost';
				}
			}

			// No port annotation (:) found, setting port to default PostgreSQL port 5432
			else
			{
				$this->options['port'] = '5432';
			}
		}

		// Build the DSN for the connection.
		$dsn = "host={$this->options['host']} port={$this->options['port']} dbname={$this->options['database']} " .
			"user={$this->options['user']} password={$this->options['password']}";

		// Attempt to connect to the server.
		if (!($this->connection = @pg_connect($dsn)))
		{
			$this->log(Log\LogLevel::ERROR, 'Error connecting to PGSQL database.');

			throw new ConnectionFailureException('Error connecting to PGSQL database.');
		}

		pg_set_error_verbosity($this->connection, PGSQL_ERRORS_DEFAULT);
		pg_query($this->connection, 'SET standard_conforming_strings=off');
		pg_query($this->connection, 'SET escape_string_warning=off');
	}

	/**
	 * Disconnects the database.
	 *
	 * @return  void
	 *
	 * @since   1.0
	 */
	public function disconnect()
	{
		// Close the connection.
		if (is_resource($this->connection))
		{
			pg_close($this->connection);
		}

		$this->connection = null;
	}

	/**
	 * Method to escape a string for usage in an SQL statement.
	 *
	 * @param   string   $text   The string to be escaped.
	 * @param   boolean  $extra  Optional parameter to provide extra escaping.
	 *
	 * @return  string  The escaped string.
	 *
	 * @since   1.0
	 */
	public function escape($text, $extra = false)
	{
		$this->connect();

		$result = pg_escape_string($this->connection, $text);

		if ($extra)
		{
			$result = addcslashes($result, '%_');
		}

		return $result;
	}

	/**
	 * Test to see if the PostgreSQL connector is available
	 *
	 * @return  boolean  True on success, false otherwise.
	 *
	 * @since   1.0
	 * @deprecated  1.1.0  Use isSupported() instead
	 */
	public static function test()
	{
		return static::isSupported();
	}

	/**
	 * Determines if the connection to the server is active.
	 *
	 * @return	boolean
	 *
	 * @since	1.0
	 */
	public function connected()
	{
		$this->connect();

		if (is_resource($this->connection))
		{
			return pg_ping($this->connection);
		}

		return false;
	}

	/**
	 * Drops a table from the database.
	 *
	 * @param   string   $tableName  The name of the database table to drop.
	 * @param   boolean  $ifExists   Optionally specify that the table must exist before it is dropped.
	 *
	 * @return  boolean	true
	 *
	 * @since   1.0
	 * @throws  \RuntimeException
	 */
	public function dropTable($tableName, $ifExists = true)
	{
		$this->connect();

		$this->setQuery('DROP TABLE ' . ($ifExists ? 'IF EXISTS ' : '') . $this->quoteName($tableName));
		$this->execute();

		return true;
	}

	/**
	 * Get the number of affected rows for the previous executed SQL statement.
	 *
	 * @return  integer  The number of affected rows in the previous operation
	 *
	 * @since   1.0
	 */
	public function getAffectedRows()
	{
		$this->connect();

		return pg_affected_rows($this->cursor);
	}

	/**
	 * Method to get the database collation in use by sampling a text field of a table in the database.
	 *
	 * @return  mixed  The collation in use by the database or boolean false if not supported.
	 *
	 * @since   1.0
	 * @throws  \RuntimeException
	 */
	public function getCollation()
	{
		$this->connect();

		$this->setQuery('SHOW LC_COLLATE');
		$array = $this->loadAssocList();

		return $array[0]['lc_collate'];
	}

	/**
	 * Method to get the database connection collation, as reported by the driver. If the connector doesn't support
	 * reporting this value please return an empty string.
	 *
	 * @return  string
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	public function getConnectionCollation()
	{
		return pg_client_encoding($this->connection);
	}

	/**
	 * Get the number of returned rows for the previous executed SQL statement.
	 *
	 * @param   resource  $cur  An optional database cursor resource to extract the row count from.
	 *
	 * @return  integer   The number of returned rows.
	 *
	 * @since   1.0
	 */
	public function getNumRows($cur = null)
	{
		$this->connect();

		return pg_num_rows((int) $cur ? $cur : $this->cursor);
	}

	/**
	 * Get the current or query, or new DatabaseQuery object.
	 *
	 * @param   boolean  $new    False to return the last query set, True to return a new Query object.
	 * @param   boolean  $asObj  False to return last query as string, true to get Postgresql query object.
	 *
	 * @return  PostgresqlQuery  The current query object or a new object extending the Query class.
	 *
	 * @since   1.0
	 * @throws  \RuntimeException
	 */
	public function getQuery($new = false, $asObj = false)
	{
		if ($new)
		{
			// Make sure we have a query class for this driver.
			if (!class_exists('\\Joomla\\Database\\Postgresql\\PostgresqlQuery'))
			{
				throw new UnsupportedAdapterException('\\Joomla\\Database\\Postgresql\\PostgresqlQuery Class not found.');
			}

			$this->queryObject = new PostgresqlQuery($this);

			return $this->queryObject;
		}
		else
		{
			if ($asObj)
			{
				return $this->queryObject;
			}
			else
			{
				return $this->sql;
			}
		}
	}

	/**
	 * Shows the table CREATE statement that creates the given tables.
	 *
	 * This is unsuported by PostgreSQL.
	 *
	 * @param   mixed  $tables  A table name or a list of table names.
	 *
	 * @return  string  An empty string because this function is not supported by PostgreSQL.
	 *
	 * @since   1.0
	 * @throws  \RuntimeException
	 */
	public function getTableCreate($tables)
	{
		return '';
	}

	/**
	 * Retrieves field information about a given table.
	 *
	 * @param   string   $table     The name of the database table.
	 * @param   boolean  $typeOnly  True to only return field types.
	 *
	 * @return  array  An array of fields for the database table.
	 *
	 * @since   1.0
	 * @throws  \RuntimeException
	 */
	public function getTableColumns($table, $typeOnly = true)
	{
		$this->connect();

		$result = array();

		$tableSub = $this->replacePrefix($table);

		$this->setQuery('
			SELECT a.attname AS "column_name",
				pg_catalog.format_type(a.atttypid, a.atttypmod) as "type",
				CASE WHEN a.attnotnull IS TRUE
					THEN \'NO\'
					ELSE \'YES\'
				END AS "null",
				CASE WHEN pg_catalog.pg_get_expr(adef.adbin, adef.adrelid, true) IS NOT NULL
					THEN pg_catalog.pg_get_expr(adef.adbin, adef.adrelid, true)
				END as "Default",
				CASE WHEN pg_catalog.col_description(a.attrelid, a.attnum) IS NULL
				THEN \'\'
				ELSE pg_catalog.col_description(a.attrelid, a.attnum)
				END  AS "comments"
			FROM pg_catalog.pg_attribute a
			LEFT JOIN pg_catalog.pg_attrdef adef ON a.attrelid=adef.adrelid AND a.attnum=adef.adnum
			LEFT JOIN pg_catalog.pg_type t ON a.atttypid=t.oid
			WHERE a.attrelid =
				(SELECT oid FROM pg_catalog.pg_class WHERE relname=' . $this->quote($tableSub) . '
					AND relnamespace = (SELECT oid FROM pg_catalog.pg_namespace WHERE
					nspname = \'public\')
				)
			AND a.attnum > 0 AND NOT a.attisdropped
			ORDER BY a.attnum'
		);

		$fields = $this->loadObjectList();

		if ($typeOnly)
		{
			foreach ($fields as $field)
			{
				$result[$field->column_name] = preg_replace("/[(0-9)]/", '', $field->type);
			}
		}
		else
		{
			foreach ($fields as $field)
			{
				if (stristr(strtolower($field->type), "character varying"))
				{
					$field->Default = "";
				}

				if (stristr(strtolower($field->type), "text"))
				{
					$field->Default = "";
				}

				// Do some dirty translation to MySQL output.
				// @todo: Come up with and implement a standard across databases.
				$result[$field->column_name] = (object) array(
					'column_name' => $field->column_name,
					'type' => $field->type,
					'null' => $field->null,
					'Default' => $field->Default,
					'comments' => '',
					'Field' => $field->column_name,
					'Type' => $field->type,
					'Null' => $field->null,
					// @todo: Improve query above to return primary key info as well
					// 'Key' => ($field->PK == '1' ? 'PRI' : '')
				);
			}
		}

		/* Change Postgresql's NULL::* type with PHP's null one */
		foreach ($fields as $field)
		{
			if (preg_match("/^NULL::*/", $field->Default))
			{
				$field->Default = null;
			}
		}

		return $result;
	}

	/**
	 * Get the details list of keys for a table.
	 *
	 * @param   string  $table  The name of the table.
	 *
	 * @return  array  An array of the column specification for the table.
	 *
	 * @since   1.0
	 * @throws  \RuntimeException
	 */
	public function getTableKeys($table)
	{
		$this->connect();

		// To check if table exists and prevent SQL injection
		$tableList = $this->getTableList();

		if ( in_array($table, $tableList) )
		{
			// Get the details columns information.
			$this->setQuery('
				SELECT indexname AS "idxName", indisprimary AS "isPrimary", indisunique  AS "isUnique",
					CASE WHEN indisprimary = true THEN
						( SELECT \'ALTER TABLE \' || tablename || \' ADD \' || pg_catalog.pg_get_constraintdef(const.oid, true)
							FROM pg_constraint AS const WHERE const.conname= pgClassFirst.relname )
					ELSE pg_catalog.pg_get_indexdef(indexrelid, 0, true)
					END AS "Query"
				FROM pg_indexes
				LEFT JOIN pg_class AS pgClassFirst ON indexname=pgClassFirst.relname
				LEFT JOIN pg_index AS pgIndex ON pgClassFirst.oid=pgIndex.indexrelid
				WHERE tablename=' . $this->quote($table) . ' ORDER BY indkey'
			);
			$keys = $this->loadObjectList();

			return $keys;
		}

		return false;
	}

	/**
	 * Method to get an array of all tables in the database.
	 *
	 * @return  array  An array of all the tables in the database.
	 *
	 * @since   1.0
	 * @throws  \RuntimeException
	 */
	public function getTableList()
	{
		$this->connect();

		$query = $this->getQuery(true);
		$query->select('table_name')
			->from('information_schema.tables')
			->where('table_type=' . $this->quote('BASE TABLE'))
			->where(
				'table_schema NOT IN (' . $this->quote('pg_catalog') . ', ' . $this->quote('information_schema') . ')'
			)
			->order('table_name ASC');

		$this->setQuery($query);
		$tables = $this->loadColumn();

		return $tables;
	}

	/**
	 * Get the details list of sequences for a table.
	 *
	 * @param   string  $table  The name of the table.
	 *
	 * @return  array  An array of sequences specification for the table.
	 *
	 * @since   1.0
	 * @throws  \RuntimeException
	 */
	public function getTableSequences($table)
	{
		$this->connect();

		// To check if table exists and prevent SQL injection
		$tableList = $this->getTableList();

		if ( in_array($table, $tableList) )
		{
			$name = array('s.relname', 'n.nspname', 't.relname', 'a.attname', 'info.data_type',
							'info.minimum_value', 'info.maximum_value', 'info.increment', 'info.cycle_option');
			$as = array('sequence', 'schema', 'table', 'column', 'data_type',
							'minimum_value', 'maximum_value', 'increment', 'cycle_option');

			if (version_compare($this->getVersion(), '9.1.0') >= 0)
			{
				$name[] .= 'info.start_value';
				$as[] .= 'start_value';
			}

			// Get the details columns information.
			$query = $this->getQuery(true);
			$query->select($this->quoteName($name, $as))
				->from('pg_class AS s')
				->leftJoin("pg_depend d ON d.objid=s.oid AND d.classid='pg_class'::regclass AND d.refclassid='pg_class'::regclass")
				->leftJoin('pg_class t ON t.oid=d.refobjid')
				->leftJoin('pg_namespace n ON n.oid=t.relnamespace')
				->leftJoin('pg_attribute a ON a.attrelid=t.oid AND a.attnum=d.refobjsubid')
				->leftJoin('information_schema.sequences AS info ON info.sequence_name=s.relname')
				->where("s.relkind='S' AND d.deptype='a' AND t.relname=" . $this->quote($table));
			$this->setQuery($query);
			$seq = $this->loadObjectList();

			return $seq;
		}

		return false;
	}

	/**
	 * Get the version of the database connector.
	 *
	 * @return  string  The database connector version.
	 *
	 * @since   1.0
	 */
	public function getVersion()
	{
		$this->connect();
		$version = pg_version($this->connection);

		return $version['server'];
	}

	/**
	 * Method to get the auto-incremented value from the last INSERT statement.
	 * To be called after the INSERT statement, it's MANDATORY to have a sequence on
	 * every primary key table.
	 *
	 * To get the auto incremented value it's possible to call this function after
	 * INSERT INTO query, or use INSERT INTO with RETURNING clause.
	 *
	 * @example with insertid() call:
	 *		$query = $this->getQuery(true);
	 *		$query->insert('jos_dbtest')
	 *				->columns('title,start_date,description')
	 *				->values("'testTitle2nd','1971-01-01','testDescription2nd'");
	 *		$this->setQuery($query);
	 *		$this->execute();
	 *		$id = $this->insertid();
	 *
	 * @example with RETURNING clause:
	 *		$query = $this->getQuery(true);
	 *		$query->insert('jos_dbtest')
	 *				->columns('title,start_date,description')
	 *				->values("'testTitle2nd','1971-01-01','testDescription2nd'")
	 *				->returning('id');
	 *		$this->setQuery($query);
	 *		$id = $this->loadResult();
	 *
	 * @return  integer  The value of the auto-increment field from the last inserted row.
	 *
	 * @since   1.0
	 */
	public function insertid()
	{
		$this->connect();
		$insertQuery = $this->getQuery(false, true);
		$table = $insertQuery->insert->getElements();

		/* find sequence column name */
		$colNameQuery = $this->getQuery(true);
		$colNameQuery->select('column_default')
			->from('information_schema.columns')
			->where(
				"table_name=" . $this->quote(
					$this->replacePrefix(str_replace('"', '', $table[0]))
				), 'AND'
			)
			->where("column_default LIKE '%nextval%'");

		$this->setQuery($colNameQuery);
		$colName = $this->loadRow();
		$changedColName = str_replace('nextval', 'currval', $colName);

		$insertidQuery = $this->getQuery(true);
		$insertidQuery->select($changedColName);
		$this->setQuery($insertidQuery);
		$insertVal = $this->loadRow();

		return $insertVal[0];
	}

	/**
	 * Locks a table in the database.
	 *
	 * @param   string  $tableName  The name of the table to unlock.
	 *
	 * @return  PostgresqlDriver  Returns this object to support chaining.
	 *
	 * @since   1.0
	 * @throws  \RuntimeException
	 */
	public function lockTable($tableName)
	{
		$this->transactionStart();
		$this->setQuery('LOCK TABLE ' . $this->quoteName($tableName) . ' IN ACCESS EXCLUSIVE MODE')->execute();

		return $this;
	}

	/**
	 * Execute the SQL statement.
	 *
	 * @return  mixed  A database cursor resource on success, boolean false on failure.
	 *
	 * @since   1.0
	 * @throws  \RuntimeException
	 */
	public function execute()
	{
		$this->connect();

		// Take a local copy so that we don't modify the original query and cause issues later
		$sql = $this->replacePrefix((string) $this->sql);


		if (!($this->sql instanceof LimitableInterface) && ($this->limit > 0 || $this->offset > 0))
		{
			$sql .= ' LIMIT ' . $this->limit . ' OFFSET ' . $this->offset;
		}

		$count = $this->getCount();

		// Increment the query counter.
		$this->count++;

		// If debugging is enabled then let's log the query.
		if ($this->debug)
		{
			// Add the query to the object queue.
			$this->log(
				Log\LogLevel::DEBUG,
				'{sql}',
				array('sql' => $sql, 'category' => 'databasequery', 'trace' => debug_backtrace())
			);
		}

		// Reset the error values.
		$this->errorNum = 0;
		$this->errorMsg = '';

		// Bind the variables
		if ($this->sql instanceof PreparableInterface && !($this->sql instanceof LimitableInterface))
		{
			$bounded =& $this->sql->getBounded();

			if (count($bounded))
			{
				// Execute the query. Error suppression is used here to prevent warnings/notices that the connection has been lost.
				$this->cursor = @pg_execute($this->connection, $this->queryName . $count, array_values($bounded));
			}
			else
			{
				// Execute the query. Error suppression is used here to prevent warnings/notices that the connection has been lost.
				$this->cursor = @pg_query($this->connection, $sql);
			}
		}
		else
		{
			// Execute the query. Error suppression is used here to prevent warnings/notices that the connection has been lost.
			$this->cursor = @pg_query($this->connection, $sql);
		}

		// If an error occurred handle it.
		if (!$this->cursor)
		{
			// Check if the server was disconnected.
			if (!$this->connected())
			{
				try
				{
					// Attempt to reconnect.
					$this->connection = null;
					$this->connect();
				}
				catch (ConnectionFailureException $e)
				// If connect fails, ignore that exception and throw the normal exception.
				{
					// Get the error number and message.
					$this->errorMsg = pg_last_error($this->connection);

					if ($this->cursor === false)
					{
						throw new ExecutionFailureException($this->sql, $this->errorMsg);
					}

					$this->errorNum = (int) pg_result_error_field($this->cursor, PGSQL_DIAG_SQLSTATE);

					// Throw the normal query exception.
					$this->log(
						Log\LogLevel::ERROR,
						'Database query failed (error #{code}): {message}; Failed query: {sql}',
						array('code' => $this->errorNum, 'message' => $this->errorMsg, 'sql' => $sql)
					);

					throw new ExecutionFailureException($sql, $this->errorMsg, $this->errorNum);
				}

				// Since we were able to reconnect, run the query again.
				return $this->execute();
			}
			else
			// The server was not disconnected.
			{
				// Get the error number and message.
				$this->errorNum = (int) pg_result_error_field($this->cursor, PGSQL_DIAG_SQLSTATE);
				$this->errorMsg = pg_last_error($this->connection);

				// Throw the normal query exception.
				$this->log(
					Log\LogLevel::ERROR,
					'Database query failed (error #{code}): {message}; Failed query: {sql}',
					array('code' => $this->errorNum, 'message' => $this->errorMsg, 'sql' => $sql)
				);

				throw new ExecutionFailureException($sql, $this->errorMsg, $this->errorNum);
			}
		}

		return $this->cursor;
	}

	/**
	 * Renames a table in the database.
	 *
	 * @param   string  $oldTable  The name of the table to be renamed
	 * @param   string  $newTable  The new name for the table.
	 * @param   string  $backup    Not used by PostgreSQL.
	 * @param   string  $prefix    Not used by PostgreSQL.
	 *
	 * @return  PostgresqlDriver  Returns this object to support chaining.
	 *
	 * @since   1.0
	 * @throws  \RuntimeException
	 */
	public function renameTable($oldTable, $newTable, $backup = null, $prefix = null)
	{
		$this->connect();

		// To check if table exists and prevent SQL injection
		$tableList = $this->getTableList();

		// Origin Table does not exist
		if ( !in_array($oldTable, $tableList) )
		{
			// Origin Table not found
			throw new \RuntimeException('Table not found in Postgresql database.');
		}
		else
		{
			/* Rename indexes */
			$this->setQuery(
				'SELECT relname
					FROM pg_class
					WHERE oid IN (
						SELECT indexrelid
						FROM pg_index, pg_class
						WHERE pg_class.relname=' . $this->quote($oldTable, true) . '
						AND pg_class.oid=pg_index.indrelid );'
			);

			$oldIndexes = $this->loadColumn();

			foreach ($oldIndexes as $oldIndex)
			{
				$changedIdxName = str_replace($oldTable, $newTable, $oldIndex);
				$this->setQuery('ALTER INDEX ' . $this->escape($oldIndex) . ' RENAME TO ' . $this->escape($changedIdxName));
				$this->execute();
			}

			/* Rename sequence */
			$this->setQuery(
				'SELECT relname
					FROM pg_class
					WHERE relkind = \'S\'
					AND relnamespace IN (
						SELECT oid
						FROM pg_namespace
						WHERE nspname NOT LIKE \'pg_%\'
						AND nspname != \'information_schema\'
					)
					AND relname LIKE \'%' . $oldTable . '%\' ;'
			);

			$oldSequences = $this->loadColumn();

			foreach ($oldSequences as $oldSequence)
			{
				$changedSequenceName = str_replace($oldTable, $newTable, $oldSequence);
				$this->setQuery('ALTER SEQUENCE ' . $this->escape($oldSequence) . ' RENAME TO ' . $this->escape($changedSequenceName));
				$this->execute();
			}

			/* Rename table */
			$this->setQuery('ALTER TABLE ' . $this->escape($oldTable) . ' RENAME TO ' . $this->escape($newTable));
			$this->execute();
		}

		return true;
	}

	/**
	 * Selects the database, but redundant for PostgreSQL
	 *
	 * @param   string  $database  Database name to select.
	 *
	 * @return  boolean  Always true
	 *
	 * @since   1.0
	 */
	public function select($database)
	{
		return true;
	}

	/**
	 * Sets the SQL statement string for later execution.
	 *
	 * @param   DatabaseQuery|string  $query   The SQL statement to set either as a DatabaseQuery object or a string.
	 * @param   integer               $offset  The affected row offset to set.
	 * @param   integer               $limit   The maximum affected rows to set.
	 *
	 * @return  PostgresqlDriver  This object to support method chaining.
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	public function setQuery($query, $offset = null, $limit = null)
	{
		$this->connect();

		$this->freeResult();

		if (is_string($query))
		{
			// Allows taking advantage of bound variables in a direct query:
			$query = $this->getQuery(true)->setQuery($query);
		}

		if ($query instanceof LimitableInterface && !is_null($offset) && !is_null($limit))
		{
			$query->setLimit($limit, $offset);
		}

		$sql = $this->replacePrefix((string) $query);

		$this->prepared = pg_prepare($this->connection, $this->queryName . $this->getCount(), $sql);

		// Store reference to the DatabaseQuery instance
		return parent::setQuery($query, $offset, $limit);
	}

	/**
	 * Custom settings for UTF support
	 *
	 * @return  integer  Zero on success, -1 on failure
	 *
	 * @since   1.0
	 */
	public function setUtf()
	{
		$this->connect();

		if (!function_exists('pg_set_client_encoding'))
		{
			return -1;
		}

		return pg_set_client_encoding($this->connection, 'UTF8');
	}

	/**
	 * This function return a field value as a prepared string to be used in a SQL statement.
	 *
	 * @param   array   $columns      Array of table's column returned by ::getTableColumns.
	 * @param   string  $field_name   The table field's name.
	 * @param   string  $field_value  The variable value to quote and return.
	 *
	 * @return  string  The quoted string.
	 *
	 * @since   1.0
	 */
	public function sqlValue($columns, $field_name, $field_value)
	{
		switch ($columns[$field_name])
		{
			case 'boolean':
				$val = 'NULL';

				if ($field_value == 't')
				{
					$val = 'TRUE';
				}
				elseif ($field_value == 'f')
				{
					$val = 'FALSE';
				}
				break;

			case 'bigint':
			case 'bigserial':
			case 'integer':
			case 'money':
			case 'numeric':
			case 'real':
			case 'smallint':
			case 'serial':
			case 'numeric,':
				$val = strlen($field_value) == 0 ? 'NULL' : $field_value;
				break;

			case 'date':
			case 'timestamp without time zone':
				if (empty($field_value))
				{
					$field_value = $this->getNullDate();
				}

				$val = $this->quote($field_value);

				break;

			default:
				$val = $this->quote($field_value);
				break;
		}

		return $val;
	}

	/**
	 * Method to commit a transaction.
	 *
	 * @param   boolean  $toSavepoint  If true, commit to the last savepoint.
	 *
	 * @return  void
	 *
	 * @since   1.0
	 * @throws  \RuntimeException
	 */
	public function transactionCommit($toSavepoint = false)
	{
		$this->connect();

		if (!$toSavepoint || $this->transactionDepth <= 1)
		{
			if ($this->setQuery('COMMIT')->execute())
			{
				$this->transactionDepth = 0;
			}

			return;
		}

		$this->transactionDepth--;
	}

	/**
	 * Method to roll back a transaction.
	 *
	 * @param   boolean  $toSavepoint  If true, rollback to the last savepoint.
	 *
	 * @return  void
	 *
	 * @since   1.0
	 * @throws  \RuntimeException
	 */
	public function transactionRollback($toSavepoint = false)
	{
		$this->connect();

		if (!$toSavepoint || $this->transactionDepth <= 1)
		{
			if ($this->setQuery('ROLLBACK')->execute())
			{
				$this->transactionDepth = 0;
			}

			return;
		}

		$savepoint = 'SP_' . ($this->transactionDepth - 1);
		$this->setQuery('ROLLBACK TO SAVEPOINT ' . $this->quoteName($savepoint));

		if ($this->execute())
		{
			$this->transactionDepth--;
			$this->setQuery('RELEASE SAVEPOINT ' . $this->quoteName($savepoint))->execute();
		}
	}

	/**
	 * Method to initialize a transaction.
	 *
	 * @param   boolean  $asSavepoint  If true and a transaction is already active, a savepoint will be created.
	 *
	 * @return  void
	 *
	 * @since   1.0
	 * @throws  \RuntimeException
	 */
	public function transactionStart($asSavepoint = false)
	{
		$this->connect();

		if (!$asSavepoint || !$this->transactionDepth)
		{
			if ($this->setQuery('START TRANSACTION')->execute())
			{
				$this->transactionDepth = 1;
			}

			return;
		}

		$savepoint = 'SP_' . $this->transactionDepth;
		$this->setQuery('SAVEPOINT ' . $this->quoteName($savepoint));

		if ($this->execute())
		{
			$this->transactionDepth++;
		}
	}

	/**
	 * Method to fetch a row from the result set cursor as an array.
	 *
	 * @param   mixed  $cursor  The optional result set cursor from which to fetch the row.
	 *
	 * @return  mixed  Either the next row from the result set or false if there are no more rows.
	 *
	 * @since   1.0
	 */
	protected function fetchArray($cursor = null)
	{
		return pg_fetch_row($cursor ? $cursor : $this->cursor);
	}

	/**
	 * Method to fetch a row from the result set cursor as an associative array.
	 *
	 * @param   mixed  $cursor  The optional result set cursor from which to fetch the row.
	 *
	 * @return  mixed  Either the next row from the result set or false if there are no more rows.
	 *
	 * @since   1.0
	 */
	protected function fetchAssoc($cursor = null)
	{
		return pg_fetch_assoc($cursor ? $cursor : $this->cursor);
	}

	/**
	 * Method to fetch a row from the result set cursor as an object.
	 *
	 * @param   mixed   $cursor  The optional result set cursor from which to fetch the row.
	 * @param   string  $class   The class name to use for the returned row object.
	 *
	 * @return  mixed   Either the next row from the result set or false if there are no more rows.
	 *
	 * @since   1.0
	 */
	protected function fetchObject($cursor = null, $class = 'stdClass')
	{
		return pg_fetch_object(is_null($cursor) ? $this->cursor : $cursor, null, $class);
	}

	/**
	 * Method to free up the memory used for the result set.
	 *
	 * @param   mixed  $cursor  The optional result set cursor from which to fetch the row.
	 *
	 * @return  void
	 *
	 * @since   1.0
	 */
	protected function freeResult($cursor = null)
	{
		$useCursor = $cursor ?: $this->cursor;

		if (is_resource($useCursor))
		{
			pg_free_result($useCursor);
		}
	}

	/**
	 * Inserts a row into a table based on an object's properties.
	 *
	 * @param   string  $table    The name of the database table to insert into.
	 * @param   object  &$object  A reference to an object whose public properties match the table fields.
	 * @param   string  $key      The name of the primary key. If provided the object property is updated.
	 *
	 * @return  boolean    True on success.
	 *
	 * @since   1.0
	 * @throws  \RuntimeException
	 */
	public function insertObject($table, &$object, $key = null)
	{
		$columns = $this->getTableColumns($table);

		$fields = array();
		$values = array();

		// Iterate over the object variables to build the query fields and values.
		foreach (get_object_vars($object) as $k => $v)
		{
			// Skip columns that don't exist in the table.
			if (! array_key_exists($k, $columns))
			{
				continue;
			}

			// Only process non-null scalars.
			if (is_array($v) or is_object($v) or $v === null)
			{
				continue;
			}

			// Ignore any internal fields or primary keys with value 0.
			if (($k[0] == "_") || ($k == $key && (($v === 0) || ($v === '0'))))
			{
				continue;
			}

			// Prepare and sanitize the fields and values for the database query.
			$fields[] = $this->quoteName($k);
			$values[] = $this->sqlValue($columns, $k, $v);
		}

		// Create the base insert statement.
		$query = $this->getQuery(true);

		$query->insert($this->quoteName($table))
			->columns($fields)
			->values(implode(',', $values));

		$retVal = false;

		if ($key)
		{
			$query->returning($key);

			// Set the query and execute the insert.
			$this->setQuery($query);

			$id = $this->loadResult();

			if ($id)
			{
				$object->$key = $id;
				$retVal = true;
			}
		}
		else
		{
			// Set the query and execute the insert.
			$this->setQuery($query);

			if ($this->execute())
			{
				$retVal = true;
			}
		}

		return $retVal;
	}

	/**
	 * Test to see if the PostgreSQL connector is available.
	 *
	 * @return  boolean  True on success, false otherwise.
	 *
	 * @since   1.0
	 */
	public static function isSupported()
	{
		return (function_exists('pg_connect'));
	}

	/**
	 * Returns an array containing database's table list.
	 *
	 * @return  array  The database's table list.
	 *
	 * @since   1.0
	 */
	public function showTables()
	{
		$this->connect();

		$query = $this->getQuery(true);
		$query->select('table_name')
			->from('information_schema.tables')
			->where('table_type=' . $this->quote('BASE TABLE'))
			->where(
				'table_schema NOT IN (' . $this->quote('pg_catalog') . ', ' . $this->quote('information_schema') . ' )'
			);

		$this->setQuery($query);
		$tableList = $this->loadColumn();

		return $tableList;
	}

	/**
	 * Get the substring position inside a string
	 *
	 * @param   string  $substring  The string being sought
	 * @param   string  $string     The string/column being searched
	 *
	 * @return  integer  The position of $substring in $string
	 *
	 * @since   1.0
	 */
	public function getStringPositionSql($substring, $string)
	{
		$this->connect();

		$query = "SELECT POSITION( $substring IN $string )";
		$this->setQuery($query);
		$position = $this->loadRow();

		return $position['position'];
	}

	/**
	 * Generate a random value
	 *
	 * @return  float  The random generated number
	 *
	 * @since   1.0
	 */
	public function getRandom()
	{
		$this->connect();

		$this->setQuery('SELECT RANDOM()');
		$random = $this->loadAssoc();

		return $random['random'];
	}

	/**
	 * Get the query string to alter the database character set.
	 *
	 * @param   string  $dbName  The database name
	 *
	 * @return  string  The query that alter the database query string
	 *
	 * @since   1.0
	 */
	public function getAlterDbCharacterSet($dbName)
	{
		$query = 'ALTER DATABASE ' . $this->quoteName($dbName) . ' SET CLIENT_ENCODING TO ' . $this->quote('UTF8');

		return $query;
	}

	/**
	 * Get the query string to create new Database in correct PostgreSQL syntax.
	 *
	 * @param   object   $options  object coming from "initialise" function to pass user
	 * 									and database name to database driver.
	 * @param   boolean  $utf      True if the database supports the UTF-8 character set,
	 * 									not used in PostgreSQL "CREATE DATABASE" query.
	 *
	 * @return  string	The query that creates database, owned by $options['user']
	 *
	 * @since   1.0
	 */
	public function getCreateDbQuery($options, $utf)
	{
		$query = 'CREATE DATABASE ' . $this->quoteName($options->db_name) . ' OWNER ' . $this->quoteName($options->db_user);

		if ($utf)
		{
			$query .= ' ENCODING ' . $this->quote('UTF-8');
		}

		return $query;
	}

	/**
	 * This function replaces a string identifier <var>$prefix</var> with the string held is the
	 * <var>tablePrefix</var> class variable.
	 *
	 * @param   string  $sql     The SQL statement to prepare.
	 * @param   string  $prefix  The common table prefix.
	 *
	 * @return  string  The processed SQL statement.
	 *
	 * @since   1.0
	 */
	public function replacePrefix($sql, $prefix = '#__')
	{
		$sql = trim($sql);

		if (strpos($sql, '\''))
		{
			// Sequence name quoted with ' ' but need to be replaced
			if (strpos($sql, 'currval'))
			{
				$sql = explode('currval', $sql);

				for ($nIndex = 1; $nIndex < count($sql); $nIndex = $nIndex + 2)
				{
					$sql[$nIndex] = str_replace($prefix, $this->tablePrefix, $sql[$nIndex]);
				}

				$sql = implode('currval', $sql);
			}

			// Sequence name quoted with ' ' but need to be replaced
			if (strpos($sql, 'nextval'))
			{
				$sql = explode('nextval', $sql);

				for ($nIndex = 1; $nIndex < count($sql); $nIndex = $nIndex + 2)
				{
					$sql[$nIndex] = str_replace($prefix, $this->tablePrefix, $sql[$nIndex]);
				}

				$sql = implode('nextval', $sql);
			}

			// Sequence name quoted with ' ' but need to be replaced
			if (strpos($sql, 'setval'))
			{
				$sql = explode('setval', $sql);

				for ($nIndex = 1; $nIndex < count($sql); $nIndex = $nIndex + 2)
				{
					$sql[$nIndex] = str_replace($prefix, $this->tablePrefix, $sql[$nIndex]);
				}

				$sql = implode('setval', $sql);
			}

			$explodedQuery = explode('\'', $sql);

			for ($nIndex = 0; $nIndex < count($explodedQuery); $nIndex = $nIndex + 2)
			{
				if (strpos($explodedQuery[$nIndex], $prefix))
				{
					$explodedQuery[$nIndex] = str_replace($prefix, $this->tablePrefix, $explodedQuery[$nIndex]);
				}
			}

			$replacedQuery = implode('\'', $explodedQuery);
		}
		else
		{
			$replacedQuery = str_replace($prefix, $this->tablePrefix, $sql);
		}

		return $replacedQuery;
	}

	/**
	 * Method to release a savepoint.
	 *
	 * @param   string  $savepointName  Savepoint's name to release
	 *
	 * @return  void
	 *
	 * @since   1.0
	 */
	public function releaseTransactionSavepoint($savepointName)
	{
		$this->connect();
		$this->setQuery('RELEASE SAVEPOINT ' . $this->quoteName($this->escape($savepointName)));
		$this->execute();
	}

	/**
	 * Method to create a savepoint.
	 *
	 * @param   string  $savepointName  Savepoint's name to create
	 *
	 * @return  void
	 *
	 * @since   1.0
	 */
	public function transactionSavepoint($savepointName)
	{
		$this->connect();
		$this->setQuery('SAVEPOINT ' . $this->quoteName($this->escape($savepointName)));
		$this->execute();
	}

	/**
	 * Unlocks tables in the database, this command does not exist in PostgreSQL,
	 * it is automatically done on commit or rollback.
	 *
	 * @return  PostgresqlDriver  Returns this object to support chaining.
	 *
	 * @since   1.0
	 * @throws  \RuntimeException
	 */
	public function unlockTables()
	{
		$this->transactionCommit();

		return $this;
	}

	/**
	 * Updates a row in a table based on an object's properties.
	 *
	 * @param   string   $table    The name of the database table to update.
	 * @param   object   &$object  A reference to an object whose public properties match the table fields.
	 * @param   array    $key      The name of the primary key.
	 * @param   boolean  $nulls    True to update null fields or false to ignore them.
	 *
	 * @return  boolean  True on success.
	 *
	 * @since   1.0
	 * @throws  \RuntimeException
	 */
	public function updateObject($table, &$object, $key, $nulls = false)
	{
		$columns = $this->getTableColumns($table);
		$fields  = array();
		$where   = array();

		if (is_string($key))
		{
			$key = array($key);
		}

		if (is_object($key))
		{
			$key = (array) $key;
		}

		// Create the base update statement.
		$statement = 'UPDATE ' . $this->quoteName($table) . ' SET %s WHERE %s';

		// Iterate over the object variables to build the query fields/value pairs.
		foreach (get_object_vars($object) as $k => $v)
		{
			// Skip columns that don't exist in the table.
			if (! array_key_exists($k, $columns))
			{
				continue;
			}

			// Only process scalars that are not internal fields.
			if (is_array($v) or is_object($v) or $k[0] == '_')
			{
				continue;
			}

			// Set the primary key to the WHERE clause instead of a field to update.
			if (in_array($k, $key))
			{
				$key_val = $this->sqlValue($columns, $k, $v);
				$where[] = $this->quoteName($k) . '=' . $key_val;
				continue;
			}

			// Prepare and sanitize the fields and values for the database query.
			if ($v === null)
			{
				// If the value is null and we want to update nulls then set it.
				if ($nulls)
				{
					$val = 'NULL';
				}
				else
				// If the value is null and we do not want to update nulls then ignore this field.
				{
					continue;
				}
			}
			else
			// The field is not null so we prep it for update.
			{
				$val = $this->sqlValue($columns, $k, $v);
			}

			// Add the field to be updated.
			$fields[] = $this->quoteName($k) . '=' . $val;
		}

		// We don't have any fields to update.
		if (empty($fields))
		{
			return true;
		}

		// Set the query and execute the update.
		$this->setQuery(sprintf($statement, implode(",", $fields), implode(' AND ', $where)));

		return $this->execute();
	}
}
