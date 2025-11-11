<?php
session_start();

// Cek jika user adalah admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header("Location: admin-login.php");
    exit();
}

require 'config/mysql-connection.php';

// Converter Rupiah
function format_rupiah($angka) {
    return 'Rp ' . number_format($angka, 0, ',', '.');
}

function format_tanggal($tanggal) {
    return date('d M Y', strtotime($tanggal));
}

// Filter
$filter_periode = $_GET['periode'] ?? 'all';
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';
$search_user = $_GET['search_user'] ?? '';

$transaction_filter_condition = "";
$transaction_filter_params = [];
$param_types = "";

if ($filter_periode === 'week') {
    $transaction_filter_condition = " AND t.tanggal BETWEEN DATE_SUB(CURDATE(), INTERVAL 1 WEEK) AND CURDATE()";
} elseif ($filter_periode === 'month') {
    $transaction_filter_condition = " AND t.tanggal BETWEEN DATE_SUB(CURDATE(), INTERVAL 1 MONTH) AND CURDATE()";
} elseif ($filter_periode === 'custom' && $start_date && $end_date) {
    $transaction_filter_condition = " AND t.tanggal BETWEEN ? AND ?";
    $transaction_filter_params = [$start_date, $end_date];
    $param_types = "ss";
}

/* Statisic */
$total_users = $conn->query("SELECT COUNT(*) as total FROM users WHERE email != 'admin@dompetku.com'")->fetch_assoc()['total'];
$total_transactions = $conn->query("SELECT COUNT(*) as total FROM transaksi")->fetch_assoc()['total'];
$total_all_pemasukan = $conn->query("SELECT COALESCE(SUM(jumlah), 0) as total FROM transaksi WHERE tipe = 'pemasukan'")->fetch_assoc()['total'];
$total_all_pengeluaran = $conn->query("SELECT COALESCE(SUM(jumlah), 0) as total FROM transaksi WHERE tipe = 'pengeluaran'")->fetch_assoc()['total'];
$total_saldo = $total_all_pemasukan - $total_all_pengeluaran;

/* Manage USer */
// Add user
if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST['action'] ?? '') === 'add_user') {
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);
    
    $check = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $check->bind_param("s", $email);
    $check->execute();
    
    if ($check->get_result()->num_rows > 0) {
        $_SESSION['toast_message'] = "Email sudah terdaftar!";
        $_SESSION['toast_type'] = "error";
    } else {
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("INSERT INTO users (email, password) VALUES (?, ?)");
        $stmt->bind_param("ss", $email, $hashed);
        
        if ($stmt->execute()) {
            $_SESSION['toast_message'] = "User berhasil ditambahkan!";
            $_SESSION['toast_type'] = "success";
        } else {
            $_SESSION['toast_message'] = "Gagal menambah user!";
            $_SESSION['toast_type'] = "error";
        }
        $stmt->close();
    }
    $check->close();
    header("Location: admin-dashboard.php");
    exit();
}

// Reset password user
if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST['action'] ?? '') === 'reset_password') {
    $user_id = (int)($_POST['user_id'] ?? 0);
    $new_password = trim($_POST['new_password']);
    
    if ($user_id > 0 && !empty($new_password)) {
        $hashed = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->bind_param("si", $hashed, $user_id);
        
        if ($stmt->execute()) {
            $_SESSION['toast_message'] = "Password berhasil direset!";
            $_SESSION['toast_type'] = "success";
        } else {
            $_SESSION['toast_message'] = "Gagal reset password!";
            $_SESSION['toast_type'] = "error";
        }
        $stmt->close();
    } else {
        $_SESSION['toast_message'] = "Data tidak valid!";
        $_SESSION['toast_type'] = "error";
    }
    header("Location: admin-dashboard.php?tab=users");
    exit();
}

// Delete user
if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST['action'] ?? '') === 'delete_user') {
    $user_id = (int)($_POST['user_id'] ?? 0);
    
    if ($user_id > 0 && $user_id != $_SESSION['admin_id']) {
        $conn->query("DELETE FROM transaksi WHERE user_id = $user_id");
        $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        
        if ($stmt->execute()) {
            $_SESSION['toast_message'] = "User berhasil dihapus!";
            $_SESSION['toast_type'] = "success";
        } else {
            $_SESSION['toast_message'] = "Gagal menghapus user!";
            $_SESSION['toast_type'] = "error";
        }
        $stmt->close();
    } else {
        $_SESSION['toast_message'] = "Tidak dapat menghapus user ini!";
        $_SESSION['toast_type'] = "error";
    }
    header("Location: admin-dashboard.php");
    exit();
}

/* manage transaksi */

// Delete transaction
if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST['action'] ?? '') === 'delete_transaction') {
    $transaction_id = (int)($_POST['transaction_id'] ?? 0);
    
    if ($transaction_id > 0) {
        $stmt = $conn->prepare("DELETE FROM transaksi WHERE id = ?");
        $stmt->bind_param("i", $transaction_id);
        
        if ($stmt->execute()) {
            $_SESSION['toast_message'] = "Transaksi berhasil dihapus!";
            $_SESSION['toast_type'] = "success";
        } else {
            $_SESSION['toast_message'] = "Gagal menghapus transaksi!";
            $_SESSION['toast_type'] = "error";
        }
        $stmt->close();
    }
    header("Location: admin-dashboard.php?tab=transactions&periode=" . $filter_periode . "&start_date=" . $start_date . "&end_date=" . $end_date);
    exit();
}

// Get config
$config_result = $conn->query("SELECT config_key, config_value FROM system_config");
$config = [];
while ($row = $config_result->fetch_assoc()) {
    $config[$row['config_key']] = $row['config_value'];
}

$app_name = $config['app_name'] ?? 'DOMPETKU';
$admin_email = $config['admin_email'] ?? $_SESSION['admin_email'];
$system_version = $config['system_version'] ?? 'v1.0.0';
$maintenance_mode = $config['maintenance_mode'] ?? '0';

// Update system configuration
if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST['action'] ?? '') === 'update_config') {
    $app_name = trim($_POST['app_name']);
    $admin_email = trim($_POST['admin_email']);
    $maintenance_mode = isset($_POST['maintenance_mode']) ? '1' : '0';
    
    $configs = [
        'app_name' => $app_name,
        'admin_email' => $admin_email,
        'maintenance_mode' => $maintenance_mode
    ];
    
    $success = true;
    foreach ($configs as $key => $value) {
        $stmt = $conn->prepare("INSERT INTO system_config (config_key, config_value) 
                               VALUES (?, ?) 
                               ON DUPLICATE KEY UPDATE config_value = ?");
        $stmt->bind_param("sss", $key, $value, $value);
        if (!$stmt->execute()) {
            $success = false;
        }
        $stmt->close();
    }
    
    if ($success) {
        $_SESSION['toast_message'] = "Konfigurasi sistem berhasil diperbarui!";
        $_SESSION['toast_type'] = "success";
    } else {
        $_SESSION['toast_message'] = "Gagal memperbarui konfigurasi!";
        $_SESSION['toast_type'] = "error";
    }
    header("Location: admin-dashboard.php?tab=config");
    exit();
}

$db_status = $conn->ping() ? "Connected ✅" : "Disconnected ❌";

$app_name_result = $conn->query("SELECT config_value FROM system_config WHERE config_key = 'app_name'");
$app_name = $app_name_result->fetch_assoc()['config_value'] ?? 'DOMPETKU';


// fetch data
// Get all users dengan search
$users_query = "SELECT id, email, created_at FROM users WHERE email != 'admin@dompetku.com'";
if (!empty($search_user)) {
    $users_query .= " AND email LIKE '%" . $conn->real_escape_string($search_user) . "%'";
}
$users_query .= " ORDER BY created_at DESC";
$users = $conn->query($users_query);

// Get all transactions with user info and filter
$transactions_query = "
    SELECT t.*, u.email as user_email 
    FROM transaksi t 
    JOIN users u ON t.user_id = u.id 
    WHERE 1=1" . $transaction_filter_condition . "
    ORDER BY t.tanggal DESC 
    LIMIT 50
";

if ($transaction_filter_params) {
    $transactions_stmt = $conn->prepare($transactions_query);
    $transactions_stmt->bind_param($param_types, ...$transaction_filter_params);
    $transactions_stmt->execute();
    $transactions = $transactions_stmt->get_result();
} else {
    $transactions = $conn->query($transactions_query);
}

// Get user statistics for charts with filter
$user_stats_query = "
    SELECT u.email, 
           COUNT(t.id) as total_transactions,
           COALESCE(SUM(CASE WHEN t.tipe = 'pemasukan' THEN t.jumlah ELSE 0 END), 0) as total_pemasukan,
           COALESCE(SUM(CASE WHEN t.tipe = 'pengeluaran' THEN t.jumlah ELSE 0 END), 0) as total_pengeluaran
    FROM users u 
    LEFT JOIN transaksi t ON u.id = t.user_id 
    WHERE u.email != 'admin@dompetku.com'
    " . $transaction_filter_condition . "
    GROUP BY u.id, u.email
";

if ($transaction_filter_params) {
    $user_stats_stmt = $conn->prepare($user_stats_query);
    $user_stats_stmt->bind_param($param_types, ...$transaction_filter_params);
    $user_stats_stmt->execute();
    $user_stats = $user_stats_stmt->get_result();
} else {
    $user_stats = $conn->query($user_stats_query);
}

// statistik bulanan
$monthly_stats_query = "
    SELECT 
        DATE_FORMAT(tanggal, '%Y-%m') as bulan,
        SUM(CASE WHEN tipe = 'pemasukan' THEN jumlah ELSE 0 END) as pemasukan,
        SUM(CASE WHEN tipe = 'pengeluaran' THEN jumlah ELSE 0 END) as pengeluaran
    FROM transaksi 
    WHERE 1=1" . str_replace('t.', '', $transaction_filter_condition);

if ($transaction_filter_params) {
    $monthly_stats_stmt = $conn->prepare($monthly_stats_query);
    $monthly_stats_stmt->bind_param($param_types, ...$transaction_filter_params);
    $monthly_stats_stmt->execute();
    $monthly_stats = $monthly_stats_stmt->get_result();
} else {
    $monthly_stats = $conn->query($monthly_stats_query . " AND tanggal >= DATE_SUB(NOW(), INTERVAL 6 MONTH)");
}

if (isset($_SESSION['toast_message'])) {
    $toast_message = $_SESSION['toast_message'];
    $toast_type = $_SESSION['toast_type'];
    unset($_SESSION['toast_message']);
    unset($_SESSION['toast_type']);
}

$active_tab = $_GET['tab'] ?? 'users';
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - <?= htmlspecialchars($app_name) ?></title>
    <link rel="stylesheet" href="./style/admin-dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>

<body>
<div class="admin-container">

    <!-- Header -->
    <div class="admin-header">
        <h1><i class="fas fa-cogs"></i> Admin Dashboard <?= htmlspecialchars($app_name) ?></h1>
        <div class="admin-info">
            <span><i class="fas fa-user-shield"></i> <?= $_SESSION['admin_email'] ?></span>
            <a href="admin-logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </div>

    <!-- Toast Notification -->
    <?php if (isset($toast_message)): ?>
        <div id="toast" class="toast <?= $toast_type ?>" style="display: none;">
            <div class="toast-content">
                <div class="toast-icon">
                    <?php if ($toast_type === 'success'): ?>
                        <i class="fas fa-check-circle"></i>
                    <?php else: ?>
                        <i class="fas fa-exclamation-triangle"></i>
                    <?php endif; ?>
                </div>
                <div class="toast-message"><?= $toast_message ?></div>
                <button class="toast-close" onclick="hideToast()">×</button>
            </div>
        </div>
    <?php endif; ?>

    <!-- Statistics Cards -->
    <div class="stats-cards">
        <div class="stat-card gradient-users">
            <div class="stat-icon"><i class="fas fa-users"></i></div>
            <div class="stat-info">
                <h3>Total Users</h3>
                <p><?= $total_users ?></p>
            </div>
        </div>
        
        <div class="stat-card gradient-transactions">
            <div class="stat-icon"><i class="fas fa-exchange-alt"></i></div>
            <div class="stat-info">
                <h3>Total Transaksi</h3>
                <p><?= $total_transactions ?></p>
            </div>
        </div>
        
        <div class="stat-card gradient-income">
            <div class="stat-icon"><i class="fas fa-arrow-up"></i></div>
            <div class="stat-info">
                <h3>Total Pemasukan</h3>
                <p><?= format_rupiah($total_all_pemasukan) ?></p>
            </div>
        </div>
        
        <div class="stat-card gradient-balance">
            <div class="stat-icon"><i class="fas fa-wallet"></i></div>
            <div class="stat-info">
                <h3>Total Saldo</h3>
                <p><?= format_rupiah($total_saldo) ?></p>
            </div>
        </div>
    </div>

    <!-- Main Content Tabs -->
    <div class="admin-tabs">
        <button class="tab-button <?= $active_tab === 'users' ? 'active' : '' ?>" onclick="openTab('users')">
            <i class="fas fa-users-cog"></i> Manage Users
        </button>
        <button class="tab-button <?= $active_tab === 'transactions' ? 'active' : '' ?>" onclick="openTab('transactions')">
            <i class="fas fa-money-bill-wave"></i> Manage Transactions
        </button>
        <button class="tab-button <?= $active_tab === 'reports' ? 'active' : '' ?>" onclick="openTab('reports')">
            <i class="fas fa-chart-bar"></i> Reports & Charts
        </button>
        <button class="tab-button <?= $active_tab === 'config' ? 'active' : '' ?>" onclick="openTab('config')">
            <i class="fas fa-sliders-h"></i> System Config
        </button>
    </div>

    <!-- Users Management Tab -->
    <div id="users" class="tab-content <?= $active_tab === 'users' ? 'active' : '' ?>">
        <div class="section-header">
            <h2><i class="fas fa-users-cog"></i> User Management</h2>
            <div class="header-actions">
                <form method="GET" class="search-form">
                    <input type="hidden" name="tab" value="users">
                    <div class="search-box">
                        <input type="text" name="search_user" value="<?= htmlspecialchars($search_user) ?>" placeholder="Cari user by email...">
                        <button type="submit" class="btn-search">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                </form>
                <button class="btn-primary" onclick="openModal('addUserModal')">
                    <i class="fas fa-user-plus"></i> Add User
                </button>
            </div>
        </div>

        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Email</th>
                        <th>Tanggal Daftar</th>
                        <th>Total Transaksi</th>
                        <th class="actions-cell">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($users->num_rows > 0): ?>
                        <?php while($user = $users->fetch_assoc()): 
                            $user_transactions = $conn->query("SELECT COUNT(*) as total FROM transaksi WHERE user_id = " . $user['id'])->fetch_assoc()['total'];
                        ?>
                        <tr>
                            <td><?= $user['id'] ?></td>
                            <td><?= htmlspecialchars($user['email']) ?></td>
                            <td><?= format_tanggal($user['created_at']) ?></td>
                            <td><?= $user_transactions ?></td>
                            <td class="actions-cell">
                                <div class="action-buttons">
                                    <button class="btn-view" onclick="viewUserTransactions(<?= $user['id'] ?>, '<?= htmlspecialchars($user['email']) ?>')">
                                        <i class="fas fa-eye"></i> View
                                    </button>
                                    <button class="btn-reset" onclick="openResetPasswordModal(<?= $user['id'] ?>, '<?= htmlspecialchars($user['email']) ?>')">
                                        <i class="fas fa-key"></i> Reset
                                    </button>
                                    <button class="btn-delete" onclick="deleteUser(<?= $user['id'] ?>)">
                                        <i class="fas fa-trash"></i> Delete
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="text-center">
                                <i class="fas fa-user-slash empty-icon"></i>
                                <p class="empty-message"><?= empty($search_user) ? 'Belum ada user terdaftar.' : 'User tidak ditemukan.' ?></p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Transactions Management Tab -->
    <div id="transactions" class="tab-content <?= $active_tab === 'transactions' ? 'active' : '' ?>">
        <div class="filter-section">
            <h3><i class="fas fa-filter"></i> Filter Transaksi</h3>
            <form method="GET" class="filter-form">
                <input type="hidden" name="tab" value="transactions">
                <div class="filter-options">
                    <label class="filter-option">
                        <input type="radio" name="periode" value="all" <?= $filter_periode === 'all' ? 'checked' : '' ?> onchange="this.form.submit()">
                        <span class="filter-label">Semua Transaksi</span>
                    </label>
                    <label class="filter-option">
                        <input type="radio" name="periode" value="week" <?= $filter_periode === 'week' ? 'checked' : '' ?> onchange="this.form.submit()">
                        <span class="filter-label">1 Minggu Terakhir</span>
                    </label>
                    <label class="filter-option">
                        <input type="radio" name="periode" value="month" <?= $filter_periode === 'month' ? 'checked' : '' ?> onchange="this.form.submit()">
                        <span class="filter-label">1 Bulan Terakhir</span>
                    </label>
                    <label class="filter-option">
                        <input type="radio" name="periode" value="custom" <?= $filter_periode === 'custom' ? 'checked' : '' ?> id="customRadio" onchange="toggleCustomDate()">
                        <span class="filter-label">Tanggal Custom</span>
                    </label>
                </div>
                
                <div class="custom-date" id="customDate" style="<?= $filter_periode === 'custom' ? 'display: flex;' : 'display: none;' ?>">
                    <input type="date" name="start_date" value="<?= $start_date ?>">
                    <span>s/d</span>
                    <input type="date" name="end_date" value="<?= $end_date ?>">
                    <button type="submit" class="btn-filter"><i class="fas fa-check"></i> Terapkan</button>
                </div>
            </form>
            
            <!-- Summary Cards -->
            <div class="summary-cards">
                <div class="summary-card">
                    <span class="summary-label">Periode Aktif:</span>
                    <span class="summary-value">
                        <?php
                        if ($filter_periode === 'week') {
                            echo '1 Minggu Terakhir';
                        } elseif ($filter_periode === 'month') {
                            echo '1 Bulan Terakhir';
                        } elseif ($filter_periode === 'custom' && $start_date && $end_date) {
                            echo date('d M Y', strtotime($start_date)) . ' - ' . date('d M Y', strtotime($end_date));
                        } else {
                            echo 'Semua Waktu';
                        }
                        ?>
                    </span>
                </div>
                <div class="summary-card">
                    <span class="summary-label">Data Tersedia:</span>
                    <span class="summary-value"><?= $transactions->num_rows ?> transaksi</span>
                </div>
            </div>
        </div>

        <div class="section-header">
            <h2><i class="fas fa-money-bill-wave"></i> Transaction Management</h2>
        </div>

        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>User</th>
                        <th>Tanggal</th>
                        <th>Deskripsi</th>
                        <th>Tipe</th>
                        <th class="number-cell">Jumlah</th>
                        <th class="actions-cell">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($transactions->num_rows > 0): ?>
                        <?php while($transaction = $transactions->fetch_assoc()): ?>
                        <tr>
                            <td><?= $transaction['id'] ?></td>
                            <td><?= htmlspecialchars($transaction['user_email']) ?></td>
                            <td><?= format_tanggal($transaction['tanggal']) ?></td>
                            <td><?= htmlspecialchars($transaction['deskripsi']) ?></td>
                            <td>
                                <span class="badge <?= $transaction['tipe'] === 'pemasukan' ? 'badge-success' : 'badge-danger' ?>">
                                    <?= ucfirst($transaction['tipe']) ?>
                                </span>
                            </td>
                            <td class="number-cell <?= $transaction['tipe'] === 'pemasukan' ? 'positive' : 'negative' ?>">
                                <?= format_rupiah($transaction['jumlah']) ?>
                            </td>
                            <td class="actions-cell">
                                <div class="action-buttons">
                                    <button class="btn-delete" onclick="deleteTransaction(<?= $transaction['id'] ?>)">
                                        <i class="fas fa-trash"></i> Delete
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center">
                                <i class="fas fa-receipt empty-icon"></i>
                                <p class="empty-message">Tidak ada transaksi pada periode yang dipilih.</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Reports & Charts Tab -->
    <div id="reports" class="tab-content <?= $active_tab === 'reports' ? 'active' : '' ?>">
        <div class="filter-section">
            <h3><i class="fas fa-filter"></i> Filter Laporan</h3>
            <form method="GET" class="filter-form">
                <input type="hidden" name="tab" value="reports">
                <div class="filter-options">
                    <label class="filter-option">
                        <input type="radio" name="periode" value="all" <?= $filter_periode === 'all' ? 'checked' : '' ?> onchange="this.form.submit()">
                        <span class="filter-label">Semua Data</span>
                    </label>
                    <label class="filter-option">
                        <input type="radio" name="periode" value="week" <?= $filter_periode === 'week' ? 'checked' : '' ?> onchange="this.form.submit()">
                        <span class="filter-label">1 Minggu Terakhir</span>
                    </label>
                    <label class="filter-option">
                        <input type="radio" name="periode" value="month" <?= $filter_periode === 'month' ? 'checked' : '' ?> onchange="this.form.submit()">
                        <span class="filter-label">1 Bulan Terakhir</span>
                    </label>
                    <label class="filter-option">
                        <input type="radio" name="periode" value="custom" <?= $filter_periode === 'custom' ? 'checked' : '' ?> id="customRadioReports" onchange="toggleCustomDateReports()">
                        <span class="filter-label">Tanggal Custom</span>
                    </label>
                </div>
                
                <div class="custom-date" id="customDateReports" style="<?= $filter_periode === 'custom' ? 'display: flex;' : 'display: none;' ?>">
                    <input type="date" name="start_date" value="<?= $start_date ?>">
                    <span>s/d</span>
                    <input type="date" name="end_date" value="<?= $end_date ?>">
                    <button type="submit" class="btn-filter"><i class="fas fa-check"></i> Terapkan</button>
                </div>
            </form>
        </div>

        <div class="section-header">
            <h2><i class="fas fa-chart-bar"></i> Reports & Analytics</h2>
            <div class="report-period">
                Periode: <?= $filter_periode === 'all' ? 'Semua Waktu' : ($filter_periode === 'week' ? '1 Minggu Terakhir' : ($filter_periode === 'month' ? '1 Bulan Terakhir' : 'Custom')) ?>
            </div>
        </div>

        <div class="charts-grid">
            <div class="chart-container">
                <h3><i class="fas fa-chart-pie"></i> User Transaction Statistics</h3>
                <canvas id="userStatsChart"></canvas>
            </div>
            
            <div class="chart-container">
                <h3><i class="fas fa-chart-line"></i> Monthly Financial Trend</h3>
                <canvas id="monthlyTrendChart"></canvas>
            </div>
        </div>

        <div class="user-stats-table">
            <h3><i class="fas fa-table"></i> Detailed User Statistics</h3>
            <div class="table-container">
                <table class="data-table no-actions-right">
                    <thead>
                        <tr>
                            <th>User Email</th>
                            <th>Total Transaksi</th>
                            <th class="number-cell">Total Pemasukan</th>
                            <th class="number-cell">Total Pengeluaran</th>
                            <th class="number-cell">Saldo</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $user_stats->data_seek(0);
                        if ($user_stats->num_rows > 0): 
                            while($stat = $user_stats->fetch_assoc()): 
                                $saldo = $stat['total_pemasukan'] - $stat['total_pengeluaran'];
                        ?>
                        <tr>
                            <td><?= htmlspecialchars($stat['email']) ?></td>
                            <td><?= $stat['total_transactions'] ?></td>
                            <td class="number-cell positive"><?= format_rupiah($stat['total_pemasukan']) ?></td>
                            <td class="number-cell negative"><?= format_rupiah($stat['total_pengeluaran']) ?></td>
                            <td class="number-cell <?= $saldo >= 0 ? 'positive' : 'negative' ?>">
                                <?= format_rupiah($saldo) ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                        <?php else: ?>
                        <tr>
                            <td colspan="5" class="text-center">
                                <i class="fas fa-chart-bar empty-icon"></i>
                                <p class="empty-message">Tidak ada data statistik pada periode yang dipilih.</p>
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- System Config Tab -->
    <div id="config" class="tab-content <?= $active_tab === 'config' ? 'active' : '' ?>">
        <div class="section-header">
            <h2><i class="fas fa-sliders-h"></i> System Configuration</h2>
        </div>

        <div class="config-form">
            <form method="POST">
                <input type="hidden" name="action" value="update_config">
                
                <div class="form-group">
                    <label><i class="fas fa-heading"></i> Application Name</label>
                    <input type="text" name="app_name" value="<?= htmlspecialchars($app_name) ?>" required>
                </div>

                <div class="form-group">
                    <label><i class="fas fa-envelope"></i> Admin Email</label>
                    <input type="email" name="admin_email" value="<?= htmlspecialchars($admin_email) ?>" required>
                </div>

                <div class="form-group">
                    <label><i class="fas fa-code-branch"></i> System Version</label>
                    <input type="text" value="<?= htmlspecialchars($system_version) ?>" readonly>
                </div>

                <div class="form-group">
                    <label><i class="fas fa-tools"></i> Maintenance Mode</label>
                    <div class="checkbox-group">
                        <label class="checkbox-label">
                            <input type="checkbox" name="maintenance_mode" value="1" <?= $maintenance_mode === '1' ? 'checked' : '' ?>>
                            <span class="checkbox-custom"></span>
                            <span class="checkbox-text">Aktifkan mode maintenance</span>
                        </label>
                        <small class="form-help">Saat aktif, user tidak bisa mengakses aplikasi</small>
                    </div>
                </div>

                <div class="form-group">
                    <label><i class="fas fa-database"></i> Database Status</label>
                    <input type="text" value="<?= $db_status ?>" readonly>
                </div>

                <div class="form-group">
                    <label><i class="fas fa-users"></i> Total Users</label>
                    <input type="text" value="<?= $total_users ?> users" readonly>
                </div>

                <div class="form-group">
                    <label><i class="fas fa-exchange-alt"></i> Total Transactions</label>
                    <input type="text" value="<?= $total_transactions ?> transactions" readonly>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn-primary">
                        <i class="fas fa-save"></i> Update Configuration
                    </button>
                    <button type="button" class="btn-secondary" onclick="resetToDefault()">
                        <i class="fas fa-undo"></i> Reset to Default
                    </button>
                </div>
            </form>
        </div>
    </div>

<!-- Add User Modal -->
<div id="addUserModal" class="modal">
    <div class="modal-content">
        <h3><i class="fas fa-user-plus"></i> Add New User</h3>
        <form method="POST">
            <input type="hidden" name="action" value="add_user">
            
            <div class="form-group">
                <label><i class="fas fa-envelope"></i> Email</label>
                <input type="email" name="email" required>
            </div>

            <div class="form-group">
                <label><i class="fas fa-lock"></i> Password</label>
                <input type="password" name="password" required>
            </div>

            <div class="modal-buttons">
                <button type="submit" class="btn-primary">
                    <i class="fas fa-user-plus"></i> Add User
                </button>
                <button type="button" onclick="closeModal('addUserModal')" class="btn-cancel">
                    <i class="fas fa-times"></i> Cancel
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Reset Password Modal -->
<div id="resetPasswordModal" class="modal">
    <div class="modal-content">
        <h3><i class="fas fa-key"></i> Reset Password User</h3>
        <form method="POST">
            <input type="hidden" name="action" value="reset_password">
            <input type="hidden" name="user_id" id="resetPasswordUserId">
            
            <div class="form-group">
                <label><i class="fas fa-user"></i> User Email</label>
                <input type="text" id="resetPasswordUserEmail" readonly>
            </div>

            <div class="form-group">
                <label><i class="fas fa-lock"></i> New Password</label>
                <input type="password" name="new_password" placeholder="Masukkan password baru" required minlength="6">
                <small class="form-help">Minimal 6 karakter</small>
            </div>

            <div class="form-group">
                <label><i class="fas fa-lock"></i> Confirm Password</label>
                <input type="password" id="confirmPassword" placeholder="Konfirmasi password baru" required>
                <small class="form-help" id="passwordMatchMessage"></small>
            </div>

            <div class="modal-buttons">
                <button type="submit" class="btn-primary" id="resetPasswordSubmit">
                    <i class="fas fa-sync-alt"></i> Reset Password
                </button>
                <button type="button" onclick="closeModal('resetPasswordModal')" class="btn-cancel">
                    <i class="fas fa-times"></i> Cancel
                </button>
            </div>
        </form>
    </div>
</div>

<!-- View User Transactions Modal -->
<div id="viewUserModal" class="modal">
    <div class="modal-content">
        <h3 id="userModalTitle"><i class="fas fa-eye"></i> User Transactions</h3>
        <div id="userTransactionsContent">
        </div>
        <div class="modal-buttons">
            <button type="button" onclick="closeModal('viewUserModal')" class="btn-cancel">
                <i class="fas fa-times"></i> Close
            </button>
        </div>
    </div>
</div>

<script>
// Tab Navigation
function openTab(tabName) {
    const url = new URL(window.location);
    url.searchParams.set('tab', tabName);
    window.history.pushState({}, '', url);
    
    const tabcontents = document.getElementsByClassName("tab-content");
    for (let i = 0; i < tabcontents.length; i++) {
        tabcontents[i].classList.remove("active");
    }

    const tabbuttons = document.getElementsByClassName("tab-button");
    for (let i = 0; i < tabbuttons.length; i++) {
        tabbuttons[i].classList.remove("active");
    }

    document.getElementById(tabName).classList.add("active");
    event.currentTarget.classList.add("active");
}

// Modal Functions
function openModal(modalId) {
    document.getElementById(modalId).classList.add('show');
}

function closeModal(modalId) {
    document.getElementById(modalId).classList.remove('show');
}

// Delete Functions
function deleteUser(userId) {
    if (confirm('Are you sure you want to delete this user? All their transactions will also be deleted.')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'admin-dashboard.php?tab=users';
        
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'action';
        input.value = 'delete_user';
        
        const input2 = document.createElement('input');
        input2.type = 'hidden';
        input2.name = 'user_id';
        input2.value = userId;
        
        form.appendChild(input);
        form.appendChild(input2);
        document.body.appendChild(form);
        form.submit();
    }
}

function deleteTransaction(transactionId) {
    if (confirm('Are you sure you want to delete this transaction?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'admin-dashboard.php?tab=transactions&periode=<?= $filter_periode ?>&start_date=<?= $start_date ?>&end_date=<?= $end_date ?>';
        
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'action';
        input.value = 'delete_transaction';
        
        const input2 = document.createElement('input');
        input2.type = 'hidden';
        input2.name = 'transaction_id';
        input2.value = transactionId;
        
        form.appendChild(input);
        form.appendChild(input2);
        document.body.appendChild(form);
        form.submit();
    }
}

// View User Transactions
function viewUserTransactions(userId, userEmail) {
    document.getElementById('userModalTitle').innerHTML = '<i class="fas fa-eye"></i> Transactions - ' + userEmail;
    
    fetch('admin-ajax.php?action=get_user_transactions&user_id=' + userId)
        .then(response => response.text())
        .then(data => {
            document.getElementById('userTransactionsContent').innerHTML = data;
            openModal('viewUserModal');
        })
        .catch(error => {
            document.getElementById('userTransactionsContent').innerHTML = '<p>Error loading transactions</p>';
            openModal('viewUserModal');
        });
}

// Filter Functions
function toggleCustomDate() {
    const customDate = document.getElementById('customDate');
    const customRadio = document.getElementById('customRadio');
    
    if (customRadio.checked) {
        customDate.style.display = 'flex';
    } else {
        customDate.style.display = 'none';
    }
}

function toggleCustomDateReports() {
    const customDate = document.getElementById('customDateReports');
    const customRadio = document.getElementById('customRadioReports');
    
    if (customRadio.checked) {
        customDate.style.display = 'flex';
    } else {
        customDate.style.display = 'none';
    }
}

// Toast Notification
<?php if (isset($toast_message)): ?>
function showToast() {
    const toast = document.getElementById('toast');
    if (toast) {
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

document.addEventListener('DOMContentLoaded', function() {
    showToast();
});
<?php endif; ?>

// Close modal when clicking outside
document.addEventListener('click', function(e) {
    const modals = document.getElementsByClassName('modal');
    for (let modal of modals) {
        if (e.target === modal) {
            modal.classList.remove('show');
        }
    }
});

// Charts
document.addEventListener('DOMContentLoaded', function() {
    // User Stats Chart
    const userStatsCtx = document.getElementById('userStatsChart').getContext('2d');
    new Chart(userStatsCtx, {
        type: 'bar',
        data: {
            labels: [<?php 
                $user_stats->data_seek(0);
                $labels = [];
                while($stat = $user_stats->fetch_assoc()) {
                    $labels[] = "'" . htmlspecialchars($stat['email']) . "'";
                }
                echo implode(',', $labels);
            ?>],
            datasets: [{
                label: 'Pemasukan',
                data: [<?php 
                    $user_stats->data_seek(0);
                    $pemasukan_data = [];
                    while($stat = $user_stats->fetch_assoc()) {
                        $pemasukan_data[] = $stat['total_pemasukan'];
                    }
                    echo implode(',', $pemasukan_data);
                ?>],
                backgroundColor: '#2ecc71'
            }, {
                label: 'Pengeluaran',
                data: [<?php 
                    $user_stats->data_seek(0);
                    $pengeluaran_data = [];
                    while($stat = $user_stats->fetch_assoc()) {
                        $pengeluaran_data[] = $stat['total_pengeluaran'];
                    }
                    echo implode(',', $pengeluaran_data);
                ?>],
                backgroundColor: '#e74c3c'
            }]
        },
        options: {
            responsive: true,
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });

    // Monthly Trend Chart
    const monthlyCtx = document.getElementById('monthlyTrendChart').getContext('2d');
    new Chart(monthlyCtx, {
        type: 'line',
        data: {
            labels: [<?php 
                $monthly_stats->data_seek(0);
                $bulan_labels = [];
                while($stat = $monthly_stats->fetch_assoc()) {
                    $bulan_labels[] = "'" . $stat['bulan'] . "'";
                }
                echo implode(',', $bulan_labels);
            ?>],
            datasets: [{
                label: 'Pemasukan',
                data: [<?php 
                    $monthly_stats->data_seek(0);
                    $pemasukan_trend = [];
                    while($stat = $monthly_stats->fetch_assoc()) {
                        $pemasukan_trend[] = $stat['pemasukan'];
                    }
                    echo implode(',', $pemasukan_trend);
                ?>],
                borderColor: '#2ecc71',
                tension: 0.1
            }, {
                label: 'Pengeluaran',
                data: [<?php 
                    $monthly_stats->data_seek(0);
                    $pengeluaran_trend = [];
                    while($stat = $monthly_stats->fetch_assoc()) {
                        $pengeluaran_trend[] = $stat['pengeluaran'];
                    }
                    echo implode(',', $pengeluaran_trend);
                ?>],
                borderColor: '#e74c3c',
                tension: 0.1
            }]
        },
        options: {
            responsive: true
        }
    });
});

// Reset Password Functions
function openResetPasswordModal(userId, userEmail) {
    document.getElementById('resetPasswordUserId').value = userId;
    document.getElementById('resetPasswordUserEmail').value = userEmail;
    
    // Reset form
    document.querySelector('#resetPasswordModal input[name="new_password"]').value = '';
    document.getElementById('confirmPassword').value = '';
    document.getElementById('passwordMatchMessage').textContent = '';
    document.getElementById('resetPasswordSubmit').disabled = true;
    
    openModal('resetPasswordModal');
}

// Password confirmation validation
document.addEventListener('DOMContentLoaded', function() {
    const newPasswordInput = document.querySelector('#resetPasswordModal input[name="new_password"]');
    const confirmPasswordInput = document.getElementById('confirmPassword');
    const passwordMatchMessage = document.getElementById('passwordMatchMessage');
    const resetPasswordSubmit = document.getElementById('resetPasswordSubmit');
    
    if (newPasswordInput && confirmPasswordInput) {
        function validatePasswords() {
            const newPassword = newPasswordInput.value;
            const confirmPassword = confirmPasswordInput.value;
            
            if (newPassword.length < 6) {
                passwordMatchMessage.textContent = 'Password minimal 6 karakter';
                passwordMatchMessage.style.color = 'var(--warning)';
                resetPasswordSubmit.disabled = true;
            } else if (newPassword !== confirmPassword) {
                passwordMatchMessage.textContent = 'Password tidak cocok';
                passwordMatchMessage.style.color = 'var(--danger)';
                resetPasswordSubmit.disabled = true;
            } else {
                passwordMatchMessage.textContent = 'Password cocok';
                passwordMatchMessage.style.color = 'var(--success)';
                resetPasswordSubmit.disabled = false;
            }
        }
        
        newPasswordInput.addEventListener('input', validatePasswords);
        confirmPasswordInput.addEventListener('input', validatePasswords);
    }
});

// System Config Functions
function resetToDefault() {
    if (confirm('Reset semua konfigurasi ke nilai default? Perubahan yang belum disimpan akan hilang.')) {
        document.querySelector('input[name="app_name"]').value = 'DOMPETKU';
        document.querySelector('input[name="admin_email"]').value = '<?= $_SESSION['admin_email'] ?>';
        document.querySelector('input[name="maintenance_mode"]').checked = false;
    }
}

// Real-time config preview
document.addEventListener('DOMContentLoaded', function() {
    const appNameInput = document.querySelector('input[name="app_name"]');
    const maintenanceCheckbox = document.querySelector('input[name="maintenance_mode"]');
    
    if (appNameInput) {
        appNameInput.addEventListener('input', function() {
            // Bisa ditambahkan preview real-time jika perlu
            console.log('App name changed to:', this.value);
        });
    }
    
    if (maintenanceCheckbox) {
        maintenanceCheckbox.addEventListener('change', function() {
            const status = this.checked ? 'AKTIF' : 'NON-AKTIF';
            console.log('Maintenance mode:', status);
        });
    }
});
</script>

</body>
</html>