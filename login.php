<?php
require_once 'config/security.php';
start_secure_session();

// Redirect jika sudah login
if (isset($_SESSION['user_logged_in']) && $_SESSION['user_logged_in'] === true) {
    header('Location: admin/dashboard.php');
    exit;
}

// Koneksi database (path relatif dari folder admin/)
require_once 'config/database.php';

$error_message = '';
$username_value = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    verify_csrf_or_fail();

    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $username_value = htmlspecialchars($username);

    $now = time();
    $rateStatus = login_rate_status($username);
    $lockedUntil = (int) $rateStatus['locked_until'];

    if ($lockedUntil > $now) {
        $error_message = 'Terlalu banyak percobaan login. Coba kembali dalam beberapa menit.';
    } elseif ($username === '' || $password === '') {
        $error_message = 'Username dan password tidak boleh kosong.';
    } elseif (mb_strlen($username, 'UTF-8') > 100 || strlen($password) > 4096) {
        record_login_failure($username);
        $error_message = 'Username atau password salah. Silakan coba lagi.';
    } else {
        try {
            // Tabel: users — kolom: id, username, password, nama_lengkap
            $stmt = $conn->prepare("SELECT id, username, password, nama_lengkap FROM users WHERE username = :username LIMIT 1");
            $stmt->execute([':username' => $username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            // password_verify() mencocokkan password plain text dari form dengan hash bcrypt di database
            if ($user && password_verify($password, $user['password'])) {
                session_regenerate_id(true);
                clear_login_failures($username);
                $_SESSION['user_logged_in']  = true;
                $_SESSION['user_id']          = $user['id'];
                $_SESSION['user_username']    = $user['username'];
                $_SESSION['user_nama']        = $user['nama_lengkap'];
                header('Location: admin/dashboard.php');
                exit;
            } else {
                record_login_failure($username);
                $error_message = 'Username atau password salah. Silakan coba lagi.';
            }
        } catch (PDOException $e) {
            $error_message = 'Terjadi kesalahan sistem. Silakan hubungi administrator.';
            // Log error secara internal, jangan tampilkan detail ke user
            error_log('Login PDOException: ' . $e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Halaman login admin portal informasi Kecamatan Maron — Kabupaten Probolinggo.">
    <meta name="robots" content="noindex, nofollow">
    <title>Login Admin — Portal Kecamatan Maron</title>

    <!-- Google Fonts: Public Sans -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">

    <!-- CSS -->
    <link rel="stylesheet" href="assets/css/style.css"> <!--depedensi-->
    <link rel="stylesheet" href="assets/css/admin.css">
</head>

<body class="admin-body" style="background: linear-gradient(rgba(255, 255, 255, 0.65), rgba(255, 255, 255, 0.42)), url('assets/images/image1.jpeg') no-repeat center center fixed; background-size: cover;">

    <!-- Skip navigation untuk aksesibilitas -->
    <a class="skip-link" href="#login-form">Lewati ke formulir login</a>

    <!-- ====================================================
         MAIN CONTENT
         ==================================================== -->
    <main class="page-wrapper" role="main">
        <div class="login-card" aria-labelledby="login-heading">

            <!-- Branding -->
            <div class="login-brand">
                <div class="login-brand__logo" aria-hidden="true" style="background: transparent; height: auto;">
                    <img src="assets/images/logo kab.svg" alt="Logo Kabupaten Probolinggo" style="width: 56px; height: auto; object-fit: contain;">
                </div>
                <h1 class="login-brand__title" id="login-heading">Portal Admin</h1>
                <p class="login-brand__subtitle">Kecamatan Maron</p>
            </div>

            <!-- Error Message -->
            <?php if (!empty($error_message)): ?>
                <div class="alert-error" role="alert" aria-live="assertive">
                    <svg viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="18" height="18">
                        <circle cx="12" cy="12" r="10" />
                        <line x1="12" y1="8" x2="12" y2="12" />
                        <line x1="12" y1="16" x2="12.01" y2="16" />
                    </svg>
                    <span><?= htmlspecialchars($error_message) ?></span>
                </div>
            <?php endif; ?>

            <!-- Login Form -->
            <form
                class="login-form"
                id="login-form"
                method="POST"
                action="login.php"
                novalidate
                aria-label="Formulir login admin">
                <?= csrf_input() ?>

                <!-- Username -->
                <div class="form-group">
                    <label class="form-label" for="username">Username</label>
                    <input
                        class="form-input"
                        type="text"
                        id="username"
                        name="username"
                        value="<?= htmlspecialchars($username_value) ?>"
                        autocomplete="username"
                        placeholder="Masukkan username"
                        required
                        aria-required="true"
                        aria-describedby="username-hint"
                        autofocus>
                </div>

                <!-- Password -->
                <div class="form-group">
                    <label class="form-label" for="password">Password</label>
                    <div class="input-wrapper">
                        <input
                            class="form-input"
                            type="password"
                            id="password"
                            name="password"
                            autocomplete="current-password"
                            placeholder="Masukkan password"
                            required
                            aria-required="true">
                        <button
                            type="button"
                            class="btn-toggle-password"
                            id="btn-toggle-password"
                            aria-label="Tampilkan password"
                            aria-pressed="false">
                            <!-- Eye icon (default: lihat password) -->
                            <svg id="icon-eye-show" viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="20" height="20">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" />
                                <circle cx="12" cy="12" r="3" />
                            </svg>
                            <!-- Eye-off icon (saat password terlihat) -->
                            <svg id="icon-eye-hide" viewBox="0 0 24 24" aria-hidden="true" style="display:none;" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="20" height="20">
                                <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24" />
                                <line x1="1" y1="1" x2="23" y2="23" />
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- Submit -->
                <button
                    class="btn-primary"
                    type="submit"
                    id="btn-login"
                    aria-label="Masuk ke panel admin">
                    <svg viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="18" height="18">
                        <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4" />
                        <polyline points="10 17 15 12 10 7" />
                        <line x1="15" y1="12" x2="3" y2="12" />
                    </svg>
                    Masuk
                </button>
            </form>

            <!-- Back to public site -->
            <div class="login-divider">
                <span class="login-divider__text">Kembali ke halaman publik?</span>
                <a class="login-divider__link" href="index.php" aria-label="Kembali ke halaman utama publik">
                    Halaman Utama
                </a>
            </div>

        </div><!-- /.login-card -->
    </main>

    <!-- ====================================================
         FOOTER
         ==================================================== -->
    <footer class="page-footer" role="contentinfo">
        <p>&copy; <?= date('Y') ?> Kecamatan Maron — Kabupaten Probolinggo</p>
    </footer>

    <!-- ====================================================
         JAVASCRIPT — Toggle password visibility & form UX
         ==================================================== -->
    <script>
        (function() {
            'use strict';

            // ---- Toggle password visibility ----
            const btnToggle = document.getElementById('btn-toggle-password');
            const inputPwd = document.getElementById('password');
            const iconShow = document.getElementById('icon-eye-show');
            const iconHide = document.getElementById('icon-eye-hide');

            if (btnToggle && inputPwd) {
                btnToggle.addEventListener('click', function() {
                    const isPassword = inputPwd.type === 'password';
                    inputPwd.type = isPassword ? 'text' : 'password';

                    iconShow.style.display = isPassword ? 'none' : '';
                    iconHide.style.display = isPassword ? '' : 'none';

                    btnToggle.setAttribute('aria-label', isPassword ? 'Sembunyikan password' : 'Tampilkan password');
                    btnToggle.setAttribute('aria-pressed', isPassword ? 'true' : 'false');
                });
            }

            // ---- Disable tombol submit saat form dikirim (cegah double-submit) ----
            const form = document.getElementById('login-form');
            const btnLogin = document.getElementById('btn-login');

            if (form && btnLogin) {
                form.addEventListener('submit', function() {
                    btnLogin.disabled = true;
                    btnLogin.textContent = 'Memproses…';
                });
            }

            // ---- Client-side validation ringan (UX, bukan keamanan) ----
            if (form) {
                form.addEventListener('submit', function(e) {
                    const username = document.getElementById('username').value.trim();
                    const password = document.getElementById('password').value;

                    if (!username || !password) {
                        e.preventDefault();
                        btnLogin.disabled = false;
                        btnLogin.innerHTML = `<svg viewBox="0 0 24 24" aria-hidden="true" style="width:18px;height:18px;fill:none;stroke:currentColor;stroke-width:2.5;stroke-linecap:round;stroke-linejoin:round;">
                            <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/>
                            <polyline points="10 17 15 12 10 7"/>
                            <line x1="15" y1="12" x2="3" y2="12"/>
                        </svg> Masuk`;
                    }
                });
            }
        })();
    </script>

</body>

</html>
