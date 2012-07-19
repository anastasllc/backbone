<?php

require_once("base.class.php");

class dbpdo extends base
{
  public $mh = NULL;
  public $ps = array(); // prepared statements

  function __autoload($class)
  {
    require_once("$class.class.php");
  }

  function __construct($config)
  {
    parent::__construct($config);
    $this->config = $config;
    $this->connect();
  }

  function connect()
  {
    try
      {
	$this->mh = new PDO(
			    config::db . ':host=' . $this->config->host() . ';dbname=' . $this->config->db(), 
			    $this->config->user(),
			    $this->config->pass(),
			    array(PDO::ATTR_PERSISTENT => true)
			    );
      }
    catch (PDOException $e)
      {
	$this->error($e->getMessage());
      }
  }

  function get_prepared_statement($q)
  {
    $hash = md5($q);
    if(!in_array($hash, $this->ps))
      $this->ps[$hash] = $this->mh->prepare($q);
    return $this->ps[$hash];
  }

  function exec_statement($statement, $values, $inserting = false)
  {
    try
      {
	$this->mh->beginTransaction();
	if(@$statement->execute($values))
	  {
	    if($inserting)
	      {
		$last_insert_id = $this->mh->lastInsertId();
		$this->mh->commit();
		return $last_insert_id;
	      }
	    else
	      {
		$this->mh->commit();
		return $statement->fetchAll();
	      }
	  }
	else
	  {
	    $error_info = $statement->errorInfo();
	    throw new Exception ("(Error " . $statement->errorCode() . ") SQLSTATE error code " . $error_info[0] . ", driver error code " . $error_info[1] . ": " . $error_info[2]);
	  }
      }
    catch (PDOException $e)
      {
	$this->mh->rollBack();
	$this->error($e->getMessage());
      }
    catch (Exception $e)
      {
	$this->mh->rollBack();
	$this->error($e->getMessage());
      }
  }

  function query($q, $values, $memcache_key = NULL, $memcache_expiration = 3600, $memcache_flags = 0)
  {
    $statement = $this->get_prepared_statement($q);
    if(preg_match('/^\s*INSERT/i', $q))
      $result = $this->exec_statement($statement, $values, true);
    else
      {
	if(config::use_memcache && $memcache_key !== NULL)
	  {
	    if(!($result = $this->memcache_get($memcache_key)))
	      {
		$result = $this->exec_statement($statement, $values);
		$this->memcache_set($memcache_key, $result, $memcache_expiration, $memcache_flags);
	      }
	  }
	else
	  {
	    $result = $this->exec_statement($statement, $values);
	  }
      }
    return $result;
  }


}

?>