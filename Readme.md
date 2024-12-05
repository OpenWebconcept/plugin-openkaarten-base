# OpenKaarten Plugin

This plugin adds Datalayers and Locations to WordPress which can be retrieved via the OpenKaarten REST API.

## Requirements

### OpenKaarten

In order to make the OpenKaarten Plugin work, you will need to have a WordPress installation with at least the following installed (and activated):

* [WordPress](https://wordpress.org/)
* [CMB2](https://wordpress.org/plugins/cmb2/)
* [CMB2 Field Type: Flexible Content](https://github.com/acato-plugins/cmb2-flexible-content)

On this WordPress installation you will have to enable pretty permalinks (Settings > Permalinks > Select any of the options that is not plain).

There are two possible setups for the OpenKaarten, this can be:

1. On the WordPress installation of an existing website.
2. On a completely new WordPress installation.

In all scenarios the OpenKaarten needs to have the following installed (and activated):

* [WordPress](https://wordpress.org/)
* [CMB2](https://wordpress.org/plugins/cmb2/)
* [CMB2 Field Type: Flexible Content](https://github.com/acato-plugins/cmb2-flexible-content)
* [OpenKaarten Base](https://github.com/OpenWebconcept/plugin-openkaarten-base)

With this installed you can use the OpenKaarten Base plugin in your WordPress website.

If you chose for option 2 (new WordPress installation), you will probably need to install a WordPress theme. Since the OpenKaarten plugin is a REST API, it can be used in any WordPress theme.

## Works best with

The OpenKaarten Base plugin works best with the following plugins, which can be installed on a different WordPress installation:

- [OpenKaarten Frontend](https://github.com/OpenWebconcept/plugin-openkaarten-frontend-plugin): This plugin adds a map to your WordPress website where you can show the locations of the datalayers.
- [OpenKaarten GeoData](https://github.com/OpenWebconcept/plugin-openkaarten-geodata-for-posts): This plugin adds GeoData fields to the OpenPub Items post type and creates a REST endpoint to retrieve OpenPub Items with geodata.

## Installation

### Manual installation

At this point manual installation is not supported, because of composer dependencies. We are working on this.

### Composer installation

1. `composer source git@github.com:OpenWebconcept/plugin-openkaarten-base.git`
2. `composer require acato/openkaarten-base`
3. `cd /wp-content/plugins/openkaarten-base`
4. `npm install && npm run build`
5. Activate the OpenKaarten Base Plugin through the 'Plugins' menu in WordPress.

## Usage

### Importing datalayers with locations
Datalayers can be imported via the WordPress admin panel. Go to the Datalayers menu, add a New Datalayer and click on the 'Add or Upload file' button. Here you can select a file to import.

The import supports the following file extensions:
- (geo)JSON
- KML
- XML

You can add a datalayer in the following ways:
1. Import a file: Select a file to import. The file will be uploaded, the datalayer will be created and the locations will be imported.
2. Add a URL: Enter a URL to import a file from. The content of the URL will be downloaded, the datalayer will be created and the locations will be imported. You have the option to sync the datalayer. This will import the locations from the URL and update the locations of the datalayer.
3. Link a live URL: Enter a URL to link a live datalayer. The datalayer will be created. The locations won't be imported. The locations will be shown directly from the source file.

### GeoJSON specifications ###
GeoJSON is the preferred format for importing datalayers.

The import requires the following properties (as stated in the GeoJSON specification):
- type: The type of the datalayer (e.g. 'FeatureCollection').
- features: An array of features.

Each feature requires the following properties (as stated in the GeoJSON specification):
- type: The type of the feature (e.g. 'Feature').
- geometry: The geometry of the feature.
- properties: The properties of the feature.

The geometry requires the following properties (as stated in the GeoJSON specification):
- type: The type of the geometry (e.g. 'Point').
- coordinates: The coordinates of the geometry.

More information about the GeoJSON specification can be found [here](https://geojson.org/).

### Adding locations
Locations can be added in two ways:
1. Via the dayalayer importer described above. When importing a datalayer, the locations will be added to as a location post type and will be connected to the datalayer.
2. Manually via the WordPress admin panel. Go to the Locations menu, add a New Location, select a datalayer and fill in the required fields. This will only work for datalayers that use the import function (either via file upload or via URL). When syncing a datalayer, the manually added locations will be removed.

### Title mapping and field mapping
After importing a datalayer file, you can create a title mapping and field mapping for the datalayer. The title mapping is used to determine the title of the location post. The field mapping is used to determine the content of the location post.
Per field you can also select whether the field should be included in the REST API output for this datalayer.

### Customizing map markers
For each datalayer a Leaflet map will be created and a preview of the map with all the connected locations will be shown in the WordPress admin panel.
The map markers can be customized within the datalayer post. First you can create a default marker by selecting a marker color. Additionally, you can create multiple custom marker by selecting the property that should be used to determine the marker color and the marker icon.

### Marker icons
The marker icons are SVG icons that are supplied by the OpenGemeenten Iconenset. More information about the icons can be found [here](https://www.opengemeenten.nl/producten/iconenset).

### Tooltip ###
For each datalayer a tooltip can be created. The tooltip can be customized by selecting the properties that should be shown in the tooltip. The tooltip will be shown in the OpenKaarten Frontend plugin map.

### Deleting datalayers and locations
Datalayers and locations can be deleted via the WordPress admin panel. Go to the Datalayers or Locations menu, hover over the datalayer or location you want to delete and click on the 'Trash' link. If a datalayer is deleted, all connected locations will also be deleted.

## Development

### Coding Standards

Please remember, we use the WordPress PHP Coding Standards for this plugin! (https://make.wordpress.org/core/handbook/best-practices/coding-standards/php/) To check if your changes are compatible with these standards:

*  `cd /wp-content/plugins/openkaarten-base`
*  `composer install` (this step is only needed once after installing the plugin)
*  `./vendor/bin/phpcs --standard=phpcs.xml.dist .`
*  See the output if you have made any errors.
    *  Errors marked with `[x]` can be fixed automatically by phpcbf, to do so run: `./vendor/bin/phpcbf --standard=phpcs.xml.dist .`

N.B. the `composer install` command will also install a git hook, preventing you from committing code that isn't compatible with the coding standards.

### NPM
The plugin uses NPM for managing the JavaScript dependencies and building the leaflet map for showing locations within a datalayer. To install the dependencies, run the following command:
```
npm install
```

To deploy the JavaScript files, run the following command:
```
npm run build
```

To watch the JavaScript files for changes, run the following command:
```
npm run watch
```

### Translations
```
wp i18n make-pot . languages/openkaarten-base.pot --exclude="node_modules/,vendor/" --domain="openkaarten-base"
```

```
cd languages && wp i18n make-json openkaarten-base-nl_NL.po --no-purge
```

### Datalayers and Location Custom Post Types
This plugin adds two custom post types to WordPress:
- Datalayers
- Location

## REST API Endpoints
This plugin adds the following REST API GET-endpoints:
- `/wp-json/owc/openkaarten/v1`
- `/wp-json/owc/openkaarten/v1/datasets`
- `/wp-json/owc/openkaarten/v1/datasets/id/{id}`
- `/wp-json/owc/openkaarten/v1/datasets/id/{id}/{output_format}`

Further documentation about using the REST API can be found in the [OpenKaarten API documentation](https://redocly.github.io/redoc/?url=https://raw.githubusercontent.com/OpenWebconcept/plugin-openkaarten-base/main/openapi/openapi.yaml&nocors).

### REST API Output formats
With the endpoint `/wp-json/owc/openkaarten/v1/datasets/id/{id}/{output_format}` you can specify an output format for a specific datalayer. The following output formats are supported:
- `json` (default if no output format is specified)
- `geojson`
- `kml`
- `gpx`

### Coordinate projection
With the endpoint `/wp-json/owc/openkaarten/v1/datasets/id/{id}?projection=<projection>` you can specify a projection for the output for showing coordinates within a geometry object of a location. The following projections are supported:
- `wgs84` (default if no projection is specified)
- `rd`

## Integration with plugins
This plugin is compatible with the following open source projects:
* [CMB2](https://wordpress.org/plugins/cmb2/)
