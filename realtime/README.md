# Servicio de tiempo real para desarrollo local

El gateway Laravel de `:8001` solo atiende `/api/`. Socket.IO se sirve desde
el proceso Node independiente. Para iniciarlo desde este directorio:

```bash
cp .env.example .env
# Configura JWT_SECRET con el mismo secreto que usa el backend core.
npm ci
npm start
```

`GET http://127.0.0.1:3001/health` debe responder `{"status":"ok"}`.
El cliente Socket.IO debe usar `http://<IP_DEL_EQUIPO>:3001`, con el path
predeterminado `/socket.io/`. Para un teléfono físico, usa la IP de la red
local del equipo servidor; `127.0.0.1` en el teléfono apunta al teléfono.
También debe permitirse el puerto 3001 en la red local.

Si se necesita una sola URL para API y Socket.IO, configura un proxy inverso.
[`nginx-local.conf`](nginx-local.conf) muestra un bloque `server` para Nginx:
expone ambos servicios en `:8080`, reenvía `/api/` al gateway en `:8001` y
`/socket.io/` a Node en `:3001`, con soporte para WebSocket. En ese caso el
cliente usa `http://<IP_DEL_EQUIPO>:8080` para ambos servicios.

El servidor conserva las últimas posiciones y muestras de velocidad en memoria.
`route:joined` y `micro:position` publican únicamente los campos del contrato
`Micro`; las muestras de velocidad se usan solo para calcular el ETA.
