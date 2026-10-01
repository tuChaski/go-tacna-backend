# Rutas en Tiempo Real — Backend

Servidor del proyecto: **API REST** (`api/`) y **servidor de tiempo real** (`realtime/`).

> **Pregunta que responde el sistema:** "¿Dónde está mi micro y cuánto falta para que llegue?"

---

## ⚠️ Estado actual

**Este repositorio está vacío.** Todo el código de backend vive hoy en el monorepo `rutas-en-tiempo-real-frontend/`:

| Componente | Ruta actual | Estado |
|---|---|---|
| `api/` | `rutas-en-tiempo-real-frontend/backend/` | esqueleto de Laravel 13, sin lógica de negocio |
| `realtime/` | `rutas-en-tiempo-real-frontend/realtime/` | **funcional**, con pruebas |

Este README describe el **diseño objetivo** de cada componente y, en `realtime/`, también el comportamiento exacto que ya está implementado. Al migrar el código, este README pasa a describir la estructura real.

---

## 1. Qué vive en este repositorio

```
api/                     API REST — datos, cuentas y reglas de negocio
  app/
    Http/Controllers/    Endpoints
    Models/              Empresa, Ruta, Micro, Conductor, Pasajero, Posicion
  database/migrations/   Esquema de PostgreSQL + PostGIS
  routes/api.php         Rutas de la API (prefijo /api)
  tests/                 PHPUnit

realtime/                Servidor de posiciones GPS en vivo
  src/
    config/env.js        Lectura y validación de variables de entorno
    middleware/auth.js   Verificación del JWT del conductor
    middleware/rateLimit.js  Límite de peticiones por micro
    handlers/gps.js      Endpoints de posición y fin de recorrido
    handlers/passenger.js Eventos Socket.io del pasajero
    services/fleetState.js  Memoria de las unidades en movimiento
    services/eta.js      Distancia haversine y cálculo del ETA
  tests/                 Pruebas con node:test
```

---

## 2. Por qué el tiempo real está separado de la API

Laravel atiende **cada petición desde cero**: recibe, procesa y responde. El tiempo real necesita algo distinto:

- **recordar** la última posición de cada micro mientras la conexión está abierta,
- **mantener** conexiones vivas con todos los pasajeros,
- **empujar** posiciones nuevas sin que el cliente pregunte.

Meter eso en Laravel significaría estado en sesión y recargas constantes. Por eso `realtime/` es un proceso Node.js aparte, y ambos comparten **el mismo `JWT_SECRET`** para validar al conductor con el mismo token.

---

## 3. `api/` — API REST (Laravel)

### Stack

| Tecnología | Versión | Propósito |
|---|---|---|
| **PHP** | `^8.3` | Lenguaje del servidor |
| **Laravel** | `^13.17` | Framework: login, CRUD, validación |
| **PostgreSQL** | 16 | Base de datos |
| **PostGIS** | 3.4 | Guardar el trazado de rutas y calcular distancias |
| **php-open-source-saver/jwt-auth** | — | Login: entrega un token que prueba quién eres |
| **Laravel Magellan** | — | Usar datos de PostGIS desde Laravel |
| **L5-Swagger** | — | Documentación navegable de la API |
| **PHPUnit** | `^12.5` | Pruebas automáticas |
| **Pint** | `^1.27` | Estilo de código |
| **Pail** | `^1.2.5` | Logs en tiempo real |

### Puesta en marcha

```bash
composer install
cp .env.example .env
php artisan key:generate

# Conexión a PostgreSQL + PostGIS
# En .env:
#   DB_CONNECTION=pgsql
#   DB_HOST=127.0.0.1
#   DB_PORT=5432
#   DB_DATABASE=rutatacna
#   DB_USERNAME=rutatacna
#   DB_PASSWORD=<tu clave>

php artisan migrate
php artisan serve          # http://localhost:8000
php artisan test
```

Levantar solo la base de datos, desde el monorepo:

```bash
docker compose -f docker/docker-compose.yml up -d db
```

### Recursos que administra

El administrador da de alta y mantiene:

| Recurso | Para qué |
|---|---|
| **Empresa** | Empresa de transporte (solo Tacna por ahora) |
| **Ruta** | Trayecto con su trazado geospatial (PostGIS) |
| **Parada** | Punto de espera de una ruta |
| **Micro** | Unidad que reporta su posición |
| **Conductor** | Persona autorizada a transmitir GPS |
| **Pasajero** | Persona que consulta posiciones |

### Claims del token

El token JWT que entrega el login debe incluir:

```json
{
  "role": "conductor",
  "micro_id": 12,
  "ruta_id": 3,
  "exp": 1767225600
}
```

`realtime/` **rechaza** el token si falta cualquiera de los tres primeros campos (`realtime/src/middleware/auth.js:11`).

---

## 4. `realtime/` — Tiempo real (Node.js)

### Stack

| Tecnología | Versión | Propósito |
|---|---|---|
| **Node.js** | `>=24` | Entorno del servidor |
| **Express** | `^5.1.0` | Recibe el GPS del conductor |
| **Socket.io** | `^4.8.1` | Conexiones vivas con los pasajeros |
| **jsonwebtoken** | `^9.0.2` | Verifica que quien envía GPS es un conductor autorizado |
| **express-rate-limit** | `^8.1.0` | Evita que alguien sature el servidor |
| **cors** | `^2.8.5` | Permite el origen de la web |

### Comandos

```bash
cd realtime
cp .env.example .env      # define JWT_SECRET (mismo valor que api/.env)
npm ci
npm start                 # node src/server.js
npm run dev               # node --watch --env-file=.env
npm test                  # node --test
```

### Variables de entorno

| Variable | Por defecto | Para qué |
|---|---|---|
| `PORT` | `3001` | Puerto de escucha |
| `JWT_SECRET` | — | **obligatoria**: el proceso lanza error si falta |
| `CORS_ORIGIN` | `*` | Origen permitido del navegador |
| `OFFLINE_AFTER_MS` | `120000` | Tras 2 min sin señal, el micro pasa a `sin_conexion` |

### API HTTP

| Método | Ruta | Auth | Respuesta |
|---|---|---|---|
| `GET` | `/health` | no | `{ "status": "ok" }` |
| `POST` | `/tracking/position` | conductor | `202 { "ok": true }` |
| `POST` | `/tracking/stop` | conductor | `200 { "ok": true }` |

`POST /tracking/position` acepta:

```json
{ "lat": -18.0146, "lng": -70.2536, "speed": 8.4 }
```

Valida que las coordenadas estén dentro de rango y la velocidad no sea negativa; si no, devuelve `400 "Datos GPS inválidos"`.

Límite: **60 peticiones por minuto por micro** (`middleware/rateLimit.js`). El conductor envía cada 3-5 s, así que queda margen.

### Eventos WebSocket

| Evento | Dirección | Datos |
|---|---|---|
| `route:join` | cliente → servidor | `rutaId` → responde con las unidades de esa ruta |
| `route:leave` | cliente → servidor | `rutaId` |
| `eta:request` | cliente → servidor | `{ microId, lat, lng }` |
| `micro:position` | servidor → cliente | `{ microId, rutaId, lat, lng, speed, status, lastSeen }` |
| `micro:offline` | servidor → cliente | `{ microId, lastSeen }` |
| `micro:stopped` | servidor → cliente | `{ microId }` |

Los pasajeros se agrupan en **salas** (`ruta:{rutaId}`): cada uno solo recibe posiciones de su ruta.

`services/fleetState.js:2` conserva las **60 últimas muestras de velocidad** de cada micro en memoria, y `server.js:27` revisa cada 15 s cuáles llevan demasiado tiempo sin señal para marcarlos `sin_conexion`.

> El estado vive **solo en memoria**. Si el proceso se reinicia, se pierde el mapa de posiciones. Es aceptable en el MVP porque las posiciones no se consultan como histórico.

### Cálculo del ETA

`services/eta.js:19`:

```js
segundos = distancia_metros / max(velocidad_promedio, 2)
```

- **haversine** para la distancia en línea recta entre el pasajero y el micro.
- **velocidad promedio** de las últimas 60 muestras, no la instantánea: evita que un micro detenido genere tiempos absurdos.
- **mínimo de `2 m/s`** como red de seguridad.
- Siempre se marca `estimated: true`. **No considera tráfico ni recorridos.**

---

## 5. Contrato con los demás repositorios

Este repo **no** define los contratos; los consume.

- **Contrato de la API** → `rutas-en-tiempo-real-docs/docs/openapi.yaml`
- **Eventos WebSocket y claims del JWT** → `rutas-en-tiempo-real-docs`
- **Proxy que expone `/api/` y `/socket.io/`** → `rutas-en-tiempo-real-infraestructura`

Si un contrato cambia, se cambia primero en `docs/` y después se ajusta aquí.

---

## 6. Problemas conocidos

1. **`api/` es el esqueleto de Laravel**: solo el modelo `User`, tres migraciones de fábrica y `routes/web.php`. No hay `routes/api.php`, ni controladores, ni los paquetes de JWT, Magellan o Swagger.
2. **Falta el `Dockerfile` de `api/`**, así que el servicio `backend` no se puede construir.
3. **`api/.env.example` sigue con SQLite**; hay que cambiarlo a `pgsql` para usar PostGIS.
4. **La documentación OpenAPI es un borrador**: solo declara `/health`, `/routes` y `/buses/{busId}/location`.

---

## 7. Seguridad

- **`JWT_SECRET` idéntico** en `api/` y `realtime/`. Se configura como secreto en CI, nunca en el código.
- **Nunca se suben archivos `.env`.** Cada componente incluye su `.env.example`.
- Solo los conductores con `role`, `micro_id` y `ruta_id` pueden transmitir GPS.
- El límite por micro evita que un celular mal configurado inunde el servidor.

---

## 8. Equipo

| Integrante | Rol prioritario |
|---|---|
| Royfrankly Navarro | Coordinación general, Backend y DevOps |
| David Montador | Arquitectura de software y módulo GPS en tiempo real |
| Alex Huaracha | Diseño UI/UX y desarrollo Frontend/Móvil |
| Edison Catari | Calidad (QA), pruebas y automatización |

---

## 9. Enlaces

| Repositorio | Propósito |
|---|---|
| `rutas-en-tiempo-real-backend` | Este repositorio. API y tiempo real. |
| `rutas-en-tiempo-real-frontend` | Web pública y panel de administración. |
| `rutas-en-tiempo-real-movil` | App Android (Pasajero y Modo Conductor). |
| `rutas-en-tiempo-real-infraestructura` | Docker, k3s, Traefik y despliegue. |
| `rutas-en-tiempo-real-docs` | Informe, requisitos, contratos y cronograma. |

Tablero de tareas: [Planificación rutas-en-tiempo-real](https://github.com/orgs/tuChaski/projects/1)