<?php

class base {
  public $config = NULL;
  public $memcache = NULL;

  function __autoload($class)
  {
    require_once("$class.class.php");
  }

  function __construct($config)
  {
    $this->config = $config;
    if(config::use_memcache)
      {
	$this->memcache = new Memcache;
	if(!$this->memcache->pconnect(config::memcache_host, $config::memcache_port))
	  $this->error("Connection to memcached failed.");
      }
  }

  function memcache_get($key)
  {
    return $this->memcache->get($key);
  }

  function memcache_set($key, $value, $timeout = 3600, $flags = 0)
  {
    $this->memcache->set($key, $value, $flags, $timeout);
  }

  function memcache_delete($key)
  {
    $this->memcache->delete($key);
  }

  function timestamp($format = "Y-m-d H:i:s")
  {
    return date($format);
  }

  function session($key)
  {
    if(isset($_SESSION) && isset($_SESSION[$key]))
      return $_SESSION[$key];
    return false;
  }

  function error($arg, $log = false)
  {
    if($log)
      {
	$trace = debug_backtrace();
	$this->log("=====================\n");
	$this->log("ERROR: " . $arg . "\n");
	$this->log(implode('\n',$trace));
	$this->log("=====================\n");
      }
    die($arg);
  }

  function log($msg, $logfile = "log.txt")
  {
    if(!($fh = fopen($logfile, "at")))
      die("<strong>ERROR</strong>: Could not open $logfile for logging.");
    $fwrite($fh, $msg."\n");
    @fclose($fh);
  }

  function display()
  {
    echo " == " . get_class($this) . "<br />";
    foreach($this as $key => $value)
      if(is_object($value) && (is_subclass_of($value,"base") || get_class($value) == "base"))
	{
	  echo "$key...<br>";
	  $value->display();
	}
      else 
	if(!is_object($value))
	  if(is_array($value))
	    {
	      echo "$key => ";
	      print_r($value);
	      echo "<br />";
	    }
	  else
	    echo "$key => $value<br />";
    echo " /// " . get_class($this) . "<br />";;
  }
}

?>