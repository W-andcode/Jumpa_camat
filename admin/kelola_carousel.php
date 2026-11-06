<?php
require_once '../config/security.php';
start_secure_session();
if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true) {
    header('Location: ../login.php');
    exit;
}
require_once '../config/database.php';

// Semua kegagalan validasi/unggahan dikembalikan sebagai flash message agar
// halaman admin tetap utuh dan detail internal tidak tampil kepada pengguna.
set_exception_handler(static function (Throwable $exception): void {
    if ($exception instanceof InvalidArgumentException) {
        $_SESSION['flash_error'] = $exception->getMessage();
    } else {
        error_log('Kelola carousel gagal: ' . $exception->__toString());
        $_SESSION['flash_error'] = 'Terjadi kesalahan saat memproses carousel. Silakan coba kembali.';
    }
    header('Location: kelola_carousel.php');
    exit;
});

$success_message = $_SESSION['flash_success'] ?? '';
$error_message = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['action'])) {
    verify_csrf_or_fail();
    try {
    $action = (string) $_POST['action'];
    $title = '';
    $deskripsi = '';
    $alt_text = '';
    $aktif = 1;
    $urutan = 0;
    $focal_x = 50.0;
    $focal_y = 50.0;

    if (!in_array($action, ['tambah', 'edit', 'hapus'], true)) {
        throw new InvalidArgumentException('Aksi carousel tidak valid.');
    }

    if ($action === 'tambah' || $action === 'edit') {
        $title = post_string('title', 150);
        $deskripsi = post_string('deskripsi', 500);
        $alt_text = post_string('alt_text', 180);
        $aktif = (int) validate_choice((string) ($_POST['aktif'] ?? ''), ['0', '1'], 'Status slide tidak valid.');
        $urutan = filter_var($_POST['urutan'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
        if ($urutan === false || $urutan === null) {
            throw new InvalidArgumentException('Urutan slide harus berupa angka nol atau lebih.');
        }
        $focal_x = filter_var($_POST['focal_x'] ?? null, FILTER_VALIDATE_FLOAT);
        $focal_y = filter_var($_POST['focal_y'] ?? null, FILTER_VALIDATE_FLOAT);
        if ($focal_x === false || $focal_y === false
            || $focal_x < 0 || $focal_x > 100
            || $focal_y < 0 || $focal_y > 100) {
            throw new InvalidArgumentException('Posisi fokus gambar harus berada antara 0 dan 100 persen.');
        }
    }

    switch ($action) {
        case 'tambah':
            $gambar_baru = save_hero_image($_FILES['gambar'] ?? []);
            if ($gambar_baru === null) {
                throw new InvalidArgumentException('Gambar wajib dipilih untuk slide baru.');
            }
            try {
                $stmt = $conn->prepare(
                    'INSERT INTO hero_carousel (gambar_path, title, deskripsi, alt_text, aktif, urutan, focal_x, focal_y) VALUES (:gambar, :title, :deskripsi, :alt, :aktif, :urutan, :focal_x, :focal_y)'
                );
                $stmt->execute([
                    ':gambar' => $gambar_baru,
                    ':title' => $title,
                    ':deskripsi' => $deskripsi,
                    ':alt' => $alt_text,
                    ':aktif' => $aktif,
                    ':urutan' => $urutan,
                    ':focal_x' => $focal_x,
                    ':focal_y' => $focal_y,
                ]);
            } catch (Throwable $e) {
                delete_hero_image($gambar_baru);
                throw $e;
            }
            $_SESSION['flash_success'] = 'Slide carousel berhasil ditambahkan.';
            break;

        case 'edit':
            $id = post_positive_int('id');
            $stmt_lama = $conn->prepare('SELECT gambar_path FROM hero_carousel WHERE id = :id LIMIT 1');
            $stmt_lama->execute([':id' => $id]);
            $gambar_lama = $stmt_lama->fetchColumn();
            if ($gambar_lama === false) {
                throw new InvalidArgumentException('Slide yang akan diedit tidak ditemukan.');
            }
            $gambar_upload = save_hero_image($_FILES['gambar'] ?? []);
            $gambar_tersimpan = $gambar_upload ?? $gambar_lama;
            try {
                $stmt = $conn->prepare(
                    'UPDATE hero_carousel SET gambar_path=:gambar, title=:title, deskripsi=:deskripsi, alt_text=:alt, aktif=:aktif, urutan=:urutan, focal_x=:focal_x, focal_y=:focal_y WHERE id=:id'
                );
                $stmt->execute([
                    ':gambar' => $gambar_tersimpan,
                    ':title' => $title,
                    ':deskripsi' => $deskripsi,
                    ':alt' => $alt_text,
                    ':aktif' => $aktif,
                    ':urutan' => $urutan,
                    ':focal_x' => $focal_x,
                    ':focal_y' => $focal_y,
                    ':id' => $id,
                ]);
            } catch (Throwable $e) {
                delete_hero_image($gambar_upload);
                throw $e;
            }
            if ($gambar_upload !== null && $gambar_lama !== $gambar_upload) {
                delete_hero_image($gambar_lama);
            }
            $_SESSION['flash_success'] = 'Slide carousel berhasil diperbarui.';
            break;

        case 'hapus':
            $id = post_positive_int('id');
            $stmt_lama = $conn->prepare('SELECT gambar_path FROM hero_carousel WHERE id = :id LIMIT 1');
            $stmt_lama->execute([':id' => $id]);
            $gambar_lama = $stmt_lama->fetchColumn();
            $stmt = $conn->prepare('DELETE FROM hero_carousel WHERE id = :id');
            $stmt->execute([':id' => $id]);
            if ($stmt->rowCount() === 1) {
                delete_hero_image($gambar_lama ?: null);
                $_SESSION['flash_success'] = 'Slide carousel berhasil dihapus.';
            }
            break;
    }
    } catch (InvalidArgumentException $e) {
        $_SESSION['flash_error'] = $e->getMessage();
    } catch (Throwable $e) {
        error_log('Kelola carousel gagal: ' . $e->__toString());
        $_SESSION['flash_error'] = 'Terjadi kesalahan saat memproses carousel. Silakan coba kembali.';
    }
    header('Location: kelola_carousel.php');
    exit;
}

$edit_data = null;
if (isset($_GET['edit'])) {
    $edit_id = filter_var($_GET['edit'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($edit_id !== false) {
        $stmt = $conn->prepare('SELECT * FROM hero_carousel WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $edit_id]);
        $edit_data = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
}

$slides = $conn->query('SELECT * FROM hero_carousel ORDER BY urutan ASC, id ASC')->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Kelola Carousel — Portal Kecamatan Maron</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <style>
        .hero-focus-editor { margin-bottom: 16px; }
        .hero-focus-preview { position: relative; overflow: hidden; width: 100%; aspect-ratio: 16 / 6; border-radius: 12px; background: var(--color-surface-low); cursor: crosshair; }
        .hero-focus-preview img { width: 100%; height: 100%; display: block; object-fit: cover; }
        .hero-focus-target { position: absolute; width: 26px; height: 26px; border: 3px solid #fff; border-radius: 50%; background: rgba(27,94,32,.75); box-shadow: 0 2px 8px rgba(0,0,0,.35); transform: translate(-50%, -50%); pointer-events: none; }
        .hero-focus-target::before, .hero-focus-target::after { content: ''; position: absolute; background: #fff; }
        .hero-focus-target::before { width: 12px; height: 2px; left: 4px; top: 9px; }
        .hero-focus-target::after { width: 2px; height: 12px; left: 9px; top: 4px; }
        .hero-focus-controls { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-top: 12px; }
        .hero-focus-control label { display: flex; justify-content: space-between; gap: 8px; margin-bottom: 5px; font-size: 12px; font-weight: 600; }
        .hero-focus-control input { width: 100%; accent-color: var(--color-primary-container); }
        @media (max-width: 600px) { .hero-focus-controls { grid-template-columns: 1fr; } }
    </style>
</head>
<body class="admin-body">
    <header class="admin-header">
        <div class="admin-header__container">
            <a href="dashboard.php" class="admin-header__brand">Kelola Carousel</a>
            <nav class="admin-header__nav"><a href="dashboard.php" class="btn-logout">Kembali</a></nav>
        </div>
    </header>

    <main class="admin-container">
        <h1 class="dashboard-title">Kelola Carousel</h1>

        <?php if ($success_message): ?>
            <div class="alert-success" style="margin-bottom: var(--stack-md); padding: 12px 16px; border-radius: 8px; background: var(--status-ready-bg); color: var(--status-ready-text);"><?= htmlspecialchars($success_message, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>
        <?php if ($error_message): ?>
            <div class="alert-error" style="margin-bottom: var(--stack-md);"><?= htmlspecialchars($error_message, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>

        <div class="dashboard-grid">
            <section class="dashboard-card" style="height: fit-content;">
                <header class="dashboard-card__header">
                    <h2 class="dashboard-card__title"><?= $edit_data ? 'Edit Slide' : 'Tambah Slide' ?></h2>
                    <?php if ($edit_data): ?><a href="kelola_carousel.php" class="btn-logout" style="padding: 4px 8px; text-decoration: none;">Batal Edit</a><?php endif; ?>
                </header>
                <div class="dashboard-card__body">
                    <form method="POST" enctype="multipart/form-data" class="login-form">
                        <?= csrf_input() ?>
                        <input type="hidden" name="action" value="<?= $edit_data ? 'edit' : 'tambah' ?>">
                        <?php if ($edit_data): ?><input type="hidden" name="id" value="<?= (int) $edit_data['id'] ?>"><?php endif; ?>

                        <?php
                        $focal_x_value = max(0, min(100, (float) ($edit_data['focal_x'] ?? 50)));
                        $focal_y_value = max(0, min(100, (float) ($edit_data['focal_y'] ?? 50)));
                        ?>
                        <div class="hero-focus-editor" id="heroFocusEditor" <?= $edit_data ? '' : 'hidden' ?>>
                            <div class="hero-focus-preview" id="heroFocusPreview" title="Klik gambar untuk menentukan titik fokus">
                                <img
                                    id="heroPreviewImage"
                                    src="<?= $edit_data ? '../' . htmlspecialchars($edit_data['gambar_path'], ENT_QUOTES, 'UTF-8') : '' ?>"
                                    alt="Preview posisi gambar carousel"
                                    style="object-position: <?= $focal_x_value ?>% <?= $focal_y_value ?>%;"
                                >
                                <span class="hero-focus-target" id="heroFocusTarget" style="left:<?= $focal_x_value ?>%;top:<?= $focal_y_value ?>%;" aria-hidden="true"></span>
                            </div>
                            <div class="hero-focus-controls">
                                <div class="hero-focus-control">
                                    <label for="focal_x">Posisi horizontal <output id="focalXOutput"><?= round($focal_x_value) ?>%</output></label>
                                    <input type="range" id="focal_x" name="focal_x" min="0" max="100" step="1" value="<?= $focal_x_value ?>">
                                </div>
                                <div class="hero-focus-control">
                                    <label for="focal_y">Posisi vertikal <output id="focalYOutput"><?= round($focal_y_value) ?>%</output></label>
                                    <input type="range" id="focal_y" name="focal_y" min="0" max="100" step="1" value="<?= $focal_y_value ?>">
                                </div>
                            </div>
                            <button type="button" class="btn-logout" id="resetHeroFocus" style="margin-top:10px;padding:6px 10px;">Atur ke Tengah</button>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="gambar">Gambar <?= $edit_data ? 'Baru (Opsional)' : '' ?></label>
                            <input class="form-input" type="file" id="gambar" name="gambar" style="border: none;" accept="image/jpeg,image/png,image/webp" <?= $edit_data ? '' : 'required' ?>>
                            <small style="display:block;margin-top:4px;color:var(--color-on-surface-variant);">JPEG, PNG, atau WebP; maksimal 5 MB. Setelah dipilih, klik preview untuk menentukan bagian utama gambar.</small>
                        </div>
                        <div class="form-group"><label class="form-label" for="title">Judul</label><input class="form-input" id="title" name="title" maxlength="150" required value="<?= htmlspecialchars($edit_data['title'] ?? '', ENT_QUOTES, 'UTF-8') ?>"></div>
                        <div class="form-group"><label class="form-label" for="deskripsi">Deskripsi</label><textarea class="form-input" id="deskripsi" name="deskripsi" maxlength="500" rows="4" required style="height:auto;"><?= htmlspecialchars($edit_data['deskripsi'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea></div>
                        <div class="form-group"><label class="form-label" for="alt_text">Teks Alternatif Gambar</label><input class="form-input" id="alt_text" name="alt_text" maxlength="180" required value="<?= htmlspecialchars($edit_data['alt_text'] ?? '', ENT_QUOTES, 'UTF-8') ?>"><small style="display:block;margin-top:4px;color:var(--color-on-surface-variant);">Jelaskan isi gambar untuk aksesibilitas.</small></div>
                        <div class="form-group"><label class="form-label" for="urutan">Urutan</label><input class="form-input" type="number" id="urutan" name="urutan" min="0" required value="<?= (int) ($edit_data['urutan'] ?? count($slides) + 1) ?>"></div>
                        <div class="form-group"><label class="form-label" for="aktif">Status</label><select class="form-input" id="aktif" name="aktif" required><option value="1" <?= (int) ($edit_data['aktif'] ?? 1) === 1 ? 'selected' : '' ?>>Aktif</option><option value="0" <?= isset($edit_data['aktif']) && (int) $edit_data['aktif'] === 0 ? 'selected' : '' ?>>Tidak Aktif</option></select></div>
                        <button class="btn-primary" type="submit">Simpan Slide</button>
                    </form>
                </div>
            </section>

            <section class="dashboard-card">
                <header class="dashboard-card__header"><h2 class="dashboard-card__title">Daftar Slide (<?= count($slides) ?>)</h2></header>
                <div class="dashboard-card__body" style="padding: 0;">
                    <ul class="admin-list" style="padding: 0 var(--stack-md);">
                        <?php foreach ($slides as $slide): ?>
                            <li style="padding: var(--stack-md) 0; align-items: flex-start; gap: 12px;">
                                <img src="../<?= htmlspecialchars($slide['gambar_path'], ENT_QUOTES, 'UTF-8') ?>" alt="" style="width: 110px; height: 68px; object-fit: cover; border-radius: 8px; flex: 0 0 110px;">
                                <div class="admin-list__info">
                                    <strong><?= htmlspecialchars($slide['title'], ENT_QUOTES, 'UTF-8') ?></strong>
                                    <span style="display:block;margin-top:4px;font-size:12px;color:var(--color-on-surface-variant);">Urutan <?= (int) $slide['urutan'] ?> · <?= (int) $slide['aktif'] === 1 ? 'Aktif' : 'Tidak aktif' ?> · Fokus <?= round((float) $slide['focal_x']) ?>% / <?= round((float) $slide['focal_y']) ?>%</span>
                                </div>
                                <div style="display:flex;flex-direction:column;gap:8px;">
                                    <a href="?edit=<?= (int) $slide['id'] ?>" class="btn-primary" style="padding:5px 9px;height:auto;font-size:12px;text-decoration:none;">Edit</a>
                                    <form method="POST" style="margin:0;">
                                        <?= csrf_input() ?>
                                        <input type="hidden" name="action" value="hapus">
                                        <input type="hidden" name="id" value="<?= (int) $slide['id'] ?>">
                                        <button class="btn-logout" type="submit" style="padding:5px 9px;height:auto;font-size:12px;" onclick="return confirm('Yakin ingin menghapus slide ini?');">Hapus</button>
                                    </form>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </section>
        </div>
    </main>
    <script>
        const heroFileInput = document.getElementById('gambar');
        const focusEditor = document.getElementById('heroFocusEditor');
        const focusPreview = document.getElementById('heroFocusPreview');
        const previewImage = document.getElementById('heroPreviewImage');
        const focusTarget = document.getElementById('heroFocusTarget');
        const focalX = document.getElementById('focal_x');
        const focalY = document.getElementById('focal_y');
        const focalXOutput = document.getElementById('focalXOutput');
        const focalYOutput = document.getElementById('focalYOutput');
        const resetFocus = document.getElementById('resetHeroFocus');

        function updateHeroFocus() {
            const x = Number(focalX.value);
            const y = Number(focalY.value);
            previewImage.style.objectPosition = `${x}% ${y}%`;
            focusTarget.style.left = `${x}%`;
            focusTarget.style.top = `${y}%`;
            focalXOutput.value = `${Math.round(x)}%`;
            focalYOutput.value = `${Math.round(y)}%`;
        }

        heroFileInput.addEventListener('change', () => {
            const file = heroFileInput.files && heroFileInput.files[0];
            if (!file) return;
            const reader = new FileReader();
            reader.addEventListener('load', event => {
                previewImage.src = event.target.result;
                focusEditor.hidden = false;
                updateHeroFocus();
            });
            reader.readAsDataURL(file);
        });

        focusPreview.addEventListener('click', event => {
            const rect = focusPreview.getBoundingClientRect();
            focalX.value = Math.max(0, Math.min(100, ((event.clientX - rect.left) / rect.width) * 100));
            focalY.value = Math.max(0, Math.min(100, ((event.clientY - rect.top) / rect.height) * 100));
            updateHeroFocus();
        });
        focalX.addEventListener('input', updateHeroFocus);
        focalY.addEventListener('input', updateHeroFocus);
        resetFocus.addEventListener('click', () => {
            focalX.value = 50;
            focalY.value = 50;
            updateHeroFocus();
        });
        updateHeroFocus();
    </script>
</body>
</html>
