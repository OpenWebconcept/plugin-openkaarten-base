import L from "leaflet";
import { MarkerClusterGroup } from "leaflet.markercluster/src";

// Retrieve the locations and map configuration from the global window object.
const { mapLocations, minLat, maxLat, minLong, maxLong, centerLat, centerLong, defaultZoom, fitBounds } = window.leaflet_vars;
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
const map = new L.Map('map-base', {
  center: [config.centerY, config.centerX],
  zoom: config.defaultZoom,
  minZoom: config.minimumZoom,
  maxZoom: config.maximumZoom,
  maxBounds: fitBounds ? config.maxBounds : null,
  boxZoom: config.enableBoxZoomControl,
  defaultExtentControl: config.enableHomeControl,
  enableZoomControl: config.enableZoomControl,
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

// Add locations to the map as markers.
if ( locationItems.length !== 0 ) {
  for (let i = 0; i < locationItems.length; i++) {
    const location = locationItems[i];
    const geojsonData = location.feature;
    const content = location.content;

    // Create a custom marker icon with the location color and icon.
    let customIconHtml = "<div style='background-color:" + location.color + ";' class='marker-pin'></div>";
    if (location.icon) {
      customIconHtml += "<span class='marker-icon'><img src='" + location.icon + "'  alt='marker icon' /></span>";
    }

    let customIcon = L.divIcon( {
      className: 'leaflet-custom-icon',
      html: customIconHtml,
      iconSize: [30, 42],
      iconAnchor: [15, 42]
    } );

    var geojsonLayer = new L.GeoJSON( geojsonData, {
      pointToLayer: function(feature, latlng) {
        return L.marker(latlng, { icon: customIcon }).bindPopup( content );
      }
    }).addTo(map);
  }
}
