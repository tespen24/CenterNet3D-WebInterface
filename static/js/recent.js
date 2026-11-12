/*
 * recent.js - halaman "Recent".
 * Data dummy; belum terhubung ke database atau filesystem server.
 */
(() => {
  // TODO BACKEND:
  // GET /api/recordings
  // Respons: [{ "id", "filename", "recorded_at", "duration", "resolution", "size", "path" }, ...]
  let recordings = [
    { id: 1, filename: "rec_20261106_1600.mp4", date: "06 Nov 2026, 16:00", duration: "03:00", resolution: "1280 × 720", size: "109 MB", path: "/data/videos/jalan-tol/" },
    { id: 2, filename: "rec_20261105_1015.mp4", date: "05 Nov 2026, 10:15", duration: "10:00", resolution: "1280 × 720", size: "361 MB", path: "/data/videos/" },
    { id: 3, filename: "rec_20261105_0830.mp4", date: "05 Nov 2026, 08:30", duration: "05:00", resolution: "1280 × 720", size: "182 MB", path: "/data/videos/" },
    { id: 4, filename: "rec_20261103_1700.mp4", date: "03 Nov 2026, 17:00", duration: "15:00", resolution: "1280 × 720", size: "540 MB", path: "/data/videos/simpang-tiga/" },
    { id: 5, filename: "rec_20261102_0745.mp4", date: "02 Nov 2026, 07:45", duration: "05:00", resolution: "1280 × 720", size: "178 MB", path: "/data/videos/" },
    { id: 6, filename: "rec_20261030_1200.mp4", date: "30 Okt 2026, 12:00", duration: "08:00", resolution: "1280 × 720", size: "291 MB", path: "/mnt/storage/recordings/" },
  ];

  const $ = (id) => document.getElementById(id);
  const body = $("recBody"), search = $("recSearch"), empty = $("recEmpty"), count = $("recCount");
  let pendingDeleteId = null;
  const esc = App.escapeHtml;

  function render() {
    const q = search.value.trim().toLowerCase();
    const rows = recordings.filter((r) => !q || (r.filename + r.path).toLowerCase().includes(q));
    count.textContent = rows.length;
    empty.hidden = rows.length > 0;
    body.innerHTML = rows.map((r) => `
      <tr data-id="${r.id}">
        <td class="file"><i data-lucide="file-video"></i>${esc(r.filename)}</td>
        <td>${esc(r.date)}</td><td>${esc(r.duration)}</td><td>${esc(r.resolution)}</td><td>${esc(r.size)}</td>
        <td>${esc(r.path)}</td>
        <td class="actions">
          <button class="btn btn-outline btn-sm" data-act="preview" type="button"><i data-lucide="eye"></i>Preview</button>
          <button class="btn btn-primary btn-sm" data-act="download" type="button"><i data-lucide="download"></i>Download</button>
          <button class="btn btn-danger btn-sm" data-act="delete" type="button"><i data-lucide="trash-2"></i>Delete</button>
        </td>
      </tr>`).join("");
    App.icons();
  }

  body.addEventListener("click", (e) => {
    const btn = e.target.closest("button[data-act]");
    if (!btn) return;
    const id = Number(btn.closest("tr").dataset.id);
    const rec = recordings.find((r) => r.id === id);

    if (btn.dataset.act === "preview") {
      // TODO BACKEND:
      // Ambil URL streaming dari GET /api/recordings/<id>/stream
      // lalu pasang ke elemen <video> di modal ini.
      $("pvName").textContent = rec.filename;
      App.openModal("previewModal");
    } else if (btn.dataset.act === "download") {
      // TODO BACKEND:
      // Arahkan ke GET /api/recordings/<id>/download
      // (response berupa file dengan header Content-Disposition: attachment).
      App.toast("info", `Simulasi: mengunduh ${rec.filename}`);
    } else if (btn.dataset.act === "delete") {
      pendingDeleteId = id;
      $("delName").textContent = rec.filename;
      App.openModal("deleteModal");
    }
  });

  $("btnConfirmDelete").addEventListener("click", () => {
    const rec = recordings.find((r) => r.id === pendingDeleteId);
    // TODO BACKEND:
    // DELETE /api/recordings/<id>
    // Hapus baris dari tampilan hanya setelah server membalas sukses.
    recordings = recordings.filter((r) => r.id !== pendingDeleteId);
    App.closeModal("deleteModal");
    render();
    if (rec) App.toast("error", `${rec.filename} dihapus (simulasi).`);
    pendingDeleteId = null;
  });

  search.addEventListener("input", render);
  render();
})();
