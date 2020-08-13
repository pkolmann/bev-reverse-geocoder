<?php
header("content-type: application/json");

$default_distance = 30;
$max_distance = 100;
$default_limit = 5;
$max_limit = 10;


$appDir = dirname(__FILE__, 2);
require_once($appDir . DIRECTORY_SEPARATOR . "config.php");

// Connecting, selecting database
$dbconn = pg_connect(DB_CONNECT_STRING)
   or die('{"status": "server_error", "message":"The web application was unable to connect to the database: ' . pg_last_error().'"}');

$query = <<<SQL
  SELECT date
  FROM bev_date
SQL;

$pgQuery = pg_prepare($dbconn, "date_query", $query);
$dateResult = pg_execute($dbconn, "date_query", array());

$line = pg_fetch_array($dateResult, null, PGSQL_ASSOC);
$date = $line['date'];


# Get the HTTP GET parameters and use default values where it makes sense.

$lat = null;
if (array_key_exists('lat', $_GET)) {
    $lat = $_GET['lat'];
}

$lon = null;
if (array_key_exists('lon', $_GET)) {
    $lon = $_GET['lon'];
}

$epsg = null;
if (array_key_exists('epsg', $_GET)) {
    $epsg = $_GET['epsg'];
}

$distance = null;
if (array_key_exists('distance', $_GET)) {
    $distance = $_GET['distance'];
}

$limit = null;
if (array_key_exists('limit', $_GET)) {
    $limit = $_GET['limit'];
}

if (is_null($epsg) || !is_numeric($epsg)) {
    $err = array();
    $err['status'] = 'bad_request';
    $err['message'] = 'The EPSG parameter must be an integer value.';
    print json_encode($err);
    die;
}

$query = <<<SQL
  SELECT srid
  FROM spatial_ref_sys
  WHERE srid=\$1
SQL;

$pgQuery = pg_prepare($dbconn, "srid_query", $query);
$sridResult = pg_execute($dbconn, "srid_query", array($epsg));

if (pg_num_rows($sridResult) < 1) {
    $err = array();
    $err['status'] = 'bad_request';
    $err['message'] = 'EPSG ' . $epsg . ' is not supported or does not exist. Try 4326!';
    print json_encode($err);
    die;
}

if (!is_numeric($distance) || intval($distance) > $max_distance || intval($distance) < 0) {
    $err = array();
    $err['status'] = 'bad_request';
    $err['message'] = 'The distance value must be an integer between 0 and ' . $max_distance . '.';
    print json_encode($err);
    die;
}

if (!is_numeric($limit) || intval($limit) > $max_limit || intval($limit) < 0) {
    $err = array();
    $err['status'] = 'bad_request';
    $err['message'] = 'The limit parameter must be an integer between 0 and ' . $max_limit . '.';
    print json_encode($err);
    die;
}


$query = <<<SQL
  SELECT b.municipality, b.locality, b.postcode, b.street, 
          CASE 
            WHEN b.subaddress != '' THEN b.house_number || ' / ' || b.subaddress 
            ELSE b.house_number 
          END AS house_number, b.house_name, b.address_type,
          ST_Distance(ST_SetSRID(ST_MakePoint(\$1, \$2), \$3), b.point) AS distance,
          ST_X(ST_Transform(point::geometry, \$3)) AS lon, ST_Y(ST_Transform(point::geometry, \$3)) AS lat,
          municipality_has_ambiguous_addresses
  FROM bev_addresses b
  WHERE ST_DWithin(ST_SetSRID(ST_MakePoint(\$1, \$2), \$3), b.point, \$4)
  ORDER BY distance
  LIMIT \$5
SQL;

$pgQuery = pg_prepare($dbconn, "addr_query", $query);
$result = pg_execute($dbconn, "addr_query", array($lon, $lat, $epsg, $distance, $limit));
if ($result === false) {
    $err = array();
    $err['status'] = 'server_error';
    $err['message'] = 'There was a problem querying the database. Please verify that the parameters you submitted ' .
                      '(especially the coordinates according to the EPSG you specified) make sense.';
    print json_encode($err);
    die;
}

$addresses = array();
while ($line = pg_fetch_array($result, null, PGSQL_ASSOC)) {
    $addr = $line;
    $addr['distance'] = floatval($line['distance']);
    $addr['municipality_has_ambiguous_addresses'] = false;
    if ($line['municipality_has_ambiguous_addresses'] == 't') {
        $addr['municipality_has_ambiguous_addresses'] = true;
    }
    $addresses[] = $addr;
}

$res = array();
$res['status'] = 'ok';
$dateTime = strtotime($date);
$res['copyright'] = '© Österreichisches Adressregister 2017, N 23806/2017 (Stichtagsdaten vom ' . 
    date('d. m. Y', $dateTime) . ')';
$res['address_date'] = $date;
$res['results'] = $addresses;

print json_encode($res);
