<?php
// Check if the request is a POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get the JSON content from the request body
    $jsonData = file_get_contents('php://input');

    // You can decode the JSON data if you need to process it further in PHP
    // $data = json_decode($jsonData, true);

    // Here, we are just logging the data to a file for simplicity
    file_put_contents('analytics_log.txt', $jsonData . PHP_EOL, FILE_APPEND);
}
?>
