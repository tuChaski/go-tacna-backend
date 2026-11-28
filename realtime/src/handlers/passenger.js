import { getRuta } from '../services/fleetState.js';
import { calculateETA } from '../services/eta.js';
import { toMicroDto } from '../services/microDto.js';

export function createEventLimiter(max = 60, windowMs = 60_000, now = Date.now) {
  let windowStart = now();
  let count = 0;

  return () => {
    const current = now();
    if (current - windowStart >= windowMs) {
      windowStart = current;
      count = 0;
    }
    count += 1;
    return count <= max;
  };
}

export function passengerHandler(socket, io) {
  const allowEvent = createEventLimiter();

  socket.on('route:join', (rutaId) => {
    if (!allowEvent()) {
      socket.emit('error', { message: 'Demasiados eventos. Intenta nuevamente en un minuto.' });
      return;
    }
    if (!Number.isSafeInteger(rutaId) || rutaId <= 0) {
      socket.emit('error', { message: 'rutaId inválido' });
      return;
    }
    socket.join(`ruta:${rutaId}`);
    const micros = getRuta(rutaId).map(toMicroDto);
    socket.emit('route:joined', { rutaId, micros });
  });

  socket.on('route:leave', (rutaId) => {
    if (!allowEvent()) return;
    if (!Number.isSafeInteger(rutaId) || rutaId <= 0) return;
    socket.leave(`ruta:${rutaId}`);
  });

  socket.on('eta:request', (datos, callback) => {
    if (!allowEvent()) {
      const err = { error: 'Demasiados eventos. Intenta nuevamente en un minuto.' };
      if (callback) callback(err);
      else socket.emit('eta:response', err);
      return;
    }
    const { microId, lat, lng } = datos && typeof datos === 'object' ? datos : {};
    if (!Number.isSafeInteger(microId) || microId <= 0
      || !Number.isFinite(lat) || lat < -90 || lat > 90
      || !Number.isFinite(lng) || lng < -180 || lng > 180) {
      const err = { error: 'Parámetros inválidos: microId, lat, lng requeridos' };
      if (callback) callback(err);
      else socket.emit('eta:response', err);
      return;
    }

    try {
      const eta = calculateETA(microId, lat, lng);
      if (callback) callback(null, eta);
      else socket.emit('eta:response', eta);
    } catch (e) {
      const err = { error: e.message };
      if (callback) callback(err);
      else socket.emit('eta:response', err);
    }
  });
}
