<?php
session_start();

if (isset($_SESSION['user_id'])) {
    header("Location: beranda.php");
    exit();
}

// Koneksi database
require_once "config/mysql-connection.php";

$db = isset($conn) ? $conn : (isset($koneksi) ? $koneksi : null);
if (!$db) die("Koneksi database tidak ditemukan!");

$error = '';
$toast_message = "";
$toast_type = "";

if (isset($_SESSION['toast_message'])) {
    $toast_message = $_SESSION['toast_message'];
    $toast_type = $_SESSION['toast_type'];
    unset($_SESSION['toast_message']);
    unset($_SESSION['toast_type']);
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($db->real_escape_string($_POST['username']));
    $password = $_POST['password'];

    // Validasi input
    if (empty($email) || empty($password)) {
        $error = "Email dan password harus diisi.";
        $_SESSION['toast_message'] = $error;
        $_SESSION['toast_type'] = "error";
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }

    $stmt = $db->prepare("SELECT id, email, password FROM users WHERE email = ? LIMIT 1");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();

        if (password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['email'] = $user['email'];

            session_regenerate_id(true);

            header("Location: beranda.php");
            exit();
        } else {
            $error = "Password salah.";
            $_SESSION['toast_message'] = $error;
            $_SESSION['toast_type'] = "error";
        }
    } else {
        $error = "Email tidak ditemukan.";
        $_SESSION['toast_message'] = $error;
        $_SESSION['toast_type'] = "error";
    }

    $stmt->close();
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// cek maintenance mode
$maintenance_result = $conn->query("SELECT config_value FROM system_config WHERE config_key = 'maintenance_mode'");
$maintenance_mode = $maintenance_result->fetch_assoc()['config_value'] ?? '0';

if ($maintenance_mode === '1' && !(isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true)) {
    header("Location: maintenance.php");
    exit();
}

$app_name_result = $conn->query("SELECT config_value FROM system_config WHERE config_key = 'app_name'");
$app_name = $app_name_result->fetch_assoc()['config_value'] ?? 'DOMPETKU';

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?= htmlspecialchars($app_name) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="./style/login.css">
</head>
<body>
    <div class="login-wrapper">
        <div class="login-container">
            <div class="login-header">
                <div class="logo">
                    <i class="fas fa-wallet"></i>
                    <h1 class="login-title"><?= htmlspecialchars($app_name) ?></h1>
                </div>
                <p class="login-subtitle">Kelola keuangan Anda dengan mudah dan aman</p>
            </div>

            <form method="POST" class="login-form">
                <div class="input-group">
                    <div class="input-icon">
                        <i class="fas fa-envelope"></i>
                    </div>
                    <input type="email" name="username" placeholder="Masukkan email Anda" value="<?= isset($_POST['username']) ? htmlspecialchars($_POST['username']) : '' ?>" required>
                </div>

                <div class="input-group">
                    <div class="input-icon">
                        <i class="fas fa-lock"></i>
                    </div>
                    <input type="password" name="password" placeholder="Masukkan password Anda" required>
                    <button type="button" class="password-toggle" onclick="togglePassword()">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>

                <button type="submit" class="login-button">
                        <i class="fas fa-sign-in-alt"></i>
                        Login User
                    </button>
                </form>

                <div class="admin-login-section">
                    <div class="divider">
                        <span>atau</span>
                    </div>
                    <a href="admin-login.php" class="admin-login-btn">
                        <i class="fas fa-shield-alt"></i>
                        Login sebagai Admin
                    </a>
                </div>

            <div class="login-footer">
                <p>Belum punya akun? <a href="register.php">Daftar Sekarang</a></p>
            </div>
        </div>
    </div>

    <!-- Toast Notification -->
    <?php if (!empty($toast_message)): ?>
    <div id="toast" class="toast <?= $toast_type ?>">
        <div class="toast-content">
            <div class="toast-icon">
                <?php if ($toast_type === 'success'): ?>
                    <i class="fas fa-check-circle"></i>
                <?php else: ?>
                    <i class="fas fa-exclamation-triangle"></i>
                <?php endif; ?>
            </div>
            <div class="toast-message"><?= htmlspecialchars($toast_message) ?></div>
            <button class="toast-close" onclick="hideToast()">
                <i class="fas fa-times"></i>
            </button>
        </div>
    </div>
    <?php endif; ?>

    <script>
        // Password toggle functionality
        function togglePassword() {
            const passwordInput = document.querySelector('input[name="password"]');
            const toggleIcon = document.querySelector('.password-toggle i');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleIcon.className = 'fas fa-eye-slash';
            } else {
                passwordInput.type = 'password';
                toggleIcon.className = 'fas fa-eye';
            }
        }

        // Toast notification
        function showToast() {
            const toast = document.getElementById('toast');
            if (toast) {
                setTimeout(() => {
                    toast.classList.add('show');
                }, 100);
                
                setTimeout(() => {
                    hideToast();
                }, 5000);
            }
        }

        function hideToast() {
            const toast = document.getElementById('toast');
            if (toast) {
                toast.classList.remove('show');
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            showToast();
            
            document.addEventListener('click', function(event) {
                const toast = document.getElementById('toast');
                if (toast && !toast.contains(event.target)) {
                    hideToast();
                }
            });

            if (window.history.replaceState) {
                window.history.replaceState(null, null, window.location.href);
            }

            const form = document.querySelector('.login-form');
            form.addEventListener('submit', function() {
                const button = this.querySelector('.login-button');
                button.innerHTML = '<i class="fas fa-spinner fa-spin"></i><span>Memproses...</span>';
                button.disabled = true;
            });
        });
    </script>
</body>
</html>