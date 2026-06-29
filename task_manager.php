<?php
/*
 * Plugin Name:       Task Manager
 * Plugin URI:        https://example.com/plugins/
 * Description:       Handle the basics with this plugin.
 * Version:           1.1.2
 * Requires at least: 5.2
 * Requires PHP:      7.2
 * Author:            Al Mostakim
 * Author URI:        https://author.example.com/
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       task-manager
 * Domain Path:       /languages
 */

if (!defined('ABSPATH')) {
    exit;
}

/*
|--------------------------------------------------------------------------
| Enqueue Assets
|--------------------------------------------------------------------------
*/

add_action('admin_enqueue_scripts', function ($hook) {
    if ('toplevel_page_task-manager' !== $hook) {
        return;
    }

    $base = plugin_dir_url(__FILE__);

    wp_enqueue_style(
        'tm-style',
        $base . 'assets/css/style.css',
        [],
        '1.0.0'
    );

    wp_enqueue_script(
        'tm-script',
        $base . 'assets/js/script.js',
        [],
        '1.0.1',
        true
    );
});

/*
|--------------------------------------------------------------------------
| Register Custom Post Type
|--------------------------------------------------------------------------
*/

add_action('init', 'tm_register_cpt');

function tm_register_cpt()
{
    register_post_type('tm_tasks', [
        'label'    => 'Tasks',
        'public'   => false,
        'show_ui'  => false,
        'supports' => ['title'],
    ]);
}

/*
|--------------------------------------------------------------------------
| Admin Menu
|--------------------------------------------------------------------------
*/

add_action('admin_menu', 'tm_admin_menu');

function tm_admin_menu()
{
    add_menu_page(
        'Task Manager',
        'Task Manager',
        'manage_options',
        'task-manager',
        'tm_render_admin_page',
        'dashicons-list-view',
        25
    );
}

/*
|--------------------------------------------------------------------------
| Save or Update Task
|--------------------------------------------------------------------------
*/

add_action('admin_post_tm_save_task', 'tm_handle_save');

function tm_handle_save()
{
    check_admin_referer('tm_save_task');

    if (!current_user_can('manage_options')) {
        wp_die(
            esc_html__('You do not have permission to perform this action.', 'task-manager')
        );
    }

    $task_id = isset($_POST['tm_task_id'])
        ? absint($_POST['tm_task_id'])
        : 0;

    $title = isset($_POST['tm_title'])
        ? sanitize_text_field(wp_unslash($_POST['tm_title']))
        : '';

    $status = isset($_POST['_tm_status'])
        ? sanitize_text_field(wp_unslash($_POST['_tm_status']))
        : 'Pending';

    $allowed_statuses = [
        'Pending',
        'Completed',
    ];

    if (!in_array($status, $allowed_statuses, true)) {
        $status = 'Pending';
    }

    if ('' === $title) {
        wp_safe_redirect(
            admin_url('admin.php?page=task-manager&error=empty-title')
        );
        exit;
    }

    if ($task_id > 0) {
        $existing_task = get_post($task_id);

        if (!$existing_task || 'tm_tasks' !== $existing_task->post_type) {
            wp_safe_redirect(
                admin_url('admin.php?page=task-manager&error=task-not-found')
            );
            exit;
        }

        $result = wp_update_post(
            [
                'ID'          => $task_id,
                'post_title'  => $title,
                'post_status' => 'publish',
            ],
            true
        );

        if (is_wp_error($result)) {
            wp_die(esc_html($result->get_error_message()));
        }

        /*
        * Keep the task active when updating.
        */
        update_post_meta($task_id, 'tm_status', 'active');

        /*
        * Save Pending/Completed status.
        */
        update_post_meta($task_id, '_tm_status', $status);

        if (is_wp_error($result)) {
            wp_die(esc_html($result->get_error_message()));
        }

        update_post_meta($task_id, '_tm_status', $status);

        wp_safe_redirect(
            admin_url('admin.php?page=task-manager&updated=1')
        );
        exit;
    }

    $task_id = wp_insert_post(
        [
            'post_type'   => 'tm_tasks',
            'post_title'  => $title,
            'post_status' => 'publish',
        ],
        true
    );

    if (is_wp_error($task_id)) {
        wp_die(esc_html($task_id->get_error_message()));
    }

    update_post_meta($task_id, '_tm_status', $status);

    wp_safe_redirect(
        admin_url('admin.php?page=task-manager&saved=1')
    );
    exit;
}

/*
|--------------------------------------------------------------------------
| Delete Task
|--------------------------------------------------------------------------
*/

add_action('admin_post_tm_delete_task', 'tm_handle_delete_task');

function tm_handle_delete_task()
{
    if (!current_user_can('manage_options')) {
        wp_die(
            esc_html__(
                'You do not have permission to delete this task.',
                'task-manager'
            )
        );
    }

    $task_id = isset($_POST['tm_task_id'])
        ? absint(wp_unslash($_POST['tm_task_id']))
        : 0;

    if (!$task_id) {
        wp_safe_redirect(
            admin_url('admin.php?page=task-manager&error=invalid-task')
        );
        exit;
    }

    check_admin_referer('tm_delete_task_' . $task_id);

    $task = get_post($task_id);

    if (!$task || 'tm_tasks' !== $task->post_type) {
        wp_safe_redirect(
            admin_url('admin.php?page=task-manager&error=task-not-found')
        );
        exit;
    }

    /*
     * Keep the original soft-delete concept:
     * mark the task as inactive instead of permanently deleting it.
     */
    update_post_meta(
        $task_id,
        'tm_status',
        'inactive'
    );

    wp_safe_redirect(
        admin_url('admin.php?page=task-manager&deleted=1')
    );
    exit;
}

/*
|--------------------------------------------------------------------------
| Admin Page
|--------------------------------------------------------------------------
*/

function tm_render_admin_page()
{
    $tasks = get_posts([
        'post_type'      => 'tm_tasks',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'orderby'        => 'ID',
        'order'          => 'DESC',

        /*
         * Show normal/active tasks only.
         * Tasks marked inactive by the Delete button are hidden.
         */
        'meta_query'     => [
            'relation' => 'OR',
            [
                'key'     => 'tm_status',
                'compare' => 'NOT EXISTS',
            ],
            [
                'key'     => 'tm_status',
                'value'   => 'inactive',
                'compare' => '!=',
            ],
        ],
    ]);
    ?>

    <div class="wrap tm-wrap">
        <div class="tm-toolbar">
            <h1 class="tm-title">Task Manager</h1>

            <?php if (isset($_GET['saved'])) : ?>
                <div class="notice notice-success is-dismissible">
                    <p>Task saved successfully.</p>
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['updated'])) : ?>
                <div class="notice notice-success is-dismissible">
                    <p>Task updated successfully.</p>
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['deleted'])) : ?>
                <div class="notice notice-error is-dismissible">
                    <p>Task deleted successfully.</p>
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['error'])) : ?>
                <div class="notice notice-error is-dismissible">
                    <p>Unable to save the task. Please check the submitted information.</p>
                </div>
            <?php endif; ?>

            <button
                type="button"
                id="tm-add-btn"
                class="tm-btn tm-btn-primary"
            >
                + Add New Task
            </button>
        </div>

        <div class="tm-card">
            <table class="tm-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Title</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="tm-tbody">
                    <?php if (empty($tasks)) : ?>
                        <tr>
                            <td colspan="4" class="tm-empty">
                                No tasks yet. Click "Add New Task" to create one.
                            </td>
                        </tr>
                    <?php else : ?>
                        <?php foreach ($tasks as $index => $task) : ?>
                            <?php
                            $status = get_post_meta($task->ID, '_tm_status', true);
                            $status = $status ? $status : 'Pending';
                            ?>
                            <tr>
                                <td><?php echo esc_html($index + 1); ?></td>
                                <td><?php echo esc_html($task->post_title); ?></td>
                                <td><?php echo esc_html($status); ?></td>
                                <td style="display: flex; gap: 5px;">
                                    <button type="button" class="tm-btn tm-btn-primary tm-edit-btn" data-id="<?php echo esc_attr($task->ID); ?>" data-title="<?php echo esc_attr($task->post_title); ?>" data-status="<?php echo esc_attr($status); ?>">
                                        Edit
                                    </button>
                                    <form method="POST" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="tm-delete-form" data-title="<?php echo esc_attr($task->post_title); ?>">
                                        <?php wp_nonce_field('tm_delete_task_' . $task->ID); ?>
                                        <input type="hidden" name="action" value="tm_delete_task">
                                        <input type="hidden" name="tm_task_id" value="<?php echo esc_attr($task->ID); ?>">
                                        <button type="submit" class="tm-btn tm-btn-danger tm-delete-btn">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div id="tm-modal" class="tm-modal" aria-hidden="true">
            <div class="tm-modal-backdrop" data-close></div>
            <div class="tm-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="tm-modal-title">
                <div class="tm-modal-header">
                    <h2 id="tm-modal-title">Add New Task</h2>
                    <button type="button"class="tm-modal-close" data-close aria-label="Close">
                        &times;
                    </button>
                </div>

                <form method="POST" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" id="tm-form" class="tm-form">
                    <?php wp_nonce_field('tm_save_task'); ?>
                    <input type="hidden" name="action" value="tm_save_task">
                    <input type="hidden" name="tm_task_id" id="tm-task-id" value="">
                    <label class="tm-label" for="tm-title">Title</label>
                    <input type="text" name="tm_title" id="tm-title" class="tm-input" maxlength="120" required>
                    <label class="tm-label" for="tm-status">Status</label>
                    <select name="_tm_status" id="tm-status" class="tm-input">
                        <option value="Pending">Pending</option>
                        <option value="Completed">Completed</option>
                    </select>
                    <div class="tm-modal-footer">
                        <button type="button" class="tm-btn" data-close>
                            Cancel
                        </button>
                        <button type="submit" id="tm-submit-btn" class="tm-btn tm-btn-primary">
                            Save Task
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php
}
