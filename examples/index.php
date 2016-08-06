<?php
/**
 * Copyright 2016 Tom Walder
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

// Auto loader
require('../vendor/autoload.php');

// Start the session
GDS\Session\Handler::start();

// Gather some display data
$str_now = date('Y-m-d H:i:s');
$str_ip = $_SERVER['REMOTE_ADDR'];

// Update the session
if(!isset($_SESSION['first_seen'])) {
    $_SESSION['first_seen'] = $str_now;
    $_SESSION['first_seen_ip'] = $str_ip;
}

?><!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>GDS Session Demo</title>
        <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css" integrity="sha384-BVYiiSIFeK1dGmJRAkycuHAHRg32OmUcww7on3RYdg4Va+PmSTsz/K68vbdEjh4u" crossorigin="anonymous">
    </head>
    <body>
        <div class="container">
            <div class="row">
                <div class="col-xs-12">
                    <h1>GDS Session Demo</h1>
                    <p><a href="https://github.com/tomwalder/php-gds-session">https://github.com/tomwalder/php-gds-session</a></p>
                    <h2>Session Name</h2>
                    <pre><?php echo session_name(); ?></pre>
                    <h2>Session ID</h2>
                    <pre><?php echo session_id(); ?></pre>
                    <h2>Session Data</h2>
                    <pre><?php print_r($_SESSION); ?></pre>
                </div>
            </div>
        </div>
    </body>
</html>
<?php

// And finally record that we've just been seen
$_SESSION['last_seen'] = $str_now;
$_SESSION['last_seen_ip'] = $str_ip;

