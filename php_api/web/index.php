<?php



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
$date = date("d. m. Y", strtotime($line['date']));

?>
<!DOCTYPE html>
<html class="no-js" lang="de">
    <head>
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">
        <title>BEV Address Data Reverse Geocoder</title>
        <meta name="description" content="">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <link href='./googlefonts.css' rel='stylesheet' type='text/css'>

        <link rel="stylesheet" href="bootstrap-4.3.1.min.css">
        <style>
            body {
                padding-top: 50px;
                padding-bottom: 20px;
            }

            body {
                font-family: 'Roboto', sans-serif;
            }

            body, .btn {
                font-size: 16px;
            }

            h1 {
                margin-top: 30px;
                margin-bottom: 15px;
                font-size: 40px;
            }

            h2 {
                margin-bottom: 10px;
            }
            
        </style>

        <script src="bootstrap-4.3.1.min.js"></script>
        <script src="jquery-3.3.1.slim.min.js"></script>
        <script src="popper-1.14.7.min.js"></script>
    </head>
    <body>
    <div class="navbar navbar-inverse navbar-fixed-top" role="navigation">
        <div class="container">
            <div class="navbar-header">
                <a class="navbar-brand" href="/">BEV Address Data Reverse Geocoder</a>
            </div>
        </div>
    </div>

    <div class="container">
    <h1>BEV Address Data Reverse Geocoder</h1>

    <p>Welcome to the Reverse Geocoder for the Address data released by the Bundesamt für Eich- und Vermessungswesen
    (BEV) in Austria! This services converts coordinates into an array of address data sets. The source code of the original
    application can be found <a href="https://github.com/thomaskonrad/bev-reverse-geocoder">here</a>. The source code of my
    improved version can be found <a href="https://github.com/pkolmann/bev-reverse-geocoder">here</a>.</p>

    <div class="alert alert-success" role="alert"><strong>Data</strong>: © Österreichisches Adressregister 2017, N 23806/2017 (Stichtagsdaten vom <?php print $date; ?>)</div>

    <h2>Map</h2>
    <p>A map view of the BEV address data can be found <a href="map/">here</a>.</p>
    <h2>BEV Adressdaten Straßenliste</h2>
    <p>A list of all streets in the BEV address data can be found <a href="strassen/">here</a>.</p>
    <h2>Example</h2>
    <div id="my-tab-content" class="tab-content">
        <div class="tab-pane active" id="json">
            <h3>JSON</h3>

            <p><a href="reverse-geocode.php?lat=48.20808&lon=16.37236&distance=50&limit=3&epsg=4326" class="btn btn-primary btn-lg">Try it yourself!</a></p>

            <pre>https://bev.kolmann.at/reverse-geocode.php?lat=48.20808&lon=16.37236&distance=50&limit=3&epsg=4326</pre>

    <pre>{
   "status":"ok",
       "copyright":"\u00a9 \u00d6sterreichisches Adressregister 2017, N 23806/2017 (Stichtagsdaten vom <?php print $date; ?>)",
   "address_date": "2016-10-02",
   "results":[
      {
         "address_type:"street",
         "municipality":"Wien",
         "locality": "Innere Stadt",
         "postcode":"1010",
         "street":"Stephansplatz",
         "house_name":"",
         "house_number":"2",
         "lat":48.208111,
         "lon":16.372235,
         "distance":9.909445594,
         "municipality_has_ambiguous_addresses": false
      },
      {
         "address_type:"street",
         "municipality":"Wien",
         "locality": "Innere Stadt",
         "postcode":"1010",
         "street":"Stephansplatz",
         "house_number":"3A",
         "house_name":"",
         "lat":16.372547,
         "lon":48.20809,
         "distance":13.943139329,
         "municipality_has_ambiguous_addresses": false
      },
      {
         "address_type:"street",
         "municipality":"Wien",
         "locality": "Innere Stadt",
         "postcode":"1010",
         "street":"Stock-im-Eisen-Platz",
         "house_number":"1",
         "house_name":"",
         "lat":16.372116,
         "lon":48.208116,
         "distance":18.571775123,
         "municipality_has_ambiguous_addresses": false
      }
   ]
}</pre>
        </div>

    </div>

    <script type="text/javascript">
        $(document).ready(function ($) {
            $('#tabs').tab();
        });
    </script>

    <h2>API Definition</h2>

    <p>These are the parameters that can be passed to the API:</p>

    <dl class="dl-horizontal">
        <dt><code>lat</code></dt>
        <dd>The latitude value of the coordinates. Must make sense according to the EPSG code you specify.</dd>

        <dt><code>lon</code></dt>
        <dd>The longitude value of the coordinates. Must make sense according to the EPSG you specify.</dd>

        <dt><code>distance</code></dt>
        <dd>The radius around the point you specified where addresses should be included in meters.
            <br /><strong>Minimum:</strong> 0. <strong>Maximum:</strong> 100. <strong>Default:</strong> 30.</dd>

        <dt><code>limit</code></dt>
        <dd>The maximum number of address data sets to be returned.
            <br /><strong>Minimum:</strong> 1. <strong>Maximum:</strong> 10. <strong>Default:</strong> 5.</dd>

        <dt><code>epsg</code></dt>
        <dd>The EPSG code for the spatial reference system by which you specify your coordinates. Many of them are
            supported, for example: <a href="http://www.spatialreference.org/ref/epsg/4326/" target="_blank">EPSG 4326 (WGS 84)</a>,
            <a href="http://spatialreference.org/ref/sr-org/6864/" target="_blank">EPSG:3857 / EPSG 900913</a> (the one used internally
            by OpenStreetMap), <a href="http://www.spatialreference.org/ref/epsg/31287/" target="_blank">31287 (Austria Lambert)</a>.
            <br /><strong>Default:</strong> 4326 (WGS 84).</dd>
    </dl>

    <h2>Usage Limits</h2>

    <p>You are free to use the service without any restrictions, If, however, I notice excessive usage by a service,
    IP address or IP range, I will not hesitate to block it if it soaks up too much of the server resources. So please
    be fair when using this service.</p>


      <hr>

      <footer>
      <p>&copy; <a href="https://thomaskonrad.at">Thomas Konrad</a> 2017 | 
         &copy; <a href="https://www.kolmann.at">Philpp Kolmann</a> 2021
      </p>
      </footer>
    </div> <!-- /container -->

    </body>
</html>

