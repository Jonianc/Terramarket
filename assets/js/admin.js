jQuery(function ($) {
  if ($.fn.wpColorPicker && $('.tm-color-field').length) {
    $('.tm-color-field').wpColorPicker();
  }

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
});
