import test from 'node:test';
import assert from 'node:assert/strict';

const enabled = process.env.LIVE_COMPOSE === '1';
const base = (process.env.LIVE_BASE_URL || 'http://127.0.0.1:8001').replace(/\/$/, '');
const api = `${base}/api/v1`;

async function jsonRequest(path, { token, method = 'GET', body } = {}) {
  const response = await fetch(`${api}${path}`, {
    method,
    headers: {
      accept: 'application/json',
      ...(token ? { authorization: `Bearer ${token}` } : {}),
      ...(body ? { 'content-type': 'application/json' } : {})
    },
    body: body ? JSON.stringify(body) : undefined,
    signal: AbortSignal.timeout(8000)
  });
  return { status: response.status, data: await response.json() };
}

async function login(email, password) {
  const response = await jsonRequest('/auth/login', {
    method: 'POST', body: { correo: email, password }
  });
  assert.equal(response.status, 200, `Falló el login de ${email}: ${response.data.message}`);
  return response.data.data.token;
}

async function poll(sid) {
  const response = await fetch(`${base}/socket.io/?EIO=4&transport=polling${sid ? `&sid=${sid}` : ''}`, {
    signal: AbortSignal.timeout(8000)
  });
  assert.equal(response.status, 200, 'El sondeo de Socket.IO debe responder');
  return (await response.text()).split('\x1e');
}

async function send(sid, packet) {
  const response = await fetch(`${base}/socket.io/?EIO=4&transport=polling&sid=${sid}`, {
    method: 'POST',
    headers: { 'content-type': 'text/plain' },
    body: packet,
    signal: AbortSignal.timeout(8000)
  });
  assert.equal(response.status, 200, 'Socket.IO debe aceptar el paquete');
}

const pendingPackets = new Map();

async function waitPacket(sid, prefix) {
  for (let attempt = 0; attempt < 4; attempt++) {
    const packets = pendingPackets.get(sid) || [];
    const index = packets.findIndex(value => value.startsWith(prefix));
    if (index >= 0) return packets.splice(index, 1)[0];
    packets.push(...await poll(sid));
    pendingPackets.set(sid, packets);
    const received = packets.findIndex(value => value.startsWith(prefix));
    if (received >= 0) return packets.splice(received, 1)[0];
  }
  assert.fail(`No llegó el evento ${prefix}`);
}

async function waitEvent(sid, prefix) {
  const packet = await waitPacket(sid, prefix);
  return JSON.parse(packet.slice(prefix.includes('/admin,') ? 9 : 2));
}

async function openSocket() {
  const [open] = await poll();
  assert.ok(open.startsWith('0'));
  return JSON.parse(open.slice(1)).sid;
}

test('Docker real: Laravel guarda GPS y Socket.IO notifica a pasajero y empresa', {
  skip: !enabled,
  timeout: 60000
}, async () => {
  const driverEmail = process.env.LIVE_DRIVER_EMAIL;
  const driverPassword = process.env.LIVE_DRIVER_PASSWORD;
  const adminEmail = process.env.LIVE_ADMIN_EMAIL;
  const adminPassword = process.env.LIVE_ADMIN_PASSWORD;
  assert.ok(driverEmail && driverPassword && adminEmail && adminPassword,
    'Configura LIVE_DRIVER_EMAIL, LIVE_DRIVER_PASSWORD, LIVE_ADMIN_EMAIL y LIVE_ADMIN_PASSWORD');

  const driverToken = await login(driverEmail, driverPassword);
  const adminToken = await login(adminEmail, adminPassword);
  const perfil = await jsonRequest('/conductor/perfil', { token: driverToken });
  assert.equal(perfil.status, 200);
  const micro = perfil.data.data.micro_actual;
  assert.ok(micro?.id && micro?.ruta_id && micro?.empresa_id, 'El conductor necesita micro y ruta');
  assert.notEqual(micro.estado, 'en_recorrido', 'La prueba no debe interrumpir un recorrido existente');

  const acceso = await jsonRequest('/admin/tiempo-real/acceso', { token: adminToken });
  assert.equal(acceso.status, 200);
  assert.ok(acceso.data.data.empresa_ids.includes(micro.empresa_id));

  const passengerSid = await openSocket();
  await send(passengerSid, '40');
  await waitPacket(passengerSid, '40');
  await send(passengerSid, `42["route:join",${micro.ruta_id}]`);
  const joined = await waitEvent(passengerSid, '42["route:joined",');
  assert.equal(joined[1].rutaId, micro.ruta_id);

  const adminSid = await openSocket();
  await send(adminSid, `40/admin,${JSON.stringify({ token: adminToken })}`);
  await waitPacket(adminSid, '40/admin,');
  const fleet = await waitEvent(adminSid, '42/admin,["fleet:joined",');
  assert.ok(fleet[1].empresaIds.includes(micro.empresa_id));

  let started = false;
  try {
    const start = await jsonRequest('/conductor/iniciar', { token: driverToken, method: 'POST' });
    assert.equal(start.status, 200, `No inició el recorrido: ${start.data.message}`);
    started = true;

    const position = await jsonRequest('/tracking/position', {
      token: driverToken,
      method: 'POST',
      body: { lat: -18.0145, lng: -70.246, speed: 8 }
    });
    assert.equal(position.status, 202, `No se guardó el GPS: ${position.data.message}`);

    const publicEvent = await waitEvent(passengerSid, '42["micro:position",');
    const adminEvent = await waitEvent(adminSid, '42/admin,["micro:position",');
    for (const event of [publicEvent, adminEvent]) {
      assert.equal(event[1].microId, micro.id);
      assert.equal(event[1].rutaId, micro.ruta_id);
      assert.equal(event[1].lat, -18.0145);
    }

    const detail = await jsonRequest(`/micros/${micro.id}`);
    assert.equal(detail.status, 200);
    assert.equal(detail.data.data.ultima_posicion.latitud, -18.0145);
    assert.equal(detail.data.data.conductor_actual, undefined);

    const stop = await jsonRequest('/conductor/finalizar', { token: driverToken, method: 'POST' });
    assert.equal(stop.status, 200, `No finalizó el recorrido: ${stop.data.message}`);
    started = false;
    assert.equal((await waitEvent(passengerSid, '42["micro:stopped",'))[1].microId, micro.id);
    assert.equal((await waitEvent(adminSid, '42/admin,["micro:stopped",'))[1].microId, micro.id);
  } finally {
    if (started) {
      await jsonRequest('/conductor/finalizar', { token: driverToken, method: 'POST' });
    }
  }
});
