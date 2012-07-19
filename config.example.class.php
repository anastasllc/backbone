<?php

class config
{
  const db = 'mysql';

  const mysqlHOST = 'localhost';
  const mysqlUSER = '';
  const mysqlPASS = '';
  const mysqlDB = '';

  const pgsqlHOST = '';
  const pgsqlUSER = '';
  const pgsqlPASS = '';
  const pgsqlDB = '';

  const private_key = "-----BEGIN RSA PRIVATE KEY-----";

  const use_memcache = true;
  const memcache_host = 'localhost';
  const memcache_port = '11211';

  function host()
  {
    $const = $this::db . "HOST";
    return constant("self::$const");
  }

  function user()
  {
    $const = $this::db . "USER";
    return constant("self::$const");
  }

  function pass()
  {
    $const = $this::db . "PASS";
    return constant("self::$const");
  }

  function db()
  {
    $const = $this::db . "DB";
    return constant("self::$const");
  }

}

?>