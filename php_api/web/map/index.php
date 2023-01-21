<?php
$appRoot = dirname(__FILE__, 3);
require_once($appRoot . DIRECTORY_SEPARATOR . 'config.php');

// Connecting, selecting database
$dbconn = pg_connect(DB_CONNECT_STRING)
   or die('{"status": "server_error", "message":"The web application was unable to connect to the database: ' . pg_last_error().'"}');

$query = <<<SQL
  SELECT TO_CHAR(date, 'dd. mm. yyyy') AS date
  FROM bev_date
SQL;

$pgQuery = pg_prepare($dbconn, "date_query", $query);
$dateResult = pg_execute($dbconn, "date_query", array());

$line = pg_fetch_array($dateResult, null, PGSQL_ASSOC);
$bevDate = $line['date'];

?>

<!DOCTYPE html>
<html>
<head>
	
	<title>BEV Address Viewer</title>

	<meta charset="utf-8" />
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	
	<link rel="shortcut icon" type="image/x-icon" href="docs/images/favicon.ico" />

    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.7.1/dist/leaflet.css" integrity="sha512-xodZBNTC5n17Xt2atTPuE1HxjVMSvLVW9ocqUKLsCC5CXdbqCmblAshOMAS6/keqq/sMZMZ19scR4PsZChSR7A==" crossorigin=""/>
    <script src="https://unpkg.com/leaflet@1.7.1/dist/leaflet.js" integrity="sha512-XQoYMqMTK8LvdxXYG3nZ448hOEQiglfqkJs1NOQV44cWnUrBc8PkAOcXy20w0vlaXaVUearIOBhiXZ5V3ynxwA==" crossorigin=""></script>
    <script src="leaflet.ajax.min.js"></script>
    <script src="leaflet-hash.js"></script>

    <link rel="stylesheet" href="leaflet-legend.css">
    <script src="leaflet-legend.js"></script>

    <link rel="stylesheet" href="easy-button.css">
    <script src="easy-button.js"></script>

	<style>
		html, body {
			height: 100%;
			margin: 0;
            padding: 0;
		}

         #map {
            height: 100%;
            width: 100vw;
            z-index: 0;
        }

        .locator{
            font-size: 1.5em;
        }

        .helpcontainer {
            position: absolute;
            z-index: 10;
            width: 100%;
            bottom: 20px;
        }

        #help {
            padding: 1em 2em;
            margin: 0 auto;
            width: fit-content;
            border-radius: 1.75em;
            background-color: rgb(255, 48, 48);
            display: none;
            text-align: center;
        }

    </style>
</head>
<body>

<div class="helpcontainer">
    <div id="help"></div>
</div>
<div id="map"></div>

<script>
    let helpTimeout = undefined;
    function helpDivTimeout() {
        if (helpTimeout !== undefined) {
            clearTimeout(helpTimeout);
            helpTimeout = undefined;
        }
        helpTimeout = window.setTimeout(function() {
            const help = document.getElementById('help');
            help.style.display = 'none';
            helpTimeout = undefined;
        }, 5000);
    }

    let markers = {};
    // https://mokole.com/palette.html
    const colors = {
        '01': '#ff0000', // Gebäude mit einer Wohnung
        '02': '#ffd700', // Gebäude mit zwei oder mehr Wohnungen
        '03': '#a020f0', // Wohngebäude für Gemeinschaften
        '04': '#ff1493', // Hotels und ähnliche Gebäude
        '05': '#1e90ff', // Bürogebäude
        '06': '#00ff00', // Groß- und Einzelhandelsgebäude
        '07': '#006400', // Gebäude des Verkehrs- und Nachrichtenwesens
        '08': '#00ffff', // Industrie- und Lagergebäude
        '09': '#bc8f8f'  // Gebäude für Kultur- und Freizeitzwecke sowie das Bildungs- und Gesundheitswesen
    };

    // https://leaflet-extras.github.io/leaflet-providers/preview/
    const addrData = 'Adressdaten: &copy; ' +
        '<a href="https://www.bev.gv.at/portal/page?_pageid=713,2601271&_dad=portal&_schema=PORTAL" ' +
        'target="_blank">Österreichisches Adressregister, Stichtagsdaten vom <?php print $bevDate; ?></a>';

    const osm = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        minZoom: 0,
        maxZoom: 20,
        maxNativeZoom:19,
        attribution: addrData+', Map: &copy; <a href="https://openstreetmap.org/copyright" target="_blank">OpenStreetMap contributors</a>'
    });

    const basemap = L.tileLayer('https://maps{s}.wien.gv.at/basemap/geolandbasemap/{type}/google3857/{z}/{y}/{x}.{format}', {
        minZoom: 0,
        maxZoom: 20,
        maxNativeZoom:18,
        attribution: addrData+', Map: <a href="https://www.basemap.at">basemap.at</a>',
        subdomains: ["", "1", "2", "3", "4"],
        type: 'normal',
        format: 'png',
        bounds: [[46.35877, 8.782379], [49.037872, 17.189532]]
    });

    const basemapOrtho = L.tileLayer('https://maps{s}.wien.gv.at/basemap/{type}/normal/google3857/{z}/{y}/{x}.{format}', {
        minZoom: 0,
        maxZoom: 20,
        maxNativeZoom:18,
        attribution: addrData+', Map: <a href="https://www.basemap.at">basemap.at</a>',
        subdomains: ["", "1", "2", "3", "4"],
        type: 'bmaporthofoto30cm',
        format: 'jpeg',
        bounds: [[46.35877, 8.782379], [49.037872, 17.189532]]
    });

    const basemapOverlay = L.tileLayer('https://maps{s}.wien.gv.at/basemap/bmapoverlay/{type}/google3857/{z}/{y}/{x}.{format}', {
        minZoom: 0,
        maxZoom: 20,
        maxNativeZoom:18,
        attribution: addrData+', Map: <a href="https://www.basemap.at">basemap.at</a>',
        subdomains: ["", "1", "2", "3", "4"],
        type: 'normal',
        format: 'png',
        bounds: [[46.35877, 8.782379], [49.037872, 17.189532]]
    });

    const basemapOrthoOverlay = new L.LayerGroup([basemapOrtho, basemapOverlay]);

    const llMapLayers = {
        "Open Street Map": osm,
        "Basemap.at": basemap,
        "Basemap.at Orthofoto": basemapOrtho,
        "Basemap.at Orthofoto mit Overlay": basemapOrthoOverlay
    };

    var southWest = L.latLng(45.5, 8.5),
        northEast = L.latLng(50.0, 18.0),
        bounds = L.latLngBounds(southWest, northEast);

    var map = L.map('map', {
        maxBounds: bounds,
        maxZoom: 20,
        minZoom: 7,
        layers: [osm]
    });

    const layerControl = L.control.layers(llMapLayers).addTo(map);


    function popUp(f,l){
        var out = [];
        if ('properties' in f){
            var addr = '';
            if ('street' in f.properties) {
                addr += f.properties['street'];
            }
            if ('house_number' in f.properties) {
                if (addr != '') { addr += ' '; }
                addr += f.properties['house_number'];
            }
            if (addr != '') { addr += '<br/>'; }
            if ('postcode' in f.properties) {
                addr += f.properties['postcode'];
            }
            if ('municipality' in f.properties) {
                if (addr != '') { addr += ' '; }
                addr += f.properties['municipality'];
            }
            if ('locality' in f.properties && 'municipality' in f.properties && f.properties['municipality'] != f.properties['locality']) {
                if (addr != '') { addr += ' - '; }
                addr += f.properties['locality'];
            }

            if (addr != '') {
                out.push("Adresse: "+addr+"<br/>");
            }


            if ('house_name' in f.properties && f.properties['house_name'] != '') {
                out.push("Haus Name: " + f.properties['house_name']+"<br/>");
            }

            if ('house_attribute_string' in f.properties && f.properties['house_attribute_string'] != '') {
                out.push("Haustyp: " + f.properties['house_attribute_string']+"<br/>");
            }

            if ('house_function_string' in f.properties && f.properties['house_function_string'] != '') {
                out.push("Hausfunktion: " + f.properties['house_function_string']+"<br/>");
            }

            if (window.location.search.substr(1).includes('debug')) {
                for (var prop in f.properties) {
                    out.push("   "+prop+": "+f.properties[prop]);
                }
            }

            l.bindPopup(out.join("<br />"));
        }
    }

    function getBevData(latlng) {
        var url = "api/getBevData/";
        url += '?lat=' + latlng.lat;
        url += '&lon=' + latlng.lng;
        url += '&distance=250';
        url += '&limit=500';
        url += '&epsg=4326';

        var geojsonLayer = new L.GeoJSON.AJAX(url,
            {
                onEachFeature:popUp,
                pointToLayer: function(geoJsonPoint, latlng) {
                    if (latlng in markers) {
                        return null;
                    }
                    markers[latlng] = 1;

                    var markerColor = '#ffbfc2';
                    if ('properties' in geoJsonPoint 
                        && 'house_attribute' in geoJsonPoint.properties
                        && geoJsonPoint.properties['house_attribute'] in colors
                    ) {
                        markerColor = colors[geoJsonPoint.properties['house_attribute']];
                    }
                    return L.circleMarker(latlng, {
                        color: markerColor,
                        fillOpacity: 0.5
                    });
                }
            }
        ).addTo(map);
    }

    if (location.hash == '') {
	    map.locate({setView: true, maxZoom: 16});
    }


    var hash = new L.Hash(map);

    L.easyButton( '<span class="locator">&target;</span>', function(){
	    map.locate({setView: true, maxZoom: 16});
    }).addTo(map);

    var layerFeatureGroup = L.featureGroup([])
        .bindPopup('Hello world!')
        .on('click', function() { alert('Clicked on a member of the group!'); })
        .addTo(map);


    map.on('click', function(e) {
        var latlng = e.latlng;
        getBevData(latlng);
    });

    map.on("moveend", function () {
        getBevData(map.getCenter());
    });

    // Position Label
    let Position = L.Control.extend({
        _container: null,
        options: {
            position: 'bottomright'
        },

        onAdd: function (map) {
            const latlng = L.DomUtil.create('div', 'leaflet-control-layers leaflet-control-layers-expanded');
            this._latlng = latlng;
            return latlng;
        },

        updateHTML: function(lat, lng) {
            //this._latlng.innerHTML = "Latitude: " + lat + "   Longitiude: " + lng;
            this._latlng.innerHTML = "LatLng: " + lat + " " + lng;
        }
    });
    const position = new Position();
    let posStr = '';
    map.addControl(position);
    map.addEventListener('mousemove', (event) => {
        let lat = Math.round(event.latlng.lat * 100000) / 100000;
        let lng = Math.round(event.latlng.lng * 100000) / 100000;
        position.updateHTML(lat, lng);
        posStr = '[' + lat + ', ' + lng + ']';
        const zoomAdd = document.getElementById('checkbox-zoom');
        if (zoomAdd.checked) {
            posStr = '[' + posStr + ', ' + map.getZoom() + ']';
        }
    });


    L.control.legend({
        items: [
            {color: colors['01'], label: 'Gebäude mit einer Wohnung'},
            {color: colors['02'], label: 'Gebäude mit zwei oder mehr Wohnungen'},
            {color: colors['03'], label: 'Wohngebäude für Gemeinschaften'},
            {color: colors['04'], label: 'Hotels und ähnliche Gebäude'},
            {color: colors['05'], label: 'Bürogebäude'},
            {color: colors['06'], label: 'Groß- und Einzelhandelsgebäude'},
            {color: colors['07'], label: 'Gebäude des Verkehrs- und Nachrichtenwesens'},
            {color: colors['08'], label: 'Industrie- und Lagergebäude'},
            {color: colors['09'], label: 'Gebäude für Kultur- und Freizeitzwecke sowie<br>das Bildungs- und Gesundheitswesen'},
            {color: '#ffbfc2', label: 'Adresse ohne Gebäudetyp'},
        ],
        collapsed: true,
        // insert different label for the collapsed legend button.
        buttonHtml: 'Legende der Gebäudetypen'
    }).addTo(map);

    L.control.legend({
        items: [
            {checkboxname: "zoom", label: 'Zoom-Level mitkopieren', checkboxchecked: false},
        ],
        collapsed: true,
        // insert different label for the collapsed legend button.
        buttonHtml: 'Zwischenablage'
    }).addTo(map);

    L.control.scale({
        imperial: false
    }).addTo(map);

    let ctrlActive = false;
    let cActive = false;
    let vActive = false

    document.body.addEventListener('keyup', event => {
        if (event.key == 'Control') ctrlActive = false;
        if (event.code == 'KeyC') cActive = false;
        if (event.code == 'KeyV') vActive = false;
    });

    document.body.addEventListener('keydown', event => {
        if (event.key == 'Control') ctrlActive = true;
        if (ctrlActive == true && event.code == 'KeyC') {

            // this disables the browsers default copy functionality
            event.preventDefault()

            // perform desired action(s) here...
            console.log('Copied LatLng: ' + posStr);
            navigator.clipboard.writeText(posStr).then(function() {
                console.log('Async: Copying to clipboard was successful!');
                const help = document.getElementById('help');
                help.innerHTML = '"' + posStr + '" wurde in die Zwischenablage kopiert';
                help.style.display = 'block';
                helpDivTimeout();
            }, function(err) {
                console.error('Async: Could not copy text: ', err);
            });
        }
    });

    const help = document.getElementById('help');
    help.innerHTML = "Der aktuelle Standort kann mittels Strg-C in die Zwischenablage kopiert werden.";
    help.style.display = 'block';
    helpDivTimeout();

</script>
</body>
</html>

