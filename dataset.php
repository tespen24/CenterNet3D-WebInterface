<?php
/*
|--------------------------------------------------------------------------
| CenterNet3D (dataset.php)
|--------------------------------------------------------------------------
| Struktur dataset:
| - carla_dataset/ menyimpan seluruh dataset.
| - Setiap folder sesi menggunakan format YYYY-MM-DD-HH-MM.
| - Setiap sesi memiliki folder images/ dan labels/.
| - images/ menyimpan gambar (.png).
| - labels/ menyimpan anotasi 3D Bounding Box (.txt).
| - Setiap gambar memiliki file label dengan nama yang sama.
|
| File terkait:
| - dataset.css : tampilan halaman
| - dataset.js  : pencarian, modal label, render tabel
| - sidebar.php : navigasi (navbar) bersama
|
| Gambar dan label dilayani lewat PHP (action=img / action=label),
| sehingga website tidak bergantung pada posisi folder di document root.
|--------------------------------------------------------------------------
*/

// =====================================================
// 1. KONFIGURASI
// =====================================================

$datasetRoot = '/opt/lampp/htdocs/3d_bbox_highway/carla_dataset';
$imageExtensions = ['png', 'jpg', 'jpeg', 'webp'];

$mimeTypes = [
    'png'  => 'image/png',
    'jpg'  => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'webp' => 'image/webp',
];


// =====================================================
// 2. FUNGSI BANTU
// =====================================================

function e($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

// Daftar folder sesi (yang memiliki subfolder images), terbaru di atas.
function listSessions($root)
{
    $sessions = [];

    if (!is_dir($root)) {
        return $sessions;
    }

    foreach (scandir($root) as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }
        if (is_dir("$root/$entry/images")) {
            $sessions[] = $entry;
        }
    }

    rsort($sessions);
    return $sessions;
}

// Daftar file gambar dalam satu folder, urut alami.
function listImages($dir, $extensions)
{
    $images = [];

    if (!is_dir($dir)) {
        return $images;
    }

    foreach (scandir($dir) as $file) {
        if ($file === '.' || $file === '..') {
            continue;
        }
        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        if (in_array($ext, $extensions, true)) {
            $images[] = $file;
        }
    }

    natsort($images);
    return array_values($images);
}

// image_0001.png -> 0001
function imageNumber($filename)
{
    if (preg_match('/(\d+)\.[^.]+$/', $filename, $m)) {
        return $m[1];
    }
    return null;
}

// image_0001.png -> path label_0001.txt (atau null bila tidak ada)
function labelPath($labelDir, $imageFilename)
{
    $number = imageNumber($imageFilename);

    if ($number === null) {
        return null;
    }

    $path = "$labelDir/image_$number.txt";
    return is_file($path) ? $path : null;
}

// Membaca file label (CSV atau dipisah spasi) menjadi header + baris.
function readLabel($path)
{
    $result = ['header' => [], 'rows' => []];

    if ($path === null || !is_file($path)) {
        return $result;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    foreach ($lines as $i => $line) {
        $line = trim($line);

        if ($line === '') {
            continue;
        }

        $cells = (strpos($line, ',') !== false)
            ? array_map('trim', str_getcsv($line))
            : preg_split('/\s+/', $line);

        // Baris pertama non-angka dianggap header.
        if ($i === 0 && !is_numeric($cells[0])) {
            $result['header'] = $cells;
            continue;
        }

        $result['rows'][] = $cells;
    }

    return $result;
}

function jsonResponse($data)
{
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($data);
    exit;
}

function fail($code, $message)
{
    http_response_code($code);
    echo $message;
    exit;
}


// =====================================================
// 3. VALIDASI SESI
// =====================================================

$sessions = listSessions($datasetRoot);

$session = $_GET['session'] ?? '';

// Hanya terima nama sesi yang benar-benar ada di daftar (anti path traversal).
if ($session !== '' && !in_array($session, $sessions, true)) {
    $session = '';
}

$action = $_GET['action'] ?? '';


// =====================================================
// 4. ENDPOINT: GAMBAR & LABEL
// =====================================================

if ($action === 'img' || $action === 'label') {

    if ($session === '') {
        fail(404, 'Session not found');
    }

    $imageDir = "$datasetRoot/$session/images";
    $labelDir = "$datasetRoot/$session/labels";

    $file = basename($_GET['file'] ?? '');

    // File harus ada di daftar gambar sesi tersebut.
    if (!in_array($file, listImages($imageDir, $imageExtensions), true)) {
        fail(404, 'File not found');
    }

    if ($action === 'img') {
        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        header('Content-Type: ' . $mimeTypes[$ext]);
        header('Cache-Control: public, max-age=3600');
        readfile("$imageDir/$file");
        exit;
    }

    // action === 'label'
    $path = labelPath($labelDir, $file);
    $label = readLabel($path);

    jsonResponse([
        'filename' => $file,
        'label'    => $path ? basename($path) : null,
        'header'   => $label['header'],
        'rows'     => $label['rows'],
    ]);
}


// =====================================================
// 5. DATA UNTUK TAMPILAN
// =====================================================

$datasetAvailable = is_dir($datasetRoot);

// Ringkasan tiap sesi (halaman daftar sesi)
$sessionInfo = [];

if ($session === '') {
    foreach ($sessions as $s) {
        $imgs = listImages("$datasetRoot/$s/images", $imageExtensions);
        $labelCount = 0;
        foreach ($imgs as $img) {
            if (labelPath("$datasetRoot/$s/labels", $img)) {
                $labelCount++;
            }
        }
        $sessionInfo[] = [
            'name'   => $s,
            'images' => count($imgs),
            'labels' => $labelCount,
        ];
    }
}

// Daftar gambar pada sesi terpilih (halaman gambar)
$images = [];

if ($session !== '') {
    foreach (listImages("$datasetRoot/$session/images", $imageExtensions) as $img) {
        $images[] = [
            'filename'     => $img,
            'label_exists' => labelPath("$datasetRoot/$session/labels", $img) !== null,
        ];
    }
}

// Menu aktif untuk sidebar.php
$activePage = 'dataset';

?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dataset Explorer | CenterNet3D</title>
    <link rel="stylesheet" href="dataset.css">
</head>

<body data-session="<?= e($session) ?>">

<div class="layout">

    <!-- NAVBAR -->
    <?php include __DIR__ . '/sidebar.php'; ?>

    <!-- MAIN -->
    <main class="main">

        <div class="page-title">
            <h1>Dataset Explorer</h1>
            <p>Jelajahi dataset CARLA yang digunakan pada proyek Car Detection and Counting using CenterNet3D.</p>
        </div>

        <?php if (!$datasetAvailable): ?>

            <div class="empty">
                Folder dataset tidak ditemukan.
                <code><?= e($datasetRoot) ?></code>
            </div>

        <?php elseif ($session === ''): ?>

            <!-- ============ HALAMAN 1: DAFTAR SESI ============ -->

            <div class="toolbar">
                <h2>Sesi Dataset (<?= count($sessionInfo) ?>)</h2>
            </div>

            <div class="grid">

                <?php if (empty($sessionInfo)): ?>
                    <div class="empty">
                        Belum ada folder sesi yang memiliki subfolder <b>images</b>.
                        <code><?= e($datasetRoot) ?>/&lt;sesi&gt;/images</code>
                    </div>
                <?php endif; ?>

                <?php foreach ($sessionInfo as $s): ?>
                    <a class="card session-card"
                       href="?session=<?= e(rawurlencode($s['name'])) ?>">
                        <div class="session-icon">📁</div>
                        <h3><?= e($s['name']) ?></h3>
                        <p><?= number_format($s['images']) ?> gambar</p>
                        <p><?= number_format($s['labels']) ?> file label</p>
                    </a>
                <?php endforeach; ?>

            </div>

        <?php else: ?>

            <!-- ============ HALAMAN 2: GAMBAR DALAM SESI ============ -->

            <div class="breadcrumb">
                <a href="dataset.php">Semua Sesi</a> / <?= e($session) ?>
            </div>

            <div class="toolbar">
                <h2><?= count($images) ?> gambar</h2>
                <input type="text" id="searchInput" class="search-box"
                       placeholder="Cari nama file...">
            </div>

            <div class="grid" id="imageGrid">

                <?php if (empty($images)): ?>
                    <div class="empty">Tidak ada gambar pada sesi ini.</div>
                <?php endif; ?>

                <?php foreach ($images as $item): ?>
                    <?php
                    $imageUrl = '?action=img&session=' . rawurlencode($session)
                              . '&file=' . rawurlencode($item['filename']);
                    ?>
                    <article class="card image-card"
                             data-filename="<?= e(strtolower($item['filename'])) ?>">

                        <img src="<?= e($imageUrl) ?>"
                             alt="<?= e($item['filename']) ?>"
                             loading="lazy">

                        <div class="image-info">
                            <h3><?= e($item['filename']) ?></h3>

                            <?php if ($item['label_exists']): ?>
                                <span class="tag tag-ok">Label ada</span>
                            <?php else: ?>
                                <span class="tag tag-missing">Label tidak ada</span>
                            <?php endif; ?>

                            <button type="button" class="btn"
                                    <?= $item['label_exists'] ? '' : 'disabled' ?>
                                    data-file="<?= e($item['filename']) ?>">
                                View Label
                            </button>
                        </div>
                    </article>
                <?php endforeach; ?>

            </div>

        <?php endif; ?>

    </main>
</div>


<!-- MODAL LABEL -->
<div class="modal" id="labelModal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 id="modalTitle">Label</h2>
            <button type="button" class="close-button" id="closeModal">&times;</button>
        </div>
        <div class="modal-meta" id="modalMeta"></div>
        <div id="labelContent"></div>
    </div>
</div>

<script src="dataset.js"></script>

</body>
</html>