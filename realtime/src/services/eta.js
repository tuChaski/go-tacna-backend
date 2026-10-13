import { getMicro } from './fleetState.js';

const R = 6371000;
const toRad = deg => deg * Math.PI / 180;

export function haversine(lat1, lng1, lat2, lng2) {
  const dLat = toRad(lat2 - lat1);
  const dLng = toRad(lng2 - lng1);
  const a = Math.sin(dLat/2)**2 + 
            Math.cos(toRad(lat1)) * Math.cos(toRad(lat2)) * 
            Math.sin(dLng/2)**2;
  const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
  return R * c;
}

export function calculateETA(microId, latPasajero, lngPasajero) {
  const micro = getMicro(microId);
  if (!micro) throw new Error('Micro no encontrado');

  const distancia = haversine(latPasajero, lngPasajero, micro.lat, micro.lng);

  const velPromedio = micro.speedSamples.length > 0
    ? micro.speedSamples.reduce((a, b) => a + b, 0) / micro.speedSamples.length
    : 2;

  const velocidad = Math.max(velPromedio, 2);
  const etaSeconds = Math.round(distancia / velocidad);

  return { microId, etaSeconds, estimated: true };
}