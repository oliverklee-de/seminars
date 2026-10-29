/*
 * This file provides some JavaScript functions for the seminars front-end
 * editor and the registration form.
 */

;((root, exports) => {
  'use strict'

  class Seminars {
    /**
     * Classes that will show an element (with `display: block;`).
     *
     * The first class comes from Bootstrap, while the other is our own
     * (so that this feature works both with the Bootstrap CSS or the seminars CSS).
     */
    static visibilityClasses = ['d-block', 'tx-seminars-display-block']

    /**
     * Classes that will hide an element (with `display: none;`).
     *
     * The first class comes from Bootstrap, while the other is our own
     * (so that this feature works both with the Bootstrap CSS or the seminars CSS).
     */
    static invisibilityClasses = ['d-none', 'tx-seminars-display-none']

    elements = {}

    isAlreadySubmitted = false;

    constructor() {
      document.addEventListener('readystatechange', () => {
        this.initializeSearchWidget();
        this.initializeRegistrationForm();
        this.initializeUnregistrationForm();
        this.initializePreventDoubleSubmits();
      });
    }

    /**
     * Clears the selection of the search widget.
     */
    clearSearchWidgetFields() {
      var prefix = 'tx_seminars_pi1';
      var textElements = ['sword', 'search_age', 'price_from', 'price_to'];
      for (var i = 0; i < textElements.length; i++) {
        var textElement = document.getElementById(prefix + '_' + textElements[i]);
        if (textElement) {
          textElement.value = null;
        }
      }

      var suffixes = ['from_day', 'from_month', 'from_year', 'to_day', 'to_month',
        'to_year', 'event_type', 'city', 'place', 'date',
        'organizer', 'categories'
      ];

      for (var j = 0; j < suffixes.length; j++) {
        var suffix = suffixes[j];
        var element = document.getElementById(prefix + '-' + suffix);
        if (element) {
          for (var k = 0; k < element.options.length; k++) {
            element.options[k].selected = false;
          }
        }
      }
    }

    /**
     * Initializes the search widget.
     */
    initializeSearchWidget() {
      const searchWidget = document.querySelector('.tx-seminars-pi1-selectorwidget');
      const clearSearchWidgetButton = document.querySelector('#tx-seminars-pi1-clear-search-widget');
      if (!(searchWidget instanceof Element) || !(clearSearchWidgetButton instanceof Element)) {
        return;
      }

      clearSearchWidgetButton.addEventListener('click', this.clearSearchWidgetFields);
    }

    findRegistrationFormElements() {
      const selectors = {
        registrationForm: 'form[data-behavior="tx-seminars-registration-form"]',
        seats: '[data-behavior="tx-seminars-seats"]',
        registeredThemselves: '[data-behavior="tx-seminars-registered-themselves"]',
        separateAttendeeNames: '[data-behavior="tx-seminars-separate-attendee-names"]',
        attendeeTemplate: '[data-behavior="tx-seminars-attendee-template"]',
        humanReadableAttendeeNames: '[data-behavior="tx-seminars-human-readable-attendee-names"]',
        machineReadableAttendeeNames: '[data-behavior="tx-seminars-machine-readable-attendee-names"]',
      }

      for (const [key, selector] of Object.entries(selectors)) {
        this.elements[key] = document.querySelector(selector);
      }
    }

    existsRegistrationForm() {
      return this.elements.registrationForm instanceof Element;
    }

    initializeRegistrationForm() {
      this.findRegistrationFormElements();
      if (!this.existsRegistrationForm()) {
        return;
      }

      this.showOrHideAttendeeNames();
      this.restoreAttendeeNames();
      this.addSeatsListener();
    }

    initializeUnregistrationForm() {
      const cancelButton = document.querySelector('button[data-behavior="tx-seminars-cancel-unregistration"]');
      if (!(cancelButton instanceof Element)) {
        return;
      }

      cancelButton.addEventListener('click', this.goBack);
    }

    goBack() {
      history.back();
    }

    addSeatsListener() {
      if (this.elements.seats instanceof Element) {
        this.elements.seats.addEventListener('change', this.showOrHideAttendeeNames.bind(this));
      }
      if (this.elements.registeredThemselves instanceof Element) {
        this.elements.registeredThemselves
          .addEventListener('change', this.showOrHideAttendeeNames.bind(this));
      }
    }

    /**
     * Shows/hides the attendee fields depending on the number of seats.
     */
    showOrHideAttendeeNames() {
      if (!(this.elements.separateAttendeeNames instanceof Element)) {
        return;
      }

      const otherSeats = Math.max(this.numberOfRequiredAdditionalAttendees(), 0);
      if (otherSeats > 0) {
        this.showElement(this.elements.separateAttendeeNames);
      } else {
        this.hideElement(this.elements.separateAttendeeNames);
      }
      if (!(this.elements.attendeeTemplate instanceof Element)) {
        return;
      }

      let currentlyVisibleAttendeeFieldsCount = this.elements.separateAttendeeNames.children.length;
      while (currentlyVisibleAttendeeFieldsCount !== otherSeats) {
        if (currentlyVisibleAttendeeFieldsCount < otherSeats) {
          this.addAttendeeLine();
          currentlyVisibleAttendeeFieldsCount++;
        } else {
          this.removeLastAttendeeLine();
          currentlyVisibleAttendeeFieldsCount--;
        }
      }
    }

    addAttendeeLine() {
      const newAttendeeLine = this.elements.attendeeTemplate.cloneNode(true);
      const newAttendeeLineNumber = (this.elements.separateAttendeeNames.children.length + 1).toString();

      for (const inputElement of newAttendeeLine.querySelectorAll('input')) {
        inputElement.setAttribute('id', inputElement.getAttribute('id').replace('xxx', newAttendeeLineNumber));
        inputElement.setAttribute('required', 'required');
        inputElement.addEventListener('change', this.compileAttendeeNames.bind(this));
      }
      for (const labelElement of newAttendeeLine.querySelectorAll('label')) {
        labelElement.setAttribute('for', labelElement.getAttribute('for').replace('xxx', newAttendeeLineNumber));
      }

      this.showElement(newAttendeeLine);
      this.elements.separateAttendeeNames.appendChild(newAttendeeLine);
    }

    /**
     * @return {NodeList}
     */
    getAttendeeLines() {
      return this.elements.separateAttendeeNames.querySelectorAll('li');
    }

    removeLastAttendeeLine() {
      const lines = this.getAttendeeLines();
      const lastLine = lines[lines.length - 1];
      for (const inputElement of lastLine.querySelectorAll('input')) {
        inputElement.removeEventListener('change', this.compileAttendeeNames);
      }

      lastLine.remove();

      this.compileAttendeeNames();
    }

    /**
     * @return {number}
     */
    numberOfRequiredAdditionalAttendees() {
      let seats = 1;
      if (this.elements.seats instanceof Element) {
        seats = parseInt(this.elements.seats.value);
      }

      let registeredThemselves = true;
      if (this.elements.registeredThemselves instanceof Element) {
        if (this.elements.registeredThemselves.type === 'checkbox') {
          registeredThemselves = this.elements.registeredThemselves.checked === true;
        } else if (this.elements.registeredThemselves.type === 'hidden') {
          registeredThemselves = this.elements.registeredThemselves.value === '1';
        }
      }
      return seats - (registeredThemselves ? 1 : 0);
    }

    /**
     * Takes the names (and potentially email addresses) of the additional attendees from the corresponding separate
     * input fields and compiles them both human-readable into the `attendeesNames` input field and machine-readable
     * into the `jsonEncodedAdditionAttendees` input field.
     */
    compileAttendeeNames() {
      if (!(this.elements.separateAttendeeNames instanceof Element)) {
        return;
      }

      let humanReadableAttendeeNames = [];
      let machineReadableAttendeeNames = [];
      for (const attendeeLine of this.getAttendeeLines()) {
        const nameInput = attendeeLine.querySelector('input[name="attendeeName"]');
        const emailInput = attendeeLine.querySelector('input[name="attendeeEmail"]');
        const name = (nameInput instanceof Element) ? nameInput.value : '';
        const email = (emailInput instanceof Element) ? emailInput.value : '';
        humanReadableAttendeeNames.push((name + ' ' + email).trim());
        machineReadableAttendeeNames.push({name: name, email: email});
      }

      const hasHumanReadableAttendeeNames = this.elements.humanReadableAttendeeNames instanceof Element;
      if (hasHumanReadableAttendeeNames) {
        this.elements.humanReadableAttendeeNames.value = humanReadableAttendeeNames.join("\n").trim();
      }
      const hasMachineReadableAttendeeNames = this.elements.machineReadableAttendeeNames instanceof Element;
      if (hasMachineReadableAttendeeNames) {
        this.elements.machineReadableAttendeeNames.value = JSON.stringify(machineReadableAttendeeNames);
      }
    }

    /**
     * Takes the machine-readable attendee names from the `jsonEncodedAdditionAttendees` input field and fills the
     * corresponding separate input fields with the names and email addresses.
     */
    restoreAttendeeNames() {
      const hasMachineReadableAttendeeNames = this.elements.machineReadableAttendeeNames instanceof Element
        && this.elements.machineReadableAttendeeNames.value !== '';
      if (!hasMachineReadableAttendeeNames) {
        return;
      }

      const attendeeNames = JSON.parse(this.elements.machineReadableAttendeeNames.value);
      if (!Array.isArray(attendeeNames)) {
        return;
      }

      let attendeeIndex = 0;
      for (const attendeeLine of this.getAttendeeLines()) {
        if (typeof (attendeeNames[attendeeIndex]) === 'object') {
          const nameInput = attendeeLine.querySelector('input[name="attendeeName"]');
          if (nameInput instanceof Element && typeof (attendeeNames[attendeeIndex].name) === 'string') {
            nameInput.value = attendeeNames[attendeeIndex].name;
          }
          const emailInput = attendeeLine.querySelector('input[name="attendeeEmail"]');
          if (emailInput instanceof Element && typeof (attendeeNames[attendeeIndex].email) === 'string') {
            emailInput.value = attendeeNames[attendeeIndex].email;
          }
        }

        attendeeIndex++;
      }
    }

    initializePreventDoubleSubmits() {
      const forms = document.querySelectorAll('[data-prevent-multiple-submits]');

      forms.forEach((form) => {
        form.addEventListener('submit', (event) => this.handleRegistrationSubmit(event), false);
      });
    }

    handleRegistrationSubmit(event) {
      if (this.isAlreadySubmitted) {
        event.preventDefault();
        return;
      }

      this.isAlreadySubmitted = true;

      const form = event.currentTarget;
      const submitButtons = form.querySelectorAll('button[type="submit"], input[type="submit"]');

      submitButtons.forEach((submitButton) => {
        submitButton.setAttribute('disabled', 'disabled');
      });
    }

    showElement(element) {
      if (!(element instanceof Element)) {
        return;
      }

      Seminars.visibilityClasses.forEach((className) => {
        element.classList.add(className);
      });
      Seminars.invisibilityClasses.forEach((className) => {
        element.classList.remove(className);
      });
    }

    hideElement(element) {
      Seminars.invisibilityClasses.forEach((className) => {
        element.classList.add(className);
      });
      Seminars.visibilityClasses.forEach((className) => {
        element.classList.remove(className);
      });
    }
  }

  exports.seminars = new Seminars();
})(
  typeof self !== 'undefined' ? self : this,
  self.TYPO3 = self.TYPO3 || {},
)
