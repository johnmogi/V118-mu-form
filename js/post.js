jQuery(function ($) {
  const $submit = $('#submit-form');

  function apply() {
    const onStep1 = $('.form-step[data-step="1"]').hasClass('active');
    const onStep2 = $('.form-step[data-step="2"]').hasClass('active');
    const onStep4 = $('.form-step[data-step="4"]').hasClass('active');

    if (onStep4) {
      // Step 4: Show submit button, hide next button
      $submit[0].style.setProperty('display', 'inline-block', 'important');
      $('.next-btn').hide();
    } else if (onStep1) {
      // Step 1: Hide next button initially (will be shown when validation passes)
      $submit[0].style.setProperty('display', 'none', 'important');
      $('.next-btn').hide();
    } else {
      // Steps 2-3: Hide submit button, show next button
      $submit[0].style.setProperty('display', 'none', 'important');
      $('.next-btn').show();
    }

    // Step 2 ID field validation tooltip
    if (onStep2) {
      handleStep2Validation();
    }
  }

  function handleStep2Validation() {
    const $nextBtn = $('.next-btn');
    const $idField = $('#id_number');
    
    // Add tooltip when next button is clicked but validation fails
    $nextBtn.off('click.idTooltip').on('click.idTooltip', function(e) {
      const idValue = $idField.val().trim();
      const idRegex = /^[\d-]{8,9}$/;
      
      if (!idValue || !idRegex.test(idValue)) {
        e.preventDefault();
        e.stopPropagation();
        
        // Remove existing tooltip
        $('.id-validation-tooltip').remove();
        
        // Create tooltip
        const tooltip = $('<div class="id-validation-tooltip" style="position: absolute; background: #dc3545; color: white; padding: 8px 12px; border-radius: 4px; font-size: 14px; z-index: 1000; box-shadow: 0 2px 8px rgba(0,0,0,0.2);">אנא הזן מספר זהות תקין (8-9 ספרות)</div>');
        
        // Position tooltip above ID field
        const fieldOffset = $idField.offset();
        tooltip.css({
          top: fieldOffset.top - 45,
          left: fieldOffset.left,
          direction: 'rtl'
        });
        
        $('body').append(tooltip);
        
        // Highlight the field
        $idField.css({
          'border-color': '#dc3545 !important',
          'box-shadow': '0 0 0 3px rgba(220, 53, 69, 0.25) !important'
        });
        
        // Remove tooltip after 3 seconds
        setTimeout(() => {
          tooltip.fadeOut(300, () => tooltip.remove());
          $idField.css({
            'border-color': '',
            'box-shadow': ''
          });
        }, 3000);
        
        return false;
      }
    });
  }

  // Fix broken image preview
  function fixImagePreview() {
    const $previewImg = $('#preview_image');
    $previewImg.on('error', function() {
      $(this).hide();
    });
    
    // Only show image when src is actually set
    $previewImg.on('load', function() {
      if (this.src && this.src !== window.location.href) {
        $(this).show();
      }
    });
  }

  // Initial run
  apply();
  fixImagePreview();

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
