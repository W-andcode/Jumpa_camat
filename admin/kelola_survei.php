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

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['update'])) {
    verify_csrf_or_fail();
    $id = post_positive_int('id_row');
    $url = validate_http_url(post_string('url_survei', 255));
    $judul = mb_strtolower(post_string('judul_survei', 255, false), 'UTF-8');
    
    $stmt = $conn->prepare("UPDATE link_survei SET judul=:judul, url=:url WHERE id=:id");
    $stmt->execute([
        ':id'=>$id,
        ':url'=>$url,
        ':judul'=>$judul
    ]);
    $_SESSION['flash_success'] = 'Link survei berhasil diperbarui.';
    header('Location: kelola_survei.php');
    exit();
}

// query data from db
$stmt = $conn->query("SELECT * FROM link_survei LIMIT 1");
$data = $stmt->fetch(PDO::FETCH_ASSOC);

// Jika tabel kosong, inisialisasi array kosong agar tidak error saat diakses
if (!$data) {
    $data = ['id' => null, 'url' => '', 'judul' => ''];
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Link Survei — Portal Kecamatan Maron</title>
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
            <h1 class="dashboard-title" style="margin-bottom: 0;">Kelola Link Survei</h1>
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
            <!-- Form Edit Link Survei -->
            <section class="dashboard-card" style="height: fit-content;">
                <header class="dashboard-card__header">
                    <h2 class="dashboard-card__title">Perbarui URL Survei</h2>
                </header>
                <div class="dashboard-card__body">
                    <form action="" method="POST" class="login-form">
                        <?= csrf_input() ?>
                        <?php if (!empty($data['id'])): ?>
                            <input type="hidden" name="id_row" value="<?= (int) $data['id']; ?>">
                        <?php endif; ?>
                        <div class="form-group">
                            <label class="form-label" for="url_survei">Tautan Baru (URL Google Form dll)</label>
                            <input class="form-input" type="url" id="url_survei" name="url_survei" required value="<?= htmlspecialchars($data['url'] ?? ''); ?>">
                            <small style="color: var(--color-on-surface-variant); margin-top: 4px;">Pastikan link valid dan diawali dengan https://</small>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="judul_survei">Judul Survei (Opsional)</label>
                            <input class="form-input" type="text" id="judul_survei" name="judul_survei" value="<?= htmlspecialchars($data['judul'] ?? ''); ?>">
                        </div>

                        <button class="btn-primary" type="submit" name="update">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="18" height="18">
                                <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path>
                                <polyline points="17 21 17 13 7 13 7 21"></polyline>
                                <polyline points="7 3 7 8 15 8"></polyline>
                            </svg>
                            Simpan Perubahan
                        </button>
                    </form>
                </div>
            </section>

            <!-- Status URL Saat Ini -->
            <section class="dashboard-card">
                <header class="dashboard-card__header">
                    <h2 class="dashboard-card__title">Link Aktif Saat Ini</h2>
                </header>
                <div class="dashboard-card__body" style="padding: 0;">
                    <!-- Placeholder Data / TUGAS BACKEND -->
                    <div style="padding: var(--stack-lg); text-align: center;">
                        <svg viewBox="0 0 24 24" width="48" height="48" fill="none" stroke="var(--color-primary-container)" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="margin-bottom: var(--stack-sm);">
                            <path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"></path>
                            <path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"></path>
                        </svg>
                        <h3 style="font-size: var(--fs-body-lg); margin-bottom: 8px;">Survei Kepuasan Masyarakat 2026</h3>
                        <a href="<?= htmlspecialchars($data["url"] ?? '', ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer" style="color: var(--color-primary); word-break: break-all; text-decoration: underline;">
                            <?= htmlspecialchars($data["judul"] ?? '', ENT_QUOTES, 'UTF-8'); ?>
                        </a>
                        <p style="margin-top: 16px; font-size: 14px; color: var(--color-on-surface-variant);">
                            Tautan ini yang saat ini terpasang di halaman publik (index.php).
                        </p>
                    </div>
                </div>
            </section>
        </div>
    </main>
</body>

</html>
