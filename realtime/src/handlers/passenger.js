import { getRuta } from '../services/fleetState.js';
import { calculateETA } from '../services/eta.js';

export function passengerHandler(socket, io) {
  socket.on('route:join', (rutaId) => {
    if (!rutaId || typeof rutaId !== 'number') {
      socket.emit('error', { message: 'rutaId inválido' });
      return;
    }
    socket.join(`ruta:${rutaId}`);
    const micros = getRuta(rutaId);
    socket.emit('route:joined', { rutaId, micros });
  });

  socket.on('route:leave', (rutaId) => {
    if (!rutaId || typeof rutaId !== 'number') return;
    socket.leave(`ruta:${rutaId}`);
  });

  socket.on('eta:request', ({ microId, lat, lng }, callback) => {
    if (!microId || typeof lat !== 'number' || typeof lng !== 'number') {
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