import { env } from '../config/env.js';
import { restorePositions } from './fleetState.js';

export async function recoverFleet(fetchPage = fetch, now = Date.now()) {
  const micros = [];
  let lastPage = 1;

  for (let page = 1; page <= lastPage; page++) {
    const url = new URL(`${env.coreApiUrl.replace(/\/$/, '')}/api/v1/micros/activos`);
    url.searchParams.set('per_page', '100');
    url.searchParams.set('page', String(page));
    const response = await fetchPage(url, {
      headers: { accept: 'application/json' },
      signal: AbortSignal.timeout(2000)
    });
    if (!response.ok) throw new Error(`Laravel respondió ${response.status}`);

    const result = await response.json();
    if (!Array.isArray(result.data) || !Number.isSafeInteger(result.meta?.last_page)
      || result.meta.last_page < page) {
      throw new Error('Respuesta de micros activos inválida');
    }
    lastPage = result.meta.last_page;
    micros.push(...result.data);
  }

  return restorePositions(micros, env.offlineAfterMs, now);
}

export async function startFleetRecovery() {
  for (;;) {
    try {
      const count = await recoverFleet();
      console.log(`Posiciones GPS recuperadas: ${count}`);
      return;
    } catch (error) {
      console.warn(`No se pudo recuperar el GPS guardado: ${error.message}. Se reintentará en 5 segundos.`);
      await new Promise(resolve => setTimeout(resolve, 5000));
    }
  }
}
