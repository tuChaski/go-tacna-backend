import jwt from 'jsonwebtoken';
import { env } from '../config/env.js';

export function createAuthMiddleware(checkAssignment = async token => {
  const response = await fetch(`${env.coreApiUrl.replace(/\/$/, '')}/api/v1/conductor/asignacion-actual`, {
    headers: { authorization: `Bearer ${token}`, accept: 'application/json' },
    signal: AbortSignal.timeout(2000)
  });
  if (response.status === 401 || response.status === 403 || response.status === 404) return null;
  if (!response.ok) throw new Error('No se pudo verificar la asignación');
  return (await response.json()).data;
}) {
  return async function authMiddleware(req, res, next) {
    const authHeader = req.headers.authorization;

    if (!authHeader?.startsWith('Bearer ')) {
      return res.status(401).json({ error: 'No se proporcionó un token de autenticación' });
    }

    const token = authHeader.slice(7);

    try {
      const payload = jwt.verify(token, env.jwtSecret);
      const role = payload.rol ?? payload.role;
      const microId = payload.micro_id ?? payload.microId;
      const rutaId = payload.ruta_id ?? payload.rutaId;

      if (role !== 'conductor' || !microId || !rutaId || !payload.sub) {
        return res.status(403).json({ error: 'Token de autenticación inválido' });
      }

      if (payload.realtime_bridge === true) {
        const issuedAt = Number(payload.realtime_bridge_at);
        const empresaId = Number(payload.empresa_id);
        if (payload.realtime_action !== req.path.slice(1)
          || !Number.isSafeInteger(issuedAt)
          || Math.abs(Math.floor(Date.now() / 1000) - issuedAt) > 10
          || !Number.isSafeInteger(empresaId) || empresaId <= 0) {
          return res.status(403).json({ error: 'Credencial interna inválida' });
        }
        req.conductor = {
          microId: Number(microId),
          rutaId: Number(rutaId),
          empresaId
        };
        next();
        return;
      }

      let assignment;
      try {
        assignment = await checkAssignment(token);
      } catch {
        return res.status(503).json({ error: 'No se pudo verificar la asignación del conductor' });
      }

      if (!assignment || Number(assignment.micro_id) !== Number(microId)
        || Number(assignment.ruta_id) !== Number(rutaId)
        || !Number.isSafeInteger(Number(assignment.empresa_id))
        || Number(assignment.empresa_id) <= 0) {
        return res.status(403).json({ error: 'La asignación del conductor ya no está vigente' });
      }

      req.conductor = {
        microId: Number(assignment.micro_id),
        rutaId: Number(assignment.ruta_id),
        empresaId: Number(assignment.empresa_id)
      };
      next();
    } catch {
      return res.status(401).json({ error: 'Token inválido o expirado' });
    }
  };
}

export const authMiddleware = createAuthMiddleware();
