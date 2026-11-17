<?php
// Database settings can be overridden with environment variables.
$connect = mysqli_connect(
    getenv('DB_HOST') ?: "localhost:3306",
    getenv('DB_USER') ?: "root",
    getenv('DB_PASS') !== false ? getenv('DB_PASS') : "123456",
    getenv('DB_NAME') ?: "barang"
) or die("failed...");
