<?php
/*
 * Plugin Name:       Task Manager
 * Plugin URI:        https://example.com/plugins/the-basics/
 * Description:       Handle the basics with this plugin.
 * Version:           1.1.1
 * Requires at least: 5.2
 * Requires PHP:      7.2
 * Author:            Al Mostakim
 * Author URI:        https://author.example.com/
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Update URI:        https://example.com/my-plugin/
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

    if ($hook !== 'toplevel_page_task-manager') {
        return;
    }

    $base = plugin_dir_url(__FILE__);

    wp_enqueue_style(
        'tm-style',
        $base . 'assets/style.css',
        [],
        '1.0.0'
    );

    wp_enqueue_script(
        'tm-script',
        $base . 'assets/script.js',
        [],
        '1.0.0',
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
        'label' => 'Tasks',
        'public' => false,
        'show_ui' => false,
        'supports' => ['title']
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
| Save Task
|--------------------------------------------------------------------------
*/

add_action('admin_post_tm_save_task', 'tm_handle_save');

function tm_handle_save()
{
    check_admin_referer('tm_save_task');

    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }

    $title  = sanitize_text_field($_POST['tm_title'] ?? '');
    $status = sanitize_text_field($_POST['_tm_status'] ?? 'Pending');

    if (empty($title)) {
        wp_redirect(admin_url('admin.php?page=task-manager'));
        exit;
    }

    $task_id = wp_insert_post([
        'post_type'   => 'tm_tasks',
        'post_title'  => $title,
        'post_status' => 'publish',
    ], true);

    if (is_wp_error($task_id)) {
        wp_die($task_id->get_error_message());
    }

    update_post_meta(
        $task_id,
        '_tm_status',
        $status
    );

    wp_redirect(admin_url('admin.php?page=task-manager&saved=1'));
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
        'order'          => 'DESC'
    ]);
?>

<div class="wrap tm-wrap">

    <div class="tm-toolbar">
        <h1 class="tm-title">Task Manager</h1>

        <button type="button"
                id="tm-add-btn"
                class="tm-btn tm-btn-primary">
            + Add New Task
        </button>
    </div>

    <?php if (isset($_GET['saved'])) : ?>
        <div class="notice notice-success is-dismissible">
            <p>Task saved successfully.</p>
        </div>
    <?php endif; ?>
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
                        <td colspan="3" class="tm-empty">
                            No tasks yet. Click "Add New Task" to create one.
                        </td>
                    </tr>
                <?php else : ?>
                <?php foreach ($tasks as $index => $task) :
                        $status = get_post_meta(
                            $task->ID,
                            '_tm_status',
                            true
                        );
                    ?>
                        <tr>
                            <td><?php echo esc_html($index + 1); ?></td>
                            <td>
                                <?php echo esc_html($task->post_title); ?>
                            </td>
                            <td>
                                <?php echo esc_html(
                                    $status ? $status : 'Pending'
                                ); ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Modal -->

    <div id="tm-modal" class="tm-modal">
        <div class="tm-modal-backdrop" data-close></div>
        <div class="tm-modal-dialog">
            <div class="tm-modal-header">
                <h2 id="tm-modal-title">
                    Add New Task
                </h2>
                <button type="button"class="tm-modal-close" data-close>&times;</button>
            </div>
            <form method="POST" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"id="tm-form" class="tm-form">
                <?php wp_nonce_field('tm_save_task'); ?>
                <input type="hidden" name="action" value="tm_save_task">
                <label class="tm-label">Title </label>
                <input type="text" name="tm_title" id="tm-title" class="tm-input" maxlength="120" required> 
                <label class="tm-label">Status</label> 
                <select name="_tm_status" id="tm-status" class="tm-input">
                    <option value="Pending">Pending</option>
                    <option value="Completed">Completed</option>
                </select> 
                <div class="tm-modal-footer">
                    <button type="button" class="tm-btn" data-close>Cancel</button> 
                    <button type="submit" class="tm-btn tm-btn-primary">Save Task</button>
                </div>
            </form>
        </div>
    </div>
</div>

   <div id="tm-modal" class="tm-modal" aria-hidden="true">
            <div class="tm-modal-backdrop" data-close></div>
            <div class="tm-modal-dialog" role="dialog" aria-modal="true">
                <div class="tm-modal-header">
                    <h2 id="tm-modal-title">Add New Task</h2>
                    <button class="tm-modal-close" data-close>&times;</button>
                </div>
                <form id="tm-form" class="tm-form">
                    <input type="hidden" id="tm-task-id" />
                    <label class="tm-label">Title
                        <input type="text" id="tm-title" class="tm-input" required maxlength="120" />
                    </label>
                    <label class="tm-label">Description
                        <textarea id="tm-desc" class="tm-input" rows="3" maxlength="500"></textarea>
                    </label>
                    <div class="tm-grid-2">
                        <label class="tm-label">Priority
                            <select id="tm-priority" class="tm-input">
                                <option value="Low">Low</option>
                                <option value="Medium" selected>Medium</option>
                                <option value="High">High</option>
                            </select>
                        </label>
                        <label class="tm-label">Status
                            <select id="tm-status" class="tm-input">
                                <option value="Pending" selected>Pending</option>
                                <option value="In Progress">In Progress</option>
                                <option value="Completed">Completed</option>
                            </select>
                        </label>
                    </div>
                    <label class="tm-label">Due Date
                        <input type="date" id="tm-due" class="tm-input" />
                    </label>
                    <div class="tm-modal-footer">
                        <button type="button" class="tm-btn" data-close>Cancel</button>
                        <button type="submit" class="tm-btn tm-btn-primary" id="tm-save-btn">Save Task</button>
                    </div>
                </form>
            </div>
        </div>
<?php
}