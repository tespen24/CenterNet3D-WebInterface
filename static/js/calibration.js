/*
 * calibration.js - halaman "Kalibrasi Kamera".
 * Simulasi frontend saja: tanpa OpenCV dan tanpa akses kamera.
 */
(() => {
  // TODO BACKEND:
  // GET /api/calibration/camera
  // Respons: { "device": "...", "path": "/dev/video0", "resolution": "...", "fps": 30, "connected": true }
  const CAMERA = [
    ["Perangkat", "Logitech C270", "camera"],
    ["Antarmuka", "USB (/dev/video0)", "usb"],
    ["Resolusi", "1280 × 720", "monitor"],
    ["FPS", "30 FPS", "gauge"],
    ["Status", "Connected", "wifi"],
  ];

  // TODO BACKEND:
  // Hasil ini nanti berasal dari proses cv2.calibrateCamera() di server.
  // Endpoint: POST /api/calibration/process  ->  GET /api/calibration/result
  const DUMMY_RESULT = {
    matrix: [[1012.34, 0, 642.18], [0, 1009.87, 361.52], [0, 0, 1]],
    dist: [0.1243, -0.4871, 0.0012, -0.0008, 0.5129],
    error: 0.3184,
  };

  const $ = (id) => document.getElementById(id);
  const el = {
    total: $("calTotal"), totalErr: $("calTotalError"),
    start: $("btnCalStart"), capture: $("btnCalCapture"), process: $("btnCalProcess"),
    badge: $("calStatus"), corner: $("calCorner"),
    bar: $("calProgress"), count: $("calCount"), pct: $("calPct"),
    empty: $("calEmpty"), result: $("calResult"),
  };

  let state = "idle"; // idle | collecting | ready | processing | done
  let taken = 0, total = Number(el.total.value);

  function renderCamera() {
    $("camInfo").innerHTML = CAMERA.map(([k, v, icon]) =>
      `<li><span class="k"><i data-lucide="${icon}"></i>${k}</span><span class="v">${v}</span></li>`).join("");
    App.icons();
  }

  function render() {
    const map = {
      idle: ["idle", "Idle"], collecting: ["active", "Mengambil gambar"], ready: ["success", "Siap diproses"],
      processing: ["active", "Memproses"], done: ["success", "Selesai"],
    };
    App.setBadge(el.badge, ...map[state]);
    const pct = total ? Math.round((taken / total) * 100) : 0;
    el.bar.firstElementChild.style.width = `${pct}%`;
    el.bar.classList.toggle("done", taken >= total && total > 0);
    el.count.textContent = `${taken} dari ${total} gambar`;
    el.pct.textContent = `${pct}%`;
    el.corner.textContent = taken ? `Gambar ${taken}/${total}` : "Belum ada gambar";

    el.start.disabled = state === "collecting" || state === "processing";
    el.total.disabled = state === "collecting" || state === "ready" || state === "processing";
    el.capture.disabled = state !== "collecting";
    el.process.disabled = state !== "ready";
  }

  function start() {
    const n = Number(el.total.value);
    el.totalErr.textContent = "";
    if (!Number.isInteger(n) || n < 5 || n > 100) {
      el.totalErr.textContent = "Masukkan bilangan bulat antara 5 dan 100.";
      return;
    }
    // TODO BACKEND:
    // POST /api/calibration/start
    // Payload: { "image_count": n }
    total = n; taken = 0; state = "collecting";
    el.empty.hidden = false; el.result.hidden = true;
    render();
    App.log("success", `Kalibrasi dimulai. Target ${n} gambar papan kalibrasi.`);
  }

  function capture() {
    if (state !== "collecting") return;
    // TODO BACKEND:
    // POST /api/calibration/capture
    // Server mengambil satu frame dari kamera dan mendeteksi sudut papan catur.
    taken += 1;
    App.log("info", `Gambar ${taken} dari ${total} diambil.`);
    if (taken >= total) {
      state = "ready";
      App.log("success", "Semua gambar terkumpul. Tekan Proses Kalibrasi.");
    }
    render();
  }

  function processCalibration() {
    if (state !== "ready") return;
    // TODO BACKEND:
    // POST /api/calibration/process
    // Server menjalankan kalibrasi OpenCV, lalu frontend mengambil hasilnya
    // dan memanggil showResult(data).
    state = "processing"; render();
    App.log("info", "Memproses kalibrasi (simulasi)...");
    setTimeout(() => { state = "done"; render(); showResult(DUMMY_RESULT); }, 1800);
  }

  function showResult(r) {
    $("calMatrix").innerHTML = r.matrix.flat().map((v) => `<span>${v.toFixed(2)}</span>`).join("");
    $("calDist").innerHTML = r.dist.map((v) => `<span>${v.toFixed(4)}</span>`).join("");
    $("calError").textContent = `${r.error.toFixed(4)} px`;
    const good = r.error < 0.5;
    $("calErrorBadge").className = `badge ${good ? "success" : "warn"}`;
    $("calErrorBadge").textContent = good ? "Baik (< 0,5 px)" : "Perlu diulang";
    el.empty.hidden = true; el.result.hidden = false;
    App.icons();
    App.log("success", "Kalibrasi selesai. Parameter ditampilkan (data dummy).");
  }

  el.start.addEventListener("click", start);
  el.capture.addEventListener("click", capture);
  el.process.addEventListener("click", processCalibration);
  el.total.addEventListener("input", () => { if (state === "idle" || state === "done") { total = Number(el.total.value) || 0; render(); } });

  renderCamera();
  render();
})();
