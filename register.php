<?php
session_start();

if (isset($_SESSION['user_id'])) {
    header("Location: beranda.php");
    exit();
}

require 'config/mysql-connection.php';

$error = "";
$toast_message = "";
$toast_type = "";

if (isset($_SESSION['toast_message'])) {
    $toast_message = $_SESSION['toast_message'];
    $toast_type = $_SESSION['toast_type'];
    $error = $_SESSION['error'] ?? "";
    unset($_SESSION['toast_message']);
    unset($_SESSION['toast_type']);
    unset($_SESSION['error']);
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);
    $confirm = trim($_POST['confirm_password']);

    // Validasi input
    if (empty($email) || empty($password) || empty($confirm)) {
        $_SESSION['toast_message'] = "Semua field wajib diisi.";
        $_SESSION['toast_type'] = "error";
        $_SESSION['error'] = "Semua field wajib diisi.";
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }

    // Validasi email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['toast_message'] = "Format email tidak valid.";
        $_SESSION['toast_type'] = "error";
        $_SESSION['error'] = "Format email tidak valid.";
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }

    // Validasi password strength
    if (strlen($password) < 6) {
        $_SESSION['toast_message'] = "Password minimal 6 karakter.";
        $_SESSION['toast_type'] = "error";
        $_SESSION['error'] = "Password minimal 6 karakter.";
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }

    if ($password !== $confirm) {
        $_SESSION['toast_message'] = "Password dan konfirmasi tidak cocok.";
        $_SESSION['toast_type'] = "error";
        $_SESSION['error'] = "Password dan konfirmasi tidak cocok.";
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }

    try {
        $check_stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $check_stmt->bind_param("s", $email);
        $check_stmt->execute();
        $check_stmt->store_result();
        
        if ($check_stmt->num_rows > 0) {
            $_SESSION['toast_message'] = "Email sudah terdaftar!";
            $_SESSION['toast_type'] = "error";
            $_SESSION['error'] = "Email sudah terdaftar!";
            $check_stmt->close();
            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        }
        $check_stmt->close();

        // Hash password dan simpan user
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("INSERT INTO users (email, password, created_at) VALUES (?, ?, NOW())");
        
        if ($stmt === false) {
            throw new Exception("Prepare failed: " . $conn->error);
        }

        $stmt->bind_param("ss", $email, $hashed);

        if ($stmt->execute()) {
            $_SESSION['toast_message'] = "Registrasi berhasil! Silakan login.";
            $_SESSION['toast_type'] = "success";
            $stmt->close();
            header("Location: login.php");
            exit();
        } else {
            throw new Exception("Gagal mendaftar: " . $stmt->error);
        }

    } catch (Exception $e) {
        $_SESSION['toast_message'] = "Terjadi kesalahan sistem. Silakan coba lagi.";
        $_SESSION['toast_type'] = "error";
        $_SESSION['error'] = $e->getMessage();
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
}

// cek maintenance
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
    <title>Register - <?= htmlspecialchars($app_name) ?></title>
    <link rel="stylesheet" href="./style/login.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="login-wrapper">
        <div class="login-container">
            <div class="login-header">
                <div class="logo">
                    <i class="fas fa-wallet"></i>
                    <h1 class="login-title"><?= htmlspecialchars($app_name) ?></h1>
                </div>
                <p class="login-subtitle">Buat akun baru untuk mulai mencatat keuangan</p>
            </div>

            <form method="POST" class="login-form">
                <div class="input-group">
                    <div class="input-icon">
                        <i class="fas fa-envelope"></i>
                    </div>
                    <input type="email" name="email" placeholder="Masukkan email Anda" value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>" required>
                </div>

                <div class="input-group">
                    <div class="input-icon">
                        <i class="fas fa-lock"></i>
                    </div>
                    <input type="password" name="password" placeholder="Buat password (min. 6 karakter)" required>
                    <button type="button" class="password-toggle" onclick="togglePassword('password')">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>

                <div class="input-group">
                    <div class="input-icon">
                        <i class="fas fa-lock"></i>
                    </div>
                    <input type="password" name="confirm_password" placeholder="Konfirmasi password" required>
                    <button type="button" class="password-toggle" onclick="togglePassword('confirm_password')">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>

                <button type="submit" class="login-button">
                    <i class="fas fa-user-plus"></i>
                    <span>Daftar Sekarang</span>
                </button>
            </form>

            <div class="login-footer">
                <p>Sudah punya akun? <a href="login.php">Masuk Sekarang</a></p>
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
        function togglePassword(fieldName) {
            const passwordInput = document.querySelector(`input[name="${fieldName}"]`);
            const toggleIcon = passwordInput.parentNode.querySelector('.password-toggle i');
            
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

        // Auto show toast on page load
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

            // Password validastion
            const passwordInput = document.querySelector('input[name="password"]');
            const confirmInput = document.querySelector('input[name="confirm_password"]');
            
            function validatePasswords() {
                if (passwordInput.value !== confirmInput.value && confirmInput.value !== '') {
                    confirmInput.style.borderColor = '#e74c3c';
                } else {
                    confirmInput.style.borderColor = '';
                }
            }
            
            passwordInput.addEventListener('input', validatePasswords);
            confirmInput.addEventListener('input', validatePasswords);
        });
    </script>
</body>
</html>