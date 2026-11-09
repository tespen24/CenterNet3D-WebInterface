/*
|--------------------------------------------------------------------------
| dataset.js
|--------------------------------------------------------------------------
| Pencarian gambar, modal label, dan render tabel label.
| Nama sesi dibaca dari atribut <body data-session="...">.
|--------------------------------------------------------------------------
*/

const currentSession = document.body.dataset.session || '';

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