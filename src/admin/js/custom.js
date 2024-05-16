jQuery( document ).ready( function( $ ) {
  'use strict';

  $("#show_check_all").on("click", function(event){
    var $el = $(this);
    toggleAllShow( $el );
  });

  $("#required_check_all").on("click", function(event){
    var $el = $(this);
    toggleAllRequired( $el );
  });

  function toggleAllShow( $el ) {
    // Toggle all checkboxes which end with '_field_show'.
    $('input[id$="_field_show"]').prop( 'checked', $el.prop( 'checked' ) );
  }

  function toggleAllRequired( $el, $field_name ) {
    // Toggle all checkboxes which end with '_field_required'.
    $('input[id$="_field_required"]').prop( 'checked', $el.prop( 'checked' ) );
  }

});
