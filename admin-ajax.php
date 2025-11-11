<?php
session_start();

if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header("HTTP/1.1 403 Forbidden");
    exit();
}

require 'config/mysql-connection.php';

if ($_GET['action'] === 'get_user_transactions') {
    $user_id = (int)$_GET['user_id'];
    
    $transactions = $conn->query("
        SELECT * FROM transaksi 
        WHERE user_id = $user_id 
        ORDER BY tanggal DESC 
        LIMIT 20
    ");
    
    if ($transactions->num_rows > 0) {
        echo '<table class="data-table">';
        echo '<thead><tr><th>Tanggal</th><th>Deskripsi</th><th>Tipe</th><th>Jumlah</th></tr></thead>';
        echo '<tbody>';
        
        while($transaction = $transactions->fetch_assoc()) {
            $color = $transaction['tipe'] === 'pemasukan' ? 'positive' : 'negative';
            echo '<tr>';
            echo '<td>' . date('d M Y', strtotime($transaction['tanggal'])) . '</td>';
            echo '<td>' . htmlspecialchars($transaction['deskripsi']) . '</td>';
            echo '<td>' . ucfirst($transaction['tipe']) . '</td>';
            echo '<td class="' . $color . '">Rp ' . number_format($transaction['jumlah'], 0, ',', '.') . '</td>';
            echo '</tr>';
        }
        
        echo '</tbody></table>';
    } else {
        echo '<p>No transactions found for this user.</p>';
    }
}
?>
