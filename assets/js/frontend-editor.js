( function( window, $, document ) {
	'use strict';

	var states = {};
	var scriptStrings = window.ffe_frontend_editor_strings || {};

	function getState( formId ) {
		if ( ! states[ formId ] ) {
			states[ formId ] = {
				activeItemKey: null,
				activeItemType: null,
				config: null
			};
		}

		return states[ formId ];
	}

	function getConfig( formId ) {
		return window.ffeData && window.ffeData[ String( formId ) ] ? window.ffeData[ String( formId ) ] : null;
	}

	function getFormWrapper( formId ) {
		return $( '#gform_wrapper_' + formId );
	}

	function getFieldWrapper( formId, fieldId ) {
		return $( '#field_' + formId + '_' + fieldId );
	}

	function getFormHeading( formId ) {
		return getFormWrapper( formId ).find( '.gform_heading' ).first();
	}

	function getCurrentFormPage( formId ) {
		var pageInput = $( '#gform_source_page_number_' + formId );
		var pageNumber = parseInt( pageInput.val(), 10 );

		return isNaN( pageNumber ) || pageNumber < 1 ? 1 : pageNumber;
	}

	function getFormItemElement( formId, itemKey ) {
		var heading = getFormHeading( formId );

		if ( ! heading.length ) {
			return $();
		}

		if ( 'title' === itemKey ) {
			return heading.find( '.gform_title' ).first();
		}

		if ( 'description' === itemKey ) {
			return heading.find( '.gform_description' ).first();
		}

		return $();
	}

	function escapeHtml( value ) {
		return String( value || '' )
			.replace( /&/g, '&amp;' )
			.replace( /</g, '&lt;' )
			.replace( />/g, '&gt;' )
			.replace( /"/g, '&quot;' )
			.replace( /'/g, '&#039;' );
	}

	function getString( key, fallback ) {
		return scriptStrings[ key ] || fallback;
	}

	function getEditButtonMarkup() {
		var label = escapeHtml( getString( 'editLabel', 'Edit' ) );

		return '' +
			'<button type="button" class="ffe-field-edit-button" aria-label="' + label + '" title="' + label + '">' +
				'<span class="ffe-field-edit-button__text">' + label + '</span>' +
			'</button>';
	}

	function getItemConfig( config, itemType, itemKey ) {
		if ( ! config ) {
			return null;
		}

		if ( 'form' === itemType ) {
			return config.formItems && config.formItems[ itemKey ] ? config.formItems[ itemKey ] : null;
		}

		return config.fields && config.fields[ itemKey ] ? config.fields[ itemKey ] : null;
	}

	function getFieldActionAnchor( field ) {
		var anchor = field.children( '.ginput_container, .gform-grid-row, .gfield_time, .gfield_checkbox, .gfield_radio, table.gfield_list' ).first();

		if ( anchor.length ) {
			return anchor;
		}

		return field.children().not( '.ffe-field-edit-actions, .gfield_label, legend.gfield_label, .gsection_title, .gfield_description, .gfield_validation_message' ).first();
	}

	function usesLabelHeader( field ) {
		return ! field.hasClass( 'hidden_label' ) && ! field.closest( '.left_label, .right_label' ).length && ( field.children( 'label.gfield_label' ).length > 0 || field.children( '.ffe-field-edit-header' ).length > 0 );
	}

	function usesLegendLayout( field ) {
		return ! field.hasClass( 'hidden_label' ) && ! field.closest( '.left_label, .right_label' ).length && field.children( 'legend.gfield_label' ).length > 0;
	}

	function getLegendActionContainer( field ) {
		var legend = field.children( 'legend.gfield_label' ).first();
		var fieldContainer = field.children( '.ffe-field-edit-actions' ).first();
		var container = legend.children( '.ffe-field-edit-actions' ).first();
		var copy = legend.children( '.ffe-field-edit-legend-copy' ).first();

		if ( ! legend.length ) {
			return fieldContainer;
		}

		if ( ! copy.length ) {
			copy = $( '<span class="ffe-field-edit-legend-copy"></span>' );

			if ( container.length ) {
				container.before( copy );
			} else {
				legend.append( copy );
			}
		}

		normalizeLegendLabelCopy( legend, copy, container );

		if ( ! container.length ) {
			container = $( '<span class="ffe-field-edit-actions ffe-field-edit-actions--legend"></span>' );

			if ( fieldContainer.length ) {
				container.append( fieldContainer.contents() );
				fieldContainer.remove();
			}

			legend.append( container );
		} else {
			container.addClass( 'ffe-field-edit-actions--legend' );

			if ( fieldContainer.length ) {
				container.append( fieldContainer.contents() );
				fieldContainer.remove();
			}
		}

		return container;
	}

	function getLegendCopyLabelText( copy ) {
		var clone;

		if ( ! copy.length ) {
			return '';
		}

		clone = copy.clone();
		clone.find( '.gfield_required' ).remove();

		return $.trim( clone.text() );
	}

	function normalizeLegendLabelCopy( legend, copy, container ) {
		var strayNodes;
		var hasCopyLabelText;
		var actionNode = container && container.length ? container.get( 0 ) : null;

		if ( ! legend.length || ! copy.length ) {
			return;
		}

		strayNodes = legend.contents().filter( function() {
			if ( this === copy.get( 0 ) || this === actionNode ) {
				return false;
			}

			return this.nodeType !== 3 || $.trim( this.nodeValue || '' ) !== '';
		} );

		if ( ! strayNodes.length ) {
			return;
		}

		hasCopyLabelText = getLegendCopyLabelText( copy ) !== '';

		if ( hasCopyLabelText ) {
			strayNodes.remove();
			return;
		}

		copy.prepend( strayNodes );
	}

	function getFieldActionContainer( field ) {
		var container = field.children( '.ffe-field-edit-actions' );
		var header;
		var label;
		var anchor = getFieldActionAnchor( field );

		if ( ! container.length ) {
			container = field.children( '.ffe-field-edit-header' ).children( '.ffe-field-edit-actions' ).first();
		}

		if ( ! container.length ) {
			container = field.children( 'legend.gfield_label' ).children( '.ffe-field-edit-actions' ).first();
		}

		if ( ! container.length ) {
			container = $( '<div class="ffe-field-edit-actions"></div>' );
		}

		if ( usesLabelHeader( field ) ) {
			field.removeClass( 'ffe-field-edit-legend-layout' );
			header = field.children( '.ffe-field-edit-header' );
			label = field.children( 'label.gfield_label' ).first();

			if ( ! header.length ) {
				header = $( '<div class="ffe-field-edit-header"></div>' );
				label.before( header );
			}

			if ( label.length && ! header.children( 'label.gfield_label' ).length ) {
				header.append( label );
			}

			header.append( container );

			return container;
		}

		if ( usesLegendLayout( field ) ) {
			field.addClass( 'ffe-field-edit-legend-layout' );
			return getLegendActionContainer( field );
		}

		field.removeClass( 'ffe-field-edit-legend-layout' );

		if ( anchor.length ) {
			container.insertBefore( anchor );
		} else {
			field.prepend( container );
		}

		return container;
	}

	function getPrimaryFieldLabelElement( field ) {
		var headerLabel = field.children( '.ffe-field-edit-header' ).children( 'label.gfield_label' ).first();
		var directLabel;

		if ( headerLabel.length ) {
			return headerLabel;
		}

		directLabel = field.children( 'label.gfield_label' ).first();

		if ( directLabel.length ) {
			return directLabel;
		}

		return field.children( 'legend.gfield_label' ).first();
	}

	function patchFieldLabelText( field, value ) {
		var label = getPrimaryFieldLabelElement( field );
		var labelCopy;
		var requiredHtml;

		if ( ! label.length ) {
			return;
		}

		labelCopy = label.children( '.ffe-field-edit-legend-copy' ).first();

		if ( labelCopy.length ) {
			normalizeLegendLabelCopy( label, labelCopy, label.children( '.ffe-field-edit-actions' ).first() );
			requiredHtml = labelCopy.children( '.gfield_required' ).first().prop( 'outerHTML' ) || '';
			labelCopy.html( value + requiredHtml );
			return;
		}

		requiredHtml = label.children( '.gfield_required' ).first().prop( 'outerHTML' ) || '';
		label.html( value + requiredHtml );
	}

	function ensurePanel( formId, state ) {
		var panel = $( '#ffe-editor-panel-' + formId );
		var previousLabel = escapeHtml( getString( 'previousLabel', 'Previous' ) );
		var nextLabel = escapeHtml( getString( 'nextLabel', 'Next' ) );
		var previousTooltip = escapeHtml( getString( 'previousTooltip', 'Previous field (CTRL+\u2190)' ) );
		var nextTooltip = escapeHtml( getString( 'nextTooltip', 'Next field (CTRL+\u2192)' ) );
		var saveTooltip = escapeHtml( getString( 'saveTooltip', 'CTRL+S/↵ to save' ) );

		if ( panel.length ) {
			panel.attr( 'aria-hidden', 'true' ).removeClass( 'is-open' );
			panel.find( '.ffe-editor-panel__body, .ffe-editor-panel__status' ).empty();
			panel.find( '.ffe-editor-panel__title' ).text( getString( 'panelTitle', 'Edit Field' ) );
			panel.find( '.ffe-editor-panel__subtitle' ).empty();
			state.activeItemType = null;
			state.activeItemKey = null;
			updatePanelNavigationButtons( formId, panel );
			return panel;
		}

		panel = $( '' +
			'<aside class="ffe-editor-panel" id="ffe-editor-panel-' + formId + '" aria-hidden="true">' +
				'<div class="ffe-editor-panel__header">' +
					'<div>' +
						'<h3 class="ffe-editor-panel__title">' + escapeHtml( getString( 'panelTitle', 'Edit Field' ) ) + '</h3>' +
						'<p class="ffe-editor-panel__subtitle"></p>' +
					'</div>' +
					'<button type="button" class="ffe-editor-panel__close">' + escapeHtml( getString( 'closeLabel', 'Close' ) ) + '</button>' +
				'</div>' +
				'<div class="ffe-editor-panel__body"></div>' +
				'<div class="ffe-editor-panel__status" role="status" aria-live="polite"></div>' +
				'<div class="ffe-editor-panel__actions">' +
					'<div class="ffe-editor-panel__navigation" hidden>' +
						'<button type="button" class="ffe-editor-panel__button ffe-editor-panel__button--secondary ffe-editor-panel__button--nav ffe-editor-panel__previous" aria-label="' + previousLabel + '" title="' + previousTooltip + '"><span class="ffe-editor-panel__button-arrow" aria-hidden="true">&#8592;</span></button>' +
						'<button type="button" class="ffe-editor-panel__button ffe-editor-panel__button--secondary ffe-editor-panel__button--nav ffe-editor-panel__next" aria-label="' + nextLabel + '" title="' + nextTooltip + '"><span class="ffe-editor-panel__button-arrow" aria-hidden="true">&#8594;</span></button>' +
					'</div>' +
					'<button type="button" class="ffe-editor-panel__button ffe-editor-panel__button--secondary ffe-editor-panel__cancel">' + escapeHtml( getString( 'cancelLabel', 'Cancel' ) ) + '</button>' +
					'<button type="button" class="ffe-editor-panel__button ffe-editor-panel__button--primary ffe-editor-panel__save" title="' + saveTooltip + '">' + escapeHtml( getString( 'saveLabel', 'Save' ) ) + '</button>' +
				'</div>' +
			'</aside>'
		);

		panel.find( '.ffe-editor-panel__previous' ).on( 'click', function() {
			openAdjacentItemPanel( formId, -1 );
		} );

		panel.find( '.ffe-editor-panel__next' ).on( 'click', function() {
			openAdjacentItemPanel( formId, 1 );
		} );

		panel.find( '.ffe-editor-panel__close, .ffe-editor-panel__cancel' ).on( 'click', function() {
			closePanel( formId );
		} );

		panel.find( '.ffe-editor-panel__save' ).on( 'click', function() {
			saveChanges( formId );
		} );

		panel.on( 'click', '.gf_insert_field_choice', function( event ) {
			event.preventDefault();
			insertChoiceRow( $( this ).closest( '.ffe-editor-panel__control--choices' ), $( this ).closest( '.field-choice-row' ) );
		} );

		panel.on( 'click', '.gf_delete_field_choice', function( event ) {
			event.preventDefault();
			deleteChoiceRow( $( this ).closest( '.ffe-editor-panel__control--choices' ), $( this ).closest( '.field-choice-row' ) );
		} );

		panel.on( 'keydown', function( event ) {
			var key = String( event.key || '' ).toLowerCase();

			if ( handleArrowNavigation( formId, event ) ) {
				return;
			}

			if ( key !== 'escape' && event.keyCode !== 27 ) {
				return;
			}

			event.preventDefault();
			closePanel( formId );
		} );

		panel.on( 'keydown', '.ffe-editor-panel__control input, .ffe-editor-panel__control textarea, .ffe-editor-panel__control select', function( event ) {
			var key = String( event.key || '' ).toLowerCase();
			var isSaveShortcut = event.ctrlKey && ! event.altKey && ! event.metaKey && ! event.shiftKey && ( key === 's' || key === 'enter' || event.keyCode === 83 || event.keyCode === 13 );

			if ( ! isSaveShortcut ) {
				return;
			}

			if ( panel.find( '.ffe-editor-panel__save' ).prop( 'disabled' ) ) {
				return;
			}

			event.preventDefault();
			saveChanges( formId );
		} );

		$( 'body' ).append( panel );
		updatePanelNavigationButtons( formId, panel );

		return panel;
	}

	function refreshFieldButtons( formId, state ) {
		var config = state.config;

		$.each( config.fields || {}, function( fieldId, fieldConfig ) {
			var field = getFieldWrapper( formId, fieldId );
			var actionContainer;
			var button;

			if ( ! field.length ) {
				return;
			}

			field.toggleClass( 'ffe-editable-field', true );
			actionContainer = getFieldActionContainer( field );
			button = actionContainer.children( '.ffe-field-edit-button' );

			if ( ! button.length ) {
				button = $( getEditButtonMarkup() );
				actionContainer.append( button );
			}

			button.off( 'click.ffe' ).on( 'click.ffe', function( event ) {
				event.preventDefault();
				openItemPanel( formId, 'field', fieldId );
			} );
		} );
	}

	function getFormItemActionContainer( element ) {
		var header = element.parent( '.ffe-form-edit-header' );
		var container;

		if ( ! header.length ) {
			header = $( '<div class="ffe-form-edit-header"></div>' );
			element.before( header );
			header.append( element );
		}

		container = header.children( '.ffe-field-edit-actions' ).first();

		if ( ! container.length ) {
			container = $( '<div class="ffe-field-edit-actions"></div>' );
			header.append( container );
		}

		return container;
	}

	function refreshFormButtons( formId, state ) {
		var config = state.config;

		$.each( config.formItems || {}, function( itemKey, itemConfig ) {
			var element = getFormItemElement( formId, itemKey );
			var actionContainer;
			var button;

			if ( ! element.length ) {
				return;
			}

			actionContainer = getFormItemActionContainer( element );
			button = actionContainer.children( '.ffe-field-edit-button' );

			if ( ! button.length ) {
				button = $( getEditButtonMarkup() );
				actionContainer.append( button );
			}

			button.off( 'click.ffe' ).on( 'click.ffe', function( event ) {
				event.preventDefault();
				openItemPanel( formId, 'form', itemKey );
			} );
		} );
	}

	function buildFieldControls( fieldConfig ) {
		var html = '';
		var orderedSettings = getOrderedSettings( fieldConfig );

		$.each( orderedSettings, function( index, settingKey ) {
			var isChoices = isChoiceListSetting( settingKey );
			var isNameFields = isNameFieldsSetting( settingKey );
			var isTimeFormat = isTimeFormatSetting( settingKey );
			var isTimeSubLabels = isTimeSubLabelsSetting( settingKey );
			var isAddressFields = isAddressFieldsSetting( settingKey );
			var value;
			var isMultiline = settingKey === 'description';
			var isCheckbox = isCheckboxSetting( settingKey );
			var controlId = getControlId( fieldConfig, settingKey );

			if ( isChoices ) {
				value = normalizeChoiceListValue( fieldConfig.values[ settingKey ] );
			} else if ( isNameFields ) {
				value = normalizeNameFieldListValue( fieldConfig.values[ settingKey ] );
			} else if ( isTimeSubLabels ) {
				value = normalizeTimeSubLabelListValue( fieldConfig.values[ settingKey ] );
			} else if ( isAddressFields ) {
				value = normalizeAddressFieldListValue( fieldConfig.values[ settingKey ], false );
			} else {
				value = normalizeSettingValue( settingKey, fieldConfig.values[ settingKey ] );
			}

			if ( isCheckbox ) {
				html += buildCheckboxControl( fieldConfig, settingKey, 'ffe-editor-panel__control ffe-editor-panel__control--checkbox' );
				return;
			}

			if ( isChoices ) {
				html += buildChoicesControl( fieldConfig, controlId, value );
				return;
			}

			if ( isNameFields ) {
				html += buildNameFieldsControl( fieldConfig, controlId, value );
				return;
			}

			if ( isTimeFormat ) {
				html += buildTimeFormatControl( fieldConfig, controlId, value );
				return;
			}

			if ( isTimeSubLabels ) {
				html += buildTimeSubLabelsControl( fieldConfig, controlId, value );
				return;
			}

			if ( isAddressFields ) {
				html += buildAddressFieldsControl( fieldConfig, controlId, value );
				return;
			}

			html += '<div class="ffe-editor-panel__control" data-setting-key="' + escapeHtml( settingKey ) + '">';
			html += '<label class="ffe-editor-panel__control-label" for="' + escapeHtml( controlId ) + '">' + escapeHtml( humanizeSetting( settingKey ) ) + '</label>';

			if ( isMultiline ) {
				html += '<textarea id="' + escapeHtml( controlId ) + '" class="' + ( settingKey === 'description' ? 'ffe-editor-panel__textarea-autogrow' : '' ) + '" rows="' + ( settingKey === 'description' ? '1' : '2' ) + '">' + escapeHtml( value ) + '</textarea>';
			} else {
				html += '<input type="text" id="' + escapeHtml( controlId ) + '" value="' + escapeHtml( value ) + '" />';
			}

			html += '</div>';
		} );

		return html;
	}

	function buildTimeFormatControl( fieldConfig, controlId, timeFormat ) {
		var normalizedTimeFormat = normalizeSettingValue( 'time_format', timeFormat );
		var options = [
			{ value: '12', label: getString( 'timeFormat12Label', '12 hour' ) },
			{ value: '24', label: getString( 'timeFormat24Label', '24 hour' ) }
		];
		var html = '';

		html += '<div class="ffe-editor-panel__control ffe-editor-panel__control--time-format" data-setting-key="time_format">';
		html += '<label class="ffe-editor-panel__control-label" for="' + escapeHtml( controlId ) + '">' + escapeHtml( humanizeSetting( 'time_format' ) ) + '</label>';
		html += '<select id="' + escapeHtml( controlId ) + '">';

		$.each( options, function( index, option ) {
			html += '<option value="' + escapeHtml( option.value ) + '"' + ( normalizedTimeFormat === option.value ? ' selected' : '' ) + '>' + escapeHtml( option.label ) + '</option>';
		} );

		html += '</select>';
		html += '</div>';

		return html;
	}

	function buildTimeSubLabelsControl( fieldConfig, controlId, timeFields ) {
		var html = '';

		html += '<div class="ffe-editor-panel__control ffe-editor-panel__control--time-sub-labels" data-setting-key="time_sub_labels" data-control-id="' + escapeHtml( controlId ) + '">';
		html += '<span class="ffe-editor-panel__control-label">' + escapeHtml( humanizeSetting( 'time_sub_labels' ) ) + '</span>';
		html += '<div class="field_custom_inputs_ui gform-sidebar-setting-grid-wrapper gform-sidebar-setting-grid-wrapper__two-column">';
		html += '<div class="gform-sidebar-setting-grid-header"><span>' + escapeHtml( getString( 'fieldLabel', 'Field' ) ) + '</span><span>' + escapeHtml( getString( 'customSubLabelLabel', 'Custom Sub-Label' ) ) + '</span></div>';

		$.each( timeFields, function( index, timeField ) {
			html += buildTimeSubLabelRowMarkup( controlId, timeField, index );
		} );

		html += '</div>';
		html += '</div>';

		return html;
	}

	function buildAddressFieldsControl( fieldConfig, controlId, addressFields ) {
		var html = '';

		html += '<div class="ffe-editor-panel__control ffe-editor-panel__control--address-fields" data-setting-key="address_fields" data-control-id="' + escapeHtml( controlId ) + '">';
		html += '<span class="ffe-editor-panel__control-label">' + escapeHtml( humanizeSetting( 'address_fields' ) ) + '</span>';
		html += '<div class="field_custom_inputs_ui gform-sidebar-setting-grid-wrapper gform-sidebar-setting-grid-wrapper__three-column">';
		html += '<div class="gform-sidebar-setting-grid-header"><span>' + escapeHtml( getString( 'showLabel', 'Show' ) ) + '</span><span>' + escapeHtml( getString( 'fieldLabel', 'Field' ) ) + '</span><span>' + escapeHtml( getString( 'customSubLabelLabel', 'Custom Sub-Label' ) ) + '</span></div>';

		$.each( addressFields, function( index, addressField ) {
			html += buildAddressFieldRowMarkup( controlId, addressField, index );
		} );

		html += '</div>';
		html += '</div>';

		return html;
	}

	function buildNameFieldsControl( fieldConfig, controlId, nameFields ) {
		var html = '';

		html += '<div class="ffe-editor-panel__control ffe-editor-panel__control--name-fields" data-setting-key="name_fields" data-control-id="' + escapeHtml( controlId ) + '">';
		html += '<span class="ffe-editor-panel__control-label">' + escapeHtml( humanizeSetting( 'name_fields' ) ) + '</span>';
		html += '<div class="field_custom_inputs_ui gform-sidebar-setting-grid-wrapper gform-sidebar-setting-grid-wrapper__three-column">';
		html += '<div class="gform-sidebar-setting-grid-header"><span>' + escapeHtml( getString( 'showLabel', 'Show' ) ) + '</span><span>' + escapeHtml( getString( 'fieldLabel', 'Field' ) ) + '</span><span>' + escapeHtml( getString( 'customSubLabelLabel', 'Custom Sub-Label' ) ) + '</span></div>';

		$.each( nameFields, function( index, nameField ) {
			html += buildNameFieldRowMarkup( controlId, nameField, index );
		} );

		html += '</div>';
		html += '</div>';

		return html;
	}

	function buildTimeSubLabelRowMarkup( controlId, timeField, index ) {
		var customLabelId;

		timeField = normalizeTimeSubLabelValue( timeField );
		customLabelId = controlId + '-custom-label-' + index;

		return '' +
			'<div data-input-id="' + escapeHtml( timeField.id ) + '" class="field_custom_input_row field_custom_input_row_' + escapeHtml( timeField.id.replace( /[^a-z0-9_-]/ig, '_' ) ) + '">' +
				'<label for="' + escapeHtml( customLabelId ) + '">' + escapeHtml( timeField.defaultLabel ) + '</label>' +
				'<input class="field_custom_input_default_label" type="text" id="' + escapeHtml( customLabelId ) + '" placeholder="' + escapeHtml( timeField.defaultLabel ) + '" value="' + escapeHtml( timeField.customLabel ) + '" />' +
				'<input type="hidden" class="ffe-time-field-id" value="' + escapeHtml( timeField.id ) + '" />' +
				'<input type="hidden" class="ffe-time-field-default-label" value="' + escapeHtml( timeField.defaultLabel ) + '" />' +
			'</div>';
	}

	function buildNameFieldRowMarkup( controlId, nameField, index ) {
		var toggleId;
		var customLabelId;
		var isActive;
		var toggleStateText;

		nameField = normalizeNameFieldValue( nameField );
		toggleId = controlId + '-toggle-' + index;
		customLabelId = controlId + '-custom-label-' + index;
		isActive = ! nameField.isHidden;
		toggleStateText = getString( isActive ? 'activeText' : 'inactiveText', isActive ? 'Active' : 'Inactive' );

		return '' +
			'<div data-input-id="' + escapeHtml( nameField.id ) + '" class="field_custom_input_row field_custom_input_row_' + escapeHtml( nameField.id.replace( /[^a-z0-9_-]/ig, '_' ) ) + '">' +
				'<div><div data-input_id="' + escapeHtml( nameField.id ) + '" class="gform-field__toggle">' +
					'<span class="gform-settings-input__container">' +
						'<input class="gform-field__toggle-input" type="checkbox" name="' + escapeHtml( toggleId ) + '" id="' + escapeHtml( toggleId ) + '"' + ( isActive ? ' checked' : '' ) + ' />' +
						'<label class="gform-field__toggle-container" for="' + escapeHtml( toggleId ) + '">' +
							'<span class="gform-field__toggle-switch-text screen-reader-text">' + escapeHtml( toggleStateText ) + '</span>' +
							'<span class="gform-field__toggle-switch"></span>' +
						'</label>' +
					'</span>' +
				'</div></div>' +
				'<label for="' + escapeHtml( customLabelId ) + '">' + escapeHtml( nameField.defaultLabel ) + '</label>' +
				'<input class="field_custom_input_default_label" type="text" id="' + escapeHtml( customLabelId ) + '" placeholder="' + escapeHtml( nameField.defaultLabel ) + '" value="' + escapeHtml( nameField.customLabel ) + '" />' +
				'<input type="hidden" class="ffe-name-field-id" value="' + escapeHtml( nameField.id ) + '" />' +
				'<input type="hidden" class="ffe-name-field-default-label" value="' + escapeHtml( nameField.defaultLabel ) + '" />' +
			'</div>';
	}

	function buildAddressFieldRowMarkup( controlId, addressField, index ) {
		var toggleId;
		var customLabelId;
		var displayLabel;
		var placeholderLabel;
		var isActive;
		var toggleStateText;

		addressField = normalizeAddressFieldValue( addressField );
		toggleId = controlId + '-toggle-' + index;
		customLabelId = controlId + '-custom-label-' + index;
		displayLabel = getAddressFieldEditorDisplayLabel( addressField );
		placeholderLabel = addressField.defaultLabel || displayLabel;
		isActive = ! addressField.isHidden;
		toggleStateText = getString( isActive ? 'activeText' : 'inactiveText', isActive ? 'Active' : 'Inactive' );

		return '' +
			'<div data-input-id="' + escapeHtml( addressField.id ) + '" class="field_custom_input_row field_custom_input_row_' + escapeHtml( addressField.id.replace( /[^a-z0-9_-]/ig, '_' ) ) + '">' +
				'<div><div data-input_id="' + escapeHtml( addressField.id ) + '" class="gform-field__toggle">' +
					'<span class="gform-settings-input__container">' +
						'<input class="gform-field__toggle-input" type="checkbox" name="' + escapeHtml( toggleId ) + '" id="' + escapeHtml( toggleId ) + '"' + ( isActive ? ' checked' : '' ) + ' />' +
						'<label class="gform-field__toggle-container" for="' + escapeHtml( toggleId ) + '">' +
							'<span class="gform-field__toggle-switch-text screen-reader-text">' + escapeHtml( toggleStateText ) + '</span>' +
							'<span class="gform-field__toggle-switch"></span>' +
						'</label>' +
					'</span>' +
				'</div></div>' +
				'<label for="' + escapeHtml( customLabelId ) + '">' + escapeHtml( displayLabel ) + '</label>' +
				'<input class="field_custom_input_default_label" type="text" id="' + escapeHtml( customLabelId ) + '" placeholder="' + escapeHtml( placeholderLabel ) + '" value="' + escapeHtml( addressField.customLabel ) + '" />' +
				'<input type="hidden" class="ffe-address-field-id" value="' + escapeHtml( addressField.id ) + '" />' +
				'<input type="hidden" class="ffe-address-field-default-label" value="' + escapeHtml( addressField.defaultLabel ) + '" />' +
			'</div>';
	}

	function getAddressFieldEditorDisplayLabel( addressField ) {
		var defaultLabel = String( addressField && addressField.defaultLabel ? addressField.defaultLabel : '' );

		switch ( defaultLabel ) {
			case 'State / Province / Region':
				return 'State/Prov.';

			case 'ZIP / Postal Code':
				return 'ZIP / Postal';

			default:
				return defaultLabel;
		}
	}

	function buildChoicesControl( fieldConfig, controlId, choices ) {
		var html = '';
		var selectionInputType = getChoiceSelectionInputType( fieldConfig );
		var renderType = getFieldChoiceRenderType( fieldConfig );
		var firstChoiceInputId = controlId + '-choice-0-text';

		html += '<div class="ffe-editor-panel__control ffe-editor-panel__control--choices" data-setting-key="choices" data-control-id="' + escapeHtml( controlId ) + '" data-selection-input-type="' + escapeHtml( selectionInputType ) + '" data-render-type="' + escapeHtml( renderType ) + '">';
		html += '<label class="ffe-editor-panel__control-label" for="' + escapeHtml( firstChoiceInputId ) + '">' + escapeHtml( humanizeSetting( 'choices' ) ) + '</label>';
		html += '<div class="ffe-editor-panel__choices-list-wrapper">';
		html += '<ul class="ffe-editor-panel__choices-list" id="' + escapeHtml( controlId ) + '">';

		$.each( choices, function( index, choice ) {
			html += buildChoiceRowMarkup( controlId, choice, index, selectionInputType );
		} );

		html += '</ul>';
		html += '</div>';
		html += '</div>';

		return html;
	}

	function buildChoiceRowMarkup( controlId, choice, index, selectionInputType ) {
		var addLabel = escapeHtml( getString( 'addChoiceLabel', 'Add choice' ) );
		var deleteLabel = escapeHtml( getString( 'deleteChoiceLabel', 'Delete choice' ) );
		var selectedLabel = escapeHtml( getString( 'selectedChoiceLabel', 'Selected by default' ) );
		var baseId = controlId + '-choice-' + index;
		var selectedInputId = baseId + '-selected';
		var textInputId = baseId + '-text';
		var selectedInputName = 'radio' === selectionInputType ? controlId + '-selected' : controlId + '-selected-' + index;

		choice = normalizeChoiceValue( choice, index );

		return '' +
			'<li class="field-choice-row gform-choice" data-choice-index="' + index + '">' +
				'<span class="field-choice-handle gform-choice__handle" aria-hidden="true"></span>' +
				'<input type="' + escapeHtml( selectionInputType ) + '" class="field-choice-type gform-choice__selected" id="' + escapeHtml( selectedInputId ) + '" name="' + escapeHtml( selectedInputName ) + '"' + ( choice.isSelected ? ' checked' : '' ) + ' />' +
				'<label class="gform-choice__selected-label" for="' + escapeHtml( selectedInputId ) + '" aria-label="' + selectedLabel + '" title="' + selectedLabel + '"><span class="gform-choice__selected-icon" aria-hidden="true">&#10003;</span></label>' +
				'<input type="text" class="field-choice-text gform-choice__input gform-choice__input--label" id="' + escapeHtml( textInputId ) + '" value="' + escapeHtml( choice.text ) + '" />' +
				'<input type="hidden" class="ffe-choice-value" value="' + escapeHtml( choice.value ) + '" />' +
				'<input type="hidden" class="ffe-choice-key" value="' + escapeHtml( choice.key ) + '" />' +
				'<input type="hidden" class="ffe-choice-input-id" value="' + escapeHtml( choice.inputId ) + '" />' +
				'<input type="hidden" class="ffe-choice-source-index" value="' + escapeHtml( choice.sourceIndex ) + '" />' +
				'<button type="button" class="field-choice-button field-choice-button--insert gform-choice__button gform-choice__button--add gf_insert_field_choice" aria-label="' + addLabel + '" title="' + addLabel + '"></button>' +
				'<button type="button" class="field-choice-button field-choice-button--delete gform-choice__button gform-choice__button--delete gf_delete_field_choice" aria-label="' + deleteLabel + '" title="' + deleteLabel + '"></button>' +
			'</li>';
	}

	function buildCheckboxControl( fieldConfig, settingKey, controlClassName ) {
		var controlId = getControlId( fieldConfig, settingKey );
		var isChecked = normalizeSettingValue( settingKey, fieldConfig.values[ settingKey ] ) === '1';
		var html = '';

		html += '<div class="' + escapeHtml( controlClassName ) + '" data-setting-key="' + escapeHtml( settingKey ) + '">';
		html += '<div class="ffe-editor-panel__checkbox-row">';
		html += '<input type="checkbox" class="gfield-choice-input" id="' + escapeHtml( controlId ) + '"' + ( isChecked ? ' checked' : '' ) + ' />';
		html += '<label class="inline" for="' + escapeHtml( controlId ) + '">' + escapeHtml( humanizeSetting( settingKey ) ) + '</label>';
		html += '</div>';
		html += '</div>';

		return html;
	}

	function moveSettingAfter( orderedSettings, settingKey, anchorKey ) {
		var settingIndex = orderedSettings.indexOf( settingKey );
		var anchorIndex = orderedSettings.indexOf( anchorKey );

		if ( settingIndex === -1 || anchorIndex === -1 ) {
			return orderedSettings;
		}

		orderedSettings.splice( settingIndex, 1 );
		anchorIndex = orderedSettings.indexOf( anchorKey );
		orderedSettings.splice( anchorIndex + 1, 0, settingKey );

		return orderedSettings;
	}

	function getOrderedSettings( fieldConfig ) {
		var orderedSettings = $.isArray( fieldConfig && fieldConfig.settings ) ? fieldConfig.settings.slice() : [];
		var descriptionAnchor = 'label';
		var hadRequired = orderedSettings.indexOf( 'required' ) !== -1;

		if ( isConsentFieldConfig( fieldConfig ) ) {
			orderedSettings = moveSettingAfter( orderedSettings, 'consent_checkbox_label', 'label' );

			if ( orderedSettings.indexOf( 'consent_checkbox_label' ) !== -1 ) {
				descriptionAnchor = 'consent_checkbox_label';
			}
		}

		orderedSettings = moveSettingAfter( orderedSettings, 'description', descriptionAnchor );

		if ( isTimeFieldConfig( fieldConfig ) ) {
			orderedSettings = moveSettingAfter( orderedSettings, 'time_format', 'description' );
			orderedSettings = moveSettingAfter( orderedSettings, 'time_sub_labels', orderedSettings.indexOf( 'time_format' ) !== -1 ? 'time_format' : 'description' );
		}

		if ( ! hadRequired ) {
			return orderedSettings;
		}

		orderedSettings = $.grep( orderedSettings, function( settingKey ) {
			return settingKey !== 'required';
		} );

		orderedSettings.push( 'required' );

		return orderedSettings;
	}

	function getControlId( fieldConfig, settingKey ) {
		var baseId = ( fieldConfig && fieldConfig.id != null ? String( fieldConfig.id ) : 'field' ) + '-' + settingKey;

		return 'ffe-editor-control-' + baseId.replace( /[^a-z0-9_-]/ig, '-' );
	}

	function isChoiceListSetting( settingKey ) {
		return settingKey === 'choices';
	}

	function isNameFieldsSetting( settingKey ) {
		return settingKey === 'name_fields';
	}

	function isTimeFormatSetting( settingKey ) {
		return settingKey === 'time_format';
	}

	function isTimeSubLabelsSetting( settingKey ) {
		return settingKey === 'time_sub_labels';
	}

	function isAddressFieldsSetting( settingKey ) {
		return settingKey === 'address_fields';
	}

	function isCheckboxSetting( settingKey ) {
		return settingKey === 'required' || settingKey === 'email_confirmation';
	}

	function getFieldChoiceRenderType( fieldConfig ) {
		var fieldType = String( fieldConfig && fieldConfig.type ? fieldConfig.type : '' );

		if ( fieldType === 'select' ) {
			return 'select';
		}

		return fieldAllowsMultipleSelectedChoices( fieldConfig ) ? 'checkbox' : 'radio';
	}

	function getChoiceSelectionInputType( fieldConfig ) {
		return fieldAllowsMultipleSelectedChoices( fieldConfig ) ? 'checkbox' : 'radio';
	}

	function fieldAllowsMultipleSelectedChoices( fieldConfig ) {
		var fieldType = String( fieldConfig && fieldConfig.type ? fieldConfig.type : '' );
		var inputType = String( fieldConfig && fieldConfig.inputType ? fieldConfig.inputType : fieldType );
		var choiceLimit = String( fieldConfig && fieldConfig.choiceLimit ? fieldConfig.choiceLimit : '' );

		if ( fieldType === 'checkbox' ) {
			return true;
		}

		if ( fieldType === 'multi_choice' ) {
			return inputType === 'checkbox' || $.inArray( choiceLimit, [ 'exactly', 'range', 'unlimited' ] ) !== -1;
		}

		return false;
	}

	function isConsentFieldConfig( fieldConfig ) {
		var fieldType = String( fieldConfig && fieldConfig.type ? fieldConfig.type : '' );
		var inputType = String( fieldConfig && fieldConfig.inputType ? fieldConfig.inputType : '' );

		return fieldType === 'consent' || inputType === 'consent';
	}

	function isTimeFieldConfig( fieldConfig ) {
		var fieldType = String( fieldConfig && fieldConfig.type ? fieldConfig.type : '' );
		var inputType = String( fieldConfig && fieldConfig.inputType ? fieldConfig.inputType : '' );

		return fieldType === 'time' || inputType === 'time';
	}

	function normalizeChoiceListValue( value ) {
		var choices = [];

		if ( $.isArray( value ) ) {
			$.each( value, function( index, choice ) {
				choices.push( normalizeChoiceValue( choice, index ) );
			} );
		}

		if ( ! choices.length ) {
			choices.push( normalizeChoiceValue( null, 0 ) );
		}

		return choices;
	}

	function normalizeChoiceValue( choice, index ) {
		var normalizedChoice = $.isPlainObject( choice ) ? choice : {};
		var sourceIndex = normalizeChoiceSourceIndex( normalizedChoice.sourceIndex, index );

		return {
			text: normalizedChoice.text == null ? '' : String( normalizedChoice.text ),
			value: normalizedChoice.value == null ? '' : String( normalizedChoice.value ),
			isSelected: !! normalizedChoice.isSelected,
			key: normalizedChoice.key == null ? '' : String( normalizedChoice.key ),
			inputId: normalizedChoice.inputId == null ? '' : String( normalizedChoice.inputId ),
			sourceIndex: String( sourceIndex )
		};
	}

	function normalizeNameFieldListValue( value ) {
		var nameFields = [];

		if ( $.isArray( value ) ) {
			$.each( value, function( index, nameField ) {
				nameFields.push( normalizeNameFieldValue( nameField ) );
			} );
		}

		return nameFields;
	}

	function normalizeAddressFieldListValue( value, includeLocked ) {
		var addressFields = [];

		if ( $.isArray( value ) ) {
			$.each( value, function( index, addressField ) {
				var normalizedAddressField = normalizeAddressFieldValue( addressField );

				if ( includeLocked === false && normalizedAddressField.isLocked ) {
					return;
				}

				addressFields.push( normalizedAddressField );
			} );
		}

		return addressFields;
	}

	function normalizeTimeSubLabelListValue( value ) {
		var timeFields = [];

		if ( $.isArray( value ) ) {
			$.each( value, function( index, timeField ) {
				timeFields.push( normalizeTimeSubLabelValue( timeField ) );
			} );
		}

		return timeFields;
	}

	function normalizeNameFieldValue( nameField ) {
		var normalizedNameField = $.isPlainObject( nameField ) ? nameField : {};

		return {
			id: normalizedNameField.id == null ? '' : String( normalizedNameField.id ),
			defaultLabel: normalizedNameField.defaultLabel == null ? '' : String( normalizedNameField.defaultLabel ),
			customLabel: normalizedNameField.customLabel == null ? '' : String( normalizedNameField.customLabel ),
			isHidden: normalizedNameField.isHidden === true || normalizedNameField.isHidden === 1 || normalizedNameField.isHidden === '1' || normalizedNameField.isHidden === 'true'
		};
	}

	function normalizeAddressFieldValue( addressField ) {
		var normalizedAddressField = $.isPlainObject( addressField ) ? addressField : {};

		return {
			id: normalizedAddressField.id == null ? '' : String( normalizedAddressField.id ),
			defaultLabel: normalizedAddressField.defaultLabel == null ? '' : String( normalizedAddressField.defaultLabel ),
			customLabel: normalizedAddressField.customLabel == null ? '' : String( normalizedAddressField.customLabel ),
			isHidden: normalizedAddressField.isHidden === true || normalizedAddressField.isHidden === 1 || normalizedAddressField.isHidden === '1' || normalizedAddressField.isHidden === 'true',
			isLocked: normalizedAddressField.isLocked === true || normalizedAddressField.isLocked === 1 || normalizedAddressField.isLocked === '1' || normalizedAddressField.isLocked === 'true'
		};
	}

	function normalizeTimeSubLabelValue( timeField ) {
		var normalizedTimeField = $.isPlainObject( timeField ) ? timeField : {};

		return {
			id: normalizedTimeField.id == null ? '' : String( normalizedTimeField.id ),
			defaultLabel: normalizedTimeField.defaultLabel == null ? '' : String( normalizedTimeField.defaultLabel ),
			customLabel: normalizedTimeField.customLabel == null ? '' : String( normalizedTimeField.customLabel )
		};
	}

	function normalizeChoiceSourceIndex( value, fallback ) {
		var parsedValue = parseInt( value, 10 );

		if ( ! isNaN( parsedValue ) ) {
			return parsedValue;
		}

		return fallback;
	}

	function normalizeSettingValue( settingKey, value ) {
		if ( isCheckboxSetting( settingKey ) ) {
			return value === true || value === 1 || value === '1' || value === 'true' ? '1' : '0';
		}

		return value == null ? '' : String( value );
	}

	function getControlValue( control, settingKey ) {
		if ( isCheckboxSetting( settingKey ) ) {
			return control.find( 'input[type="checkbox"]' ).prop( 'checked' ) ? '1' : '0';
		}

		if ( isChoiceListSetting( settingKey ) ) {
			return getChoicesControlValue( control );
		}

		if ( isNameFieldsSetting( settingKey ) ) {
			return getNameFieldsControlValue( control );
		}

		if ( isTimeSubLabelsSetting( settingKey ) ) {
			return getTimeSubLabelsControlValue( control );
		}

		if ( isAddressFieldsSetting( settingKey ) ) {
			return getAddressFieldsControlValue( control );
		}

		if ( isTimeFormatSetting( settingKey ) ) {
			return String( control.find( 'select' ).val() || '' );
		}

		return String( control.find( 'textarea, input, select' ).val() || '' );
	}

	function getChoicesControlValue( control ) {
		var choices = [];

		control.find( '.field-choice-row' ).each( function( index ) {
			var row = $( this );

			choices.push(
				{
					text: String( row.find( '.field-choice-text' ).val() || '' ),
					value: String( row.find( '.ffe-choice-value' ).val() || '' ),
					isSelected: row.find( '.field-choice-type' ).prop( 'checked' ),
					key: String( row.find( '.ffe-choice-key' ).val() || '' ),
					inputId: String( row.find( '.ffe-choice-input-id' ).val() || '' ),
					sourceIndex: normalizeChoiceSourceIndex( row.find( '.ffe-choice-source-index' ).val(), index )
				}
			);
		} );

		return choices;
	}

	function getNameFieldsControlValue( control ) {
		var nameFields = [];

		control.find( '.field_custom_input_row' ).each( function() {
			var row = $( this );

			nameFields.push(
				{
					id: String( row.find( '.ffe-name-field-id' ).val() || row.data( 'inputId' ) || '' ),
					defaultLabel: String( row.find( '.ffe-name-field-default-label' ).val() || '' ),
					customLabel: String( row.find( '.field_custom_input_default_label' ).val() || '' ),
					isHidden: ! row.find( '.gform-field__toggle-input' ).prop( 'checked' )
				}
			);
		} );

		return nameFields;
	}

	function getTimeSubLabelsControlValue( control ) {
		var timeFields = [];

		control.find( '.field_custom_input_row' ).each( function() {
			var row = $( this );

			timeFields.push(
				{
					id: String( row.find( '.ffe-time-field-id' ).val() || row.data( 'inputId' ) || '' ),
					defaultLabel: String( row.find( '.ffe-time-field-default-label' ).val() || '' ),
					customLabel: String( row.find( '.field_custom_input_default_label' ).val() || '' )
				}
			);
		} );

		return timeFields;
	}

	function getAddressFieldsControlValue( control ) {
		var addressFields = [];

		control.find( '.field_custom_input_row' ).each( function() {
			var row = $( this );

			addressFields.push(
				{
					id: String( row.find( '.ffe-address-field-id' ).val() || row.data( 'inputId' ) || '' ),
					defaultLabel: String( row.find( '.ffe-address-field-default-label' ).val() || '' ),
					customLabel: String( row.find( '.field_custom_input_default_label' ).val() || '' ),
					isHidden: ! row.find( '.gform-field__toggle-input' ).prop( 'checked' )
				}
			);
		} );

		return addressFields;
	}

	function getComparableSettingValue( settingKey, value ) {
		if ( isChoiceListSetting( settingKey ) ) {
			return JSON.stringify( normalizeChoiceListValue( value ) );
		}

		if ( isNameFieldsSetting( settingKey ) ) {
			return JSON.stringify( normalizeNameFieldListValue( value ) );
		}

		if ( isTimeSubLabelsSetting( settingKey ) ) {
			return JSON.stringify( normalizeTimeSubLabelListValue( value ) );
		}

		if ( isAddressFieldsSetting( settingKey ) ) {
			return JSON.stringify( normalizeAddressFieldListValue( value, false ) );
		}

		return normalizeSettingValue( settingKey, value );
	}

	function getRequestSettingValue( settingKey, value ) {
		if ( isChoiceListSetting( settingKey ) ) {
			return JSON.stringify( normalizeChoiceListValue( value ) );
		}

		if ( isNameFieldsSetting( settingKey ) ) {
			return JSON.stringify( normalizeNameFieldListValue( value ) );
		}

		if ( isTimeSubLabelsSetting( settingKey ) ) {
			return JSON.stringify( normalizeTimeSubLabelListValue( value ) );
		}

		if ( isAddressFieldsSetting( settingKey ) ) {
			return JSON.stringify( normalizeAddressFieldListValue( value, false ) );
		}

		return value;
	}

	function humanizeSetting( settingKey ) {
		switch ( settingKey ) {
			case 'email_confirmation':
				return 'Enable Email Confirmation';

			case 'consent_checkbox_label':
				return 'Consent Checkbox Label';

				case 'time_format':
					return 'Time Format';

				case 'time_sub_labels':
					return 'Sub-Labels';

			case 'default_value':
				return 'Default Value';

			case 'admin_label':
				return 'Admin Field Label';

			default:
				return settingKey.replace( /_/g, ' ' ).replace( /\b\w/g, function( match ) {
					return match.toUpperCase();
				} );
		}
	}

	function openItemPanel( formId, itemType, itemKey ) {
		var state = getState( formId );
		var config = state.config;
		var itemConfig = getItemConfig( config, itemType, itemKey );
		var panel = ensurePanel( formId, state );
		var title;
		var subtitle;

		if ( ! itemConfig ) {
			return;
		}

		if ( 'form' === itemType ) {
			title = itemConfig.label || humanizeSetting( itemKey );
			subtitle = 'Form • ' + humanizeSetting( itemKey );
		} else {
			title = itemConfig.values.label || humanizeSetting( itemConfig.type );
			subtitle = 'Field ' + itemKey + ' • ' + itemConfig.type;
		}

		state.activeItemType = itemType;
		state.activeItemKey = String( itemKey );

		panel.find( '.ffe-editor-panel__title' ).text( title );
		panel.find( '.ffe-editor-panel__subtitle' ).text( subtitle );
		panel.find( '.ffe-editor-panel__body' ).html( buildFieldControls( itemConfig ) );
		initializeAutogrowControls( panel );
		initializeChoiceControls( panel );
		initializeNameFieldsControls( panel );
		initializeAddressFieldsControls( panel );
		panel.find( '.ffe-editor-panel__status' ).empty();
		updatePanelNavigationButtons( formId, panel );
		panel.attr( 'aria-hidden', 'false' ).addClass( 'is-open' );
		focusFirstEditableControl( panel );
	}

	function initializeAutogrowControls( panel ) {
		panel.find( '.ffe-editor-panel__textarea-autogrow' ).each( function() {
			initTextareaAutogrow( this );
		} );
	}

	function initializeChoiceControls( panel ) {
		panel.find( '.ffe-editor-panel__control--choices' ).each( function() {
			var control = $( this );

			ensureChoiceListSortable( control );
			updateChoiceControlState( control );
			updateChoiceListHeight( control );
		} );
	}

	function initializeNameFieldsControls( panel ) {
		panel.off( 'change.ffeNameFields', '.ffe-editor-panel__control--name-fields .gform-field__toggle-input' );
		panel.on( 'change.ffeNameFields', '.ffe-editor-panel__control--name-fields .gform-field__toggle-input', function() {
			updateNameFieldsControlState( $( this ).closest( '.ffe-editor-panel__control--name-fields' ) );
		} );

		panel.find( '.ffe-editor-panel__control--name-fields' ).each( function() {
			updateNameFieldsControlState( $( this ) );
		} );
	}

	function initializeAddressFieldsControls( panel ) {
		panel.off( 'change.ffeAddressFields', '.ffe-editor-panel__control--address-fields .gform-field__toggle-input' );
		panel.on( 'change.ffeAddressFields', '.ffe-editor-panel__control--address-fields .gform-field__toggle-input', function() {
			updateNameFieldsControlState( $( this ).closest( '.ffe-editor-panel__control--address-fields' ) );
		} );

		panel.find( '.ffe-editor-panel__control--address-fields' ).each( function() {
			updateNameFieldsControlState( $( this ) );
		} );
	}

	function updateNameFieldsControlState( control ) {
		control.find( '.field_custom_input_row' ).each( function() {
			var row = $( this );
			var isActive = row.find( '.gform-field__toggle-input' ).prop( 'checked' );

			row.toggleClass( 'is-inactive', ! isActive );
			row.find( '.gform-field__toggle-switch-text' ).text( getString( isActive ? 'activeText' : 'inactiveText', isActive ? 'Active' : 'Inactive' ) );
		} );
	}

	function ensureChoiceListSortable( control ) {
		var list = control.find( '.ffe-editor-panel__choices-list' );

		if ( ! list.length || ! $.fn.sortable ) {
			return;
		}

		if ( list.data( 'ui-sortable' ) ) {
			list.sortable( 'refresh' );
			return;
		}

		list.sortable(
			{
				axis: 'y',
				containment: 'parent',
				handle: '.field-choice-handle',
				items: '> .field-choice-row',
				tolerance: 'pointer',
				update: function() {
					updateChoiceControlState( control );
					updateChoiceListHeight( control );
				}
			}
		);
	}

	function insertChoiceRow( control, currentRow ) {
		var list = control.find( '.ffe-editor-panel__choices-list' );
		var controlId = String( control.data( 'controlId' ) || list.attr( 'id' ) || 'ffe-editor-control-choices' );
		var selectionInputType = String( control.data( 'selectionInputType' ) || 'radio' );
		var sourceIndex = getNextChoiceSourceIndex( control );
		var newRow = $( buildChoiceRowMarkup( controlId, { sourceIndex: sourceIndex }, list.children( '.field-choice-row' ).length, selectionInputType ) );

		if ( currentRow && currentRow.length ) {
			currentRow.after( newRow );
		} else {
			list.append( newRow );
		}

		if ( list.data( 'ui-sortable' ) ) {
			list.sortable( 'refresh' );
		}

		updateChoiceControlState( control );
		updateChoiceListHeight( control );
		newRow.find( '.field-choice-text' ).trigger( 'focus' );
	}

	function deleteChoiceRow( control, row ) {
		var list = control.find( '.ffe-editor-panel__choices-list' );
		var rows = list.children( '.field-choice-row' );
		var nextRow;

		if ( ! row.length || rows.length <= 1 ) {
			return;
		}

		nextRow = row.next( '.field-choice-row' );

		if ( ! nextRow.length ) {
			nextRow = row.prev( '.field-choice-row' );
		}

		row.remove();

		if ( list.data( 'ui-sortable' ) ) {
			list.sortable( 'refresh' );
		}

		updateChoiceControlState( control );
		updateChoiceListHeight( control );

		if ( nextRow.length ) {
			nextRow.find( '.field-choice-text' ).trigger( 'focus' );
		}
	}

	function updateChoiceControlState( control ) {
		var list = control.find( '.ffe-editor-panel__choices-list' );
		var rows = list.children( '.field-choice-row' );
		var controlId = String( control.data( 'controlId' ) || list.attr( 'id' ) || 'ffe-editor-control-choices' );
		var selectionInputType = String( control.data( 'selectionInputType' ) || 'radio' );

		rows.each( function( index ) {
			var row = $( this );
			var baseId = controlId + '-choice-' + index;
			var selectedInput = row.find( '.field-choice-type' );
			var textInput = row.find( '.field-choice-text' );
			var selectedLabel = row.find( '.gform-choice__selected-label' );

			row.attr( 'data-choice-index', index );
			selectedInput.attr( 'id', baseId + '-selected' );
			selectedInput.attr( 'name', 'radio' === selectionInputType ? controlId + '-selected' : controlId + '-selected-' + index );
			selectedLabel.attr( 'for', baseId + '-selected' );
			textInput.attr( 'id', baseId + '-text' );
		} );

		control.find( '.gf_delete_field_choice' )
			.prop( 'disabled', rows.length <= 1 )
			.attr( 'aria-disabled', rows.length <= 1 ? 'true' : 'false' );
	}

	function getNextChoiceSourceIndex( control ) {
		var nextIndex = 0;

		control.find( '.ffe-choice-source-index' ).each( function() {
			var currentValue = parseInt( $( this ).val(), 10 );

			if ( ! isNaN( currentValue ) && currentValue >= nextIndex ) {
				nextIndex = currentValue + 1;
			}
		} );

		return nextIndex;
	}

	function updateChoiceListHeight( control ) {
		var wrapper = control.find( '.ffe-editor-panel__choices-list-wrapper' ).get( 0 );
		var body;
		var controlNode;
		var bodyRect;
		var controlRect;
		var wrapperRect;
		var gap;
		var reservedHeight;
		var sibling;
		var controlTop;
		var maxHeight;

		if ( ! wrapper ) {
			return;
		}

		body = wrapper.closest( '.ffe-editor-panel__body' );
		controlNode = wrapper.closest( '.ffe-editor-panel__control' );

		if ( ! body || ! controlNode ) {
			return;
		}

		bodyRect = body.getBoundingClientRect();
		controlRect = controlNode.getBoundingClientRect();
		wrapperRect = wrapper.getBoundingClientRect();
		gap = parseFloat( window.getComputedStyle( body ).rowGap || window.getComputedStyle( body ).gap ) || 0;
		reservedHeight = controlRect.height - wrapperRect.height;
		sibling = controlNode.nextElementSibling;
		controlTop = Math.max( 0, controlRect.top - bodyRect.top );

		while ( sibling ) {
			reservedHeight += sibling.getBoundingClientRect().height + gap;
			sibling = sibling.nextElementSibling;
		}

		maxHeight = Math.max( 7.5 * 16, bodyRect.height - controlTop - reservedHeight );
		wrapper.style.maxHeight = maxHeight + 'px';
		wrapper.style.overflowY = wrapper.scrollHeight > maxHeight ? 'auto' : 'hidden';
	}

	function initTextareaAutogrow( textarea ) {
		var style = window.getComputedStyle( textarea );
		var declaredRows = parseInt( textarea.getAttribute( 'rows' ), 10 ) || 1;
		var lineHeight = parseFloat( style.lineHeight ) || 24;
		var paddingTop = parseFloat( style.paddingTop ) || 0;
		var paddingBottom = parseFloat( style.paddingBottom ) || 0;
		var borderTop = parseFloat( style.borderTopWidth ) || 0;
		var borderBottom = parseFloat( style.borderBottomWidth ) || 0;
		var minRows = Math.max( 1, declaredRows );
		var minHeight = minRows * lineHeight + paddingTop + paddingBottom + borderTop + borderBottom;
		var baseScrollHeight;

		textarea.style.boxSizing = 'border-box';
		textarea.style.overflowY = 'hidden';
		textarea.style.resize = 'none';

		baseScrollHeight = getBaseScrollHeight();

		function getBaseScrollHeight() {
			var originalValue = textarea.value;
			var originalRows = textarea.rows;
			var scrollHeight;

			textarea.value = '';
			textarea.rows = minRows;
			textarea.style.removeProperty( 'height' );
			scrollHeight = textarea.scrollHeight;
			textarea.value = originalValue;
			textarea.rows = originalRows;

			return scrollHeight;
		}

		function getAvailableHeight() {
			var body = textarea.closest( '.ffe-editor-panel__body' );
			var control = textarea.closest( '.ffe-editor-panel__control' );
			var bodyRect;
			var controlRect;
			var textareaRect;
			var gap;
			var reservedHeight;
			var sibling;
			var controlTop;

			if ( ! body || ! control ) {
				return minHeight;
			}

			bodyRect = body.getBoundingClientRect();
			controlRect = control.getBoundingClientRect();
			textareaRect = textarea.getBoundingClientRect();
			gap = parseFloat( window.getComputedStyle( body ).rowGap || window.getComputedStyle( body ).gap ) || 0;
			reservedHeight = controlRect.height - textareaRect.height;
			sibling = control.nextElementSibling;
			controlTop = Math.max( 0, controlRect.top - bodyRect.top );

			while ( sibling ) {
				reservedHeight += sibling.getBoundingClientRect().height + gap;
				sibling = sibling.nextElementSibling;
			}

			return Math.max( minHeight, bodyRect.height - controlTop - reservedHeight );
		}

		function resize() {
			var extraRows;
			var targetRows;
			var availableHeight = getAvailableHeight();

			textarea.rows = minRows;
			textarea.style.removeProperty( 'height' );
			extraRows = Math.max( 0, Math.ceil( ( textarea.scrollHeight - baseScrollHeight ) / lineHeight ) );
			targetRows = minRows + extraRows;
			textarea.rows = targetRows;
			textarea.style.maxHeight = availableHeight + 'px';
			textarea.style.overflowY = textarea.scrollHeight > availableHeight ? 'auto' : 'hidden';
		}

		resize();
		textarea.removeEventListener( 'input', textarea._ffeAutogrowHandler );
		textarea._ffeAutogrowHandler = resize;
		textarea.addEventListener( 'input', textarea._ffeAutogrowHandler );
	}

	function focusFirstEditableControl( panel ) {
		var focusControl = function() {
			var body = panel.find( '.ffe-editor-panel__body' );
			var firstControl = body
				.find( '.field-choice-text, textarea, input[type="text"], select' )
				.filter( ':enabled' )
				.not( '[readonly]' )
				.first();

			if ( ! firstControl.length ) {
				firstControl = body
					.find( 'textarea, input:not([type="hidden"]):not([type="button"]):not([type="submit"]):not([type="reset"]):not([type="image"]), select' )
					.filter( ':enabled' )
					.not( '[readonly]' )
					.first();
			}

			if ( ! firstControl.length ) {
				return;
			}

			firstControl.trigger( 'focus' );
		};

		if ( window.requestAnimationFrame ) {
			window.requestAnimationFrame( focusControl );
			return;
		}

		window.setTimeout( focusControl, 0 );
	}

	function handleArrowNavigation( formId, event ) {
		var key = String( event.key || '' ).toLowerCase();
		var direction = 0;

		if ( ! event.ctrlKey || event.altKey || event.metaKey || event.shiftKey ) {
			return false;
		}

		if ( key === 'arrowright' || event.keyCode === 39 ) {
			direction = 1;
		} else if ( key === 'arrowleft' || event.keyCode === 37 ) {
			direction = -1;
		} else {
			return false;
		}

		if ( ! openAdjacentItemPanel( formId, direction ) ) {
			return false;
		}

		event.preventDefault();
		event.stopPropagation();

		return true;
	}

	function updatePanelNavigationButtons( formId, panel ) {
		var isSaving = panel.find( '.ffe-editor-panel__save' ).prop( 'disabled' );
		var previousItem = getAdjacentItem( formId, -1 );
		var nextItem = getAdjacentItem( formId, 1 );
		var hasNavigation = !! previousItem || !! nextItem;

		panel.find( '.ffe-editor-panel__navigation' ).prop( 'hidden', ! hasNavigation );
		panel.find( '.ffe-editor-panel__previous' ).prop( 'disabled', ! previousItem || isSaving );
		panel.find( '.ffe-editor-panel__next' ).prop( 'disabled', ! nextItem || isSaving );
	}

	function openAdjacentItemPanel( formId, direction ) {
		var nextItem;
		var panel = $( '#ffe-editor-panel-' + formId );

		if ( panel.find( '.ffe-editor-panel__save' ).prop( 'disabled' ) ) {
			return false;
		}

		nextItem = getAdjacentItem( formId, direction );

		if ( ! nextItem ) {
			return false;
		}

		openItemPanel( formId, nextItem.itemType, nextItem.itemKey );

		return true;
	}

	function getAdjacentItem( formId, direction ) {
		var state = getState( formId );
		var orderedItems = getOrderedEditableItems( formId );
		var currentIndex;

		if ( ! state.activeItemType || ! state.activeItemKey ) {
			return null;
		}

		currentIndex = getActiveItemIndex( orderedItems, state.activeItemType, state.activeItemKey );

		if ( currentIndex === -1 || typeof orderedItems[ currentIndex + direction ] === 'undefined' ) {
			return null;
		}

		return orderedItems[ currentIndex + direction ];
	}

	function getActiveItemIndex( orderedItems, itemType, itemKey ) {
		var activeIndex = -1;

		$.each( orderedItems, function( index, item ) {
			if ( item.itemType === itemType && String( item.itemKey ) === String( itemKey ) ) {
				activeIndex = index;
				return false;
			}
		} );

		return activeIndex;
	}

	function getOrderedEditableItems( formId ) {
		var state = getState( formId );
		var orderedItems = [];
		var orderedFormItemKeys = [ 'title', 'description' ];

		$.each( orderedFormItemKeys, function( index, itemKey ) {
			if ( state.config && state.config.formItems && state.config.formItems[ itemKey ] ) {
				orderedItems.push(
					{
						itemType: 'form',
						itemKey: String( itemKey )
					}
				);
			}
		} );

		$.each( ( state.config && state.config.fields ) || {}, function( fieldId ) {
			orderedItems.push(
				{
					itemType: 'field',
					itemKey: String( fieldId )
				}
			);
		} );

		return orderedItems;
	}

	function closePanel( formId ) {
		var panel = $( '#ffe-editor-panel-' + formId );
		var state = getState( formId );

		state.activeItemKey = null;
		state.activeItemType = null;
		panel.attr( 'aria-hidden', 'true' ).removeClass( 'is-open' );
		updatePanelNavigationButtons( formId, panel );
	}

	function collectChangedValues( formId ) {
		var state = getState( formId );
		var itemKey = state.activeItemKey;
		var itemType = state.activeItemType;
		var config = state.config;
		var itemConfig = itemKey ? getItemConfig( config, itemType, itemKey ) : null;
		var changes = [];

		if ( ! itemConfig ) {
			return changes;
		}

		$( '#ffe-editor-panel-' + formId + ' .ffe-editor-panel__control' ).each( function() {
			var control = $( this );
			var settingKey = control.data( 'settingKey' );
			var value = getControlValue( control, settingKey );
			var oldValue = itemConfig.values[ settingKey ];

			if ( getComparableSettingValue( settingKey, oldValue ) !== getComparableSettingValue( settingKey, value ) ) {
				changes.push(
					{
						settingKey: settingKey,
						value: getRequestSettingValue( settingKey, value ),
						oldValue: oldValue
					}
				);
			}
		} );

		return changes;
	}

	function saveChanges( formId ) {
		var state = getState( formId );
		var config = state.config;
		var itemKey = state.activeItemKey;
		var itemType = state.activeItemType;
		var changes = collectChangedValues( formId );
		var panel = $( '#ffe-editor-panel-' + formId );
		var status = panel.find( '.ffe-editor-panel__status' );
		var saveButton = panel.find( '.ffe-editor-panel__save' );
		var requestIndex = 0;
		var didScopedRerender = false;

		if ( ! itemKey || ! changes.length ) {
			closePanel( formId );
			return;
		}

		saveButton.prop( 'disabled', true ).text( getString( 'savingLabel', 'Saving...' ) );
		updatePanelNavigationButtons( formId, panel );
		status.text( getString( 'savingLabel', 'Saving...' ) );

		function sendNext() {
			var change = changes[ requestIndex ];

			if ( ! change ) {
				saveButton.prop( 'disabled', false ).text( getString( 'saveLabel', 'Save' ) );
				status.text( getString( 'successLabel', 'Field updated.' ) );
				closePanel( formId );

				if ( didScopedRerender ) {
					triggerScopedRerenderRefresh( formId );
				}

				return;
			}

			$.ajax(
				{
					url: config.ajaxUrl,
					type: 'POST',
					dataType: 'json',
					data: {
						action: 'ffe_save_field_setting',
						nonce: config.nonce,
						form_id: formId,
						target_type: itemType,
						field_id: 'field' === itemType ? itemKey : '',
						setting_key: change.settingKey,
						value: change.value,
						current_page: getCurrentFormPage( formId ),
						form_hash: config.formHash
					}
				}
			)
				.done( function( response ) {
					var updatedItem;
					var patchedValue;
					var usedScopedRerender;

					if ( ! response || ! response.success ) {
						handleSaveFailure( response );
						return;
					}

					config.formHash = response.data.formHash;
					updatedItem = response.data.item || null;

					if ( updatedItem ) {
						if ( 'form' === itemType ) {
							config.formItems[ itemKey ] = updatedItem;
						} else {
							config.fields[ itemKey ] = updatedItem;
						}
					}

					patchedValue = updatedItem && updatedItem.values && Object.prototype.hasOwnProperty.call( updatedItem.values, change.settingKey ) ? updatedItem.values[ change.settingKey ] : response.data.value;
					usedScopedRerender = rerenderItemDom( formId, itemType, itemKey, response.data );

					if ( usedScopedRerender ) {
						didScopedRerender = true;
					} else {
						patchItemDom( formId, itemType, itemKey, change.settingKey, patchedValue, change.oldValue, config );
					}

					requestIndex += 1;
					sendNext();
				} )
				.fail( function( xhr ) {
					handleSaveFailure( xhr.responseJSON );
				} );
		}

		function handleSaveFailure( response ) {
			var errorMessage = getString( 'errorLabel', 'Unable to save the requested change.' );

			if ( response && response.data && response.data.message ) {
				errorMessage = response.data.message;
			}

			if ( response && response.data && response.data.code === 'form_conflict' ) {
				errorMessage = getString( 'conflictLabel', 'This form changed since the panel was opened. Refresh the page and try again.' );
			}

			saveButton.prop( 'disabled', false ).text( getString( 'saveLabel', 'Save' ) );
			updatePanelNavigationButtons( formId, panel );
			status.text( errorMessage );
		}

		sendNext();
	}

	function rerenderItemDom( formId, itemType, itemKey, responseData ) {
		if ( itemType !== 'field' || ! shouldUseRenderedFieldReplacement( responseData ) ) {
			return false;
		}

		return rerenderFieldDom( formId, itemKey, responseData.renderedFieldHtml );
	}

	function shouldUseRenderedFieldReplacement( responseData ) {
		return !! ( responseData && responseData.renderedFieldHtml );
	}

	function rerenderFieldDom( formId, fieldId, renderedFieldHtml ) {
		var existingField = getFieldWrapper( formId, fieldId );
		var renderedField;
		var preservedInputState;
		var state = getState( formId );

		if ( ! existingField.length || ! renderedFieldHtml ) {
			return false;
		}

		renderedField = $( $.trim( String( renderedFieldHtml ) ) ).first();

		if ( ! renderedField.length ) {
			return false;
		}

		preservedInputState = captureFieldInputState( existingField );
		existingField.replaceWith( renderedField );
		refreshFieldButtons( formId, state );
		restoreFieldInputState( getFieldWrapper( formId, fieldId ), preservedInputState );

		return true;
	}

	function captureFieldInputState( field ) {
		var inputStates = [];
		var keyCounts = {};

		field.find( 'input:not([type="hidden"]):not([type="submit"]):not([type="button"]):not([type="image"]):not([type="reset"]), select, textarea' ).each( function() {
			var input = $( this );
			var key = buildFieldInputStateKey( input, keyCounts );

			inputStates.push(
				{
					key: key,
					tagName: String( this.tagName || '' ).toLowerCase(),
					type: String( input.attr( 'type' ) || '' ).toLowerCase(),
					value: input.is( 'select[multiple]' ) ? ( input.val() || [] ) : input.val(),
					checked: input.is( ':checkbox, :radio' ) ? input.prop( 'checked' ) : null
				}
			);
		} );

		return inputStates;
	}

	function restoreFieldInputState( field, inputStates ) {
		var statesByKey = {};
		var keyCounts = {};

		if ( ! field.length || ! $.isArray( inputStates ) || ! inputStates.length ) {
			return;
		}

		$.each( inputStates, function( index, inputState ) {
			statesByKey[ inputState.key ] = inputState;
		} );

		field.find( 'input:not([type="hidden"]):not([type="submit"]):not([type="button"]):not([type="image"]):not([type="reset"]), select, textarea' ).each( function() {
			var input = $( this );
			var key = buildFieldInputStateKey( input, keyCounts );
			var inputState = statesByKey[ key ];

			if ( ! inputState ) {
				return;
			}

			if ( input.is( ':checkbox, :radio' ) ) {
				input.prop( 'checked', !! inputState.checked );
				return;
			}

			input.val( inputState.value );
		} );
	}

	function buildFieldInputStateKey( input, keyCounts ) {
		var keyBase = input.attr( 'name' ) ? 'name:' + input.attr( 'name' ) : ( input.attr( 'id' ) ? 'id:' + input.attr( 'id' ) : 'tag:' + String( input.prop( 'tagName' ) || '' ).toLowerCase() );
		var keyIndex = keyCounts[ keyBase ] || 0;

		keyCounts[ keyBase ] = keyIndex + 1;

		return keyBase + '::' + keyIndex;
	}

	function triggerScopedRerenderRefresh( formId ) {
		var currentPage = getCurrentFormPage( formId );

		if ( window.gform && window.gform.core && typeof window.gform.core.triggerPostRenderEvents === 'function' ) {
			window.gform.core.triggerPostRenderEvents( formId, currentPage );
			return;
		}

		$( document ).trigger( 'gform_post_render', [ formId, currentPage ] );
	}

	function patchFormItemDom( formId, itemKey, settingKey, value ) {
		var element = getFormItemElement( formId, itemKey );

		if ( ! element.length ) {
			return;
		}

		switch ( settingKey ) {
			case 'title':
			case 'description':
				element.html( value );
				break;
		}
	}

	function getRequiredIndicatorHtml( config ) {
		var indicatorHtml = config && config.requiredIndicatorHtml ? String( config.requiredIndicatorHtml ) : '<span class="gfield_required"><span class="gfield_required gfield_required_text">(Required)</span></span>';
		var indicator = $( '<div>' ).html( indicatorHtml );
		var root = indicator.children().first();

		if ( root.length && root.hasClass( 'gfield_required' ) && root.find( '.gfield_required_text, .gfield_required_custom, .gfield_required_asterisk' ).length ) {
			return indicatorHtml;
		}

		return '<span class="gfield_required">' + indicatorHtml + '</span>';
	}

	function patchRequiredIndicator( field, isRequired, config ) {
		var label = getPrimaryFieldLabelElement( field );
		var labelCopy;

		if ( ! label.length ) {
			return;
		}

		labelCopy = label.children( '.ffe-field-edit-legend-copy' ).first();

		if ( labelCopy.length ) {
			labelCopy.children( '.gfield_required' ).remove();

			if ( isRequired ) {
				labelCopy.append( getRequiredIndicatorHtml( config ) );
			}

			return;
		}

		label.children( '.gfield_required' ).remove();

		if ( isRequired ) {
			label.append( getRequiredIndicatorHtml( config ) );
		}
	}

	function patchRequiredAttributes( field, isRequired ) {
		field.toggleClass( 'gfield_contains_required', isRequired );

		field.find( 'input:not([type="hidden"]):not([type="submit"]):not([type="button"]):not([type="image"]), select, textarea' ).each( function() {
			var input = $( this );

			if ( isRequired ) {
				input.attr( 'aria-required', 'true' );
			} else {
				input.removeAttr( 'aria-required' );
			}
		} );
	}

	function patchChoicesFieldDom( formId, fieldId, value, config, field ) {
		var fieldConfig = getItemConfig( config, 'field', String( fieldId ) ) || {};
		var choices = normalizeChoiceListValue( value );
		var renderType = getFieldChoiceRenderType( fieldConfig );

		if ( renderType === 'select' ) {
			patchSelectChoicesFieldDom( field, choices );
			return;
		}

		patchChoiceInputsFieldDom( formId, field, fieldId, choices, renderType );
	}

	function patchSelectChoicesFieldDom( field, choices ) {
		var select = field.find( 'select' ).first();
		var placeholderOption;
		var hasSelectedChoice = false;

		if ( ! select.length ) {
			return;
		}

		placeholderOption = select.find( 'option[value=""]' ).first().clone();
		select.empty();

		if ( placeholderOption.length ) {
			select.append( placeholderOption );
		}

		$.each( choices, function( index, choice ) {
			var normalizedChoice = normalizeChoiceValue( choice, index );
			var option = $( '<option></option>' )
				.val( normalizedChoice.value )
				.text( normalizedChoice.text );

			if ( normalizedChoice.isSelected && ! hasSelectedChoice ) {
				option.prop( 'selected', true );
				hasSelectedChoice = true;
			}

			select.append( option );
		} );

		if ( placeholderOption.length ) {
			select.find( 'option' ).first().prop( 'selected', ! hasSelectedChoice );
		} else if ( ! hasSelectedChoice && select.find( 'option' ).length ) {
			select.find( 'option' ).first().prop( 'selected', true );
		}
	}

	function patchChoiceInputsFieldDom( formId, field, fieldId, choices, renderType ) {
		var container = field.find( 'checkbox' === renderType ? '.gfield_checkbox' : '.gfield_radio' ).first();
		var tagName;
		var selectAllRow;
		var selectAllHtml = '';
		var isRequired = field.hasClass( 'gfield_contains_required' );
		var selectedCount = 0;

		if ( ! container.length ) {
			return;
		}

		tagName = String( container.children( '.gchoice' ).not( '.gchoice_select_all' ).first().prop( 'tagName' ) || 'div' ).toLowerCase();
		selectAllRow = container.children( '.gchoice_select_all' ).first().clone();
		selectAllHtml = selectAllRow.length ? ( selectAllRow.prop( 'outerHTML' ) || '' ) : '';

		$.each( choices, function( index, choice ) {
			if ( normalizeChoiceValue( choice, index ).isSelected ) {
				selectedCount += 1;
			}
		} );

		container.html( buildRenderedChoiceItemsHtml( formId, fieldId, choices, renderType, tagName, isRequired, selectAllHtml ) );

		if ( selectAllRow.length ) {
			container.children( '.gchoice_select_all' ).find( '.gfield_choice_all_toggle' ).prop( 'checked', selectedCount === choices.length && choices.length > 0 );
		}
	}

	function patchNameFieldDom( formId, fieldId, value, config, field ) {
		var fieldConfig = getItemConfig( config, 'field', String( fieldId ) ) || {};
		var nameFields = normalizeNameFieldListValue( value );
		var nameState = captureNameFieldState( field, formId, fieldId );
		var html = buildNameFieldHtml( formId, fieldId, fieldConfig, nameState, nameFields, getResolvedNameSubLabelPlacement( config, fieldConfig ) );

		if ( ! html ) {
			return;
		}

		replaceNameFieldContainer( field, html );
		patchRequiredAttributes( field, field.hasClass( 'gfield_contains_required' ) );
	}

	function captureNameFieldState( field, formId, fieldId ) {
		var inputBaseId = getNameFieldBaseInputId( formId, fieldId );
		var state = $.extend( true, { inputs: {} }, field.data( 'ffeNameFieldState' ) || {} );
		var simpleInput = field.find( '#' + inputBaseId ).first();
		var suffixes = [ '2', '3', '4', '6', '8' ];

		if ( simpleInput.length ) {
			state.simpleValue = String( simpleInput.val() || state.simpleValue || '' );
			state.simplePlaceholder = String( simpleInput.attr( 'placeholder' ) || state.simplePlaceholder || '' );
			state.simpleAutocomplete = String( simpleInput.attr( 'autocomplete' ) || state.simpleAutocomplete || '' );
			state.simpleAriaDescribedby = String( simpleInput.attr( 'aria-describedby' ) || state.simpleAriaDescribedby || '' );
			state.simpleAriaInvalid = String( simpleInput.attr( 'aria-invalid' ) || state.simpleAriaInvalid || 'false' );
			state.simpleAriaRequired = String( simpleInput.attr( 'aria-required' ) || state.simpleAriaRequired || '' );
		}

		$.each( suffixes, function( index, suffix ) {
			var inputElement = field.find( '#' + inputBaseId + '_' + suffix ).first();
			var labelElement = field.find( 'label[for="' + inputBaseId + '_' + suffix + '"]' ).first();
			var inputState = $.extend( {}, state.inputs[ suffix ] || {} );

			if ( inputElement.length ) {
				inputState.value = String( inputElement.val() || inputState.value || '' );
				inputState.placeholder = String( inputElement.attr( 'placeholder' ) || inputState.placeholder || '' );
				inputState.autocomplete = String( inputElement.attr( 'autocomplete' ) || inputState.autocomplete || getDefaultNameAutocomplete( suffix ) );
				inputState.ariaDescribedby = String( inputElement.attr( 'aria-describedby' ) || inputState.ariaDescribedby || '' );
				inputState.ariaInvalid = String( inputElement.attr( 'aria-invalid' ) || inputState.ariaInvalid || 'false' );
				inputState.ariaRequired = String( inputElement.attr( 'aria-required' ) || inputState.ariaRequired || '' );
			} else if ( suffix === '3' && simpleInput.length ) {
				inputState.value = state.simpleValue || inputState.value || '';
				inputState.placeholder = state.simplePlaceholder || inputState.placeholder || '';
				inputState.autocomplete = state.simpleAutocomplete || inputState.autocomplete || getDefaultNameAutocomplete( suffix );
				inputState.ariaDescribedby = state.simpleAriaDescribedby || inputState.ariaDescribedby || '';
				inputState.ariaInvalid = state.simpleAriaInvalid || inputState.ariaInvalid || 'false';
				inputState.ariaRequired = state.simpleAriaRequired || inputState.ariaRequired || '';
			} else if ( ! inputState.autocomplete ) {
				inputState.autocomplete = getDefaultNameAutocomplete( suffix );
			}

			if ( labelElement.length ) {
				inputState.label = $.trim( labelElement.text() || inputState.label || '' );
			}

			state.inputs[ suffix ] = inputState;
		} );

		field.data( 'ffeNameFieldState', state );

		return state;
	}

	function buildNameFieldHtml( formId, fieldId, fieldConfig, nameState, nameFields, subLabelPlacement ) {
		var inputBaseId = getNameFieldBaseInputId( formId, fieldId );
		var subLabelClass = subLabelPlacement === 'hidden_label' ? 'hidden_sub_label screen-reader-text' : '';
		var prefixChoices = $.isArray( fieldConfig.namePrefixChoices ) ? fieldConfig.namePrefixChoices : [];
		var html = '';

		if ( ! nameFields.length ) {
			return html;
		}

		html += '<div class="ginput_complex ginput_container ginput_container--name ' + escapeHtml( buildNameFieldContainerClassName( nameFields ) ) + ' gform-grid-row" id="' + escapeHtml( inputBaseId ) + '">';

		$.each( nameFields, function( index, nameField ) {
			var normalizedNameField = normalizeNameFieldValue( nameField );
			var suffix = getNameFieldInputSuffix( normalizedNameField.id );
			var inputState = nameState && nameState.inputs ? ( nameState.inputs[ suffix ] || {} ) : {};
			var labelText = normalizedNameField.customLabel || normalizedNameField.defaultLabel || inputState.label || '';
			var inputHtml;

			if ( ! suffix || normalizedNameField.isHidden ) {
				return;
			}

			if ( suffix === '2' ) {
				inputHtml = buildNamePrefixInputHtml( fieldId, inputBaseId, inputState, prefixChoices, labelText );
			} else {
				inputHtml = buildNameTextInputHtml( fieldId, inputBaseId, suffix, inputState );
			}

			html += buildNameInputContainerHtml( inputBaseId, suffix, getNameFieldContainerClass( suffix, prefixChoices ), inputHtml, labelText, subLabelClass, subLabelPlacement );
		} );

		html += '</div>';

		return html;
	}

	function buildNamePrefixInputHtml( fieldId, inputBaseId, inputState, prefixChoices, fallbackLabel ) {
		var attributes;

		if ( $.isArray( prefixChoices ) && prefixChoices.length ) {
			attributes = buildHtmlAttributes(
				{
					name: 'input_' + String( fieldId ) + '.2',
					id: inputBaseId + '_2',
					autocomplete: inputState.autocomplete || getDefaultNameAutocomplete( '2' ),
					'aria-invalid': inputState.ariaInvalid || 'false',
					'aria-describedby': inputState.ariaDescribedby
				}
			);

			return '<select' + attributes + '>' + buildNamePrefixOptionsHtml( prefixChoices, inputState.value || '' ) + '</select>';
		}

		attributes = buildHtmlAttributes(
			{
				name: 'input_' + String( fieldId ) + '.2',
				id: inputBaseId + '_2',
				type: 'text',
				value: inputState.value || '',
				placeholder: inputState.placeholder || fallbackLabel || '',
				autocomplete: inputState.autocomplete || getDefaultNameAutocomplete( '2' ),
				'aria-invalid': inputState.ariaInvalid || 'false',
				'aria-describedby': inputState.ariaDescribedby
			}
		);

		return '<input' + attributes + ' />';
	}

	function buildNamePrefixOptionsHtml( prefixChoices, selectedValue ) {
		var html = '<option value=""></option>';

		$.each( prefixChoices || [], function( index, choice ) {
			var text = choice && choice.text != null ? String( choice.text ) : '';
			var value = choice && choice.value != null ? String( choice.value ) : text;
			var selected = String( selectedValue || '' ) === value ? ' selected="selected"' : '';

			html += '<option value="' + escapeHtml( value ) + '"' + selected + '>' + escapeHtml( text ) + '</option>';
		} );

		return html;
	}

	function buildNameTextInputHtml( fieldId, inputBaseId, suffix, inputState ) {
		var attributes = buildHtmlAttributes(
			{
				name: 'input_' + String( fieldId ) + '.' + suffix,
				id: inputBaseId + '_' + suffix,
				type: 'text',
				value: inputState.value || '',
				placeholder: inputState.placeholder || '',
				autocomplete: inputState.autocomplete || getDefaultNameAutocomplete( suffix ),
				'aria-invalid': inputState.ariaInvalid || 'false',
				'aria-describedby': inputState.ariaDescribedby
			}
		);

		return '<input' + attributes + ' />';
	}

	function buildNameInputContainerHtml( inputBaseId, suffix, containerClass, inputHtml, labelText, subLabelClass, subLabelPlacement ) {
		var labelHtml = '<label for="' + escapeHtml( inputBaseId + '_' + suffix ) + '" class="gform-field-label gform-field-label--type-sub ' + escapeHtml( subLabelClass ) + '">' + escapeHtml( labelText ) + '</label>';
		var content = subLabelPlacement === 'above' ? labelHtml + inputHtml : inputHtml + labelHtml;

		return '<span id="' + escapeHtml( inputBaseId + '_' + suffix ) + '_container" class="' + escapeHtml( containerClass ) + '">' + content + '</span>';
	}

	function buildNameFieldContainerClassName( nameFields ) {
		var classMap = {
			'2': 'prefix',
			'3': 'first_name',
			'4': 'middle_name',
			'6': 'last_name',
			'8': 'suffix'
		};
		var orderedSuffixes = [ '2', '3', '4', '6', '8' ];
		var classes = [];
		var visibleCount = 0;

		$.each( orderedSuffixes, function( index, suffix ) {
			var fieldConfig = getNameFieldConfigBySuffix( nameFields, suffix );
			var isVisible = !! fieldConfig && ! fieldConfig.isHidden;

			classes.push( ( isVisible ? 'has_' : 'no_' ) + classMap[ suffix ] );

			if ( isVisible ) {
				visibleCount += 1;
			}
		} );

		classes.push( 'gf_name_has_' + visibleCount );
		classes.push( 'ginput_container_name' );

		return classes.join( ' ' );
	}

	function getNameFieldConfigBySuffix( nameFields, suffix ) {
		var matchedField = null;

		$.each( nameFields || [], function( index, nameField ) {
			if ( getNameFieldInputSuffix( nameField.id ) === suffix ) {
				matchedField = normalizeNameFieldValue( nameField );
				return false;
			}
		} );

		return matchedField;
	}

	function getNameFieldContainerClass( suffix, prefixChoices ) {
		switch ( suffix ) {
			case '2':
				return 'name_prefix' + ( $.isArray( prefixChoices ) && prefixChoices.length ? ' name_prefix_select' : '' ) + ' gform-grid-col gform-grid-col--size-auto';

			case '3':
				return 'name_first gform-grid-col gform-grid-col--size-auto';

			case '4':
				return 'name_middle gform-grid-col gform-grid-col--size-auto';

			case '6':
				return 'name_last gform-grid-col gform-grid-col--size-auto';

			case '8':
				return 'name_suffix gform-grid-col gform-grid-col--size-auto';

			default:
				return 'gform-grid-col gform-grid-col--size-auto';
		}
	}

	function replaceNameFieldContainer( field, html ) {
		var containers = field.children( '.ginput_complex.ginput_container--name, .ginput_container.ginput_container_name, .ginput_container--name, .ginput_container_name' );
		var firstContainer = containers.first();
		var replacement = $( html );
		var nextContent;

		if ( firstContainer.length ) {
			firstContainer.before( replacement );
			containers.remove();
			return;
		}

		nextContent = field.children( '.gfield_description, .gfield_validation_message' ).first();

		if ( nextContent.length ) {
			nextContent.before( replacement );
			return;
		}

		field.append( replacement );
	}

	function getNameFieldBaseInputId( formId, fieldId ) {
		return 'input_' + String( formId ) + '_' + String( fieldId );
	}

	function getNameFieldInputSuffix( inputId ) {
		var matches = String( inputId || '' ).match( /\.(\d+)$/ );

		return matches ? matches[1] : '';
	}

	function getDefaultNameAutocomplete( suffix ) {
		switch ( String( suffix || '' ) ) {
			case '2':
				return 'honorific-prefix';

			case '3':
				return 'given-name';

			case '4':
				return 'additional-name';

			case '6':
				return 'family-name';

			case '8':
				return 'honorific-suffix';

			default:
				return '';
		}
	}

	function patchTimeFieldDom( formId, fieldId, value, config, field ) {
		var fieldConfig = getItemConfig( config, 'field', String( fieldId ) ) || {};
		var values = $.isPlainObject( fieldConfig.values ) ? fieldConfig.values : {};
		var timeFormat = normalizeSettingValue( 'time_format', values.time_format == null ? value : values.time_format );
		var timeFields = normalizeTimeSubLabelListValue( values.time_sub_labels );
		var timeState = captureTimeFieldState( field, formId, fieldId );
		var html = buildTimeFieldHtml( formId, fieldId, timeState, timeFields, timeFormat, getResolvedNameSubLabelPlacement( config, fieldConfig ) );

		if ( ! html ) {
			return;
		}

		replaceTimeFieldContainer( field, html );
		patchRequiredAttributes( field, field.hasClass( 'gfield_contains_required' ) );
	}

	function captureTimeFieldState( field, formId, fieldId ) {
		var inputBaseId = getTimeFieldBaseInputId( formId, fieldId );
		var state = $.extend( true, { inputs: {} }, field.data( 'ffeTimeFieldState' ) || {} );
		var container = field.children( '.ginput_container.ginput_complex.gform-grid-row, .ginput_complex.gform-grid-row' ).first();
		var suffixes = [ '1', '2', '3' ];

		if ( container.length ) {
			state.containerClass = String( container.attr( 'class' ) || state.containerClass || '' );
		}

		$.each( suffixes, function( index, suffix ) {
			var inputElement = field.find( '#' + inputBaseId + '_' + suffix ).first();
			var labelElement = field.find( 'label[for="' + inputBaseId + '_' + suffix + '"]' ).first();
			var inputState = $.extend( {}, state.inputs[ suffix ] || {} );

			if ( inputElement.length ) {
				inputState.value = String( inputElement.val() || inputState.value || '' );
				inputState.placeholder = String( inputElement.attr( 'placeholder' ) || inputState.placeholder || '' );
				inputState.tabindex = String( inputElement.attr( 'tabindex' ) || inputState.tabindex || '' );
				inputState.ariaDescribedby = String( inputElement.attr( 'aria-describedby' ) || inputState.ariaDescribedby || '' );
				inputState.ariaInvalid = String( inputElement.attr( 'aria-invalid' ) || inputState.ariaInvalid || 'false' );
				inputState.ariaRequired = String( inputElement.attr( 'aria-required' ) || inputState.ariaRequired || '' );
			}

			if ( labelElement.length ) {
				inputState.label = $.trim( labelElement.text() || inputState.label || '' );
			}

			state.inputs[ suffix ] = inputState;
		} );

		field.data( 'ffeTimeFieldState', state );

		return state;
	}

	function buildTimeFieldHtml( formId, fieldId, timeState, timeFields, timeFormat, subLabelPlacement ) {
		var inputBaseId = getTimeFieldBaseInputId( formId, fieldId );
		var isSubLabelAbove = subLabelPlacement === 'above';
		var colonPlacement = isSubLabelAbove ? 'above' : 'below';
		var containerClass = buildTimeFieldContainerClassName( timeState && timeState.containerClass );
		var hourField = getTimeFieldConfigBySuffix( timeFields, '1' ) || normalizeTimeSubLabelValue( { id: String( fieldId ) + '.1', defaultLabel: 'Hour' } );
		var minuteField = getTimeFieldConfigBySuffix( timeFields, '2' ) || normalizeTimeSubLabelValue( { id: String( fieldId ) + '.2', defaultLabel: 'Minute' } );
		var hourState = timeState && timeState.inputs ? ( timeState.inputs[ '1' ] || {} ) : {};
		var minuteState = timeState && timeState.inputs ? ( timeState.inputs[ '2' ] || {} ) : {};
		var ampmState = timeState && timeState.inputs ? ( timeState.inputs[ '3' ] || {} ) : {};
		var hourLabelHtml = buildTimeFieldLabelHtml( inputBaseId + '_1', getTimeFieldLabelInfo( hourField, hourState, '1' ), 'hour_label', subLabelPlacement );
		var minuteLabelHtml = buildTimeFieldLabelHtml( inputBaseId + '_2', getTimeFieldLabelInfo( minuteField, minuteState, '2' ), 'minute_label', subLabelPlacement );
		var hourInputHtml = '<input' + buildHtmlAttributes(
			{
				type: 'number',
				name: 'input_' + String( fieldId ) + '[]',
				id: inputBaseId + '_1',
				value: hourState.value || '',
				tabindex: hourState.tabindex || '',
				min: '0',
				max: timeFormat === '24' ? '24' : '12',
				step: '1',
				placeholder: getTimeFieldPlaceholder( hourState, '1' ),
				'aria-required': hourState.ariaRequired || '',
				'aria-invalid': hourState.ariaInvalid || 'false',
				'aria-describedby': hourState.ariaDescribedby || ''
			}
		) + ' />';
		var minuteInputHtml = '<input' + buildHtmlAttributes(
			{
				type: 'number',
				name: 'input_' + String( fieldId ) + '[]',
				id: inputBaseId + '_2',
				value: minuteState.value || '',
				tabindex: minuteState.tabindex || '',
				min: '0',
				max: '59',
				step: '1',
				placeholder: getTimeFieldPlaceholder( minuteState, '2' ),
				'aria-required': minuteState.ariaRequired || '',
				'aria-invalid': minuteState.ariaInvalid || 'false',
				'aria-describedby': minuteState.ariaDescribedby || ''
			}
		) + ' />';
		var ampmFieldHtml = '';
		var html = '';

		if ( timeFormat !== '24' ) {
			ampmFieldHtml = '' +
				'<div class="gfield_time_ampm ginput_container ginput_container_time ' + escapeHtml( colonPlacement ) + ' gform-grid-col">' +
					'<select' + buildHtmlAttributes(
						{
							name: 'input_' + String( fieldId ) + '[]',
							id: inputBaseId + '_3',
							tabindex: ampmState.tabindex || ''
						}
					) + '>' + buildTimeAmPmOptionsHtml( ampmState.value || 'am' ) + '</select>' +
					'<label class="gform-field-label gform-field-label--type-sub am_pm_label screen-reader-text" for="' + escapeHtml( inputBaseId + '_3' ) + '">' + escapeHtml( ampmState.label || 'AM/PM' ) + '</label>' +
				'</div>';
		}

		html += '<div class="' + escapeHtml( containerClass ) + '">';
		html += '<div class="gfield_time_hour ginput_container ginput_container_time gform-grid-col" id="' + escapeHtml( inputBaseId ) + '">';
		html += isSubLabelAbove ? hourLabelHtml + hourInputHtml : hourInputHtml + hourLabelHtml;
		html += '</div>';
		html += '<div class="' + escapeHtml( colonPlacement ) + ' hour_minute_colon gform-grid-col">:</div>';
		html += '<div class="gfield_time_minute ginput_container ginput_container_time gform-grid-col">';
		html += isSubLabelAbove ? minuteLabelHtml + minuteInputHtml : minuteInputHtml + minuteLabelHtml;
		html += '</div>';
		html += ampmFieldHtml;
		html += '</div>';

		return html;
	}

	function buildTimeFieldContainerClassName( existingClass ) {
		var className = String( existingClass || 'ginput_container ginput_complex gform-grid-row' );

		if ( className.indexOf( 'ginput_container' ) === -1 ) {
			className += ' ginput_container';
		}

		if ( className.indexOf( 'ginput_complex' ) === -1 ) {
			className += ' ginput_complex';
		}

		if ( className.indexOf( 'gform-grid-row' ) === -1 ) {
			className += ' gform-grid-row';
		}

		return $.trim( className );
	}

	function buildTimeFieldLabelHtml( inputId, labelInfo, labelClassName, subLabelPlacement ) {
		var classNames = [ 'gform-field-label', 'gform-field-label--type-sub', labelClassName ];

		if ( subLabelPlacement === 'hidden_label' ) {
			classNames.push( 'hidden_sub_label', 'screen-reader-text' );
		} else if ( labelInfo.className ) {
			classNames.push( labelInfo.className );
		}

		return '<label class="' + escapeHtml( $.trim( classNames.join( ' ' ) ) ) + '" for="' + escapeHtml( inputId ) + '">' + escapeHtml( labelInfo.text ) + '</label>';
	}

	function buildTimeAmPmOptionsHtml( selectedValue ) {
		var normalizedValue = String( selectedValue || 'am' ).toLowerCase() === 'pm' ? 'pm' : 'am';

		return '' +
			'<option value="am"' + ( normalizedValue === 'am' ? ' selected' : '' ) + '>AM</option>' +
			'<option value="pm"' + ( normalizedValue === 'pm' ? ' selected' : '' ) + '>PM</option>';
	}

	function getTimeFieldLabelInfo( timeField, inputState, suffix ) {
		var customLabel = String( timeField && timeField.customLabel ? timeField.customLabel : '' );

		if ( customLabel ) {
			return {
				text: customLabel,
				className: ''
			};
		}

		if ( getTimeFieldPlaceholder( inputState, suffix ) ) {
			return {
				text: suffix === '1' ? 'HH' : 'MM',
				className: ''
			};
		}

		return {
			text: suffix === '1' ? 'Hours' : 'Minutes',
			className: 'screen-reader-text'
		};
	}

	function getTimeFieldPlaceholder( inputState, suffix ) {
		var placeholder = String( inputState && inputState.placeholder ? inputState.placeholder : '' );

		if ( placeholder ) {
			return placeholder;
		}

		return suffix === '1' ? 'HH' : 'MM';
	}

	function replaceTimeFieldContainer( field, html ) {
		var containers = field.children( '.ginput_container.ginput_complex.gform-grid-row, .ginput_complex.gform-grid-row' );
		var firstContainer = containers.first();
		var replacement = $( html );
		var nextContent;

		if ( firstContainer.length ) {
			firstContainer.before( replacement );
			containers.remove();
			return;
		}

		nextContent = field.children( '.gfield_description, .gfield_validation_message' ).first();

		if ( nextContent.length ) {
			nextContent.before( replacement );
			return;
		}

		field.append( replacement );
	}

	function getTimeFieldBaseInputId( formId, fieldId ) {
		return 'input_' + String( formId ) + '_' + String( fieldId );
	}

	function getTimeFieldInputSuffix( inputId ) {
		var matches = String( inputId || '' ).match( /\.(\d+)$/ );

		return matches ? matches[1] : '';
	}

	function getTimeFieldConfigBySuffix( timeFields, suffix ) {
		var matchedField = null;

		$.each( timeFields || [], function( index, timeField ) {
			if ( getTimeFieldInputSuffix( timeField.id ) === suffix ) {
				matchedField = normalizeTimeSubLabelValue( timeField );
				return false;
			}
		} );

		return matchedField;
	}

	function patchAddressFieldDom( formId, fieldId, value, config, field ) {
		var fieldConfig = getItemConfig( config, 'field', String( fieldId ) ) || {};
		var addressFields = normalizeAddressFieldListValue( value, true );
		var addressState = captureAddressFieldState( field, formId, fieldId, fieldConfig );
		var html = buildAddressFieldHtml( formId, fieldId, fieldConfig, addressState, addressFields, getResolvedNameSubLabelPlacement( config, fieldConfig ) );

		if ( ! html ) {
			return;
		}

		replaceAddressFieldContainer( field, html );
		patchRequiredAttributes( field, field.hasClass( 'gfield_contains_required' ) );
	}

	function captureAddressFieldState( field, formId, fieldId, fieldConfig ) {
		var inputBaseId = getAddressFieldBaseInputId( formId, fieldId );
		var state = $.extend( true, { inputs: {} }, field.data( 'ffeAddressFieldState' ) || {} );
		var container = field.children( '.ginput_complex.ginput_container.ginput_container_address, .ginput_container.ginput_container_address, .ginput_container_address' ).first();
		var suffixes = [ '1', '2', '3', '4', '5', '6' ];

		if ( container.length ) {
			state.containerClass = String( container.attr( 'class' ) || state.containerClass || '' );
		}

		$.each( suffixes, function( index, suffix ) {
			var inputElement = field.find( '#' + inputBaseId + '_' + suffix ).first();
			var labelElement = field.find( 'label[for="' + inputBaseId + '_' + suffix + '"]' ).first();
			var containerElement = field.find( '#' + inputBaseId + '_' + suffix + '_container' ).first();
			var inputState = $.extend( {}, state.inputs[ suffix ] || {} );
			var firstOption;

			if ( containerElement.length ) {
				inputState.containerClass = String( containerElement.attr( 'class' ) || inputState.containerClass || '' );
			}

			if ( inputElement.length ) {
				inputState.tagName = String( inputElement.prop( 'tagName' ) || 'input' ).toLowerCase();
				inputState.type = String( inputElement.attr( 'type' ) || inputState.type || '' );
				inputState.value = String( inputElement.val() || inputState.value || '' );
				inputState.placeholder = String( inputElement.attr( 'placeholder' ) || inputState.placeholder || '' );
				inputState.autocomplete = String( inputElement.attr( 'autocomplete' ) || inputState.autocomplete || getDefaultAddressAutocomplete( suffix ) );
				inputState.ariaDescribedby = String( inputElement.attr( 'aria-describedby' ) || inputState.ariaDescribedby || '' );
				inputState.ariaInvalid = String( inputElement.attr( 'aria-invalid' ) || inputState.ariaInvalid || 'false' );
				inputState.ariaRequired = String( inputElement.attr( 'aria-required' ) || inputState.ariaRequired || '' );

				if ( inputElement.is( 'select' ) ) {
					firstOption = inputElement.find( 'option' ).first();

					if ( firstOption.length && String( firstOption.val() || '' ) === '' ) {
						inputState.emptyOptionText = String( firstOption.text() || inputState.emptyOptionText || '' );
					}
				}
			} else if ( ! inputState.autocomplete ) {
				inputState.autocomplete = getDefaultAddressAutocomplete( suffix );
			}

			if ( labelElement.length ) {
				inputState.label = $.trim( labelElement.text() || inputState.label || '' );
			}

			state.inputs[ suffix ] = inputState;
		} );

		if ( fieldConfig && fieldConfig.addressConfig && fieldConfig.addressConfig.fixedCountry ) {
			state.inputs[ '6' ] = $.extend( {}, state.inputs[ '6' ] || {}, {
				value: String( ( state.inputs[ '6' ] && state.inputs[ '6' ].value ) || fieldConfig.addressConfig.fixedCountry || '' )
			} );
		}

		field.data( 'ffeAddressFieldState', state );

		return state;
	}

	function buildAddressFieldHtml( formId, fieldId, fieldConfig, addressState, addressFields, subLabelPlacement ) {
		var inputBaseId = getAddressFieldBaseInputId( formId, fieldId );
		var addressConfig = $.isPlainObject( fieldConfig && fieldConfig.addressConfig ) ? fieldConfig.addressConfig : {};
		var displayFormat = String( addressConfig.displayFormat || 'default' );
		var subLabelClass = subLabelPlacement === 'hidden_label' ? 'hidden_sub_label screen-reader-text' : '';
		var stateOptions = normalizeAddressOptionList( addressConfig.stateOptions );
		var countryOptions = normalizeAddressOptionList( addressConfig.countryOptions );
		var fixedCountry = String( addressConfig.fixedCountry || '' );
		var orderedSuffixes = displayFormat === 'zip_before_city' ? [ '1', '2', '5', '3', '4', '6' ] : [ '1', '2', '3', '4', '5', '6' ];
		var html = '';

		if ( ! addressFields.length ) {
			return html;
		}

		html += '<div class="' + escapeHtml( buildAddressFieldContainerClassName( addressState && addressState.containerClass, addressFields ) ) + '" id="' + escapeHtml( inputBaseId ) + '">';

		$.each( orderedSuffixes, function( index, suffix ) {
			var addressField = getAddressFieldConfigBySuffix( addressFields, suffix );
			var inputState = addressState && addressState.inputs ? ( addressState.inputs[ suffix ] || {} ) : {};
			var labelText;
			var inputHtml;

			if ( ! addressField ) {
				return;
			}

			labelText = addressField.customLabel || addressField.defaultLabel || inputState.label || '';

			if ( addressField.isHidden ) {
				if ( suffix === '4' || suffix === '6' ) {
					html += buildAddressHiddenInputHtml( fieldId, inputBaseId, suffix, suffix === '6' && fixedCountry ? fixedCountry : inputState.value || '' );
				}

				return;
			}

			if ( suffix === '4' && stateOptions.length ) {
				inputHtml = buildAddressSelectInputHtml( fieldId, inputBaseId, suffix, inputState, stateOptions );
			} else if ( suffix === '6' ) {
				inputHtml = buildAddressSelectInputHtml( fieldId, inputBaseId, suffix, inputState, countryOptions );
			} else {
				inputHtml = buildAddressTextInputHtml( fieldId, inputBaseId, suffix, inputState );
			}

			html += buildAddressInputContainerHtml(
				inputBaseId,
				suffix,
				inputState.containerClass || getDefaultAddressContainerClass( suffix, displayFormat, isAddressFieldVisible( addressFields, '4' ) ),
				inputHtml,
				labelText,
				subLabelClass,
				subLabelPlacement
			);
		} );

		html += '<div class="gf_clear gf_clear_complex"></div>';
		html += '</div>';

		return html;
	}

	function buildAddressTextInputHtml( fieldId, inputBaseId, suffix, inputState ) {
		var attributes = buildHtmlAttributes(
			{
				name: 'input_' + String( fieldId ) + '.' + suffix,
				id: inputBaseId + '_' + suffix,
				type: 'text',
				value: inputState.value || '',
				placeholder: inputState.placeholder || '',
				autocomplete: inputState.autocomplete || getDefaultAddressAutocomplete( suffix ),
				'aria-required': inputState.ariaRequired,
				'aria-invalid': inputState.ariaInvalid || 'false',
				'aria-describedby': inputState.ariaDescribedby
			}
		);

		return '<input' + attributes + ' />';
	}

	function buildAddressSelectInputHtml( fieldId, inputBaseId, suffix, inputState, options ) {
		var attributes = buildHtmlAttributes(
			{
				name: 'input_' + String( fieldId ) + '.' + suffix,
				id: inputBaseId + '_' + suffix,
				autocomplete: inputState.autocomplete || getDefaultAddressAutocomplete( suffix ),
				'aria-required': inputState.ariaRequired,
				'aria-invalid': inputState.ariaInvalid || 'false',
				'aria-describedby': inputState.ariaDescribedby
			}
		);

		return '<select' + attributes + '>' + buildAddressOptionsHtml( options, inputState.value || '', inputState.emptyOptionText || '' ) + '</select>';
	}

	function buildAddressOptionsHtml( options, selectedValue, emptyOptionText ) {
		var html = '';
		var normalizedOptions = normalizeAddressOptionList( options );
		var hasBlankOption = normalizedOptions.length && normalizedOptions[0].value === '';

		if ( ! hasBlankOption ) {
			html += '<option value="">' + escapeHtml( emptyOptionText || '' ) + '</option>';
		}

		$.each( normalizedOptions, function( index, option ) {
			var optionText = option.value === '' && emptyOptionText && ! option.text ? emptyOptionText : option.text;
			var selected = String( selectedValue || '' ) === option.value ? ' selected="selected"' : '';

			html += '<option value="' + escapeHtml( option.value ) + '"' + selected + '>' + escapeHtml( optionText ) + '</option>';
		} );

		return html;
	}

	function buildAddressInputContainerHtml( inputBaseId, suffix, containerClass, inputHtml, labelText, subLabelClass, subLabelPlacement ) {
		var labelHtml = '<label for="' + escapeHtml( inputBaseId + '_' + suffix ) + '" class="gform-field-label gform-field-label--type-sub ' + escapeHtml( subLabelClass ) + '">' + escapeHtml( labelText ) + '</label>';
		var content = subLabelPlacement === 'above' ? labelHtml + inputHtml : inputHtml + labelHtml;

		return '<span id="' + escapeHtml( inputBaseId + '_' + suffix ) + '_container" class="' + escapeHtml( containerClass ) + '">' + content + '</span>';
	}

	function buildAddressHiddenInputHtml( fieldId, inputBaseId, suffix, value ) {
		var attributes = buildHtmlAttributes(
			{
				name: 'input_' + String( fieldId ) + '.' + suffix,
				id: inputBaseId + '_' + suffix,
				type: 'hidden',
				class: 'gform_hidden',
				value: value || ''
			}
		);

		return '<input' + attributes + ' />';
	}

	function buildAddressFieldContainerClassName( existingClassName, addressFields ) {
		var strippedClassName = String( existingClassName || 'ginput_complex ginput_container ginput_container_address gform-grid-row' )
			.replace( /\bhas_street2?\b|\bhas_city\b|\bhas_state\b|\bhas_zip\b|\bhas_country\b/g, ' ' )
			.replace( /\s+/g, ' ' );
		var classes = $.grep( strippedClassName.split( ' ' ), function( className ) {
			return !! className;
		} );
		var classMap = {
			'1': 'has_street',
			'2': 'has_street2',
			'3': 'has_city',
			'4': 'has_state',
			'5': 'has_zip',
			'6': 'has_country'
		};

		$.each( [ '1', '2', '3', '4', '5', '6' ], function( index, suffix ) {
			if ( isAddressFieldVisible( addressFields, suffix ) ) {
				pushUniqueClass( classes, classMap[ suffix ] );
			}
		} );

		pushUniqueClass( classes, 'ginput_complex' );
		pushUniqueClass( classes, 'ginput_container' );
		pushUniqueClass( classes, 'ginput_container_address' );
		pushUniqueClass( classes, 'gform-grid-row' );

		return classes.join( ' ' );
	}

	function pushUniqueClass( classes, className ) {
		if ( $.inArray( className, classes ) === -1 ) {
			classes.push( className );
		}
	}

	function getAddressFieldConfigBySuffix( addressFields, suffix ) {
		var matchedField = null;

		$.each( addressFields || [], function( index, addressField ) {
			if ( getNameFieldInputSuffix( addressField.id ) === suffix ) {
				matchedField = normalizeAddressFieldValue( addressField );
				return false;
			}
		} );

		return matchedField;
	}

	function isAddressFieldVisible( addressFields, suffix ) {
		var fieldConfig = getAddressFieldConfigBySuffix( addressFields, suffix );

		return !! fieldConfig && ! fieldConfig.isHidden;
	}

	function normalizeAddressOptionList( options ) {
		var normalizedOptions = [];

		$.each( options || [], function( index, option ) {
			if ( $.isPlainObject( option ) ) {
				normalizedOptions.push(
					{
						value: option.value == null ? '' : String( option.value ),
						text: option.text == null ? String( option.value == null ? '' : option.value ) : String( option.text )
					}
				);
				return;
			}

			normalizedOptions.push(
				{
					value: option == null ? '' : String( option ),
					text: option == null ? '' : String( option )
				}
			);
		} );

		return normalizedOptions;
	}

	function getDefaultAddressContainerClass( suffix, displayFormat, hasState ) {
		var cityLocation = displayFormat === 'zip_before_city' ? 'right' : 'left';
		var zipLocation = displayFormat !== 'zip_before_city' && ! hasState ? 'right' : 'left';
		var stateLocation = displayFormat === 'zip_before_city' ? 'left' : 'right';
		var countryLocation = hasState ? 'right' : 'left';

		switch ( String( suffix || '' ) ) {
			case '1':
				return 'ginput_full address_line_1 ginput_address_line_1 gform-grid-col';

			case '2':
				return 'ginput_full address_line_2 ginput_address_line_2 gform-grid-col';

			case '3':
				return 'ginput_' + cityLocation + ' address_city ginput_address_city gform-grid-col';

			case '4':
				return 'ginput_' + stateLocation + ' address_state ginput_address_state gform-grid-col';

			case '5':
				return 'ginput_' + zipLocation + ' address_zip ginput_address_zip gform-grid-col';

			case '6':
				return 'ginput_' + countryLocation + ' address_country ginput_address_country gform-grid-col';

			default:
				return 'gform-grid-col';
		}
	}

	function replaceAddressFieldContainer( field, html ) {
		var containers = field.children( '.ginput_complex.ginput_container.ginput_container_address, .ginput_container.ginput_container_address, .ginput_container_address' );
		var firstContainer = containers.first();
		var replacement = $( html );
		var nextContent;

		if ( firstContainer.length ) {
			firstContainer.before( replacement );
			containers.remove();
			return;
		}

		nextContent = field.children( '.gfield_description, .gfield_validation_message' ).first();

		if ( nextContent.length ) {
			nextContent.before( replacement );
			return;
		}

		field.append( replacement );
	}

	function getAddressFieldBaseInputId( formId, fieldId ) {
		return 'input_' + String( formId ) + '_' + String( fieldId );
	}

	function getDefaultAddressAutocomplete( suffix ) {
		switch ( String( suffix || '' ) ) {
			case '1':
				return 'address-line1';

			case '2':
				return 'address-line2';

			case '3':
				return 'address-level2';

			case '4':
				return 'address-level1';

			case '5':
				return 'postal-code';

			case '6':
				return 'country-name';

			default:
				return '';
		}
	}

	function buildRenderedChoiceItemsHtml( formId, fieldId, choices, renderType, tagName, isRequired, selectAllHtml ) {
		var html = '';
		var inputType = 'checkbox' === renderType ? 'checkbox' : 'radio';
		var ariaRequired = isRequired ? ' aria-required="true"' : '';

		if ( selectAllHtml ) {
			html += selectAllHtml;
		}

		$.each( choices, function( index, choice ) {
			var normalizedChoice = normalizeChoiceValue( choice, index );
			var renderedChoiceId = getRenderedChoiceId( formId, fieldId, index );
			var checked = normalizedChoice.isSelected ? ' checked="checked"' : '';
			var inputName = getRenderedChoiceName( fieldId, normalizedChoice, renderType, index );

			html += '<' + tagName + ' class="gchoice gchoice_' + escapeHtml( renderedChoiceId ) + '">';
			html += '<input class="gfield-choice-input" name="' + escapeHtml( inputName ) + '" type="' + inputType + '" value="' + escapeHtml( normalizedChoice.value ) + '" id="choice_' + escapeHtml( renderedChoiceId ) + '"' + checked + ariaRequired + ' />';
			html += '<label for="choice_' + escapeHtml( renderedChoiceId ) + '" id="label_' + escapeHtml( renderedChoiceId ) + '" class="gform-field-label gform-field-label--type-inline">' + escapeHtml( normalizedChoice.text ) + '</label>';
			html += '</' + tagName + '>';
		} );

		return html;
	}

	function getRenderedChoiceId( formId, fieldId, index ) {
		return String( formId ) + '_' + String( fieldId ) + '_' + String( index + 1 );
	}

	function getRenderedChoiceName( fieldId, choice, renderType, index ) {
		if ( 'checkbox' === renderType ) {
			return 'input_' + ( choice.inputId || ( String( fieldId ) + '.' + String( index + 1 ) ) );
		}

		return 'input_' + String( fieldId );
	}

	function patchEmailConfirmationFieldDom( formId, fieldId, value, config, field ) {
		var fieldConfig = getItemConfig( config, 'field', String( fieldId ) ) || {};
		var isEnabled = normalizeSettingValue( 'email_confirmation', value ) === '1';
		var emailState = captureEmailFieldState( field, fieldConfig );
		var label = field.children( 'label.gfield_label' ).first();
		var html;
		var isFieldsetWrapper = field.is( 'fieldset' );

		if ( label.length && typeof label.data( 'ffeOriginalFor' ) === 'undefined' ) {
			label.data( 'ffeOriginalFor', String( label.attr( 'for' ) || '' ) );
		}

		if ( isEnabled ) {
			html = buildEmailConfirmFieldHtml( formId, fieldId, fieldConfig, emailState, getResolvedEmailSubLabelPlacement( config, fieldConfig ) );
			replaceEmailFieldContainer( field, html );
			field.removeAttr( 'data-ffe-email-confirm-live-disabled' );

			if ( label.length ) {
				label.removeAttr( 'for' );
			}
		} else {
			html = buildEmailSingleFieldHtml( formId, fieldId, fieldConfig, emailState );
			replaceEmailFieldContainer( field, html );

			if ( isFieldsetWrapper ) {
				field.attr( 'data-ffe-email-confirm-live-disabled', '1' );
			} else {
				field.removeAttr( 'data-ffe-email-confirm-live-disabled' );
			}

			if ( label.length ) {
				if ( label.data( 'ffeOriginalFor' ) ) {
					label.attr( 'for', label.data( 'ffeOriginalFor' ) );
				} else {
					label.attr( 'for', getEmailInputId( formId, fieldId ) );
				}
			}
		}

		patchRequiredAttributes( field, field.hasClass( 'gfield_contains_required' ) );
	}

	function captureEmailFieldState( field, fieldConfig ) {
		var emailState = $.extend( {}, field.data( 'ffeEmailFieldState' ) || {} );
		var singleInput = field.children( '.ginput_single_email, .ginput_container.ginput_container_email:not(.ginput_complex)' ).first().find( 'input' ).first();
		var confirmContainer = field.children( '.ginput_confirm_email, .ginput_complex.ginput_container.ginput_container_email' ).first();
		var firstInput = confirmContainer.find( 'input' ).eq( 0 );
		var secondInput = confirmContainer.find( 'input' ).eq( 1 );
		var firstLabel = confirmContainer.find( 'label' ).eq( 0 );
		var secondLabel = confirmContainer.find( 'label' ).eq( 1 );

		emailState.singleClass = String( singleInput.attr( 'class' ) || emailState.singleClass || getDefaultEmailSingleClass( fieldConfig ) );
		emailState.confirmClass = String( firstInput.attr( 'class' ) || emailState.confirmClass || stripFieldSizeClasses( emailState.singleClass ) );
		emailState.singleValue = String( singleInput.val() || emailState.singleValue || '' );
		emailState.firstValue = String( firstInput.val() || emailState.firstValue || emailState.singleValue || '' );
		emailState.secondValue = String( secondInput.val() || emailState.secondValue || '' );
		emailState.singlePlaceholder = String( singleInput.attr( 'placeholder' ) || emailState.singlePlaceholder || '' );
		emailState.firstPlaceholder = String( firstInput.attr( 'placeholder' ) || emailState.firstPlaceholder || emailState.singlePlaceholder || '' );
		emailState.secondPlaceholder = String( secondInput.attr( 'placeholder' ) || emailState.secondPlaceholder || '' );
		emailState.singleAutocomplete = String( singleInput.attr( 'autocomplete' ) || emailState.singleAutocomplete || '' );
		emailState.firstAutocomplete = String( firstInput.attr( 'autocomplete' ) || emailState.firstAutocomplete || emailState.singleAutocomplete || '' );
		emailState.secondAutocomplete = String( secondInput.attr( 'autocomplete' ) || emailState.secondAutocomplete || '' );
		emailState.enterLabel = $.trim( firstLabel.text() || emailState.enterLabel || getString( 'enterEmailLabel', 'Enter Email' ) );
		emailState.confirmLabel = $.trim( secondLabel.text() || emailState.confirmLabel || getString( 'confirmEmailLabel', 'Confirm Email' ) );
		emailState.ariaDescribedby = String( firstInput.attr( 'aria-describedby' ) || singleInput.attr( 'aria-describedby' ) || emailState.ariaDescribedby || '' );
		emailState.ariaInvalid = String( firstInput.attr( 'aria-invalid' ) || singleInput.attr( 'aria-invalid' ) || emailState.ariaInvalid || 'false' );
		emailState.ariaRequired = String( firstInput.attr( 'aria-required' ) || singleInput.attr( 'aria-required' ) || emailState.ariaRequired || '' );

		field.data( 'ffeEmailFieldState', emailState );

		return emailState;
	}

	function getResolvedEmailSubLabelPlacement( config, fieldConfig ) {
		var fieldSubLabelPlacement = String( fieldConfig && fieldConfig.subLabelPlacement ? fieldConfig.subLabelPlacement : '' );
		var formSubLabelPlacement = String( config && config.formSubLabelPlacement ? config.formSubLabelPlacement : 'below' );

		if ( fieldSubLabelPlacement === 'hidden_label' ) {
			return 'hidden_label';
		}

		if ( fieldSubLabelPlacement === 'above' ) {
			return 'above';
		}

		return formSubLabelPlacement === 'above' ? 'above' : 'below';
	}

	function getResolvedNameSubLabelPlacement( config, fieldConfig ) {
		var fieldSubLabelPlacement = String( fieldConfig && fieldConfig.subLabelPlacement ? fieldConfig.subLabelPlacement : '' );
		var formSubLabelPlacement = String( config && config.formSubLabelPlacement ? config.formSubLabelPlacement : 'below' );

		if ( fieldSubLabelPlacement === 'hidden_label' ) {
			return 'hidden_label';
		}

		if ( fieldSubLabelPlacement === 'above' ) {
			return 'above';
		}

		return formSubLabelPlacement === 'above' ? 'above' : 'below';
	}

	function buildEmailSingleFieldHtml( formId, fieldId, fieldConfig, emailState ) {
		var attributes = buildHtmlAttributes(
			{
				name: 'input_' + String( fieldId ),
				id: getEmailInputId( formId, fieldId ),
				type: 'email',
				value: emailState.firstValue || emailState.singleValue || '',
				class: emailState.singleClass || getDefaultEmailSingleClass( fieldConfig ),
				placeholder: emailState.firstPlaceholder || emailState.singlePlaceholder || '',
				'aria-required': emailState.ariaRequired,
				'aria-invalid': emailState.ariaInvalid || 'false',
				'aria-describedby': emailState.ariaDescribedby,
				autocomplete: emailState.firstAutocomplete || emailState.singleAutocomplete || ''
			}
		);

		return '<div class="ginput_container ginput_container_email ginput_single_email"><input' + attributes + ' /></div>';
	}

	function buildEmailConfirmFieldHtml( formId, fieldId, fieldConfig, emailState, subLabelPlacement ) {
		var inputId = getEmailInputId( formId, fieldId );
		var confirmInputId = inputId + '_2';
		var inputClass = emailState.confirmClass || stripFieldSizeClasses( emailState.singleClass || getDefaultEmailSingleClass( fieldConfig ) );
		var subLabelClass = subLabelPlacement === 'hidden_label' ? 'hidden_sub_label screen-reader-text' : '';
		var baseAttributes = {
			type: 'email',
			class: inputClass,
			'aria-required': emailState.ariaRequired,
			'aria-invalid': emailState.ariaInvalid || 'false',
			'aria-describedby': emailState.ariaDescribedby
		};
		var enterInputAttributes = buildHtmlAttributes(
			$.extend( {}, baseAttributes, {
				name: 'input_' + String( fieldId ),
				id: inputId,
				value: emailState.firstValue || emailState.singleValue || '',
				placeholder: emailState.firstPlaceholder || emailState.singlePlaceholder || '',
				autocomplete: emailState.firstAutocomplete || emailState.singleAutocomplete || ''
			} )
		);
		var confirmInputAttributes = buildHtmlAttributes(
			$.extend( {}, baseAttributes, {
				name: 'input_' + String( fieldId ) + '_2',
				id: confirmInputId,
				value: emailState.secondValue || '',
				placeholder: emailState.secondPlaceholder || '',
				autocomplete: emailState.secondAutocomplete || ''
			} )
		);
		var enterLabel = '<label for="' + escapeHtml( inputId ) + '" class="gform-field-label gform-field-label--type-sub ' + escapeHtml( subLabelClass ) + '">' + escapeHtml( emailState.enterLabel || getString( 'enterEmailLabel', 'Enter Email' ) ) + '</label>';
		var confirmLabel = '<label for="' + escapeHtml( confirmInputId ) + '" class="gform-field-label gform-field-label--type-sub ' + escapeHtml( subLabelClass ) + '">' + escapeHtml( emailState.confirmLabel || getString( 'confirmEmailLabel', 'Confirm Email' ) ) + '</label>';
		var enterContent = subLabelPlacement === 'above' ? enterLabel + '<input' + enterInputAttributes + ' />' : '<input' + enterInputAttributes + ' />' + enterLabel;
		var confirmContent = subLabelPlacement === 'above' ? confirmLabel + '<input' + confirmInputAttributes + ' />' : '<input' + confirmInputAttributes + ' />' + confirmLabel;

		return '' +
			'<div class="ginput_complex ginput_container ginput_container_email gform-grid-row ginput_confirm_email" id="' + escapeHtml( inputId ) + '_container">' +
				'<span id="' + escapeHtml( inputId ) + '_1_container" class="ginput_left gform-grid-col gform-grid-col--size-auto">' + enterContent + '</span>' +
				'<span id="' + escapeHtml( inputId ) + '_2_container" class="ginput_right gform-grid-col gform-grid-col--size-auto">' + confirmContent + '</span>' +
				'<div class="gf_clear gf_clear_complex"></div>' +
			'</div>';
	}

	function replaceEmailFieldContainer( field, html ) {
		var containers = field.children( '.ginput_single_email, .ginput_confirm_email, .ginput_container.ginput_container_email, .ginput_complex.ginput_container.ginput_container_email' );
		var firstContainer = containers.first();
		var replacement = $( html );
		var nextContent;

		if ( firstContainer.length ) {
			firstContainer.before( replacement );
			containers.remove();
			return;
		}

		nextContent = field.children( '.gfield_description, .gfield_validation_message' ).first();

		if ( nextContent.length ) {
			nextContent.before( replacement );
			return;
		}

		field.append( replacement );
	}

	function getEmailInputId( formId, fieldId ) {
		return 'input_' + String( formId ) + '_' + String( fieldId );
	}

	function getDefaultEmailSingleClass( fieldConfig ) {
		var size = String( fieldConfig && fieldConfig.size ? fieldConfig.size : 'large' );

		return size || 'large';
	}

	function stripFieldSizeClasses( className ) {
		return $.trim( String( className || '' ).replace( /\b(?:small|medium|large)\b/g, ' ' ).replace( /\s+/g, ' ' ) );
	}

	function buildHtmlAttributes( attributes ) {
		var html = '';

		$.each( attributes || {}, function( attributeName, attributeValue ) {
			if ( attributeValue == null || attributeValue === '' ) {
				return;
			}

			html += ' ' + attributeName + '="' + escapeHtml( attributeValue ) + '"';
		} );

		return html;
	}

	function patchConsentFieldDom( formId, fieldId, config, field ) {
		var fieldConfig = getItemConfig( config, 'field', String( fieldId ) ) || {};
		var values = $.isPlainObject( fieldConfig.values ) ? fieldConfig.values : {};
		var checkboxLabel = values.consent_checkbox_label == null ? '' : String( values.consent_checkbox_label );
		var descriptionValue = values.description == null ? '' : String( values.description );
		var isRequired = normalizeSettingValue( 'required', values.required ) === '1';
		var consentContainer = field.find( '.ginput_container_consent' ).first();
		var checkbox = consentContainer.find( 'input[type="checkbox"]' ).first();
		var revisionInput = consentContainer.find( 'input[name="input_' + String( fieldId ) + '.3"]' ).first();
		var checkboxId = String( checkbox.attr( 'id' ) || ( 'input_' + String( formId ) + '_' + String( fieldId ) ) );
		var checkboxName = String( checkbox.attr( 'name' ) || ( 'input_' + String( fieldId ) + '.1' ) );
		var checkboxValue = String( checkbox.val() || '1' );
		var checkboxChecked = checkbox.prop( 'checked' );
		var checkboxTabindex = checkbox.attr( 'tabindex' );
		var checkboxAriaInvalid = checkbox.attr( 'aria-invalid' ) || 'false';
		var checkboxAriaRequired = isRequired ? 'true' : '';
		var checkboxAriaDescribedby = getConsentAriaDescribedby( formId, fieldId, checkbox.attr( 'aria-describedby' ), descriptionValue );
		var labelHtml = checkboxLabel;
		var inputHtml;

		if ( shouldUseConsentInlineRequiredIndicator( field, isRequired ) ) {
			labelHtml += getRequiredIndicatorHtml( config );
		}

		inputHtml = '<input' + buildHtmlAttributes(
			{
				name: checkboxName,
				id: checkboxId,
				type: 'checkbox',
				value: checkboxValue,
				tabindex: checkboxTabindex,
				checked: checkboxChecked ? 'checked' : null,
				'aria-describedby': checkboxAriaDescribedby,
				'aria-required': checkboxAriaRequired,
				'aria-invalid': checkboxAriaInvalid
			}
		) + ' /> ';
		inputHtml += '<label' + buildHtmlAttributes(
			{
				'class': 'gform-field-label gform-field-label--type-inline gfield_consent_label',
				for: checkboxId
			}
		) + '>' + labelHtml + '</label>';
		inputHtml += '<input' + buildHtmlAttributes(
			{
				type: 'hidden',
				name: 'input_' + String( fieldId ) + '.2',
				value: checkboxLabel,
				'class': 'gform_hidden'
			}
		) + ' />';
		inputHtml += '<input' + buildHtmlAttributes(
			{
				type: 'hidden',
				name: 'input_' + String( fieldId ) + '.3',
				value: String( revisionInput.val() || '' ),
				'class': 'gform_hidden'
			}
		) + ' />';

		if ( consentContainer.length ) {
			consentContainer.html( inputHtml );
		}

		patchConsentDescriptionDom( formId, fieldId, descriptionValue, field );
	}

	function patchConsentDescriptionDom( formId, fieldId, descriptionValue, field ) {
		var description = field.find( '.gfield_consent_description, .gfield_description' ).first();
		var descriptionId = 'gfield_consent_description_' + String( formId ) + '_' + String( fieldId );
		var html = formatConsentDescriptionHtml( descriptionValue );

		if ( description.length ) {
			description.attr( 'id', descriptionId );
			description.attr( 'tabindex', '0' );
			description.addClass( 'gfield_consent_description' );
			description.html( html );
			return;
		}

		if ( ! descriptionValue ) {
			return;
		}

		field.append( '<div class="gfield_description gfield_consent_description" id="' + escapeHtml( descriptionId ) + '" tabindex="0">' + html + '</div>' );
	}

	function formatConsentDescriptionHtml( descriptionValue ) {
		return String( descriptionValue || '' ).replace( /\r\n|\r|\n/g, '<br />' );
	}

	function getConsentAriaDescribedby( formId, fieldId, currentValue, descriptionValue ) {
		var descriptionId = 'gfield_consent_description_' + String( formId ) + '_' + String( fieldId );
		var describedbyIds = $.grep( String( currentValue || '' ).split( /\s+/ ), function( id ) {
			return !! id && id !== descriptionId;
		} );

		if ( descriptionValue ) {
			describedbyIds.push( descriptionId );
		}

		return describedbyIds.join( ' ' );
	}

	function shouldUseConsentInlineRequiredIndicator( field, isRequired ) {
		var fieldLabel = field.find( '> .gfield_label, > legend.gfield_label' ).first();

		return isRequired && ( ! fieldLabel.length || fieldLabel.hasClass( 'hidden_label' ) || fieldLabel.hasClass( 'screen-reader-text' ) || fieldLabel.hasClass( 'gform-screen-reader-text' ) || field.hasClass( 'hidden_label' ) );
	}

	function patchFieldDom( formId, fieldId, settingKey, value, oldValue, config ) {
		var field = getFieldWrapper( formId, fieldId );
		var fieldConfig = getItemConfig( config, 'field', String( fieldId ) ) || {};
		var isRequired;
		var label;
		var requiredHtml;
		var description;
		var input;

		if ( ! field.length ) {
			return;
		}

		switch ( settingKey ) {
			case 'label':
				patchFieldLabelText( field, value );
				break;

			case 'required':
				isRequired = normalizeSettingValue( settingKey, value ) === '1';
				patchRequiredIndicator( field, isRequired, config );
				patchRequiredAttributes( field, isRequired );

				if ( isConsentFieldConfig( fieldConfig ) ) {
					patchConsentFieldDom( formId, fieldId, config, field );
				}
				break;

			case 'email_confirmation':
				patchEmailConfirmationFieldDom( formId, fieldId, value, config, field );
				break;

			case 'name_fields':
				patchNameFieldDom( formId, fieldId, value, config, field );
				break;

			case 'address_fields':
				patchAddressFieldDom( formId, fieldId, value, config, field );
				break;

				case 'time_format':
				case 'time_sub_labels':
					patchTimeFieldDom( formId, fieldId, value, config, field );
					break;

			case 'choices':
				patchChoicesFieldDom( formId, fieldId, value, config, field );
				break;

			case 'description':
				if ( isConsentFieldConfig( fieldConfig ) ) {
					patchConsentFieldDom( formId, fieldId, config, field );
					break;
				}

				description = field.find( '.gfield_description' ).first();
				if ( description.length ) {
					description.html( value );
				} else if ( value ) {
					field.append( '<div class="gfield_description">' + value + '</div>' );
				}
				break;

			case 'consent_checkbox_label':
				patchConsentFieldDom( formId, fieldId, config, field );
				break;

			case 'placeholder':
				input = field.find( 'input:not([type="hidden"]), textarea' ).first();
				if ( input.length ) {
					input.attr( 'placeholder', value );
				}
				break;

			case 'default_value':
				input = field.find( 'input:not([type="hidden"]):not([type="radio"]):not([type="checkbox"]), textarea' ).first();
				if ( input.length ) {
					input.attr( 'value', value );
					if ( ! input.val() || String( input.val() ) === String( oldValue || '' ) ) {
						input.val( value );
					}
				}
				break;

			case 'admin_label':
				break;
		}
	}

	function patchItemDom( formId, itemType, itemKey, settingKey, value, oldValue, config ) {
		if ( 'form' === itemType ) {
			patchFormItemDom( formId, itemKey, settingKey, value );
			return;
		}

		patchFieldDom( formId, itemKey, settingKey, value, oldValue, config );
	}

	function bootForm( formId ) {
		var config = getConfig( formId );
		var state = getState( formId );

		if ( ! config ) {
			return;
		}

		state.config = config;
		ensurePanel( formId, state );
		refreshFormButtons( formId, state );
		refreshFieldButtons( formId, state );
	}

	function initializeAllForms() {
		$.each( window.ffeData || {}, function( formId ) {
			bootForm( parseInt( formId, 10 ) );
		} );
	}

	if ( window.gform && typeof window.gform.addAction === 'function' ) {
		window.gform.addAction( 'gform_post_render', function( formId ) {
			bootForm( parseInt( formId, 10 ) );
		} );

		window.gform.addAction( 'gform_page_loaded', function( formId ) {
			bootForm( parseInt( formId, 10 ) );
		} );

		window.gform.addAction( 'gform_confirmation_loaded', function( formId ) {
			bootForm( parseInt( formId, 10 ) );
		} );
	}

	$( document ).on( 'gform_post_render', function( event, formId ) {
		bootForm( parseInt( formId, 10 ) );
	} );

	$( document ).on( 'elementor/popup/show', function() {
		initializeAllForms();
	} );

	$( function() {
		initializeAllForms();
	} );
} )( window, jQuery, document );