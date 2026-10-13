import { Router } from 'express';
import { updatePosition, markStopped, getMicro } from '../services/fleetState.js';

const router = Router();

function validarGPS({ lat, lng, speed }) {
  if (typeof lat !== 'number' || typeof lng !== 'number' || typeof speed !== 'number') {
    return 'Datos GPS inválidos: lat, lng y speed deben ser números';
  }
  if (lat < -90 || lat > 90) return 'Latitud fuera de rango [-90, 90]';
  if (lng < -180 || lng > 180) return 'Longitud fuera de rango [-180, 180]';
  if (speed < 0) return 'Velocidad no puede ser negativa';
  return null;
}

router.post('/position', (req, res) => {
  const error = validarGPS(req.body);
  if (error) return res.status(400).json({ error });

  const { microId, rutaId } = req.conductor;
  const { lat, lng, speed } = req.body;

  updatePosition(microId, { lat, lng, speed, rutaId });

  const micro = getMicro(microId);
  req.io.to(`ruta:${rutaId}`).emit('micro:position', {
    microId,
    rutaId,
    lat: micro.lat,
    lng: micro.lng,
    speed: micro.speed,
    status: micro.status,
    lastSeen: micro.lastSeen
  });

  res.status(202).json({ ok: true });
});

router.post('/stop', (req, res) => {
  const { microId, rutaId } = req.conductor;

  markStopped(microId);
  req.io.to(`ruta:${rutaId}`).emit('micro:stopped', { microId });

  res.json({ ok: true });
});

export default router;