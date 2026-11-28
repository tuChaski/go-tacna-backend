# Servicio Node de tiempo real

El arranque recomendado del proyecto completo está en el [README de infraestructura](../../rutas-en-tiempo-real-infraestructura/README.md). Compose monta este directorio en el contenedor `websocket`, configura `CORE_API_URL=http://backend:8000` y publica Socket.IO a través de Nginx en `http://localhost:8001/socket.io/`. El puerto 3001 queda dentro de Docker.

## Qué hace

- Recibe `start`, `position` y `stop` desde Laravel por `/tracking/*` interno con credenciales breves firmadas. Un conductor que llama directamente a Node debe pasar la revalidación de asignación vigente en Laravel.
- Emite posiciones y cambios de estado a la sala pública `ruta:{id}` y a la sala administrativa `empresa:{id}` del namespace `/admin`.
- Valida al administrador con Laravel al conectar y cada 5 s; desconecta cuando pierde acceso.
- Publica únicamente el DTO `Micro` contratado. Las muestras de velocidad permanecen en memoria para ETA.
- Antes de aceptar sockets, pagina `GET /api/v1/micros/activos` de Laravel y recupera micros `en_recorrido` con último GPS. Una lectura vieja (>120 s por defecto) aparece `desconectado`. Si Laravel no responde, reintenta cada 5 s.

El contrato de eventos está en [eventos-websocket.md](../../rutas-en-tiempo-real-docs/contratos/eventos-websocket.md).

## Ejecución directa, sin Docker

Desde `realtime/`:

```bash
cp .env.example .env
npm ci
npm start
```

En `.env`, configura `JWT_SECRET` con el mismo valor de Laravel y `CORE_API_URL` con la URL alcanzable del Core. Node necesita que Laravel responda al arrancar para recuperar el estado; los mensajes `Posiciones GPS recuperadas: N` y `Realtime server on port 3001` confirman que terminó. El healthcheck directo está en `http://127.0.0.1:3001/health`. Para un teléfono físico sin proxy usa la IP local del equipo servidor, nunca `127.0.0.1` del teléfono.

## Pruebas

```bash
npm test
```

La prueba `live-compose.test.js` se omite en la suite normal: requiere Docker, servicios activos y cuentas de prueba. La integración local con Laravel simulado sí se ejecuta normalmente y verifica HTTP, Socket.IO, listas iniciales, filtros por empresa y DTO.
