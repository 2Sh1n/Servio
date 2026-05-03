/**
 * Provider Stepper Form Controller
 *
 * Manages step navigation, per-step validation, sidebar state,
 * progress bar, and review step rendering for add_partner / edit_partner forms.
 *
 * Set window.stepperMode = 'edit' before loading to enable edit mode (9 steps).
 */
(function () {
    'use strict';

    var isEditMode = (window.stepperMode === 'edit');
    var TOTAL_STEPS = 8;
    var currentStep = 1;
    var highestReached = isEditMode ? TOTAL_STEPS : 1;

    var STEP_META = [
        null,
        { icon: 'fa-user' },
        { icon: 'fa-briefcase' },
        { icon: 'fa-map-marker-alt' },
        { icon: 'fa-clock' },
        { icon: 'fa-images' },
        { icon: 'fa-university' },
        { icon: 'fa-magnifying-glass' },
        { icon: 'fa-check-circle' }
    ];

    var sidebarItems, horizontalItems, stepPanels, progressFill, progressBar,
        btnBack, btnNext, btnSubmit, stepTitle, stepSubtitle;

    // ============================================================
    //  Initialisation
    // ============================================================
    function init() {
        sidebarItems    = Array.from(document.querySelectorAll('.stepper-sidebar .step-item'));
        horizontalItems = Array.from(document.querySelectorAll('.stepper-horizontal .step-h-item'));
        stepPanels      = Array.from(document.querySelectorAll('.step-panel'));
        progressFill = document.querySelector('.stepper-progress-bar .progress-fill');
        progressBar  = document.querySelector('.stepper-progress-bar');
        btnBack      = document.querySelector('.btn-step-back');
        btnNext      = document.querySelector('.btn-step-next');
        btnSubmit    = document.querySelector('.btn-step-submit');
        stepTitle    = document.getElementById('stepper-step-title');
        stepSubtitle = document.getElementById('stepper-step-subtitle');

        if (!sidebarItems.length || !stepPanels.length) return;

        btnBack.addEventListener('click', function (e) { e.preventDefault(); goToStep(currentStep - 1); });
        btnNext.addEventListener('click', function (e) { e.preventDefault(); attemptNext(); });

        sidebarItems.forEach(function (item, idx) {
            item.addEventListener('click', function () {
                var stepNum = idx + 1;
                if (stepNum <= highestReached && stepNum !== currentStep) {
                    goToStep(stepNum);
                }
            });
        });

        horizontalItems.forEach(function (item, idx) {
            item.addEventListener('click', function () {
                var stepNum = idx + 1;
                if (stepNum <= highestReached && stepNum !== currentStep) {
                    goToStep(stepNum);
                }
            });
        });

        goToStep(1);

        // --- Toggle Card Logic ---
        // Synchronise card 'active' class when the checkbox changes
        $(document).on('change', '.stepper-toggle-card input[type="checkbox"].custom-control-input', function () {
            $(this).closest('.stepper-toggle-card').toggleClass('active', this.checked);
        });

        // Make entire card clickable
        $(document).on('click', '.stepper-toggle-card', function (e) {
            // If click was on the switch or its label, let it propagate naturally
            if ($(e.target).closest('.custom-control').length) return;
            var $cb = $(this).find('input[type="checkbox"].custom-control-input');
            if ($cb.length) {
                $cb.prop('checked', !$cb.prop('checked')).trigger('change');
            }
        });
    }

    // ============================================================
    //  Navigation
    // ============================================================
    function goToStep(step) {
        if (step < 1 || step > TOTAL_STEPS) return;

        currentStep = step;
        if (step > highestReached) highestReached = step;

        // Toggle panels
        stepPanels.forEach(function (panel, idx) {
            panel.classList.toggle('active', idx + 1 === step);
        });

        // Update sidebar
        sidebarItems.forEach(function (item, idx) {
            var s = idx + 1;
            var iconEl = item.querySelector('.step-icon i');
            item.classList.remove('active', 'completed', 'disabled');

            if (s === step) {
                item.classList.add('active');
                if (iconEl) iconEl.className = 'fas ' + STEP_META[s].icon;
            } else if (s <= highestReached || s < step) {
                item.classList.add('completed');
                if (iconEl) iconEl.className = 'fas fa-check';
            } else {
                item.classList.add('disabled');
                if (iconEl) iconEl.className = 'fas ' + STEP_META[s].icon;
            }
        });

        // Update horizontal stepper (mobile)
        horizontalItems.forEach(function (item, idx) {
            var s = idx + 1;
            var iconEl = item.querySelector('.step-h-icon i');
            item.classList.remove('active', 'completed', 'disabled');

            if (s === step) {
                item.classList.add('active');
                if (iconEl) iconEl.className = 'fas ' + STEP_META[s].icon;
            } else if (s <= highestReached || s < step) {
                item.classList.add('completed');
                if (iconEl) iconEl.className = 'fas fa-check';
            } else {
                item.classList.add('disabled');
                if (iconEl) iconEl.className = 'fas ' + STEP_META[s].icon;
            }
        });

        // Scroll active horizontal step into view on mobile
        var activeHItem = document.querySelector('.stepper-horizontal .step-h-item.active');
        if (activeHItem) {
            activeHItem.scrollIntoView({ behavior: 'smooth', inline: 'center', block: 'nearest' });
        }

        // Update progress bar
        var pct = Math.round((step / TOTAL_STEPS) * 100);
        progressFill.style.width = pct + '%';
        progressBar.classList.toggle('complete', step === TOTAL_STEPS);

        // Update footer buttons
        btnBack.disabled = (step === 1);
        btnBack.style.visibility = (step === 1) ? 'hidden' : 'visible';
        btnNext.style.display = (step === TOTAL_STEPS) ? 'none' : 'inline-flex';
        btnSubmit.style.display = (step === TOTAL_STEPS) ? 'inline-flex' : 'none';

        // Update step title/subtitle
        var subtitles = [
            null,
            getLabel('provider_identity_contact_account', 'Provider identity, contact details, and account setup'),
            getLabel('configure_business_charges', 'Configure business type, charges, and operational preferences'),
            getLabel('set_provider_location', 'Set the provider service location on the map'),
            getLabel('configure_working_schedule', 'Configure the weekly working schedule'),
            getLabel('upload_images_documents', 'Upload profile images and required documents'),
            getLabel('enter_bank_account_details', 'Enter bank account and payment details'),
            getLabel('configure_seo_meta_tags', 'Configure SEO meta tags for better search visibility'),
            getLabel('verify_details_before_submitting', 'Verify all provider details before submitting')
        ];
        var titles = [
            null,
            getLabel('basic_info', 'Basic Info'),
            getLabel('business_settings', 'Business Settings'),
            getLabel('location', 'Location'),
            getLabel('working_hours', 'Working Hours'),
            getLabel('media_and_docs', 'Media & Docs'),
            getLabel('bank_details', 'Bank Details'),
            getLabel('seo_settings', 'SEO Settings'),
            getLabel('review_and_submit', 'Review & Submit')
        ];

        if (stepTitle && titles[step]) stepTitle.textContent = titles[step];
        if (stepSubtitle && subtitles[step]) stepSubtitle.textContent = subtitles[step];

        // Build review content when entering the review step
        if (step === TOTAL_STEPS) {
            buildReview();
        }

        // Initialize or resize Google Maps when Location step becomes visible
        if (step === 3) {
            setTimeout(function () {
                // If map init was deferred because the container was hidden, do it now
                if (window._partnerMapPending && typeof initPartnerMap === 'function') {
                    window._partnerMapPending = false;
                    initPartnerMap();
                    if (typeof initPartnerAutocomplete === 'function') {
                        initPartnerAutocomplete();
                    }
                } else {
                    var mapObj = partnerMap || map;
                    if (mapObj && typeof google !== 'undefined' && google.maps) {
                        google.maps.event.trigger(mapObj, 'resize');
                        // Re-center on the current marker after resize
                        if (marker) {
                            mapObj.setCenter(marker.getPosition());
                        }
                    }
                }
            }, 300);
        }

        // Scroll to top
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    function attemptNext() {
        if (validateCurrentStep()) {
            goToStep(currentStep + 1);
        }
    }

    // ============================================================
    //  Validation
    // ============================================================
    function validateCurrentStep() {
        var panel = stepPanels[currentStep - 1];
        if (!panel) return true;

        // 1. Standard required inputs/selects/textareas
        var requiredFields = panel.querySelectorAll('input[required], select[required], textarea[required]');
        for (var i = 0; i < requiredFields.length; i++) {
            var field = requiredFields[i];

            // Skip hidden fields (fields in non-active language tabs, filepond managed inputs)
            if (!isVisible(field)) continue;
            if (field.classList.contains('filepond') || field.classList.contains('filepond-custom-field')) continue;

            if (!field.checkValidity()) {
                var fieldLabel = field.closest('.form-group')?.querySelector('label')?.textContent?.trim() || field.placeholder || field.name || 'Field';
                var msg = getValidationMessage(field, fieldLabel);
                showValidationToast(fieldLabel, msg);
                field.focus();
                return false;
            }
        }

        // 2. Summernote rich text editors (check non-empty for required ones)
        // Summernote hides the original textarea, so check the .note-editor visibility instead
        var summernotes = panel.querySelectorAll('textarea.summernotes[required]');
        for (var j = 0; j < summernotes.length; j++) {
            var sn = summernotes[j];
            var noteEditor = sn.closest('.form-group')?.querySelector('.note-editor');
            if (!noteEditor || !isVisible(noteEditor)) continue;
            var $sn = $(sn);
            if ($sn.summernote && $sn.summernote('isEmpty')) {
                $sn.summernote('focus');
                showValidationToast(sn.closest('.form-group')?.querySelector('label')?.textContent?.trim() || 'Description');
                return false;
            }
        }

        // 3. FilePond required file inputs
        var fileInputs = panel.querySelectorAll('input.filepond[required]');
        for (var k = 0; k < fileInputs.length; k++) {
            var fp = fileInputs[k];
            if (!isVisible(fp)) continue;
            var pondInstance = FilePond.find(fp);
            if (pondInstance && pondInstance.getFiles().length === 0) {
                var label = fp.closest('.form-group')?.querySelector('label')?.textContent?.trim() || 'File';
                showValidationToast(label);
                fp.closest('.form-group')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
                return false;
            }
        }

        // 4. Select2 required selects
        var selects = panel.querySelectorAll('select[required]');
        for (var l = 0; l < selects.length; l++) {
            var sel = selects[l];
            if (!isVisible(sel)) continue;
            if (!sel.value || sel.value === '' || sel.selectedOptions[0]?.disabled) {
                $(sel).select2('open');
                showValidationToast(sel.closest('.form-group')?.querySelector('label')?.textContent?.trim() || 'Selection');
                return false;
            }
        }

        return true;
    }

    function isVisible(el) {
        while (el && el !== document.body) {
            if (el.style && el.style.display === 'none') return false;
            var cs = window.getComputedStyle(el);
            if (cs.display === 'none') return false;
            el = el.parentElement;
        }
        return true;
    }

    function getValidationMessage(field, fieldLabel) {
        var v = field.validity;
        if (v.valueMissing) {
            return getLabel('validation_required', '{field} is required.').replace('{field}', fieldLabel);
        }
        if (v.typeMismatch) {
            if (field.type === 'email') {
                return getLabel('validation_invalid_email', 'Please enter a valid email address.');
            }
            return getLabel('validation_invalid_value', 'Please enter a valid value for {field}.').replace('{field}', fieldLabel);
        }
        if (v.patternMismatch) {
            return field.title || getLabel('validation_invalid_format', 'Please enter a valid format for {field}.').replace('{field}', fieldLabel);
        }
        if (v.tooShort) {
            return getLabel('validation_too_short', '{field} must be at least {min} characters.')
                .replace('{field}', fieldLabel)
                .replace('{min}', field.minLength);
        }
        if (v.rangeUnderflow) {
            return getLabel('validation_min_value', '{field} must be at least {min}.')
                .replace('{field}', fieldLabel)
                .replace('{min}', field.min);
        }
        return getLabel('validation_invalid_value', 'Please enter a valid value for {field}.').replace('{field}', fieldLabel);
    }

    function showValidationToast(fieldLabel, customMessage) {
        if (typeof iziToast !== 'undefined') {
            iziToast.error({
                title: '',
                message: customMessage || getLabel('validation_required', '{field} is required.').replace('{field}', fieldLabel),
                position: 'topRight',
                timeout: 3000
            });
        }
    }

    // ============================================================
    //  Review Builder
    // ============================================================
    function buildReview() {
        var container = document.getElementById('stepper-review-content');
        if (!container) return;
        container.innerHTML = '';

        var sections = [
            { step: 1, title: getLabel('basic_info', 'Basic Info'), builder: buildBasicInfoReview },
            { step: 2, title: getLabel('business_settings', 'Business Settings'), builder: buildBusinessSettingsReview },
            { step: 3, title: getLabel('location', 'Location'), builder: buildLocationReview },
            { step: 4, title: getLabel('working_hours', 'Working Hours'), builder: buildWorkingHoursReview },
            { step: 5, title: getLabel('media_and_docs', 'Media & Docs'), builder: buildMediaDocsReview },
            { step: 6, title: getLabel('bank_details', 'Bank Details'), builder: buildBankDetailsReview },
            { step: 7, title: getLabel('seo_settings', 'SEO Settings'), builder: buildSeoReview }
        ];

        // Subscription step is excluded from review — it is managed separately via assign/cancel actions

        sections.forEach(function (sec) {
            var fields = sec.builder();
            if (!fields || fields.length === 0) return;
            var accordion = createAccordion(sec.title, sec.step, fields);
            container.appendChild(accordion);
        });
    }

    function createAccordion(title, stepNum, fieldsHtml) {
        var wrapper = document.createElement('div');
        wrapper.className = 'review-accordion open';

        wrapper.innerHTML =
            '<div class="review-accordion-header">' +
                '<div class="review-header-left">' +
                    '<div class="review-step-icon"><i class="fas fa-check"></i></div>' +
                    '<span>' + escapeHtml(title) + '</span>' +
                '</div>' +
                '<div class="review-actions">' +
                    '<button type="button" class="review-edit-link" data-goto-step="' + stepNum + '">' +
                        '<i class="fas fa-pen" style="font-size:11px;margin-right:4px;"></i> ' +
                        getLabel('edit', 'Edit') +
                    '</button>' +
                    '<i class="fas fa-chevron-down review-chevron"></i>' +
                '</div>' +
            '</div>' +
            '<div class="review-accordion-body">' + fieldsHtml + '</div>';

        var header = wrapper.querySelector('.review-accordion-header');
        header.addEventListener('click', function (e) {
            if (e.target.closest('.review-edit-link')) return;
            wrapper.classList.toggle('open');
        });

        var editBtn = wrapper.querySelector('.review-edit-link');
        editBtn.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            goToStep(stepNum);
        });

        return wrapper;
    }

    function reviewField(label, value) {
        if (!value || (typeof value === 'string' && value.trim() === '')) return '';
        return '<div class="review-field">' +
            '<span class="review-field-label">' + escapeHtml(label) + '</span>' +
            '<span class="review-field-value">' + value + '</span>' +
        '</div>';
    }

    function toggleBadge(isChecked) {
        return isChecked
            ? '<span class="review-badge-enabled">' + getLabel('enabled', 'Enabled') + '</span>'
            : '<span class="review-badge-disabled">' + getLabel('disabled_label', 'Disabled') + '</span>';
    }

    function fileBadge(filename) {
        var ext = filename.split('.').pop().toLowerCase();
        var icon = (ext === 'pdf') ? 'fa-file-pdf' : 'fa-image';
        return '<span class="review-file-badge"><i class="fas ' + icon + '"></i> ' + escapeHtml(filename) + '</span>';
    }

    // ---- Section Builders ----

    function buildBasicInfoReview() {
        var html = '';
        var defaultLangCode = getLabel('defaultLanguageCode', '');
        var defaultLang = defaultLangCode
            ? document.getElementById('translationDiv-' + defaultLangCode)
            : document.querySelector('[id^="translationDiv-"][style*="display: block"]');
        if (defaultLang) {
            var nameInput = defaultLang.querySelector('[name^="username["]');
            var companyInput = defaultLang.querySelector('[name^="company_name["]');
            var aboutInput = defaultLang.querySelector('[name^="about_provider["]') || defaultLang.querySelector('[name^="about["]');
            html += reviewField(getLabel('name', 'Name'), escapeHtml(val(nameInput)));
            html += reviewField(getLabel('company_name', 'Company Name'), escapeHtml(val(companyInput)));
            html += reviewField(getLabel('about_provider', 'About Provider'), escapeHtml(val(aboutInput)));
        }
        html += reviewField(getLabel('email', 'Email'), escapeHtml(val('#email')));
        html += reviewField(getLabel('phone_number', 'Phone'), escapeHtml(val('#country_code') + ' ' + val('#phone')));
        html += reviewField(getLabel('login_type', 'Login Type'), escapeHtml(selectedText('#login_type')));
        return html;
    }

    function buildBusinessSettingsReview() {
        var html = '';
        html += reviewField(getLabel('slug', 'Slug'), escapeHtml(val('#provider_slug')));
        html += reviewField(getLabel('type', 'Type'), escapeHtml(selectedText('#type')));
        html += reviewField(getLabel('visiting_charges', 'Visiting Charges'), escapeHtml(val('#visiting_charges')));
        html += reviewField(getLabel('advance_booking_days', 'Advance Booking Days'), escapeHtml(val('#advance_booking_days')));
        html += reviewField(getLabel('number_Of_members', 'Members'), escapeHtml(val('#number_of_members')));
        html += reviewField(getLabel('at_store', 'At Store'), toggleBadge(isChecked('#at_store')));
        html += reviewField(getLabel('at_doorstep', 'At Doorstep'), toggleBadge(isChecked('#at_doorstep')));

        var postChat = document.getElementById('post_chat');
        if (postChat) html += reviewField(getLabel('allow_post_booking_chat', 'Post-Booking Chat'), toggleBadge(postChat.checked));

        var preChat = document.getElementById('pre_chat');
        if (preChat) html += reviewField(getLabel('allow_pre_booking_chat', 'Pre-Booking Chat'), toggleBadge(preChat.checked));

        html += reviewField(getLabel('need_approval_for_the_service', 'Service Approval'), toggleBadge(isChecked('#need_approval_for_the_service')));
        return html;
    }

    function buildLocationReview() {
        var html = '';
        html += reviewField(getLabel('city', 'City'), escapeHtml(val('[name="city"]')));
        html += reviewField(getLabel('address', 'Address'), escapeHtml(val('#address')));
        html += reviewField(getLabel('latitude', 'Latitude'), escapeHtml(val('#partner_latitude')));
        html += reviewField(getLabel('longitude', 'Longitude'), escapeHtml(val('#partner_longitude')));
        return html;
    }

    function buildWorkingHoursReview() {
        var html = '';
        var days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
        var dayLabels = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
        var startTimes = document.querySelectorAll('.start_time');
        var endTimes = document.querySelectorAll('.end_time');
        var dayHtml = '';

        days.forEach(function (day, idx) {
            var checkbox = document.querySelector('input[name="' + day + '"]');
            var isOn = checkbox ? checkbox.checked : false;
            dayHtml += '<div class="review-working-day">';
            dayHtml += '<span class="day-name">' + dayLabels[idx] + '</span>';
            if (isOn && startTimes[idx] && endTimes[idx]) {
                dayHtml += '<span class="day-time">' + startTimes[idx].value + ' &mdash; ' + endTimes[idx].value + '</span>';
            } else {
                dayHtml += '<span class="day-closed">' + getLabel('closed', 'Closed') + '</span>';
            }
            dayHtml += '</div>';
        });

        html += '<div class="review-field" style="grid-template-columns:1fr;border-bottom:none;padding-bottom:4px;">' +
            '<span class="review-field-label">' + getLabel('working_days', 'Working Hours') + '</span></div>';
        html += '<div style="padding-left:4px;padding-bottom:4px;">' + dayHtml + '</div>';
        return html;
    }

    function buildMediaDocsReview() {
        var html = '';
        var otherImgSelector = isEditMode ? '#other_service_image_selector_edit' : '#other_service_image_selector';
        var pondInputs = [
            { selector: '#image', label: getLabel('image', 'Profile Image') },
            { selector: '#banner_image', label: getLabel('banner_image', 'Banner Image') },
            { selector: otherImgSelector, label: getLabel('other_images', 'Other Images') }
        ];

        pondInputs.forEach(function (item) {
            var el = document.querySelector(item.selector);
            if (!el) return;
            var pond = FilePond.find(el);
            if (pond) {
                var files = pond.getFiles();
                if (files.length > 0) {
                    var badges = files.map(function (f) { return fileBadge(f.filename); }).join('');
                    html += reviewField(item.label, badges);
                }
            }
        });

        // Custom document fields (filepond)
        document.querySelectorAll('.step-panel[data-step="5"] .filepond-custom-field').forEach(function (el) {
            var pond = FilePond.find(el);
            if (pond && pond.getFiles().length > 0) {
                var label = el.closest('.form-group')?.querySelector('label')?.textContent?.trim() || el.name;
                var badges = pond.getFiles().map(function (f) { return fileBadge(f.filename); }).join('');
                html += reviewField(label, badges);
            }
        });

        // Custom document fields (non-file)
        document.querySelectorAll('.step-panel[data-step="5"] input:not(.filepond):not(.filepond-custom-field):not([type="file"]):not([type="hidden"]), .step-panel[data-step="5"] textarea:not(.filepond), .step-panel[data-step="5"] select').forEach(function (el) {
            if (el.closest('.filepond--root')) return;
            var v = el.value?.trim();
            if (!v) return;
            var label = el.closest('.form-group')?.querySelector('label')?.textContent?.trim() || el.name;
            html += reviewField(label, escapeHtml(v));
        });

        return html;
    }

    function buildBankDetailsReview() {
        var html = '';

        // Bank detail custom fields
        document.querySelectorAll('.step-panel[data-step="6"] .bank-details-section input, .step-panel[data-step="6"] .bank-details-section textarea, .step-panel[data-step="6"] .bank-details-section select').forEach(function (el) {
            if (el.closest('.filepond--root')) return;
            var v = el.value?.trim();
            if (!v) return;
            var label = el.closest('.form-group')?.querySelector('label')?.textContent?.trim() || el.name;
            html += reviewField(label, escapeHtml(v));
        });

        return html;
    }

    function buildSeoReview() {
        var html = '';

        // SEO — default language only
        var defaultLangCode = getLabel('defaultLanguageCode', '');
        var seoDiv = defaultLangCode
            ? document.getElementById('translationDivSeo-' + defaultLangCode)
            : document.querySelector('[id^="translationDivSeo-"][style*="display: block"]');
        if (seoDiv) {
            var metaTitle = seoDiv.querySelector('[name^="meta_title["]');
            var metaDesc = seoDiv.querySelector('[name^="meta_description["]');
            html += reviewField(getLabel('meta_title', 'Meta Title'), escapeHtml(val(metaTitle)));
            html += reviewField(getLabel('meta_description', 'Meta Description'), escapeHtml(val(metaDesc)));

            // Meta keywords (Tagify)
            var kwInput = seoDiv.querySelector('[name^="meta_keywords["]');
            if (kwInput && kwInput.value) {
                try {
                    var tags = JSON.parse(kwInput.value);
                    var kwText = tags.map(function (t) { return t.value; }).join(', ');
                    html += reviewField(getLabel('meta_keywords', 'Meta Keywords'), escapeHtml(kwText));
                } catch (e) {
                    html += reviewField(getLabel('meta_keywords', 'Meta Keywords'), escapeHtml(kwInput.value));
                }
            }
        }

        return html;
    }


    // ---- Subscription Review (edit mode only) ----
    function buildSubscriptionReview() {
        var html = '';
        var container = document.getElementById('subscription-active-info');
        if (container) {
            var planName = container.getAttribute('data-plan-name') || '';
            var planPrice = container.getAttribute('data-plan-price') || '';
            var planExpiry = container.getAttribute('data-plan-expiry') || '';
            var planDuration = container.getAttribute('data-plan-duration') || '';
            var planOrders = container.getAttribute('data-plan-orders') || '';

            if (planName) {
                html += reviewField(getLabel('subscription', 'Subscription'), escapeHtml(planName));
                html += reviewField(getLabel('price', 'Price'), escapeHtml(planPrice));
                if (planDuration === 'unlimited') {
                    html += reviewField(getLabel('duration', 'Duration'), getLabel('unlimited', 'Lifetime'));
                } else {
                    html += reviewField(getLabel('duration', 'Duration'), escapeHtml(planDuration) + ' ' + getLabel('days', 'Days'));
                }
                html += reviewField(getLabel('order_limit', 'Order Limit'), planOrders === 'unlimited' ? getLabel('unlimited', 'Unlimited') : escapeHtml(planOrders));
                if (planExpiry) {
                    html += reviewField(getLabel('expiry_date', 'Expiry Date'), escapeHtml(planExpiry));
                }
            } else {
                html += reviewField(getLabel('subscription', 'Subscription'), getLabel('no_active_subscription', 'No active subscription'));
            }
        }
        return html;
    }

    // ============================================================
    //  Helpers
    // ============================================================
    function val(selectorOrEl) {
        var el = (typeof selectorOrEl === 'string') ? document.querySelector(selectorOrEl) : selectorOrEl;
        return el ? (el.value || '').trim() : '';
    }

    function selectedText(selector) {
        var el = document.querySelector(selector);
        if (!el || el.selectedIndex < 0) return '';
        return el.options[el.selectedIndex].text || '';
    }

    function isChecked(selector) {
        var el = document.querySelector(selector);
        return el ? el.checked : false;
    }

    function escapeHtml(str) {
        if (!str) return '';
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(str));
        return div.innerHTML;
    }

    function getLabel(key, fallback) {
        if (typeof window.stepperLabels !== 'undefined' && window.stepperLabels[key]) {
            return window.stepperLabels[key];
        }
        return fallback;
    }

    // ============================================================
    //  Lat/Lng normalisation — always exactly 7 decimal places
    // ============================================================
    function formatCoord(value) {
        var num = parseFloat(value);
        if (isNaN(num)) return value;
        return num.toFixed(7);
    }

    function normalizeLatLngFields() {
        var latEl = document.getElementById('partner_latitude');
        var lngEl = document.getElementById('partner_longitude');
        if (latEl && latEl.value.trim() !== '') latEl.value = formatCoord(latEl.value);
        if (lngEl && lngEl.value.trim() !== '') lngEl.value = formatCoord(lngEl.value);
    }

    // Normalize on blur so the user sees the formatted value immediately
    document.addEventListener('DOMContentLoaded', function () {
        ['partner_latitude', 'partner_longitude'].forEach(function (id) {
            var el = document.getElementById(id);
            if (el) {
                el.addEventListener('blur', function () {
                    if (this.value.trim() !== '') this.value = formatCoord(this.value);
                });
            }
        });
    });

    // Normalize just before form submission to guarantee 7 decimal places
    document.addEventListener('submit', function (e) {
        var form = e.target;
        if (form.id === 'add_partner' || form.id === 'edit_partner') {
            normalizeLatLngFields();
        }
    }, true);

    // Expose for scripts.js map interactions
    window.formatCoord = formatCoord;

    // ---- Bootstrap ----
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
