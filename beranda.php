<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require 'config/mysql-connection.php';

// Rupiah Converter
function format_rupiah($angka) {
    return 'Rp ' . number_format($angka, 0, ',', '.');
}

function format_tanggal($tanggal) {
    return date('d M Y', strtotime($tanggal));
}

function format_tanggal_pendek($tanggal) {
    return date('d M', strtotime($tanggal));
}

$user_id = $_SESSION['user_id'];

// Filter
$filter_periode = $_GET['periode'] ?? 'all';
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';

$filter_condition = "WHERE user_id = ?";
$filter_params = ["i", $user_id];

if ($filter_periode === 'week') {
    $filter_condition .= " AND tanggal BETWEEN DATE_SUB(CURDATE(), INTERVAL 1 WEEK) AND CURDATE()";
} elseif ($filter_periode === 'month') {
    $filter_condition .= " AND tanggal BETWEEN DATE_SUB(CURDATE(), INTERVAL 1 MONTH) AND CURDATE()";
} elseif ($filter_periode === 'custom' && $start_date && $end_date) {
    $filter_condition .= " AND tanggal BETWEEN ? AND ?";
    $filter_params[0] .= "ss";
    $filter_params[] = $start_date;
    $filter_params[] = $end_date;
}

/* CRUD */

// Add Transaction
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['action'])) {
    if ($_POST['action'] === 'add') {
        $tipe = strtolower($_POST['tipe'] ?? '');
        $jumlah = (int)($_POST['jumlah'] ?? 0);
        $deskripsi = trim($_POST['deskripsi'] ?? '');
        $tanggal = $_POST['tanggal'] ?? date('Y-m-d');

        if ($tipe && $deskripsi && $jumlah > 0) {
            $stmt = $conn->prepare("INSERT INTO transaksi (user_id, tipe, deskripsi, jumlah, tanggal) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("issis", $user_id, $tipe, $deskripsi, $jumlah, $tanggal);
            
            if ($stmt->execute()) {
                $_SESSION['success'] = "Transaksi berhasil disimpan!";
            } else {
                $_SESSION['error'] = "Gagal menyimpan transaksi!";
            }
            $stmt->close();
        } else {
            $_SESSION['error'] = "Isi data dengan benar!";
        }
    }
    
    // Edit Transaction
    elseif ($_POST['action'] === 'edit') {
        $id = (int)($_POST['id'] ?? 0);
        $tipe = strtolower($_POST['tipe'] ?? '');
        $jumlah = (int)($_POST['jumlah'] ?? 0);
        $deskripsi = trim($_POST['deskripsi'] ?? '');
        $tanggal = $_POST['tanggal'] ?? date('Y-m-d');

        if ($id > 0 && $tipe && $deskripsi && $jumlah > 0) {
            $stmt = $conn->prepare("UPDATE transaksi SET tipe=?, deskripsi=?, jumlah=?, tanggal=? WHERE id=? AND user_id=?");
            $stmt->bind_param("ssisii", $tipe, $deskripsi, $jumlah, $tanggal, $id, $user_id);
            
            if ($stmt->execute()) {
                $_SESSION['success'] = "Transaksi berhasil diperbarui!";
            } else {
                $_SESSION['error'] = "Gagal memperbarui transaksi!";
            }
            $stmt->close();
        } else {
            $_SESSION['error'] = "Data edit tidak valid!";
        }
    }
    
    // Delete Transaction
    elseif ($_POST['action'] === 'delete') {
        $id = (int)($_POST['id'] ?? 0);

        if ($id > 0) {
            $stmt = $conn->prepare("DELETE FROM transaksi WHERE id=? AND user_id=?");
            $stmt->bind_param("ii", $id, $user_id);
            
            if ($stmt->execute()) {
                $_SESSION['success'] = "Transaksi berhasil dihapus!";
            } else {
                $_SESSION['error'] = "Gagal menghapus transaksi!";
            }
            $stmt->close();
        } else {
            $_SESSION['error'] = "ID transaksi tidak valid!";
        }
    }

    // Redirect back with filter parameters
    $redirect_url = "beranda.php?periode=" . urlencode($filter_periode);
    if ($filter_periode === 'custom' && $start_date && $end_date) {
        $redirect_url .= "&start_date=" . urlencode($start_date) . "&end_date=" . urlencode($end_date);
    }
    header("Location: " . $redirect_url);
    exit;
}

/* fetch data */
$stmt = $conn->prepare("SELECT * FROM transaksi $filter_condition ORDER BY tanggal DESC, id DESC");
$stmt->bind_param(...$filter_params);
$stmt->execute();
$result = $stmt->get_result();

$riwayat = [];
$total_pemasukan = 0;
$total_pengeluaran = 0;

while ($row = $result->fetch_assoc()) {
    $riwayat[] = $row;
    if ($row['tipe'] === 'pemasukan') $total_pemasukan += $row['jumlah'];
    if ($row['tipe'] === 'pengeluaran') $total_pengeluaran += $row['jumlah'];
}

$stmt->close();
$saldo = $total_pemasukan - $total_pengeluaran;

$chart_stmt = $conn->prepare("
    SELECT 
        SUM(CASE WHEN tipe = 'pemasukan' THEN jumlah ELSE 0 END) as total_pemasukan,
        SUM(CASE WHEN tipe = 'pengeluaran' THEN jumlah ELSE 0 END) as total_pengeluaran
    FROM transaksi $filter_condition
");
$chart_stmt->bind_param(...$filter_params);
$chart_stmt->execute();
$chart_result = $chart_stmt->get_result();
$chart_data = $chart_result->fetch_assoc();
$chart_stmt->close();

$chart_pemasukan = $chart_data['total_pemasukan'] ?? 0;
$chart_pengeluaran = $chart_data['total_pengeluaran'] ?? 0;

/* export function */
if (isset($_GET['export'])) {
    $export_type = $_GET['export'];
    
    $export_data = [];
    $export_stmt = $conn->prepare("SELECT * FROM transaksi $filter_condition ORDER BY tanggal DESC, id DESC");
    $export_stmt->bind_param(...$filter_params);
    $export_stmt->execute();
    $export_result = $export_stmt->get_result();
    
    while ($row = $export_result->fetch_assoc()) {
        $export_data[] = $row;
    }
    $export_stmt->close();
    
    if ($export_type === 'csv') {
        exportToCSV($export_data, $filter_periode);
    } elseif ($export_type === 'excel') {
        exportToExcel($export_data, $filter_periode);
    } 
}

function exportToCSV($data, $periode) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="laporan-keuangan-' . $periode . '-' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Tanggal', 'Jenis', 'Deskripsi', 'Jumlah', 'Tipe']);
    
    foreach ($data as $row) {
        fputcsv($output, [
            $row['tanggal'],
            $row['tipe'] === 'pemasukan' ? 'Pemasukan' : 'Pengeluaran',
            $row['deskripsi'],
            $row['jumlah'],
            $row['tipe']
        ]);
    }
    fclose($output);
    exit;
}

function exportToExcel($data, $periode) {
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="laporan-keuangan-' . $periode . '-' . date('Y-m-d') . '.xls"');
    
    echo "<table border='1'>";
    echo "<tr><th>Tanggal</th><th>Jenis</th><th>Deskripsi</th><th>Jumlah</th><th>Tipe</th></tr>";
    
    foreach ($data as $row) {
        echo "<tr>";
        echo "<td>" . $row['tanggal'] . "</td>";
        echo "<td>" . ($row['tipe'] === 'pemasukan' ? 'Pemasukan' : 'Pengeluaran') . "</td>";
        echo "<td>" . $row['deskripsi'] . "</td>";
        echo "<td>" . $row['jumlah'] . "</td>";
        echo "<td>" . $row['tipe'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    exit;
}

/* Maintenance */
$maintenance_result = $conn->query("SELECT config_value FROM system_config WHERE config_key = 'maintenance_mode'");
$maintenance_mode = $maintenance_result->fetch_assoc()['config_value'] ?? '0';

if ($maintenance_mode === '1') {
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
    <title><?= htmlspecialchars($app_name) ?> - Dashboard</title>
    <link rel="stylesheet" href="./style/beranda.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>

<body>
    <!-- Sidebar Navigation -->
    <nav class="sidebar">
        <div class="sidebar-header">
            <div class="logo">
                <i class="fas fa-wallet"></i>
                <span><?= htmlspecialchars($app_name) ?></span>
            </div>
            <button class="sidebar-toggle" onclick="toggleSidebar()">
                <i class="fas fa-bars"></i>
            </button>
        </div>
        
        <div class="sidebar-menu">
            <a href="beranda.php" class="menu-item active">
                <i class="fas fa-home"></i>
                <span>Dashboard</span>
            </a>
            <a href="javascript:void(0)" class="menu-item" onclick="openQuickAdd()">
                <i class="fas fa-plus-circle"></i>
                <span>Tambah Transaksi</span>
            </a>
            <a href="settings.php" class="menu-item">
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
        <!-- Header -->
        <header class="content-header">
            <div class="header-title">
                <h1>Dashboard Keuangan</h1>
                <p>Kelola keuangan Anda dengan mudah</p>
            </div>
            <div class="header-actions">
                <button class="btn-primary" onclick="openQuickAdd()">
                    <i class="fas fa-plus"></i>
                    <span>Tambah Transaksi</span>
                </button>
            </div>
        </header>

        <!-- Toast Notification -->
        <?php if (!empty($_SESSION['success']) || !empty($_SESSION['error'])): ?>
            <?php 
                $toast_message = $_SESSION['success'] ?? $_SESSION['error'];
                $toast_type = isset($_SESSION['success']) ? 'success' : 'error';
                unset($_SESSION['success'], $_SESSION['error']);
            ?>
            <div class="toast toast-<?= $toast_type ?>">
                <div class="toast-content">
                    <i class="fas fa-<?= $toast_type === 'success' ? 'check' : 'exclamation-triangle' ?>"></i>
                    <span><?= $toast_message ?></span>
                </div>
                <button class="toast-close" onclick="this.parentElement.remove()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        <?php endif; ?>

        <!-- Financial Overview Cards -->
        <section class="financial-overview">
            <div class="overview-card income-card">
                <div class="card-icon">
                    <i class="fas fa-arrow-up"></i>
                </div>
                <div class="card-content">
                    <h3>Pemasukan</h3>
                    <p class="amount"><?= format_rupiah($total_pemasukan) ?></p>
                    <span class="card-trend positive">
                        <i class="fas fa-trend-up"></i>
                        Total pemasukan
                    </span>
                </div>
            </div>

            <div class="overview-card expense-card">
                <div class="card-icon">
                    <i class="fas fa-arrow-down"></i>
                </div>
                <div class="card-content">
                    <h3>Pengeluaran</h3>
                    <p class="amount"><?= format_rupiah($total_pengeluaran) ?></p>
                    <span class="card-trend negative">
                        <i class="fas fa-trend-down"></i>
                        Total pengeluaran
                    </span>
                </div>
            </div>

            <div class="overview-card balance-card">
                <div class="card-icon">
                    <i class="fas fa-wallet"></i>
                </div>
                <div class="card-content">
                    <h3>Saldo</h3>
                    <p class="amount <?= $saldo >= 0 ? 'positive' : 'negative' ?>">
                        <?= format_rupiah($saldo) ?>
                    </p>
                    <span class="card-trend <?= $saldo >= 0 ? 'positive' : 'negative' ?>">
                        <i class="fas fa-<?= $saldo >= 0 ? 'chart-line' : 'exclamation-circle' ?>"></i>
                        <?= $saldo >= 0 ? 'Sehat' : 'Perhatian' ?>
                    </span>
                </div>
            </div>
        </section>

        <!-- Quick Actions & Filter -->
        <section class="quick-actions-section">
            <div class="section-header">
                <h2>Filter & Analisis</h2>
                <div class="action-buttons">
                    <div class="filter-group">
                        <select class="filter-select" onchange="applyPeriodFilter(this.value)">
                            <option value="all" <?= $filter_periode === 'all' ? 'selected' : '' ?>>Semua Waktu</option>
                            <option value="week" <?= $filter_periode === 'week' ? 'selected' : '' ?>>1 Minggu</option>
                            <option value="month" <?= $filter_periode === 'month' ? 'selected' : '' ?>>1 Bulan</option>
                            <option value="custom" <?= $filter_periode === 'custom' ? 'selected' : '' ?>>Custom</option>
                        </select>
                        
                        <?php if ($filter_periode === 'custom'): ?>
                        <div class="custom-date-range">
                            <input type="date" name="start_date" value="<?= $start_date ?>" onchange="updateCustomFilter()">
                            <span>s/d</span>
                            <input type="date" name="end_date" value="<?= $end_date ?>" onchange="updateCustomFilter()">
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="export-dropdown">
                        <button class="btn-secondary export-btn">
                            <i class="fas fa-download"></i>
                            <span>Export Data</span>
                            <i class="fas fa-chevron-down"></i>
                        </button>
                        <div class="export-options">
                            <a href="javascript:void(0)" onclick="exportData('csv')" class="export-option">
                                <i class="fas fa-file-csv"></i>
                                Export CSV
                            </a>
                            <a href="javascript:void(0)" onclick="exportData('excel')" class="export-option">
                                <i class="fas fa-file-excel"></i>
                                Export Excel
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Main Grid Layout -->
        <div class="dashboard-grid">
            <!-- Recent Transactions -->
            <div class="grid-card transactions-card">
                <div class="card-header">
                    <h3><i class="fas fa-history"></i> Transaksi Terbaru</h3>
                    <span class="badge"><?= count($riwayat) ?> transaksi</span>
                </div>
                <div class="card-body">
                    <?php if ($riwayat): ?>
                        <div class="transactions-list">
                            <?php foreach (array_slice($riwayat, 0, 8) as $t): ?>
                            <div class="transaction-item">
                                <div class="transaction-icon <?= $t['tipe'] ?>">
                                    <i class="fas fa-<?= $t['tipe'] === 'pemasukan' ? 'arrow-down' : 'arrow-up' ?>"></i>
                                </div>
                                <div class="transaction-details">
                                    <span class="transaction-desc"><?= htmlspecialchars($t['deskripsi']) ?></span>
                                    <span class="transaction-date"><?= format_tanggal_pendek($t['tanggal']) ?></span>
                                </div>
                                <div class="transaction-amount <?= $t['tipe'] === 'pemasukan' ? 'positive' : 'negative' ?>">
                                    <?= $t['tipe'] === 'pemasukan' ? '+' : '-' ?><?= format_rupiah($t['jumlah']) ?>
                                </div>
                                <div class="transaction-actions">
                                    <button class="btn-icon" onclick="openEditModal(<?= htmlspecialchars(json_encode($t)) ?>)">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn-icon danger" onclick="openDeleteModal(<?= $t['id'] ?>)">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php if (count($riwayat) > 8): ?>
                        <div class="card-footer">
                            <a href="javascript:void(0)" class="view-all">Lihat Semua Transaksi</a>
                        </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-receipt"></i>
                            <p>Belum ada transaksi</p>
                            <button class="btn-text" onclick="openQuickAdd()">Tambah Transaksi Pertama</button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Charts Section -->
            <div class="grid-card charts-card">
                <div class="card-header">
                    <h3><i class="fas fa-chart-bar"></i> Analisis Keuangan</h3>
                </div>
                <div class="card-body">
                    <div class="charts-container">
                        <div class="chart-wrapper">
                            <h4>Distribusi Keuangan</h4>
                            <canvas id="pieChart"></canvas>
                        </div>
                        <div class="chart-wrapper">
                            <h4>Perbandingan</h4>
                            <canvas id="barChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Stats -->
            <div class="grid-card stats-card">
                <div class="card-header">
                    <h3><i class="fas fa-chart-line"></i> Statistik Cepat</h3>
                </div>
                <div class="card-body">
                    <div class="stats-grid">
                        <div class="stat-item">
                            <div class="stat-value"><?= count($riwayat) ?></div>
                            <div class="stat-label">Total Transaksi</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-value"><?= format_rupiah($total_pemasukan) ?></div>
                            <div class="stat-label">Total Pemasukan</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-value"><?= format_rupiah($total_pengeluaran) ?></div>
                            <div class="stat-label">Total Pengeluaran</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-value <?= $saldo >= 0 ? 'positive' : 'negative' ?>">
                                <?= format_rupiah($saldo) ?>
                            </div>
                            <div class="stat-label">Saldo Saat Ini</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Quick Add Modal -->
    <div id="quickAddModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Tambah Transaksi Cepat</h3>
                <button class="btn-close" onclick="closeQuickAdd()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form method="POST" class="modal-form">
                <input type="hidden" name="action" value="add">
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Jenis Transaksi</label>
                        <div class="type-selector">
                            <button type="button" class="type-option active" data-value="pemasukan">
                                <i class="fas fa-arrow-down"></i>
                                <span>Pemasukan</span>
                            </button>
                            <button type="button" class="type-option" data-value="pengeluaran">
                                <i class="fas fa-arrow-up"></i>
                                <span>Pengeluaran</span>
                            </button>
                        </div>
                        <input type="hidden" name="tipe" value="pemasukan" id="transactionType">
                    </div>
                    
                    <div class="form-group">
                        <label>Jumlah (Rp)</label>
                        <input type="number" name="jumlah" placeholder="0" min="1" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Keterangan</label>
                    <input type="text" name="deskripsi" placeholder="Deskripsi transaksi" required>
                </div>
                
                <div class="form-group">
                    <label>Tanggal</label>
                    <input type="date" name="tanggal" value="<?= date('Y-m-d') ?>" required>
                </div>
                
                <div class="modal-actions">
                    <button type="button" class="btn-secondary" onclick="closeQuickAdd()">Batal</button>
                    <button type="submit" class="btn-primary">
                        <i class="fas fa-save"></i>
                        Simpan Transaksi
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Edit Transaksi</h3>
                <button class="btn-close" onclick="closeEditModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form method="POST" class="modal-form">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="editId">
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Jenis Transaksi</label>
                        <div class="type-selector">
                            <button type="button" class="type-option" data-value="pemasukan" id="editTypePemasukan">
                                <i class="fas fa-arrow-down"></i>
                                <span>Pemasukan</span>
                            </button>
                            <button type="button" class="type-option" data-value="pengeluaran" id="editTypePengeluaran">
                                <i class="fas fa-arrow-up"></i>
                                <span>Pengeluaran</span>
                            </button>
                        </div>
                        <input type="hidden" name="tipe" value="pemasukan" id="editTransactionType">
                    </div>
                    
                    <div class="form-group">
                        <label>Jumlah (Rp)</label>
                        <input type="number" name="jumlah" id="editJumlah" placeholder="0" min="1" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Keterangan</label>
                    <input type="text" name="deskripsi" id="editDeskripsi" placeholder="Deskripsi transaksi" required>
                </div>
                
                <div class="form-group">
                    <label>Tanggal</label>
                    <input type="date" name="tanggal" id="editTanggal" required>
                </div>
                
                <div class="modal-actions">
                    <button type="button" class="btn-secondary" onclick="closeEditModal()">Batal</button>
                    <button type="submit" class="btn-primary">
                        <i class="fas fa-save"></i>
                        Update Transaksi
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Delete Modal -->
    <div id="deleteModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Hapus Transaksi</h3>
                <button class="btn-close" onclick="closeDeleteModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form method="POST" class="modal-form">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" id="deleteId">
                
                <div class="delete-confirmation">
                    <i class="fas fa-exclamation-triangle"></i>
                    <p>Apakah Anda yakin ingin menghapus transaksi ini?</p>
                    <p class="delete-warning">Tindakan ini tidak dapat dibatalkan!</p>
                </div>
                
                <div class="modal-actions">
                    <button type="button" class="btn-secondary" onclick="closeDeleteModal()">Batal</button>
                    <button type="submit" class="btn-danger">
                        <i class="fas fa-trash"></i>
                        Ya, Hapus
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Chart 
        const totalPemasukan = <?= (int)$chart_pemasukan ?>;
        const totalPengeluaran = <?= (int)$chart_pengeluaran ?>;

        document.addEventListener('DOMContentLoaded', function() {
            // Pie Chart
            new Chart(document.getElementById('pieChart'), {
                type: 'doughnut',
                data: {
                    labels: ['Pemasukan', 'Pengeluaran'],
                    datasets: [{
                        data: [totalPemasukan, totalPengeluaran],
                        backgroundColor: ['#10b981', '#ef4444'],
                        borderWidth: 0
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '70%',
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });

            // Bar Chart
            new Chart(document.getElementById('barChart'), {
                type: 'bar',
                data: {
                    labels: ['Periode Ini'],
                    datasets: [
                        {
                            label: 'Pemasukan',
                            data: [totalPemasukan],
                            backgroundColor: '#10b981'
                        },
                        {
                            label: 'Pengeluaran',
                            data: [totalPengeluaran],
                            backgroundColor: '#ef4444'
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: { beginAtZero: true }
                    }
                }
            });
        });

        function toggleSidebar() {
            document.body.classList.toggle('sidebar-collapsed');
        }

        function openQuickAdd() {
            document.getElementById('quickAddModal').classList.add('show');
        }

        function closeQuickAdd() {
            document.getElementById('quickAddModal').classList.remove('show');
        }

        function openEditModal(transaction) {
            document.getElementById('editId').value = transaction.id;
            document.getElementById('editJumlah').value = transaction.jumlah;
            document.getElementById('editDeskripsi').value = transaction.deskripsi;
            document.getElementById('editTanggal').value = transaction.tanggal;
            
            const type = transaction.tipe;
            document.getElementById('editTransactionType').value = type;
            
            document.querySelectorAll('#editModal .type-option').forEach(opt => {
                opt.classList.remove('active');
            });
            
            if (type === 'pemasukan') {
                document.getElementById('editTypePemasukan').classList.add('active');
            } else {
                document.getElementById('editTypePengeluaran').classList.add('active');
            }
            
            document.getElementById('editModal').classList.add('show');
        }

        function closeEditModal() {
            document.getElementById('editModal').classList.remove('show');
        }

        function openDeleteModal(id) {
            document.getElementById('deleteId').value = id;
            document.getElementById('deleteModal').classList.add('show');
        }

        function closeDeleteModal() {
            document.getElementById('deleteModal').classList.remove('show');
        }

        function applyPeriodFilter(period) {
            if (period === 'custom') {
                const url = new URL(window.location);
                url.searchParams.set('periode', 'custom');
                window.location.href = url.toString();
            } else {
                const url = new URL(window.location);
                url.searchParams.set('periode', period);
                url.searchParams.delete('start_date');
                url.searchParams.delete('end_date');
                window.location.href = url.toString();
            }
        }

        function updateCustomFilter() {
            const startDate = document.querySelector('input[name="start_date"]').value;
            const endDate = document.querySelector('input[name="end_date"]').value;
            if (startDate && endDate) {
                const url = new URL(window.location);
                url.searchParams.set('periode', 'custom');
                url.searchParams.set('start_date', startDate);
                url.searchParams.set('end_date', endDate);
                window.location.href = url.toString();
            }
        }

        document.querySelectorAll('.type-option').forEach(option => {
            option.addEventListener('click', function() {
                const modal = this.closest('.modal');
                modal.querySelectorAll('.type-option').forEach(opt => opt.classList.remove('active'));
                this.classList.add('active');
                modal.querySelector('input[name="tipe"]').value = this.dataset.value;
            });
        });

        setTimeout(() => {
            document.querySelectorAll('.toast').forEach(toast => {
                toast.style.opacity = '0';
                setTimeout(() => toast.remove(), 300);
            });
        }, 5000);

        document.querySelectorAll('.modal').forEach(modal => {
            modal.addEventListener('click', function(e) {
                if (e.target === this) {
                    this.classList.remove('show');
                }
            });
        });

        function exportData(type) {
            const baseUrl = window.location.href.split('?')[0];
            const currentParams = new URLSearchParams(window.location.search);
            
            let exportUrl = `${baseUrl}?${currentParams.toString()}&export=${type}`;
            
            window.open(exportUrl, '_blank');
        }
    </script>
</body>
</html>