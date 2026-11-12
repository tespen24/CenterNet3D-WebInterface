/*
 * capture.js - halaman "Ambil Video".
 * SEMUA interaksi di sini hanya simulasi frontend (tanpa request HTTP).
 */
(() => {
  // ---------- Data dummy ----------
  // TODO BACKEND:
  // Ganti data ini dengan hasil pemanggilan REST API.
  //
  // Endpoint:
  // GET /api/raspberry/status
  //
  // Contoh respons:
  // { "name": "...", "connected": true, "camera": "...",
  //   "resolution": "1280 × 720", "fps": 30 }
  const PI_INFO = {
    name: "Raspberry Pi 4 Model B",
    connected: true,
    camera: "USB Webcam (Logitech C270)",
    resolution: "1280 × 720",
    fps: 30,
  };

  // TODO BACKEND:
  // GET /api/storage/directories?path=/data
  // Respons: { "directories": ["/data/videos/", ...] }
  const DUMMY_DIRS = ["/data/videos/", "/data/videos/jalan-tol/", "/data/videos/simpang-tiga/", "/mnt/storage/recordings/"];

  const $ = (id) => document.getElementById(id);
  const el = {
    name: $("piName"), status: $("piStatus"), camera: $("piCamera"), res: $("piResolution"), fps: $("piFps"),
    duration: $("duration"), durationErr: $("durationError"),
    path: $("storagePath"), pathErr: $("pathError"),
    badge: $("captureStatus"), timer: $("captureTimer"), progress: $("captureProgress"),
    start: $("btnStart"), stop: $("btnStop"), refresh: $("btnRefreshPi"),
    browse: $("btnBrowse"), dirList: $("dirList"), pickDir: $("btnPickDir"),
  };

  let state = "idle"; // idle | recording | stopped
  let elapsed = 0, totalSec = 0, ticker = null, chosenDir = null;

  // ---------- Render ----------
  function renderPi() {
    el.name.textContent = PI_INFO.name;
    el.camera.textContent = PI_INFO.camera;
    el.res.textContent = PI_INFO.resolution;
    el.fps.textContent = `${PI_INFO.fps} FPS`;
    App.setBadge(el.status, PI_INFO.connected ? "success" : "danger", PI_INFO.connected ? "Connected" : "Disconnected");
  }

  function renderState() {
    const map = { idle: ["idle", "Idle"], recording: ["active", "Recording"], stopped: ["danger", "Stopped"] };
    App.setBadge(el.badge, ...map[state]);
    const rec = state === "recording";
    el.start.disabled = rec;
    el.stop.disabled = !rec;
    el.duration.disabled = rec;
    el.path.disabled = rec;
    el.browse.disabled = rec;
  }

  function renderTimer() {
    const total = totalSec || (Number(el.duration.value) > 0 ? Number(el.duration.value) * 60 : 0);
    el.timer.textContent = `${App.formatTime(elapsed)} / ${App.formatTime(total)}`;
    el.progress.firstElementChild.style.width = total ? `${Math.min(100, (elapsed / total) * 100)}%` : "0";
  }

  // ---------- Validasi ----------
  function validate() {
    let ok = true;
    const d = Number(el.duration.value);
    el.durationErr.textContent = "";
    el.pathErr.textContent = "";
    el.duration.classList.remove("invalid");
    el.path.classList.remove("invalid");

    if (el.duration.value.trim() === "" || !Number.isFinite(d) || d <= 0) {
      el.durationErr.textContent = "Durasi harus berupa angka positif (dalam menit).";
      el.duration.classList.add("invalid");
      ok = false;
    }
    const p = el.path.value.trim();
    if (!p.startsWith("/")) {
      el.pathErr.textContent = "Lokasi harus berupa path absolut, contoh: /data/videos/";
      el.path.classList.add("invalid");
      ok = false;
    }
    return ok;
  }

  // ---------- Aksi ----------
  function startCapture() {
    if (!PI_INFO.connected) {
      App.log("error", "Raspberry Pi tidak terhubung. Pengambilan video tidak dapat dimulai.");
      return;
    }
    if (!validate()) {
      App.log("warn", "Konfigurasi belum valid. Periksa durasi dan lokasi penyimpanan.");
      return;
    }
    const duration = Number(el.duration.value);
    const storagePath = el.path.value.trim();

    // TODO BACKEND:
    // Tambahkan request POST ke Raspberry Pi
    // untuk memulai proses pengambilan video.
    //
    // Endpoint:
    // POST /api/capture/start
    //
    // Payload:
    // {
    //     "duration": duration,        // menit
    //     "storage_path": storagePath  // direktori di server
    // }

    totalSec = duration * 60;
    elapsed = 0;
    state = "recording";
    renderState();
    renderTimer();
    App.log("success", `Simulasi: perekaman dimulai selama ${duration} menit, disimpan di ${storagePath}.`);

    // Simulasi waktu berjalan.
    // TODO BACKEND: ganti dengan polling GET /api/capture/status (atau WebSocket/SSE).
    ticker = setInterval(() => {
      elapsed += 1;
      renderTimer();
      if (elapsed >= totalSec) finish("Durasi tercapai. Perekaman (simulasi) selesai.");
    }, 1000);
  }

  function stopCapture() {
    if (state !== "recording") {
      App.log("warn", "Tidak ada perekaman yang sedang berjalan.");
      return;
    }

    // TODO BACKEND:
    // Tambahkan request POST untuk menghentikan
    // proses pengambilan video.
    //
    // Endpoint:
    // POST /api/capture/stop

    finish(`Perekaman dihentikan pada ${App.formatTime(elapsed)}.`, "error");
  }

  function finish(message, type = "info") {
    clearInterval(ticker);
    ticker = null;
    state = "stopped";
    renderState();
    renderTimer();
    App.log(type, message);
  }

  // ---------- Browse direktori (placeholder) ----------
  function openBrowse() {
    // TODO BACKEND:
    // Ambil daftar direktori server melalui
    // GET /api/storage/directories lalu render di modal ini.
    chosenDir = null;
    el.dirList.innerHTML = DUMMY_DIRS.map((d) =>
      `<li><button type="button" data-dir="${App.escapeHtml(d)}"><i data-lucide="folder"></i>${App.escapeHtml(d)}</button></li>`).join("");
    App.icons();
    App.openModal("browseModal");
  }

  el.dirList.addEventListener("click", (e) => {
    const btn = e.target.closest("button[data-dir]");
    if (!btn) return;
    el.dirList.querySelectorAll("button").forEach((b) => b.classList.remove("selected"));
    btn.classList.add("selected");
    chosenDir = btn.dataset.dir;
  });

  el.pickDir.addEventListener("click", () => {
    if (!chosenDir) { App.toast("info", "Pilih salah satu direktori terlebih dahulu."); return; }
    el.path.value = chosenDir;
    el.pathErr.textContent = "";
    el.path.classList.remove("invalid");
    App.closeModal("browseModal");
    App.log("info", `Lokasi penyimpanan diubah ke ${chosenDir}.`);
  });

  // ---------- Event ----------
  el.start.addEventListener("click", startCapture);
  el.stop.addEventListener("click", stopCapture);
  el.browse.addEventListener("click", openBrowse);
  el.duration.addEventListener("input", () => { if (state !== "recording") { totalSec = 0; elapsed = 0; renderTimer(); } });
  el.refresh.addEventListener("click", () => {
    // TODO BACKEND: panggil ulang GET /api/raspberry/status lalu perbarui PI_INFO.
    renderPi();
    App.log("info", "Informasi Raspberry Pi diperbarui (data dummy).");
  });

  renderPi();
  renderState();
  renderTimer();
})();
