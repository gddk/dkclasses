<?php
//
// +------------------------------------------------------------------------+
// | DbTable                                                        |
// +------------------------------------------------------------------------+
// | Authors       David Koopman                                            |
// | Web           http://www.koopman.me                                 |
// +------------------------------------------------------------------------+
//

/**
 * Class for manipulating rows in a database.  This is an abstract class
 * and should not be initiated directly.
 *
 * @since 1.0
 */
abstract class DbTable {

  //////////////////////////////////////////////////////////////////////////
  ///////////////////////////  MEMBERS  ////////////////////////////////////
  //////////////////////////////////////////////////////////////////////////

  /**
   * Holds the database connection objects.
   *
   * We keep an associate array of database connection,
   * like this:  self::$aDB[$this->dsn]
   *
   * @var array
   */
  public static $aDB = array();

  /**
   * This variable is the auto-increment, primary key column of the table.
   *
   * @var int
   */
  protected $id;

  /**
   * Bool of whether this instance may be saved or not.
   *
   * @var bool
   */
  protected $readonly = false;

  /**
   * This holds the name of the table.
   *
   * @var string
   */
  private  $table_name;

  /**
   * Holds associative array of database keys => values.
   *
   * @var array
   */
  private  $database_fields;

  /**
   * Tells whether the row has been loaded from the database table.
   *
   * @var bool
   */
  private  $loaded;

  /**
   * Tells whether any database_fields have been modified since the
   * last load.
   *
   * @var bool
   */
  private   $modified;

  /**
   * Holds associative array of modified fields in  keys => bool format
   *
   * @var array
   */
  private   $modified_fields;

  /**
   * Holds the database DSN for the currently connected database.
   *
   * We need this DSN, because DbTable is a generic object that has an array
   * of connections.  We need to know which database this particular DbTable
   * object is associated with.  We keep an associate array of database
   * connection, like this:  self::$aDB[$this->dsn]
   *
   * @var string
   */
  private   $dsn;

  private static $object_cache = array();
  private static $describe_cache = array();

  //////////////////////////////////////////////////////////////////////////
  ///////////////////////////  METHODS  ////////////////////////////////////
  //////////////////////////////////////////////////////////////////////////

  /**
   * Creates an Array of Objects of the given type with the given tuple ids
   *
   * Populates an array of DbTable objects by selecting all the data in one
   * query and then populating the objects.  This saves overhead over creating
   * the objects in a loop in your code, which will go to the database and load
   * the rows one at a time.
   *
   * @param string $dsn DSN to connect to
   * @param string $class_name The kind of classes to create (also to the table name to load from)
   * @param array $ids Array of tuple_ids to load
   */
  public static function loadArray($dsn,$class_name,$ids)
  {
    self::connect($dsn);
    $aDB = self::$aDB[$dsn];

    $objs = array();

    $aSQL = "SELECT * FROM $class_name WHERE " . $GLOBALS['primary_keys'][$dsn][$class_name] .
            " IN (" . implode(",",$ids) . ")";
    $aResult = $aDB->query($aSQL);
    if ( DB::isError($aResult) )
      return new Error(ERROR_DBCON);
      #Error::display("ERROR: (". __CLASS__ ."/". __LINE__ .") Query error: $aSQL b/c".$aResult->getMessage(), 'FATAL');

    while ( $aRow = $aResult->fetchrow() )
    {
      $objs[] = new $class_name($aRow);
    }

    return $objs;
  }

  /**
   * Fetches a row from the database and loads $this->database_fields
   *
   * If $this->id is 0, then default values are fetched from the table
   * and loaded into $this->database_fields.  If $this->id is set to a
   * row that doesn't exist, a fatal error occurs.
   *
   */

  public function load() {
    $id = $this->id;
    $table_name = $this->table_name;

    if ($id == 0) {
      if ( isset(self::$describe_cache[$this->table_name]) )
      {
        $this->database_fields = self::$describe_cache[$this->table_name];
      }
      else
      {
        $aSQL = "DESCRIBE `$table_name`";
        $aResult = self::$aDB[$this->dsn]->query($aSQL);
        $this->database_fields = array();
        while ( $aRow = $aResult->fetchRow() ) {
          switch ( $aRow['Default'] ) {
            case '' :
              $aRow['Default'] = null;
              break;
            case 'CURRENT_TIMESTAMP' :
              $aRow['Default'] = Date("Y-m-d H:i:s");
              break;
            default :

          }
          $this->database_fields[$aRow['Field']] = $aRow['Default'];
        }
        self::$describe_cache[$this->table_name] = $this->database_fields;
      }
    }
    else
    {

      if ( isset( self::$object_cache[$table_name][$id]) )
      {
        $this->database_fields = self::$object_cache[$table_name][$id];
      }
      else
      {
        $aSQL = "SELECT * FROM `$table_name` WHERE {$GLOBALS['primary_keys'][$this->dsn][$table_name]}=".self::$aDB[$this->dsn]->quote($id);

        $aResult = self::$aDB[$this->dsn]->query($aSQL);

        if ( DB::isError($aResult) )
          return new Error(ERROR_BADQUERY);
          #Error::display("ERROR: (". __CLASS__ ."/". __LINE__ .") Query error: $aSQL b/c".$aResult->getMessage(), 'FATAL');

        $this->database_fields = array();
        $numRows = $aResult->numRows();
        if (is_int($numRows) && $numRows > 0 )
        {
          $this->database_fields = $aResult->fetchRow();
          $aResult->free();
        }
        else
        {
          return new Error(ERROR_IMPROPERUSE);
          #Error::display("ERROR (". __CLASS__ ."/". __LINE__ .") with table: $table_name, id: $id", 'FATAL');
          #return false;
        }
        self::$object_cache[$table_name][$id] = $this->database_fields;
        $aResult->free();
      }
    }

    $this->loaded = 1;
    unset($this->modified_fields);
  }

  /**
   * Sets $this->loaded to 1
   *
   */
  public function forceLoaded() {
    $this->loaded = 1;
  }

  /**
   * Sets $this->loaded to 0
   *
   */
  public function forceUnloaded() {
    $this->loaded = 0;
  }

  /**
   * Returns the value of the field of the current row in the table.
   *
   * @param string $field
   * @return string
   */
  public function getField($field) {
    if ($this->loaded == 0) {
      $this->load();
    }

    if ( ! array_key_exists($field,$this->database_fields) )
      return '';
      #return new Error(1);
      #Error::display("ERROR: (". __CLASS__ ."/". __LINE__ .") Tried to getField on nonexistent key '$field'".print_r($this, true),"FATAL");
    return $this->database_fields[$field];
  }

  /**
   * Returns an associative array of fields => values for the
   * currently selected row.
   *
   * @return array
   */
  public function getAllFields() {
    if ($this->loaded == 0) {
      $this->load();
    }
    return($this->database_fields);
  }

  /**
   * Returns an array of column names
   *
   * @return array
   */
  public function getColumnNames()
  {
    return array_keys($this->database_fields);
  }

  /**
   * Returns the primary key of this table row ($this->id)
   *
   * @return int
   */
  public function getID() {
    return $this->id;
  }

  /**
   * Returns an array of the column names which are marked as modified
   *
   * @return array
   */
  public function getModifiedColumns()
  {
    $keys = array();
    foreach ( $this->modified_fields as $field => $value )
    {
      if ( $this->modified_fields[$field] == true )
        $keys[] = $field;
    }

    return $keys;
  }

  /**
   * Establishes a connection to the passed DSN
   *
   * The connection object that results is stored in the static
   * class array self::$aDB[$dsn]
   *
   * @param string $dsn
   */
  static function connect($dsn) {
    if ( !isset(self::$aDB[$dsn])) {
      self::$aDB[$dsn] = DB::connect( $dsn );
      if ( DB::isError( self::$aDB[$dsn] ) )
         return new Error(ERROR_DBCON);
         //Error::display("Could not connect to $dsn (". __CLASS__ ."/". __LINE__ .") b/c: ".self::$aDB[$dsn]->getMessage(), 'FATAL');

      self::$aDB[$dsn]->setFetchMode( DB_FETCHMODE_ASSOC );

#      $result = self::$aDB[$dsn]->query("SET SESSION TRANSACTION ISOLATION LEVEL READ COMMITTED");
#      if ( DB::isError( $result ) )
#        return new Error(ERROR_BADQUERY);
#
#      $result = self::$aDB[$dsn]->query("SET AUTOCOMMIT = 0");
#      if ( DB::isError( $result ) )
#        return new Error(ERROR_BADQUERY);
    }
  }

  /**
   * Fetches a row from the table and initializes this object.
   *
   * This method is generally called from the constructor of
   * extended objects of DbTable.  $tuple_id may be empty, in which
   * case a default object is created, rather than fetching a row
   * from the table.
   *
   * $tuple_id may also be an array of data, in which
   * case an object containing that data is created rather than asking
   * the database.  This is useful if you're already doing a query to
   * figure out what rows you want and then just want to create objects
   * for the returned data.
   *
   * @param string $dsn
   * @param string $table_name
   * @param int|array $tuple_id the primary key to fetch, or an array of data to load
   */
  protected function initialize($dsn, $table_name, $tuple_id = "")
  {
    if ( ! isset($GLOBALS['primary_keys'][$dsn][$table_name]) )
    {
      return new Error(ERROR_CONFIG);
      #Error::display("ERROR: (". __CLASS__ ."/". __LINE__ .") GLOBALS['primary_keys'][$dsn][$table_name] does not exist", 'FATAL');
    }

    self::connect($dsn);
    $this->table_name = $table_name;
    $this->dsn = $dsn;

    if ( is_array($tuple_id) )
    {
      $this->database_fields = $tuple_id;
      $this->id = $tuple_id[ $GLOBALS['primary_keys'][$dsn][$table_name] ];
      $this->loaded=1;
    }
    else
    {
      $this->id = $tuple_id;
      $this->load();
    }
#if ( $GLOBALS['dktmp']==2 )
#{ print " about to seg fault 3";  }
  }

  /**
   * Sets the field value.
   *
   * This sets the field value and marks the associated
   * $this->modified_fields.  The modified value will not be saved in
   * the table until the save() method is called.
   *
   * @param string $field
   * @param string $value
   */
  public function setField($field, $value) {
    if ($this->loaded == 0) {
      if ($this->id) {
        $this->load();
      };
    };

    if ( ! array_key_exists($field,$this->database_fields) )
      return new Error(ERROR_IMPROPERUSE);
      #Error::display("Tried to setField on nonexistent key '$field'".print_r($this, true),"FATAL");

    // Only mark as updated if there's a change
    if ( $this->id == 0 || $this->database_fields[$field] != $value )
    {
      $this->database_fields[$field] = $value;
      $this->modified = 1;
      $this->modified_fields[$field] = true;
    }
  }

  /**
   * Sets an array of field values.
   *
   * This sets the field values and marks the associated
   * $this->modified_fields.  The modified value will not be saved in
   * the table until the save() method is called.
   *
   * @param array $assoc -- an associative array of form ($field => $value)
   */
  public function setFields($assoc) {
    if ($this->loaded == 0) {
      if ($this->id) {
        $this->load();
      };
    };

    foreach ( $assoc as $field => $value )
    {
      if ( ! array_key_exists($field,$this->database_fields) )
        return new Error(ERROR_IMPROPERUSE);

        #Error::display("Tried to setFields on nonexistent key '$field'","FATAL");

      // Only mark as updated if there's a change
      if ( $this->id == 0 || $this->database_fields[$field] != $value )
      {
        $this->database_fields[$field] = $value;
        $this->modified = 1;
        $this->modified_fields[$field] = true;
      }
    }
  }

  /**
   * Deletes this row from the table.
   *
   */
  public function destroy() {
    $id = $this->id;
    $table_name = $this->table_name;
    if ($id) {
      $stmt = "DELETE FROM `$table_name` WHERE {$GLOBALS['primary_keys'][$this->dsn][$table_name]}=".self::$aDB[$this->dsn]->quote($id);
      $result = self::$aDB[$this->dsn]->query($stmt);
      if ( DB::isError($result) )
        return new Error(ERROR_BADQUERY);
        #Error::display("ERROR: (". __CLASS__ ."/". __LINE__ .") Query error: $stmt b/c".$result->getMessage(), 'FATAL');
    }
  }

  /**
   * Truncates the table.  You may want to disable this one, else use with cation.
   *
   */
  public function truncate() {
    $table_name = $this->table_name;
    $stmt = "TRUNCATE TABLE `$table_name`";
    $result = self::$aDB[$this->dsn]->query($stmt);
    if ( DB::isError($result) )
      return new Error(ERROR_BADQUERY, $stmt);
  }


  /**
   * Sends "COMMIT" to the database.
   *
   */
  public function commit() {
    self::$aDB[$this->dsn]->query("COMMIT");
  }

  /**
   * Sends "COMMIT" to every database connected in the
   * static class array self::$aDB
   *
   */
  public function commitAll() {
    $dsn = array_keys(self::$aDB);
    foreach ( $dsn as $value ) {
      self::$aDB[$value]->query("COMMIT");
    }
  }

  /**
   * Sends "ROLLBACK" to the database.
   *
   */
  public function rollback() {
    self::$aDB[$this->dsn]->query("ROLLBACK");
  }

  /**
   * Sends "ROLLBACK" to every database connected in the
   * static class array self::$aDB
   *
   */
  public function rollbackAll() {
    $dsn = array_keys(self::$aDB);
    foreach ( $dsn as $value ) {
      self::$aDB[$value]->query("ROLLBACK");
    }
  }

  /**
   * Sends an INSERT or UPDATE to the database to save any changes.
   * If there are no modified_fields, then no UPDATE will be sent.
   *
   */
  public function save()
  {
    if ($this->readonly)
      return false;

    $id = $this->id;
    $table_name = $this->table_name;
    if (!$id) {
      $this->loaded = 0;
    };
    if ($this->loaded == 0) {
      # assume this is a new entity
      $stmt = "INSERT INTO `$table_name` (";
      if ( is_array($this->database_fields) ) {
        foreach ($this->database_fields as $key => $value) {
          if (!is_numeric($key)) {
            $key = str_replace("'", "\'", $key);
            if ($value != "") {
              $stmt .= "`$key`,";
            }
          }
        }
      }

      # Chop last comma
      $stmt = ereg_replace(",$", "", $stmt);
      $stmt .= ") VALUES (";
      if ( is_array($this->database_fields) ) {
        foreach ($this->database_fields as $key => $value) {
          if (!is_numeric($key)) {
            if ($value != "") {
              $value = self::$aDB[$this->dsn]->quote($value);
              $stmt .= "$value,";
            }
          }
        }
      }
      # Chop last comma
      $stmt = ereg_replace(",$", "", $stmt);
      $stmt .= ")";
    }
    else if ( ! $this->modified )
    {
      return true;
    }
    else
    {
      $stmt = "UPDATE `$table_name` SET ";
      foreach ($this->database_fields as $key => $value) {
        if (!is_numeric($key)) {
          if ($this->modified_fields[$key] == true) {
            $value = self::$aDB[$this->dsn]->quote($value);
            if ($value == "") {
              $stmt .= "`$key` = NULL, ";
            } else {
              $stmt .= "`$key` = $value, ";
            }
          }
        }
      }
      # Chop last comma and space
      $stmt = ereg_replace(", $", "", $stmt);
      $stmt .= " WHERE {$GLOBALS['primary_keys'][$this->dsn][$table_name]}='$id'";
    }

    $result = self::$aDB[$this->dsn]->query($stmt);

    if (DB::isError($result)) {
      return new Error(ERROR_BADQUERY);
      #Error::display("ERROR: (". __CLASS__ ."/". __LINE__ .") Query error: $stmt b/c".$result->getMessage(), 'FATAL');
    }

    if ( $this->loaded == 0) {
      # Try to get the ID of the new tuple.
      $stmt = "SELECT LAST_INSERT_ID() As {$GLOBALS['primary_keys'][$this->dsn][$table_name]}";

      $result = self::$aDB[$this->dsn]->query($stmt);
      if ( $result->numRows() > 0 ) {
        $row = $result->fetchRow();
        $proposed_id = $row[$GLOBALS['primary_keys'][$this->dsn][$table_name]];
        $this->database_fields[$GLOBALS['primary_keys'][$this->dsn][$table_name]] = $proposed_id;
      }
      $result->free();
      if ($proposed_id > 0) {
        $this->loaded = 1;
        $this->id = $proposed_id;
      }
      else
      {
        return false;
      }
    }

    $this->modified = 0;
    unset($this->modified_fields);
    self::$object_cache[$table_name][$this->id] = $this->database_fields;

    return true;
  }

  /**
   * Changes this record to be readonly or not readonly
   *
   * @param bool $bool (true for readonly, false for NOT readonly)
   *
   */
  function setReadOnly( $bool=true )
  {
    $bool = (bool) $bool;
    $this->readonly = $bool;
  }

  /**
   * Retrieves the readonly status of this object
   *
   * @return bool
   */
  function getReadOnly()
  {
    return $this->readonly;
  }

  static function convertToAssoc($aObjs,$keyBy=false)
  {
    if ( ! is_array($aObjs) )
      return $aObjs->getAllFields();

    $assoc = array();
    foreach ($aObjs as $aObj)
    {
      if ( $keyBy )
        $assoc[ $aObj->getField($keyBy) ] = $aObj->getAllFields();
      else
        $assoc[] = $aObj->getAllFields();
    }
    return $assoc;
  }

  public function printme()
  {
    $aRow = $this->getAllFields();
    foreach($aRow as $key => $val)
      print $val."\t";
    print "\n";
  }

}
