/**
 * Calculate geographical distance in metres between two coordinate pairs using the Haversine formula.
 * Earth radius = 6,371,000 metres.
 */
function calculateHaversineDistance(lat1, lon1, lat2, lon2) {
  const earthRadius = 6371000.0; // Metres

  const toRad = (deg) => (deg * Math.PI) / 180.0;

  const dLat = toRad(lat2 - lat1);
  const dLon = toRad(lon2 - lon1);

  const a =
    Math.sin(dLat / 2) * Math.sin(dLat / 2) +
    Math.cos(toRad(lat1)) * Math.cos(toRad(lat2)) * Math.sin(dLon / 2) * Math.sin(dLon / 2);

  const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));

  return Math.round(earthRadius * c * 100) / 100; // 2 decimal precision
}

module.exports = {
  calculateHaversineDistance,
};
