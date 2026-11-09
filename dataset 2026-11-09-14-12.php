<?php
/*
|--------------------------------------------------------------------------
| CenterNet3D Dataset Explorer
|--------------------------------------------------------------------------
| Struktur dataset:
|   carla_dataset/<sesi>/images/image_0001.png
|   carla_dataset/<sesi>/labels/label_0001.txt
|
| Alur halaman:
|   1. dataset.php                 -> daftar folder sesi
|   2. dataset.php?session=<sesi>  -> daftar gambar pada sesi
|   3. Tombol "View Label"         -> tabel label (modal)
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

?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dataset Explorer | CenterNet3D</title>

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, Helvetica, sans-serif;
            background: #f5f7fa;
            color: #1f2937;
            min-height: 100vh;
        }

        .layout {
            display: flex;
            min-height: 100vh;
        }

        /* NAVBAR */

        .navbar {
            width: 100%;
            height: 65px;
            background: #ffffff;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 35px;
        }

        .brand h2 {
            font-size: 19px;
            color: #111827;
        }

        .brand span {
            color: #2563eb;
        }

        .menu {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .menu a {
            display: block;
            text-decoration: none;
            color: #4b5563;
            padding: 10px 16px;
            border-radius: 6px;
            font-size: 13px;
        }

        .menu a:hover {
            background: #f3f4f6;
        }

        .menu a.active {
            background: #eff6ff;
            color: #2563eb;
            font-weight: bold;
        }

        /* MAIN */

        .main {
            flex: 1;
            padding: 32px;
            min-width: 0;
        }

        .page-title {
            margin-bottom: 24px;
        }

        .page-title h1 {
            font-size: 24px;
            color: #111827;
        }

        .page-title p {
            font-size: 13px;
            color: #6b7280;
            margin-top: 6px;
        }

        .breadcrumb {
            font-size: 13px;
            color: #6b7280;
            margin-bottom: 14px;
        }

        .breadcrumb a {
            color: #2563eb;
            text-decoration: none;
        }

        .breadcrumb a:hover {
            text-decoration: underline;
        }

        /* TOOLBAR */

        .toolbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            margin-bottom: 18px;
        }

        .toolbar h2 {
            font-size: 17px;
            color: #111827;
        }

        .search-box {
            border: 1px solid #d1d5db;
            background: #ffffff;
            padding: 9px 12px;
            border-radius: 6px;
            width: 240px;
            font-size: 13px;
            outline: none;
        }

        .search-box:focus {
            border-color: #2563eb;
        }

        /* GRID */

        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(230px, 1fr));
            gap: 16px;
        }

        .card {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            overflow: hidden;
        }

        /* SESSION CARD */

        a.session-card {
            display: block;
            padding: 20px;
            text-decoration: none;
            color: inherit;
        }

        a.session-card:hover {
            border-color: #2563eb;
            box-shadow: 0 2px 8px rgba(37, 99, 235, 0.12);
        }

        .session-icon {
            font-size: 28px;
            margin-bottom: 10px;
        }

        .session-card h3 {
            font-size: 15px;
            color: #111827;
            margin-bottom: 8px;
        }

        .session-card p {
            font-size: 12px;
            color: #6b7280;
            margin-bottom: 4px;
        }

        /* IMAGE CARD */

        .image-card img {
            width: 100%;
            height: 160px;
            object-fit: cover;
            background: #e5e7eb;
            display: block;
        }

        .image-info {
            padding: 12px;
        }

        .image-info h3 {
            font-size: 13px;
            color: #111827;
            margin-bottom: 8px;
            word-break: break-all;
        }

        .tag {
            display: inline-block;
            font-size: 10px;
            padding: 3px 8px;
            border-radius: 4px;
        }

        .tag-ok {
            background: #dcfce7;
            color: #166534;
        }

        .tag-missing {
            background: #fef3c7;
            color: #92400e;
        }

        .btn {
            display: block;
            width: 100%;
            margin-top: 10px;
            padding: 8px;
            border: 1px solid #2563eb;
            background: #2563eb;
            color: #ffffff;
            border-radius: 6px;
            font-size: 12px;
            cursor: pointer;
        }

        .btn:hover {
            background: #1d4ed8;
        }

        .btn:disabled {
            background: #f3f4f6;
            border-color: #e5e7eb;
            color: #9ca3af;
            cursor: not-allowed;
        }

        /* EMPTY */

        .empty {
            grid-column: 1 / -1;
            background: #ffffff;
            border: 1px dashed #d1d5db;
            border-radius: 8px;
            padding: 45px 20px;
            text-align: center;
            color: #6b7280;
            font-size: 14px;
        }

        .empty code {
            display: block;
            margin-top: 10px;
            color: #2563eb;
            font-size: 12px;
        }

        /* MODAL */

        .modal {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(17, 24, 39, 0.5);
            z-index: 100;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }

        .modal-content {
            background: #ffffff;
            width: 100%;
            max-width: 780px;
            max-height: 90vh;
            overflow-y: auto;
            border-radius: 10px;
            padding: 22px;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 14px;
        }

        .modal-header h2 {
            font-size: 16px;
            color: #111827;
        }

        .close-button {
            background: none;
            border: none;
            font-size: 24px;
            color: #6b7280;
            cursor: pointer;
        }

        .modal-meta {
            font-size: 12px;
            color: #6b7280;
            margin-bottom: 12px;
        }

        .table-wrapper {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
        }

        th,
        td {
            padding: 9px 12px;
            border-bottom: 1px solid #e5e7eb;
            text-align: left;
        }

        th {
            background: #f9fafb;
            color: #374151;
        }

        .notice {
            background: #fef3c7;
            color: #92400e;
            padding: 12px;
            border-radius: 6px;
            font-size: 13px;
        }

        /* RESPONSIVE */

        @media (max-width: 800px) {
            .layout {
                display: block;
                min-height: 100vh;
            }

            .sidebar {
                width: 100%;
                padding: 16px;
            }

            .brand {
                margin-bottom: 12px;
            }

            .menu {
                display: flex;
                flex-wrap: wrap;
                gap: 5px;
            }

            .menu a {
                margin: 0;
            }

            .main {
                padding: 32px;
                min-width: 0;
            }

            .toolbar {
                flex-direction: column;
                align-items: stretch;
            }

            .search-box {
                width: 100%;
            }
        }
    </style>
</head>

<body>

<div class="layout">


    <!-- NAVBAR -->
    <header class="navbar">

        <div class="brand">
            <h2>CenterNet<span>3D</span></h2>
        </div>

        <nav class="menu">
            <a href="ambil_data.php">Ambil Data</a>
            <a href="dataset.php" class="active">Dataset</a>
        </nav>

    </header>

    <!-- MAIN -->
    <main class="main">

        <div class="page-title">
            <h1>Dataset Explorer</h1>
            <p>Jelajahi dataset CARLA yang digunakan pada proyek CenterNet3D.</p>
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


<script>
const currentSession = <?= json_encode($session) ?>;

const modal = document.getElementById('labelModal');
const content = document.getElementById('labelContent');

// ---------- Pencarian ----------
document.getElementById('searchInput')?.addEventListener('input', function () {
    const keyword = this.value.toLowerCase();

    document.querySelectorAll('.image-card').forEach(function (card) {
        card.style.display = card.dataset.filename.includes(keyword) ? '' : 'none';
    });
});

// ---------- Tombol View Label ----------
document.querySelectorAll('.btn[data-file]').forEach(function (button) {
    button.addEventListener('click', function () {
        showLabel(button.dataset.file);
    });
});

function showNotice(text) {
    content.replaceChildren();

    const div = document.createElement('div');
    div.className = 'notice';
    div.textContent = text;

    content.appendChild(div);
}

async function showLabel(filename) {
    document.getElementById('modalTitle').textContent = filename;
    document.getElementById('modalMeta').textContent = '';
    showNotice('Memuat label...');
    modal.style.display = 'flex';

    try {
        const url = '?action=label&session=' + encodeURIComponent(currentSession)
                  + '&file=' + encodeURIComponent(filename);

        const response = await fetch(url);

        if (!response.ok) {
            throw new Error('HTTP ' + response.status);
        }

        const data = await response.json();

        document.getElementById('modalMeta').textContent =
            (data.label ? 'File: ' + data.label + ' · ' : '') +
            data.rows.length + ' objek';

        if (data.rows.length === 0) {
            showNotice('File label kosong atau tidak ditemukan.');
            return;
        }

        renderTable(data.header, data.rows);

    } catch (error) {
        showNotice('Gagal memuat label: ' + error.message);
    }
}

function renderTable(header, rows) {
    const columnCount = Math.max(header.length, ...rows.map(r => r.length));

    const wrapper = document.createElement('div');
    wrapper.className = 'table-wrapper';

    const table = document.createElement('table');

    // Header: pakai header dari file, atau Kolom 1, Kolom 2, ...
    const headRow = document.createElement('tr');
    const titles = ['#'];

    for (let i = 0; i < columnCount; i++) {
        titles.push(header[i] ?? 'Kolom ' + (i + 1));
    }

    titles.forEach(function (title) {
        const th = document.createElement('th');
        th.textContent = title;
        headRow.appendChild(th);
    });

    const thead = document.createElement('thead');
    thead.appendChild(headRow);
    table.appendChild(thead);

    const tbody = document.createElement('tbody');

    rows.forEach(function (row, index) {
        const tr = document.createElement('tr');

        [index + 1, ...row].forEach(function (value) {
            const td = document.createElement('td');
            td.textContent = value;
            tr.appendChild(td);
        });

        tbody.appendChild(tr);
    });

    table.appendChild(tbody);
    wrapper.appendChild(table);

    content.replaceChildren(wrapper);
}

// ---------- Tutup modal ----------
function closeModal() {
    modal.style.display = 'none';
}

document.getElementById('closeModal').addEventListener('click', closeModal);

modal.addEventListener('click', function (event) {
    if (event.target === modal) {
        closeModal();
    }
});

document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') {
        closeModal();
    }
});
</script>

</body>
</html>
