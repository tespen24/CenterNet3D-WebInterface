<?php
/*
|--------------------------------------------------------------------------
| sidebar.php
|--------------------------------------------------------------------------
| Navigasi bersama untuk semua halaman CenterNet3D.
| Set $activePage sebelum include, contoh: $activePage = 'dataset';
|--------------------------------------------------------------------------
*/

$activePage = $activePage ?? '';

$menuItems = [
    'ambil_data' => ['label' => 'Ambil Data', 'href' => 'ambil_data.php'],
    'dataset'    => ['label' => 'Dataset',    'href' => 'dataset.php'],
];
?>
<header class="navbar">
    <div class="brand">
        <h2>CenterNet<span>3D</span></h2>
    </div>

    <nav class="menu">
        <?php foreach ($menuItems as $key => $item): ?>
            <a href="<?= htmlspecialchars($item['href'], ENT_QUOTES, 'UTF-8') ?>"
               class="<?= $activePage === $key ? 'active' : '' ?>">
                <?= htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') ?>
            </a>
        <?php endforeach; ?>
    </nav>
</header>