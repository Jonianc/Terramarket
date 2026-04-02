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


  function getSelectLabel(field) {
    if (!field || !field.options || field.selectedIndex < 0) return '';
    var option = field.options[field.selectedIndex];
    return option ? String(option.text || '').trim() : '';
  }

  function getFieldValue(field) {
    if (!field) return '';
    if (field.tagName === 'SELECT') return getSelectLabel(field);
    return String(field.value || '').trim();
  }

  function formatCurrency(value) {
    var amount = parseInt(value, 10);
    if (!amount) return '';
    if (window.Intl && Intl.NumberFormat) {
      return new Intl.NumberFormat('es-CL', { style: 'currency', currency: 'CLP', maximumFractionDigits: 0 }).format(amount);
    }
    return '$' + String(amount);
  }

  function ensureFieldError(field) {
    var fieldWrap = field && field.closest('.tm-field');
    if (!fieldWrap) return null;
    var error = fieldWrap.querySelector('.tm-field-error');
    if (!error) {
      error = document.createElement('p');
      error.className = 'tm-field-error';
      error.setAttribute('aria-live', 'polite');
      fieldWrap.appendChild(error);
    }
    return error;
  }

  function setFieldError(field, message) {
    if (!field) return;
    var error = ensureFieldError(field);
    field.classList.toggle('is-invalid', !!message);
    if (error) {
      error.textContent = message || '';
      error.hidden = !message;
    }
  }

  function initSubmitWizard() {
    var form = document.querySelector('[data-tm-submit-wizard]');
    if (!form) return;

    var steps = Array.prototype.slice.call(form.querySelectorAll('[data-tm-step]'));
    var stepperButtons = Array.prototype.slice.call(document.querySelectorAll('[data-tm-go-step]'));
    var currentStepInput = form.querySelector('[data-tm-current-step]');
    var currentStep = currentStepInput ? parseInt(currentStepInput.value || '1', 10) : 1;
    var prevSidebar = form.querySelector('[data-tm-prev-step-sidebar]');
    var nextSidebar = form.querySelector('[data-tm-next-step-sidebar]');
    var currentStepLabel = form.querySelector('[data-tm-current-step-label]');
    var currentStepCount = form.querySelector('[data-tm-current-step-count]');
    var progressBar = form.querySelector('[data-tm-progress-bar]');
    var imageField = form.querySelector('#tm_images');
    var deleteMediaInputs = Array.prototype.slice.call(form.querySelectorAll('input[name="tm_delete_media[]"]'));
    var totalSteps = steps.length;
    var existingImages = parseInt(form.getAttribute('data-existing-images') || '0', 10);

    function activeRemainingImages() {
      var deleted = deleteMediaInputs.filter(function (input) { return input.checked; }).length;
      return Math.max(0, existingImages - deleted);
    }

    function updateSummary() {
      var title = form.querySelector('#tm_title');
      var price = form.querySelector('#tm_price_clp');
      var category = form.querySelector('#tm_category_term_id');
      var subcategory = form.querySelector('#tm_subcategory_term_id');
      var region = form.querySelector('#tm_region_term_id');
      var comuna = form.querySelector('#tm_comuna_term_id');
      var condition = form.querySelector('#tm_condition_term_id');
      var status = form.querySelector('#tm_listing_status');
      var summaries = {
        title: getFieldValue(title) || 'Sin título todavía',
        price: formatCurrency(price && price.value) || 'Pendiente',
        category: getFieldValue(category) && getFieldValue(category) !== 'Seleccionar' ? getFieldValue(category) : 'Pendiente',
        subcategory: getFieldValue(subcategory) && getFieldValue(subcategory) !== 'Seleccionar' ? getFieldValue(subcategory) : 'Pendiente',
        condition: getFieldValue(condition) && getFieldValue(condition) !== 'Seleccionar' ? getFieldValue(condition) : 'Pendiente',
        status: getFieldValue(status) || 'Activo'
      };
      var locationParts = [];
      if (getFieldValue(comuna) && getFieldValue(comuna) !== 'Seleccionar') locationParts.push(getFieldValue(comuna));
      if (getFieldValue(region) && getFieldValue(region) !== 'Seleccionar') locationParts.push(getFieldValue(region));
      summaries.location = locationParts.join(', ') || 'Pendiente';

      Object.keys(summaries).forEach(function (key) {
        var target = form.querySelector('[data-tm-summary="' + key + '"]');
        if (target) target.textContent = summaries[key];
      });

      var totalImages = activeRemainingImages() + (imageField && imageField.files ? imageField.files.length : 0);
      var imageSummary = form.querySelector('[data-tm-summary="images_count"]');
      if (imageSummary) {
        imageSummary.textContent = totalImages === 1 ? '1 imagen' : String(totalImages) + ' imágenes';
      }
    }

    function validateImagesStep(stepElement) {
      if (!stepElement || String(stepElement.getAttribute('data-tm-step')) !== '3') return true;
      if (!imageField) return true;
      var totalImages = activeRemainingImages() + (imageField.files ? imageField.files.length : 0);
      if (totalImages < 1) {
        setFieldError(imageField, 'Debes mantener o subir al menos una imagen.');
        return false;
      }
      if (totalImages > maxImages) {
        setFieldError(imageField, 'Máximo permitido: ' + String(maxImages) + ' imágenes.');
        return false;
      }
      setFieldError(imageField, '');
      return true;
    }

    function validateStep(stepElement) {
      if (!stepElement) return true;
      var isValid = true;
      Array.prototype.slice.call(stepElement.querySelectorAll('input, select, textarea')).forEach(function (field) {
        if (!field.required || field.disabled || field.type === 'hidden' || field.type === 'file') return;
        if (field.closest('[hidden]')) return;
        var message = '';
        if (!field.checkValidity()) {
          message = field.validationMessage || 'Completa este campo.';
        }
        if (field.tagName === 'SELECT' && !field.value) {
          message = 'Selecciona una opción.';
        }
        setFieldError(field, message);
        if (message && isValid) {
          isValid = false;
        }
      });
      if (!validateImagesStep(stepElement)) {
        isValid = false;
      }
      return isValid;
    }

    function goToStep(stepNumber) {
      currentStep = Math.max(1, Math.min(totalSteps, stepNumber));
      steps.forEach(function (step) {
        var stepIndex = parseInt(step.getAttribute('data-tm-step') || '0', 10);
        var isActive = stepIndex === currentStep;
        step.hidden = !isActive;
        step.classList.toggle('is-active', isActive);
      });
      stepperButtons.forEach(function (button) {
        var isActive = parseInt(button.getAttribute('data-tm-go-step') || '0', 10) === currentStep;
        button.classList.toggle('is-active', isActive);
        button.setAttribute('aria-current', isActive ? 'step' : 'false');
      });
      if (currentStepInput) currentStepInput.value = String(currentStep);
      if (currentStepLabel) {
        var sourceButton = stepperButtons.find(function (button) {
          return parseInt(button.getAttribute('data-tm-go-step') || '0', 10) === currentStep;
        });
        currentStepLabel.textContent = sourceButton ? String(sourceButton.getAttribute('data-tm-step-label') || '') : '';
      }
      if (currentStepCount) currentStepCount.textContent = 'Paso ' + String(currentStep) + ' de ' + String(totalSteps);
      if (progressBar) progressBar.style.width = String((currentStep / totalSteps) * 100) + '%';
      if (prevSidebar) prevSidebar.disabled = currentStep === 1;
      if (nextSidebar) {
        nextSidebar.disabled = currentStep === totalSteps;
        nextSidebar.hidden = currentStep === totalSteps;
      }
      updateSummary();

      var activeStepperButton = stepperButtons.find(function (button) {
        return parseInt(button.getAttribute('data-tm-go-step') || '0', 10) === currentStep;
      });
      if (activeStepperButton && typeof activeStepperButton.scrollIntoView === 'function' && window.matchMedia('(max-width: 1100px)').matches) {
        activeStepperButton.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' });
      }
      if (window.matchMedia('(max-width: 767px)').matches) {
        var activeStep = form.querySelector('[data-tm-step].is-active');
        if (activeStep && typeof activeStep.scrollIntoView === 'function') {
          activeStep.scrollIntoView({ behavior: 'smooth', block: 'start', inline: 'nearest' });
        }
      }
    }

    function focusFirstInvalid(stepElement) {
      var invalid = stepElement.querySelector('.is-invalid');
      if (!invalid) return;
      if (typeof invalid.scrollIntoView === 'function') {
        invalid.scrollIntoView({ behavior: 'smooth', block: 'center', inline: 'nearest' });
      }
      if (typeof invalid.focus === 'function') invalid.focus();
    }

    form.querySelectorAll('input, select, textarea').forEach(function (field) {
      var eventName = field.tagName === 'SELECT' || field.type === 'file' ? 'change' : 'input';
      field.addEventListener(eventName, function () {
        if (field.classList.contains('is-invalid')) {
          if (field.type === 'file') {
            validateImagesStep(field.closest('[data-tm-step]'));
          } else {
            setFieldError(field, field.checkValidity() ? '' : (field.tagName === 'SELECT' && !field.value ? 'Selecciona una opción.' : (field.validationMessage || 'Completa este campo.')));
          }
        }
        updateSummary();
      });
      if (eventName !== 'change') {
        field.addEventListener('change', updateSummary);
      }
    });

    deleteMediaInputs.forEach(function (input) {
      input.addEventListener('change', updateSummary);
    });

    form.querySelectorAll('[data-tm-next-step]').forEach(function (button) {
      button.addEventListener('click', function () {
        var activeStep = form.querySelector('[data-tm-step].is-active');
        if (!validateStep(activeStep)) {
          focusFirstInvalid(activeStep);
          return;
        }
        goToStep(currentStep + 1);
      });
    });

    form.querySelectorAll('[data-tm-prev-step]').forEach(function (button) {
      button.addEventListener('click', function () {
        goToStep(currentStep - 1);
      });
    });

    if (prevSidebar) {
      prevSidebar.addEventListener('click', function () {
        goToStep(currentStep - 1);
      });
    }

    if (nextSidebar) {
      nextSidebar.addEventListener('click', function () {
        var activeStep = form.querySelector('[data-tm-step].is-active');
        if (!validateStep(activeStep)) {
          focusFirstInvalid(activeStep);
          return;
        }
        goToStep(currentStep + 1);
      });
    }

    stepperButtons.forEach(function (button) {
      button.addEventListener('click', function () {
        var targetStep = parseInt(button.getAttribute('data-tm-go-step') || '1', 10);
        if (targetStep > currentStep) {
          var activeStep = form.querySelector('[data-tm-step].is-active');
          if (!validateStep(activeStep)) {
            focusFirstInvalid(activeStep);
            return;
          }
        }
        goToStep(targetStep);
      });
    });

    form.addEventListener('submit', function (event) {
      var invalidStep = null;
      steps.some(function (step) {
        if (!validateStep(step)) {
          invalidStep = step;
          return true;
        }
        return false;
      });
      if (invalidStep) {
        event.preventDefault();
        goToStep(parseInt(invalidStep.getAttribute('data-tm-step') || '1', 10));
        focusFirstInvalid(invalidStep);
      }
    });

    updateSummary();
    goToStep(currentStep);
  }

  initSubmitWizard();


  var imageInput = document.getElementById('tm_images');
  var preview = document.getElementById('tm-upload-preview');
  var uploadZone = document.querySelector('[data-tm-upload-zone]');
  var uploadCount = document.querySelector('[data-tm-upload-count]');
  var uploadPrimaryLabel = document.querySelector('[data-tm-upload-primary-label]');
  var existingGallery = document.querySelector('[data-tm-existing-gallery]');
  var existingOrderInput = document.querySelector('[data-tm-existing-media-order]');
  var featuredExistingInput = document.querySelector('[data-tm-featured-existing-id]');
  var featuredUploadInput = document.querySelector('[data-tm-featured-upload-index]');
  var previewModal = document.querySelector('[data-tm-preview-modal]');
  if (imageInput && preview) {
    var selectedFiles = [];

    function syncInputFiles() {
      if (typeof DataTransfer === 'undefined') return;
      var dt = new DataTransfer();
      selectedFiles.forEach(function (file) { dt.items.add(file); });
      imageInput.files = dt.files;
    }

    function getVisibleExistingCards() {
      if (!existingGallery) return [];
      return Array.prototype.slice.call(existingGallery.querySelectorAll('[data-tm-existing-card]')).filter(function (card) {
        return !card.classList.contains('is-marked-delete');
      });
    }

    function getPrimaryDescriptor() {
      var existingCard = existingGallery ? existingGallery.querySelector('[data-tm-existing-card].is-featured:not(.is-marked-delete)') : null;
      if (existingCard) return 'Principal actual';
      var uploadIndex = featuredUploadInput ? parseInt(featuredUploadInput.value || '-1', 10) : -1;
      if (uploadIndex >= 0 && selectedFiles[uploadIndex]) return 'Nueva principal: ' + selectedFiles[uploadIndex].name;
      if (selectedFiles[0]) return 'Nueva principal pendiente';
      return 'Sin principal definida';
    }

    function updateUploadCounters() {
      var totalImages = getVisibleExistingCards().length + selectedFiles.length;
      if (uploadCount) uploadCount.textContent = totalImages === 1 ? '1 imagen' : String(totalImages) + ' imágenes';
      if (uploadPrimaryLabel) uploadPrimaryLabel.textContent = getPrimaryDescriptor();
    }

    function updateExistingOrder() {
      if (!existingGallery || !existingOrderInput) return;
      existingOrderInput.value = Array.prototype.slice.call(existingGallery.querySelectorAll('[data-tm-existing-card]')).map(function (card) {
        return String(card.getAttribute('data-attachment-id') || '').trim();
      }).filter(Boolean).join(',');
    }

    function normalizeFeaturedSelection() {
      var visibleExisting = getVisibleExistingCards();
      var existingFeatured = existingGallery ? existingGallery.querySelector('[data-tm-existing-card].is-featured:not(.is-marked-delete)') : null;
      var uploadIndex = featuredUploadInput ? parseInt(featuredUploadInput.value || '-1', 10) : -1;
      if (existingFeatured) {
        if (featuredExistingInput) featuredExistingInput.value = String(existingFeatured.getAttribute('data-attachment-id') || '0');
        if (featuredUploadInput) featuredUploadInput.value = '-1';
      } else if (uploadIndex >= selectedFiles.length) {
        if (featuredUploadInput) featuredUploadInput.value = selectedFiles.length ? '0' : '-1';
      } else if (uploadIndex < 0 && !visibleExisting.length && selectedFiles.length) {
        if (featuredUploadInput) featuredUploadInput.value = '0';
      }
      if (!existingFeatured && !visibleExisting.length && !selectedFiles.length && featuredExistingInput) {
        featuredExistingInput.value = '0';
      }
    }

    function setExistingFeatured(card) {
      if (!card || !existingGallery) return;
      Array.prototype.slice.call(existingGallery.querySelectorAll('[data-tm-existing-card]')).forEach(function (item) {
        item.classList.remove('is-featured');
        var pill = item.querySelector('[data-tm-set-existing-featured]');
        if (pill) pill.classList.remove('is-active');
      });
      card.classList.add('is-featured');
      var button = card.querySelector('[data-tm-set-existing-featured]');
      if (button) button.classList.add('is-active');
      if (featuredExistingInput) featuredExistingInput.value = String(card.getAttribute('data-attachment-id') || '0');
      if (featuredUploadInput) featuredUploadInput.value = '-1';
      updateUploadCounters();
      imageInput.dispatchEvent(new Event('change', { bubbles: true }));
    }

    function setUploadFeatured(index) {
      if (featuredUploadInput) featuredUploadInput.value = String(index);
      if (featuredExistingInput) featuredExistingInput.value = '0';
      if (existingGallery) {
        Array.prototype.slice.call(existingGallery.querySelectorAll('[data-tm-existing-card]')).forEach(function (item) {
          item.classList.remove('is-featured');
          var pill = item.querySelector('[data-tm-set-existing-featured]');
          if (pill) pill.classList.remove('is-active');
        });
      }
    }

    function renderUploadPreview() {
      preview.innerHTML = '';
      selectedFiles.forEach(function (file, index) {
        if (!file.type.match(/^image\//)) return;
        var wrap = document.createElement('article');
        wrap.className = 'tm-preview-image';
        if (parseInt(featuredUploadInput ? featuredUploadInput.value : '-1', 10) === index) wrap.classList.add('is-featured');
        var fileSize = file.size ? Math.round((file.size / 1024 / 1024) * 10) / 10 : 0;
        wrap.innerHTML = '' +
          '<div class="tm-preview-image__frame">' +
            '<img src="' + URL.createObjectURL(file) + '" alt="">' +
            '<span class="tm-media-badge tm-media-badge--featured">Principal</span>' +
          '</div>' +
          '<div class="tm-preview-image__meta"><strong>' + file.name + '</strong><span>' + (fileSize ? (String(fileSize).replace('.', ',') + ' MB') : 'Imagen nueva') + '</span></div>' +
          '<div class="tm-preview-image__actions">' +
            '<button type="button" class="tm-media-pill ' + ((parseInt(featuredUploadInput ? featuredUploadInput.value : '-1', 10) === index) ? 'is-active' : '') + '" data-tm-set-upload-featured="' + index + '">Marcar principal</button>' +
            '<button type="button" class="tm-media-icon" data-tm-move-upload="-1" data-index="' + index + '" aria-label="Mover a la izquierda">←</button>' +
            '<button type="button" class="tm-media-icon" data-tm-move-upload="1" data-index="' + index + '" aria-label="Mover a la derecha">→</button>' +
          '</div>' +
          '<button type="button" class="tm-link-button tm-link-button--light tm-link-button--small tm-preview-image__remove" data-tm-remove-upload="' + index + '">Quitar</button>';
        preview.appendChild(wrap);
      });
      updateUploadCounters();
    }

    function enforceMaxImages() {
      var available = Math.max(0, maxImages - getVisibleExistingCards().length);
      if (selectedFiles.length > available) {
        selectedFiles = selectedFiles.slice(0, available);
        syncInputFiles();
        setFieldError(imageInput, (window.tmFrontend && window.tmFrontend.strings ? window.tmFrontend.strings.maxImages : 'Máximo de imágenes superado.'));
      } else {
        setFieldError(imageInput, '');
      }
    }

    function addFiles(fileList) {
      if (!fileList || !fileList.length) return;
      Array.prototype.forEach.call(fileList, function (file) {
        if (file && file.type && file.type.match(/^image\//)) selectedFiles.push(file);
      });
      enforceMaxImages();
      normalizeFeaturedSelection();
      syncInputFiles();
      renderUploadPreview();
      imageInput.dispatchEvent(new Event('change', { bubbles: true }));
    }

    imageInput.addEventListener('change', function (event) {
      if (event.isTrusted && imageInput.files && imageInput.files.length) {
        var incoming = Array.prototype.slice.call(imageInput.files);
        var sameSet = selectedFiles.length === incoming.length && incoming.every(function (file, idx) {
          return selectedFiles[idx] && selectedFiles[idx].name === file.name && selectedFiles[idx].size === file.size;
        });
        if (!sameSet) {
          addFiles(incoming);
          return;
        }
      }
      normalizeFeaturedSelection();
      renderUploadPreview();
    });

    if (uploadZone) {
      ['dragenter', 'dragover'].forEach(function (eventName) {
        uploadZone.addEventListener(eventName, function (event) {
          event.preventDefault();
          uploadZone.classList.add('is-dragover');
        });
      });
      ['dragleave', 'drop'].forEach(function (eventName) {
        uploadZone.addEventListener(eventName, function (event) {
          event.preventDefault();
          uploadZone.classList.remove('is-dragover');
        });
      });
      uploadZone.addEventListener('drop', function (event) { addFiles(event.dataTransfer.files); });
      uploadZone.addEventListener('keydown', function (event) {
        if (event.key === 'Enter' || event.key === ' ') {
          event.preventDefault();
          imageInput.click();
        }
      });
    }

    preview.addEventListener('click', function (event) {
      var featuredButton = event.target.closest('[data-tm-set-upload-featured]');
      var moveButton = event.target.closest('[data-tm-move-upload]');
      var removeButton = event.target.closest('[data-tm-remove-upload]');
      if (featuredButton) {
        setUploadFeatured(parseInt(featuredButton.getAttribute('data-tm-set-upload-featured') || '0', 10));
        renderUploadPreview();
        imageInput.dispatchEvent(new Event('change', { bubbles: true }));
      }
      if (moveButton) {
        var index = parseInt(moveButton.getAttribute('data-index') || '0', 10);
        var target = index + parseInt(moveButton.getAttribute('data-tm-move-upload') || '0', 10);
        if (target >= 0 && target < selectedFiles.length) {
          var moved = selectedFiles.splice(index, 1)[0];
          selectedFiles.splice(target, 0, moved);
          var featuredIndex = parseInt(featuredUploadInput ? featuredUploadInput.value : '-1', 10);
          if (featuredIndex === index) {
            setUploadFeatured(target);
          } else if (featuredIndex === target) {
            setUploadFeatured(index);
          }
          syncInputFiles();
          renderUploadPreview();
          imageInput.dispatchEvent(new Event('change', { bubbles: true }));
        }
      }
      if (removeButton) {
        var removeIndex = parseInt(removeButton.getAttribute('data-tm-remove-upload') || '0', 10);
        selectedFiles.splice(removeIndex, 1);
        var featuredIdx = parseInt(featuredUploadInput ? featuredUploadInput.value : '-1', 10);
        if (featuredIdx === removeIndex) {
          if (featuredUploadInput) featuredUploadInput.value = selectedFiles.length ? '0' : '-1';
        } else if (featuredIdx > removeIndex && featuredUploadInput) {
          featuredUploadInput.value = String(featuredIdx - 1);
        }
        syncInputFiles();
        normalizeFeaturedSelection();
        renderUploadPreview();
        imageInput.dispatchEvent(new Event('change', { bubbles: true }));
      }
    });

    if (existingGallery) {
      existingGallery.addEventListener('click', function (event) {
        var card = event.target.closest('[data-tm-existing-card]');
        if (!card) return;
        var featuredButton = event.target.closest('[data-tm-set-existing-featured]');
        var moveButton = event.target.closest('[data-tm-move-existing]');
        if (featuredButton && !card.classList.contains('is-marked-delete')) setExistingFeatured(card);
        if (moveButton) {
          var direction = parseInt(moveButton.getAttribute('data-tm-move-existing') || '0', 10);
          var sibling = direction < 0 ? card.previousElementSibling : card.nextElementSibling;
          if (sibling) {
            if (direction < 0) {
              existingGallery.insertBefore(card, sibling);
            } else {
              existingGallery.insertBefore(sibling, card);
            }
            updateExistingOrder();
            imageInput.dispatchEvent(new Event('change', { bubbles: true }));
          }
        }
      });
      existingGallery.addEventListener('change', function (event) {
        var checkbox = event.target.closest('[data-tm-delete-existing]');
        if (!checkbox) return;
        var card = checkbox.closest('[data-tm-existing-card]');
        if (!card) return;
        card.classList.toggle('is-marked-delete', checkbox.checked);
        if (checkbox.checked && card.classList.contains('is-featured')) {
          card.classList.remove('is-featured');
          var nextVisible = getVisibleExistingCards()[0];
          if (nextVisible) {
            setExistingFeatured(nextVisible);
          } else if (selectedFiles.length) {
            setUploadFeatured(0);
          } else if (featuredExistingInput) {
            featuredExistingInput.value = '0';
          }
        }
        normalizeFeaturedSelection();
        updateExistingOrder();
        updateUploadCounters();
        imageInput.dispatchEvent(new Event('change', { bubbles: true }));
      });
      updateExistingOrder();
    }

    renderUploadPreview();
  }

  var previewTrigger = document.querySelector('[data-tm-open-preview]');
  if (previewTrigger && previewModal) {
    var closePreviewButtons = previewModal.querySelectorAll('[data-tm-close-preview]');
    var modalImage = previewModal.querySelector('[data-tm-preview-modal-image]');
    var modalEmpty = previewModal.querySelector('[data-tm-preview-modal-empty]');
    var modalTitle = previewModal.querySelector('[data-tm-preview-modal-title-text]');
    var modalPrice = previewModal.querySelector('[data-tm-preview-modal-price]');
    var modalCategory = previewModal.querySelector('[data-tm-preview-modal-category]');
    var modalLocation = previewModal.querySelector('[data-tm-preview-modal-location]');
    var modalCondition = previewModal.querySelector('[data-tm-preview-modal-condition]');
    var modalDescription = previewModal.querySelector('[data-tm-preview-modal-description]');
    var modalContact = previewModal.querySelector('[data-tm-preview-modal-contact]');
    var modalStatus = previewModal.querySelector('[data-tm-preview-modal-status]');
    var previewForm = previewTrigger.closest('form');

    function getModalPrimaryImageSrc() {
      var existingFeatured = document.querySelector('[data-tm-existing-card].is-featured:not(.is-marked-delete) img');
      if (existingFeatured) return existingFeatured.getAttribute('src') || '';
      var uploadedFeatured = document.querySelector('#tm-upload-preview .tm-preview-image.is-featured img');
      if (uploadedFeatured) return uploadedFeatured.getAttribute('src') || '';
      var anyExisting = document.querySelector('[data-tm-existing-card]:not(.is-marked-delete) img');
      if (anyExisting) return anyExisting.getAttribute('src') || '';
      var anyUploaded = document.querySelector('#tm-upload-preview .tm-preview-image img');
      return anyUploaded ? (anyUploaded.getAttribute('src') || '') : '';
    }

    function refreshPreviewModal() {
      var titleField = previewForm.querySelector('#tm_title');
      var priceField = previewForm.querySelector('#tm_price_clp');
      var categoryField = previewForm.querySelector('#tm_category_term_id');
      var regionField = previewForm.querySelector('#tm_region_term_id');
      var comunaField = previewForm.querySelector('#tm_comuna_term_id');
      var conditionField = previewForm.querySelector('#tm_condition_term_id');
      var statusField = previewForm.querySelector('#tm_listing_status');
      var descriptionField = previewForm.querySelector('#tm_description');
      var contactNameField = previewForm.querySelector('#tm_contact_name');
      if (modalTitle) modalTitle.textContent = getFieldValue(titleField) || 'Sin título todavía';
      if (modalPrice) modalPrice.textContent = formatCurrency(priceField && priceField.value) || 'Pendiente';
      if (modalCategory) modalCategory.textContent = getFieldValue(categoryField) || 'Pendiente';
      var locationParts = [];
      if (getFieldValue(comunaField) && getFieldValue(comunaField) !== 'Seleccionar') locationParts.push(getFieldValue(comunaField));
      if (getFieldValue(regionField) && getFieldValue(regionField) !== 'Seleccionar') locationParts.push(getFieldValue(regionField));
      if (modalLocation) modalLocation.textContent = locationParts.join(', ') || 'Pendiente';
      if (modalCondition) modalCondition.textContent = getFieldValue(conditionField) || 'Pendiente';
      if (modalDescription) modalDescription.textContent = getFieldValue(descriptionField) || 'Agrega una descripción para revisar aquí cómo se leerá el aviso.';
      if (modalContact) modalContact.textContent = getFieldValue(contactNameField) || 'Pendiente';
      if (modalStatus) {
        modalStatus.textContent = getFieldValue(statusField) || 'Activo';
        modalStatus.className = 'tm-status tm-status--' + (statusField && statusField.value ? statusField.value : 'active');
      }
      var imageSrc = getModalPrimaryImageSrc();
      if (imageSrc) {
        modalImage.src = imageSrc;
        modalImage.hidden = false;
        modalEmpty.hidden = true;
      } else {
        modalImage.hidden = true;
        modalImage.removeAttribute('src');
        modalEmpty.hidden = false;
      }
    }

    function openPreviewModal() {
      refreshPreviewModal();
      previewModal.hidden = false;
      document.body.classList.add('tm-preview-open');
    }

    function closePreviewModal() {
      previewModal.hidden = true;
      document.body.classList.remove('tm-preview-open');
    }

    previewTrigger.addEventListener('click', openPreviewModal);
    closePreviewButtons.forEach(function (button) { button.addEventListener('click', closePreviewModal); });
    document.addEventListener('keydown', function (event) {
      if (event.key === 'Escape' && !previewModal.hidden) closePreviewModal();
    });
  }

  var thumbs = document.querySelectorAll('.tm-gallery-thumb');
  var mainImage = document.getElementById('tm-main-image');
  var galleryCounter = document.querySelector('[data-tm-gallery-counter]');
  if (thumbs.length && mainImage) {
    var galleryMain = document.querySelector('.tm-gallery-main');
    var currentGalleryIndex = 0;

    function setActiveGalleryImage(index) {
      var thumb = thumbs[index];
      if (!thumb) return;

      thumbs.forEach(function (item) {
        item.classList.remove('is-active');
        item.setAttribute('aria-current', 'false');
      });

      thumb.classList.add('is-active');
      thumb.setAttribute('aria-current', 'true');
      currentGalleryIndex = index;
      mainImage.src = thumb.getAttribute('data-full-image') || mainImage.src;
      mainImage.alt = thumb.querySelector('img') ? (thumb.querySelector('img').getAttribute('alt') || mainImage.alt) : mainImage.alt;
      if (galleryCounter) {
        galleryCounter.textContent = String(index + 1) + ' / ' + String(thumbs.length);
      }
    }

    function moveGallery(step) {
      setActiveGalleryImage((currentGalleryIndex + step + thumbs.length) % thumbs.length);
    }

    thumbs.forEach(function (thumb, index) {
      thumb.setAttribute('aria-current', index === 0 ? 'true' : 'false');
      thumb.setAttribute('aria-label', 'Ver imagen ' + (index + 1));
      thumb.addEventListener('click', function () {
        setActiveGalleryImage(index);
      });
      thumb.addEventListener('keydown', function (event) {
        if (event.key === 'ArrowLeft') {
          event.preventDefault();
          moveGallery(-1);
          thumbs[currentGalleryIndex].focus();
        }
        if (event.key === 'ArrowRight') {
          event.preventDefault();
          moveGallery(1);
          thumbs[currentGalleryIndex].focus();
        }
      });
    });

    if (galleryMain && thumbs.length > 1) {
      var prevButton = document.createElement('button');
      prevButton.type = 'button';
      prevButton.className = 'tm-gallery-nav tm-gallery-nav--prev';
      prevButton.textContent = 'Anterior';
      prevButton.addEventListener('click', function () {
        moveGallery(-1);
      });

      var nextButton = document.createElement('button');
      nextButton.type = 'button';
      nextButton.className = 'tm-gallery-nav tm-gallery-nav--next';
      nextButton.textContent = 'Siguiente';
      nextButton.addEventListener('click', function () {
        moveGallery(1);
      });

      galleryMain.appendChild(prevButton);
      galleryMain.appendChild(nextButton);
    }
  }

  var vitrine = document.querySelector('[data-tm-vitrine]');
  if (vitrine) {
    var items = vitrine.querySelectorAll('.tm-vitrine__item');
    var current = 0;
    var controls = vitrine.querySelector('[data-tm-vitrine-controls]');
    var dotsWrap = vitrine.querySelector('[data-tm-vitrine-dots]');
    var prevButton = vitrine.querySelector('[data-tm-vitrine-prev]');
    var nextButton = vitrine.querySelector('[data-tm-vitrine-next]');
    var pauseButton = vitrine.querySelector('[data-tm-vitrine-pause]');
    var autoplayTimer = null;
    var isPaused = false;
    if (items.length > 1) {
      function stopAutoplay() {
        if (autoplayTimer) {
          clearInterval(autoplayTimer);
          autoplayTimer = null;
        }
      }

      function startAutoplay() {
        stopAutoplay();
        if (isPaused) return;
        autoplayTimer = setInterval(function () {
          setActiveSlide((current + 1) % items.length);
        }, 4500);
      }

      function syncPauseButton() {
        if (!pauseButton) return;
        pauseButton.textContent = isPaused ? 'Reanudar' : 'Pausar';
        pauseButton.setAttribute('aria-pressed', isPaused ? 'true' : 'false');
      }

      function setActiveSlide(index) {
        items[current].classList.remove('is-active');
        items[current].setAttribute('aria-hidden', 'true');
        if (dotsWrap && dotsWrap.children[current]) {
          dotsWrap.children[current].classList.remove('is-active');
          dotsWrap.children[current].removeAttribute('aria-current');
        }

        current = index;
        items[current].classList.add('is-active');
        items[current].setAttribute('aria-hidden', 'false');
        if (dotsWrap && dotsWrap.children[current]) {
          dotsWrap.children[current].classList.add('is-active');
          dotsWrap.children[current].setAttribute('aria-current', 'true');
        }
      }

      items.forEach(function (item, index) {
        item.setAttribute('aria-hidden', index === 0 ? 'false' : 'true');
      });

      items.forEach(function (_, index) {
        var dot = document.createElement('button');
        dot.type = 'button';
        dot.className = 'tm-vitrine__dot' + (index === 0 ? ' is-active' : '');
        dot.setAttribute('aria-label', 'Ir al destacado ' + (index + 1));
        if (index === 0) {
          dot.setAttribute('aria-current', 'true');
        }
        dot.addEventListener('click', function () {
          setActiveSlide(index);
          startAutoplay();
        });
        dotsWrap.appendChild(dot);
      });

      if (prevButton) {
        prevButton.addEventListener('click', function () {
          setActiveSlide((current - 1 + items.length) % items.length);
          startAutoplay();
        });
      }

      if (nextButton) {
        nextButton.addEventListener('click', function () {
          setActiveSlide((current + 1) % items.length);
          startAutoplay();
        });
      }

      if (pauseButton) {
        pauseButton.addEventListener('click', function () {
          isPaused = !isPaused;
          syncPauseButton();
          if (isPaused) {
            stopAutoplay();
          } else {
            startAutoplay();
          }
        });
      }

      vitrine.addEventListener('mouseenter', function () {
        if (!isPaused) stopAutoplay();
      });

      vitrine.addEventListener('mouseleave', function () {
        if (!isPaused) startAutoplay();
      });

      vitrine.addEventListener('focusin', function () {
        if (!isPaused) stopAutoplay();
      });

      vitrine.addEventListener('focusout', function (event) {
        if (!isPaused && !vitrine.contains(event.relatedTarget)) {
          startAutoplay();
        }
      });

      if (controls) {
        controls.hidden = false;
      }
      syncPauseButton();
      startAutoplay();
    } else if (controls) {
      controls.hidden = true;
    }
  }

  var filterForm = document.getElementById('tm-archive-filters');
  var resultsWrap = document.getElementById('tm-results');
  if (filterForm && resultsWrap && window.tmFrontend) {
    var filterToggle = filterForm.querySelector('[data-tm-filter-toggle]');
    var filterCount = filterForm.querySelector('[data-tm-filter-count]');
    var mobileOpen = false;
    var filterCountInitialized = false;
    var suppressAutoUpdate = false;
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

    function countActiveFilters() {
      var count = 0;
      var keyword = filterForm.querySelector('[name="keyword"]');
      var order = filterForm.querySelector('[name="order"]');
      var selects = ['category', 'subcategory', 'region', 'comuna', 'condition'];
      var numbers = ['price_min', 'price_max'];
      var checks = ['with_photo', 'featured_only'];

      if (keyword && keyword.value.trim() !== '') count++;
      selects.forEach(function (name) {
        var field = filterForm.querySelector('[name="' + name + '"]');
        if (field && field.value !== '') count++;
      });
      numbers.forEach(function (name) {
        var field = filterForm.querySelector('[name="' + name + '"]');
        if (field && field.value !== '' && field.value !== '0') count++;
      });
      checks.forEach(function (name) {
        var field = filterForm.querySelector('[name="' + name + '"]');
        if (field && field.checked) count++;
      });
      if (order && order.value !== '' && order.value !== 'featured_recent') count++;

      return count;
    }

    function setMobileFilterOpen(isOpen) {
      mobileOpen = !!isOpen;
      filterForm.classList.toggle('is-mobile-open', mobileOpen);
      if (filterToggle) {
        filterToggle.setAttribute('aria-expanded', mobileOpen ? 'true' : 'false');
      }
    }

    function syncFilterToggle() {
      var activeCount = countActiveFilters();
      if (filterCount) {
        filterCount.textContent = String(activeCount);
        filterCount.hidden = activeCount < 1;
      }
      if (!filterCountInitialized) {
        setMobileFilterOpen(false);
        filterCountInitialized = true;
      }
    }

    if (filterToggle) {
      filterForm.classList.add('tm-filter-bar--collapsible-ready');
      filterToggle.addEventListener('click', function () {
        setMobileFilterOpen(!mobileOpen);
      });
      syncFilterToggle();
    }

    function clearFilterField(fieldName, resetValue) {
      var field = filterForm.querySelector('[name="' + fieldName + '"]');
      if (!field) return;

      if (field.type === 'checkbox') {
        field.checked = false;
        return;
      }

      field.value = typeof resetValue === 'string' ? resetValue : '';
    }

    function removeFilter(fieldName, resetValue) {
      suppressAutoUpdate = true;
      clearFilterField(fieldName, resetValue);

      if (fieldName === 'category') {
        var subcategory = filterForm.querySelector('[name="subcategory"]');
        if (subcategory) {
          subcategory.value = '';
        }
        var categoryField = filterForm.querySelector('[name="category"]');
        if (categoryField) {
          categoryField.dispatchEvent(new Event('change', { bubbles: true }));
        }
      } else if (fieldName === 'region') {
        var comuna = filterForm.querySelector('[name="comuna"]');
        if (comuna) {
          comuna.value = '';
        }
        var regionField = filterForm.querySelector('[name="region"]');
        if (regionField) {
          regionField.dispatchEvent(new Event('change', { bubbles: true }));
        }
      } else {
        var changedField = filterForm.querySelector('[name="' + fieldName + '"]');
        if (changedField && changedField.tagName === 'SELECT') {
          changedField.dispatchEvent(new Event('change', { bubbles: true }));
        }
      }

      suppressAutoUpdate = false;
      syncFilterToggle();
      updateResults('1', false);
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
        if (suppressAutoUpdate) return;
        syncFilterToggle();
        updateResults('1', false);
      });
    });

    filterForm.querySelectorAll('input[type="text"]').forEach(function (field) {
      field.addEventListener('input', function () {
        if (suppressAutoUpdate) return;
        syncFilterToggle();
        debouncedTextUpdate();
      });
    });

    resultsWrap.addEventListener('click', function (event) {
      var removeButton = event.target.closest('[data-remove-filter]');
      if (removeButton) {
        event.preventDefault();
        removeFilter(removeButton.getAttribute('data-remove-filter') || '', removeButton.getAttribute('data-reset-value'));
        return;
      }

      var pageLink = event.target.closest('.tm-page-link');
      if (!pageLink) return;
      event.preventDefault();
      updateResults(pageLink.getAttribute('data-page') || '1', true);
    });
  }
});
