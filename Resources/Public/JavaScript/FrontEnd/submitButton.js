document.addEventListener("DOMContentLoaded", function(e) {
  let form_being_submitted = false;

  let checkForm = function(e) {
    let form = e.target;

    let submitBtn = form.querySelector('button[type="submit"], input[type="submit"]');

    if (form_being_submitted) {
      alert("Das Formular wird bereits gesendet, bitte einen Moment Geduld...");
      if (submitBtn) {
        submitBtn.disabled = true;
      e.preventDefault();
      return;
    }
      submitBtn.value = "Wird gesendet...";
      form_being_submitted = true;
  }

  let formElement = document.querySelector('[data-behavior="tx-seminars-registration-form"]');

  if (formElement) {
    formElement.addEventListener("submit", checkForm, false);
  }
}});
