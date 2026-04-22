# Homography Web Calibrator

A browser-based tool for calibrating a camera-to-map homography matrix and visualising GPS coordinates as pixel positions on a camera image. Built with PHP, vanilla JavaScript, OpenCV.js, and Leaflet.

---

## What It Does

Given a static camera image and a satellite map, you pick matching point pairs (GPS ↔ camera pixel) to compute a homography matrix **H**. Once calibrated, you can click anywhere on the map and instantly see where that GPS location appears in the camera feed — with an adaptive zoom view centred on that point.

---

## Features

- **3-step workflow**: Camera Setup → Point Calibration → Location Mode
- **OpenCV.js homography**: Uses `cv.findHomography` (RANSAC) in the browser — same algorithm as Python/OpenCV
- **Adaptive zoom panel**: Shows a zoomed crop of the camera image around the projected point, scaled by local homography to represent a real-world area (default 5 m × 5 m)
- **Zoom controls**: Ratio slider, manual width/height in metres
- **Out-of-frame detection**: Warns when a point falls outside the camera view; still shows zoom if the crop box partially overlaps the frame
- **GPS input**: Plot any coordinate by typing longitude/latitude directly
- **Save & load**: All calibration data and zoom settings saved to `homography.json`

---

## Requirements

- PHP 7.4+ (built-in dev server is fine)
- A camera image saved as `camera_view.png` in the same directory
- Internet access for Leaflet tiles and OpenCV.js CDN

---

## Getting Started

```bash
# Serve the directory with PHP
php -S localhost:8000

# Open in browser
open http://localhost:8000/homography_web.php
```

---

## How to Use

### Step 1 — Camera Setup
Click on the satellite map to mark where the camera is physically located. Confirm to proceed.

### Step 2 — Calibration
Click alternating points on the **map** then the **camera image** to build corresponding pairs. At least 4 pairs are required to compute the homography. Save the result with **Save (S)**.

### Step 3 — Location Mode (Calibration Done →)
Click anywhere on the map to project that GPS coordinate onto the camera image. A zoom panel appears below the camera view showing the area around the projected point.

Use the controls in the header to adjust the zoom:
| Control | Description |
|---|---|
| **Zoom ratio** slider | Adjusts the height of the zoom crop relative to auto size |
| **W m / H m** inputs | Sets the crop area in real-world metres (overrides auto + ratio) |
| **Save** button | Saves zoom settings alongside the homography to `homography.json` |

---

## File Structure

```
.
├── homography_web.php   # Main app (PHP + HTML + JS)
├── homography.css       # Styles
├── camera_view.png      # Your camera image (you provide this)
└── homography.json      # Saved calibration output (auto-generated)
```

---

## homography.json Format

```json
{
  "H": [[...], [...], [...]],
  "pairs": [
    { "gps": { "lng": 151.0, "lat": -33.85 }, "cam_px": { "x": 320, "y": 240 } }
  ],
  "norm": { "lng0": 151.0, "lat0": -33.85, "scale": 100000 },
  "camera_position": { "lng": 151.0, "lat": -33.85 },
  "camera_to_zone_m": 42,
  "zoom_settings": { "ratio": 1.0, "w_m": null, "h_m": null },
  "saved_at": "2026-04-22T10:00:00+10:00"
}
```

**`norm`** — GPS coordinates are normalised before computing H (subtract centroid, scale by 1e5) for numerical stability. Apply the same transform when using H outside this tool.
