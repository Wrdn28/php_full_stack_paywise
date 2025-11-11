<?php
session_start();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistem Dalam Pemeliharaan</title>
    <link rel="stylesheet" href="style/maintenance.css">
</head>
<body>
    <div class="maintenance-container">
        <div class="maintenance-icon">
            <i class="fas fa-tools"></i>
        </div>
        
        <h1 class="maintenance-title">Sistem Dalam Pemeliharaan</h1>
        
        <p class="maintenance-message">
            Maaf, sistem sedang dalam proses pemeliharaan untuk pengalaman yang lebih baik. 
            Silakan coba lagi dalam beberapa saat.
        </p>
        
        <div class="admin-contact">
            <p>Untuk pertanyaan darurat, hubungi:</p>
            <a href="mailto:admin@dompetku.com" class="contact-email">
                <i class="fas fa-envelope"></i>
                admin@dompetku.com
            </a>
        </div>
        
        <?php if (isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true): ?>
        <div style="margin-top: 20px; padding: 15px; background: #dcfce7; border-radius: 8px;">
            <p style="color: #166534; margin-bottom: 10px;">
                <i class="fas fa-shield-alt"></i>
                <strong>Admin Access</strong>
            </p>
            <a href="admin-dashboard.php" style="color: #059669; text-decoration: none; font-weight: 600;">
                <i class="fas fa-cog"></i>
                Akses Dashboard Admin
            </a>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>