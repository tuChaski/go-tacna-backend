const fleet = new Map();

export function updatePosition(microId, { lat, lng, speed, rutaId }) {
  const now = Date.now();
  let micro = fleet.get(microId);

  if (!micro) {
    micro = {
      microId,
      rutaId,
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
    micro.status = 'moviendo';
    micro.lastSeen = now;
  }

  micro.speedSamples.push(speed);
  if (micro.speedSamples.length > 60) {
    micro.speedSamples.shift();
  }

  fleet.set(microId, micro);
}

export function getMicro(microId) {
  return fleet.get(microId);
}

export function getRuta(rutaId) {
  return [...fleet.values()].filter(m => m.rutaId === rutaId);
}

export function markStopped(microId) {
  const micro = fleet.get(microId);
  if (micro) micro.status = 'detenido';
}

export function startOfflineChecker(io, offlineAfterMs = 120_000) {
  setInterval(() => {
    const now = Date.now();
    for (const micro of fleet.values()) {
      const diff = now - micro.lastSeen;
      if (diff > offlineAfterMs && micro.status !== 'desconectado') {
        micro.status = 'desconectado';
        io.to(`ruta:${micro.rutaId}`).emit('micro:offline', {
          microId: micro.microId,
          lastSeen: micro.lastSeen
        });
      }
    }
  }, 15_000);
}