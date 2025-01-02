<?php
/**
 * Plugin Name: Steam Login and Email Password Display
 * Description: Verifies Steam login credentials and displays the associated email password. Includes client login page and admin management page.
 * Version: 1.0
 * Author: Your Name
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

// Создание таблицы для хранения данных аккаунтов Steam
register_activation_hook(__FILE__, 'plugin_activation_setup');
function plugin_activation_setup() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'steam_credentials';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        steam_login varchar(255) NOT NULL,
        steam_password varchar(255) NOT NULL,
        email varchar(255) NOT NULL,
        email_password varchar(255) NOT NULL,
        PRIMARY KEY  (id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);

    create_plugin_pages();
}

function create_plugin_pages() {
    // Страница для логина клиента
    if (null === get_page_by_path('client-login')) {
        wp_insert_post([
            'post_title'   => 'Client Login',
            'post_name'    => 'client-login',
            'post_content' => '[client_login_form]',
            'post_status'  => 'publish',
            'post_type'    => 'page',
        ]);
    }

    // Страница для админской панели
    if (null === get_page_by_path('admin-management')) {
        wp_insert_post([
            'post_title'   => 'Admin Management',
            'post_name'    => 'admin-management',
            'post_content' => '[admin_management_form]',
            'post_status'  => 'publish',
            'post_type'    => 'page',
        ]);
    }
}

// Краткоды для отображения форм
add_shortcode('client_login_form', 'display_client_login_form');
function display_client_login_form() {
    ob_start();
    handle_client_login();
    return ob_get_clean();
}

add_shortcode('admin_management_form', 'display_admin_management_form');
function display_admin_management_form() {
    if (!current_user_can('manage_options')) {
        return '<div style="color: red;">You do not have sufficient permissions to access this page.</div>';
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'steam_credentials';

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_credentials'])) {
        $steam_login = sanitize_text_field($_POST['steam_login']);
        $steam_password = sanitize_text_field($_POST['steam_password']);
        $email = sanitize_email($_POST['email']);
        $email_password = sanitize_text_field($_POST['email_password']); // Используем sanitize_text_field

        // Вставка данных в таблицу
        $wpdb->insert(
            $table_name,
            [
                'steam_login' => $steam_login,
                'steam_password' => $steam_password,
                'email' => $email,
                'email_password' => $email_password
            ]
        );

        echo '<div style="color: green;">Credentials added successfully!</div>';
    }

    // Форма для добавления данных
    echo '<h3>Add Steam Account Credentials</h3>';
    echo '<form method="post">';
    echo '<p>Steam Login:</p>';
    echo '<input type="text" name="steam_login" required />';
    echo '<p>Steam Password:</p>';
    echo '<input type="password" name="steam_password" required />';
    echo '<p>Email:</p>';
    echo '<input type="email" name="email" required />';
    echo '<p>Email Password:</p>';
    echo '<input type="text" name="email_password" required />'; <!-- Изменено на type="text" -->
    echo '<input type="submit" name="add_credentials" value="Add Credentials" />';
    echo '</form>';

    // Таблица с добавленными данными
    echo '<h3>Manage Steam Accounts</h3>';
    $accounts = $wpdb->get_results("SELECT * FROM $table_name");

    if ($accounts) {
        echo '<table>';
        echo '<tr><th>Steam Login</th><th>Email</th><th>Email Password</th><th>Actions</th></tr>';
        foreach ($accounts as $account) {
            echo '<tr>';
            echo '<td>' . esc_html($account->steam_login) . '</td>';
            echo '<td>' . esc_html($account->email) . '</td>';
            echo '<td>' . esc_html($account->email_password) . '</td>';
            echo '<td><a href="' . admin_url('admin.php?page=edit_account&id=' . $account->id) . '">Edit</a></td>';
            echo '</tr>';
        }
        echo '</table>';
    } else {
        echo '<div>No credentials found.</div>';
    }
}

// Логика для логина клиента
function handle_client_login() {
    global $wpdb;

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $steam_login = isset($_POST['steam_login']) ? sanitize_text_field($_POST['steam_login']) : '';
        $steam_password = isset($_POST['steam_password']) ? sanitize_text_field($_POST['steam_password']) : '';

        // Проверка учетных данных
        $table_name = $wpdb->prefix . 'steam_credentials';
        $result = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM $table_name WHERE steam_login = %s AND steam_password = %s",
                $steam_login,
                $steam_password
            )
        );

        if ($result) {
            // Если данные верны, выводим информацию о почте и пароле
            echo '<div style="color: green;">Login successful!</div>';
            echo '<p>Your email password is: <strong>' . esc_html($result->email_password) . '</strong></p>';
        } else {
            echo '<div style="color: red;">Invalid login or password. Please try again.</div>';
        }
    }

    // Формы для ввода логина и пароля Steam
    echo '<form method="post">';
    echo '<p>Steam Login:</p>';
    echo '<input type="text" name="steam_login" required />';
    echo '<p>Steam Password:</p>';
    echo '<input type="password" name="steam_password" required />';
    echo '<input type="submit" value="Login" />';
    echo '</form>';
    exit;
}
