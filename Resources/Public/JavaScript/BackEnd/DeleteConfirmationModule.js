import {SeverityEnum} from "@typo3/backend/enum/severity.js";
import Modal from "@typo3/backend/modal.js";

var DeleteConfirmation = {};

DeleteConfirmation.init = function () {
  const triggers = document.querySelectorAll('.t3js-seminars-confirmation-modal-trigger');
  triggers.forEach((triggerElement) => {
    triggerElement.addEventListener('submit', (event) => {
      DeleteConfirmation.openModal(event);
    });
  });
};

DeleteConfirmation.openModal = function (event) {
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
