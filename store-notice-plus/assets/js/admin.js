/* Store Notice Plus – admin color pickers */
(function($){
  $(function(){
    // Turn every .snp-color-field into a WP color picker
    $('.snp-color-field').each(function(){
      var $input = $(this);
      $input.wpColorPicker({
        // Respect the data-default-color attribute if present
        defaultColor: $input.data('default-color') || false,
        change: function(){ /* live preview could go here if needed */ },
        clear: function(){ /* handle clears if needed */ }
      });
    });
  });
})(jQuery);
