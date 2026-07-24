const Plugin = window.PluginBaseClass;

/**
 * Merges a dial-code select and a national number input into the single phone
 * value Shopware stores, without the backend knowing anything changed.
 *
 * Ownership of the submitted field is taken over on init: the visible input
 * arrives carrying the real `name` so the field still posts something usable
 * when JavaScript is unavailable, and this plugin moves that name onto a hidden
 * input it controls. The visible input therefore only ever holds the national
 * part — it cannot be double-prefixed by a form that failed validation and was
 * submitted again, which is the failure mode of prefixing the visible value in
 * place.
 *
 * Validation is registered against `window.formValidation` rather than done on
 * submit, so it runs through the same path as core's rules and renders the same
 * markup. The rule name `noDialCode` is applied by the Twig component.
 */
export default class DialCodePhonePlugin extends Plugin {

    static options = {
        /**
         * The name the merged value must be submitted under.
         */
        fieldName: '',

        selectSelector: '.kommandhub-dial-code-phone__code',
        nationalSelector: '.kommandhub-dial-code-phone__national',
    };

    init() {
        this._select = this.el.querySelector(this.options.selectSelector);
        this._national = this.el.querySelector(this.options.nationalSelector);

        if (!this._select || !this._national) {
            console.warn('[DialCodePhonePlugin] Missing dial code select or number input.');
            return;
        }

        this._registerValidator();
        this._createHiddenField();
        this._registerEvents();
        this._syncHiddenField();
    }

    /**
     * Moves the submitted name from the visible input onto a hidden field.
     *
     * Done in JS, not Twig, so the no-JavaScript path keeps the name on the
     * visible input and degrades to core's single-field behaviour.
     */
    _createHiddenField() {
        const name = this.options.fieldName || this._national.getAttribute('name');

        this._hidden = document.createElement('input');
        this._hidden.type = 'hidden';
        this._hidden.name = name;

        this._national.removeAttribute('name');
        this.el.appendChild(this._hidden);
    }

    _registerEvents() {
        this._select.addEventListener('change', this._onChange.bind(this));
        this._national.addEventListener('input', this._onChange.bind(this));

        // Normalising on blur rather than on every keystroke lets someone type
        // spaces or dashes while the cursor sits mid-number.
        this._national.addEventListener('blur', this._onBlur.bind(this));
    }

    _onChange() {
        this._syncHiddenField();
    }

    _onBlur() {
        this._national.value = this._normalise(this._national.value);
        this._syncHiddenField();
    }

    /**
     * Strips everything that is not a digit, then removes a single leading
     * trunk zero.
     *
     * The trunk prefix is a national dialling convention — "0803…" inside
     * Nigeria is "+234 803…" internationally — so keeping it would produce
     * "+2340803…", a number that reaches nobody.
     */
    _normalise(value) {
        const digits = String(value ?? '').replace(/\D+/g, '');

        return digits.replace(/^0+/, '');
    }

    /**
     * Writes the merged value that actually gets submitted.
     *
     * Left empty when there is no number, so an optional field submits nothing
     * rather than a bare "+234" that would fail server-side validation and look
     * to the shopper like a number they never entered.
     */
    _syncHiddenField() {
        const national = this._normalise(this._national.value);
        const dialCode = this._select.value;

        if (!national) {
            this._hidden.value = '';
            return;
        }

        this._hidden.value = dialCode ? `+${dialCode}${national}` : national;
    }

    /**
     * Registers the `noDialCode` rule once per page.
     *
     * The validator is global, so several phone fields on one page — billing
     * and shipping in the same checkout form — share it.
     */
    _registerValidator() {
        const formValidation = window.formValidation;

        if (!formValidation || typeof formValidation.addValidator !== 'function') {
            return;
        }

        if (DialCodePhonePlugin._validatorRegistered) {
            return;
        }

        formValidation.addValidator(
            'noDialCode',
            (value, field) => this._validateNoDialCode(value, field),
            this._validationMessage(),
        );

        DialCodePhonePlugin._validatorRegistered = true;
    }

    /**
     * Rejects a country code typed into the national field.
     *
     * The dial code belongs in the select; leaving it in the number too is the
     * one mistake this split field invites, and it produces "+234+234803…".
     *
     * An empty value is valid here — whether the field may be empty at all is
     * the `required` rule's business, and reporting both would show two
     * messages for one omission.
     */
    _validateNoDialCode(value, field) {
        const raw = String(value ?? '').trim();

        if (raw === '') {
            return true;
        }

        if (raw.startsWith('+') || raw.replace(/\D+/g, '').startsWith('00')) {
            return false;
        }

        const digits = raw.replace(/\D+/g, '');
        const selected = this._selectedDialCodeFor(field);

        // Typing the selected country's own code, e.g. "234803…" while +234 is
        // chosen. Guarded by length so a Nigerian number that legitimately
        // opens with those digits is not refused outright.
        if (selected && digits.startsWith(selected) && digits.length > selected.length + 6) {
            return false;
        }

        return true;
    }

    /**
     * The dial code belonging to the field being validated.
     *
     * Looked up from the field rather than from `this`, because the global
     * validator is shared by every phone field on the page.
     */
    _selectedDialCodeFor(field) {
        const group = field.closest('[data-kommandhub-dial-code-phone]');
        const select = group?.querySelector(this.options.selectSelector);

        return select?.value || null;
    }

    _validationMessage() {
        return this.el.getAttribute('data-kommandhub-dial-code-phone-message')
            || 'Enter the number without the country code — choose the country from the list next to it.';
    }
}

/**
 * Guards the global validator registration against multiple field instances.
 */
DialCodePhonePlugin._validatorRegistered = false;
