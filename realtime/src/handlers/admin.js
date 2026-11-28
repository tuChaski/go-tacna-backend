import { env } from '../config/env.js';
import { getEmpresas } from '../services/fleetState.js';
import { toMicroDto } from '../services/microDto.js';

export async function verificarAccesoAdmin(token) {
  const response = await fetch(`${env.coreApiUrl.replace(/\/$/, '')}/api/v1/admin/tiempo-real/acceso`, {
    headers: { authorization: `Bearer ${token}`, accept: 'application/json' },
    signal: AbortSignal.timeout(2000)
  });
  if (response.status === 401 || response.status === 403) return null;
  if (!response.ok) throw new Error('No se pudo verificar el acceso administrativo');

  const ids = (await response.json()).data?.empresa_ids;
  if (!Array.isArray(ids) || ids.length === 0
    || !ids.every(id => Number.isSafeInteger(id) && id > 0)) {
    throw new Error('Respuesta de autorización inválida');
  }
  return [...new Set(ids)];
}

export function registerAdminNamespace(io, checkAccess = verificarAccesoAdmin, recheckMs = 5000) {
  const namespace = io.of('/admin');

  namespace.use(async (socket, next) => {
    const token = socket.handshake.auth?.token;
    if (typeof token !== 'string' || token.length === 0) {
      next(new Error('Se requiere sesión administrativa'));
      return;
    }
    try {
      const empresaIds = await checkAccess(token);
      if (!empresaIds) {
        next(new Error('Acceso administrativo denegado'));
        return;
      }
      socket.data.adminToken = token;
      socket.data.empresaIds = empresaIds;
      next();
    } catch {
      next(new Error('No se pudo verificar la sesión administrativa'));
    }
  });

  namespace.on('connection', socket => {
    const joinCompanies = empresaIds => {
      for (const id of empresaIds) socket.join(`empresa:${id}`);
      socket.emit('fleet:joined', {
        empresaIds,
        micros: getEmpresas(empresaIds).map(toMicroDto)
      });
    };

    joinCompanies(socket.data.empresaIds);

    let checking = false;
    const timer = setInterval(async () => {
      if (!socket.connected || checking) return;
      checking = true;
      try {
        const updatedIds = await checkAccess(socket.data.adminToken);
        if (!updatedIds) {
          socket.emit('admin:revoked', { message: 'La sesión administrativa ya no está vigente.' });
          socket.disconnect();
          return;
        }
        const previousIds = socket.data.empresaIds;
        if (previousIds.length !== updatedIds.length
          || previousIds.some(id => !updatedIds.includes(id))) {
          for (const id of previousIds) socket.leave(`empresa:${id}`);
          socket.data.empresaIds = updatedIds;
          joinCompanies(updatedIds);
        }
      } catch {
        socket.emit('admin:revoked', { message: 'No se pudo verificar la sesión administrativa.' });
        socket.disconnect();
      } finally {
        checking = false;
      }
    }, recheckMs);
    timer.unref?.();
    socket.on('disconnect', () => clearInterval(timer));
  });

  return namespace;
}
