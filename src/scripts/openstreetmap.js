import L from "leaflet";
import { MarkerClusterGroup } from "leaflet.markercluster/src";

// Retrieve the locations and map configuration from the global window object.
const { mapLocations, minLat, maxLat, minLong, maxLong, centerLat, centerLong, defaultZoom, fitBounds, allowClick } = window.leaflet_vars;
const locationItems = mapLocations ? JSON.parse(mapLocations) : [];

// Set the map configuration.
const config = {
  "centerX": centerLat,
  "centerY": centerLong,
  "minimumZoom": 4,
  "maximumZoom": 16,
  "defaultZoom": defaultZoom,
  "enableHomeControl": true,
  "enableZoomControl": true,
  "enableBoxZoomControl": true,
  "maxBounds": [
    [
      minLat,
      minLong,
    ],
    [
      maxLat,
      maxLong
    ]
  ],
}

// Create the map with the specified configuration.
const map = new L.Map('map', {
  center: [config.centerY, config.centerX],
  zoom: config.defaultZoom,
  minZoom: config.minimumZoom,
  maxZoom: config.maximumZoom,
  maxBounds: fitBounds ? config.maxBounds : null,
  boxZoom: config.enableBoxZoomControl,
  defaultExtentControl: config.enableHomeControl
});

if ( fitBounds ) {
  map.fitBounds( [
    [minLat, minLong],
    [maxLat, maxLong]
  ] )
}

// Add the OpenStreetMap tile layer to the map.
L.tileLayer('https://{s}.tile.osm.org/{z}/{x}/{y}.png', {
  attribution: '&copy; <a href="https://osm.org/copyright">OpenStreetMap</a> contributors'
}).addTo(map);

console.log(allowClick);

if ( allowClick ) {
  map.on( 'click', function (e) {
    var coord = e.latlng;
    var lat = coord.lat;
    var lng = coord.lng;

    // Remove the existing markers.
    map.eachLayer( function (layer) {
      if (layer instanceof L.Marker) {
        map.removeLayer( layer );
      }
    } );

    // Add a draggable marker to the map.
    // Create a custom marker icon with the location color and icon.
    let customIconHtml = "<div style='background-color:" + location.color + ";' class='marker-pin'></div>";
    if (location.icon) {
      customIconHtml += "<span class='marker-icon'><img src='" + location.icon + "'  alt='marker icon' /></span>";
    }

    var customIcon = L.divIcon( {
      className: 'leaflet-custom-icon',
      html: customIconHtml,
      iconSize: [30, 42],
      iconAnchor: [15, 42]
    } );

    let iconOptions = {
      icon: customIcon,
      draggable: 'true'
    }

    var marker = L.marker( [lat, lng], iconOptions );
    marker.on( 'dragend', function (event) {
      var marker = event.target;
      var position = marker.getLatLng();
      marker.setLatLng( new L.LatLng( position.lat, position.lng ), {draggable: 'true'} );
      map.panTo( new L.LatLng( position.lat, position.lng ) );

      // Update the form fields with the new coordinates.
      updateGeoFields( position.lat, position.lng );
    } );
    map.addLayer( marker );

    // Update the form fields with the new coordinates.
    updateGeoFields( lat, lng );
  } );
}

function updateGeoFields(lat, lng) {
  if ( document.getElementById('field_geo_latitude') !== null ) {
    document.getElementById( 'field_geo_latitude' ).value = lat;
  }
  if ( document.getElementById('field_geo_longitude') !== null ) {
    document.getElementById( 'field_geo_longitude' ).value = lng;
  }
}

// Add locations to the map as markers.
if ( locationItems.length !== 0 ) {
  // Create a marker cluster group for the locations.
  const markers = new MarkerClusterGroup({
    disableClusteringAtZoom: 13,
    maxClusterRadius: 40,
    iconCreateFunction: function(cluster) {
      return L.divIcon({
        className: 'leaflet-custom-icon',
        html: "<div class='cluster-pin'></div><span class='cluster-count'>" + cluster.getChildCount() + "</span>",
        iconSize: [30, 42],
        iconAnchor: [15, 42]
      });
    }
  });

  for (let i = 0; i < locationItems.length; i++) {
    const location = locationItems[i];
    const lat = parseFloat( location.lat );
    const lng = parseFloat( location.long );
    const content = location.content;

    // Create a custom marker icon with the location color and icon.
    let customIconHtml = "<div style='background-color:" + location.color + ";' class='marker-pin'></div>";
    if (location.icon) {
      customIconHtml += "<span class='marker-icon'><img src='" + location.icon + "'  alt='marker icon' /></span>";
    }

    var customIcon = L.divIcon( {
      className: 'leaflet-custom-icon',
      html: customIconHtml,
      iconSize: [30, 42],
      iconAnchor: [15, 42]
    } );

    let iconOptions = {
      icon: customIcon
    }

    // Add the marker to the map.
    const marker = L.marker( [lat, lng], iconOptions ).addTo( map );
    marker.bindPopup( content );

    // Add the marker to the cluster group.
    markers.addLayer( marker );
  }

  // Add the marker cluster group to the map.
  map.addLayer( markers );
}
