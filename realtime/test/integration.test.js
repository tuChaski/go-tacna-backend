import test from 'node:test';
import assert from 'node:assert/strict';
import { spawn } from 'node:child_process';
import { createServer } from 'node:net';
import { createServer as createHttpServer } from 'node:http';
import jwt from 'jsonwebtoken';

async function freePort() {
  const server = createServer();
  await new Promise(resolve => server.listen(0, '127.0.0.1', resolve));
  const port = server.address().port;
  await new Promise(resolve => server.close(resolve));
  return port;
}

async function poll(base, sid) {
  const response = await fetch(`${base}/socket.io/?EIO=4&transport=polling${sid ? `&sid=${sid}` : ''}`, {
    signal: AbortSignal.timeout(3000)
  });
  assert.equal(response.status, 200);
  return (await response.text()).split('\x1e');
}

async function sendSocket(base, sid, packet) {
  const response = await fetch(`${base}/socket.io/?EIO=4&transport=polling&sid=${sid}`, {
    method: 'POST', headers: { 'content-type': 'text/plain' }, body: packet,
    signal: AbortSignal.timeout(3000)
  });
  assert.equal(response.status, 200);
}

async function tracking(base, token, action, body = {}) {
  return fetch(`${base}/tracking/${action}`, {
    method: 'POST',
    headers: { authorization: `Bearer ${token}`, 'content-type': 'application/json' },
    body: JSON.stringify(body),
    signal: AbortSignal.timeout(3000)
  });
}

test('integración real: HTTP, Socket.IO, recorrido, DTO y límite de tracking', { timeout: 15000 }, async () => {
  const port = await freePort();
  const secret = 'integration-secret-gotacna';
  const assignments = new Map([
    ['driver-1', { micro_id: 901, ruta_id: 77, empresa_id: 11 }],
    ['driver-2', { micro_id: 902, ruta_id: 77, empresa_id: 22 }]
  ]);
  const adminAccess = new Map([
    ['admin-11', [11]],
    ['admin-22', [22]]
  ]);
  const savedAt = new Date(Date.now() - 10_000).toISOString();
  const oldAt = new Date(Date.now() - 180_000).toISOString();
  const core = createHttpServer((req, res) => {
    if (req.url.startsWith('/api/v1/micros/activos?')) {
      const page = Number(new URL(req.url, 'http://localhost').searchParams.get('page'));
      const data = page === 1 ? [
        { id: 903, ruta_id: 77, empresa_id: 11, estado: 'en_recorrido',
          ultima_posicion: { latitud: -18.02, longitud: -70.25, velocidad: 6, fecha_hora: savedAt } },
        { id: 905, ruta_id: 77, empresa_id: 11, estado: 'disponible',
          ultima_posicion: { latitud: -18.03, longitud: -70.26, velocidad: 7, fecha_hora: savedAt } }
      ] : [
        { id: 904, ruta_id: 77, empresa_id: 22, estado: 'en_recorrido',
          ultima_posicion: { latitud: -18.04, longitud: -70.27, velocidad: 0, fecha_hora: oldAt } }
      ];
      res.writeHead(200, { 'content-type': 'application/json' });
      res.end(JSON.stringify({ data, meta: { last_page: 2 } }));
      return;
    }
    if (!['/api/v1/conductor/asignacion-actual', '/api/v1/admin/tiempo-real/acceso'].includes(req.url)) {
      res.writeHead(404).end();
      return;
    }
    try {
      const payload = jwt.verify(req.headers.authorization.slice(7), secret);
      if (req.url === '/api/v1/admin/tiempo-real/acceso') {
        const empresaIds = adminAccess.get(payload.sub);
        if (!empresaIds) {
          res.writeHead(403).end();
          return;
        }
        res.writeHead(200, { 'content-type': 'application/json' });
        res.end(JSON.stringify({ data: { empresa_ids: empresaIds } }));
        return;
      }
      const assignment = assignments.get(payload.sub);
      if (!assignment) {
        res.writeHead(403).end();
        return;
      }
      res.writeHead(200, { 'content-type': 'application/json' });
      res.end(JSON.stringify({ data: assignment }));
    } catch {
      res.writeHead(401).end();
    }
  });
  await new Promise(resolve => core.listen(0, '127.0.0.1', resolve));
  const corePort = core.address().port;
  const server = spawn(process.execPath, ['src/server.js'], {
    cwd: new URL('..', import.meta.url),
    env: { ...process.env, PORT: String(port), JWT_SECRET: secret, CORS_ORIGIN: '*', CORE_API_URL: `http://127.0.0.1:${corePort}`, ADMIN_RECHECK_MS: '100' },
    stdio: 'ignore'
  });
  const base = `http://127.0.0.1:${port}`;

  try {
    let ready = false;
    for (let attempt = 0; attempt < 60; attempt++) {
      if (server.exitCode !== null) throw new Error(`Servidor terminó con código ${server.exitCode}`);
      try {
        ready = (await fetch(`${base}/health`, { signal: AbortSignal.timeout(300) })).ok;
      } catch { /* El servidor aún está iniciando. */ }
      if (ready) break;
      await new Promise(resolve => setTimeout(resolve, 50));
    }
    assert.equal(ready, true, 'El servidor de tiempo real debe iniciar');

    const [open] = await poll(base);
    assert.ok(open.startsWith('0'));
    const { sid } = JSON.parse(open.slice(1));
    await sendSocket(base, sid, '40');
    assert.ok((await poll(base, sid)).some(packet => packet.startsWith('40')));
    await sendSocket(base, sid, '42["route:join",77]');
    const joined = await poll(base, sid);
    const routeSnapshot = JSON.parse(joined.find(packet => packet.startsWith('42["route:joined",')).slice(2))[1];
    assert.deepEqual(routeSnapshot.micros.map(micro => micro.microId).sort(), [903, 904]);
    assert.deepEqual(Object.keys(routeSnapshot.micros[0]).sort(),
      ['lastSeen', 'lat', 'lng', 'microId', 'rutaId', 'speed', 'status']);
    assert.equal(routeSnapshot.micros[0].lastSeen, Date.parse(savedAt));
    assert.equal(routeSnapshot.micros[0].status, 'moviendo');
    assert.equal(routeSnapshot.micros[1].status, 'desconectado');

    const adminToken = jwt.sign({ sub: 'admin-11', rol: 'admin_empresa' }, secret);
    const [adminOpen] = await poll(base);
    const adminSid = JSON.parse(adminOpen.slice(1)).sid;
    await sendSocket(base, adminSid, `40/admin,${JSON.stringify({ token: adminToken })}`);
    const adminJoined = await poll(base, adminSid);
    if (!adminJoined.some(packet => packet.startsWith('42/admin,["fleet:joined",'))) {
      adminJoined.push(...await poll(base, adminSid));
    }
    const adminSnapshot = JSON.parse(adminJoined.find(packet => packet.startsWith('42/admin,["fleet:joined",')).slice(9))[1];
    assert.deepEqual(adminSnapshot.empresaIds, [11]);
    assert.deepEqual(adminSnapshot.micros.map(micro => micro.microId), [903]);

    const otherAdminToken = jwt.sign({ sub: 'admin-22', rol: 'admin_empresa' }, secret);
    const [otherAdminOpen] = await poll(base);
    const otherAdminSid = JSON.parse(otherAdminOpen.slice(1)).sid;
    await sendSocket(base, otherAdminSid, `40/admin,${JSON.stringify({ token: otherAdminToken })}`);
    const otherAdminJoined = await poll(base, otherAdminSid);
    if (!otherAdminJoined.some(packet => packet.startsWith('42/admin,["fleet:joined",'))) {
      otherAdminJoined.push(...await poll(base, otherAdminSid));
    }
    const otherSnapshot = JSON.parse(otherAdminJoined.find(packet => packet.startsWith('42/admin,["fleet:joined",')).slice(9))[1];
    assert.deepEqual(otherSnapshot.empresaIds, [22]);
    assert.deepEqual(otherSnapshot.micros.map(micro => micro.microId), [904]);
    assert.equal(otherSnapshot.micros[0].status, 'desconectado');

    const [outsiderOpen] = await poll(base);
    const outsiderSid = JSON.parse(outsiderOpen.slice(1)).sid;
    await sendSocket(base, outsiderSid, `40/admin,${JSON.stringify({ token: 'token-invalido' })}`);
    assert.ok((await poll(base, outsiderSid)).some(packet => packet.startsWith('44/admin,')));

    const token = jwt.sign({ sub: 'driver-1', rol: 'conductor', micro_id: 901, ruta_id: 77 }, secret);
    assert.equal((await tracking(base, token, 'start')).status, 200);
    assert.equal((await tracking(base, token, 'position', { lat: -18.01, lng: -70.25, speed: 8 })).status, 202);
    const position = await poll(base, sid);
    const positionPacket = position.find(packet => packet.startsWith('42["micro:position",'));
    assert.ok(positionPacket);
    const [, dto] = JSON.parse(positionPacket.slice(2));
    assert.deepEqual(Object.keys(dto).sort(), ['lastSeen', 'lat', 'lng', 'microId', 'rutaId', 'speed', 'status']);
    const adminPosition = await poll(base, adminSid);
    assert.ok(adminPosition.some(packet => packet.startsWith('42/admin,["micro:position",')
      && packet.includes('"microId":901')));
    await assert.rejects(fetch(`${base}/socket.io/?EIO=4&transport=polling&sid=${otherAdminSid}`, {
      signal: AbortSignal.timeout(200)
    }), { name: 'TimeoutError' });

    assignments.set('driver-1', { micro_id: 999, ruta_id: 77, empresa_id: 11 });
    assert.equal((await tracking(base, token, 'position', { lat: -18.01, lng: -70.25, speed: 8 })).status, 403);
    assignments.set('driver-1', { micro_id: 901, ruta_id: 77, empresa_id: 11 });

    assert.equal((await tracking(base, token, 'stop')).status, 200);
    assert.ok((await poll(base, sid)).some(packet => packet.startsWith('42["micro:stopped",')));
    assert.ok((await poll(base, adminSid)).some(packet => packet.startsWith('42/admin,["micro:stopped",')));
    assert.equal((await tracking(base, token, 'position', { lat: -18.01, lng: -70.25, speed: 8 })).status, 409);

    adminAccess.set('admin-11', [22]);
    assert.ok((await poll(base, adminSid)).some(packet => packet.startsWith('42/admin,["fleet:joined",')
      && packet.includes('"empresaIds":[22]')));
    adminAccess.delete('admin-11');
    assert.ok((await poll(base, adminSid)).some(packet => packet.startsWith('42/admin,["admin:revoked",')));

    // La primera unión ya consumió un evento del cupo de esta conexión.
    for (let attempt = 0; attempt < 60; attempt++) {
      await sendSocket(base, sid, '42["route:join",77]');
    }
    const socketEvents = await poll(base, sid);
    assert.equal(socketEvents.filter(packet => packet.startsWith('42["route:joined",')).length, 59);
    assert.ok(socketEvents.some(packet => packet.startsWith('42["error",') && packet.includes('Demasiados eventos')));

    const otherToken = jwt.sign({ sub: 'driver-2', rol: 'conductor', micro_id: 902, ruta_id: 77 }, secret);
    for (let attempt = 0; attempt < 60; attempt++) {
      assert.equal((await tracking(base, otherToken, 'start')).status, 200);
    }
    assert.equal((await tracking(base, otherToken, 'start')).status, 429);
  } finally {
    server.kill();
    await new Promise(resolve => server.once('exit', resolve));
    await new Promise(resolve => core.close(resolve));
  }
});
