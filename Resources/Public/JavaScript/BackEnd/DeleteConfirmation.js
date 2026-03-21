define(['TYPO3/CMS/Backend/Modal', 'TYPO3/CMS/Backend/Enum/Severity'], function(Modal, SeverityEnum) {
  var DeleteConfirmation = {};

  DeleteConfirmation.init = function() {
    const triggers = document.querySelectorAll('.t3js-seminars-confirmation-modal-trigger');
    triggers.forEach((triggerElement) => {
      triggerElement.addEventListener('submit', (event) => {
        DeleteConfirmation.openModal(event);
      });
    });
  };

  DeleteConfirmation.openModal = function(event) {
    event.preventDefault();

    const element = event.target;
    const title = element.dataset.title || 'Alert';
    const content = element.dataset.content || 'Are you sure?';

    Modal.confirm(title, content, SeverityEnum.warning, [
      {
        text: element.getAttribute('data-button-close-text') || 'Cancel',
        active: true,
        btnClass: 'btn-default',
        trigger: () => {
          Modal.dismiss();
        },
      },
      {
        text: element.getAttribute('data-button-ok-text') || 'Delete',
        btnClass: 'btn-warning',
        trigger: () => {
          element.submit();
          Modal.dismiss();
        },
      }
    ]);
  }

  DeleteConfirmation.init();

  // To let the module be a dependency of another module, we return our object
  return DeleteConfirmation;
});
