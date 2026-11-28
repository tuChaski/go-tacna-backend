import { Router } from 'express';
import { updatePosition, markStopped, getMicro, startTrip } from '../services/fleetState.js';
import { toMicroDto } from '../services/microDto.js';

const router = Router();

function validarGPS({ lat, lng, speed }) {
  if (!Number.isFinite(lat) || !Number.isFinite(lng) || !Number.isFinite(speed)) {
    return 'Datos GPS inválidos: lat, lng y speed deben ser números';
  }
  if (lat < -90 || lat > 90) return 'Latitud fuera de rango [-90, 90]';
  if (lng < -180 || lng > 180) return 'Longitud fuera de rango [-180, 180]';
  if (speed < 0) return 'Velocidad no puede ser negativa';
  return null;
}

router.post('/start', (req, res) => {
  startTrip(req.conductor.microId);
  res.json({ ok: true });
});

router.post('/position', (req, res) => {
  const error = validarGPS(req.body);
  if (error) return res.status(400).json({ error });

  const { microId, rutaId, empresaId } = req.conductor;
  const { lat, lng, speed } = req.body;

  if (!updatePosition(microId, { lat, lng, speed, rutaId, empresaId })) {
    return res.status(409).json({ error: 'El recorrido está finalizado.' });
  }

  const micro = getMicro(microId);
  req.io.to(`ruta:${rutaId}`).emit('micro:position', toMicroDto(micro));
  if (empresaId) req.io.of('/admin').to(`empresa:${empresaId}`).emit('micro:position', toMicroDto(micro));

  res.status(202).json({ ok: true });
});

router.post('/stop', (req, res) => {
  const { microId, rutaId, empresaId } = req.conductor;

  markStopped(microId);
  req.io.to(`ruta:${rutaId}`).emit('micro:stopped', { microId });
  if (empresaId) req.io.of('/admin').to(`empresa:${empresaId}`).emit('micro:stopped', { microId });

  res.json({ ok: true });
});

export default router;
