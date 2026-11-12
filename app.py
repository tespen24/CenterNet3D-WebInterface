"""
Car Detection & Counting - Frontend (Flask)
-------------------------------------------
Tahap ini HANYA menyajikan halaman HTML. Belum ada backend, database,
REST API, integrasi Raspberry Pi, OpenCV, maupun CenterNet3D.

Jalankan:  python app.py   ->  http://127.0.0.1:5000
"""
from flask import Flask, render_template

app = Flask(__name__)

# Daftar menu navbar. `endpoint` = nama fungsi view di bawah.
NAV_ITEMS = [
    {"endpoint": "index",       "label": "Ambil Video",            "icon": "video"},
    {"endpoint": "calibration", "label": "Kalibrasi Kamera",       "icon": "crosshair"},
    {"endpoint": "centernet3d", "label": "Jalankan CenterNet3D",   "icon": "cpu"},
    {"endpoint": "recent",      "label": "Recent",                 "icon": "history"},
]


@app.context_processor
def inject_nav():
    """Membuat `nav_items` tersedia di semua template (dipakai base.html)."""
    return {"nav_items": NAV_ITEMS}


@app.route("/")
def index():
    return render_template("index.html")


@app.route("/calibration")
def calibration():
    return render_template("calibration.html")


@app.route("/centernet3d")
def centernet3d():
    return render_template("centernet3d.html")


@app.route("/recent")
def recent():
    return render_template("recent.html")


# TODO BACKEND:
# Tambahkan blueprint REST API di sini (atau file terpisah, mis. api.py):
#   GET  /api/raspberry/status        -> status & info Raspberry Pi
#   POST /api/capture/start           -> mulai perekaman
#   POST /api/capture/stop            -> hentikan perekaman
#   GET  /api/storage/directories     -> daftar direktori server
#   GET  /api/calibration/camera      -> info kamera
#   POST /api/calibration/start|capture|process
#   GET  /api/videos                  -> daftar video (CenterNet3D & Recent)
#   POST /api/centernet3d/run|stop ; GET /api/centernet3d/status
#   GET  /api/recordings ; GET .../<id>/download ; DELETE .../<id>

if __name__ == "__main__":
    app.run(debug=True)
