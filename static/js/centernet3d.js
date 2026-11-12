/*
 * centernet3d.js - halaman "Jalankan CenterNet3D".
 * Simulasi frontend saja: tanpa PyTorch, tanpa model, tanpa inferensi.
 */
(() => {
  // TODO BACKEND:
  // GET /api/videos
  // Respons: [{ "id", "filename", "path", "duration", "resolution", "size" }, ...]
  const VIDEOS = [
    { id: 1, filename: "rec_20261105_0830.mp4", path: "/data/videos/", duration: "05:00", resolution: "1280 × 720", size: "182 MB" },
    { id: 2, filename: "rec_20261105_1015.mp4", path: "/data/videos/", duration: "10:00", resolution: "1280 × 720", size: "361 MB" },
    { id: 3, filename: "rec_20261106_1600.mp4", path: "/data/videos/jalan-tol/", duration: "03:00", resolution: "1280 × 720", size: "109 MB" },
  ];

  // Hasil akhir dummy. TODO BACKEND: ganti dengan hasil dari GET /api/centernet3d/status
  const FINAL_COUNTS = { bus: 4, truck: 9, sedan: 47, pickup: 21 };
  const TOTAL_FRAMES = 9000;

  const $ = (id) => document.getElementById(id);
  const el = {
    select: $("videoSelect"), videoErr: $("videoError"), info: $("videoInfo"),
    range: $("confRange"), num: $("confNumber"), confErr: $("confError"), simFail: $("simFail"),
    run: $("btnRun"), stop: $("btnStop"),
    badge: $("cnStatus"), corner: $("cnCorner"), bar: $("cnProgress"), msg: $("cnMsg"), pct: $("cnPct"),
    cnt: { total: $("cntTotal"), bus: $("cntBus"), truck: $("cntTruck"), sedan: $("cntSedan"), pickup: $("cntPickup") },
  };

  let state = "idle"; // idle | processing | completed | failed
  let progress = 0, ticker = null;

  function renderVideos() {
    el.select.innerHTML = '<option value="">Pilih video...</option>' +
      VIDEOS.map((v) => `<option value="${v.id}">${App.escapeHtml(v.path + v.filename)}</option>`).join("");
  }

  function renderInfo() {
    const v = VIDEOS.find((x) => String(x.id) === el.select.value);
    if (!v) { el.info.innerHTML = '<li><span class="k"><i data-lucide="info"></i>Belum ada video dipilih</span></li>'; App.icons(); return; }
    const rows = [["Nama file", v.filename, "file-video"], ["Lokasi", v.path, "folder"], ["Durasi", v.duration, "clock"], ["Resolusi", v.resolution, "monitor"], ["Ukuran", v.size, "hard-drive"]];
    el.info.innerHTML = rows.map(([k, val, ic]) =>
      `<li><span class="k"><i data-lucide="${ic}"></i>${k}</span><span class="v">${App.escapeHtml(val)}</span></li>`).join("");
    App.icons();
  }

  function renderCounts(ratio) {
    const c = {};
    let sum = 0;
    for (const k in FINAL_COUNTS) { c[k] = Math.floor(FINAL_COUNTS[k] * ratio); sum += c[k]; }
    el.cnt.bus.textContent = c.bus; el.cnt.truck.textContent = c.truck;
    el.cnt.sedan.textContent = c.sedan; el.cnt.pickup.textContent = c.pickup;
    el.cnt.total.textContent = sum;
  }

  function render() {
    const map = { idle: ["idle", "Idle"], processing: ["active", "Processing"], completed: ["success", "Completed"], failed: ["danger", "Failed"] };
    App.setBadge(el.badge, ...map[state]);
    const busy = state === "processing";
    el.run.disabled = busy; el.stop.disabled = !busy;
    el.select.disabled = busy; el.range.disabled = busy; el.num.disabled = busy;
    el.bar.classList.toggle("done", state === "completed");
    el.bar.classList.toggle("fail", state === "failed");
    el.bar.firstElementChild.style.width = `${progress}%`;
    el.pct.textContent = `${Math.round(progress)}%`;
    el.corner.textContent = `Frame ${Math.round((progress / 100) * TOTAL_FRAMES)} / ${el.select.value ? TOTAL_FRAMES : 0}`;
    el.msg.textContent = { idle: "Menunggu proses dijalankan", processing: "Mendeteksi kendaraan...", completed: "Proses selesai", failed: "Proses gagal" }[state];
  }

  function validate() {
    let ok = true;
    el.videoErr.textContent = ""; el.confErr.textContent = "";
    if (!el.select.value) { el.videoErr.textContent = "Pilih video terlebih dahulu."; ok = false; }
    const c = Number(el.num.value);
    if (!Number.isFinite(c) || c < 0.05 || c > 0.95) { el.confErr.textContent = "Nilai harus antara 0,05 dan 0,95."; ok = false; }
    return ok;
  }

  function run() {
    if (!validate()) { App.log("warn", "Lengkapi konfigurasi sebelum menjalankan."); return; }
    const v = VIDEOS.find((x) => String(x.id) === el.select.value);
    const conf = Number(el.num.value);

    // TODO BACKEND:
    // POST /api/centernet3d/run
    // Payload: { "video_id": v.id, "video_path": v.path + v.filename, "confidence_threshold": conf }
    // Lalu polling GET /api/centernet3d/status untuk progress, jumlah kendaraan,
    // dan URL preview hasil deteksi (stream/MJPEG atau video hasil).

    progress = 0; state = "processing"; renderCounts(0); render();
    App.log("success", `Simulasi: memproses ${v.filename} dengan confidence ${conf.toFixed(2)}.`);

    ticker = setInterval(() => {
      progress = Math.min(100, progress + 2 + Math.random() * 4);
      renderCounts(progress / 100);
      if (el.simFail.checked && progress >= 60) return fail("Simulasi kegagalan: model tidak dapat memuat frame.");
      if (progress >= 100) return complete();
      render();
    }, 350);
  }

  function complete() {
    clearInterval(ticker); progress = 100; state = "completed";
    renderCounts(1); render();
    App.log("success", "Proses selesai. Hasil ditampilkan (data dummy).");
  }

  function fail(message) {
    clearInterval(ticker); state = "failed"; render();
    App.log("error", message);
  }

  function stop() {
    if (state !== "processing") return;
    // TODO BACKEND: POST /api/centernet3d/stop
    clearInterval(ticker); state = "idle"; progress = 0;
    renderCounts(0); render();
    App.log("error", "Proses dihentikan oleh pengguna.");
  }

  el.select.addEventListener("change", () => { el.videoErr.textContent = ""; renderInfo(); render(); });
  el.range.addEventListener("input", () => { el.num.value = Number(el.range.value).toFixed(2); });
  el.num.addEventListener("input", () => { if (el.num.value !== "") el.range.value = el.num.value; });
  el.run.addEventListener("click", run);
  el.stop.addEventListener("click", stop);

  renderVideos(); renderInfo(); renderCounts(0); render();
})();
