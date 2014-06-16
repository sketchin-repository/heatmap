function drawOpenLayerMap(data) {
    var maxValue = 0;
    console.log(data);
    for (var i in data.locations) {
        maxValue = (maxValue + data.locations[i].count) / 2;
    }

    var transformedData = {
            max: 16000,
            data: []
        },
        data = data.locations,
        datalen = data.length,
        nudata = [];

    // in order to use the OpenLayers Heatmap Layer we have to transform our data into 
    // { max: <max>, data: [{lonlat: <OpenLayers.LonLat>, count: <count>},...]}

    while (datalen--) {
        var utm = "+proj=utm +zone=32";
        var wgs84 = "+proj=longlat +ellps=WGS84 +datum=WGS84 +no_defs";
        var lonlat = proj4(utm, wgs84, [data[datalen].lng, data[datalen].lat]);

        nudata.push({
            lonlat: new OpenLayers.LonLat(lonlat[0], lonlat[1]),
            count: data[datalen].count
        });
    }
    transformedData.data = nudata;

    map = new OpenLayers.Map('heatmap', {
        zoomDuration: 0
    });

    layer = new OpenLayers.Layer.OSM();
    //layer = new OpenLayers.Layer.Google("Google Streets",{numZoomLevels: 20});

    // configure heatmap layer
    var config = {
        "radius": 45,
        "element": "heatmap-scale",
        "visible": true,
        "opacity": 60,
        "gradient": {
            0.45: "rgb(0,0,255)",
            0.55: "rgb(0,255,255)",
            0.65: "rgb(0,255,0)",
            0.95: "yellow",
            1.0: "rgb(237,69,17)"
        },
        "legend": {
            position: 'bl',
            title: 'Sessioni utente'
        }
    };

    // create heatmap layer
    heatmap = new OpenLayers.Layer.Heatmap("Heatmap Layer", map, layer, config, {
        isBaseLayer: false,
        opacity: 0.3,
        projection: new OpenLayers.Projection("EPSG:4326")
    });

    map.addLayers([layer, heatmap]);
    map.setCenter(new OpenLayers.LonLat(10.219444, 45.538889).transform('EPSG:4326', 'EPSG:3857'), 9);

    heatmap.setDataSet(transformedData);
};

function drawGoogleMap(data) {

    // standard gmaps initialization
    var myLatlng = new google.maps.LatLng(45.538889, 10.219444);

    // define map properties
    var myOptions = {
        zoom: 10,
        center: myLatlng,
        mapTypeId: google.maps.MapTypeId.ROADMAP,
        disableDefaultUI: false,
        scrollwheel: true,
        draggable: true,
        navigationControl: true,
        mapTypeControl: false,
        scaleControl: true,
        disableDoubleClickZoom: false
    };

    // we'll use the heatmapArea 
    var map = new google.maps.Map($("#heatmap")[0], myOptions);

    // let's create a heatmap-overlay
    // with heatmap config properties
    // configure heatmap layer
    var config = {
        "radius": 45,
        "element": "heatmap-scale",
        "visible": true,
        "opacity": 60,
        "gradient": {
            0.45: "rgb(0,0,255)",
            0.55: "rgb(0,255,255)",
            0.65: "rgb(0,255,0)",
            0.95: "yellow",
            1.0: "rgb(237,69,17)"
        },
        "legend": {
            position: 'bl',
            title: 'Sessioni utente'
        }
    };

    var heatmap = new HeatmapOverlay(map, config);


    // conver utm to lat lng
    for (var i in data.locations) {
        var utm = "+proj=utm +zone=32";
        var wgs84 = "+proj=longlat +ellps=WGS84 +datum=WGS84 +no_defs";
        var lonlat = proj4(utm, wgs84, [data.locations[i].lng, data.locations[i].lat]);
        data.locations[i].lat = lonlat[1];
        data.locations[i].lng = lonlat[0];
    }

    // here is our dataset
    // important: a datapoint now contains lat, lng and count property!    
    var testData = {
        max: 16000,
        data: data.locations
    };

    // now we can set the data
    google.maps.event.addListenerOnce(map, "idle", function () {
        // this is important, because if you set the data set too early, the latlng/pixel projection doesn't work
        heatmap.setDataSet(testData);
    });
}

$(document).ready(function () {
    $.ajax({
        url: "/assets/data/analytics.json",
        dataType: "json",
        success: function (data) {
            drawGoogleMap(data);
        }
    });
});