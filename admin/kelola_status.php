<?php
require_once '../config/security.php';
start_secure_session();
if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true) {
    header('Location: ../login.php');
    exit;
}
require_once '../config/database.php';
$nama_admin = $_SESSION['user_nama'] ?? 'Admin';
$id_admin = $_SESSION['user_id']; 

// Ambil flash message dari session (jika ada setelah redirect)
$success_message = $_SESSION['flash_success'] ?? '';
$error_message   = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

// 1. ALGORITMA CRUD
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['action'])) {
    verify_csrf_or_fail();
    $action = $_POST['action'];

    switch ($action) {
        case 'tambah':
            // ambil data
            $nama = mb_strtolower(post_string('nama_layanan', 225), 'UTF-8');
            $status = validate_choice(mb_strtolower(post_string('status_layanan', 20), 'UTF-8'), ['ready', 'gangguan'], 'Status layanan tidak valid.');
            $waktu_kejadian = date('Y-m-d H:i:s');
            $catatan = mb_strtolower(post_string('catatan', 2000, false), 'UTF-8');
        
            // INSERT INTO data
            $stmt = $conn->prepare("INSERT INTO status_adminduk (nama_layanan, status, waktu_kejadian, catatan, dibuat_oleh) VALUES (:layanan, :status, :waktu_kejadian, :catatan, :dibuat) ");
            $stmt->execute([
                ":layanan" => $nama,
                ":status" => $status,
                ":waktu_kejadian" => $waktu_kejadian,
                ":catatan" => $catatan,
                ":dibuat" => $id_admin
            ]);
            $_SESSION['flash_success'] = 'Status layanan berhasil ditambahkan.';
            break;
        case 'edit':
            // ambil data
            $nama = mb_strtolower(post_string('nama_layanan', 225), 'UTF-8');
            $status = validate_choice(mb_strtolower(post_string('status_layanan', 20), 'UTF-8'), ['ready', 'gangguan'], 'Status layanan tidak valid.');
            $waktu_kejadian = date('Y-m-d H:i:s'); // Perbaikan format waktu
            $catatan = mb_strtolower(post_string('catatan', 2000, false), 'UTF-8');
            $id = post_positive_int('id_status');

            $stmt = $conn->prepare("UPDATE status_adminduk SET nama_layanan=:layanan, status=:status, waktu_kejadian=:waktu_kejadian, catatan=:catatan, dibuat_oleh=:dibuat WHERE id = :id");
            $stmt->execute([
                ":layanan" => $nama,
                ":status" => $status,
                ":waktu_kejadian" => $waktu_kejadian,
                ":catatan" => $catatan,
                ":dibuat" => $id_admin,
                ":id" => $id
            ]);
            $_SESSION['flash_success'] = 'Status layanan berhasil diperbarui.';
            break;
        case 'hapus':
            $id = post_positive_int('id_status');
            $stmt_del = $conn->prepare("DELETE FROM status_adminduk WHERE id=:id");
            $stmt_del->execute([':id'=>$id]);
            $_SESSION['flash_success'] = 'Status layanan berhasil dihapus.';
            break;
    }
    // Redirect setelah proses agar tidak resubmit saat di-refresh
    header('Location: kelola_status.php');
    exit;
}

// Cek apakah mode Edit sedang aktif
$edit_data =null;
if (isset($_GET['edit']) && filter_var($_GET['edit'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])) {
    $id = $_GET['edit'];
    $stmt = $conn->prepare("SELECT * FROM status_adminduk WHERE id=:id");
    $stmt->execute([':id' => $id]);
    $edit_data = $stmt->fetch(PDO::FETCH_ASSOC);
}
    
// Query semua data status layanan
$stmt = $conn->query("SELECT * FROM status_adminduk ORDER BY waktu_kejadian DESC");
$data_status = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Status Layanan — Portal Kecamatan Maron</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>

<body class="admin-body">

    <!-- HEADER -->
    <header class="admin-header">
        <div class="admin-header__container">
            <a href="dashboard.php" class="admin-header__brand">
                <svg viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="24" height="24">
                    <path d="M3 21h18M5 21V7l7-4 7 4v14M9 21V11h6v10" />
                </svg>
                Admin Panel
            </a>
            <nav class="admin-header__nav">
                <a href="dashboard.php" class="btn-logout" style="color: var(--color-on-surface); border-color: var(--color-outline-variant);" title="Kembali ke Dashboard">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="16" height="16">
                        <path d="M19 12H5M12 19l-7-7 7-7" />
                    </svg>
                    <span>Kembali</span>
                </a>
            </nav>
        </div>
    </header>

    <!-- MAIN CONTENT -->
    <main class="admin-container">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: var(--stack-lg);">
            <h1 class="dashboard-title" style="margin-bottom: 0;">Kelola Status Layanan</h1>
        </div>

        <?php if (!empty($success_message)): ?>
            <div style="background: var(--status-ready-bg); color: var(--status-ready-text); border: 1px solid; border-radius: 8px; padding: 12px 16px; margin-bottom: var(--stack-md); display: flex; align-items: center; gap: 8px;">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="18" height="18"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                <?= htmlspecialchars($success_message) ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($error_message)): ?>
            <div style="background: var(--color-error-container); color: var(--color-error); border: 1px solid; border-radius: 8px; padding: 12px 16px; margin-bottom: var(--stack-md); display: flex; align-items: center; gap: 8px;">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="18" height="18"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                <?= htmlspecialchars($error_message) ?>
            </div>
        <?php endif; ?>


        <!-- LAYOUT UTAMA: FORM CRUD & TABEL HASIL -->
        <div class="dashboard-grid">

            <!-- FORM TAMBAH / EDIT -->
            <section class="dashboard-card" style="height: fit-content;">
                <header class="dashboard-card__header" style="display: flex; justify-content: space-between; align-items: center;">
                    <h2 class="dashboard-card__title"><?= $edit_data ? 'Edit Layanan' : 'Tambah Layanan Baru' ?></h2>
                    <?php if ($edit_data): ?>
                        <a href="kelola_status.php" class="btn-logout" style="padding: 4px 8px; font-size: 12px; text-decoration: none;">Batal Edit</a>
                    <?php endif; ?>
                </header>
                <div class="dashboard-card__body">
                    <form action="" method="POST" class="login-form">
                        <?= csrf_input() ?>
                        <input type="hidden" name="action" value="<?= $edit_data ? 'edit' : 'tambah' ?>">
                        <?php if ($edit_data): ?>
                            <input type="hidden" name="id_status" value="<?= htmlspecialchars($edit_data['id']) ?>">
                        <?php endif; ?>

                        <!-- Karena ini sistem CRUD penuh, nama layanan tidak di-hardcode di dropdown, tapi diketik manual -->
                        <div class="form-group">
                            <label class="form-label" for="nama_layanan">Nama Layanan</label>
                            <input class="form-input" type="text" id="nama_layanan" name="nama_layanan" required placeholder="Contoh: Cetak KIA" value="<?= htmlspecialchars($edit_data['nama_layanan'] ?? '') ?>">
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="status_layanan">Status Layanan</label>
                            <?php $status_aktif = $edit_data['status'] ?? ''; ?>
                            <select class="form-input" id="status_layanan" name="status_layanan" required>
                                <option value="Ready" <?= $status_aktif == 'ready' ? 'selected' : '' ?>>Ready (Normal)</option>
                                <option value="Gangguan" <?= $status_aktif == 'gangguan' ? 'selected' : '' ?>>Gangguan Teknis</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="catatan">Catatan Tambahan (Opsional)</label>
                            <textarea class="form-input" id="catatan" name="catatan" rows="3" style="height: auto; padding-top: 12px; resize: vertical;" placeholder="Contoh: Blangko habis..."><?= htmlspecialchars($edit_data['catatan'] ?? '') ?></textarea>
                        </div>

                        <button class="btn-primary" type="submit">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="18" height="18">
                                <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path>
                                <polyline points="17 21 17 13 7 13 7 21"></polyline>
                                <polyline points="7 3 7 8 15 8"></polyline>
                            </svg>
                            Simpan Data
                        </button>
                    </form>
                </div>
            </section>

            <!-- DAFTAR STATUS (TABEL/LIST) -->
            <section class="dashboard-card">
                <header class="dashboard-card__header">
                    <h2 class="dashboard-card__title">Daftar Status Saat Ini</h2>
                </header>
                <div class="dashboard-card__body" style="padding: 0;">



                    <ul class="admin-list" style="padding: 0 var(--stack-md);">
                        <?php foreach ($data_status as $row): ?>
                            <!-- baris data -->
                            <li style="padding: var(--stack-md) 0; flex-direction: column; align-items: stretch; gap: 8px;">
                                <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                                    <div class="admin-list__info">
                                        <strong style="font-size: 16px;"><?= htmlspecialchars(mb_convert_case($row["nama_layanan"], MB_CASE_TITLE, 'UTF-8'), ENT_QUOTES, 'UTF-8'); ?></strong>
                                        <span style="display: block; margin-top: 4px; font-size: 12px;">Waktu Kejadian: <?= htmlspecialchars($row["waktu_kejadian"], ENT_QUOTES, 'UTF-8'); ?></span>
                                        <span style="display: block; color: var(--color-on-surface-variant); margin-top: 4px; font-size: 14px; font-style: italic;">Catatan: <?= htmlspecialchars(ucfirst($row["catatan"]), ENT_QUOTES, 'UTF-8'); ?></span>
                                    </div>
                                    <div style="display: flex; flex-direction: column; align-items: flex-end; gap: 8px;">
                                        <?php if ($row["status"] === 'ready'): ?>
                                            <span style="color: var(--status-ready-text); background: var(--status-ready-bg); padding: 4px 12px; border-radius: 4px; font-size: 12px; font-weight: bold;">Ready</span>
                                        <?php else: ?>
                                            <span style="color: var(--status-gangguan-text); background: var(--status-gangguan-bg); padding: 2px 8px; border-radius: 4px; font-size: 12px; font-weight: bold;">Gangguan</span>
                                        <?php endif; ?>

                                        <div style="display: flex; gap: 8px; flex-direction:column;">
                                            <!-- Tombol Edit -->
                                            <a href="?edit=<?= (int) $row["id"]; ?>" class="btn-primary" style="padding: 4px 8px; font-size: 12px; height: auto; text-decoration: none; background-color: var(--color-primary);" title="Edit">Edit</a>

                                            <!-- Tombol Hapus (Kirim POST) -->
                                            <form action="" method="post" style="margin: 0;">
                                                <?= csrf_input() ?>
                                                <input type="hidden" name="action" value="hapus">
                                                <input type="hidden" name="id_status" value="<?= (int) $row["id"]; ?>">
                                                <button type="submit" class="btn-logout" style="padding: 4px 8px; font-size: 12px; height: 100%;" onclick="return confirm('Yakin ingin menghapus layanan ini?');" title="Hapus">Hapus</button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </section>
        </div>
    </main>
</body>

</html>
