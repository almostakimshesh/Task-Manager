document.addEventListener('DOMContentLoaded', function () {
    'use strict';

    const modal = document.getElementById('tm-modal');
    const form = document.getElementById('tm-form');
    const addButton = document.getElementById('tm-add-btn');
    const modalTitle = document.getElementById('tm-modal-title');
    const taskIdInput = document.getElementById('tm-task-id');
    const titleInput = document.getElementById('tm-title');
    const statusInput = document.getElementById('tm-status');
    const submitButton = document.getElementById('tm-submit-btn');

    if (
        !modal ||
        !form ||
        !addButton ||
        !modalTitle ||
        !taskIdInput ||
        !titleInput ||
        !statusInput ||
        !submitButton
    ) {
        return;
    }

    function showModal() {
        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('tm-modal-open');

        window.setTimeout(function () {
            titleInput.focus();
        }, 50);
    }

    function closeModal() {
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('tm-modal-open');
        form.reset();
        taskIdInput.value = '';
    }

    function openAddModal() {
        form.reset();
        taskIdInput.value = '';
        statusInput.value = 'Pending';
        modalTitle.textContent = 'Add New Task';
        submitButton.textContent = 'Save Task';
        showModal();
    }

    function openEditModal(button) {
        const taskId = button.getAttribute('data-id') || '';
        const taskTitle = button.getAttribute('data-title') || '';
        const taskStatus = button.getAttribute('data-status') || 'Pending';

        taskIdInput.value = taskId;
        titleInput.value = taskTitle;
        statusInput.value = taskStatus;
        modalTitle.textContent = 'Edit Task';
        submitButton.textContent = 'Update Task';
        showModal();
    }

    addButton.addEventListener('click', function (event) {
        event.preventDefault();
        openAddModal();
    });

    document.addEventListener('click', function (event) {
        const editButton = event.target.closest('.tm-edit-btn');

        if (editButton) {
            event.preventDefault();
            openEditModal(editButton);
            return;
        }

        const closeButton = event.target.closest('[data-close]');

        if (closeButton && modal.classList.contains('is-open')) {
            event.preventDefault();
            closeModal();
        }
    });

    document.addEventListener('keydown', function (event) {
        if ('Escape' === event.key && modal.classList.contains('is-open')) {
            closeModal();
        }
    });
});
