// Offline OSM extraction. No Python, remote geocoder or full-country node database.
// Usage: node import-building-points.mjs INPUT.pbf OUTPUT.sqlite minLat,minLon,maxLat,maxLon
import { createReadStream, existsSync, renameSync } from 'node:fs';
import { pipeline } from 'node:stream/promises';
import { Writable } from 'node:stream';
import { DatabaseSync } from 'node:sqlite';
import { OSMTransform } from 'osm-pbf-parser-node';
const [input, output, box] = process.argv.slice(2);
const bounds = box?.split(',').map(Number);
if (!input || !output || bounds?.length !== 4 || bounds.some(x => !Number.isFinite(x))) throw Error('Expected input, output, bounding box');
if (existsSync(output) || existsSync(output + '.partial')) throw Error('Output exists; use a new filename');
const norm = value => String(value ?? '').toLowerCase().replaceAll('ё', 'е').replace(/\s+/g, '').replace(/[.,]/g, '');
const street = value => norm(String(value ?? '').replace(/(^|\s)(улица|ул\.|ул|проспект|просп\.|пр-т)(\s|$)/gi, ' '));
const db = new DatabaseSync(output + '.partial');
db.exec('CREATE TABLE points (osm_id TEXT PRIMARY KEY, street TEXT NOT NULL, house TEXT NOT NULL, city TEXT, latitude REAL NOT NULL, longitude REAL NOT NULL); CREATE INDEX point_address ON points(street,house); BEGIN');
const insert = db.prepare('INSERT OR IGNORE INTO points VALUES(?,?,?,?,?,?)');
const nodes = new Map();
let count = 0, scanned = 0;
function save(item, lat, lon) {
  const tags = item.tags ?? {};
  if (!tags['addr:street'] || !tags['addr:housenumber']) return;
  insert.run(`${item.type}/${item.id}`, street(tags['addr:street']), norm(tags['addr:housenumber']), tags['addr:city'] ?? null, lat, lon);
  count++;
}
try {
  await pipeline(createReadStream(input), new OSMTransform({withInfo:false, withTags:{node:['addr:street','addr:housenumber','addr:city'],way:['addr:street','addr:housenumber','addr:city'],relation:false}}), new Writable({objectMode:true, write(batch, encoding, next) {
    try {
      for (const item of batch) {
        if (item.type === 'node') {
          if (item.lat >= bounds[0] && item.lat <= bounds[2] && item.lon >= bounds[1] && item.lon <= bounds[3]) {
            nodes.set(item.id, [item.lat,item.lon]); save(item,item.lat,item.lon);
          }
        } else if (item.type === 'way' && item.tags?.['addr:housenumber'] && item.refs?.length > 2 && item.refs[0] === item.refs.at(-1)) {
          const ring = item.refs.slice(0,-1).map(id => nodes.get(id));
          if (ring.every(Boolean)) save(item, ring.reduce((s,p)=>s+p[0],0)/ring.length, ring.reduce((s,p)=>s+p[1],0)/ring.length);
        }
      }
      scanned += batch.length;
      if (scanned % 100000 < batch.length) console.log(JSON.stringify({scanned,nodes:nodes.size,points:count}));
      next();
    } catch (error) { next(error); }
  }}));
  db.exec('COMMIT'); db.close(); renameSync(output + '.partial', output);
  console.log(JSON.stringify({status:'completed',points:count,bounds,attribution:'© OpenStreetMap contributors, ODbL'}));
} catch(error) { db.close(); throw error; }
