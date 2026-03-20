document.addEventListener('DOMContentLoaded', function () {
  var maxImages = window.tmFrontend && window.tmFrontend.maxImages ? parseInt(window.tmFrontend.maxImages, 10) : 5;

  function debounce(fn, wait) {
    var timer;
    return function () {
      var context = this;
      var args = arguments;
      clearTimeout(timer);
      timer = setTimeout(function () {
        fn.apply(context, args);
      }, wait);
    };
  }

  function filterOptions(select, dataKey, selectedValue) {
    if (!select) return;
    Array.prototype.slice.call(select.options).forEach(function (option, index) {
      if (index === 0) {
        option.hidden = false;
        return;
      }
      var value = option.getAttribute(dataKey);
      option.hidden = !!selectedValue && value !== selectedValue;
      if (option.hidden && option.selected) option.selected = false;
    });
  }

  function bindDependentSelect(parent, child, dataKey) {
    if (!parent || !child) return;
    filterOptions(child, dataKey, parent.value);
    parent.addEventListener('change', function () {
      filterOptions(child, dataKey, this.value);
      child.value = '';
      child.dispatchEvent(new Event('change', { bubbles: true }));
    });
  }

  bindDependentSelect(document.getElementById('tm_category_term_id'), document.getElementById('tm_subcategory_term_id'), 'data-parent-category');
  bindDependentSelect(document.getElementById('tm_region_term_id'), document.getElementById('tm_comuna_term_id'), 'data-region-id');
  bindDependentSelect(document.getElementById('tm_filter_category'), document.getElementById('tm_filter_subcategory'), 'data-parent-category');
  bindDependentSelect(document.getElementById('tm_filter_region'), document.getElementById('tm_filter_comuna'), 'data-region-id');
  bindDependentSelect(document.getElementById('tm_alert_category_term_id'), document.getElementById('tm_alert_subcategory_term_id'), 'data-parent-category');
  bindDependentSelect(document.getElementById('tm_alert_region_term_id'), document.getElementById('tm_alert_comuna_term_id'), 'data-region-id');

  var imageInput = document.getElementById('tm_images');
  var preview = document.getElementById('tm-upload-preview');
  if (imageInput && preview) {
    imageInput.addEventListener('change', function () {
      preview.innerHTML = '';
      if (!this.files || !this.files.length) return;
      if (this.files.length > maxImages) {
        alert(window.tmFrontend && window.tmFrontend.strings ? window.tmFrontend.strings.maxImages : 'Máximo de imágenes superado.');
        this.value = '';
        return;
      }
      Array.prototype.forEach.call(this.files, function (file) {
        if (!file.type.match(/^image\//)) return;
        var reader = new FileReader();
        reader.onload = function (event) {
          var wrap = document.createElement('div');
          wrap.className = 'tm-preview-image';
          wrap.innerHTML = '<img src="' + event.target.result + '" alt=""><span>' + file.name + '</span>';
          preview.appendChild(wrap);
        };
        reader.readAsDataURL(file);
      });
    });
  }

  var thumbs = document.querySelectorAll('.tm-gallery-thumb');
  var mainImage = document.getElementById('tm-main-image');
  if (thumbs.length && mainImage) {
    thumbs.forEach(function (thumb) {
      thumb.addEventListener('click', function () {
        thumbs.forEach(function (item) { item.classList.remove('is-active'); });
        this.classList.add('is-active');
        mainImage.src = this.getAttribute('data-full-image') || mainImage.src;
        mainImage.alt = this.querySelector('img') ? (this.querySelector('img').getAttribute('alt') || mainImage.alt) : mainImage.alt;
      });
    });
  }

  var vitrine = document.querySelector('[data-tm-vitrine]');
  if (vitrine) {
    var items = vitrine.querySelectorAll('.tm-vitrine__item');
    var current = 0;
    if (items.length > 1) {
      var dotsWrap = document.createElement('div');
      dotsWrap.className = 'tm-vitrine__dots';
      items.forEach(function (_, index) {
        var dot = document.createElement('button');
        dot.type = 'button';
        dot.className = 'tm-vitrine__dot' + (index === 0 ? ' is-active' : '');
        dot.setAttribute('aria-label', 'Ir al destacado ' + (index + 1));
        dot.addEventListener('click', function () {
          items[current].classList.remove('is-active');
          dotsWrap.children[current].classList.remove('is-active');
          current = index;
          items[current].classList.add('is-active');
          dotsWrap.children[current].classList.add('is-active');
        });
        dotsWrap.appendChild(dot);
      });
      vitrine.appendChild(dotsWrap);
      setInterval(function () {
        items[current].classList.remove('is-active');
        dotsWrap.children[current].classList.remove('is-active');
        current = (current + 1) % items.length;
        items[current].classList.add('is-active');
        dotsWrap.children[current].classList.add('is-active');
      }, 4500);
    }
  }

  var filterForm = document.getElementById('tm-archive-filters');
  var resultsWrap = document.getElementById('tm-results');
  if (filterForm && resultsWrap && window.tmFrontend) {
    var loadingBanner = document.createElement('div');
    loadingBanner.className = 'tm-loading-banner';
    loadingBanner.setAttribute('aria-live', 'polite');
    loadingBanner.textContent = window.tmFrontend.strings ? window.tmFrontend.strings.loading : 'Cargando avisos...';
    resultsWrap.parentNode.insertBefore(loadingBanner, resultsWrap);

    function cleanParams(params) {
      Array.from(params.keys()).forEach(function (key) {
        var value = params.get(key);
        if (value === '' || value === '0' || value === null) {
          params.delete(key);
        }
      });
      return params;
    }

    function toggleLoading(isLoading) {
      resultsWrap.classList.toggle('is-loading', !!isLoading);
      resultsWrap.setAttribute('aria-busy', isLoading ? 'true' : 'false');
      loadingBanner.classList.toggle('is-visible', !!isLoading);
    }

    function updateResults(page, shouldScroll) {
      var formData = new FormData(filterForm);
      formData.set('action', 'tm_filter_listings');
      formData.set('nonce', window.tmFrontend.nonce);
      formData.set('tm_page', page || formData.get('tm_page') || '1');
      toggleLoading(true);
      fetch(window.tmFrontend.ajaxUrl, { method: 'POST', body: formData, credentials: 'same-origin' })
        .then(function (response) { return response.json(); })
        .then(function (payload) {
          if (payload && payload.success && payload.data && payload.data.html) {
            resultsWrap.innerHTML = payload.data.html;
            var params = cleanParams(new URLSearchParams(new FormData(filterForm)));
            params.set('tm_page', formData.get('tm_page'));
            if (params.get('tm_page') === '1') params.delete('tm_page');
            var query = params.toString();
            history.replaceState({}, '', window.location.pathname + (query ? '?' + query : ''));
            if (shouldScroll) {
              resultsWrap.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
          }
        })
        .catch(function () {})
        .finally(function () { toggleLoading(false); });
    }

    var debouncedTextUpdate = debounce(function () { updateResults('1', false); }, 350);

    filterForm.addEventListener('submit', function (event) {
      event.preventDefault();
      updateResults('1', true);
    });

    filterForm.querySelectorAll('select,input[type="checkbox"],input[type="number"]').forEach(function (field) {
      field.addEventListener('change', function () {
        updateResults('1', false);
      });
    });

    filterForm.querySelectorAll('input[type="text"]').forEach(function (field) {
      field.addEventListener('input', debouncedTextUpdate);
    });

    resultsWrap.addEventListener('click', function (event) {
      var pageLink = event.target.closest('.tm-page-link');
      if (!pageLink) return;
      event.preventDefault();
      updateResults(pageLink.getAttribute('data-page') || '1', true);
    });
  }
});
