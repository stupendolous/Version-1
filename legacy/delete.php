<?php
include __DIR__ . '/../config.php';
$kod_produk = $_GET['kod_produk'] ?? '';
$stmt = mysqli_prepare($connect, "DELETE FROM jadualmainan WHERE kod_produk = ?");
mysqli_stmt_bind_param($stmt, 's', $kod_produk);
mysqli_stmt_execute($stmt);

header("Location: index.php");
