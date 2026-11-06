<?php

// KONEKSI DATABASE
require_once 'config/database.php';

// Zona waktu Indonesia Barat
date_default_timezone_set('Asia/Jakarta');

// HELPER TANGGAL INDONESIA
$hari = [
    'Sunday'    => 'Minggu',
    'Monday'    => 'Senin',
    'Tuesday'   => 'Selasa',
    'Wednesday' => 'Rabu',
    'Thursday'  => 'Kamis',
    'Friday'    => 'Jumat',
    'Saturday'  => 'Sabtu'
];

$bulan = [
    'January'   => 'Januari',
    'February'  => 'Februari',
    'March'     => 'Maret',
    'April'     => 'April',
    'May'       => 'Mei',
    'June'      => 'Juni',
    'July'      => 'Juli',
    'August'    => 'Agustus',
    'September' => 'September',
    'October'   => 'Oktober',
    'November'  => 'November',
    'December'  => 'Desember'
];

$hari_ini =
    $hari[date('l')]
    . ', '
    . date('d')
    . ' '
    . $bulan[date('F')]
    . ' '
    . date('Y');

$tanggal_hari_ini = date('Y-m-d');


// 1. QUERY HERO CAROUSEL
$stmt_hero = $conn->query("
    SELECT id, gambar_path, title, deskripsi, alt_text, focal_x, focal_y
    FROM hero_carousel
    WHERE aktif = 1
    ORDER BY urutan ASC, id ASC
");

$hero_slides = array_values(array_filter(
    $stmt_hero->fetchAll(PDO::FETCH_ASSOC),
    static function (array $slide): bool {
        $path = (string) ($slide['gambar_path'] ?? '');
        $allowedPath = preg_match('#^assets/storage/hero/[a-f0-9]{32}\.(?:jpg|png|webp)$#', $path)
            || preg_match('#^assets/images/image[1-4]\.jpeg$#', $path);
        return $allowedPath
            && is_file(__DIR__ . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path));
    }
));


// 2. QUERY JADWAL CAMAT HARI INI
$stmt_camat = $conn->prepare("
    SELECT *
    FROM jadwal_camat
    WHERE tanggal = :tanggal
    ORDER BY jam_mulai ASC
");

$stmt_camat->execute([
    ':tanggal' => $tanggal_hari_ini
]);

$jadwal_camat = $stmt_camat->fetchAll(PDO::FETCH_ASSOC);


// 3. QUERY STATUS ADMINDUK
$stmt_adminduk = $conn->query("
    SELECT *
    FROM status_adminduk
    ORDER BY created_at DESC
");

$status_adminduk = $stmt_adminduk->fetchAll(PDO::FETCH_ASSOC);


// 4. QUERY INFORMASI LAYANAN DAN PERSYARATAN
$stmt_layanan = $conn->query("
    SELECT
        l.id,
        l.kode_layanan,
        l.nama_layanan,
        l.deskripsi,
        NULL AS estimasi_waktu,
        NULL AS biaya,
        NULL AS lokasi,
        NULL AS jam_pelayanan,
        NULL AS catatan,
        l.urutan,
        p.isi_persyaratan,
        p.urutan AS urutan_persyaratan
    FROM layanan_kecamatan l
    LEFT JOIN persyaratan_layanan p ON p.layanan_id = l.id
    WHERE l.aktif = 1
    ORDER BY l.urutan ASC, l.id ASC, p.urutan ASC, p.id ASC
");

$ikon_layanan = [
    'KTP_EL' => 'assets/images/services/ktp-el.svg',
    'KK' => 'assets/images/services/kartu-keluarga.svg',
    'AKTA_KELAHIRAN' => 'assets/images/services/akta-kelahiran.svg',
    'AKTA_KEMATIAN' => 'assets/images/services/akta-kematian.svg',
    'SURAT_PINDAH' => 'assets/images/services/surat-pindah.svg',
];

$layanan_kecamatan = [];
foreach ($stmt_layanan->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $id_layanan = (int) $row['id'];
    if (!isset($layanan_kecamatan[$id_layanan])) {
        $layanan_kecamatan[$id_layanan] = [
            'id' => $id_layanan,
            'kode_layanan' => $row['kode_layanan'],
            'nama_layanan' => $row['nama_layanan'],
            'deskripsi' => $row['deskripsi'],
            'estimasi_waktu' => $row['estimasi_waktu'],
            'biaya' => $row['biaya'],
            'lokasi' => $row['lokasi'],
            'jam_pelayanan' => $row['jam_pelayanan'],
            'catatan' => $row['catatan'],
            'ikon' => $ikon_layanan[$row['kode_layanan']] ?? 'assets/images/services/katalog-layanan.svg',
            'persyaratan' => [],
        ];
    }
    if ($row['isi_persyaratan'] !== null) {
        $layanan_kecamatan[$id_layanan]['persyaratan'][] = $row['isi_persyaratan'];
    }
}
$layanan_kecamatan = array_values($layanan_kecamatan);


// 4. QUERY KONTAK LAYANAN
$stmt_kontak = $conn->query("
    SELECT *
    FROM kontak_layanan
    WHERE aktif = 1
    ORDER BY id ASC
");

$kontak_layanan = $stmt_kontak->fetchAll(PDO::FETCH_ASSOC);


// 5. QUERY LINK SURVEI
$stmt_survei = $conn->query("
    SELECT *
    FROM link_survei
    LIMIT 1
");

$link_survei = $stmt_survei->fetch(PDO::FETCH_ASSOC);
$safe_survey_url = null;
if (!empty($link_survei['url']) && filter_var($link_survei['url'], FILTER_VALIDATE_URL)) {
    $survey_scheme = strtolower((string) parse_url($link_survei['url'], PHP_URL_SCHEME));
    if (in_array($survey_scheme, ['http', 'https'], true)) {
        $safe_survey_url = $link_survei['url'];
    }
}


// WAKTU UPDATE
$waktu_sinkronisasi = date('d/m/Y H:i') . ' WIB';

?>
<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Portal Informasi - Kecamatan Maron</title>


    <!-- GOOGLE FONTS -->
    <link
        rel="preconnect"
        href="https://fonts.googleapis.com"
    >

    <link
        rel="preconnect"
        href="https://fonts.gstatic.com"
        crossorigin
    >

    <link
        href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@400;500;600;700&display=swap"
        rel="stylesheet"
    >


    <!-- CSS GLOBAL -->
    <link
        rel="stylesheet"
        href="assets/css/style.css"
    >


    <style>

           /* CONTAINER */

        .public-container {
            max-width: var(--max-width);
            margin: 0 auto;
            padding: 0 var(--gutter);
        }


           /* HEADER */

        .public-header {
            background: var(--color-primary-container);
            color: var(--color-on-primary);
            padding: 20px 0;
            border-bottom: 4px solid var(--color-primary-fixed);
        }

        .public-header__content {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            gap: 10px;
        }

        .public-header__logo {
            width: auto;
            height: 65px;
            object-fit: contain;
            display: block;
            margin: 0 auto;
        }

        .public-header__title {
            font-size: var(--fs-headline-lg-mobile);
            font-weight: var(--fw-bold);
            line-height: 1.2;
            margin: 0;
        }

        .public-header__date {
            font-size: var(--fs-body-lg);
            color: var(--color-primary-fixed);
            background: rgba(0, 0, 0, 0.2);
            padding: 4px 16px;
            border-radius: var(--radius-full);
        }


        /* =========================================================
           HERO CAROUSEL
           ========================================================= */

        .hero-carousel {
            position: relative;
            width: 100%;
            height: 350px;
            overflow: hidden;
            border-radius: var(--radius-xl);
            margin: var(--stack-lg) 0;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.1);
        }

        .carousel-inner {
            display: flex;
            height: 100%;
            transition: transform 0.6s ease-in-out;
        }

        .carousel-item {
            min-width: 100%;
            height: 100%;
            position: relative;
        }

        .carousel-item img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        .carousel-overlay {
            position: absolute;
            inset: 0;

            background:
                linear-gradient(
                    to top,
                    rgba(0, 0, 0, 0.75) 0%,
                    rgba(0, 0, 0, 0.15) 60%,
                    transparent 100%
                );

            display: flex;
            align-items: flex-end;
            padding: var(--stack-lg);
        }

        .carousel-content {
            color: #ffffff;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.5);
        }

        .carousel-content h2 {
            font-size: var(--fs-headline-md);
            margin-bottom: 8px;
            font-weight: var(--fw-bold);
        }

        .carousel-content p {
            font-size: var(--fs-body-md);
            opacity: 0.95;
            margin: 0;
        }

        .carousel-indicators {
            position: absolute;
            bottom: 20px;
            left: 50%;
            transform: translateX(-50%);
            display: flex;
            gap: 8px;
            z-index: 10;
        }

        .indicator {
            width: 10px;
            height: 10px;
            padding: 0;
            border: 0;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.5);
            cursor: pointer;
            transition:
                background 0.3s,
                transform 0.3s;
        }

        .indicator.active {
            background: #ffffff;
            transform: scale(1.3);
        }


        /* =========================================================
           SECTION TITLE
           ========================================================= */

        .section-title {
            font-size: var(--fs-body-lg);
            font-weight: var(--fw-bold);

            margin:
                var(--stack-lg)
                0
                var(--stack-md);

            color: var(--color-primary-container);

            display: flex;
            align-items: center;
            gap: 8px;

            border-bottom:
                2px solid
                var(--color-surface-variant);

            padding-bottom: 8px;
        }


        /* =========================================================
           CARD GRID
           ========================================================= */

        .card-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: var(--stack-md);
        }

        @media (min-width: 600px) {

            .card-grid {
                grid-template-columns: repeat(2, 1fr);
            }

        }


        /* =========================================================
           CARD
           ========================================================= */

        .card {
            background: rgba(255, 255, 255, 0.9);

            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);

            border:
                1px solid
                rgba(0, 70, 15, 0.12);

            border-radius: var(--radius-lg);

            padding: var(--stack-md);

            box-shadow:
                0 4px 12px
                rgba(0, 0, 0, 0.05);

            transition:
                transform 0.3s ease,
                box-shadow 0.3s ease;
        }

        .card:hover {

            transform: translateY(-4px);

            box-shadow:
                0 12px 24px
                rgba(0, 0, 0, 0.1);

            border-color:
                var(--color-primary-fixed-dim);
        }


        /* =========================================================
           JADWAL
           ========================================================= */

        .jadwal-title {
            margin-bottom: 4px;
            font-size: 18px;
            color: var(--color-primary-container);
        }

        .jadwal-keterangan {
            color: var(--color-on-surface-variant);
            font-size: 14px;
            margin-bottom: 12px;
            line-height: 1.4;
        }

        .jadwal-jam {

            display: inline-flex;
            align-items: center;
            gap: 8px;

            color: var(--color-secondary);

            font-size: 14px;
            font-weight: bold;

            background: var(--color-surface-low);

            padding: 8px 12px;

            border-radius: var(--radius-md);
        }


        /* =========================================================
           EMPTY STATE
           ========================================================= */

        .empty-state {

            text-align: center;

            padding: 32px;

            background:
                var(--color-surface-low);

            border-radius:
                var(--radius-lg);

            color:
                var(--color-on-surface-variant);
        }


        /* =========================================================
           BADGE STATUS
           ========================================================= */

        .badge-ready {

            background:
                var(--status-ready-bg);

            color:
                var(--status-ready-text);

            border:
                1px solid
                var(--status-ready-border);

            padding: 4px 12px;

            border-radius:
                var(--radius-full);

            font-size: 12px;

            font-weight:
                var(--fw-bold);

            display:
                inline-block;
        }


        .badge-error {

            background:
                var(--status-gangguan-bg);

            color:
                var(--status-gangguan-text);

            border:
                1px solid
                var(--status-gangguan-border);

            padding: 4px 12px;

            border-radius:
                var(--radius-full);

            font-size: 12px;

            font-weight:
                var(--fw-bold);

            display:
                inline-block;
        }


        /* =========================================================
           WHATSAPP
           ========================================================= */

        .wa-card {

            display: flex;

            align-items: center;

            gap: 16px;

            text-decoration: none;

            color: inherit;
        }

        .wa-card:hover {
            text-decoration: none;
        }

        .wa-icon {

            width: 56px;
            height: 56px;

            flex-shrink: 0;

            background: #25D366;

            color: #ffffff;

            border-radius: 50%;

            display: flex;
            justify-content: center;
            align-items: center;
            overflow: hidden;
        }

        .wa-icon img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        .wa-nama {

            margin: 0 0 4px;

            font-size: 16px;

            color:
                var(--color-on-surface);
        }

        .wa-nomor {

            font-size: 18px;

            font-weight: bold;

            color:
                var(--color-primary-container);
        }


        /* =========================================================
           SURVEI BANNER
           ========================================================= */

        .survei-banner {

            background:
                var(--color-primary-container);

            color:
                var(--color-on-primary);

            padding:
                var(--stack-lg);

            border-radius:
                var(--radius-xl);

            text-align: center;

            margin-top: 40px;

            box-shadow:
                0 10px 20px
                rgba(0, 69, 13, 0.15);
        }

        .survei-banner h2 {

            font-size: 24px;

            margin-bottom: 8px;
        }

        .survei-banner p {

            opacity: 0.9;

            max-width: 600px;

            margin: 0 auto;
        }

        .btn-survei {

            display: inline-block;

            background:
                var(--color-primary-fixed);

            color:
                var(--color-on-primary-fixed);

            padding: 14px 28px;

            border-radius:
                var(--radius-full);

            font-weight:
                var(--fw-bold);

            margin-top: 20px;

            text-decoration: none;

            transition:
                background 0.2s,
                transform 0.2s;
        }

        .btn-survei:hover {

            background:
                var(--color-primary-fixed-dim);

            text-decoration: none;

            transform:
                scale(1.05);
        }


        /* =========================================================
           FOOTER
           ========================================================= */

        .public-footer {

            text-align: center;

            padding:
                var(--stack-lg) 0;

            color:
                var(--color-on-surface-variant);

            font-size:
                var(--fs-label-md);

            border-top:
                1px solid
                var(--color-surface-variant);

            margin-top: 40px;
        }


        /* =========================================================
           RESPONSIVE
           ========================================================= */

        @media (max-width: 768px) {

            .hero-carousel {
                height: 260px;
            }

            .carousel-overlay {
                padding: 20px;
            }

            .carousel-content h2 {
                font-size: 22px;
            }

            .public-header__logo {
                height: 55px;
            }

        }

        @media (max-width: 480px) {

            .hero-carousel {
                height: 220px;
            }

            .carousel-content h2 {
                font-size: 18px;
            }

            .carousel-content p {
                font-size: 13px;
            }

        }

        /* =========================================================
           KATALOG INFORMASI LAYANAN
           ========================================================= */
        .service-hub {
            position: relative;
            overflow: hidden;
            display: grid;
            grid-template-columns: auto 1fr auto;
            align-items: center;
            gap: 20px;
            margin-bottom: 32px;
            padding: 28px;
            border: 1px solid var(--color-outline-variant);
            border-radius: var(--radius-lg);
            background: linear-gradient(135deg, var(--color-surface) 0%, var(--color-surface-low) 100%);
            box-shadow: var(--shadow-sm);
        }
        .service-hub::after {
            content: '';
            position: absolute;
            width: 150px;
            height: 150px;
            right: -55px;
            top: -70px;
            border-radius: 50%;
            border: 24px solid color-mix(in srgb, var(--color-primary-container) 18%, transparent);
            pointer-events: none;
        }
        .service-hub__icon {
            width: 64px;
            height: 64px;
            display: grid;
            place-items: center;
            border-radius: 18px;
            color: var(--color-on-primary);
            background: var(--color-primary-container);
            box-shadow: 0 8px 20px color-mix(in srgb, var(--color-primary-container) 24%, transparent);
        }
        .service-hub__icon img { width: 48px; height: 48px; display: block; }
        .service-hub__title { margin: 0 0 6px; font-size: 20px; }
        .service-hub__text { margin: 0; color: var(--color-on-surface-variant); line-height: 1.55; }
        .service-hub__meta { display: inline-flex; gap: 7px; align-items: center; margin-top: 10px; font-size: 13px; font-weight: 700; color: var(--color-primary-container); }
        .service-hub__meta img { width: 17px; height: 17px; }
        .service-hub__button,
        .service-detail__back,
        .service-modal__close {
            border: 0;
            cursor: pointer;
            font: inherit;
        }
        .service-hub__button {
            position: relative;
            z-index: 1;
            padding: 12px 18px;
            border-radius: var(--radius-md);
            color: var(--color-on-primary);
            background: var(--color-primary-container);
            font-weight: 700;
            white-space: nowrap;
        }
        .service-modal[hidden] { display: none; }
        .service-modal {
            position: fixed;
            inset: 0;
            z-index: 1000;
            display: grid;
            place-items: center;
            padding: 20px;
        }
        .service-modal__backdrop { position: absolute; inset: 0; background: rgba(15, 23, 42, .58); backdrop-filter: blur(3px); }
        .service-modal__dialog {
            position: relative;
            width: min(960px, 100%);
            max-height: min(86vh, 820px);
            display: flex;
            flex-direction: column;
            overflow: hidden;
            border: 1px solid var(--color-outline-variant);
            border-radius: 22px;
            background: var(--color-surface);
            box-shadow: 0 24px 70px rgba(15, 23, 42, .28);
        }
        .service-modal__header {
            position: relative;
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 16px;
            padding: 22px 24px 18px;
            border-bottom: 1px solid var(--color-outline-variant);
            background: linear-gradient(120deg, var(--color-surface-low), var(--color-surface));
        }
        .service-modal__eyebrow { margin: 0 0 4px; color: var(--color-primary-container); font-size: 12px; font-weight: 800; letter-spacing: .08em; text-transform: uppercase; }
        .service-modal__title { margin: 0; font-size: 23px; }
        .service-modal__subtitle { margin: 5px 0 0; color: var(--color-on-surface-variant); font-size: 14px; }
        .service-modal__close { width: 40px; height: 40px; display: grid; place-items: center; flex: 0 0 40px; border-radius: 50%; color: var(--color-on-surface); background: var(--color-surface-low); font-size: 24px; line-height: 1; }
        .service-modal__body { overflow-y: auto; padding: 22px 24px 26px; }
        .service-search { position: relative; margin-bottom: 18px; }
        .service-search input { width: 100%; min-height: 46px; padding: 10px 44px 10px 14px; border: 1px solid var(--color-outline-variant); border-radius: var(--radius-md); color: var(--color-on-surface); background: var(--color-surface); font: inherit; }
        .service-search > img { position: absolute; right: 14px; top: 50%; width: 20px; height: 20px; transform: translateY(-50%); pointer-events: none; }
        .service-catalog { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 14px; }
        .service-card {
            position: relative;
            min-height: 164px;
            padding: 18px;
            text-align: left;
            border: 1px solid var(--color-outline-variant);
            border-radius: var(--radius-lg);
            color: var(--color-on-surface);
            background: var(--color-surface);
            cursor: pointer;
            transition: transform .18s ease, border-color .18s ease, box-shadow .18s ease;
        }
        .service-card:hover, .service-card:focus-visible { transform: translateY(-3px); border-color: var(--color-primary-container); box-shadow: var(--shadow-sm); outline: none; }
        .service-card__top { display: flex; justify-content: space-between; align-items: center; gap: 10px; margin-bottom: 14px; }
        .service-card__badge { padding: 5px 9px; border-radius: 999px; color: var(--color-primary-container); background: color-mix(in srgb, var(--color-primary-container) 12%, transparent); font-size: 11px; font-weight: 800; letter-spacing: .04em; }
        .service-card__ornament { width: 28px; height: 28px; display: grid; place-items: center; border-radius: 9px; color: var(--color-primary-container); background: var(--color-surface-low); }
        .service-card__ornament img { width: 24px; height: 24px; display: block; }
        .service-card h3 { margin: 0 0 7px; font-size: 16px; }
        .service-card p { margin: 0; color: var(--color-on-surface-variant); font-size: 13px; line-height: 1.45; }
        .service-card__link { display: inline-flex; align-items: center; gap: 5px; margin-top: 13px; color: var(--color-primary-container); font-size: 12px; font-weight: 800; }
        .service-empty-search { grid-column: 1 / -1; padding: 30px; text-align: center; color: var(--color-on-surface-variant); }
        .service-detail[hidden], .service-catalog-view[hidden] { display: none; }
        .service-detail__back { display: inline-flex; align-items: center; gap: 7px; margin-bottom: 18px; padding: 8px 11px; border-radius: 10px; color: var(--color-primary-container); background: var(--color-surface-low); font-weight: 700; }
        .service-detail__hero { position: relative; overflow: hidden; padding: 22px; border-radius: var(--radius-lg); color: var(--color-on-primary); background: linear-gradient(135deg, var(--color-primary-container), var(--color-secondary)); }
        .service-detail__hero::after { content: ''; position: absolute; width: 110px; height: 110px; right: -30px; bottom: -50px; border-radius: 50%; border: 20px solid rgba(255,255,255,.13); }
        .service-detail__code { display: inline-block; margin-bottom: 9px; padding: 4px 9px; border-radius: 999px; background: rgba(255,255,255,.17); font-size: 11px; font-weight: 800; }
        .service-detail__hero h3 { margin: 0 0 7px; font-size: 22px; }
        .service-detail__hero p { max-width: 680px; margin: 0; opacity: .9; line-height: 1.55; }
        .service-detail__icon { float: right; width: 70px; height: 70px; margin: -2px 0 12px 18px; padding: 7px; border-radius: 18px; background: rgba(255,255,255,.9); }
        .service-requirements { margin-top: 20px; padding: 20px; border: 1px solid var(--color-outline-variant); border-radius: var(--radius-lg); background: var(--color-surface); }
        .service-requirements h4 { margin: 0 0 15px; font-size: 17px; }
        .service-requirements ol { display: grid; gap: 10px; margin: 0; padding: 0; list-style: none; counter-reset: requirements; }
        .service-requirements li { counter-increment: requirements; display: grid; grid-template-columns: 30px 1fr; align-items: center; gap: 10px; color: var(--color-on-surface-variant); }
        .service-requirements li::before { content: counter(requirements); width: 30px; height: 30px; display: grid; place-items: center; border-radius: 9px; color: var(--color-primary-container); background: color-mix(in srgb, var(--color-primary-container) 11%, transparent); font-size: 12px; font-weight: 800; }
        .service-facts { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px; margin-top: 14px; }
        .service-fact { padding: 13px; border-radius: var(--radius-md); background: var(--color-surface-low); }
        .service-fact small { display: block; margin-bottom: 3px; color: var(--color-on-surface-variant); }
        .service-fact strong { font-size: 14px; }
        body.modal-open { overflow: hidden; }
        .sr-only { position: absolute !important; width: 1px !important; height: 1px !important; padding: 0 !important; margin: -1px !important; overflow: hidden !important; clip: rect(0, 0, 0, 0) !important; white-space: nowrap !important; border: 0 !important; }
        @media (max-width: 760px) {
            .service-hub { grid-template-columns: auto 1fr; padding: 21px; }
            .service-hub__button { grid-column: 1 / -1; width: 100%; }
            .service-catalog { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .service-modal { padding: 10px; }
            .service-modal__dialog { max-height: 92vh; border-radius: 18px; }
        }
        @media (max-width: 520px) {
            .service-hub { grid-template-columns: 1fr; }
            .service-hub__icon { width: 54px; height: 54px; }
            .service-catalog, .service-facts { grid-template-columns: 1fr; }
            .service-modal__header, .service-modal__body { padding-left: 17px; padding-right: 17px; }
        }

        /* =========================================================
           WEBSITE RESMI KECAMATAN
           ========================================================= */
        .official-site-cta {
            position: relative;
            overflow: hidden;
            display: grid;
            grid-template-columns: 180px 1fr;
            align-items: center;
            gap: 30px;
            margin-top: 40px;
            padding: 34px;
            border-radius: var(--radius-xl);
            color: var(--color-on-primary);
            background: linear-gradient(135deg, #123d18 0%, var(--color-primary-container) 58%, #2e7d32 100%);
            box-shadow: 0 14px 32px rgba(0, 69, 13, .2);
        }
        .official-site-cta::before,
        .official-site-cta::after {
            content: '';
            position: absolute;
            border-radius: 50%;
            border: 22px solid rgba(255,255,255,.07);
            pointer-events: none;
        }
        .official-site-cta::before { width: 160px; height: 160px; right: -60px; top: -85px; }
        .official-site-cta::after { width: 90px; height: 90px; left: -45px; bottom: -55px; }
        .official-site-cta__logo {
            position: relative;
            z-index: 1;
            display: grid;
            place-items: center;
            min-height: 150px;
            padding: 20px;
            border: 1px solid rgba(255,255,255,.28);
            border-radius: 24px;
            background: rgba(255,255,255,.94);
            box-shadow: 0 10px 24px rgba(0,0,0,.16);
        }
        .official-site-cta__logo img { width: auto; height: 104px; display: block; }
        .official-site-cta__content { position: relative; z-index: 1; }
        .official-site-cta__eyebrow { display: inline-flex; align-items: center; gap: 7px; margin-bottom: 10px; padding: 5px 10px; border: 1px solid rgba(255,255,255,.22); border-radius: 999px; background: rgba(255,255,255,.1); font-size: 11px; font-weight: 800; letter-spacing: .08em; text-transform: uppercase; }
        .official-site-cta h2 { max-width: 620px; margin: 0 0 10px; color: #fff; font-size: clamp(23px, 3vw, 32px); line-height: 1.2; }
        .official-site-cta__description { max-width: 680px; margin: 0; color: rgba(255,255,255,.86); line-height: 1.65; }
        .official-site-cta__tags { display: flex; flex-wrap: wrap; gap: 8px; margin: 17px 0 20px; }
        .official-site-cta__tags span { padding: 5px 9px; border-radius: 8px; background: rgba(255,255,255,.1); font-size: 12px; }
        .official-site-cta__action { display: inline-flex; align-items: center; gap: 9px; padding: 12px 17px; border-radius: var(--radius-md); color: var(--color-primary-container); background: #fff; font-weight: 800; text-decoration: none; box-shadow: 0 8px 18px rgba(0,0,0,.15); transition: transform .18s ease, box-shadow .18s ease; }
        .official-site-cta__action:hover, .official-site-cta__action:focus-visible { color: var(--color-primary-container); text-decoration: none; transform: translateY(-2px); box-shadow: 0 11px 24px rgba(0,0,0,.2); }
        .official-site-cta__domain { display: block; margin-top: 9px; color: rgba(255,255,255,.7); font-size: 12px; }
        @media (max-width: 700px) {
            .official-site-cta { grid-template-columns: 1fr; gap: 22px; padding: 25px; text-align: center; }
            .official-site-cta__logo { width: 130px; min-height: 120px; margin: 0 auto; }
            .official-site-cta__logo img { height: 82px; }
            .official-site-cta__tags { justify-content: center; }
            .official-site-cta__action { justify-content: center; width: 100%; }
        }

    </style>

</head>


<body>


<!-- =========================================================
     HEADER
     ========================================================= -->

<header class="public-header">

    <div class="public-container public-header__content">

        <img
            src="assets/images/logo kab.svg"
            alt="Logo Kabupaten Probolinggo"
            class="public-header__logo"
        >

        <h1 class="public-header__title">
            Portal Layanan Publik Kecamatan Maron
        </h1>

        <div class="public-header__date">

            <?= htmlspecialchars($hari_ini) ?>

        </div>

    </div>

</header>



<!-- =========================================================
     MAIN CONTENT
     ========================================================= -->

<main class="public-container">


    <!-- =====================================================
         HERO CAROUSEL
         ===================================================== -->

    <?php if (!empty($hero_slides)): ?>
        <div class="hero-carousel" aria-label="Informasi utama Kecamatan Maron">
            <div class="carousel-inner" id="carouselInner">
                <?php foreach ($hero_slides as $index => $slide): ?>
                    <article class="carousel-item" aria-hidden="<?= $index === 0 ? 'false' : 'true' ?>">
                        <img
                            src="<?= htmlspecialchars($slide['gambar_path'], ENT_QUOTES, 'UTF-8') ?>"
                            alt="<?= htmlspecialchars($slide['alt_text'], ENT_QUOTES, 'UTF-8') ?>"
                            style="object-position: <?= max(0, min(100, (float) $slide['focal_x'])) ?>% <?= max(0, min(100, (float) $slide['focal_y'])) ?>%;"
                            <?= $index === 0 ? 'fetchpriority="high"' : 'loading="lazy"' ?>
                        >
                        <div class="carousel-overlay">
                            <div class="carousel-content">
                                <h2><?= htmlspecialchars($slide['title'], ENT_QUOTES, 'UTF-8') ?></h2>
                                <p><?= htmlspecialchars($slide['deskripsi'], ENT_QUOTES, 'UTF-8') ?></p>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>

            <?php if (count($hero_slides) > 1): ?>
                <div class="carousel-indicators" aria-label="Pilih slide">
                    <?php foreach ($hero_slides as $index => $slide): ?>
                        <button
                            type="button"
                            class="indicator<?= $index === 0 ? ' active' : '' ?>"
                            onclick="goToSlide(<?= $index ?>)"
                            aria-label="Tampilkan slide <?= $index + 1 ?>: <?= htmlspecialchars($slide['title'], ENT_QUOTES, 'UTF-8') ?>"
                            aria-current="<?= $index === 0 ? 'true' : 'false' ?>"
                        ></button>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>



    <!-- =====================================================
         1. JADWAL CAMAT
         ===================================================== -->
        <h2 class="section-title">

            <svg
                viewBox="0 0 24 24"
                width="24"
                height="24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
            >

                <rect
                    x="3"
                    y="4"
                    width="18"
                    height="18"
                    rx="2"
                    ry="2"
                ></rect>

                <line
                    x1="16"
                    y1="2"
                    x2="16"
                    y2="6"
                ></line>

                <line
                    x1="8"
                    y1="2"
                    x2="8"
                    y2="6"
                ></line>

                <line
                    x1="3"
                    y1="10"
                    x2="21"
                    y2="10"
                ></line>

            </svg>

            Info Kegiatan Camat Hari Ini
        </h2>
        <p style="margin-top: -8px; margin-bottom: 20px; color: var(--color-on-surface-variant); font-size: 14px; font-weight: bold;">
            Layanan tatap muka dengan Camat
        </p>
    

    <?php if (!empty($jadwal_camat)): ?>

        <div class="card-grid">

            <?php foreach ($jadwal_camat as $row): ?>

                <div class="card">

                    <h3 class="jadwal-title">

                        <?= htmlspecialchars(
                            mb_convert_case(
                                $row['jenis_kegiatan'],
                                MB_CASE_TITLE,
                                'UTF-8'
                            )
                        ) ?>

                    </h3>


                    <p class="jadwal-keterangan">

                        <?= htmlspecialchars(
                            ucfirst(
                                $row['keterangan'] ?? ''
                            )
                        ) ?>

                    </p>


                    <div class="jadwal-jam">

                        <svg
                            viewBox="0 0 24 24"
                            width="16"
                            height="16"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                        >

                            <circle
                                cx="12"
                                cy="12"
                                r="10"
                            ></circle>

                            <polyline
                                points="12 6 12 12 16 14"
                            ></polyline>

                        </svg>


                        Pukul

                        <?= htmlspecialchars(
                            substr(
                                $row['jam_mulai'],
                                0,
                                5
                            )
                        ) ?>

                        -

                        <?= htmlspecialchars(
                            substr(
                                $row['jam_selesai'],
                                0,
                                5
                            )
                        ) ?>

                        WIB

                    </div>

                </div>

            <?php endforeach; ?>

        </div>


    <?php else: ?>


        <div class="empty-state">

            Belum ada jadwal kegiatan Camat
            untuk hari ini.

        </div>


    <?php endif; ?>



    <!-- =====================================================
         2. STATUS ADMINDUK
         ===================================================== -->

    <h2 class="section-title">

        <svg
            viewBox="0 0 24 24"
            width="24"
            height="24"
            fill="none"
            stroke="currentColor"
            stroke-width="2"
        >

            <path
                d="M22 11.08V12a10 10 0 1 1-5.93-9.14"
            ></path>

            <polyline
                points="22 4 12 14.01 9 11.01"
            ></polyline>

        </svg>

        Status Sistem Adminduk

    </h2>


    <?php if (!empty($status_adminduk)): ?>

        <div class="card-grid">

            <?php foreach ($status_adminduk as $row): ?>

                <div
                    class="card"
                    style="
                        display:flex;
                        justify-content:space-between;
                        align-items:flex-start;
                        gap:16px;
                    "
                >

                    <div style="flex:1;">


                        <h3
                            style="
                                margin-bottom:4px;
                                font-size:16px;
                            "
                        >

                            <?= htmlspecialchars(
                                mb_convert_case(
                                    $row['nama_layanan'],
                                    MB_CASE_TITLE,
                                    'UTF-8'
                                )
                            ) ?>

                        </h3>


                        <?php
                        $catatan = trim(
                            $row['catatan'] ?? ''
                        );
                        ?>


                        <?php if (
                            $catatan !== ''
                            &&
                            $catatan !== '-'
                        ): ?>

                            <p
                                style="
                                    color:
                                    var(--color-on-surface-variant);
                                    font-size:13px;
                                    font-style:italic;
                                    margin-bottom:0;
                                "
                            >

                                Catatan:

                                <?= htmlspecialchars(
                                    ucfirst($catatan)
                                ) ?>

                            </p>

                        <?php endif; ?>


                    </div>


                    <div>

                        <?php
                        $status = mb_strtolower(
                            trim(
                                $row['status'] ?? ''
                            ),
                            'UTF-8'
                        );
                        ?>


                        <?php if ($status === 'ready'): ?>

                            <span class="badge-ready">
                                Ready
                            </span>

                        <?php else: ?>

                            <span class="badge-error">
                                Gangguan
                            </span>

                        <?php endif; ?>

                    </div>

                </div>

            <?php endforeach; ?>

        </div>


    <?php else: ?>

        <div class="empty-state">

            Belum ada data status layanan Adminduk.

        </div>

    <?php endif; ?>



    <!-- =====================================================
         3. INFORMASI LAYANAN KECAMATAN
         ===================================================== -->

    <?php if (!empty($layanan_kecamatan)): ?>

        <section aria-labelledby="service-hub-title" style="margin-top: 20px;">
            <div class="service-hub">
                <div class="service-hub__icon" aria-hidden="true">
                    <img src="assets/images/services/katalog-layanan.svg" alt="">
                </div>
                <div>
                    <h2 class="service-hub__title" id="service-hub-title">Informasi Layanan Kecamatan</h2>
                    <p class="service-hub__text">Temukan jenis layanan dan dokumen yang perlu disiapkan sebelum datang ke Kecamatan Maron.</p>
                    <span class="service-hub__meta">
                        <img src="assets/images/services/status-tersedia.svg" alt="" aria-hidden="true">
                        <?= count($layanan_kecamatan) ?> layanan tersedia
                    </span>
                </div>
                <button type="button" class="service-hub__button" id="openServiceModal" aria-haspopup="dialog">
                    Lihat Semua Layanan
                </button>
            </div>
        </section>

        <div class="service-modal" id="serviceModal" hidden>
            <div class="service-modal__backdrop" data-service-close></div>
            <section class="service-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="serviceModalTitle" tabindex="-1">
                <header class="service-modal__header">
                    <div>
                        <p class="service-modal__eyebrow">Pusat Informasi</p>
                        <h2 class="service-modal__title" id="serviceModalTitle">Layanan Kecamatan</h2>
                        <p class="service-modal__subtitle" id="serviceModalSubtitle">Pilih layanan untuk melihat persyaratan lengkap.</p>
                    </div>
                    <button type="button" class="service-modal__close" data-service-close aria-label="Tutup informasi layanan">&times;</button>
                </header>

                <div class="service-modal__body">
                    <div class="service-catalog-view" id="serviceCatalogView">
                        <label class="service-search">
                            <span class="sr-only">Cari layanan</span>
                            <input type="search" id="serviceSearch" placeholder="Cari KTP, KK, akta, atau layanan lain..." autocomplete="off">
                            <img src="assets/images/services/pencarian.svg" alt="" aria-hidden="true">
                        </label>

                        <div class="service-catalog" id="serviceCatalog" style="margin-top: 10px;">
                            <?php foreach ($layanan_kecamatan as $layanan): ?>
                                <button
                                    type="button"
                                    class="service-card"
                                    data-service-id="<?= (int) $layanan['id'] ?>"
                                    data-service-search="<?= htmlspecialchars(mb_strtolower($layanan['kode_layanan'] . ' ' . $layanan['nama_layanan'] . ' ' . $layanan['deskripsi'], 'UTF-8'), ENT_QUOTES, 'UTF-8') ?>"
                                >
                                    <span class="service-card__top">
                                        <span class="service-card__badge"><?= htmlspecialchars(str_replace('_', ' ', $layanan['kode_layanan']), ENT_QUOTES, 'UTF-8') ?></span>
                                        <span class="service-card__ornament" aria-hidden="true">
                                            <img src="<?= htmlspecialchars($layanan['ikon'], ENT_QUOTES, 'UTF-8') ?>" alt="">
                                        </span>
                                    </span>
                                    <h3><?= htmlspecialchars($layanan['nama_layanan'], ENT_QUOTES, 'UTF-8') ?></h3>
                                    <p><?= htmlspecialchars($layanan['deskripsi'] ?: 'Lihat persyaratan yang perlu disiapkan.', ENT_QUOTES, 'UTF-8') ?></p>
                                    <span class="service-card__link">Lihat persyaratan <span aria-hidden="true">&rarr;</span></span>
                                </button>
                            <?php endforeach; ?>
                            <p class="service-empty-search" id="serviceEmptySearch" hidden>Layanan yang Anda cari belum ditemukan.</p>
                        </div>
                    </div>

                    <?php foreach ($layanan_kecamatan as $layanan): ?>
                        <article class="service-detail" id="service-detail-<?= (int) $layanan['id'] ?>" data-service-detail hidden>
                            <button type="button" class="service-detail__back" data-service-back>
                                <span aria-hidden="true">&larr;</span> Kembali ke daftar
                            </button>
                            <div class="service-detail__hero">
                                <img class="service-detail__icon" src="<?= htmlspecialchars($layanan['ikon'], ENT_QUOTES, 'UTF-8') ?>" alt="" aria-hidden="true">
                                <span class="service-detail__code"><?= htmlspecialchars(str_replace('_', ' ', $layanan['kode_layanan']), ENT_QUOTES, 'UTF-8') ?></span>
                                <h3><?= htmlspecialchars($layanan['nama_layanan'], ENT_QUOTES, 'UTF-8') ?></h3>
                                <p><?= htmlspecialchars($layanan['deskripsi'] ?: 'Informasi persyaratan layanan Kecamatan Maron.', ENT_QUOTES, 'UTF-8') ?></p>
                            </div>

                            <div class="service-requirements">
                                <h4>Persyaratan yang perlu disiapkan</h4>
                                <?php if (!empty($layanan['persyaratan'])): ?>
                                    <ol>
                                        <?php foreach ($layanan['persyaratan'] as $persyaratan): ?>
                                            <li><?= htmlspecialchars($persyaratan, ENT_QUOTES, 'UTF-8') ?></li>
                                        <?php endforeach; ?>
                                    </ol>
                                <?php else: ?>
                                    <p class="service-modal__subtitle">Persyaratan belum tersedia. Silakan hubungi petugas.</p>
                                <?php endif; ?>
                            </div>

                            <?php if ($layanan['estimasi_waktu'] || $layanan['biaya'] || $layanan['lokasi'] || $layanan['jam_pelayanan']): ?>
                                <div class="service-facts">
                                    <?php foreach (['estimasi_waktu' => 'Estimasi', 'biaya' => 'Biaya', 'lokasi' => 'Lokasi', 'jam_pelayanan' => 'Jam pelayanan'] as $field => $label): ?>
                                        <?php if (!empty($layanan[$field])): ?>
                                            <div class="service-fact"><small><?= $label ?></small><strong><?= htmlspecialchars($layanan[$field], ENT_QUOTES, 'UTF-8') ?></strong></div>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($layanan['catatan'])): ?>
                                <p class="service-modal__subtitle" style="margin-top: 14px;"><strong>Catatan:</strong> <?= htmlspecialchars($layanan['catatan'], ENT_QUOTES, 'UTF-8') ?></p>
                            <?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>
        </div>

    <?php endif; ?>



    <!-- =====================================================
         4. KONTAK WHATSAPP
         ===================================================== -->

    <h2 class="section-title">

        <svg
            viewBox="0 0 24 24"
            width="24"
            height="24"
            fill="none"
            stroke="currentColor"
            stroke-width="2"
        >

            <path
                d="
                    M22 16.92v3
                    a2 2 0 0 1-2.18 2
                    19.79 19.79 0 0 1-8.63-3.07
                    19.5 19.5 0 0 1-6-6
                    19.79 19.79 0 0 1-3.07-8.67
                    A2 2 0 0 1 4.11 2h3
                    a2 2 0 0 1 2 1.72
                    12.84 12.84 0 0 0 .7 2.81
                    2 2 0 0 1-.45 2.11
                    L8.09 9.91
                    a16 16 0 0 0 6 6
                    l1.27-1.27
                    a2 2 0 0 1 2.11-.45
                    12.84 12.84 0 0 0 2.81.7
                    A2 2 0 0 1 22 16.92z
                "
            ></path>

        </svg>

        Kontak Layanan WhatsApp

    </h2>


    <?php if (!empty($kontak_layanan)): ?>

        <div class="card-grid">


            <?php foreach ($kontak_layanan as $row): ?>


                <?php

                // Ambil angka saja
                $nomor_wa = preg_replace(
                    '/\D/',
                    '',
                    $row['no_layanan']
                );


                /*
                 * Normalisasi nomor WhatsApp Indonesia
                 *
                 * 085645975480
                 * menjadi
                 * 6285645975480
                 *
                 * atau
                 *
                 * 85645975480
                 * menjadi
                 * 6285645975480
                 */

                if (
                    str_starts_with(
                        $nomor_wa,
                        '0'
                    )
                ) {

                    $nomor_wa =
                        '62'
                        .
                        substr(
                            $nomor_wa,
                            1
                        );

                } elseif (
                    str_starts_with(
                        $nomor_wa,
                        '8'
                    )
                ) {

                    $nomor_wa =
                        '62'
                        .
                        $nomor_wa;

                }


                $link_wa =
                    'https://wa.me/'
                    .
                    $nomor_wa;

                $gambar_profil = null;
                $gambar_path = (string) ($row['gambar_profil'] ?? '');
                if (
                    preg_match('#^assets/storage/kontak/[a-f0-9]{32}\.(?:jpg|png|webp)$#', $gambar_path)
                    && is_file(__DIR__ . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $gambar_path))
                ) {
                    $gambar_profil = $gambar_path;
                }

                ?>


                <a
                    href="<?= htmlspecialchars($link_wa) ?>"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="card wa-card"
                >


                    <div class="wa-icon">

                        <?php if ($gambar_profil): ?>

                            <img
                                src="<?= htmlspecialchars($gambar_profil, ENT_QUOTES, 'UTF-8') ?>"
                                alt="Profil <?= htmlspecialchars(mb_convert_case($row['nama'], MB_CASE_TITLE, 'UTF-8'), ENT_QUOTES, 'UTF-8') ?>"
                                loading="lazy"
                                decoding="async"
                            >

                        <?php else: ?>

                        <svg
                            viewBox="0 0 24 24"
                            width="28"
                            height="28"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                        >

                            <path
                                d="
                                    M22 16.92v3
                                    a2 2 0 0 1-2.18 2
                                    19.79 19.79 0 0 1-8.63-3.07
                                    19.5 19.5 0 0 1-6-6
                                    19.79 19.79 0 0 1-3.07-8.67
                                    A2 2 0 0 1 4.11 2h3
                                    a2 2 0 0 1 2 1.72
                                    12.84 12.84 0 0 0 .7 2.81
                                    2 2 0 0 1-.45 2.11
                                    L8.09 9.91
                                    a16 16 0 0 0 6 6
                                    l1.27-1.27
                                    a2 2 0 0 1 2.11-.45
                                    12.84 12.84 0 0 0 2.81.7
                                    A2 2 0 0 1 22 16.92z
                                "
                            ></path>

                        </svg>

                        <?php endif; ?>

                    </div>


                    <div>


                        <h3 class="wa-nama">

                            <?= htmlspecialchars(
                                mb_convert_case(
                                    $row['nama'],
                                    MB_CASE_TITLE,
                                    'UTF-8'
                                )
                            ) ?>

                        </h3>


                        <div class="wa-nomor">

                            <?= htmlspecialchars(
                                $row['no_layanan']
                            ) ?>

                        </div>


                        <div
                            style="
                                margin-top:4px;
                                font-size:12px;
                                color:
                                var(--color-on-surface-variant);
                            "
                        >

                            Klik untuk membuka WhatsApp

                        </div>


                    </div>


                </a>


            <?php endforeach; ?>


        </div>


    <?php else: ?>


        <div class="empty-state">

            Belum ada kontak layanan yang aktif.

        </div>


    <?php endif; ?>



    <!-- =====================================================
         5. WEBSITE RESMI KECAMATAN
         ===================================================== -->

    <section class="official-site-cta" aria-labelledby="official-site-title">
        <div class="official-site-cta__logo" aria-hidden="true">
            <img src="assets/images/logo kab.svg" alt="">
        </div>
        <div class="official-site-cta__content">
            <span class="official-site-cta__eyebrow">
                <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="m8 12 2.5 2.5L16 9"/></svg>
                Untuk Masyarakat
            </span>
            <h2 id="official-site-title">Informasi Kecamatan Maron Lebih Lengkap</h2>
            <p class="official-site-cta__description">Kunjungi website resmi Kecamatan Maron untuk mendapatkan berita terbaru, profil wilayah, agenda pemerintahan, informasi publik, dan layanan masyarakat lainnya.</p>
            <div class="official-site-cta__tags" aria-label="Informasi yang tersedia">
                <span>Berita Daerah</span>
                <span>Profil Kecamatan</span>
                <span>Informasi Publik</span>
            </div>
            <a
                class="official-site-cta__action"
                href="https://maron.probolinggokab.go.id/"
                target="_blank"
                rel="noopener noreferrer"
                aria-label="Kunjungi website resmi Kecamatan Maron di tab baru"
            >
                Kunjungi Website Resmi
                <svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M14 3h7v7"/><path d="M10 14 21 3"/><path d="M21 14v5a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5"/></svg>
            </a>
            <small class="official-site-cta__domain">maron.probolinggokab.go.id</small>
        </div>
    </section>


    <!-- =====================================================
         6. SURVEI
         ===================================================== -->

    <?php if (
        !empty($safe_survey_url)
    ): ?>


        <div class="survei-banner">


            <h2>

                Puas dengan layanan kami?

            </h2>


            <p>

                Bantu kami menjadi lebih baik dengan
                meluangkan sedikit waktu Anda untuk
                mengisi survei kepuasan masyarakat.

            </p>


            <a
                href="<?= htmlspecialchars(
                    $safe_survey_url,
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>"
                target="_blank"
                rel="noopener noreferrer"
                class="btn-survei"
            >

                Isi Survei Sekarang

            </a>


        </div>


    <?php endif; ?>
    


</main>



<!-- =========================================================
     FOOTER
     ========================================================= -->

<footer class="public-footer">


    <div class="public-container">


        <p>
            &copy; <?= date('Y') ?> 
            Kecamatan Maron — Kabupaten Probolinggo.
        </p>


        <p
            style="
                margin-top:4px;
                opacity:0.7;
            "
        >

            Informasi ini diperbarui pada:

            <?= htmlspecialchars(
                $waktu_sinkronisasi
            ) ?>

        </p>
        

        <!-- LOGIN ADMIN -->

        <a
            href="login.php"
            style="
                display:inline-block;
                margin-top:24px;
                color:
                var(--color-surface-dim);
            "
            aria-label="Login Admin"
            title="Login Admin"
        >

            <svg
                viewBox="0 0 24 24"
                width="16"
                height="16"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
            >

                <rect
                    x="3"
                    y="11"
                    width="18"
                    height="11"
                    rx="2"
                    ry="2"
                ></rect>

                <path
                    d="
                        M7 11V7
                        a5 5 0 0 1 10 0v4
                    "
                ></path>

            </svg>

        </a>


    </div>


</footer>



<!-- =========================================================
     JAVASCRIPT CAROUSEL
     ========================================================= -->

<script>

    let currentSlide = 0;

    const slides =
        document.querySelectorAll(
            '.carousel-item'
        );

    const indicators =
        document.querySelectorAll(
            '.indicator'
        );

    const carouselInner =
        document.getElementById(
            'carouselInner'
        );

    const totalSlides =
        slides.length;

    let slideInterval;


    function updateCarousel() {

        if (
            !carouselInner
            ||
            totalSlides === 0
        ) {
            return;
        }


        carouselInner.style.transform =
            `translateX(-${currentSlide * 100}%)`;


        indicators.forEach(
            (indicator, index) => {

                indicator.classList.toggle(
                    'active',
                    index === currentSlide
                );
                indicator.setAttribute(
                    'aria-current',
                    index === currentSlide ? 'true' : 'false'
                );

            }
        );

        slides.forEach((slide, index) => {
            slide.setAttribute('aria-hidden', index === currentSlide ? 'false' : 'true');
        });

    }


    function nextSlide() {

        if (totalSlides === 0) {
            return;
        }

        currentSlide =
            (currentSlide + 1)
            %
            totalSlides;

        updateCarousel();

    }


    function goToSlide(index) {

        if (
            index < 0
            ||
            index >= totalSlides
        ) {
            return;
        }

        currentSlide = index;

        updateCarousel();

        resetInterval();

    }


    function resetInterval() {

        clearInterval(
            slideInterval
        );

        slideInterval =
            setInterval(
                nextSlide,
                5000
            );

    }


    if (totalSlides > 1) {

        slideInterval =
            setInterval(
                nextSlide,
                5000
            );

    }

    const serviceModal = document.getElementById('serviceModal');
    const openServiceModal = document.getElementById('openServiceModal');

    if (serviceModal && openServiceModal) {
        const serviceDialog = serviceModal.querySelector('.service-modal__dialog');
        const catalogView = document.getElementById('serviceCatalogView');
        const serviceSearch = document.getElementById('serviceSearch');
        const serviceCards = Array.from(serviceModal.querySelectorAll('[data-service-id]'));
        const serviceDetails = Array.from(serviceModal.querySelectorAll('[data-service-detail]'));
        const emptySearch = document.getElementById('serviceEmptySearch');
        const modalTitle = document.getElementById('serviceModalTitle');
        const modalSubtitle = document.getElementById('serviceModalSubtitle');
        let previousFocus = null;
        let activeServiceButton = null;

        function showServiceCatalog(restoreFocus = false) {
            catalogView.hidden = false;
            serviceDetails.forEach(detail => detail.hidden = true);
            modalTitle.textContent = 'Layanan Kecamatan';
            modalSubtitle.textContent = 'Pilih layanan untuk melihat persyaratan lengkap.';
            if (restoreFocus && activeServiceButton) {
                activeServiceButton.focus();
            }
        }

        function openServices() {
            previousFocus = document.activeElement;
            serviceModal.hidden = false;
            document.body.classList.add('modal-open');
            showServiceCatalog();
            serviceSearch.value = '';
            serviceCards.forEach(card => card.hidden = false);
            emptySearch.hidden = true;
            requestAnimationFrame(() => serviceSearch.focus());
        }

        function closeServices() {
            serviceModal.hidden = true;
            document.body.classList.remove('modal-open');
            showServiceCatalog();
            if (previousFocus) previousFocus.focus();
        }

        function showServiceDetail(button) {
            const detail = document.getElementById(`service-detail-${button.dataset.serviceId}`);
            if (!detail) return;
            activeServiceButton = button;
            catalogView.hidden = true;
            serviceDetails.forEach(item => item.hidden = item !== detail);
            const detailTitle = detail.querySelector('h3');
            modalTitle.textContent = detailTitle ? detailTitle.textContent : 'Detail Layanan';
            modalSubtitle.textContent = 'Periksa seluruh dokumen sebelum datang ke kantor kecamatan.';
            const backButton = detail.querySelector('[data-service-back]');
            if (backButton) backButton.focus();
        }

        openServiceModal.addEventListener('click', openServices);
        serviceModal.querySelectorAll('[data-service-close]').forEach(button => {
            button.addEventListener('click', closeServices);
        });
        serviceCards.forEach(button => {
            button.addEventListener('click', () => showServiceDetail(button));
        });
        serviceModal.querySelectorAll('[data-service-back]').forEach(button => {
            button.addEventListener('click', () => showServiceCatalog(true));
        });
        serviceSearch.addEventListener('input', () => {
            const query = serviceSearch.value.trim().toLocaleLowerCase('id-ID');
            let visibleCount = 0;
            serviceCards.forEach(card => {
                const visible = card.dataset.serviceSearch.includes(query);
                card.hidden = !visible;
                if (visible) visibleCount += 1;
            });
            emptySearch.hidden = visibleCount !== 0;
        });

        serviceModal.addEventListener('keydown', event => {
            if (event.key === 'Escape') {
                event.preventDefault();
                closeServices();
                return;
            }
            if (event.key !== 'Tab') return;
            const focusable = Array.from(serviceDialog.querySelectorAll(
                'button:not([hidden]), input:not([hidden]), [href]:not([hidden]), [tabindex]:not([tabindex="-1"]):not([hidden])'
            )).filter(element => element.offsetParent !== null);
            if (!focusable.length) return;
            const first = focusable[0];
            const last = focusable[focusable.length - 1];
            if (event.shiftKey && document.activeElement === first) {
                event.preventDefault();
                last.focus();
            } else if (!event.shiftKey && document.activeElement === last) {
                event.preventDefault();
                first.focus();
            }
        });
    }

</script>


</body>

</html>
