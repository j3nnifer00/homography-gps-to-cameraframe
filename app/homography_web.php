<?php
if (isset($_GET['load'])) {
    header('Content-Type: application/json');
    $file = __DIR__ . '/homography.json';
    if (file_exists($file)) { readfile($file); }
    else { echo json_encode(['error' => 'homography.json not found']); }
    exit;
}

if (isset($_GET['img'])) {
    $file = __DIR__ . '/camera_view.png';
    if (file_exists($file)) { header('Content-Type: image/png'); readfile($file); }
    exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $body = json_decode(file_get_contents('php://input'), true);
    if (($body['action'] ?? '') === 'save') {
        $out = [
            'H'                => $body['H'],
            'pairs'            => $body['pairs'],
            'norm'             => $body['norm'],
            'camera_position'  => $body['camera_position'] ?? null,
            'camera_to_zone_m' => $body['camera_to_zone_m'] ?? null,
            'saved_at'         => date('c'),
        ];
        file_put_contents(__DIR__ . '/homography.json', json_encode($out, JSON_PRETTY_PRINT));
        echo json_encode(['ok' => true]);
    }
    exit;
}
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>PLANER — Homography Calibration</title>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
<link rel="stylesheet" href="homography.css"/>
</head>
<body>

<header>
  <h1>PLANER — Homography Calibration</h1>
  <div id="status-bar">Click the MAP panel first</div>
  <div id="save-msg">✓ homography.json saved</div>
  <div class="controls" id="ctrl-camera-setup">
    <button id="btn-cam-confirm" class="orange" disabled style="margin-left:12px;">Confirm Camera Position →</button>
    <span style="margin-left:16px; border-left:1px solid #0f3460; padding-left:16px; display:flex; align-items:center; gap:8px;">
      <span style="font-size:10px; color:#546e7a;">or</span>
      <button id="btn-load">Load setting json file</button>
    </span>
  </div>
  <div class="controls" id="ctrl-calib" style="display:none">
    <button id="btn-back-to-setup" style="margin-left:12px;">← Camera Setup</button>
    <button id="btn-undo">Undo (Z)</button>
    <button id="btn-reset">Reset (R)</button>
    <button id="btn-save" class="green" disabled>Save (S)</button>
    <button id="btn-lock" class="orange" disabled>Calibration Done →</button>
  </div>
  <div class="controls" id="ctrl-locate" style="display:none">
    <span style="margin-left:12px; display:flex; align-items:center; gap:6px;">
      <input id="inp-lng" type="number" step="0.000001" placeholder="Longitude" style="width:130px; padding:4px 6px; background:#0f3460; border:1px solid #4fc3f7; color:#e0e0e0; font-family:inherit; font-size:11px; border-radius:3px;">
      <input id="inp-lat" type="number" step="0.000001" placeholder="Latitude"  style="width:130px; padding:4px 6px; background:#0f3460; border:1px solid #4fc3f7; color:#e0e0e0; font-family:inherit; font-size:11px; border-radius:3px;">
      <button id="btn-gps-plot">Plot GPS</button>
    </span>
    <span style="margin-left:12px; display:flex; align-items:center; gap:6px; border-left:1px solid #0f3460; padding-left:12px;">
      <span style="font-size:10px; color:#546e7a;">Zoom ratio</span>
      <input id="inp-zoom-ratio" type="range" min="0.3" max="3.0" step="0.05" value="1.0" style="width:90px; accent-color:#4fc3f7;">
      <span id="lbl-zoom-ratio" style="font-size:11px; color:#4fc3f7; min-width:32px;">1.0×</span>
    </span>
    <span style="margin-left:8px; display:flex; align-items:center; gap:4px; border-left:1px solid #0f3460; padding-left:12px;">
      <span style="font-size:10px; color:#546e7a;">Size</span>
      <input id="inp-zoom-w" type="number" min="0.5" step="0.5" placeholder="W m (auto)" style="width:90px; padding:4px 6px; background:#0f3460; border:1px solid #4fc3f7; color:#e0e0e0; font-family:inherit; font-size:11px; border-radius:3px;">
      <span style="font-size:10px; color:#546e7a;">×</span>
      <input id="inp-zoom-h" type="number" min="0.5" step="0.5" placeholder="H m (auto)" style="width:90px; padding:4px 6px; background:#0f3460; border:1px solid #4fc3f7; color:#e0e0e0; font-family:inherit; font-size:11px; border-radius:3px;">
    </span>
    <button id="btn-loc-clear">Clear Locations</button>
    <button id="btn-save-zoom" class="green">Save</button>
    <button id="btn-back">← Back to Calibration</button>
  </div>
</header>

<main>
  <div class="panel" id="map-panel">
    <div class="panel-label" id="map-label">[ MAP ] → Click Here</div>
    <div id="map-leaflet"></div>
  </div>
  <div class="panel" id="cam-panel">
    <div class="panel-label" id="cam-label">[ CAMERA ] waiting...</div>
    <div class="cam-wrap">
      <canvas id="cam-canvas"></canvas>
    </div>
    <div id="zoom-wrap" style="display:none; flex-shrink:0; height:300px; flex-direction:column;">
      <div id="zoom-label" class="panel-label" style="color:#ef5350; border-top-color:#ef5350; background:#fff5f5; flex-shrink:0;">[ ZOOM ]</div>
      <div style="flex:1; display:flex; justify-content:center; align-items:center; overflow:hidden;">
        <canvas id="zoom-canvas" style="display:block;"></canvas>
        <span id="zoom-out-of-frame" style="display:none; color:#ff5252; font-size:13px; font-family:monospace;">⚠ Out of camera frame</span>
      </div>
    </div>
  </div>
</main>

<footer>
  <span>Map pts: <b id="n-map">0</b></span>
  <span>Camera pts: <b id="n-cam">0</b></span>
  <span>Pairs: <b id="n-pairs">0</b></span>
  <span>Homography: <b id="h-status">— (need 4)</b></span>
  <span id="footer-hint" style="margin-left:auto">Z=Undo &nbsp; R=Reset &nbsp; S=Save</span>
</footer>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script async src="https://docs.opencv.org/4.8.0/opencv.js" onload="onOpenCvReady()"></script>
<script>
// ── Config ────────────────────────────────────────────────────────
const LIDAR_ZONE = [
    {lng: 151.038777726, lat: -33.851131673},
    {lng: 151.039069819, lat: -33.850777102},
    {lng: 151.039217608, lat: -33.850996162},
    {lng: 151.039108661, lat: -33.851136312},
];

// GPS → normalize to numerically stable coordinates (subtract origin, scale by 1e5)
const GPS_LNG0 = LIDAR_ZONE.reduce((s,p) => s+p.lng, 0) / LIDAR_ZONE.length;
const GPS_LAT0 = LIDAR_ZONE.reduce((s,p) => s+p.lat, 0) / LIDAR_ZONE.length;
const GPS_SCALE = 1e5;
function gpsToLocal(lng, lat) {
    return { x: (lng - GPS_LNG0) * GPS_SCALE, y: (lat - GPS_LAT0) * GPS_SCALE };
}
const MIN_POINTS = 4;

// ── State ─────────────────────────────────────────────────────────
let mode         = 'camera-setup'; // 'camera-setup' | 'calib' | 'locate'
let activeWin    = 'map';
let cameraPos    = null;   // L.LatLng — camera position on map
let cameraMark   = null;   // L.Marker
let clickedMap   = [];     // L.LatLng[]
let clickedCam   = [];     // {x,y}[]
let homography   = null;
let camImage     = null;
let camW = 0, camH = 0;
let leafletMap, zoneLayer;
let mapMarkers   = [];     // L.CircleMarker[]
let zoneMarkers  = [];
let locMarkers   = [];     // markers in location mode
let locPoints    = [];     // [{latlng, camPx}]
let zoomHeightScale = 1.0; // user-adjustable inset height multiplier
let zoomMetW = 0, zoomMetH = 0; // manual crop size in metres (0 = auto)

// ── Leaflet init ──────────────────────────────────────────────────
function initLeaflet() {
    const avgLat = LIDAR_ZONE.reduce((s,p) => s + p.lat, 0) / LIDAR_ZONE.length;
    const avgLng = LIDAR_ZONE.reduce((s,p) => s + p.lng, 0) / LIDAR_ZONE.length;

    leafletMap = L.map('map-leaflet', { center: [avgLat, avgLng], zoom: 19 });

    L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
        attribution: '© Esri, Maxar, Earthstar Geographics',
        maxZoom: 21, maxNativeZoom: 19,
    }).addTo(leafletMap);

    // Zone polygon
    const latlngs = LIDAR_ZONE.map(p => [p.lat, p.lng]);
    zoneLayer = L.polygon(latlngs, {
        color: '#1565c0', weight: 2.5,
        fillColor: '#1976d2', fillOpacity: 0.25,
    }).addTo(leafletMap);

    // Zone vertex labels
    LIDAR_ZONE.forEach((p, i) => {
        const m = L.circleMarker([p.lat, p.lng], {
            radius: 6, color: '#fff', weight: 1.5,
            fillColor: '#1976d2', fillOpacity: 1,
        }).addTo(leafletMap);
        m.bindTooltip(`Z${i+1}`, { permanent: true, direction: 'top',
            className: 'pt-label', offset: [0, -4] });
        zoneMarkers.push(m);
    });

    // Map click
    leafletMap.on('click', e => {
        if (mode === 'camera-setup') {
            onCameraSetupClick(e.latlng);
            return;
        }
        if (mode === 'locate') {
            onLocateClick(e.latlng);
            return;
        }
        if (activeWin !== 'map') return;
        clickedMap.push(e.latlng);
        const n = clickedMap.length;
        const m = L.circleMarker(e.latlng, {
            radius: 9, color: '#fff', weight: 2,
            fillColor: '#00e676', fillOpacity: 1,
        }).addTo(leafletMap);
        m.bindTooltip(String(n), { permanent: true, direction: 'top',
            className: 'pt-label', offset: [0, -4] });
        mapMarkers.push(m);

        activeWin = 'cam';
        tryComputeH();
        drawCam();
        updateUI();
    });

}


// ── Camera setup ──────────────────────────────────────────────────
function onCameraSetupClick(latlng) {
    cameraPos = latlng;
    if (cameraMark) cameraMark.remove();
    cameraMark = L.marker(latlng, {
        icon: L.divIcon({
            className: '',
            html: `<div style="background:#ffa726;border:2px solid #fff;border-radius:50% 50% 50% 0;
                   width:22px;height:22px;transform:rotate(-45deg);box-shadow:0 2px 6px rgba(0,0,0,0.5)">
                   </div>`,
            iconAnchor: [11, 22],
        })
    }).addTo(leafletMap);
    cameraMark.bindTooltip('Camera', { permanent: true, direction: 'top', className: 'pt-label' });

    const dist = cameraToZoneDist();
    document.getElementById('btn-cam-confirm').disabled = false;
    document.getElementById('status-bar').textContent =
        `Camera position: (${latlng.lng.toFixed(6)}, ${latlng.lat.toFixed(6)}) — ${dist}m to zone center. Click "Confirm Camera Position →" to proceed.`;
}

function cameraToZoneDist() {
    if (!cameraPos) return '—';
    const zoneLat = LIDAR_ZONE.reduce((s,p) => s+p.lat,0) / LIDAR_ZONE.length;
    const zoneLng = LIDAR_ZONE.reduce((s,p) => s+p.lng,0) / LIDAR_ZONE.length;
    const dLat = (cameraPos.lat - zoneLat) * 111000;
    const dLng = (cameraPos.lng - zoneLng) * 111000 * Math.cos(zoneLat * Math.PI / 180);
    return Math.round(Math.hypot(dLat, dLng));
}

document.getElementById('btn-cam-confirm').addEventListener('click', () => {
    mode = 'calib';
    document.getElementById('ctrl-camera-setup').style.display = 'none';
    document.getElementById('ctrl-calib').style.display        = 'flex';
    document.getElementById('map-label').textContent  = '[ MAP ] → Click Here';
    document.getElementById('status-bar').textContent = 'Camera position saved. Now click corresponding points on MAP then CAMERA.';
    document.getElementById('map-panel').classList.add('active');
});

document.getElementById('btn-load').addEventListener('click', async () => {
    const res  = await fetch('?load=1');
    const data = await res.json();
    if (data.error) {
        document.getElementById('status-bar').textContent = '⚠ ' + data.error;
        return;
    }
    loadHomographyData(data);
});

function loadHomographyData(data) {
    // Restore homography matrix
    homography = data.H;

    // Restore camera position marker
    if (data.camera_position) {
        const ll = L.latLng(data.camera_position.lat, data.camera_position.lng);
        cameraPos = ll;
        if (cameraMark) cameraMark.remove();
        cameraMark = L.marker(ll, {
            icon: L.divIcon({
                className: '',
                html: `<div style="background:#ffa726;border:2px solid #fff;border-radius:50% 50% 50% 0;
                       width:22px;height:22px;transform:rotate(-45deg);box-shadow:0 2px 6px rgba(0,0,0,0.5)">
                       </div>`,
                iconAnchor: [11, 22],
            })
        }).addTo(leafletMap);
        cameraMark.bindTooltip('Camera', { permanent: true, direction: 'top', className: 'pt-label' });
    }

    // Restore calibration pair markers on map
    if (data.pairs?.length) {
        clickedMap = []; clickedCam = [];
        mapMarkers.forEach(m => m.remove()); mapMarkers = [];
        data.pairs.forEach((pair, i) => {
            const ll = L.latLng(pair.gps.lat, pair.gps.lng);
            clickedMap.push(ll);
            clickedCam.push({ x: pair.cam_px.x, y: pair.cam_px.y });
            const m = L.circleMarker(ll, {
                radius: 9, color: '#fff', weight: 2,
                fillColor: '#00e676', fillOpacity: 1,
            }).addTo(leafletMap);
            m.bindTooltip(String(i + 1), { permanent: true, direction: 'top',
                className: 'pt-label', offset: [0, -4] });
            mapMarkers.push(m);
        });
    }

    // Switch straight to locate mode
    mode = 'locate';
    document.body.classList.add('location-mode');
    document.getElementById('ctrl-camera-setup').style.display = 'none';
    document.getElementById('ctrl-calib').style.display        = 'none';
    document.getElementById('ctrl-locate').style.display       = 'flex';
    document.getElementById('map-label').textContent  = '[ MAP ] Click a location';
    document.getElementById('cam-label').textContent  = '[ CAMERA ] Projected position';
    document.getElementById('map-panel').classList.add('active');
    document.getElementById('cam-panel').classList.remove('active');
    document.getElementById('footer-hint').textContent = 'Map click → show camera position';

    // Restore zoom settings
    if (data.zoom_settings) {
        const z = data.zoom_settings;
        zoomHeightScale = z.ratio ?? 1.0;
        zoomMetW = z.w_m ?? 0;
        zoomMetH = z.h_m ?? 0;
        document.getElementById('inp-zoom-ratio').value = zoomHeightScale;
        document.getElementById('lbl-zoom-ratio').textContent = zoomHeightScale.toFixed(2) + '×';
        document.getElementById('inp-zoom-w').value = zoomMetW || '';
        document.getElementById('inp-zoom-h').value = zoomMetH || '';
    }

    const saved = data.saved_at ? ` (saved ${new Date(data.saved_at).toLocaleString()})` : '';
    document.getElementById('status-bar').textContent =
        `Loaded: ${data.pairs?.length ?? 0} pairs, camera ${data.camera_to_zone_m ?? '?'}m from zone${saved}`;

    drawCam();
    updateUI();
}

// ── OpenCV.js ready ───────────────────────────────────────────────
let cvReady = false;
function onOpenCvReady() {
    cvReady = true;
    document.getElementById('status-bar').textContent = 'OpenCV ready — Click the MAP panel first';
    // compute now if points were added before OpenCV loaded
    tryComputeH();
    drawCam();
    updateUI();
}

// ── Homography via cv.findHomography (same as Python) ────────────
function computeHomography(srcPts, dstPts) {
    const n      = srcPts.length;
    const srcArr = new Float32Array(srcPts.flatMap(p => [p.x, p.y]));
    const dstArr = new Float32Array(dstPts.flatMap(p => [p.x, p.y]));

    const srcMat = cv.matFromArray(n, 1, cv.CV_32FC2, srcArr);
    const dstMat = cv.matFromArray(n, 1, cv.CV_32FC2, dstArr);
    const mask   = new cv.Mat();

    // findHomography returns H as a return value (not a parameter)
    const H = cv.findHomography(srcMat, dstMat, cv.RANSAC || 8, 5.0, mask);

    const h = H.data64F;
    const result = [
        [h[0], h[1], h[2]],
        [h[3], h[4], h[5]],
        [h[6], h[7], h[8]],
    ];

    srcMat.delete(); dstMat.delete(); H.delete(); mask.delete();
    return result;
}

function perspectiveTransform(pts, H) {
    return pts.map(({x,y}) => {
        const w = H[2][0]*x + H[2][1]*y + H[2][2];
        return { x:(H[0][0]*x+H[0][1]*y+H[0][2])/w,
                 y:(H[1][0]*x+H[1][1]*y+H[1][2])/w };
    });
}

function tryComputeH() {
    const mapPts = clickedMap.map(ll => gpsToLocal(ll.lng, ll.lat));
    const n = Math.min(mapPts.length, clickedCam.length);
    if (n >= MIN_POINTS) {
        if (!cvReady) {
            document.getElementById('h-status').textContent = '⏳ OpenCV loading... (will compute automatically)';
            return;
        }
        try {
            homography = computeHomography(mapPts.slice(0,n), clickedCam.slice(0,n));
        } catch(e) {
            homography = null;
            console.error('Homography failed:', e);
            document.getElementById('h-status').textContent = '⚠ Computation error: ' + e.message;
        }
    } else { homography = null; }
}

// ── Camera canvas ─────────────────────────────────────────────────
function drawCam() {
    const canvas = document.getElementById('cam-canvas');
    const ctx    = canvas.getContext('2d');
    ctx.clearRect(0, 0, camW, camH);

    if (camImage) ctx.drawImage(camImage, 0, 0, camW, camH);
    else { ctx.fillStyle='#263238'; ctx.fillRect(0,0,camW,camH); }

    // Projected zone
    if (homography) {
        const zonePts = LIDAR_ZONE.map(p => gpsToLocal(p.lng, p.lat));
        const proj = perspectiveTransform(zonePts, homography);

        // Debug: print projected coords on canvas
        ctx.fillStyle = 'rgba(0,0,0,0.55)';
        ctx.fillRect(4, 4, 260, 18 + proj.length * 16);
        ctx.fillStyle = '#69f0ae';
        ctx.font = '11px Courier New';
        ctx.fillText('Projected zone:', 8, 18);
        proj.forEach((p,i) => {
            const ok = p.x>=0 && p.x<=camW && p.y>=0 && p.y<=camH;
            ctx.fillStyle = ok ? '#69f0ae' : '#ff5252';
            ctx.fillText(`Z${i+1}: (${Math.round(p.x)}, ${Math.round(p.y)})${ok?'':' OUT'}`, 8, 34+i*16);
        });

        ctx.beginPath();
        ctx.moveTo(proj[0].x, proj[0].y);
        proj.slice(1).forEach(p => ctx.lineTo(p.x, p.y));
        ctx.closePath();
        ctx.fillStyle   = 'rgba(0,230,118,0.2)';
        ctx.fill();
        ctx.strokeStyle = '#00e676';
        ctx.lineWidth   = 2.5;
        ctx.stroke();
        proj.forEach((p,i) => {
            ctx.beginPath(); ctx.arc(p.x,p.y,5,0,Math.PI*2);
            ctx.fillStyle='#00e676'; ctx.fill();
            ctx.fillStyle='#fff'; ctx.font='bold 11px Courier New';
            ctx.fillText(`Z${i+1}`, p.x+7, p.y-4);
        });
    } else {
        ctx.fillStyle = 'rgba(0,0,0,0.5)';
        ctx.fillRect(4, 4, 200, 22);
        ctx.fillStyle = '#ff5252';
        ctx.font = '11px Courier New';
        ctx.fillText('H: null (not computed)', 8, 18);
    }

    // Dashed box on main image for the most recent location point
    if (locPoints.length > 0 && camImage && homography) {
        const {camPx: p, latlng} = locPoints[locPoints.length - 1];
        const crop = computeZoomCrop(p, latlng);
        if (crop) {
            ctx.save();
            ctx.setLineDash([4, 3]);
            ctx.strokeStyle = '#ff5252'; ctx.lineWidth = 1.5;
            ctx.strokeRect(crop.srcX, crop.srcY, crop.srcW, crop.srcH);
            ctx.restore();
        }
    }

    // Location mode points
    locPoints.forEach(({camPx: p, n}) => {
        ctx.beginPath(); ctx.arc(p.x, p.y, 12, 0, Math.PI*2);
        ctx.fillStyle = 'rgba(255,82,82,0.25)'; ctx.fill();
        ctx.beginPath(); ctx.arc(p.x, p.y, 6, 0, Math.PI*2);
        ctx.fillStyle = '#ff5252'; ctx.fill();
        ctx.strokeStyle = '#fff'; ctx.lineWidth = 2; ctx.stroke();
        // crosshair
        ctx.strokeStyle = '#ff5252'; ctx.lineWidth = 1.5;
        ctx.beginPath(); ctx.moveTo(p.x-18, p.y); ctx.lineTo(p.x+18, p.y); ctx.stroke();
        ctx.beginPath(); ctx.moveTo(p.x, p.y-18); ctx.lineTo(p.x, p.y+18); ctx.stroke();
        ctx.fillStyle = '#fff'; ctx.font = 'bold 12px Courier New';
        ctx.fillText(n, p.x + 14, p.y - 8);
    });

    // Clicked camera points (calib mode)
    clickedCam.forEach((p,i) => {
        ctx.beginPath(); ctx.arc(p.x,p.y,9,0,Math.PI*2);
        ctx.fillStyle='#ff7043'; ctx.fill();
        ctx.strokeStyle='#fff'; ctx.lineWidth=2; ctx.stroke();
        ctx.fillStyle='#fff'; ctx.font='bold 12px Courier New';
        ctx.fillText(i+1, p.x+12, p.y+4);
    });

    // Active border
    if (activeWin==='cam') {
        ctx.strokeStyle='#69f0ae'; ctx.lineWidth=3;
        ctx.strokeRect(2,2,camW-4,camH-4);
    }

    drawZoom();
}

// ── Zoom panel ────────────────────────────────────────────────────
function computeZoomCrop(p, latlng) {
    if (!homography) return null;
    const mPerDegLat = 111000;
    const mPerDegLng = 111000 * Math.cos(latlng.lat * Math.PI / 180);
    const step = 1;
    const stepCorners = [
        gpsToLocal(latlng.lng - step/mPerDegLng, latlng.lat + step/mPerDegLat),
        gpsToLocal(latlng.lng + step/mPerDegLng, latlng.lat + step/mPerDegLat),
        gpsToLocal(latlng.lng + step/mPerDegLng, latlng.lat - step/mPerDegLat),
        gpsToLocal(latlng.lng - step/mPerDegLng, latlng.lat - step/mPerDegLat),
    ];
    const sc = perspectiveTransform(stepCorners, homography);
    const scW = Math.max(...sc.map(c=>c.x)) - Math.min(...sc.map(c=>c.x));
    const scH = Math.max(...sc.map(c=>c.y)) - Math.min(...sc.map(c=>c.y));
    const targetM = 5;
    const autoW = Math.max(20, scW * targetM / (2 * step));
    const autoH = Math.max(20, scH * targetM / (2 * step));
    const pxPerMW = scW / (2 * step);
    const pxPerMH = scH / (2 * step);
    const srcW = zoomMetW > 0 ? Math.max(20, pxPerMW * zoomMetW) : autoW;
    const srcH = zoomMetH > 0 ? Math.max(20, pxPerMH * zoomMetH) : autoH * zoomHeightScale;
    const srcX = Math.max(0, Math.min(p.x - srcW / 2, camW - srcW));
    const srcY = Math.max(0, Math.min(p.y - srcH / 2, camH - srcH));
    const distM = cameraPos ? cameraPos.distanceTo(latlng) : null;
    return { srcX, srcY, srcW, srcH, distM };
}

function drawZoom() {
    const wrap = document.getElementById('zoom-wrap');
    if (locPoints.length === 0 || !camImage) { wrap.style.display = 'none'; return; }

    const {camPx: p, latlng, n} = locPoints[locPoints.length - 1];
    const crop = computeZoomCrop(p, latlng);
    if (!crop) { wrap.style.display = 'none'; return; }

    wrap.style.display = 'flex';

    const zc      = document.getElementById('zoom-canvas');
    const warning = document.getElementById('zoom-out-of-frame');
    // Show zoom if the crop box (≈ car size) overlaps the frame, even if centre is outside
    const inBounds = (p.x + crop.srcW / 2) > 0 && (p.x - crop.srcW / 2) < camW &&
                     (p.y + crop.srcH / 2) > 0 && (p.y - crop.srcH / 2) < camH;
    if (!inBounds) {
        zc.style.display      = 'none';
        warning.style.display = 'inline';
        document.getElementById('zoom-label').textContent = '[ ZOOM ]';
        return;
    }
    zc.style.display      = 'block';
    warning.style.display = 'none';

    const maxH     = zc.parentElement.clientHeight || 180;
    const maxW     = zc.parentElement.clientWidth  || 400;
    const aspect   = crop.srcW / crop.srcH;
    const dispH    = maxH;
    const dispW    = Math.round(Math.min(maxH * aspect, maxW));
    const zoom     = dispW / crop.srcW;
    zc.width  = dispW;
    zc.height = dispH;

    const zctx = zc.getContext('2d');
    const imgScale = camImage.naturalWidth / camW;
    zctx.drawImage(camImage,
        crop.srcX * imgScale, crop.srcY * imgScale,
        crop.srcW * imgScale, crop.srcH * imgScale,
        0, 0, dispW, dispH);

    // Crosshair
    const cx = ((p.x - crop.srcX) / crop.srcW) * dispW;
    const cy = ((p.y - crop.srcY) / crop.srcH) * dispH;
    zctx.strokeStyle = '#ff5252'; zctx.lineWidth = 1.5;
    zctx.beginPath(); zctx.moveTo(cx - 24, cy); zctx.lineTo(cx + 24, cy); zctx.stroke();
    zctx.beginPath(); zctx.moveTo(cx, cy - 24); zctx.lineTo(cx, cy + 24); zctx.stroke();

    // Label
    const distLabel = crop.distM !== null ? `  ${crop.distM.toFixed(1)}m` : '';
    document.getElementById('zoom-label').textContent =
        `[ ZOOM  ${zoom.toFixed(1)}×${distLabel}  pt ${n} ]`;
}

// ── Camera click ──────────────────────────────────────────────────
document.getElementById('cam-canvas').addEventListener('click', e => {
    if (activeWin !== 'cam') return;
    const r = e.target.getBoundingClientRect();
    clickedCam.push({ x: e.clientX - r.left, y: e.clientY - r.top });
    activeWin = 'map';
    tryComputeH();
    drawCam();
    updateUI();
});

// ── UI ────────────────────────────────────────────────────────────
function updateUI() {
    if (mode === 'camera-setup') {
        document.getElementById('status-bar').textContent = 'Click the map to mark the camera location';
        return;
    }
    const n = Math.min(clickedMap.length, clickedCam.length);
    document.getElementById('n-map').textContent   = clickedMap.length;
    document.getElementById('n-cam').textContent   = clickedCam.length;
    document.getElementById('n-pairs').textContent = n;
    document.getElementById('h-status').textContent =
        homography ? `✓ (${n} pairs)` : `— (need ${MIN_POINTS})`;
    document.getElementById('btn-save').disabled = !homography;
    document.getElementById('btn-lock').disabled = !homography;

    const isMap = activeWin === 'map';
    document.getElementById('map-panel').classList.toggle('active', isMap);
    document.getElementById('cam-panel').classList.toggle('active', !isMap);
    document.getElementById('map-label').textContent  = isMap ? '[ MAP ] → Click Here' : '[ MAP ] waiting...';
    document.getElementById('cam-label').textContent  = !isMap ? '[ CAMERA ] → Click Here' : '[ CAMERA ] waiting...';
    document.getElementById('status-bar').textContent = isMap
        ? `Click on the MAP (${clickedMap.length} points added)`
        : `Click corresponding point ${clickedMap.length} on the CAMERA`;
}

// ── Location mode ─────────────────────────────────────────────────
function enterLocateMode() {
    mode = 'locate';
    document.body.classList.add('location-mode');
    document.getElementById('ctrl-calib').style.display  = 'none';
    document.getElementById('ctrl-locate').style.display = 'flex';
    document.getElementById('map-label').textContent  = '[ MAP ] Click a location';
    document.getElementById('cam-label').textContent  = '[ CAMERA ] Projected position';
    document.getElementById('map-panel').classList.add('active');
    document.getElementById('cam-panel').classList.remove('active');
    document.getElementById('status-bar').textContent = 'Click on the map to show its position on the camera';
    document.getElementById('footer-hint').textContent = 'Map click → show camera position';
}

function exitLocateMode() {
    mode = 'calib';
    document.body.classList.remove('location-mode');
    locMarkers.forEach(m => m.remove()); locMarkers = []; locPoints = [];
    document.getElementById('ctrl-calib').style.display  = 'flex';
    document.getElementById('ctrl-locate').style.display = 'none';
    document.getElementById('footer-hint').textContent = 'Z=Undo \u00a0 R=Reset \u00a0 S=Save';
    drawCam(); updateUI();
}

function onLocateClick(latlng) {
    const local  = gpsToLocal(latlng.lng, latlng.lat);
    const camPx  = perspectiveTransform([local], homography)[0];

    // Leaflet marker
    const m = L.circleMarker(latlng, {
        radius: 10, color: '#fff', weight: 2,
        fillColor: '#ff5252', fillOpacity: 1,
    }).addTo(leafletMap);
    const n = locPoints.length + 1;
    m.bindTooltip(String(n), { permanent: true, direction: 'top',
        className: 'loc-label', offset: [0, -4] });
    locMarkers.push(m);
    locPoints.push({ latlng, camPx, n });

    drawCam();
    const inBounds = camPx.x >= 0 && camPx.x <= camW && camPx.y >= 0 && camPx.y <= camH;
    document.getElementById('status-bar').innerHTML =
        `Point ${n}: (${latlng.lng.toFixed(6)}, ${latlng.lat.toFixed(6)}) → camera (${Math.round(camPx.x)}, ${Math.round(camPx.y)})` +
        (inBounds ? '' : '  <span style="color:#ff5252;">⚠ Out of camer frame</span>');
}

// ── Buttons ───────────────────────────────────────────────────────
document.getElementById('btn-reset').addEventListener('click', () => {
    clickedMap = []; clickedCam = []; homography = null; activeWin = 'map';
    mapMarkers.forEach(m => m.remove()); mapMarkers = [];
    document.getElementById('save-msg').style.display = 'none';
    drawCam(); updateUI();
});

document.getElementById('btn-undo').addEventListener('click', () => {
    if (activeWin === 'cam' && clickedMap.length > 0) {
        clickedMap.pop();
        const m = mapMarkers.pop(); if (m) m.remove();
        activeWin = 'map';
    } else if (activeWin === 'map' && clickedCam.length > 0) {
        clickedCam.pop();
        activeWin = 'cam';
    }
    tryComputeH(); drawCam(); updateUI();
});

document.getElementById('btn-save').addEventListener('click', async () => {
    if (!homography) return;
    const n = Math.min(clickedMap.length, clickedCam.length);
    const pairs = clickedMap.slice(0, n).map((ll, i) => ({
        gps:    { lng: ll.lng, lat: ll.lat },
        cam_px: { x: clickedCam[i].x, y: clickedCam[i].y },
    }));
    const payload = {
        action: 'save',
        H: homography,
        pairs,
        norm:           { lng0: GPS_LNG0, lat0: GPS_LAT0, scale: GPS_SCALE },
        camera_position: cameraPos ? { lng: cameraPos.lng, lat: cameraPos.lat } : null,
        camera_to_zone_m: cameraPos ? cameraToZoneDist() : null,
        zoom_settings: { ratio: zoomHeightScale, w_m: zoomMetW || null, h_m: zoomMetH || null },
    };
    const res  = await fetch('', { method:'POST',
        headers:{'Content-Type':'application/json'},
        body: JSON.stringify(payload) });
    const data = await res.json();
    if (data.ok) {
        const el = document.getElementById('save-msg');
        el.style.display = 'inline';
        setTimeout(() => el.style.display = 'none', 3000);
    }
});

document.getElementById('btn-lock').addEventListener('click', enterLocateMode);
document.getElementById('btn-back').addEventListener('click', exitLocateMode);
document.getElementById('btn-back-to-setup').addEventListener('click', () => {
    mode = 'camera-setup';
    document.getElementById('ctrl-calib').style.display  = 'none';
    document.getElementById('ctrl-camera-setup').style.display = 'flex';
    document.getElementById('map-label').textContent = '[ MAP ] → Click Here';
    document.getElementById('cam-label').textContent = '[ CAMERA ] waiting...';
    document.getElementById('map-panel').classList.add('active');
    document.getElementById('cam-panel').classList.remove('active');
    updateUI();
});
document.getElementById('btn-save-zoom').addEventListener('click', async () => {
    if (!homography) return;
    const n = Math.min(clickedMap.length, clickedCam.length);
    const pairs = clickedMap.slice(0, n).map((ll, i) => ({
        gps:    { lng: ll.lng, lat: ll.lat },
        cam_px: { x: clickedCam[i].x, y: clickedCam[i].y },
    }));
    const payload = {
        action: 'save',
        H: homography,
        pairs,
        norm:             { lng0: GPS_LNG0, lat0: GPS_LAT0, scale: GPS_SCALE },
        camera_position:  cameraPos ? { lng: cameraPos.lng, lat: cameraPos.lat } : null,
        camera_to_zone_m: cameraPos ? cameraToZoneDist() : null,
        zoom_settings:    { ratio: zoomHeightScale, w_m: zoomMetW || null, h_m: zoomMetH || null },
    };
    const res  = await fetch('', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify(payload) });
    const data = await res.json();
    if (data.ok) {
        const el = document.getElementById('save-msg');
        el.style.display = 'inline';
        setTimeout(() => el.style.display = 'none', 3000);
    }
});

document.getElementById('btn-loc-clear').addEventListener('click', () => {
    locMarkers.forEach(m => m.remove()); locMarkers = []; locPoints = [];
    drawCam();
});

document.getElementById('inp-zoom-ratio').addEventListener('input', e => {
    zoomHeightScale = parseFloat(e.target.value);
    document.getElementById('lbl-zoom-ratio').textContent = zoomHeightScale.toFixed(2) + '×';
    zoomMetW = 0; zoomMetH = 0;
    document.getElementById('inp-zoom-w').value = '';
    document.getElementById('inp-zoom-h').value = '';
    drawCam();
});

document.getElementById('inp-zoom-w').addEventListener('input', e => {
    zoomMetW = parseFloat(e.target.value) || 0;
    drawCam();
});
document.getElementById('inp-zoom-h').addEventListener('input', e => {
    zoomMetH = parseFloat(e.target.value) || 0;
    drawCam();
});

document.getElementById('btn-gps-plot').addEventListener('click', () => {
    const lng = parseFloat(document.getElementById('inp-lng').value);
    const lat = parseFloat(document.getElementById('inp-lat').value);
    if (isNaN(lng) || isNaN(lat)) {
        document.getElementById('status-bar').textContent = '⚠ Enter a valid Longitude / Latitude value.';
        return;
    }
    if (!homography) {
        document.getElementById('status-bar').textContent = '⚠ Homography has not been computed yet.';
        return;
    }
    const local = gpsToLocal(lng, lat);
    const camPx = perspectiveTransform([local], homography)[0];
    const inBounds = camPx.x >= 0 && camPx.x <= camW && camPx.y >= 0 && camPx.y <= camH;
    console.log('local:', local, '→ camPx:', camPx, '| canvas:', camW, 'x', camH, '| H[2][2]:', homography[2][2]);

    // Add marker only — no panTo (moving the map breaks tile loading)
    const n = locPoints.length + 1;
    const m = L.circleMarker([lat, lng], {
        radius: 10, color: '#fff', weight: 2,
        fillColor: '#ff5252', fillOpacity: 1,
    }).addTo(leafletMap);
    m.bindTooltip(String(n), { permanent: true, direction: 'top',
        className: 'loc-label', offset: [0, -4] });
    locMarkers.push(m);
    locPoints.push({ latlng: L.latLng(lat, lng), camPx, n });

    drawCam();
    document.getElementById('status-bar').innerHTML =
        `Point ${n}: (${lng.toFixed(6)}, ${lat.toFixed(6)}) → camera (${Math.round(camPx.x)}, ${Math.round(camPx.y)})` +
        (inBounds ? '' : '  <span style="color:#ff5252;">⚠ Out of frame</span>');
});


// Trigger Plot GPS on Enter key
['inp-lng', 'inp-lat'].forEach(id => {
    document.getElementById(id).addEventListener('keydown', e => {
        if (e.key === 'Enter') document.getElementById('btn-gps-plot').click();
    });
});

document.addEventListener('keydown', e => {
    if (mode === 'calib') {
        if (e.key.toLowerCase() === 'r') document.getElementById('btn-reset').click();
        if (e.key.toLowerCase() === 's') document.getElementById('btn-save').click();
        if (e.key.toLowerCase() === 'z') document.getElementById('btn-undo').click();
    }
});

// ── Init ──────────────────────────────────────────────────────────
function init() {
    initLeaflet();

    // Camera canvas sizing
    const wrap = document.querySelector('.cam-wrap');
    const img  = new Image();
    img.onload = () => {
        const scale = Math.min(wrap.clientWidth/img.naturalWidth,
                               wrap.clientHeight/img.naturalHeight, 1);
        camW = Math.round(img.naturalWidth  * scale);
        camH = Math.round(img.naturalHeight * scale);
        camImage = img;
        const c = document.getElementById('cam-canvas');
        c.width = camW; c.height = camH;
        drawCam();
    };
    img.onerror = () => {
        camW = wrap.clientWidth || 800;
        camH = wrap.clientHeight || 600;
        const c = document.getElementById('cam-canvas');
        c.width = camW; c.height = camH;
        drawCam();
    };
    img.src = '?img=camera';

    updateUI();
}

window.addEventListener('load', init);
</script>
</body>
</html>
