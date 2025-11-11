<?php
session_start();

// Koneksi
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
    $email = $db->real_escape_string($_POST['username']);
    $password = $_POST['password'];

    if ($email === 'admin@gmail.com' || $email === 'admin@paywise.com') {
        $query = $db->query("SELECT * FROM users WHERE email='$email' LIMIT 1");

        if ($query->num_rows === 1) {
            $user = $query->fetch_assoc();

            if (password_verify($password, $user['password'])) {
                $_SESSION['admin_id'] = $user['id'];
                $_SESSION['admin_email'] = $user['email'];
                $_SESSION['is_admin'] = true;

                header("Location: admin-dashboard.php");
                exit();
            } else {
                $error = "Password salah.";
                $_SESSION['toast_message'] = "Password salah.";
                $_SESSION['toast_type'] = "error";
                header("Location: " . $_SERVER['PHP_SELF']);
                exit();
            }
        } else {
            $error = "Email admin tidak ditemukan.";
            $_SESSION['toast_message'] = "Email admin tidak ditemukan.";
            $_SESSION['toast_type'] = "error";
            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        }
    } else {
        $error = "Hanya admin yang dapat login di sini.";
        $_SESSION['toast_message'] = "Hanya admin yang dapat login di sini.";
        $_SESSION['toast_type'] = "error";
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
}

$app_name_result = $conn->query("SELECT config_value FROM system_config WHERE config_key = 'app_name'");
$app_name = $app_name_result->fetch_assoc()['config_value'] ?? 'DOMPETKU';

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - <?= htmlspecialchars($app_name) ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="./style/admin-login.css">
</head>
<body>

<div class="login-container">
    <h2 class="login-title">
        <i class="fas fa-shield-alt"></i>
        ADMIN <?= strtoupper(htmlspecialchars($app_name)) ?>
    </h2>
    <p class="login-subtitle">Login untuk mengelola sistem keuangan</p>

    <div class="admin-badge">
        <i class="fas fa-user-shield"></i>
        Administrator Access
    </div>

    <form method="POST" class="login-form">
        <div class="input-group">
            <label><i class="fas fa-envelope"></i> Email Admin</label>
            <div class="input-wrapper">
                <i class="fas fa-user input-icon"></i>
                <input type="text" name="username" placeholder="admin@dompetku.com" required>
            </div>
        </div>

        <div class="input-group">
            <label><i class="fas fa-lock"></i> Password</label>
            <div class="input-wrapper">
                <i class="fas fa-key input-icon"></i>
                <input type="password" name="password" placeholder="Masukkan password admin" required id="password">
                <button type="button" class="password-toggle" onclick="togglePassword()">
                    <i class="fas fa-eye"></i>
                </button>
            </div>
        </div>

        <button type="submit" class="login-button">
            <i class="fas fa-sign-in-alt"></i>
            Login Admin
        </button>
    </form>

    <div class="security-notice">
        <p><i class="fas fa-info-circle"></i> Hanya untuk administrator yang berwenang</p>
    </div>

    <p class="register-link">User biasa? <a href="login.php">Login User</a></p>
</div>

<!-- Toast Notification -->
<div id="toast" class="toast <?= $toast_type ?>" style="display: none;">
    <div class="toast-content">
        <div class="toast-icon">
            <?php if ($toast_type === 'success'): ?>
                <i class="fas fa-check"></i>
            <?php else: ?>
                <i class="fas fa-exclamation-triangle"></i>
            <?php endif; ?>
        </div>
        <div class="toast-message"><?= $toast_message ?></div>
        <button class="toast-close" onclick="hideToast()">
            <i class="fas fa-times"></i>
        </button>
    </div>
</div>

<script>
    function showToast() {
        const toast = document.getElementById('toast');
        if (toast && '<?= $toast_message ?>' !== '') {
            toast.style.display = 'block';
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
            setTimeout(() => {
                toast.style.display = 'none';
            }, 500);
        }
    }

    function togglePassword() {
        const passwordInput = document.getElementById('password');
        const toggleButton = document.querySelector('.password-toggle i');
        
        if (passwordInput.type === 'password') {
            passwordInput.type = 'text';
            toggleButton.classList.remove('fa-eye');
            toggleButton.classList.add('fa-eye-slash');
        } else {
            passwordInput.type = 'password';
            toggleButton.classList.remove('fa-eye-slash');
            toggleButton.classList.add('fa-eye');
        }
    }
    
    document.addEventListener('DOMContentLoaded', function() {
        showToast();
        
        document.querySelector('input[name="username"]').focus();
        
        document.addEventListener('click', function(event) {
            const toast = document.getElementById('toast');
            if (toast && !toast.contains(event.target)) {
                hideToast();
            }
        });

        document.querySelector('input[name="password"]').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                document.querySelector('.login-button').click();
            }
        });
    });
</script>

</body>
</html>