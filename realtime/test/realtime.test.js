import test from 'node:test';
import assert from 'node:assert/strict';
import jwt from 'jsonwebtoken';

// Configurar variables de entorno requeridas antes de importar los modulos
process.env.JWT_SECRET = 'test-secret-gotacna-qa';
process.env.PORT = '3099';
process.env.OFFLINE_AFTER_MS = '120000';

const { haversine, calculateETA } = await import('../src/services/eta.js');
const { updatePosition, getMicro, getRuta, markStopped } = await import('../src/services/fleetState.js');
const { authMiddleware } = await import('../src/middleware/auth.js');
const { passengerHandler } = await import('../src/handlers/passenger.js');
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
});

test('PRB-027 y PRB-040 (T099/T076): authMiddleware valida Bearer token, rol conductor y claims microId/rutaId', () => {
  const createRes = () => ({
    statusCode: 200,
    body: null,
    status(code) { this.statusCode = code; return this; },
    json(payload) { this.body = payload; return this; }
  });

  // 1. Sin cabecera Authorization -> 401
  const resSinToken = createRes();
  authMiddleware({ headers: {} }, resSinToken, () => {});
  assert.equal(resSinToken.statusCode, 401);

  // 2. Token con rol distinto de conductor -> 403
  const tokenPasajero = jwt.sign({ role: 'pasajero', microId: 'm1', rutaId: 10 }, process.env.JWT_SECRET);
  const resRolInvalido = createRes();
  authMiddleware({ headers: { authorization: `Bearer ${tokenPasajero}` } }, resRolInvalido, () => {});
  assert.equal(resRolInvalido.statusCode, 403);

  // 3. Token valido de conductor -> next() y asigna req.conductor
  const tokenConductor = jwt.sign({ role: 'conductor', microId: 'micro-24', rutaId: 10 }, process.env.JWT_SECRET);
  const reqValido = { headers: { authorization: `Bearer ${tokenConductor}` } };
  let llamoNext = false;
  authMiddleware(reqValido, createRes(), () => { llamoNext = true; });
  assert.equal(llamoNext, true);
  assert.deepEqual(reqValido.conductor, { microId: 'micro-24', rutaId: 10 });

  // 4. Token emitido por Laravel (claims en snake_case: rol, micro_id, ruta_id) -> next()
  const tokenLaravel = jwt.sign({ sub: '3', rol: 'conductor', micro_id: 1, ruta_id: 1 }, process.env.JWT_SECRET);
  const reqLaravel = { headers: { authorization: `Bearer ${tokenLaravel}` } };
  let llamoNextLaravel = false;
  authMiddleware(reqLaravel, createRes(), () => { llamoNextLaravel = true; });
  assert.equal(llamoNextLaravel, true);
  assert.deepEqual(reqLaravel.conductor, { microId: 1, rutaId: 1 });
});

test('PRB-034 y PRB-038 (T071): gpsRouter valida coordenadas/velocidad y emite micro:position y micro:stopped', () => {
  const positionLayer = gpsRouter.stack.find(l => l.route?.path === '/position');
  const stopLayer = gpsRouter.stack.find(l => l.route?.path === '/stop');
  const postPosition = positionLayer.route.stack[0].handle;
  const postStop = stopLayer.route.stack[0].handle;

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
