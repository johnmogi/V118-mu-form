<script>
jQuery(function ($) {
  const $submit = $('#submit-form');

  function apply() {
    const onStep4 = $('.form-step[data-step="4"]').hasClass('active');

    if (onStep4) {
      // Override the CSS rule with inline !important
      $submit[0].style.setProperty('display', 'inline-block', 'important');
      $('.next-btn').hide();
    } else {
      $submit[0].style.setProperty('display', 'none', 'important');
      $('.next-btn').show();
    }
  }

  // Initial run
  apply();

  // Re-run after nav clicks (give the DOM a tick to flip classes)
  $(document).on('click', '.next-btn, .prev-btn', function () {
    setTimeout(apply, 50);
  });

  // Also react if something else toggles the classes
  const obs = new MutationObserver(apply);
  $('.form-step').each(function () {
    obs.observe(this, { attributes: true, attributeFilter: ['class'] });
  });
});
</script>
