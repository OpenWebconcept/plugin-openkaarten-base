# Changelog
## v0.3.5
- Fixed: the edit screen of a datalayer with a live URL source could time out (504) on slow or large sources. The external URL was requested again for every read of the source fields mapping (once per group row and subfield) plus once more for the map preview. The response is now cached per URL for the current request, the source fields mapping is computed only once per request, and the map preview reuses the same fetch, so the edit screen makes a single external request. (#86, Thank you @richardkorthuis et al)
- Changed: CMB2 is now required as `cmb2/cmb2` (^2.13) directly from Packagist instead of `wpackagist-plugin/cmb2`; the wpackagist repository has been removed from composer.json. This bumps the bundled CMB2 from 2.11.0 to 2.13.5. Projects that also require `wpackagist-plugin/cmb2` should switch to `cmb2/cmb2` to avoid both packages installing into the same `cmb2` plugin directory. (#85, Thank you @ictbeheer et al)

## v0.3.4
- Added: support for custom marker hex colors. A color picker is now available for the datalayer default marker color and for each per-value marker, so you can override the predefined marker colors with any custom hex value. Custom colors are applied inline on the map, while preset colors keep using their `marker-<name>` CSS classes.

## v0.3.3
- Fixed: maps on the location and datalayer edit screens could stay grey / only load the top-left tile until the browser window was resized. Maps now recalculate their size via a ResizeObserver (and map.whenReady), so they render correctly on initial load and when becoming visible.
- Fixed: addMarker() referenced an undefined `location` variable (the global window.location) for the marker colour/icon; colour and icon are now optional parameters with a sensible CSS default.
- Improved: the openstreetmap-base and openstreetmap-geodata scripts now declare their jquery dependency.

## v0.3.2
- Fixed importing of properties for certain json files.
- Changed the tooltip configurator so the image is fixed at the top and limited to only 1 image per tooltip.

## v0.3.1
- Changed the marker color hex values.
- Fixed converting objects to arrays for locations in the datalayer.

## v0.3.0
- Added settings for default marker color and default marker icon.

## v0.2.9
- Cleanup of dependencies, change origins, load through packagist.

## v0.2.8
- Fix issue with executing cmb2 conditional logic script on all admin pages.

## v0.2.7
- Update package Openkaarten Base Functions.

## v0.2.6
- Flush cache when saving settings.

## v0.2.5
- Added settings for default latitude, longitude and zoom level.
- Fixed bugs for map not showing when there are no markers (AS-21249).

## v0.2.4
- Set default marker color to blue (AS-20050).

## v0.2.2
- Initialize JavaScript only when the div for the map is present (AS-19732).

## v0.2.1
- Update composer dependencies.

## v0.2.0
- Several bug fixes and new features.

## v0.1.2
- Prepare the plugin for the use of the OpenKaarten Geodata Addon Plugin.

## v0.1.1
- Make the plugin compatible with PHP 7.4.

## v0.1.0
- Initial release.
