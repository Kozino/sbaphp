<?php
/*
 * SITE CONFIGURATION — the only file you normally edit on the server.
 *
 * On cPanel: open File Manager → private/config.php, fill in the database
 * details from cPanel → MySQL® Databases, then save. (You may also set the
 * same values as environment variables.)
 */
return [
    // Database (cPanel → MySQL Databases: create DB + user, add user to DB, grant ALL)
    'db_host' => getenv('SBA_DB_HOST') ?: 'localhost',
    'db_port' => (int)(getenv('SBA_DB_PORT') ?: 3306),
    'db_name' => getenv('SBA_DB_NAME') ?: 'CPANELUSER_sba',
    'db_user' => getenv('SBA_DB_USER') ?: 'CPANELUSER_sba',
    'db_pass' => getenv('SBA_DB_PASS') ?: 'CHANGE_ME',

    // Chatbot (optional). Free key from console.groq.com. Leave '' to disable.
    'groq_api_key' => getenv('GROQ_API_KEY') ?: '',

    // Used ONLY when the database has no admin yet (first visit).
    // Log in and change the password right away (Admin → Password).
    'default_admin_password' => 'sba12admin',

    // Folders (relative to this file). Keep private/ OUTSIDE public_html on cPanel.
    'upload_dir' => __DIR__ . '/../public_html/uploads',   // public images
    'document_dir' => __DIR__ . '/documents',              // private documents (served by /file.php only)
];
