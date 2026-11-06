<?php

require_once '../config/security.php';
start_secure_session();

if (
    !isset($_SESSION['user_logged_in']) ||
    $_SESSION['user_logged_in'] !== true
) {
    header('Location: ../login.php');
    exit;
}

require_once '../config/database.php';

date_default_timezone_set('Asia/Jakarta');

$nama_admin = $_SESSION['user_nama'] ?? 'Admin';


// =========================================================
// JADWAL CAMAT HARI INI
// =========================================================

$tanggal_hari_ini = date('Y-m-d');

$stmt = $conn->prepare("
    SELECT *
    FROM jadwal_camat
    WHERE tanggal = :tanggal
    ORDER BY jam_mulai ASC
");

$stmt->execute([
    ':tanggal' => $tanggal_hari_ini
]);

$dataCamat = $stmt->fetchAll(PDO::FETCH_ASSOC);


// =========================================================
// STATUS ADMINDUK
// =========================================================

$stmt_status = $conn->query("
    SELECT *
    FROM status_adminduk
    ORDER BY created_at DESC
");

$data_status = $stmt_status->fetchAll(PDO::FETCH_ASSOC);


// =========================================================
// KONTAK
// =========================================================

$stmt_kontak = $conn->query("
    SELECT *
    FROM kontak_layanan
");

$data_kontak = $stmt_kontak->fetchAll(PDO::FETCH_ASSOC);


// =========================================================
// LINK SURVEI
// =========================================================

$stmt_link = $conn->query("
    SELECT *
    FROM link_survei
    LIMIT 1
");

$data_link = $stmt_link->fetch(PDO::FETCH_ASSOC);

$count_carousel = (int) $conn->query("SELECT COUNT(*) FROM hero_carousel")->fetchColumn();

$count_jadwal = count($dataCamat);
$count_status = count($data_status);

?>

<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <meta
        name="robots"
        content="noindex, nofollow"
    >

    <title>
        Dashboard Admin — Portal Kecamatan Maron
    </title>


    <!-- GOOGLE FONT -->

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
        href="../assets/css/style.css?v=20260811"
    >

    <!--
        ?v=20260811-3 digunakan agar browser
        tidak mengambil admin.css lama dari cache
    -->

    <link
        rel="stylesheet"
        href="../assets/css/admin.css?v=20260811-3"
    >


    <!-- =====================================================
         HEADER CSS
         Sengaja ditempatkan setelah admin.css
         supaya tidak bentrok dengan header lama.
         ===================================================== -->

    <style>

        /* =================================================
           HEADER
           ================================================= */

        .admin-header {
            position: sticky !important;
            top: 0 !important;
            z-index: 9999 !important;

            width: 100% !important;

            padding: 0 !important;

            background: #ffffff !important;

            border-bottom:
                1px solid #d9ded7 !important;
        }


        .admin-header__container {

            width: 100% !important;

            max-width: var(--max-width) !important;

            min-height: 66px !important;

            margin: 0 auto !important;

            padding:
                0 var(--gutter) !important;

            display: flex !important;

            align-items: center !important;

            justify-content:
                space-between !important;

            gap: 20px !important;
        }


        /* =================================================
           BRAND
           ================================================= */

        .admin-header__brand {

            display: flex !important;

            align-items: center !important;

            gap: 9px !important;

            flex-shrink: 0 !important;

            white-space: nowrap !important;

            color: #111811 !important;

            font-family:
                'Public Sans',
                sans-serif !important;

            font-size: 18px !important;

            font-weight: 700 !important;

            line-height: 1 !important;

            text-decoration: none !important;
        }


        .admin-header__brand:hover {
            text-decoration: none !important;
        }


        .admin-header__brand svg {

            width: 23px !important;

            height: 23px !important;

            flex-shrink: 0 !important;

            stroke: #17651f !important;
        }


        /* =================================================
           USER MENU WRAPPER
           ================================================= */

        .admin-user-menu {

            position: relative !important;

            display: block !important;

            margin-left: auto !important;
        }


        /* =================================================
           USER BUTTON
           ================================================= */

        .admin-user-button {

            -webkit-appearance:
                none !important;

            appearance:
                none !important;


            margin: 0 !important;


            border:
                1px solid #d6ddd4 !important;

            outline:
                none !important;


            background:
                #ffffff !important;


            min-height:
                42px !important;


            padding:
                8px 12px !important;


            border-radius:
                10px !important;


            display:
                flex !important;

            align-items:
                center !important;

            justify-content:
                center !important;


            gap:
                8px !important;


            color:
                #1c211c !important;


            font-family:
                'Public Sans',
                sans-serif !important;


            font-size:
                14px !important;


            font-weight:
                500 !important;


            line-height:
                1 !important;


            cursor:
                pointer !important;


            box-shadow:
                none !important;


            transition:
                background .2s ease,
                border-color .2s ease,
                box-shadow .2s ease !important;
        }


        .admin-user-button:hover {

            background:
                #f5f7f4 !important;

            border-color:
                #bfc8bd !important;
        }


        .admin-user-button:focus {

            box-shadow:
                0 0 0 3px
                rgba(23, 101, 31, .10)
                !important;
        }


        .admin-user-button svg {

            display:
                block !important;

            flex-shrink:
                0 !important;
        }


        .admin-user-chevron {

            transition:
                transform .2s ease !important;
        }


        .admin-user-button.active
        .admin-user-chevron {

            transform:
                rotate(180deg) !important;
        }


        /* =================================================
           DROPDOWN
           ================================================= */

        .admin-user-dropdown {

            position:
                absolute !important;


            top:
                calc(100% + 9px) !important;


            right:
                0 !important;


            left:
                auto !important;


            width:
                230px !important;


            padding:
                8px !important;


            margin:
                0 !important;


            background:
                #ffffff !important;


            border:
                1px solid #dce2da !important;


            border-radius:
                12px !important;


            box-shadow:
                0 14px 35px
                rgba(0, 0, 0, .16)
                !important;


            /*
             * PENTING:
             * Dropdown default HARUS tertutup
             */

            display:
                block !important;


            opacity:
                0 !important;


            visibility:
                hidden !important;


            pointer-events:
                none !important;


            transform:
                translateY(-7px) !important;


            transition:
                opacity .18s ease,
                visibility .18s ease,
                transform .18s ease
                !important;


            z-index:
                99999 !important;
        }


        /* Dropdown ketika icon diklik */

        .admin-user-dropdown.show {

            opacity:
                1 !important;

            visibility:
                visible !important;

            pointer-events:
                auto !important;

            transform:
                translateY(0) !important;
        }


        /* =================================================
           PROFILE DROPDOWN
           ================================================= */

        .admin-dropdown-profile {

            display:
                flex !important;

            flex-direction:
                column !important;

            gap:
                3px !important;


            padding:
                10px 11px !important;
        }


        .admin-dropdown-label {

            display:
                block !important;


            font-size:
                11px !important;


            font-weight:
                400 !important;


            color:
                #777e76 !important;
        }


        .admin-dropdown-profile strong {

            display:
                block !important;


            max-width:
                100% !important;


            overflow:
                hidden !important;


            white-space:
                nowrap !important;


            text-overflow:
                ellipsis !important;


            font-size:
                14px !important;


            font-weight:
                700 !important;


            color:
                #151915 !important;
        }


        /* =================================================
           DIVIDER
           ================================================= */

        .admin-dropdown-divider {

            width:
                100% !important;

            height:
                1px !important;


            margin:
                5px 0 !important;


            background:
                #e5eae4 !important;
        }


        /* =================================================
           MENU ITEM
           ================================================= */

        .admin-dropdown-item {

            width:
                100% !important;


            min-height:
                42px !important;


            padding:
                9px 10px !important;


            border-radius:
                8px !important;


            display:
                flex !important;


            align-items:
                center !important;


            gap:
                10px !important;


            color:
                #1d241e !important;


            background:
                transparent !important;


            border:
                none !important;


            font-family:
                'Public Sans',
                sans-serif !important;


            font-size:
                14px !important;


            font-weight:
                500 !important;


            line-height:
                1.2 !important;


            text-decoration:
                none !important;


            box-sizing:
                border-box !important;


            transition:
                background .15s ease
                !important;
        }


        .admin-dropdown-item:hover {

            background:
                #f2f6f1 !important;

            text-decoration:
                none !important;
        }


        .admin-dropdown-item svg {

            width:
                19px !important;

            height:
                19px !important;

            flex-shrink:
                0 !important;

            stroke:
                #17651f !important;
        }


        /* =================================================
           LOGOUT
           ================================================= */

        .admin-dropdown-logout {

            color:
                #c62828 !important;
        }


        .admin-dropdown-logout svg {

            stroke:
                #c62828 !important;
        }


        .admin-dropdown-logout:hover {

            background:
                #fff1f1 !important;
        }



        /* =================================================
           MOBILE
           ================================================= */

        @media
        (max-width: 600px) {


            .admin-header__container {

                min-height:
                    58px !important;


                padding:
                    0 15px !important;


                gap:
                    10px !important;
            }


            /*
             * ADMIN PANEL WAJIB SATU BARIS
             */

            .admin-header__brand {

                font-size:
                    17px !important;


                gap:
                    7px !important;


                white-space:
                    nowrap !important;
            }


            .admin-header__brand svg {

                width:
                    21px !important;

                height:
                    21px !important;
            }


            /* HP: hanya tampil icon user */
.admin-user-name {
    display: none !important;
}

/* Hilangkan SEMUA chevron/panah pada HP */
.admin-user-chevron,
.admin-user-button .admin-user-chevron,
.admin-user-button svg:last-child {
    display: none !important;
}

.admin-user-button {
    width: 42px !important;
    height: 42px !important;

    min-width: 42px !important;
    min-height: 42px !important;

    padding: 0 !important;

    display: flex !important;
    align-items: center !important;
    justify-content: center !important;

    border: 1px solid #d6ddd4 !important;
    border-radius: 50% !important;

    background: #ffffff !important;
}

/* Pastikan icon user tetap tampil */
.admin-user-button svg:first-child {
    display: block !important;

    width: 21px !important;
    height: 21px !important;

    margin: 0 !important;
}


            .admin-user-dropdown {

                width:
                    215px !important;


                top:
                    calc(100% + 9px)
                    !important;


                right:
                    0 !important;
            }


            /* dashboard */

            .admin-container {

                padding:
                    24px 16px !important;
            }


            .dashboard-title {

                font-size:
                    28px !important;
            }

        }



        /* =================================================
           HP SANGAT KECIL
           ================================================= */

        @media
        (max-width: 350px) {


            .admin-header__container {

                padding:
                    0 11px !important;
            }


            .admin-header__brand {

                font-size:
                    16px !important;
            }


            .admin-header__brand svg {

                width:
                    19px !important;

                height:
                    19px !important;
            }


            .admin-user-button {

                width:
                    38px !important;

                height:
                    38px !important;

                min-width:
                    38px !important;

                min-height:
                    38px !important;
            }

        }
.dashboard-card__title svg {
    flex-shrink: 0;
}

.dashboard-card__title svg path {
    vector-effect: non-scaling-stroke;
}

    </style>

</head>


<body class="admin-body">


<!-- =========================================================
     HEADER
     ========================================================= -->

<header class="admin-header">

    <div class="admin-header__container">


        <!-- BRAND -->

        <a
            href="dashboard.php"
            class="admin-header__brand"
        >

            <svg
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
                stroke-linecap="round"
                stroke-linejoin="round"
            >

                <path d="M3 21h18"></path>

                <path
                    d="M5 21V7l7-4 7 4v14"
                ></path>

                <path
                    d="M9 21v-6h6v6"
                ></path>

            </svg>


            <span>
                Admin Panel
            </span>

        </a>



        <!-- =================================================
             USER MENU
             ================================================= -->

        <div class="admin-user-menu">


            <button
                type="button"
                class="admin-user-button"
                id="adminUserButton"

                aria-expanded="false"

                aria-controls="
                    adminUserDropdown
                "

                aria-label="
                    Menu pengguna
                "
            >


                <!-- ICON USER -->

                <svg
                    viewBox="0 0 24 24"
                    width="22"
                    height="22"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                >

                    <path
                        d="
                            M20 21
                            a8 8 0 0 0-16 0
                        "
                    ></path>

                    <circle
                        cx="12"
                        cy="7"
                        r="4"
                    ></circle>

                </svg>



                <!--
                    Desktop saja.
                    Di HP otomatis hilang.
                -->

                <span class="admin-user-name">

                    Halo,
                    <?= htmlspecialchars(
                        $nama_admin
                    ) ?>

                </span>



                <!-- CHEVRON -->

                <svg
                    class="admin-user-chevron"
                    viewBox="0 0 24 24"
                    width="16"
                    height="16"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                >

                    <polyline
                        points="
                            6 9
                            12 15
                            18 9
                        "
                    ></polyline>

                </svg>


            </button>



            <!-- =================================================
                 DROPDOWN
                 ================================================= -->

            <div
                class="admin-user-dropdown"
                id="adminUserDropdown"
            >


                <!-- PROFILE -->

                <div
                    class="
                        admin-dropdown-profile
                    "
                >

                    <span
                        class="
                            admin-dropdown-label
                        "
                    >

                        Login sebagai

                    </span>


                    <strong>

                        <?= htmlspecialchars(
                            $nama_admin
                        ) ?>

                    </strong>

                </div>



                <div
                    class="
                        admin-dropdown-divider
                    "
                ></div>



                <!-- KELOLA AKUN -->

                <a
                    href="kelola_akun.php"
                    class="
                        admin-dropdown-item
                    "
                >

                    <svg
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                    >

                        <path
                            d="
                                M20 21
                                a8 8 0 0 0-16 0
                            "
                        ></path>

                        <circle
                            cx="12"
                            cy="7"
                            r="4"
                        ></circle>

                    </svg>


                    <span>
                        Kelola Akun
                    </span>

                </a>



                <!-- LOGOUT -->

                <a
                    href="logout.php"
                    class="
                        admin-dropdown-item
                        admin-dropdown-logout
                    "
                >

                    <svg
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                    >

                        <path
                            d="
                                M9 21H5
                                a2 2 0 0 1-2-2
                                V5
                                a2 2 0 0 1 2-2
                                h4
                            "
                        ></path>

                        <polyline
                            points="
                                16 17
                                21 12
                                16 7
                            "
                        ></polyline>

                        <line
                            x1="21"
                            y1="12"
                            x2="9"
                            y2="12"
                        ></line>

                    </svg>


                    <span>
                        Keluar
                    </span>

                </a>


            </div>

        </div>

    </div>

</header>



<!-- =========================================================
     MAIN
     ========================================================= -->

<main class="admin-container">


    <h1 class="dashboard-title">

        Overview Modul

    </h1>



    <div class="dashboard-grid">

        <!-- =====================================================
             HERO CAROUSEL
             ===================================================== -->

        <section class="dashboard-card">
            <header class="dashboard-card__header">
                <h2 class="dashboard-card__title">
                    <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <rect x="3" y="4" width="18" height="16" rx="2"/>
                        <circle cx="8.5" cy="9" r="1.5"/>
                        <path d="m4 17 5-5 4 4 3-3 4 4"/>
                    </svg>
                    Hero Carousel
                </h2>
                <a href="kelola_carousel.php" class="dashboard-card__action">Kelola</a>
            </header>
            <div class="dashboard-card__body">
                <div style="display:flex;align-items:center;gap:14px;">
                    <div style="width:48px;height:48px;display:grid;place-items:center;border-radius:14px;background:var(--color-surface-low);color:var(--color-primary-container);font-size:20px;font-weight:700;">
                        <?= $count_carousel ?>
                    </div>
                    <div>
                        <strong>Slide tersimpan</strong>
                        <p style="margin:4px 0 0;color:var(--color-on-surface-variant);font-size:13px;">Atur gambar, judul, deskripsi, status, dan urutan carousel halaman utama.</p>
                    </div>
                </div>
            </div>
        </section>


        <!-- =====================================================
             JADWAL CAMAT
             ===================================================== -->

        <section class="dashboard-card">


            <header
                class="
                    dashboard-card__header
                "
            >


                <h2
                    class="
                        dashboard-card__title
                    "
                >

                    <svg
                        viewBox="0 0 24 24"
                        width="20"
                        height="20"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                        stroke-linecap="round"
                        stroke-linejoin="round"
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

                    Jadwal Camat

                </h2>


                <a
                    href="kelola_jadwal.php"
                    class="
                        dashboard-card__action
                    "
                >

                    Kelola

                </a>


            </header>



            <div
                class="
                    dashboard-card__body
                "
            >


                <?php
                if ($count_jadwal > 0):
                ?>


                    <ul
                        class="
                            admin-list
                        "
                    >


                        <?php
                        foreach (
                            $dataCamat
                            as
                            $row
                        ):
                        ?>


                            <li>


                                <div
                                    class="
                                        admin-list__info
                                    "
                                >


                                    <strong>

                                        <?= htmlspecialchars(
                                            mb_convert_case(
                                                $row[
                                                    'jenis_kegiatan'
                                                ],
                                                MB_CASE_TITLE,
                                                'UTF-8'
                                            )
                                        ) ?>

                                    </strong>


                                    <span>

                                        <?= htmlspecialchars(
                                            substr(
                                                $row[
                                                    'jam_mulai'
                                                ],
                                                0,
                                                5
                                            )
                                        ) ?>

                                        -

                                        <?= htmlspecialchars(
                                            substr(
                                                $row[
                                                    'jam_selesai'
                                                ],
                                                0,
                                                5
                                            )
                                        ) ?>

                                        WIB

                                    </span>


                                    <span>

                                        <?= htmlspecialchars(
                                            $row[
                                                'tanggal'
                                            ]
                                        ) ?>

                                    </span>


                                </div>


                            </li>


                        <?php
                        endforeach;
                        ?>


                    </ul>


                <?php
                else:
                ?>


                    <div
                        class="
                            empty-state
                        "
                    >

                        <svg
                            viewBox="0 0 24 24"
                            width="48"
                            height="48"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.5"
                        >

                            <path
                                d="
                                    M14 2H6
                                    a2 2 0 0 0-2 2
                                    v16
                                    a2 2 0 0 0 2 2
                                    h12
                                    a2 2 0 0 0 2-2
                                    V8z
                                "
                            ></path>

                            <polyline
                                points="
                                    14 2
                                    14 8
                                    20 8
                                "
                            ></polyline>

                        </svg>


                        <p>
                            Belum ada jadwal hari ini.
                        </p>


                    </div>


                <?php
                endif;
                ?>


            </div>


        </section>



        <!-- =====================================================
             STATUS LAYANAN
             ===================================================== -->

        <section class="dashboard-card">


            <header
                class="
                    dashboard-card__header
                "
            >


                <h2
                    class="
                        dashboard-card__title
                    "
                >


                    <svg
                        viewBox="0 0 24 24"
                        width="20"
                        height="20"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                    >

                        <path
                            d="
                                M22 11.08V12
                                a10 10 0 1 1
                                -5.93-9.14
                            "
                        ></path>

                        <polyline
                            points="
                                22 4
                                12 14.01
                                9 11.01
                            "
                        ></polyline>

                    </svg>


                    Status Layanan


                </h2>


                <a
                    href="kelola_status.php"
                    class="
                        dashboard-card__action
                    "
                >

                    Perbarui

                </a>


            </header>



            <div
                class="
                    dashboard-card__body
                "
            >


                <?php
                if ($count_status > 0):
                ?>


                    <ul
                        class="
                            admin-list
                        "
                    >


                        <?php
                        foreach (
                            $data_status
                            as
                            $row
                        ):
                        ?>


                            <li>


                                <div
                                    class="
                                        admin-list__info
                                    "
                                >


                                    <strong>

                                        <?= htmlspecialchars(
                                            mb_convert_case(
                                                $row[
                                                    'nama_layanan'
                                                ],
                                                MB_CASE_TITLE,
                                                'UTF-8'
                                            )
                                        ) ?>

                                    </strong>


                                    <span>

                                        Terakhir diupdate:

                                        <?= htmlspecialchars(
                                            $row[
                                                'waktu_kejadian'
                                            ]
                                        ) ?>

                                    </span>


                                </div>



                                <?php
                                if (
                                    mb_strtolower(
                                        $row['status'],
                                        'UTF-8'
                                    )
                                    ===
                                    'ready'
                                ):
                                ?>


                                    <span
                                        style="
                                            color:
                                            var(--status-ready-text);

                                            background:
                                            var(--status-ready-bg);

                                            padding:
                                            3px 9px;

                                            border-radius:
                                            5px;

                                            font-size:
                                            12px;

                                            font-weight:
                                            bold;
                                        "
                                    >

                                        Ready

                                    </span>


                                <?php
                                else:
                                ?>


                                    <span
                                        style="
                                            color:
                                            var(--status-gangguan-text);

                                            background:
                                            var(--status-gangguan-bg);

                                            padding:
                                            3px 9px;

                                            border-radius:
                                            5px;

                                            font-size:
                                            12px;

                                            font-weight:
                                            bold;
                                        "
                                    >

                                        Gangguan

                                    </span>


                                <?php
                                endif;
                                ?>


                            </li>


                        <?php
                        endforeach;
                        ?>


                    </ul>


                <?php
                else:
                ?>


                    <div
                        class="
                            empty-state
                        "
                    >

                        <p>
                            Belum ada data status layanan.
                        </p>

                    </div>


                <?php
                endif;
                ?>


            </div>


        </section>



        <!-- =====================================================
             KONTAK WHATSAPP
             ===================================================== -->

        <section class="dashboard-card">


            <header
                class="
                    dashboard-card__header
                "
            >


                <h2
                    class="
                        dashboard-card__title
                    "
                >

                    <svg
    viewBox="0 0 24 24"
    width="22"
    height="22"
    fill="none"
    stroke="currentColor"
    stroke-width="1.8"
    stroke-linecap="round"
    stroke-linejoin="round"
    aria-hidden="true"
>
    <!-- Bubble WhatsApp -->
    <path
        d="M20.5 11.5
           A8.5 8.5 0 0 1 8.1 19
           L3.5 20.5
           L5 16.1
           A8.5 8.5 0 1 1 20.5 11.5Z"
    ></path>

    <!-- Telepon -->
    <path
        d="M8.3 7.8
           C8.5 7.4 8.8 7.3 9.1 7.3
           H9.6
           C9.9 7.3 10.1 7.5 10.2 7.8
           L10.9 9.5
           C11 9.8 11 10 10.8 10.2
           L10.2 10.9
           C10.9 12.2 12 13.2 13.4 13.8
           L14 13.1
           C14.2 12.9 14.5 12.8 14.8 13
           L16.4 13.8
           C16.7 13.9 16.8 14.2 16.8 14.5
           V15
           C16.8 15.4 16.6 15.8 16.3 16
           C15.7 16.4 15 16.6 14.3 16.5
           C12.6 16.2 11 15.4 9.7 14.2
           C8.4 12.9 7.6 11.3 7.4 9.7
           C7.3 9 7.6 8.3 8.3 7.8Z"
    ></path>
</svg>


                    Kontak WhatsApp


                </h2>


                <a
                    href="kelola_kontak.php"
                    class="
                        dashboard-card__action
                    "
                >

                    Kelola

                </a>


            </header>



            <div
                class="
                    dashboard-card__body
                "
            >


                <?php
                if (!empty($data_kontak)):
                ?>


                    <ul
                        class="
                            admin-list
                        "
                    >


                        <?php
                        foreach (
                            $data_kontak
                            as
                            $row
                        ):
                        ?>


                            <li>


                                <div
                                    class="
                                        admin-list__info
                                    "
                                >


                                    <strong>

                                        <?= htmlspecialchars(
                                            mb_convert_case(
                                                $row['nama'],
                                                MB_CASE_TITLE,
                                                'UTF-8'
                                            )
                                        ) ?>

                                    </strong>


                                    <span>

                                        <?= htmlspecialchars(
                                            $row[
                                                'no_layanan'
                                            ]
                                        ) ?>

                                    </span>


                                </div>


                            </li>


                        <?php
                        endforeach;
                        ?>


                    </ul>


                <?php
                else:
                ?>


                    <div
                        class="
                            empty-state
                        "
                    >

                        <p>
                            Tidak ada kontak layanan.
                        </p>

                    </div>


                <?php
                endif;
                ?>


            </div>


        </section>



        <!-- =====================================================
             LINK SURVEI
             ===================================================== -->

        <section class="dashboard-card">


            <header
                class="
                    dashboard-card__header
                "
            >


                <h2
                    class="
                        dashboard-card__title
                    "
                >

                    <svg
                        viewBox="0 0 24 24"
                        width="20"
                        height="20"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                    >

                        <path
                            d="
                                M10 13
                                a5 5 0 0 0
                                7.54.54
                                l3-3
                                a5 5 0 0 0
                                -7.07-7.07
                                l-1.72 1.71
                            "
                        ></path>

                        <path
                            d="
                                M14 11
                                a5 5 0 0 0
                                -7.54-.54
                                l-3 3
                                a5 5 0 0 0
                                7.07 7.07
                                l1.71-1.71
                            "
                        ></path>

                    </svg>


                    Link Survei


                </h2>


                <a
                    href="kelola_survei.php"
                    class="
                        dashboard-card__action
                    "
                >

                    Edit Link

                </a>


            </header>



            <div
                class="
                    dashboard-card__body
                "
            >


                <div
                    class="
                        empty-state
                    "
                    style="
                        padding:
                        1rem 0;
                    "
                >


                    <?php
                    if (
                        !empty($data_link)
                        &&
                        !empty(
                            $data_link['url']
                        )
                    ):
                    ?>


                        <p
                            style="
                                color:
                                var(--color-on-surface);

                                word-break:
                                break-all;
                            "
                        >

                            <?= htmlspecialchars(
                                $data_link['url']
                            ) ?>

                        </p>


                    <?php
                    else:
                    ?>


                        <p>

                            Link survei belum tersedia.

                        </p>


                    <?php
                    endif;
                    ?>


                </div>


            </div>


        </section>


    </div>


</main>



<!-- =========================================================
     FOOTER
     ========================================================= -->

<footer class="page-footer">

    <p>

        &copy;
        <?= date('Y') ?>

        Kecamatan Maron —
        Kabupaten Probolinggo

    </p>

</footer>



<!-- =========================================================
     DROPDOWN JAVASCRIPT
     ========================================================= -->

<script>

document.addEventListener(
    'DOMContentLoaded',
    function () {


        const userButton =
            document.getElementById(
                'adminUserButton'
            );


        const userDropdown =
            document.getElementById(
                'adminUserDropdown'
            );


        if (
            !userButton ||
            !userDropdown
        ) {

            return;

        }


        /*
         * KLIK USER BUTTON
         */

        userButton.addEventListener(
            'click',
            function (event) {


                event.preventDefault();

                event.stopPropagation();


                const opened =
                    userDropdown
                    .classList
                    .toggle('show');


                userButton
                    .classList
                    .toggle(
                        'active',
                        opened
                    );


                userButton.setAttribute(
                    'aria-expanded',
                    opened
                        ? 'true'
                        : 'false'
                );

            }
        );


        /*
         * KLIK DI DROPDOWN
         * jangan langsung ditutup.
         */

        userDropdown.addEventListener(
            'click',
            function (event) {

                event.stopPropagation();

            }
        );


        /*
         * KLIK DI LUAR
         */

        document.addEventListener(
            'click',
            function () {


                userDropdown
                    .classList
                    .remove('show');


                userButton
                    .classList
                    .remove('active');


                userButton.setAttribute(
                    'aria-expanded',
                    'false'
                );

            }
        );


        /*
         * ESC
         */

        document.addEventListener(
            'keydown',
            function (event) {


                if (
                    event.key ===
                    'Escape'
                ) {


                    userDropdown
                        .classList
                        .remove('show');


                    userButton
                        .classList
                        .remove('active');


                    userButton
                        .setAttribute(
                            'aria-expanded',
                            'false'
                        );


                }

            }
        );


    }
);

</script>


</body>
</html>
