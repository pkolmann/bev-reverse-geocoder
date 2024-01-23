<?php
header("content-type: application/json");

$default_distance = 30;
$max_distance = 250;
$default_limit = 5;
$max_limit = 500;


$appDir = dirname(__FILE__, 5);
require_once($appDir . DIRECTORY_SEPARATOR . "config.php");
require_once($appDir . DIRECTORY_SEPARATOR . "enum.php");

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
            WHEN b.subaddress != '' THEN b.house_number || b.subaddress 
            ELSE b.house_number 
          END AS house_number, b.house_name, b.address_type,
          ST_Distance(ST_SetSRID(ST_MakePoint(\$1, \$2), \$3), b.point) AS distance,
          ST_X(ST_Transform(point::geometry, \$3)) AS lon, ST_Y(ST_Transform(point::geometry, \$3)) AS lat,
          ST_X(ST_Transform(address_point::geometry, \$3)) AS address_lon, ST_Y(ST_Transform(address_point::geometry, \$3)) AS address_lat,
          house_attribute, house_function, CONCAT(adrcd, '-', subcd) AS adrcd
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

print '{"type": "FeatureCollection", "features": ['."\n";
$i = 0;
while ($line = pg_fetch_array($result, null, PGSQL_ASSOC)) {
    if ($i > 0) print ",\n";
    $addr = [];
    $addr['geometry'] = [
        'type' => 'Point',
        'coordinates' => [
            floatval($line['lon']),
            floatval($line['lat'])
        ]
    ];
    $addr['type'] = 'Feature';
    $addr['properties'] = $line;
    if (array_key_exists('address_lat', $line) && array_key_exists('address_lon', $line)) {
        $addr['properties']['address_coordinates'] = [
            floatval($line['address_lat']),
            floatval($line['address_lon'])
        ];
    }
    if (array_key_exists($line['house_attribute'], $house_attribute_string)) {
        $addr['properties']['house_attribute_string'] = $house_attribute_string[$line['house_attribute']];
    }
    if ($line['house_function'] != '') {
        $func = '';
        foreach (explode(',', $line['house_function']) as $val) {
            if ($func != '') {
                $func .= ', ';
            }
            if (array_key_exists($val, $house_function_string)) {
                $func .= $house_function_string[$val];
            }
        }
        $addr['properties']['house_function_string'] = $func;
    }

    $addr['id'] = $i;
    $i++;
    print "    ".json_encode($addr);
}
print "\n]}\n";

