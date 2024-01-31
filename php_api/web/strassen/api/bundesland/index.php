<?php
header("content-type: application/json");

$appDir = dirname(__FILE__, 5);
require_once($appDir . DIRECTORY_SEPARATOR . "config.php");

// Connecting, selecting database
$dbconn = pg_connect(DB_CONNECT_STRING)
   or die('{"status": "server_error", "message":"The web application was unable to connect to the database: ' . pg_last_error().'"}');

$query = <<<SQL
  SELECT blkz, bundesland
  FROM bundesland
  ORDER BY 2
SQL;

$pgQuery = pg_prepare($dbconn, "date_query", $query);
$dateResult = pg_execute($dbconn, "date_query", array());

$data = [];
while ($line = pg_fetch_array($dateResult, null, PGSQL_ASSOC)) {
    $data[] = $line;
}

print json_encode($data);
