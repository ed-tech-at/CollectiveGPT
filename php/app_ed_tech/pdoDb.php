<?php

namespace app_ed_tech;

class pdoDb
{
  
  /**
   * @return \PDO
   */
  static public function getConnection()
  {
    global $db_user;
    global $db_pw;
    global $db_dbname;
    global $db_host;
  
    if (! isset($GLOBALS['pdoDB'])) {
      try {
        $GLOBALS['pdoDB'] = new \PDO('mysql:host=' . $db_host . ';dbname=' . $db_dbname . ';charset=utf8', $db_user, $db_pw);
        // $GLOBALS['pdoDB']->setAttribute(\PDO::ATTR_AUTOCOMMIT,0);
      } catch (\PDOException $e) {
        echo 'DB Error: ';
        var_dump($e);
      }
    }
    return $GLOBALS['pdoDB'];
  }
}