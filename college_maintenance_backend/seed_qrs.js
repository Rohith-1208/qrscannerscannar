const { pool } = require('./config/db');
const { generateQrSignature } = require('./utils/cryptoHelper');

async function seedQrs() {
  try {
    const [systems] = await pool.execute('SELECT * FROM systems');
    console.log(`Found ${systems.length} systems to verify/seed QR signatures for...`);

    for (const sys of systems) {
      const version = 1;
      const sig = generateQrSignature(
        sys.system_id,
        parseFloat(sys.latitude),
        parseFloat(sys.longitude),
        parseFloat(sys.allowed_radius),
        version
      );

      await pool.execute(
        `INSERT INTO qr_codes (system_id, qr_version, signature, is_active) 
         VALUES (?, ?, ?, 1) 
         ON DUPLICATE KEY UPDATE signature = VALUES(signature), is_active = 1`,
        [sys.system_id, version, sig]
      );
      console.log(`[OK] Generated QR for ${sys.system_id}: sig=${sig.substring(0, 16)}...`);
    }

    console.log('All equipment systems now have active HMAC-SHA256 QR signatures!');
    process.exit(0);
  } catch (err) {
    console.error('Error seeding QRs:', err);
    process.exit(1);
  }
}

seedQrs();
