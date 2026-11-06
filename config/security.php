<?php

function start_secure_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    ini_set('session.use_strict_mode', '1');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_input(): string
{
    return '<input type="hidden" name="csrf_token" value="'
        . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8')
        . '">';
}

function verify_csrf_or_fail(): void
{
    $submitted = $_POST['csrf_token'] ?? '';
    if (!is_string($submitted) || !hash_equals(csrf_token(), $submitted)) {
        http_response_code(403);
        exit('Permintaan tidak valid. Muat ulang halaman lalu coba kembali.');
    }
}

function post_string(string $key, int $maxLength, bool $required = true): string
{
    $value = trim((string) ($_POST[$key] ?? ''));
    if ($required && $value === '') {
        throw new InvalidArgumentException('Semua kolom wajib diisi.');
    }
    if (mb_strlen($value, 'UTF-8') > $maxLength) {
        throw new InvalidArgumentException('Isian terlalu panjang.');
    }
    return $value;
}

function post_positive_int(string $key): int
{
    $value = filter_var($_POST[$key] ?? null, FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1],
    ]);
    if ($value === false || $value === null) {
        throw new InvalidArgumentException('ID data tidak valid.');
    }
    return $value;
}

function validate_http_url(string $url): string
{
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        throw new InvalidArgumentException('URL survei tidak valid.');
    }
    $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
    if (!in_array($scheme, ['http', 'https'], true)) {
        throw new InvalidArgumentException('URL survei harus menggunakan http:// atau https://.');
    }
    return $url;
}

function validate_date_value(string $date): string
{
    $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
    if (!$parsed || $parsed->format('Y-m-d') !== $date) {
        throw new InvalidArgumentException('Tanggal tidak valid.');
    }
    return $date;
}

function validate_time_value(string $time): string
{
    if (!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $time)) {
        throw new InvalidArgumentException('Format waktu tidak valid.');
    }
    return $time;
}

function validate_choice(string $value, array $allowed, string $message): string
{
    if (!in_array($value, $allowed, true)) {
        throw new InvalidArgumentException($message);
    }
    return $value;
}

function login_rate_file(string $username): string
{
    $identity = strtolower($username) . '|' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    return rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR
        . 'jumpa_camat_login_' . hash('sha256', $identity) . '.json';
}

function login_rate_status(string $username): array
{
    $file = login_rate_file($username);
    if (!is_file($file)) {
        return ['attempts' => 0, 'started_at' => time(), 'locked_until' => 0];
    }
    $data = json_decode((string) file_get_contents($file), true);
    if (!is_array($data) || (time() - (int) ($data['started_at'] ?? 0)) > 900) {
        return ['attempts' => 0, 'started_at' => time(), 'locked_until' => 0];
    }
    return $data + ['attempts' => 0, 'started_at' => time(), 'locked_until' => 0];
}

function record_login_failure(string $username): void
{
    $file = login_rate_file($username);
    $handle = fopen($file, 'c+');
    if ($handle === false) {
        return;
    }
    try {
        flock($handle, LOCK_EX);
        $data = json_decode(stream_get_contents($handle) ?: '', true);
        if (!is_array($data) || (time() - (int) ($data['started_at'] ?? 0)) > 900) {
            $data = ['attempts' => 0, 'started_at' => time(), 'locked_until' => 0];
        }
        $data['attempts'] = (int) $data['attempts'] + 1;
        if ($data['attempts'] >= 5) {
            $data['locked_until'] = time() + 900;
        }
        rewind($handle);
        ftruncate($handle, 0);
        fwrite($handle, json_encode($data, JSON_THROW_ON_ERROR));
        fflush($handle);
    } finally {
        flock($handle, LOCK_UN);
        fclose($handle);
    }
}

function clear_login_failures(string $username): void
{
    $file = login_rate_file($username);
    if (is_file($file)) {
        unlink($file);
    }
}

function contact_image_directory(): string
{
    return dirname(__DIR__) . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR
        . 'storage' . DIRECTORY_SEPARATOR . 'kontak';
}

function save_contact_image(array $file): ?string
{
    $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($error === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($error !== UPLOAD_ERR_OK || empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        throw new InvalidArgumentException('Gambar profil gagal diunggah.');
    }
    if ((int) ($file['size'] ?? 0) > 2 * 1024 * 1024) {
        throw new InvalidArgumentException('Ukuran gambar profil maksimal 2 MB.');
    }

    $imageInfo = @getimagesize($file['tmp_name']);
    if ($imageInfo === false) {
        throw new InvalidArgumentException('File yang dipilih bukan gambar yang valid.');
    }
    [$width, $height] = $imageInfo;
    if ($width < 200 || $height < 200 || $width > 3000 || $height > 3000) {
        throw new InvalidArgumentException('Dimensi gambar harus antara 200×200 dan 3000×3000 piksel.');
    }

    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    $formats = [
        'image/jpeg' => ['extension' => 'jpg', 'loader' => 'imagecreatefromjpeg'],
        'image/png' => ['extension' => 'png', 'loader' => 'imagecreatefrompng'],
        'image/webp' => ['extension' => 'webp', 'loader' => 'imagecreatefromwebp'],
    ];
    if (!isset($formats[$mime]) || !function_exists($formats[$mime]['loader'])) {
        throw new InvalidArgumentException('Format gambar harus JPEG, PNG, atau WebP.');
    }

    $directory = contact_image_directory();
    if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
        throw new RuntimeException('Folder penyimpanan gambar tidak tersedia.');
    }

    $filename = bin2hex(random_bytes(16)) . '.' . $formats[$mime]['extension'];
    $absolutePath = $directory . DIRECTORY_SEPARATOR . $filename;
    $image = $formats[$mime]['loader']($file['tmp_name']);
    if ($image === false) {
        throw new InvalidArgumentException('Gambar profil tidak dapat diproses.');
    }

    try {
        $saved = match ($mime) {
            'image/jpeg' => imagejpeg($image, $absolutePath, 88),
            'image/png' => imagepng($image, $absolutePath, 7),
            'image/webp' => imagewebp($image, $absolutePath, 85),
        };
    } finally {
        imagedestroy($image);
    }
    if (!$saved) {
        throw new RuntimeException('Gambar profil gagal disimpan.');
    }

    return 'assets/storage/kontak/' . $filename;
}

function delete_contact_image(?string $relativePath): void
{
    if (!$relativePath || !preg_match('#^assets/storage/kontak/[a-f0-9]{32}\.(?:jpg|png|webp)$#', $relativePath)) {
        return;
    }
    $absolutePath = dirname(__DIR__) . DIRECTORY_SEPARATOR
        . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
    $directory = realpath(contact_image_directory());
    $resolved = realpath($absolutePath);
    if ($directory !== false && $resolved !== false
        && str_starts_with($resolved, $directory . DIRECTORY_SEPARATOR)
        && is_file($resolved)) {
        unlink($resolved);
    }
}

function hero_image_directory(): string
{
    return dirname(__DIR__) . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR
        . 'storage' . DIRECTORY_SEPARATOR . 'hero';
}

function save_hero_image(array $file): ?string
{
    $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($error === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($error !== UPLOAD_ERR_OK || empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        throw new InvalidArgumentException('Gambar carousel gagal diunggah.');
    }
    if ((int) ($file['size'] ?? 0) > 5 * 1024 * 1024) {
        throw new InvalidArgumentException('Ukuran gambar carousel maksimal 5 MB.');
    }
    $imageInfo = @getimagesize($file['tmp_name']);
    if ($imageInfo === false) {
        throw new InvalidArgumentException('File carousel bukan gambar yang valid.');
    }
    [$width, $height] = $imageInfo;
    if ($width > 5000 || $height > 3500) {
        throw new InvalidArgumentException('Dimensi gambar carousel maksimal 5000×3500 piksel.');
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    $formats = [
        'image/jpeg' => ['extension' => 'jpg', 'loader' => 'imagecreatefromjpeg'],
        'image/png' => ['extension' => 'png', 'loader' => 'imagecreatefrompng'],
        'image/webp' => ['extension' => 'webp', 'loader' => 'imagecreatefromwebp'],
    ];
    if (!isset($formats[$mime]) || !function_exists($formats[$mime]['loader'])) {
        throw new InvalidArgumentException('Format gambar carousel harus JPEG, PNG, atau WebP.');
    }
    $directory = hero_image_directory();
    if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
        throw new RuntimeException('Folder penyimpanan carousel tidak tersedia.');
    }
    $filename = bin2hex(random_bytes(16)) . '.' . $formats[$mime]['extension'];
    $absolutePath = $directory . DIRECTORY_SEPARATOR . $filename;
    $image = $formats[$mime]['loader']($file['tmp_name']);
    if ($image === false) {
        throw new InvalidArgumentException('Gambar carousel tidak dapat diproses.');
    }
    try {
        $saved = match ($mime) {
            'image/jpeg' => imagejpeg($image, $absolutePath, 88),
            'image/png' => imagepng($image, $absolutePath, 7),
            'image/webp' => imagewebp($image, $absolutePath, 85),
        };
    } finally {
        imagedestroy($image);
    }
    if (!$saved) {
        throw new RuntimeException('Gambar carousel gagal disimpan.');
    }
    return 'assets/storage/hero/' . $filename;
}

function delete_hero_image(?string $relativePath): void
{
    if (!$relativePath || !preg_match('#^assets/storage/hero/[a-f0-9]{32}\.(?:jpg|png|webp)$#', $relativePath)) {
        return;
    }
    $absolutePath = dirname(__DIR__) . DIRECTORY_SEPARATOR
        . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
    $directory = realpath(hero_image_directory());
    $resolved = realpath($absolutePath);
    if ($directory !== false && $resolved !== false
        && str_starts_with($resolved, $directory . DIRECTORY_SEPARATOR)
        && is_file($resolved)) {
        unlink($resolved);
    }
}

set_exception_handler(static function (Throwable $exception): void {
    if ($exception instanceof InvalidArgumentException) {
        http_response_code(422);
        echo htmlspecialchars($exception->getMessage(), ENT_QUOTES, 'UTF-8');
        return;
    }
    error_log($exception->__toString());
    http_response_code(500);
    echo 'Terjadi kesalahan sistem. Silakan coba kembali.';
});
