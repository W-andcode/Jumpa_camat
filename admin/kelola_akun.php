<?php
require_once '../config/security.php';
start_secure_session();
if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true) {
    header('Location: ../login.php');
    exit;
}
require_once '../config/database.php';
$nama_admin   = $_SESSION['user_nama'] ?? 'Admin';
$id_admin     = $_SESSION['user_id'];

$success_message = '';
$error_message   = '';

// Jika admin membatalkan proses pindah data, bersihkan session
if (isset($_GET['batal'])) {
    unset($_SESSION['pindah_id'], $_SESSION['pindah_detail']);
    header('Location: kelola_akun.php');
    exit;
}

// ALGORITMA CRUD AKUN
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['action'])) {
    verify_csrf_or_fail();
    $action = $_POST['action'];

    switch ($action) {
        case 'tambah':
            $username = mb_strtolower(post_string('username', 100), 'UTF-8');
            $nama_lengkap = post_string('nama_lengkap', 100);
            $password_raw = (string) ($_POST['password'] ?? '');
            $konfirmasi   = (string) ($_POST['konfirmasi_password'] ?? '');

            if (!preg_match('/^[a-z0-9._-]{3,100}$/', $username)) {
                $error_message = 'Username hanya boleh berisi huruf kecil, angka, titik, garis bawah, atau tanda hubung.';
                break;
            }

            if (empty($username) || empty($nama_lengkap) || empty($password_raw)) {
                $error_message = 'Semua field wajib diisi.';
                break;
            }
            if ($password_raw !== $konfirmasi) {
                $error_message = 'Password dan konfirmasi password tidak cocok.';
                break;
            }
            if (strlen($password_raw) < 8) {
                $error_message = 'Password minimal 8 karakter.';
                break;
            }

            // Cek apakah username sudah digunakan
            $cek = $conn->prepare("SELECT id FROM users WHERE username = :username LIMIT 1");
            $cek->execute([':username' => $username]);
            if ($cek->fetch()) {
                $error_message = 'Username "' . htmlspecialchars($username) . '" sudah digunakan. Pilih yang lain.';
                break;
            }

            // password_hash() mengenkripsi password menggunakan algoritma bcrypt (PASSWORD_DEFAULT)
            // Hash yang dihasilkan berbeda setiap kali meskipun password-nya sama (menggunakan salt otomatis)
            $password_hash = password_hash($password_raw, PASSWORD_DEFAULT);

            $stmt = $conn->prepare("INSERT INTO users (username, password, nama_lengkap) VALUES (:username, :password, :nama)");
            $stmt->execute([
                ':username' => $username,
                ':password' => $password_hash,
                ':nama'     => $nama_lengkap,
            ]);
            $success_message = 'Akun "' . htmlspecialchars($username) . '" berhasil dibuat!';
            break;

        case 'edit':
            $id_target = post_positive_int('id_user');
            $username = mb_strtolower(post_string('username', 100), 'UTF-8');
            $nama_lengkap = post_string('nama_lengkap', 100);

            if (!preg_match('/^[a-z0-9._-]{3,100}$/', $username)) {
                $error_message = 'Username hanya boleh berisi huruf kecil, angka, titik, garis bawah, atau tanda hubung.';
                break;
            }

            $cek_target = $conn->prepare(
                "SELECT id, username, password FROM users WHERE id = :id LIMIT 1"
            );
            $cek_target->execute([':id' => $id_target]);
            $target = $cek_target->fetch(PDO::FETCH_ASSOC);
            if (!$target) {
                $error_message = 'Akun yang akan diedit tidak ditemukan.';
                break;
            }

            $cek_username = $conn->prepare(
                "SELECT id FROM users WHERE username = :username AND id <> :id LIMIT 1"
            );
            $cek_username->execute([':username' => $username, ':id' => $id_target]);
            if ($cek_username->fetch()) {
                $error_message = 'Username "' . htmlspecialchars($username) . '" sudah digunakan. Pilih yang lain.';
                break;
            }

            if ($id_target === (int) $id_admin) {
                $password_saat_ini = (string) ($_POST['password_saat_ini'] ?? '');
                $password_baru = (string) ($_POST['password_baru'] ?? '');
                $konfirmasi_baru = (string) ($_POST['konfirmasi_password_baru'] ?? '');

                if ($password_baru !== '' || $konfirmasi_baru !== '') {
                    if (!password_verify($password_saat_ini, $target['password'])) {
                        $error_message = 'Password saat ini tidak sesuai.';
                        break;
                    }
                    if (strlen($password_baru) < 8) {
                        $error_message = 'Password baru minimal 8 karakter.';
                        break;
                    }
                    if ($password_baru !== $konfirmasi_baru) {
                        $error_message = 'Password baru dan konfirmasinya tidak cocok.';
                        break;
                    }

                    $stmt_edit = $conn->prepare(
                        "UPDATE users SET nama_lengkap = :nama, username = :username, password = :password WHERE id = :id"
                    );
                    $stmt_edit->execute([
                        ':nama' => $nama_lengkap,
                        ':username' => $username,
                        ':password' => password_hash($password_baru, PASSWORD_DEFAULT),
                        ':id' => $id_target,
                    ]);
                    session_regenerate_id(true);
                } else {
                    $stmt_edit = $conn->prepare(
                        "UPDATE users SET nama_lengkap = :nama, username = :username WHERE id = :id"
                    );
                    $stmt_edit->execute([
                        ':nama' => $nama_lengkap,
                        ':username' => $username,
                        ':id' => $id_target,
                    ]);
                }

                $_SESSION['user_username'] = $username;
                $_SESSION['user_nama'] = $nama_lengkap;
                $nama_admin = $nama_lengkap;
            } else {
                // Admin lain hanya boleh diubah nama lengkap dan username-nya.
                $stmt_edit = $conn->prepare(
                    "UPDATE users SET nama_lengkap = :nama, username = :username WHERE id = :id"
                );
                $stmt_edit->execute([
                    ':nama' => $nama_lengkap,
                    ':username' => $username,
                    ':id' => $id_target,
                ]);
            }

            $success_message = 'Data akun berhasil diperbarui.';
            break;

        case 'hapus':
            $id_target = post_positive_int('id_user');

            // Cegah admin menghapus akunnya sendiri
            if ($id_target === (int) $id_admin) {
                $error_message = 'Anda tidak bisa menghapus akun Anda sendiri yang sedang aktif.';
                break;
            }

            // Cek apakah akun ini masih memiliki data terkait di tabel lain (Foreign Key)
            $cek_jadwal = $conn->prepare("SELECT COUNT(*) FROM jadwal_camat WHERE dibuat_oleh = :id");
            $cek_jadwal->execute([':id' => $id_target]);
            $jumlah_jadwal = (int) $cek_jadwal->fetchColumn();

            $cek_status = $conn->prepare("SELECT COUNT(*) FROM status_adminduk WHERE dibuat_oleh = :id");
            $cek_status->execute([':id' => $id_target]);
            $jumlah_status = (int) $cek_status->fetchColumn();

            $total_relasi = $jumlah_jadwal + $jumlah_status;

            if ($total_relasi > 0) {
                // Simpan info ke session agar form pindah data bisa ditampilkan
                $detail = [];
                if ($jumlah_jadwal > 0) $detail[] = "{$jumlah_jadwal} data jadwal camat";
                if ($jumlah_status > 0) $detail[] = "{$jumlah_status} data status layanan";
                $_SESSION['pindah_id']     = $id_target;
                $_SESSION['pindah_detail'] = implode(' dan ', $detail);
                header('Location: kelola_akun.php');
                exit;
            }

            // Aman untuk dihapus karena tidak ada data relasi
            $stmt = $conn->prepare("DELETE FROM users WHERE id = :id");
            $stmt->execute([':id' => $id_target]);
            $success_message = 'Akun berhasil dihapus.';
            break;

        case 'pindah_dan_hapus':
            $id_dari = post_positive_int('id_dari');
            $id_ke = post_positive_int('id_ke');

            // Akun sumber harus berasal dari proses hapus yang tersimpan di session,
            // bukan sekadar ID yang dikirim ulang oleh browser.
            $id_dari_session = (int) ($_SESSION['pindah_id'] ?? 0);
            if ($id_dari_session < 1 || $id_dari !== $id_dari_session) {
                $error_message = 'Proses pemindahan tidak valid atau sudah kedaluwarsa. Ulangi dari tombol Hapus.';
                unset($_SESSION['pindah_id'], $_SESSION['pindah_detail']);
                break;
            }   

            if ($id_dari === (int) $id_admin) {
                $error_message = 'Anda tidak bisa memindahkan data lalu menghapus akun yang sedang aktif.';
                unset($_SESSION['pindah_id'], $_SESSION['pindah_detail']);
                break;
            }

            if ($id_dari === $id_ke) {
                $error_message = 'Akun tujuan tidak boleh sama dengan akun yang akan dihapus.';
                break;
            }

            try {
                $conn->beginTransaction();

                // Kunci kedua akun selama transaksi dan pastikan keduanya masih tersedia.
                $cek_akun = $conn->prepare(
                    "SELECT id FROM users WHERE id IN (:id_dari, :id_ke) FOR UPDATE"
                );
                $cek_akun->execute([':id_dari' => $id_dari, ':id_ke' => $id_ke]);
                $akun_tersedia = array_map('intval', $cek_akun->fetchAll(PDO::FETCH_COLUMN));

                if (!in_array($id_dari, $akun_tersedia, true)) {
                    throw new RuntimeException('Akun sumber sudah tidak tersedia.');
                }
                if (!in_array($id_ke, $akun_tersedia, true)) {
                    throw new RuntimeException('Akun tujuan sudah tidak tersedia.');
                }

                // Semua perubahan berikut merupakan satu paket: semuanya berhasil
                // atau semuanya dibatalkan oleh rollback.
                $pindah_jadwal = $conn->prepare(
                    "UPDATE jadwal_camat SET dibuat_oleh = :ke WHERE dibuat_oleh = :dari"
                );
                $pindah_jadwal->execute([':ke' => $id_ke, ':dari' => $id_dari]);

                $pindah_status = $conn->prepare(
                    "UPDATE status_adminduk SET dibuat_oleh = :ke WHERE dibuat_oleh = :dari"
                );
                $pindah_status->execute([':ke' => $id_ke, ':dari' => $id_dari]);

                $hapus = $conn->prepare("DELETE FROM users WHERE id = :id");
                $hapus->execute([':id' => $id_dari]);
                if ($hapus->rowCount() !== 1) {
                    throw new RuntimeException('Akun sumber gagal dihapus.');
                }

                $conn->commit();
                unset($_SESSION['pindah_id'], $_SESSION['pindah_detail']);
                $success_message = 'Data berhasil dipindahkan dan akun berhasil dihapus.';
            } catch (Throwable $e) {
                if ($conn->inTransaction()) {
                    $conn->rollBack();
                }
                error_log('Pindah dan hapus akun gagal: ' . $e->getMessage());
                $error_message = 'Pemindahan data dibatalkan. Tidak ada data yang diubah. Silakan coba kembali.';
            }
            break;
    }
}

// Cek apakah ada permintaan pindah data dari session
$pindah_id     = $_SESSION['pindah_id'] ?? null;
$pindah_detail = $_SESSION['pindah_detail'] ?? null;

// Query semua akun pengguna
$stmt_users = $conn->query("SELECT id, username, nama_lengkap, created_at FROM users ORDER BY created_at DESC");
$daftar_akun = $stmt_users->fetchAll(PDO::FETCH_ASSOC);

$edit_akun = null;
if (isset($_GET['edit'])) {
    $edit_id = filter_var($_GET['edit'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($edit_id !== false) {
        $stmt_edit_akun = $conn->prepare(
            "SELECT id, username, nama_lengkap FROM users WHERE id = :id LIMIT 1"
        );
        $stmt_edit_akun->execute([':id' => $edit_id]);
        $edit_akun = $stmt_edit_akun->fetch(PDO::FETCH_ASSOC) ?: null;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Akun Admin — Portal Kecamatan Maron</title>
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
                    <path d="M3 21h18M5 21V7l7-4 7 4v14M9 21V11h6v10"/>
                </svg>
                Admin Panel
            </a>
            <nav class="admin-header__nav">
                <a href="dashboard.php" class="btn-logout" style="color: var(--color-on-surface); border-color: var(--color-outline-variant);" title="Kembali ke Dashboard">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="16" height="16">
                        <path d="M19 12H5M12 19l-7-7 7-7"/>
                    </svg>
                    <span>Kembali</span>
                </a>
            </nav>
        </div>
    </header>

    <!-- MAIN CONTENT -->
    <main class="admin-container">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: var(--stack-lg);">
            <h1 class="dashboard-title" style="margin-bottom: 0;">Kelola Akun Admin</h1>
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

        <?php if ($pindah_id): ?>
            <!-- FORM PINDAH DATA: Muncul otomatis jika akun target masih punya data relasi -->
            <div style="background: var(--color-surface-low); border: 1px solid var(--color-outline-variant); border-radius: 12px; padding: var(--stack-lg); margin-bottom: var(--stack-lg);">
                <h3 style="margin: 0 0 8px; font-size: var(--fs-body-lg); color: var(--color-error);">⚠️ Akun Memiliki Data Terkait</h3>
                <p style="margin: 0 0 var(--stack-md); color: var(--color-on-surface-variant);">Akun ini masih memiliki <strong><?= htmlspecialchars($pindah_detail) ?></strong>. Pilih akun tujuan untuk memindahkan kepemilikan data tersebut, lalu akun ini akan otomatis dihapus.</p>
                <form action="" method="POST" style="display: flex; gap: 12px; align-items: flex-end; flex-wrap: wrap;">
                    <?= csrf_input() ?>
                    <input type="hidden" name="action" value="pindah_dan_hapus">
                    <input type="hidden" name="id_dari" value="<?= (int)$pindah_id ?>">
                    <div class="form-group" style="flex: 1; min-width: 200px; margin: 0;">
                        <label class="form-label" style="font-size: 12px;">Pindahkan semua data ke akun:</label>
                        <select class="form-input" name="id_ke" required style="height: 42px;">
                            <option value="" disabled selected>Pilih akun tujuan...</option>
                            <?php foreach ($daftar_akun as $akun): ?>
                                <?php if ((int)$akun['id'] !== (int)$pindah_id): ?>
                                    <option value="<?= (int) $akun['id'] ?>">
                                        <?= htmlspecialchars(mb_convert_case($akun['nama_lengkap'], MB_CASE_TITLE, 'UTF-8'), ENT_QUOTES, 'UTF-8') ?> (@<?= htmlspecialchars($akun['username'], ENT_QUOTES, 'UTF-8') ?>)
                                    </option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div style="display: flex; gap: 8px; align-items:center;">
                        <button type="submit" class="btn-primary" style="height: 42px; padding: 0 16px; background-color: var(--color-error);" onclick="return confirm('Yakin? Semua data akan dipindahkan lalu akun dihapus permanen.');">Pindahkan & Hapus Akun</button>
                        <a href="kelola_akun.php?batal=1" class="btn-logout" style="height: 42px; padding: 0 16px; display: inline-flex; align-items: center; text-decoration: none;">Batal</a>
                    </div>
                </form>
            </div>
        <?php endif; ?>

        <div class="dashboard-grid">

            <!-- FORM TAMBAH / EDIT AKUN -->
            <section class="dashboard-card" style="height: fit-content;">
                <header class="dashboard-card__header">
                    <h2 class="dashboard-card__title"><?= $edit_akun ? 'Edit Akun Admin' : 'Tambah Akun Baru' ?></h2>
                    <?php if ($edit_akun): ?>
                        <a href="kelola_akun.php" class="btn-logout" style="padding: 4px 8px; font-size: 12px; text-decoration: none;">Batal Edit</a>
                    <?php endif; ?>
                </header>
                <div class="dashboard-card__body">
                    <form action="" method="POST" class="login-form">
                        <?= csrf_input() ?>
                        <input type="hidden" name="action" value="<?= $edit_akun ? 'edit' : 'tambah' ?>">
                        <?php if ($edit_akun): ?>
                            <input type="hidden" name="id_user" value="<?= (int) $edit_akun['id'] ?>">
                        <?php endif; ?>

                        <div class="form-group">
                            <label class="form-label" for="nama_lengkap">Nama Lengkap</label>
                            <input class="form-input" type="text" id="nama_lengkap" name="nama_lengkap" maxlength="100" required placeholder="Nama Lengkap" value="<?= htmlspecialchars($edit_akun['nama_lengkap'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="username">Username</label>
                            <input class="form-input" type="text" id="username" name="username" minlength="3" maxlength="100" pattern="[a-zA-Z0-9._-]+" required placeholder="Contoh: operator1" autocomplete="off" value="<?= htmlspecialchars($edit_akun['username'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                            <small style="color: var(--color-on-surface-variant); margin-top: 4px; display: block;">Otomatis diubah ke huruf kecil semua.</small>
                        </div>

                        <?php if (!$edit_akun): ?>
                            <div class="form-group">
                                <label class="form-label" for="password">Password</label>
                                <input class="form-input" type="password" id="password" name="password" minlength="8" required placeholder="Min. 8 karakter" autocomplete="new-password">
                            </div>

                            <div class="form-group">
                                <label class="form-label" for="konfirmasi_password">Konfirmasi Password</label>
                                <input class="form-input" type="password" id="konfirmasi_password" name="konfirmasi_password" minlength="8" required placeholder="Ulangi password di atas" autocomplete="new-password">
                            </div>
                        <?php elseif ((int) $edit_akun['id'] === (int) $id_admin): ?>
                            <div style="border-top: 1px solid var(--color-outline-variant); padding-top: var(--stack-md);">
                                <p style="margin: 0 0 var(--stack-md); color: var(--color-on-surface-variant); font-size: 13px;">Kosongkan bagian password jika Anda hanya ingin mengubah nama atau username.</p>
                                <div class="form-group">
                                    <label class="form-label" for="password_saat_ini">Password Saat Ini</label>
                                    <input class="form-input" type="password" id="password_saat_ini" name="password_saat_ini" autocomplete="current-password">
                                </div>
                                <div class="form-group">
                                    <label class="form-label" for="password_baru">Password Baru</label>
                                    <input class="form-input" type="password" id="password_baru" name="password_baru" minlength="8" placeholder="Min. 8 karakter" autocomplete="new-password">
                                </div>
                                <div class="form-group">
                                    <label class="form-label" for="konfirmasi_password_baru">Konfirmasi Password Baru</label>
                                    <input class="form-input" type="password" id="konfirmasi_password_baru" name="konfirmasi_password_baru" minlength="8" placeholder="Ulangi password baru" autocomplete="new-password">
                                </div>
                            </div>
                        <?php else: ?>
                            <p style="margin: 0; color: var(--color-on-surface-variant); font-size: 13px;">Password akun lain tidak dapat dilihat atau diubah.</p>
                        <?php endif; ?>

                        <button class="btn-primary" type="submit">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="18" height="18">
                                <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" y1="8" x2="19" y2="14"/><line x1="22" y1="11" x2="16" y2="11"/>
                            </svg>
                            <?= $edit_akun ? 'Simpan Perubahan' : 'Buat Akun' ?>
                        </button>
                    </form>
                </div>
            </section>

            <!-- DAFTAR AKUN -->
            <section class="dashboard-card">
                <header class="dashboard-card__header">
                    <h2 class="dashboard-card__title">Daftar Akun Admin</h2>
                </header>
                <div class="dashboard-card__body" style="padding: 0;">
                    <ul class="admin-list" style="padding: 0 var(--stack-md);">
                        <?php foreach ($daftar_akun as $akun): ?>
                            <li style="padding: var(--stack-md) 0;">
                                <div class="admin-list__info">
                                    <strong style="font-size: 16px;"><?= htmlspecialchars(mb_convert_case($akun['nama_lengkap'], MB_CASE_TITLE, 'UTF-8'), ENT_QUOTES, 'UTF-8') ?></strong>
                                    <span style="display: block; margin-top: 2px; font-size: 13px; color: var(--color-on-surface-variant);">@<?= htmlspecialchars($akun['username']) ?></span>
                                    <span style="display: block; margin-top: 4px; font-size: 12px; color: var(--color-on-surface-variant);">Dibuat: <?= htmlspecialchars($akun['created_at'], ENT_QUOTES, 'UTF-8') ?></span>
                                </div>
                                <div style="display: flex; align-items: center; gap: 8px;">
                                    <a href="?edit=<?= (int) $akun['id'] ?>" class="btn-primary" style="padding: 4px 10px; height: auto; font-size: 12px; text-decoration: none;">Edit</a>
                                    <?php if ((int)$akun['id'] === (int)$id_admin): ?>
                                        <span style="font-size: 11px; font-weight: bold; color: var(--color-on-primary); background: var(--color-primary-container); padding: 3px 8px; border-radius: 4px;">Anda</span>
                                    <?php else: ?>
                                        <form action="" method="POST" style="margin: 0;">
                                            <?= csrf_input() ?>
                                            <input type="hidden" name="action" value="hapus">
                                            <input type="hidden" name="id_user" value="<?= (int) $akun['id'] ?>">
                                            <button type="submit" class="btn-logout" style="padding: 4px 10px; font-size: 12px;" onclick="return confirm('Yakin ingin menghapus akun ini?');">Hapus</button>
                                        </form>
                                    <?php endif; ?>
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
