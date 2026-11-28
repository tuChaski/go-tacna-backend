const fleet = new Map();
const stoppedMicros = new Set();

export function startTrip(microId) {
  if (stoppedMicros.delete(microId)) {
    fleet.delete(microId);
  }
}

export function updatePosition(microId, { lat, lng, speed, rutaId, empresaId }) {
  if (stoppedMicros.has(microId)) return false;

  const now = Date.now();
  let micro = fleet.get(microId);

  if (!micro) {
    micro = {
      microId,
      rutaId,
      empresaId,
      lat,
      lng,
      speed,
      status: 'moviendo',
      lastSeen: now,
      speedSamples: []
    };
  } else {
    micro.lat = lat;
    micro.lng = lng;
    micro.speed = speed;
    micro.rutaId = rutaId;
    micro.empresaId = empresaId;
    micro.status = 'moviendo';
    micro.lastSeen = now;
  }

  micro.speedSamples.push(speed);
  if (micro.speedSamples.length > 60) {
    micro.speedSamples.shift();
  }

  fleet.set(microId, micro);
  return true;
}

export function getMicro(microId) {
  return fleet.get(microId);
}

export function getRuta(rutaId) {
  return [...fleet.values()].filter(m => m.rutaId === rutaId && m.status !== 'detenido');
}

export function getEmpresas(empresaIds) {
  const permitidas = new Set(empresaIds);
  return [...fleet.values()].filter(m => permitidas.has(m.empresaId) && m.status !== 'detenido');
}

export function restorePositions(micros, offlineAfterMs = 120_000, now = Date.now()) {
  let restored = 0;
  for (const item of micros) {
    const position = item.ultima_posicion;
    if (item.estado !== 'en_recorrido' || !position) continue;

    const microId = item.id;
    const rutaId = item.ruta_id;
    const empresaId = item.empresa_id;
    const lat = position.latitud;
    const lng = position.longitud;
    const speed = position.velocidad;
    const lastSeen = Date.parse(position.fecha_hora);
    if (![microId, rutaId, empresaId].every(id => Number.isSafeInteger(id) && id > 0)
      || !Number.isFinite(lat) || lat < -90 || lat > 90
      || !Number.isFinite(lng) || lng < -180 || lng > 180
      || !Number.isFinite(speed) || speed < 0
      || !Number.isFinite(lastSeen) || lastSeen > now + 60_000
      || fleet.has(microId) || stoppedMicros.has(microId)) continue;

    fleet.set(microId, {
      microId, rutaId, empresaId, lat, lng, speed,
      status: now - lastSeen > offlineAfterMs ? 'desconectado' : 'moviendo',
      lastSeen,
      speedSamples: [speed]
    });
    restored += 1;
  }
  return restored;
}

export function markStopped(microId) {
  stoppedMicros.add(microId);
  const micro = fleet.get(microId);
  if (micro) micro.status = 'detenido';
}

export function checkOffline(io, offlineAfterMs = 120_000, now = Date.now()) {
  for (const micro of fleet.values()) {
    if (micro.status === 'detenido') continue;
    const diff = now - micro.lastSeen;
    if (diff > offlineAfterMs && micro.status !== 'desconectado') {
      micro.status = 'desconectado';
      io.to(`ruta:${micro.rutaId}`).emit('micro:offline', {
        microId: micro.microId,
        lastSeen: micro.lastSeen
      });
      if (micro.empresaId) {
        io.of('/admin').to(`empresa:${micro.empresaId}`).emit('micro:offline', {
          microId: micro.microId,
          lastSeen: micro.lastSeen
        });
      }
    }
  }
}

export function startOfflineChecker(io, offlineAfterMs = 120_000) {
  return setInterval(() => checkOffline(io, offlineAfterMs), 15_000);
}
