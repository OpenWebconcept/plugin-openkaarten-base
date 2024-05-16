/**
 * Custom jQuery for CMB2 Metaboxes and Fields.
 *
 * @author Acato <eyal@acato.nl>
 */

/**
 * Custom jQuery for CMB2 Metaboxes and Fields
 */
window.CMB2 = window.CMB2 || {};
(function(window, document, $, cmb, undefined){
  'use strict';

  // localization strings
  var setTimeout = window.setTimeout;
  var $document;
  var $id = function( selector ) {
    return $( document.getElementById( selector ) );
  };
  cmb.$id = $id;

  cmb.init = function() {
    $document = $( document );

    var $metabox     = cmb.metabox();
    var $repeatGroup = $metabox.find('.cmb-repeatable-group');

    if ( $repeatGroup.length ) {
      // Hide the marker preview when a new row is added.
      $repeatGroup
        .on( 'cmb2_add_row', cmb.hideMarkerPreview )
    }

    cmb.trigger( 'cmb_init' );
  };

  cmb.hideMarkerPreview = function( evt, row ) {
    row.find('.cmb-type-markerpreview').hide();
  };

  // Kick it off!
  $( cmb.init );

})(window, document, jQuery, window.CMB2);
