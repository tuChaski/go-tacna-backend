import jwt from 'jsonwebtoken';
import { env } from '../config/env.js';

export function authMiddleware(req, res, next) {
  const authHeader = req.headers.authorization;

  if (!authHeader?.startsWith('Bearer ')) {
    return res.status(401).json({ error: 'No se proporcionó un token de autenticación' });
  }

  const token = authHeader.slice(7);

  try {
    const payload = jwt.verify(token, env.jwtSecret);

    if (!payload.role || payload.role !== 'conductor' || !payload.microId || !payload.rutaId) {
      return res.status(403).json({ error: 'Token de autenticación inválido' });
    }

    req.conductor = {
      microId: payload.microId,
      rutaId: payload.rutaId
    };
    next();
  } catch (e) {
    return res.status(401).json({ error: 'Token inválido o expirado' });
  }
}