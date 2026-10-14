// Read-only export from the existing address API database. Never prints credentials.
const path = require('node:path');
const fs = require('node:fs');
const [root, output] = process.argv.slice(2);
if (!root || !output) throw new Error('Usage: node export_kladr.cjs <api repo> <output.json>');
if (fs.existsSync(output)) throw new Error('Output exists; use a new filename');
require(path.join(root, 'node_modules/dotenv')).config({ path: path.join(root, '.env'), quiet: true });
const { PrismaClient } = require(path.join(root, 'generated/prisma'));
const client = new PrismaClient();
(async () => {
  try {
    const regions = await client.region.findMany({ select: { externalId: true, name: true } });
    const localities = await client.locality.findMany({
      select: { externalId: true, parentExternalId: true, name: true, type: true },
      orderBy: { externalId: 'asc' },
    });
    fs.writeFileSync(output, JSON.stringify({ regions, localities }), { flag: 'wx' });
    console.log(`Exported ${regions.length} regions, ${localities.length} localities`);
  } finally { await client.$disconnect(); }
})().catch(error => { console.error(error.message); process.exitCode = 1; });
