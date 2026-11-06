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

// proses CRUD
// kondisi adakah outpun dari form atau html
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['action'])) {
    verify_csrf_or_fail();
    $actions = $_POST['action'];
    switch ($actions) {
        case 'tambah':
            // insert row
            $tanggal = validate_date_value(post_string('tanggal', 10));
            $jam_mulai = validate_time_value(post_string('jam_mulai', 5));
            $jam_selesai = validate_time_value(post_string('jam_selesai', 5));
            if ($jam_selesai <= $jam_mulai) {
                $_SESSION['flash_error'] = 'Jam selesai harus setelah jam mulai.';
                header('Location: kelola_jadwal.php');
                exit;
            }
            // Ubah string menjadi huruf kecil semua menggunakan fungsi modern yang mendukung karakter multibyte (UTF-8)
            $kegiatan = validate_choice(
                mb_strtolower(post_string('jenis_kegiatan', 30), 'UTF-8'),
                ['tidak ada di kantor', 'ada di kantor', 'cuti', 'lainnya....'],
                'Jenis kegiatan tidak valid.'
            );
            $keterangan = mb_strtolower(post_string('keterangan', 2000), 'UTF-8');
            $dibuat_oleh = $id_admin;

            $stmt = $conn->prepare("INSERT INTO jadwal_camat (tanggal, jam_mulai, jam_selesai, jenis_kegiatan, keterangan, dibuat_oleh) VALUES (:tanggal, :mulai, :selesai, :kegiatan, :ket, :dibuat_oleh)");
            // jalankan insert
            $stmt->execute([
                ':tanggal' => $tanggal,
                ':mulai' => $jam_mulai,
                ':selesai' => $jam_selesai,
                ':kegiatan' => $kegiatan,
                ':ket' => $keterangan,
                ':dibuat_oleh' => $dibuat_oleh
            ]);
            $_SESSION['flash_success'] = 'Jadwal berhasil ditambahkan.';
            header('Location: kelola_jadwal.php');
            exit();
        case 'hapus':
            // ambil id
            $id = post_positive_int('id_jadwal');
            // sql
            $stmt = $conn->prepare("DELETE FROM jadwal_camat WHERE id= :id");
            $stmt->execute([
                ':id' => $id
            ]);
            $_SESSION['flash_success'] = 'Jadwal berhasil dihapus.';
            header('Location: kelola_jadwal.php');
            exit();
        case 'edit':
            $id = post_positive_int('id_jadwal');
            $tanggal = validate_date_value(post_string('tanggal', 10));
            $jam_mulai = validate_time_value(post_string('jam_mulai', 5));
            $jam_selesai = validate_time_value(post_string('jam_selesai', 5));
            if ($jam_selesai <= $jam_mulai) {
                $_SESSION['flash_error'] = 'Jam selesai harus setelah jam mulai.';
                header('Location: kelola_jadwal.php?edit=' . $id);
                exit;
            }
            // Ubah string menjadi huruf kecil semua menggunakan mb_strtolower
            $kegiatan = validate_choice(
                mb_strtolower(post_string('jenis_kegiatan', 30), 'UTF-8'),
                ['tidak ada di kantor', 'ada di kantor', 'cuti', 'lainnya....'],
                'Jenis kegiatan tidak valid.'
            );
            $keterangan = mb_strtolower(post_string('keterangan', 2000), 'UTF-8');

            $stmt = $conn->prepare("UPDATE jadwal_camat SET tanggal=:tanggal, jam_mulai=:mulai, jam_selesai=:selesai, jenis_kegiatan=:kegiatan, keterangan=:ket WHERE id=:id");
            $stmt->execute([
                ':tanggal' => $tanggal,
                ':mulai' => $jam_mulai,
                ':selesai' => $jam_selesai,
                ':kegiatan' => $kegiatan,
                ':ket' => $keterangan,
                ':id' => $id
            ]);
            $_SESSION['flash_success'] = 'Jadwal berhasil diperbarui.';
            header('Location: kelola_jadwal.php');
            exit();
    }
}

// Ambil data untuk form edit jika ada parameter ?edit=
$edit_data = null;
if (isset($_GET['edit']) && filter_var($_GET['edit'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])) {
    $stmt_edit = $conn->prepare("SELECT * FROM jadwal_camat WHERE id = :id");
    $stmt_edit->execute([':id' => $_GET['edit']]);
    $edit_data = $stmt_edit->fetch(PDO::FETCH_ASSOC);
}

// query data
$stmt = $conn->query("SELECT * FROM jadwal_camat ORDER BY created_at DESC");
$jadwal = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Jadwal Camat — Portal Kecamatan Maron</title>
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
            <h1 class="dashboard-title" style="margin-bottom: 0;">Kelola Jadwal Camat</h1>
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

        <div class="dashboard-grid">
            <!-- Form Tambah Jadwal -->
            <section class="dashboard-card" style="height: fit-content;">
                <header class="dashboard-card__header" style="display: flex; justify-content: space-between; align-items: center;">
                    <h2 class="dashboard-card__title"><?= $edit_data ? 'Edit Jadwal' : 'Tambah Jadwal Baru' ?></h2>
                    <?php if ($edit_data): ?>
                        <a href="kelola_jadwal.php" class="btn-logout" style="padding: 4px 8px; font-size: 12px; text-decoration: none;">Batal Edit</a>
                    <?php endif; ?>
                </header>
                <div class="dashboard-card__body">
                    <form action="" method="POST" class="login-form">
                        <?= csrf_input() ?>
                        <input type="hidden" name="action" value="<?= $edit_data ? 'edit' : 'tambah' ?>">
                        <?php if ($edit_data): ?>
                            <input type="hidden" name="id_jadwal" value="<?= htmlspecialchars($edit_data['id']) ?>">
                        <?php endif; ?>

                        <div class="form-group">
                            <label class="form-label" for="tanggal">Tanggal</label>
                            <input class="form-input" type="date" id="tanggal" name="tanggal" value="<?= htmlspecialchars($edit_data['tanggal'] ?? '') ?>" required>
                        </div>

                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--stack-md);">
                            <div class="form-group">
                                <label class="form-label" for="jam_mulai">Jam Mulai</label>
                                <input class="form-input" type="time" id="jam_mulai" name="jam_mulai" value="<?= htmlspecialchars($edit_data['jam_mulai'] ?? '') ?>" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label" for="jam_selesai">Jam Selesai</label>
                                <input class="form-input" type="time" id="jam_selesai" name="jam_selesai" value="<?= htmlspecialchars($edit_data['jam_selesai'] ?? '') ?>" required>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="jenis_kegiatan">Jenis Kegiatan</label>
                            <?php $kegiatan_aktif = $edit_data['jenis_kegiatan'] ?? ''; ?>
                            <select class="form-input" id="jenis_kegiatan" name="jenis_kegiatan" required>
                                <option value="" disabled <?= empty($kegiatan_aktif) ? 'selected' : '' ?>>Pilih jenis kegiatan...</option>
                                <option value="tidak ada di kantor" <?= $kegiatan_aktif == 'tidak ada di kantor' ? 'selected' : '' ?>>tidak ada kantor</option>
                                <option value="ada di kantor" <?= $kegiatan_aktif == 'ada di kantor' ? 'selected' : '' ?>>ada di kantor</option>
                                <option value="cuti" <?= $kegiatan_aktif === 'cuti' ? 'selected' : '' ?>>Cuti</option>
                                <option value="lainnya...." <?= $kegiatan_aktif === 'lainnya....' ? 'selected' : '' ?>>Lainnya...</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="keterangan">Keterangan (Lokasi / Acara)</label>
                            <textarea class="form-input" id="keterangan" name="keterangan" rows="3" style="height: auto; padding-top: 12px; resize: vertical;" required placeholder="Contoh: Rapat Paripurna di Pemkab..."><?= htmlspecialchars($edit_data['keterangan'] ?? '') ?></textarea>
                        </div>

                        <button class="btn-primary" type="submit">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="18" height="18">
                                <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path>
                                <polyline points="17 21 17 13 7 13 7 21"></polyline>
                                <polyline points="7 3 7 8 15 8"></polyline>
                            </svg>
                            Simpan Jadwal
                        </button>
                    </form>
                </div>
            </section>

            <!-- Daftar Jadwal -->
            <section class="dashboard-card">
                <header class="dashboard-card__header">
                    <h2 class="dashboard-card__title">Daftar Jadwal (Mendatang)</h2>
                </header>
                <div class="dashboard-card__body" style="padding: 0;">
                    <!-- Placeholder Data / TUGAS BACKEND -->
                    <ul class="admin-list" style="padding: 0 var(--stack-md);">
                        <!-- read data -->
                        <?php foreach ($jadwal as $row): ?>
                            <li style="padding: var(--stack-md) 0; flex-direction: column; align-items: stretch; gap: 8px;">
                                <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                                    <div>
                                        <span style="font-size: 12px; font-weight: bold; color: var(--color-primary-container);"><?= htmlspecialchars($row["tanggal"], ENT_QUOTES, 'UTF-8') ?></span>
                                        <!-- mb_convert_case(..., MB_CASE_TITLE) mengubah awal setiap kata menjadi huruf besar (Title Case) -->
                                        <strong style="font-size: 16px;"><?= htmlspecialchars(mb_convert_case($row["jenis_kegiatan"], MB_CASE_TITLE, 'UTF-8'), ENT_QUOTES, 'UTF-8'); ?></strong>
                                        
                                        <!-- ucfirst() mengubah hanya huruf paling pertama dalam satu kalimat menjadi huruf besar (Sentence Case) -->
                                        <span style="display: block; font-size: 14px; margin-top: 4px;"><?= htmlspecialchars(ucfirst($row["keterangan"]), ENT_QUOTES, 'UTF-8'); ?> </span>
                                        <span style="display: block; font-size: 14px; margin-top: 4px;"><?= htmlspecialchars($row["jam_mulai"], ENT_QUOTES, 'UTF-8'); ?> - <?= htmlspecialchars($row["jam_selesai"], ENT_QUOTES, 'UTF-8'); ?></span>
                                    </div>
                                    <div style="display: flex; gap: 5px; flex-direction: column; align-items: center;">
                                        <a href="?edit=<?= (int) $row['id'] ?>" class="btn-primary" style="padding: 4px 8px; font-size: 12px; height: 100%; text-decoration: none; background-color: var(--color-primary);" title="Edit Jadwal">Edit</a>
                                        <form action="" method="post" style="margin: 0;">
                                            <?= csrf_input() ?>
                                            <input type="hidden" name="action" value="hapus">
                                            <input type="hidden" name="id_jadwal" value="<?= (int) $row['id'] ?>">
                                            <button class="btn-logout" style="padding: 4px 8px; font-size: 12px; height: 100%;" title="Hapus" onclick="return confirm('Yakin ingin menghapus jadwal ini?');">Hapus</button>
                                        </form>
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
