# Frontend Field Edit for Gravity Forms

Frontend Field Edit for Gravity Forms lets trusted users edit safe Gravity Forms field settings from the frontend without opening the form editor.

## Control Frontend Editing

- Choose which forms expose inline editing by enabling the feature per form.
- Limit access to logged-in users who have the configured WordPress capability.
- Restrict each field type to the safe setting list before any save request is accepted.

## Edit Gravity Forms Fields In Place

- Update labels, descriptions, placeholders, default values, and required state without opening the Gravity Forms editor.
- Refresh complex fields such as Address, Consent, Email, Name, Time, and choice-based fields with Gravity Forms-rendered markup after each save.
- Keep multi-page forms on the current page while previewing frontend changes.

## Track Reliable Changes

- Record successful edits in a dedicated audit log when logging is enabled.
- Detect stale form hashes and reject conflicting saves before they overwrite newer form changes.
- Surface GitHub-powered plugin details, update metadata, and bug reporting links inside WordPress admin.

## Key Features

- **Frontend Editing:** Let trusted users update safe Gravity Forms field settings directly on the rendered form.
- **Complex Field Support:** Handle Address, Consent, Email, Name, Time, Phone, Date, Website, Number, and choice-based fields.
- **Multilingual:** Works with forms in any language supported by Gravity Forms.
- **Translation-Ready:** All user-facing strings use the `frontend-field-edit-for-gf` text domain.
- **Secure:** Enforces login, capability checks, nonces, sanitization, validation, and form conflict detection.
- **GitHub Updates:** Supports automatic updates and a WordPress "View details" modal from GitHub releases.

## Requirements

- Gravity Forms 2.5 or higher
- WordPress 5.8 or higher
- PHP 7.4 or higher
- Guilamu Bug Reporter plugin is optional if you want in-dashboard bug reporting links

## Installation

1. Upload the `frontend-field-edit-for-gravity-forms` folder to `wp-content/plugins/`.
2. Activate the plugin through the **Plugins** menu in WordPress.
3. Make sure Gravity Forms 2.5 or newer is installed and active.
4. Go to **Forms → Settings → Frontend Field Edit** and choose editable settings, supported field types, and the required capability.
5. Open each target form in **Forms → Settings** and enable frontend field editing for the forms trusted users should be allowed to edit.

## FAQ

### Who can edit fields from the frontend?

Only logged-in users with the configured capability can edit fields, and only on forms where frontend editing is enabled.

### Why do I not see edit buttons on my form?

Check that Gravity Forms is active, frontend editing is enabled for that form, your account has the required capability, and the field type is in the supported list.

### Which settings can be edited?

The plugin supports a controlled subset of field settings such as labels, descriptions, placeholders, default values, address default country, required state, choices, consent text, address sub-fields, name sub-fields, and time field options.

### Does it work with Gravity Forms multi-page forms?

Yes. The live preview path tracks the current page so complex fields can be refreshed without falling back to the first page.

### Does it include an audit trail?

Yes. When audit logging is enabled, successful frontend edits are stored in the plugin's dedicated audit log table.

### How do GitHub updates work?

When you publish releases on GitHub, WordPress can detect new versions through the built-in update screen and show plugin details in the standard "View details" modal.

## Limitations

- Only supported field types and explicitly allowed settings can be edited from the frontend.
- Anonymous visitors cannot edit fields.
- Live preview refreshes field markup after each save, but unsupported field settings remain locked even if they are changed in the browser.

## Troubleshooting

1. Confirm Gravity Forms is active and meets the minimum supported version.
2. Verify the current user has the configured capability for frontend editing.
3. Re-open the form settings and confirm frontend editing is enabled for the specific form.
4. If changes do not appear, refresh the page and check whether another user has modified the form since you opened it.
5. If update checks seem stale, clear WordPress update caches and check the latest GitHub release tag.

## Project Structure

```text
.
├── frontend-field-edit-for-gravity-forms.php  # Plugin bootstrap, headers, bug reporter, and updater integration
├── README.md                                  # Plugin details, installation, FAQ, and changelog for WordPress modal
├── assets
│   ├── css
│   │   └── frontend-editor.css                # Frontend editor panel and field button styles
│   └── js
│       └── frontend-editor.js                 # Frontend editing UI, save flow, and live field refresh logic
├── includes
│   ├── class-ffe-addon.php                    # Gravity Forms add-on settings, lifecycle, and script registration
│   ├── class-ffe-audit-log.php                # Audit log table setup and successful change logging
│   ├── class-ffe-config-resolver.php          # Editable setting rules, defaults, and supported field matrices
│   ├── class-ffe-field-settings.php           # Gravity Forms field setting controls in the form editor
│   ├── class-ffe-frontend-controller.php      # Frontend config payload builder for editable forms and fields
│   ├── class-ffe-sanitizer.php                # Sanitization for incoming setting values
│   ├── class-ffe-save-controller.php          # AJAX save handling and Gravity Forms persistence updates
│   ├── class-ffe-validator.php                # Validation rules for frontend field edits
│   ├── class-github-updater.php               # GitHub auto-updates and the WordPress plugin details modal
│   └── Parsedown.php                          # Markdown parser used to render README sections in the modal
└── languages                                  # Translation files loaded through the plugin text domain
```

## Changelog

### 1.0.4 - 2026-07-31

- **Fixed:** Gravity Forms 3.0 compatibility — editing a field label no longer removes the `gform-field-label__text` wrapper that GF 3.0 renders around the label text, so labels keep their Orbital and 2.5 theme styling after an edit. Applies to plain labels, fieldset legends, and repeated edits.
- **Fixed:** Gravity Forms 3.0 compatibility — the Consent field is rebuilt with the same checkbox label structure the form was rendered with, wrapping the label text on GF 3.0 and leaving it bare on GF 2.x.

### 1.0.3 - 2026-07-31

- **Fixed:** Gravity Forms 3.0 compatibility — Date field default values are converted between the field's display format and the stored canonical format without jQuery UI, which GF 3.0 removed. Previously the editor showed the raw `yyyy-mm-dd` value and saved back whatever was typed, unconverted.
- **Fixed:** Gravity Forms 3.0 compatibility — the Placeholder and Default Value settings now target the input that actually carries the field's value, so they work with the new international Phone field, which keeps its value in a hidden input behind a formatted control.
- **Improved:** The Date default value control is now picked up by both datepicker generations: GF 2.x initialises it directly, and GF 3.0 attaches its own accessible datepicker on first focus.

### 1.0.2 - 2026-05-09

- **Improved:** Added the native Gravity Forms datepicker to Date field default value editing in the frontend panel.
- **Fixed:** Date field default values now follow the field's configured date format while still saving in a validated canonical format.
- **Fixed:** The frontend editor datepicker popup now opens in the correct position inside the fixed sidebar.

### 1.0.1 - 2026-05-07

- **Improved:** Added frontend editing for Time field per-input default values and live preview updates without a full page refresh.
- **Improved:** Added frontend editing for Address field per-input default values and the native Gravity Forms Default Country dropdown.

### 1.0.0 - 2026-05-06

- **New:** Added frontend inline editing for safe Gravity Forms field settings on enabled forms.
- **New:** Added support for Address, Consent, Email, Name, Time, Phone, Date, Website, Number, Drop Down, Multiple Choice, Radio Buttons, and Checkboxes.
- **New:** Added capability-based access control, per-form enablement, and audit logging.
- **New:** Added GitHub auto-updates, a WordPress "View details" modal, and Bug Reporter integration.

## License

This project is licensed under the GNU Affero General Public License v3.0 (AGPL-3.0) - see the [LICENSE](LICENSE) file for details.

---

<p align="center">
  Made with love for the WordPress community
</p>