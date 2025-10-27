<?php

namespace FluentCart\App\Hooks\CLI;

use FluentCart\Framework\Support\Arr;
use WP_CLI;

if (!defined('WP_CLI') || !WP_CLI) {
    return;
}

class Fluent_Cart_DB_Command
{

    /**
     * Update database credentials in wp-config.php.
     *
     * ## OPTIONS
     *
     * <db_name>
     * : The new database name.
     *
     * <db_user>
     * : The new database user.
     *
     * <db_password>
     * : The new database password.
     *
     * [<db_host>]
     * : The new database host (optional, defaults to 'localhost').
     *
     * ## EXAMPLES
     *
     *     wp fluent-cart update-db new_db new_user new_pass
     *
     * @when after_wp_load
     */
    public static function update_db($connectionNo)
    {
        $connectionNo -= 1;

        $connections = [
            [
                'db_name'     => 'wp',
                'db_user'     => 'root',
                'db_password' => 'password',
                'db_host'     => 'localhost'
            ],

            [
                'db_name'     => 'wp_test',
                'db_user'     => 'root',
                'db_password' => 'password',
                'db_host'     => 'localhost'
            ]
        ];

        $dbConfig = Arr::get($connections, $connectionNo, $connections[0]);

        $db_name = Arr::get($dbConfig, 'db_name');
        $db_user = Arr::get($dbConfig, 'db_user');
        $db_password = Arr::get($dbConfig, 'db_password');
        $db_host = Arr::get($dbConfig, 'db_host');


        $wp_config_path = ABSPATH . 'wp-config.php';

        $mysqli = new \mysqli($db_host, $db_user, $db_password, $db_name);

        if ($mysqli->connect_error) {
            //WP_CLI::error("Failed to connect to the new database: " . $mysqli->connect_error);
            return;
        }

        $new_site_url = site_url();

        $queries = [
            "UPDATE wp_options SET option_value = '$new_site_url' WHERE option_name = 'siteurl'",
            "UPDATE wp_options SET option_value = '$new_site_url' WHERE option_name = 'home'"
        ];

        foreach ($queries as $query) {
            if (!$mysqli->query($query)) {
                echo "Failed to update site URL: " . $mysqli->error;
                //WP_CLI::warning("Failed to update site URL: " . $mysqli->error);
            }
        }

        $mysqli->close();


        if (!file_exists($wp_config_path)) {
            echo('wp-config.php not found!');
            return;
        }

        $config_content = file_get_contents($wp_config_path);

        $patterns = [
            "/define\( 'DB_NAME', '(.*?)' \);/",
            "/define\( 'DB_USER', '(.*?)' \);/",
            "/define\( 'DB_PASSWORD', '(.*?)' \);/",
            "/define\( 'DB_HOST', '(.*?)' \);/",
        ];

        $replacements = [
            "define( 'DB_NAME', '$db_name' );",
            "define( 'DB_USER', '$db_user' );",
            "define( 'DB_PASSWORD', '$db_password' );",
            "define( 'DB_HOST', '$db_host' );",
        ];

        $config_content = preg_replace($patterns, $replacements, $config_content);

//        $site_url = $new_site_url; // Change this dynamically if needed
//        if (!preg_match("/define\( 'WP_HOME',/", $config_content)) {
//            $config_content .= "\ndefine( 'WP_HOME', '$site_url' );";
//        }
//        if (!preg_match("/define\( 'WP_SITEURL',/", $config_content)) {
//            $config_content .= "\ndefine( 'WP_SITEURL', '$site_url' );";
//        }


        // Save the modified content back to wp-config.php
        if (file_put_contents($wp_config_path, $config_content)) {
            echo("Database connection updated successfully in wp-config.php!");
        } else {
            echo("Failed to update wp-config.php!");
        }
    }
}