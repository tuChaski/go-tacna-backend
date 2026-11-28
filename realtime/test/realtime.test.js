import test from 'node:test';
import assert from 'node:assert/strict';
import jwt from 'jsonwebtoken';

// Configurar variables de entorno requeridas antes de importar los modulos
process.env.JWT_SECRET = 'test-secret-gotacna-qa';
process.env.PORT = '3099';
process.env.OFFLINE_AFTER_MS = '120000';

const { haversine, calculateETA } = await import('../src/services/eta.js');
const { updatePosition, getMicro, getRuta, markStopped, startTrip, checkOffline, restorePositions } = await import('../src/services/fleetState.js');
const { createAuthMiddleware } = await import('../src/middleware/auth.js');
const { passengerHandler, createEventLimiter } = await import('../src/handlers/passenger.js');
const { default: gpsRouter } = await import('../src/handlers/gps.js');

test('PRB-031 (T099/T071): haversine calcula distancia cero en el mismo punto y distancia real en Tacna', () => {
  assert.equal(haversine(-18.0146, -70.2536, -18.0146, -70.2536), 0);
  // Entre Plaza de Armas (-18.0146, -70.2536) y Av. Pinto (-18.0065, -70.2462) hay ~1.19 km
  const dist = haversine(-18.0146, -70.2536, -18.0065, -70.2462);
  assert.ok(dist > 1100 && dist < 1300, `Distancia inesperada: ${dist}`);
});

test('PRB-031 (T099/T071): calculateETA lanza error si el micro no existe y calcula ETA estimado con piso de 2 m/s', () => {
  assert.throws(() => calculateETA('micro-inexistente', -18.0146, -70.2536), /Micro no encontrado/);

  updatePosition('micro-eta-1', { lat: -18.0146, lng: -70.2536, speed: 0, rutaId: 10 });
  const resultado = calculateETA('micro-eta-1', -18.0065, -70.2462);

  assert.equal(resultado.microId, 'micro-eta-1');
  assert.equal(resultado.estimated, true);
  assert.ok(typeof resultado.etaSeconds === 'number' && resultado.etaSeconds > 0);
});

test('PRB-035 y PRB-038 (T071): fleetState registra posicion, limita 60 muestras, filtra por ruta y marca detenido', () => {
  for (let i = 1; i <= 65; i++) {
    updatePosition('micro-01', { lat: -18.01, lng: -70.25, speed: i, rutaId: 101 });
  }
  const micro = getMicro('micro-01');
  assert.equal(micro.status, 'moviendo');
  assert.equal(micro.speedSamples.length, 60);
  assert.equal(micro.speedSamples[0], 6);

  const enRuta = getRuta(101);
  assert.equal(enRuta.length, 1);
  assert.equal(enRuta[0].microId, 'micro-01');

  markStopped('micro-01');
  assert.equal(getMicro('micro-01').status, 'detenido');
  assert.equal(getRuta(101).length, 0);
  assert.equal(updatePosition('micro-01', { lat: -18.01, lng: -70.25, speed: 4, rutaId: 101 }), false);
  startTrip('micro-01');
  assert.equal(updatePosition('micro-01', { lat: -18.01, lng: -70.25, speed: 4, rutaId: 101 }), true);
  startTrip('micro-01');
  assert.equal(getRuta(101).length, 1);
});

test('T041: marca offline una sola vez tras 120 segundos y recupera estado con nuevo GPS', () => {
  const now = Date.now();
  const events = [];
  const io = { to: room => ({ emit: (name, data) => events.push({ room, name, data }) }) };
  updatePosition(88, { lat: -18.01, lng: -70.25, speed: 5, rutaId: 8 });
  getMicro(88).lastSeen = now - 120_001;

  checkOffline(io, 120_000, now);
  assert.equal(getMicro(88).status, 'desconectado');
  assert.deepEqual(events, [{ room: 'ruta:8', name: 'micro:offline', data: { microId: 88, lastSeen: now - 120_001 } }]);
  checkOffline(io, 120_000, now);
  assert.equal(events.length, 1);

  updatePosition(88, { lat: -18.02, lng: -70.26, speed: 6, rutaId: 8 });
  assert.equal(getMicro(88).status, 'moviendo');
  markStopped(88);
  getMicro(88).lastSeen = now - 240_000;
  checkOffline(io, 120_000, now);
  assert.equal(events.length, 1);
});

test('recuperación tardía no pisa GPS nuevo ni revive recorridos detenidos', () => {
  const now = Date.now();
  const saved = (id, fecha) => ({
    id, ruta_id: 81, empresa_id: 12, estado: 'en_recorrido',
    ultima_posicion: { latitud: -18.01, longitud: -70.25, velocidad: 3,
      fecha_hora: new Date(fecha).toISOString() }
  });

  updatePosition(8101, { lat: -18.02, lng: -70.26, speed: 6, rutaId: 81, empresaId: 12 });
  updatePosition(8102, { lat: -18.02, lng: -70.26, speed: 6, rutaId: 81, empresaId: 12 });
  markStopped(8102);

  assert.equal(restorePositions([
    saved(8101, now - 30_000),
    saved(8102, now - 30_000),
    saved(8103, now - 180_000)
  ], 120_000, now), 1);
  assert.equal(getMicro(8101).lat, -18.02);
  assert.equal(getMicro(8102).status, 'detenido');
  assert.equal(getMicro(8103).status, 'desconectado');
  assert.equal(getMicro(8103).lastSeen, Date.parse(saved(8103, now - 180_000).ultima_posicion.fecha_hora));
});

test('T097: limita eventos por conexión y reinicia el cupo al siguiente minuto', () => {
  let now = 0;
  const allow = createEventLimiter(2, 1000, () => now);
  assert.equal(allow(), true);
  assert.equal(allow(), true);
  assert.equal(allow(), false);
  now = 1000;
  assert.equal(allow(), true);
});

test('tracking revalida token, cuenta y asignación vigente antes de aceptar GPS', async () => {
  let assignment = { micro_id: 24, ruta_id: 10, empresa_id: 7 };
  const authMiddleware = createAuthMiddleware(async () => assignment);
  const createRes = () => ({
    statusCode: 200,
    body: null,
    status(code) { this.statusCode = code; return this; },
    json(payload) { this.body = payload; return this; }
  });

  // 1. Sin cabecera Authorization -> 401
  const resSinToken = createRes();
  await authMiddleware({ headers: {} }, resSinToken, () => {});
  assert.equal(resSinToken.statusCode, 401);

  // 2. Token con rol distinto de conductor -> 403
  const tokenPasajero = jwt.sign({ sub: '3', role: 'pasajero', microId: 24, rutaId: 10 }, process.env.JWT_SECRET);
  const resRolInvalido = createRes();
  await authMiddleware({ headers: { authorization: `Bearer ${tokenPasajero}` } }, resRolInvalido, () => {});
  assert.equal(resRolInvalido.statusCode, 403);

  // 3. Token valido de conductor -> next() y asigna req.conductor
  const tokenConductor = jwt.sign({ sub: '3', role: 'conductor', microId: 24, rutaId: 10 }, process.env.JWT_SECRET);
  const reqValido = { headers: { authorization: `Bearer ${tokenConductor}` } };
  let llamoNext = false;
  await authMiddleware(reqValido, createRes(), () => { llamoNext = true; });
  assert.equal(llamoNext, true);
  assert.deepEqual(reqValido.conductor, { microId: 24, rutaId: 10, empresaId: 7 });

  // 4. Token emitido por Laravel (claims en snake_case: rol, micro_id, ruta_id) -> next()
  assignment = { micro_id: 1, ruta_id: 1, empresa_id: 7 };
  const tokenLaravel = jwt.sign({ sub: '3', rol: 'conductor', micro_id: 1, ruta_id: 1 }, process.env.JWT_SECRET);
  const reqLaravel = { headers: { authorization: `Bearer ${tokenLaravel}` } };
  let llamoNextLaravel = false;
  await authMiddleware(reqLaravel, createRes(), () => { llamoNextLaravel = true; });
  assert.equal(llamoNextLaravel, true);
  assert.deepEqual(reqLaravel.conductor, { microId: 1, rutaId: 1, empresaId: 7 });

  assignment = { micro_id: 2, ruta_id: 1, empresa_id: 7 };
  const resReasignado = createRes();
  await authMiddleware(reqLaravel, resReasignado, () => assert.fail('No debe aceptar asignación antigua'));
  assert.equal(resReasignado.statusCode, 403);

  assignment = null;
  const resDesactivado = createRes();
  await authMiddleware(reqLaravel, resDesactivado, () => assert.fail('No debe aceptar cuenta desactivada'));
  assert.equal(resDesactivado.statusCode, 403);

  const resSinCore = createRes();
  await createAuthMiddleware(async () => { throw new Error('Sin conexión'); })(reqLaravel, resSinCore, () => assert.fail());
  assert.equal(resSinCore.statusCode, 503);
});

test('el puente firmado por Laravel evita la consulta circular y limita acción y vigencia', async () => {
  const authMiddleware = createAuthMiddleware(async () => assert.fail('El puente no debe consultar Laravel de nuevo'));
  const response = () => ({
    statusCode: 200,
    status(code) { this.statusCode = code; return this; },
    json() { return this; }
  });
  const claims = {
    sub: '3', rol: 'conductor', micro_id: 24, ruta_id: 10, empresa_id: 7,
    realtime_bridge: true, realtime_action: 'start',
    realtime_bridge_at: Math.floor(Date.now() / 1000)
  };
  const token = jwt.sign(claims, process.env.JWT_SECRET);
  const req = { path: '/start', headers: { authorization: `Bearer ${token}` } };
  let accepted = false;
  await authMiddleware(req, response(), () => { accepted = true; });
  assert.equal(accepted, true);
  assert.deepEqual(req.conductor, { microId: 24, rutaId: 10, empresaId: 7 });

  const wrongAction = response();
  await authMiddleware({ ...req, path: '/stop' }, wrongAction, () => assert.fail());
  assert.equal(wrongAction.statusCode, 403);

  const expired = response();
  const oldToken = jwt.sign({ ...claims, realtime_bridge_at: claims.realtime_bridge_at - 11 }, process.env.JWT_SECRET);
  await authMiddleware({ path: '/start', headers: { authorization: `Bearer ${oldToken}` } }, expired, () => assert.fail());
  assert.equal(expired.statusCode, 403);
});

test('PRB-034 y PRB-038 (T071): gpsRouter valida coordenadas/velocidad y emite micro:position y micro:stopped', () => {
  const positionLayer = gpsRouter.stack.find(l => l.route?.path === '/position');
  const stopLayer = gpsRouter.stack.find(l => l.route?.path === '/stop');
  const startLayer = gpsRouter.stack.find(l => l.route?.path === '/start');
  const postPosition = positionLayer.route.stack[0].handle;
  const postStop = stopLayer.route.stack[0].handle;
  const postStart = startLayer.route.stack[0].handle;

  const eventosEmitidos = [];
  const ioMock = {
    to(sala) {
      return {
        emit(evento, datos) {
          eventosEmitidos.push({ sala, evento, datos });
        }
      };
    }
  };

  const createRes = () => ({
    statusCode: 200,
    body: null,
    status(code) { this.statusCode = code; return this; },
    json(payload) { this.body = payload; return this; }
  });

  // 1. Latitud fuera de rango -> 400
  const resInvalido = createRes();
  postPosition({ body: { lat: -95, lng: -70.25, speed: 10 }, conductor: { microId: 'm24', rutaId: 10 }, io: ioMock }, resInvalido);
  assert.equal(resInvalido.statusCode, 400);

  // 2. Posicion valida -> 202 y emite micro:position a ruta:10
  const resValido = createRes();
  postPosition({ body: { lat: -18.0146, lng: -70.2536, speed: 8.5 }, conductor: { microId: 'm24', rutaId: 10 }, io: ioMock }, resValido);
  assert.equal(resValido.statusCode, 202);
  assert.deepEqual(resValido.body, { ok: true });
  assert.equal(eventosEmitidos[0].sala, 'ruta:10');
  assert.equal(eventosEmitidos[0].evento, 'micro:position');
  assert.equal(eventosEmitidos[0].datos.microId, 'm24');

  // 3. Finalizar recorrido (/stop) -> 200 y emite micro:stopped
  const resStop = createRes();
  postStop({ conductor: { microId: 'm24', rutaId: 10 }, io: ioMock }, resStop);
  assert.equal(resStop.statusCode, 200);
  assert.equal(eventosEmitidos[1].evento, 'micro:stopped');
  assert.equal(getMicro('m24').status, 'detenido');
  assert.equal(getRuta(10).some(micro => micro.microId === 'm24'), false);

  const resDespuesDeFinalizar = createRes();
  postPosition({ body: { lat: -18.0146, lng: -70.2536, speed: 8.5 }, conductor: { microId: 'm24', rutaId: 10 }, io: ioMock }, resDespuesDeFinalizar);
  assert.equal(resDespuesDeFinalizar.statusCode, 409);
  assert.equal(eventosEmitidos.length, 2);

  const resStart = createRes();
  postStart({ conductor: { microId: 'm24', rutaId: 10 } }, resStart);
  assert.equal(resStart.statusCode, 200);
  const resReinicio = createRes();
  postPosition({ body: { lat: -18.0146, lng: -70.2536, speed: 8.5 }, conductor: { microId: 'm24', rutaId: 10 }, io: ioMock }, resReinicio);
  assert.equal(resReinicio.statusCode, 202);
});

test('PRB-035 (T071): passengerHandler gestiona route:join, route:leave y eta:request', () => {
  const listeners = {};
  const salasUnidas = [];
  const salasAbandonadas = [];
  const emitidosAlCliente = [];

  const socketMock = {
    on(evento, fn) { listeners[evento] = fn; },
    join(sala) { salasUnidas.push(sala); },
    leave(sala) { salasAbandonadas.push(sala); },
    emit(evento, payload) { emitidosAlCliente.push({ evento, payload }); }
  };

  passengerHandler(socketMock, {});

  // 1. route:join con rutaId invalido emite error
  listeners['route:join']('texto-invalido');
  assert.equal(emitidosAlCliente[0].evento, 'error');

  // 2. route:join con rutaId numerico une a la sala y emite route:joined
  updatePosition(31, { lat: -18.0146, lng: -70.2536, speed: 6, rutaId: 30 });
  listeners['route:join'](30);
  assert.ok(salasUnidas.includes('ruta:30'));
  assert.equal(emitidosAlCliente[1].evento, 'route:joined');
  assert.equal(emitidosAlCliente[1].payload.rutaId, 30);
  assert.deepEqual(emitidosAlCliente[1].payload.micros, [{
    microId: 31, rutaId: 30, lat: -18.0146, lng: -70.2536,
    speed: 6, status: 'moviendo', lastSeen: getMicro(31).lastSeen
  }]);
  listeners['route:join'](-1);
  assert.equal(emitidosAlCliente.at(-1).evento, 'error');

  // 3. eta:request con callback devuelve el calculo de ETA
  let respuestaCallback = null;
  listeners['eta:request']({ microId: 31, lat: -18.0065, lng: -70.2462 }, (err, eta) => {
    respuestaCallback = { err, eta };
  });
  assert.equal(respuestaCallback.err, null);
  assert.equal(respuestaCallback.eta.microId, 31);
  assert.equal(respuestaCallback.eta.estimated, true);
  listeners['eta:request'](undefined, (err) => { respuestaCallback = { err }; });
  assert.match(respuestaCallback.err.error, /Parámetros inválidos/);
  listeners['eta:request']({ microId: 31, lat: 91, lng: -70.2462 }, (err) => { respuestaCallback = { err }; });
  assert.match(respuestaCallback.err.error, /Parámetros inválidos/);

  // 4. route:leave abandona la sala
  listeners['route:leave'](30);
  assert.ok(salasAbandonadas.includes('ruta:30'));
});
