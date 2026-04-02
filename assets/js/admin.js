jQuery(function ($) {
  function syncMediaEmptyState($preview) {
    const target = '#' + $preview.attr('id');
    const $empty = $('[data-empty-for="' + target + '"]');
    if (!$empty.length) {
      return;
    }

    if ($preview.attr('src')) {
      $empty.addClass('is-hidden');
    } else {
      $empty.removeClass('is-hidden');
    }
  }

  function syncBrandingPreviewColor(type, value) {
    if (!type || !value) {
      return;
    }
    const preview = document.querySelector('.tm-branding-preview');
    if (!preview) {
      return;
    }
    preview.style.setProperty('--tm-preview-' + type, value);
  }

  if ($.fn.wpColorPicker && $('.tm-color-field').length) {
    $('.tm-color-field').each(function () {
      const $field = $(this);
      const previewColor = String($field.data('preview-color') || '');
      $field.wpColorPicker({
        change: function (event, ui) {
          if (ui && ui.color) {
            syncBrandingPreviewColor(previewColor, ui.color.toString());
          }
        },
        clear: function () {
          syncBrandingPreviewColor(previewColor, '');
        }
      });
    });
  }

  if (typeof wp !== 'undefined' && wp.media && $('.tm-media-select').length) {
    $('.tm-media-select').on('click', function () {
      const $button = $(this);
      const $input = $($button.data('target-input'));
      const $preview = $($button.data('target-preview'));
      const frame = wp.media({
        title: String($button.data('media-title') || ''),
        button: { text: String($button.data('media-button') || '') },
        multiple: false,
        library: { type: 'image' }
      });

      frame.on('select', function () {
        const selection = frame.state().get('selection').first();
        if (!selection) {
          return;
        }
        const attachment = selection.toJSON();
        $input.val(String(attachment.id || 0));
        const previewUrl = (attachment.sizes && attachment.sizes.thumbnail && attachment.sizes.thumbnail.url)
          ? attachment.sizes.thumbnail.url
          : String(attachment.url || '');
        if (previewUrl) {
          $preview.attr('src', previewUrl).removeClass('is-hidden');
          syncMediaEmptyState($preview);
        }
      });

      frame.open();
    });

    $('.tm-media-remove').on('click', function () {
      const $button = $(this);
      const $input = $($button.data('target-input'));
      const $preview = $($button.data('target-preview'));
      $input.val('0');
      $preview.attr('src', '').addClass('is-hidden');
      syncMediaEmptyState($preview);
    });
  }


  $('.tm-confirm-action').on('click', function (event) {
    const message = String($(this).data('confirm-message') || '');
    if (message && !window.confirm(message)) {
      event.preventDefault();
    }
  });

  $('.tm-copy-button').on('click', function () {
    const $button = $(this);
    const original = $button.text();
    const text = String($button.data('copy-text') || '');

    function markCopied() {
      $button.addClass('is-copied').text('Copiado');
      window.setTimeout(function () {
        $button.removeClass('is-copied').text(original);
      }, 1600);
    }

    if (!text) {
      return;
    }

    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(text).then(markCopied).catch(function () {
        const $temp = $('<input type="text" class="tm-copy-fallback" aria-hidden="true">').val(text).appendTo('body');
        $temp[0].select();
        document.execCommand('copy');
        $temp.remove();
        markCopied();
      });
      return;
    }

    const $temp = $('<input type="text" class="tm-copy-fallback" aria-hidden="true">').val(text).appendTo('body');
    $temp[0].select();
    document.execCommand('copy');
    $temp.remove();
    markCopied();
  });

  $('.tm-media-preview').each(function () {
    syncMediaEmptyState($(this));
  });


  function updateVitrineSelectionUI() {
    const $cards = $('[data-vitrine-card]');
    const hiddenCount = $('input[type="hidden"][name="tm_vitrine_items[]"]').length;
    let checkedCount = hiddenCount;

    $cards.each(function () {
      const $card = $(this);
      const $checkbox = $card.find('[data-vitrine-checkbox]').first();
      const isSelected = $checkbox.is(':checked');
      $card.toggleClass('is-selected', isSelected);
      if (isSelected) {
        checkedCount += 1;
      }
    });

    const label = checkedCount === 1
      ? checkedCount + ' seleccionado'
      : checkedCount + ' seleccionados';

    $('[data-vitrine-count]').text(label);
  }

  $(document).on('change', '[data-vitrine-checkbox]', function () {
    updateVitrineSelectionUI();
  });

  $('[data-vitrine-select]').on('click', function () {
    const mode = String($(this).data('vitrine-select') || '');
    $('[data-vitrine-checkbox]').prop('checked', mode === 'visible');
    updateVitrineSelectionUI();
  });

  const categorySelect = $('#tm_category_term_id');
  const subcategorySelect = $('#tm_subcategory_term_id');
  const regionSelect = $('#tm_region_term_id');
  const comunaSelect = $('#tm_comuna_term_id');

  function filterOptions($select, dataKey, selectedValue) {
    const selected = String(selectedValue || '0');
    $select.find('option').each(function () {
      const $option = $(this);
      if (!$option.val()) {
        $option.show();
        return;
      }
      const optionParent = String($option.data(dataKey) || '0');
      if (selected === '0' || optionParent === selected) {
        $option.show();
      } else {
        if ($option.is(':selected')) {
          $select.val('0');
        }
        $option.hide();
      }
    });
  }

  if (categorySelect.length && subcategorySelect.length) {
    filterOptions(subcategorySelect, 'parent-category', categorySelect.val());
    categorySelect.on('change', function () {
      filterOptions(subcategorySelect, 'parent-category', $(this).val());
    });
  }

  if (regionSelect.length && comunaSelect.length) {
    filterOptions(comunaSelect, 'region-id', regionSelect.val());
    regionSelect.on('change', function () {
      filterOptions(comunaSelect, 'region-id', $(this).val());
    });
  }

  function initListingAdminStepper() {
    const $stepper = $('[data-tm-admin-listing-stepper]');
    if (!$stepper.length) {
      return;
    }

    const $buttons = $stepper.find('[data-tm-admin-step]');
    const $panels = $('[data-tm-admin-step-panel]');
    const order = $buttons.map(function () {
      return String($(this).data('tm-admin-step') || '');
    }).get();

    function setActiveStep(stepKey) {
      if (!stepKey) {
        return;
      }
      $buttons.each(function () {
        const $button = $(this);
        const isActive = String($button.data('tm-admin-step') || '') === stepKey;
        $button.toggleClass('is-active', isActive);
        $button.attr('aria-current', isActive ? 'step' : 'false');
      });
      $panels.each(function () {
        const $panel = $(this);
        const isActive = String($panel.data('tm-admin-step-panel') || '') === stepKey;
        $panel.toggleClass('is-active', isActive).prop('hidden', !isActive);
      });
    }

    function moveStep(direction) {
      const currentKey = String($buttons.filter('.is-active').data('tm-admin-step') || order[0] || '');
      const currentIndex = order.indexOf(currentKey);
      const targetIndex = Math.max(0, Math.min(order.length - 1, currentIndex + direction));
      setActiveStep(order[targetIndex] || currentKey);
    }

    $buttons.on('click', function () {
      setActiveStep(String($(this).data('tm-admin-step') || ''));
    });

    $(document).on('click', '[data-tm-admin-next-step]', function () {
      moveStep(1);
    });

    $(document).on('click', '[data-tm-admin-prev-step]', function () {
      moveStep(-1);
    });

    setActiveStep(order[0] || 'commercial');
  }

  initListingAdminStepper();

  updateVitrineSelectionUI();
});
