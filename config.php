<?php

$user_id  = null;
$api_key  = null;

@include 'local_config.php';

if (!function_exists('readline')) {
    function readline($prompt = '')
    {
        echo $prompt;
        return rtrim(fgets(STDIN), "\n");
    }
}

if (!$user_id) $user_id = readline('User Id: ');
if (!$api_key) $api_key = readline('API Key: ');