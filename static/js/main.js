/*
 * main.js - utilitas bersama untuk semua halaman.
 * Menyediakan namespace global `App` (ikon, toast, log, modal, badge, format waktu).
 */
const App = (() => {
  const icons = () => { if (window.lucide) window.lucide.createIcons(); };

  const pad = (n) => String(n).padStart(2, "0");
  const formatTime = (sec) => `${pad(Math.floor(sec / 60))}:${pad(Math.floor(sec % 60))}`;
  const nowClock = () => { const d = new Date(); return `${pad(d.getHours())}:${pad(d.getMinutes())}:${pad(d.getSeconds())}`; };

  /** Toast sementara di pojok kanan bawah. type: success | error | info */
  function toast(type, message, ms = 3200) {
    const stack = document.getElementById("toastStack");
    if (!stack) return;
    const el = document.createElement("div");
    el.className = `toast ${type}`;
    el.textContent = message;
    stack.appendChild(el);
    setTimeout(() => el.remove(), ms);
  }

  /** Tambah entri ke area notifikasi halaman (<ul id="activityLog">). */
  function log(type, message) {
    const list = document.getElementById("activityLog");
    if (!list) return;
    list.querySelector(".log-empty")?.remove();
    const li = document.createElement("li");
    li.className = type;
    const t = document.createElement("span"); t.className = "time"; t.textContent = nowClock();
    const m = document.createElement("span"); m.textContent = message;
    li.append(t, m);
    list.prepend(li);
  }

  /** Ubah badge status. variant: idle | active | success | danger | warn */
  function setBadge(el, variant, label) {
    el.className = `badge ${variant === "idle" ? "" : variant}`.trim();
    el.innerHTML = '<span class="dot"></span>';
    el.append(document.createTextNode(label));
  }

  const openModal = (id) => document.getElementById(id)?.classList.add("open");
  const closeModal = (id) => document.getElementById(id)?.classList.remove("open");

  function escapeHtml(s) {
    return String(s).replace(/[&<>"']/g, (c) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c]));
  }

  // Navbar mobile toggle
  const toggle = document.getElementById("navToggle");
  const links = document.getElementById("navLinks");
  toggle?.addEventListener("click", () => {
    const open = links.classList.toggle("open");
    toggle.setAttribute("aria-expanded", String(open));
  });

  // Modal: tutup via tombol [data-close-modal], klik latar, atau tombol Escape
  document.addEventListener("click", (e) => {
    const closer = e.target.closest("[data-close-modal]");
    if (closer) closeModal(closer.dataset.closeModal);
    if (e.target.classList?.contains("modal")) e.target.classList.remove("open");
  });
  document.addEventListener("keydown", (e) => {
    if (e.key === "Escape") document.querySelectorAll(".modal.open").forEach((m) => m.classList.remove("open"));
  });

  icons();
  return { icons, toast, log, setBadge, openModal, closeModal, formatTime, escapeHtml };
})();
