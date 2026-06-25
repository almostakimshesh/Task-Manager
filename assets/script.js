(function () {
    'use strict';

    const $ = (id) => document.getElementById(id);

    const modal = $('tm-modal');
    const form = $('tm-form');
    const addBtn = $('tm-add-btn');

    if (!modal || !form || !addBtn) {
        return;
    }

    /**
     * Open Modal
     */
    function openModal() {

        $('tm-modal-title').textContent = 'Add New Task';

        const taskId = $('tm-task-id');
        const title = $('tm-title');
        const status = $('tm-status');

        if (taskId) taskId.value = '';
        if (title) title.value = '';
        if (status) status.value = 'Pending';

        modal.classList.add('is-open');

        setTimeout(() => {
            if (title) {
                title.focus();
            }
        }, 100);
    }

    /**
     * Close Modal
     */
    function closeModal() {

        modal.classList.remove('is-open');

        form.reset();

    }

    /**
     * Open Modal
     */
    addBtn.addEventListener('click', function (e) {

        e.preventDefault();

        openModal();

    });

    /**
     * Close Modal
     */
    modal.addEventListener('click', function (e) {

        if (
            e.target.hasAttribute('data-close')
        ) {
            closeModal();
        }

    });

    /**
     * ESC Key Close
     */
    document.addEventListener('keydown', function (e) {

        if (
            e.key === 'Escape' &&
            modal.classList.contains('is-open')
        ) {
            closeModal();
        }

    });

})();