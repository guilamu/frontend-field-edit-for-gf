
( function( window, $, document ) {
	'use strict';

	var controller = {
		booted: false,
		config: null,
		currentField: null,

		boot: function( config ) {
			this.config = config || window.ffeFieldSettingConfig || {};
			this.registerFieldSettingClass();
			this.bindLoadEvent();

			if ( this.booted ) {
				return;
			}

			this.booted = true;
		},

		registerFieldSettingClass: function() {
			var supportedTypes;
			var selectors;
			var index;

			if ( typeof window.fieldSettings === 'undefined' ) {
				return;
			}

			supportedTypes = this.config.supportedTypes || [];
			selectors = ', .ffe_mode_setting, .ffe_editable_settings_setting';
			for ( index = 0; index < supportedTypes.length; index += 1 ) {
				if ( typeof window.fieldSettings[ supportedTypes[ index ] ] !== 'string' ) {
					continue;
				}

				if ( window.fieldSettings[ supportedTypes[ index ] ].indexOf( '.ffe_mode_setting' ) === -1 ) {
					window.fieldSettings[ supportedTypes[ index ] ] += selectors;
				}
			}
		},

		bindLoadEvent: function() {
			var self = this;

			$( document )
				.off( 'gform_load_field_settings.ffe' )
				.on( 'gform_load_field_settings.ffe', function( event, field ) {
					self.loadField( field );
				} );
		},

		bindFieldEvents: function() {
			var self = this;

			$( '#ffe_mode' )
				.off( 'change.ffe' )
				.on( 'change.ffe', function() {
					var mode = $( this ).val() || 'inherit';

					if ( typeof window.SetFieldProperty === 'function' ) {
						window.SetFieldProperty( 'ffe_mode', mode );
					}

					if ( self.currentField ) {
						self.currentField.ffe_mode = mode;
					}
				} );

			$( '.ffe-setting-option input' )
				.off( 'change.ffe' )
				.on( 'change.ffe', function() {
					self.persistFieldSettings();
				} );
		},

		loadField: function( field ) {
			var availableSettings;
			var fieldType;
			var mode;
			var settingState;

			this.currentField = field || null;

			if ( ! this.currentField ) {
				return;
			}

			fieldType = this.getFieldType( this.currentField );
			availableSettings = this.getAvailableSettingsForFieldType( fieldType );

			mode = this.normalizeMode( this.currentField.ffe_mode || 'inherit' );
			settingState = this.getFieldSettingState( this.currentField, fieldType );

			$( '#ffe_mode' ).val( mode );

			$( '.ffe-setting-option' ).each( function() {
				var option = $( this );
				var settingKey = option.data( 'settingKey' );
				var isVisible = availableSettings.indexOf( settingKey ) !== -1;

				option.toggle( isVisible );
				option.find( 'input' ).prop( 'checked', !! settingState[ settingKey ] );
			} );

			$( '.ffe_editable_settings_setting' ).toggle( availableSettings.length > 0 );
			this.updateHelpText( availableSettings.length > 0 );
			this.bindFieldEvents();
		},

		persistFieldSettings: function() {
			var mergedState = this.getFieldSettingState( this.currentField || {}, this.getFieldType( this.currentField || {} ) );

			$( '.ffe-setting-option:visible' ).each( function() {
				var option = $( this );
				var settingKey = option.data( 'settingKey' );
				mergedState[ settingKey ] = option.find( 'input' ).is( ':checked' );
			} );

			if ( typeof window.SetFieldProperty === 'function' ) {
				window.SetFieldProperty( 'ffe_settings', mergedState );
			}

			if ( this.currentField ) {
				this.currentField.ffe_settings = mergedState;
			}
		},

		updateHelpText: function( hasSettings ) {
			$( '#ffe_field_setting_help' ).text( hasSettings ? this.getString( 'settingsHelp' ) : this.getString( 'noSettingsHelp' ) );
		},

		getFieldType: function( field ) {
			var fieldType = field && field.type ? String( field.type ) : '';
			var inputType = '';

			if ( field && typeof window.GetInputType === 'function' ) {
				inputType = String( window.GetInputType( field ) || '' );
			} else if ( field && field.inputType ) {
				inputType = String( field.inputType );
			}

			if ( inputType && ( inputType === 'consent' || ! fieldType || ! this.getAvailableSettingsForFieldType( fieldType ).length ) ) {
				return inputType;
			}

			return fieldType || inputType;
		},

		getFieldSettingState: function( field, fieldType ) {
			var defaults = ( this.config.defaultFieldSettings || {} )[ fieldType ] || {};
			var saved = field && typeof field.ffe_settings === 'object' && field.ffe_settings ? field.ffe_settings : {};
			var state = {};
			var key;

			for ( key in defaults ) {
				if ( Object.prototype.hasOwnProperty.call( defaults, key ) ) {
					state[ key ] = !! defaults[ key ];
				}
			}

			for ( key in saved ) {
				if ( Object.prototype.hasOwnProperty.call( saved, key ) ) {
					state[ key ] = !! saved[ key ];
				}
			}

			return state;
		},

		getAvailableSettingsForFieldType: function( fieldType ) {
			var matrix = this.config.settingMatrix || {};
			return matrix[ fieldType ] || [];
		},

		normalizeMode: function( mode ) {
			if ( mode === 'enabled' || mode === 'disabled' || mode === 'inherit' ) {
				return mode;
			}

			return 'inherit';
		},

		getString: function( key ) {
			return this.config && this.config.strings && this.config.strings[ key ] ? this.config.strings[ key ] : '';
		}
	};

	window.FFEAdminFieldSettings = controller;

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', function() {
			controller.boot( window.ffeFieldSettingConfig || {} );
		} );
	} else {
		controller.boot( window.ffeFieldSettingConfig || {} );
	}
} )( window, jQuery, document );