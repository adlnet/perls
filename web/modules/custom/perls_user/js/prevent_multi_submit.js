(function (Drupal, $) {
  Drupal.behaviors.preventMultiSubmit = {
    attach: function () {
      $('.user-register-form').submit(function() {
        $(this).submit(function (e) {
          e.preventDefault();
        });
        $('#edit-submit').attr("style", function(i, s) {
          // Determine theme context; overide style rules accordingly.
          return (s || "") + ($('body').hasClass('adminimal-theme') ? 'background: #ededed; border-color: inherit; cursor: not-allowed;' : 'background-color: transparent !important;');
        });
        // Disabling a form submit button will prevent the form from submitting.
        // To get around that you can set a timeout so the form submits, and you
        // still get the desired prevention of a user getting frustrated and
        // clicking the submit multiple times the form seems to hang.
        setTimeout(() => { $('#edit-submit').prop('disabled', true); }, 500);
      });
    }

  };
}(Drupal, jQuery));
