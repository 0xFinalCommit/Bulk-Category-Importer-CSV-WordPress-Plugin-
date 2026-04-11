<?php
/*
Plugin Name: Category Importer CSV Pro
Description: Import hierarchical categories (parent-child-subchild) from CSV with validation and reporting
Version: 1.0
*/

if (!defined('ABSPATH')) exit;

// Admin Menu
add_action('admin_menu', function() {
    add_menu_page(
        'Category CSV Importer',
        'Category Importer',
        'manage_options',
        'category-csv-importer',
        'ci_csv_page',
        'dashicons-upload'
    );
});

// UI Page
function ci_csv_page() {
?>
<div class="wrap">
    <h1>Category CSV Importer</h1>

    <form method="post" enctype="multipart/form-data">
        <?php wp_nonce_field('ci_import_nonce', 'ci_nonce'); ?>

        <input type="file" name="csv_file" accept=".csv" required>
        <p><small>Upload CSV with format: parent, child, subchild</small></p>

        <input type="submit" name="upload" class="button button-primary" value="Import CSV">
    </form>
</div>
<?php

if (isset($_POST['upload'])) {

    // Security check
    if (!isset($_POST['ci_nonce']) || !wp_verify_nonce($_POST['ci_nonce'], 'ci_import_nonce')) {
        echo "<div class='error'><p>Security check failed</p></div>";
        return;
    }

    ci_process_csv($_FILES['csv_file']);
}
}

// Process CSV
function ci_process_csv($file) {

    // File validation
    $file_type = wp_check_filetype($file['name']);
    if ($file_type['ext'] !== 'csv') {
        echo "<div class='error'><p>Error Only CSV files allowed</p></div>";
        return;
    }

    $handle = fopen($file['tmp_name'], 'r');

    if (!$handle) {
        echo "<div class='error'><p>Error Unable to open file</p></div>";
        return;
    }

    $created = 0;
    $skipped = 0;
    $failed  = 0;

    $first = true;

    while (($row = fgetcsv($handle, 1000, ",")) !== FALSE) {

        // Skip header
        if ($first) {
            $first = false;
            continue;
        }

        $parent   = sanitize_title($row[0] ?? '');
        $child    = sanitize_title($row[1] ?? '');
        $subchild = sanitize_title($row[2] ?? '');

        if (!$parent) {
            $failed++;
            continue;
        }

        // Parent
        $parent_term = term_exists($parent, 'category');

        if (!$parent_term) {
            $parent_term = wp_insert_term(
                ucwords(str_replace('-', ' ', $parent)),
                'category'
            );

            if (is_wp_error($parent_term)) {
                $failed++;
                continue;
            }

            $created++;
        } else {
            $skipped++;
        }

        $parent_id = is_array($parent_term) ? $parent_term['term_id'] : $parent_term;

        // Child
        if ($child) {

            $child_term = term_exists($child, 'category');

            if (!$child_term) {
                $child_term = wp_insert_term(
                    ucwords(str_replace('-', ' ', $child)),
                    'category',
                    ['parent' => $parent_id]
                );

                if (is_wp_error($child_term)) {
                    $failed++;
                    continue;
                }

                $created++;
            } else {
                $skipped++;
            }

            $child_id = is_array($child_term) ? $child_term['term_id'] : $child_term;

            // Subchild
            if ($subchild) {

                if (!term_exists($subchild, 'category')) {

                    $sub = wp_insert_term(
                        ucwords(str_replace('-', ' ', $subchild)),
                        'category',
                        ['parent' => $child_id]
                    );

                    if (is_wp_error($sub)) {
                        $failed++;
                        continue;
                    }

                    $created++;
                } else {
                    $skipped++;
                }
            }
        }
    }

    fclose($handle);

    // Report
    echo "<div class='updated'>";
    echo "<p>Created: $created</p>";
    echo "<p>Skipped: $skipped</p>";
    echo "<p>Failed: $failed</p>";
    echo "</div>";
}