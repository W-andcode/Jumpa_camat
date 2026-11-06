<?php
require_once '../config/security.php';
start_secure_session();
if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true) {
    header('Location: ../login.php');
    exit;
}
require_once '../config/database.php';
$nama_admin = $_SESSION['user_nama'] ?? 'Admin';

// Ambil flash message dari session (jika ada setelah redirect)
$success_message = $_SESSION['flash_success'] ?? '';
$error_message   = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['action'])) {
    verify_csrf_or_fail();
    $action = $_POST['action'];
    switch ($action) {
        case 'tambah':
            // Nomor WA tidak perlu mb_strtolower — murni angka, langsung cast ke int
            $nama = mb_strtolower(post_string('nama_pelayanan', 100), 'UTF-8');
            $no = preg_replace('/\D/', '', post_string('nomor_wa', 20));
            if (!preg_match('/^[0-9]{10,14}$/', $no)) {
                throw new InvalidArgumentException('Nomor WhatsApp harus terdiri dari 10 sampai 14 digit.');
            }
            $status = (int) validate_choice((string) ($_POST['status_aktif'] ?? ''), ['0', '1'], 'Status kontak tidak valid.');

            // INSERT INTO data
            $gambar_baru = save_contact_image($_FILES['gambar_profil'] ?? []);
            try {
                $stmt_add = $conn->prepare(
                    "INSERT INTO kontak_layanan (nama, no_layanan, gambar_profil, aktif) VALUES (:nama, :no, :gambar, :status)"
                );
                $stmt_add->execute([
                    ':nama' => $nama,
                    ':no' => $no,
                    ':gambar' => $gambar_baru,
                    ':status' => $status,
                ]);
            } catch (Throwable $e) {
                delete_contact_image($gambar_baru);
                throw $e;
            }
            $_SESSION['flash_success'] = 'Kontak "' . htmlspecialchars($nama) . '" berhasil ditambahkan.';
            break;
        case 'hapus':
            // ambil id
            $id_del = post_positive_int('id');
            $stmt_gambar = $conn->prepare("SELECT gambar_profil FROM kontak_layanan WHERE id = :id LIMIT 1");
            $stmt_gambar->execute([':id' => $id_del]);
            $gambar_lama = $stmt_gambar->fetchColumn() ?: null;
            $stmt_del = $conn->prepare("DELETE FROM kontak_layanan WHERE id=:id");
            $stmt_del->execute([':id' => $id_del]);
            if ($stmt_del->rowCount() === 1) {
                delete_contact_image($gambar_lama);
            }
            $_SESSION['flash_success'] = 'Kontak berhasil dihapus.';
            break;
        case 'edit':
            // ambil id edit data — field name harus 'id' sesuai form HTML
            $id_edit = post_positive_int('id');
            // ambil data after edit
            $nama = mb_strtolower(post_string('nama_pelayanan', 100), 'UTF-8');
            $no = preg_replace('/\D/', '', post_string('nomor_wa', 20));
            if (!preg_match('/^[0-9]{10,14}$/', $no)) {
                throw new InvalidArgumentException('Nomor WhatsApp harus terdiri dari 10 sampai 14 digit.');
            }
            $status = (int) validate_choice((string) ($_POST['status_aktif'] ?? ''), ['0', '1'], 'Status kontak tidak valid.');

            // Perbaikan bug: tambahkan WHERE id=:id agar tidak mengupdate seluruh tabel
            $stmt_lama = $conn->prepare("SELECT gambar_profil FROM kontak_layanan WHERE id = :id LIMIT 1");
            $stmt_lama->execute([':id' => $id_edit]);
            $data_lama = $stmt_lama->fetch(PDO::FETCH_ASSOC);
            if (!$data_lama) {
                throw new InvalidArgumentException('Kontak yang akan diedit tidak ditemukan.');
            }
            $gambar_lama = $data_lama['gambar_profil'] ?: null;
            $gambar_upload = save_contact_image($_FILES['gambar_profil'] ?? []);
            $hapus_gambar = isset($_POST['hapus_gambar']) && $_POST['hapus_gambar'] === '1';
            $gambar_tersimpan = $gambar_upload ?? ($hapus_gambar ? null : $gambar_lama);
            try {
                $stmt_edit = $conn->prepare(
                    "UPDATE kontak_layanan SET nama=:nama, no_layanan=:no, gambar_profil=:gambar, aktif=:status WHERE id=:id"
                );
                $stmt_edit->execute([
                    ':nama' => $nama,
                    ':no' => $no,
                    ':gambar' => $gambar_tersimpan,
                    ':status' => $status,
                    ':id' => $id_edit,
                ]);
            } catch (Throwable $e) {
                delete_contact_image($gambar_upload);
                throw $e;
            }
            if ($gambar_lama !== $gambar_tersimpan) {
                delete_contact_image($gambar_lama);
            }
            $_SESSION['flash_success'] = 'Kontak berhasil diperbarui.';
            break;
    }
    header('Location: kelola_kontak.php');
    exit();
}

// Cek apakah mode Edit sedang aktif
$edit_data = null;
if (isset($_GET['edit']) && filter_var($_GET['edit'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])) {
    $id = $_GET['edit'];    
    $stmt = $conn->prepare("SELECT * FROM kontak_layanan WHERE id=:id");
    $stmt->execute([':id' => $id]);
    $edit_data = $stmt->fetch(PDO::FETCH_ASSOC);
}

// query data
$stmt = $conn->query("SELECT * FROM kontak_layanan ORDER BY id DESC");
$contaks = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Kontak WhatsApp — Portal Kecamatan Maron</title>
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
            <h1 class="dashboard-title" style="margin-bottom: 0;">Kelola Kontak Pelayanan</h1>
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
            <!-- Form Tambah/Ubah Kontak -->
            <section class="dashboard-card" style="height: fit-content;">
                <header class="dashboard-card__header">
                    <h2 class="dashboard-card__title"><?= $edit_data ? 'Edit Kontak' : 'Tambah data' ?></h2>
                    <?php if ($edit_data): ?>
                        <a href="kelola_kontak.php" class="btn-logout" style="padding: 4px 8px; font-size: 12px; text-decoration: none;">Batal Edit</a>
                    <?php endif; ?>
                </header>
                <div class="dashboard-card__body">
                    <form action="" method="POST" enctype="multipart/form-data" class="login-form">
                        <?= csrf_input() ?>
                        <input type="hidden" name="action" value="<?= $edit_data ? 'edit': 'tambah'; ?>">
                        <?php if ($edit_data): ?>
                            <!-- Perbaikan bug: nama field harus 'id' agar terbaca $_POST['id'] di PHP -->
                            <input type="hidden" name="id" value="<?= (int)$edit_data['id'] ?>">
                        <?php endif; ?>
                        <div class="form-group">
                            <label class="form-label" for="nama_pelayanan">Nama Pelayanan / Loket</label>
                            <input class="form-input" type="text" id="nama_pelayanan" name="nama_pelayanan" required placeholder="Contoh: CS Adminduk 1" value="<?= htmlspecialchars($edit_data['nama'] ?? '') ?>">
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="nomor_wa">Nomor WhatsApp</label>
                            <input class="form-input" type="text" id="nomor_wa" name="nomor_wa" required placeholder="Contoh: 081234567890" value="<?= htmlspecialchars($edit_data['no_layanan'] ?? '') ?>">
                            <small style="color: var(--color-on-surface-variant); margin-top: 4px;">Gunakan angka saja, dimulai dengan 0 atau 62.</small>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="gambar_profil">Gambar Profil (Opsional)</label>
                            <?php if (!empty($edit_data['gambar_profil'])): ?>
                                <img src="../<?= htmlspecialchars($edit_data['gambar_profil'], ENT_QUOTES, 'UTF-8') ?>" alt="Gambar profil saat ini" style="display: block; width: 72px; height: 72px; object-fit: cover; border-radius: 50%; margin-bottom: 10px; border: 2px solid var(--color-outline-variant);">
                            <?php endif; ?>
                            <input class="form-input" style="border: none;" type="file" id="gambar_profil" name="gambar_profil" accept="image/jpeg,image/png,image/webp">
                            <small style="display: block; color: var(--color-on-surface-variant); margin-top: 4px;">JPEG, PNG, atau WebP; maksimal 2 MB; dimensi 200–3000 piksel.</small>
                            <?php if (!empty($edit_data['gambar_profil'])): ?>
                                <label style="display: flex; align-items: center; gap: 8px; margin-top: 10px; font-size: 13px;">
                                    <input type="checkbox" name="hapus_gambar" value="1">
                                    Hapus gambar saat ini dan gunakan ikon WhatsApp
                                </label>
                            <?php endif; ?>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="status_aktif">Status Nomor</label>
                            <?php $status_aktif = (int)($edit_data['aktif'] ?? 1); ?>
                            <select class="form-input" id="status_aktif" name="status_aktif" required>
                                <option value="1" <?= $status_aktif === 1 ? 'selected' : '' ?>>Aktif (Tampilkan ke Publik)</option>
                                <option value="0" <?= $status_aktif === 0 ? 'selected' : '' ?>>Tidak Aktif (Sembunyikan)</option>
                            </select>
                        </div>

                        <button class="btn-primary" type="submit">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="18" height="18">
                                <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path>
                                <polyline points="17 21 17 13 7 13 7 21"></polyline>
                                <polyline points="7 3 7 8 15 8"></polyline>
                            </svg>
                            Simpan Kontak
                        </button>
                    </form>
                </div>
            </section>

            <!-- Daftar Kontak Saat Ini -->
            <section class="dashboard-card">
                <header class="dashboard-card__header">
                    <h2 class="dashboard-card__title">Daftar Kontak</h2>
                </header>
                <div class="dashboard-card__body" style="padding: 0;">
                    <!-- Placeholder Data / TUGAS BACKEND -->
                    <?php foreach ($contaks as $row): ?>
                        <ul class="admin-list" style="padding: 0 var(--stack-md);">
                            <li style="padding: var(--stack-md) 0; align-items: flex-start; gap: 12px;">
                                <?php if (!empty($row['gambar_profil'])): ?>
                                    <img src="../<?= htmlspecialchars($row['gambar_profil'], ENT_QUOTES, 'UTF-8') ?>" alt="Profil <?= htmlspecialchars($row['nama'], ENT_QUOTES, 'UTF-8') ?>" style="width: 48px; height: 48px; flex: 0 0 48px; object-fit: cover; border-radius: 50%; border: 1px solid var(--color-outline-variant);">
                                <?php endif; ?>
                                <div class="admin-list__info">
                                    <strong style="font-size: 16px;"><?= htmlspecialchars($row['nama'], ENT_QUOTES, 'UTF-8'); ?></strong>
                                    <span style="display: block; font-size: 16px; margin-top: 4px;"><?= htmlspecialchars($row['no_layanan'], ENT_QUOTES, 'UTF-8') ?></span>
                                    <?php if ($row['aktif'] === 1): ?>
                                        <span style="display: inline-block; margin-top: 8px; color: var(--color-on-primary); background: var(--color-primary-container); padding: 2px 8px; border-radius: 4px; font-size: 12px; font-weight: bold;">Aktif</span>
                                    <?php else: ?>
                                        <span style="display: inline-block; margin-top: 8px; color: var(--color-error); background: var(--color-error-container); padding: 2px 8px; border-radius: 4px; font-size: 12px; font-weight: bold;">Tidak Aktif</span>
                                    <?php endif; ?>
                                </div>
                                <div style="display: flex; gap: 8px; flex-direction: column;">
                                    <!-- Tombol Edit -->
                                    <a href="?edit=<?= (int) $row["id"]; ?>" class="btn-primary" style="padding: 4px 8px; font-size: 12px; height: auto; text-decoration: none; background-color: var(--color-primary);" title="Edit">Edit</a>

                                    <!-- Tombol Hapus (Kirim POST) -->
                                    <form action="" method="post" style="margin: 0;">
                                        <?= csrf_input() ?>
                                        <input type="hidden" name="action" value="hapus">
                                        <!-- Perbaikan bug: nama field harus 'id' agar terbaca $_POST['id'] di PHP -->
                                        <input type="hidden" name="id" value="<?= (int)$row["id"]; ?>">
                                        <button type="submit" class="btn-logout" style="padding: 4px 8px; font-size: 12px; height: 100%;" onclick="return confirm('Yakin ingin menghapus layanan ini?');" title="Hapus">Hapus</button>
                                    </form>
                                </div>
                            </li>
                        </ul>
                    <?php endforeach; ?>
                </div>
            </section>
        </div>
    </main>
</body>

</html>
