;(function($){
  "use strict";

  $(document).ready(function(){

    $('body').on('click', '.input-with-copy-btn .copy-btn', function(e) {
      e.preventDefault();

      $(this).parent().find('input').select();
      document.execCommand('copy');
    })
  });
  
})(jQuery);