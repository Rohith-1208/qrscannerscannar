// ==============================================================================
// College System Maintenance Management - Admin Dashboard JS
// ==============================================================================

// Helper: Open Modal
function openModal(id) {
  const modal = document.getElementById(id);
  if (modal) {
    modal.classList.add('active');
  }
}

// Helper: Close Modal
function closeModal(id) {
  const modal = document.getElementById(id);
  if (modal) {
    modal.classList.remove('active');
  }
}

// Close modal on background click
window.addEventListener('click', (e) => {
  if (e.target.classList.contains('modal-overlay')) {
    e.target.classList.remove('active');
  }
});

// Auto-obtain GPS coordinates and Altitude from browser Geolocation API
function captureDeviceCoordinates(latInputId, lonInputId, statusSpanId, altInputId) {
  const statusSpan = document.getElementById(statusSpanId);
  if (!navigator.geolocation) {
    if (statusSpan) statusSpan.innerHTML = '<span style="color:#dc2626">Geolocation not supported by this browser.</span>';
    alert('Geolocation is not supported by your browser.');
    return;
  }

  if (statusSpan) statusSpan.innerHTML = '<span style="color:#2563eb">Acquiring 3D GPS fix...</span>';

  navigator.geolocation.getCurrentPosition(
    (position) => {
      const lat = position.coords.latitude.toFixed(8);
      const lon = position.coords.longitude.toFixed(8);
      const alt = position.coords.altitude !== null && !isNaN(position.coords.altitude) ? position.coords.altitude.toFixed(2) : '';
      const acc = position.coords.accuracy.toFixed(1);

      const latInput = document.getElementById(latInputId);
      const lonInput = document.getElementById(lonInputId);
      const altInput = altInputId ? document.getElementById(altInputId) : null;
      if (latInput) latInput.value = lat;
      if (lonInput) lonInput.value = lon;
      if (altInput && alt) altInput.value = alt;

      if (statusSpan) {
        const altStr = alt ? ` | Alt: ${alt}m` : '';
        statusSpan.innerHTML = `<span style="color:#059669">Acquired! Acc: &plusmn;${acc}m${altStr}</span>`;
      }
    },
    (err) => {
      let msg = 'Failed to obtain GPS coordinates.';
      if (err.code === 1) msg = 'Location permission denied by user.';
      if (err.code === 2) msg = 'Location unavailable or no GPS signal.';
      if (err.code === 3) msg = 'Location request timed out.';
      if (statusSpan) statusSpan.innerHTML = `<span style="color:#dc2626">${msg}</span>`;
      alert(msg);
    },
    {
      enableHighAccuracy: true,
      timeout: 10000,
      maximumAge: 0
    }
  );
}

// Minimal QR Code rendering using public QR Canvas API or SVG
function renderQRCodeOnCanvas(containerId, qrPayloadString) {
  const container = document.getElementById(containerId);
  if (!container) return;
  
  // Use Google Charts QR API or SVG QR for crisp print and view
  const encoded = encodeURIComponent(qrPayloadString);
  const qrUrl = `https://api.qrserver.com/v1/create-qr-code/?size=250x250&data=${encoded}&ecc=M`;

  container.innerHTML = `
    <div style="text-align: center; padding: 12px;">
      <img src="${qrUrl}" alt="QR Code" style="width: 220px; height: 220px; border: 1px solid #e2e8f0; border-radius: 8px; padding: 6px; background: #fff;" />
      <div style="margin-top: 10px; font-family: monospace; font-size: 11px; color: #64748b; word-break: break-all; max-width: 320px; margin-left: auto; margin-right: auto;">
        ${qrPayloadString}
      </div>
    </div>
  `;
}
