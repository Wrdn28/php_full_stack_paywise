<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require 'config/mysql-connection.php';

$user_id = $_SESSION['user_id'];
$success_msg = '';
$error_msg = '';

// Handle Update Profile
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_profile'])) {
    $email = trim($_POST['email']);
    
    // Check if email already exists (excluding current user)
    $check_stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
    $check_stmt->bind_param("si", $email, $user_id);
    $check_stmt->execute();
    
    if ($check_stmt->get_result()->num_rows > 0) {
        $error_msg = "Email sudah digunakan oleh user lain!";
    } else {
        $update_stmt = $conn->prepare("UPDATE users SET email = ? WHERE id = ?");
        $update_stmt->bind_param("si", $email, $user_id);
        
        if ($update_stmt->execute()) {
            $_SESSION['email'] = $email;
            $success_msg = "Profil berhasil diperbarui!";
        } else {
            $error_msg = "Gagal memperbarui profil!";
        }
        $update_stmt->close();
    }
    $check_stmt->close();
}

// Handle Change Password
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Verify current password
    $user_stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
    $user_stmt->bind_param("i", $user_id);
    $user_stmt->execute();
    $user_result = $user_stmt->get_result();
    $user = $user_result->fetch_assoc();
    $user_stmt->close();
    
    if (!password_verify($current_password, $user['password'])) {
        $error_msg = "Password saat ini salah!";
    } elseif ($new_password !== $confirm_password) {
        $error_msg = "Password baru tidak cocok!";
    } elseif (strlen($new_password) < 6) {
        $error_msg = "Password minimal 6 karakter!";
    } else {
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $pass_stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
        $pass_stmt->bind_param("si", $hashed_password, $user_id);
        
        if ($pass_stmt->execute()) {
            $success_msg = "Password berhasil diubah!";
        } else {
            $error_msg = "Gagal mengubah password!";
        }
        $pass_stmt->close();
    }
}

// Get user data
$user_stmt = $conn->prepare("SELECT email, created_at FROM users WHERE id = ?");
$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();
$user_result = $user_stmt->get_result();
$user_data = $user_result->fetch_assoc();
$user_stmt->close();

// Check maintenance mode
$maintenance_result = $conn->query("SELECT config_value FROM system_config WHERE config_key = 'maintenance_mode'");
$maintenance_mode = $maintenance_result->fetch_assoc()['config_value'] ?? '0';

if ($maintenance_mode === '1') {
    header("Location: maintenance.php");
    exit();
}

// Get app name from config
$app_name_result = $conn->query("SELECT config_value FROM system_config WHERE config_key = 'app_name'");
$app_name = $app_name_result->fetch_assoc()['config_value'] ?? 'DOMPETKU';

?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pengaturan - <?= htmlspecialchars($app_name) ?></title>
    <link rel="stylesheet" href="./style/beranda.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <!-- Sidebar Navigation -->
    <nav class="sidebar">
        <div class="sidebar-header">
            <div class="logo">
                <i class="fas fa-wallet"></i>
                <span class="login-title"><?= htmlspecialchars($app_name) ?></span>
            </div>
            <button class="sidebar-toggle" onclick="toggleSidebar()">
                <i class="fas fa-bars"></i>
            </button>
        </div>
        
        <div class="sidebar-menu">
            <a href="beranda.php" class="menu-item">
                <i class="fas fa-home"></i>
                <span>Dashboard</span>
            </a>
            <a href="pengaturan.php" class="menu-item active">
                <i class="fas fa-cog"></i>
                <span>Pengaturan</span>
            </a>
        </div>

        <div class="sidebar-footer">
            <div class="user-info">
                <div class="user-avatar">
                    <i class="fas fa-user"></i>
                </div>
                <div class="user-details">
                    <span class="user-name"><?= htmlspecialchars($_SESSION['email']) ?></span>
                    <span class="user-status">Online</span>
                </div>
            </div>
            <a href="logout.php" class="logout-btn">
                <i class="fas fa-sign-out-alt"></i>
            </a>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="main-content">
        <header class="content-header">
            <div class="header-title">
                <h1>Pengaturan Akun</h1>
                <p>Kelola profil dan pengaturan akun Anda</p>
            </div>
        </header>

        <!-- Notifications -->
        <?php if ($success_msg): ?>
            <div class="toast toast-success">
                <div class="toast-content">
                    <i class="fas fa-check-circle"></i>
                    <span><?= $success_msg ?></span>
                </div>
                <button class="toast-close" onclick="this.parentElement.remove()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        <?php endif; ?>

        <?php if ($error_msg): ?>
            <div class="toast toast-error">
                <div class="toast-content">
                    <i class="fas fa-exclamation-triangle"></i>
                    <span><?= $error_msg ?></span>
                </div>
                <button class="toast-close" onclick="this.parentElement.remove()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        <?php endif; ?>

        <div class="settings-grid">
            <!-- Profile Settings -->
            <div class="settings-card">
                <div class="card-header">
                    <h3><i class="fas fa-user-edit"></i> Profil Saya</h3>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="update_profile" value="1">
                        
                        <div class="form-group">
                            <label>Email Address</label>
                            <input type="email" name="email" value="<?= htmlspecialchars($user_data['email']) ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label>Tanggal Bergabung</label>
                            <input type="text" value="<?= date('d M Y', strtotime($user_data['created_at'])) ?>" readonly style="background: #f3f4f6;">
                        </div>
                        
                        <button type="submit" class="btn-primary">
                            <i class="fas fa-save"></i>
                            Simpan Perubahan
                        </button>
                    </form>
                </div>
            </div>

            <!-- Change Password -->
            <div class="settings-card">
                <div class="card-header">
                    <h3><i class="fas fa-lock"></i> Ubah Password</h3>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="change_password" value="1">
                        
                        <div class="form-group">
                            <label>Password Saat Ini</label>
                            <input type="password" name="current_password" required>
                        </div>
                        
                        <div class="form-group">
                            <label>Password Baru</label>
                            <input type="password" name="new_password" placeholder="Minimal 6 karakter" required>
                        </div>
                        
                        <div class="form-group">
                            <label>Konfirmasi Password Baru</label>
                            <input type="password" name="confirm_password" required>
                        </div>
                        
                        <button type="submit" class="btn-primary">
                            <i class="fas fa-key"></i>
                            Ubah Password
                        </button>
                    </form>
                </div>
            </div>

            <!-- Account Info -->
            <div class="settings-card">
                <div class="card-header">
                    <h3><i class="fas fa-info-circle"></i> Informasi Akun</h3>
                </div>
                <div class="card-body">
                    <div class="info-list">
                        <div class="info-item">
                            <span class="info-label">ID Pengguna</span>
                            <span class="info-value">#<?= $user_id ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Status Akun</span>
                            <span class="info-value badge">Aktif</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Bergabung Sejak</span>
                            <span class="info-value"><?= date('d F Y', strtotime($user_data['created_at'])) ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Danger Zone -->
            <div class="settings-card danger-zone">
                <div class="card-header">
                    <h3><i class="fas fa-exclamation-triangle"></i> Zona Berbahaya</h3>
                </div>
                <div class="card-body">
                    <p class="warning-text">
                        <i class="fas fa-exclamation-circle"></i>
                        Tindakan ini tidak dapat dibatalkan. Semua data transaksi Anda akan dihapus permanen.
                    </p>
                    
                    <div class="danger-actions">
                        <button class="btn-danger" onclick="showDeleteConfirmation()">
                            <i class="fas fa-trash"></i>
                            Hapus Akun & Semua Data
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Delete Confirmation -->
    <div id="deleteModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-exclamation-triangle"></i> Konfirmasi Hapus Akun</h3>
            </div>
            <div class="modal-body">
                <p>Apakah Anda yakin ingin menghapus akun dan semua data transaksi?</p>
                <p class="warning-text">Tindakan ini tidak dapat dibatalkan!</p>
                
                <form method="POST" action="hapus-akun.php" class="delete-form">
                    <div class="form-group">
                        <label>Ketik "HAPUS" untuk konfirmasi</label>
                        <input type="text" name="confirm_text" placeholder="HAPUS" required>
                    </div>
                    
                    <div class="modal-actions">
                        <button type="button" class="btn-secondary" onclick="closeDeleteModal()">Batal</button>
                        <button type="submit" class="btn-danger">
                            <i class="fas fa-trash"></i>
                            Ya, Hapus Akun
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function showDeleteConfirmation() {
            document.getElementById('deleteModal').classList.add('show');
        }

        function closeDeleteModal() {
            document.getElementById('deleteModal').classList.remove('show');
        }

        setTimeout(() => {
            document.querySelectorAll('.toast').forEach(toast => {
                toast.style.opacity = '0';
                setTimeout(() => toast.remove(), 300);
            });
        }, 5000);

        document.addEventListener('click', function(e) {
            const modal = document.getElementById('deleteModal');
            if (e.target === modal) {
                closeDeleteModal();
            }
        });
    </script>
</body>
</html>