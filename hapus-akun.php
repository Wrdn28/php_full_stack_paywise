<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require 'config/mysql-connection.php';

$user_id = $_SESSION['user_id'];

if ($_SERVER["REQUEST_METHOD"] == "POST" && $_POST['confirm_text'] === 'HAPUS') {
    $delete_transactions = $conn->prepare("DELETE FROM transaksi WHERE user_id = ?");
    $delete_transactions->bind_param("i", $user_id);
    $delete_transactions->execute();
    $delete_transactions->close();
    
    $delete_user = $conn->prepare("DELETE FROM users WHERE id = ?");
    $delete_user->bind_param("i", $user_id);
    $delete_user->execute();
    $delete_user->close();
    
    session_destroy();
    header("Location: login.php?message=account_deleted");
    exit();
} else {
    header("Location: pengaturan.php?error=invalid_confirmation");
    exit();
}
?>