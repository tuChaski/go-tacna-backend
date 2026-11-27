# Go Tacna — Backend

Este repositorio contiene tres servicios:

| Carpeta | Función | Puerto dentro de Docker |
| --- | --- | ---: |
| `gotacna-backend-core/` | API Laravel, PostgreSQL/PostGIS, rutas, micros y usuarios | 8000 |
| `gotacna-api-gateway/` | Reenvía `/api/*` al core | 8000 |
| `realtime/` | Socket.IO, posiciones en vivo y ETA | 3001 |

## Arranque recomendado con Docker

El archivo Compose está en el repositorio hermano `../rutas-en-tiempo-real-infraestructura`. Desde esa carpeta:

```bash
sudo systemctl start docker        # Manjaro, si el daemon está apagado
cd ../rutas-en-tiempo-real-infraestructura
cp .env.example .env               # solo la primera vez; conserva un .env existente
sudo docker compose up -d --build --wait
sudo docker compose ps
```

Docker instala las dependencias PHP y Node dentro de los contenedores, inicia PostgreSQL con PostGIS, ejecuta las migraciones del core y, si `SEED_DEMO_DATA=true`, carga datos de demostración. No necesitas ejecutar `composer install`, `php artisan serve` ni `npm start` en el host para este modo. Los `.env` locales de cada servicio no reemplazan el `.env` de infraestructura: Compose entrega su propia configuración a los contenedores.

Antes del primer arranque, sigue la [sección de claves del `.env` de infraestructura](../rutas-en-tiempo-real-infraestructura/README.md#configurar-las-claves-del-env). Allí se generan por separado `APP_KEY` y `GATEWAY_APP_KEY`, y se define un único `JWT_SECRET` compartido entre core y Socket.IO. Para Docker no hay que ejecutar `php artisan jwt:secret` en el gateway ni copiar los `.env.example` de estos servicios.

El punto de entrada es `http://localhost:8001`:

- `http://localhost:8001/api/v1/rutas` consulta la API.
- `http://localhost:8001/socket.io/?EIO=4&transport=polling` comprueba Socket.IO; la respuesta empieza por `0{`.

El puerto 8000 del core y el 3001 de Node quedan dentro de la red Docker. Por eso `/socket.io/` se consulta en el puerto **8001** cuando usas Compose. Para acceder desde un teléfono, sigue la sección de red del [README de infraestructura](../rutas-en-tiempo-real-infraestructura/README.md).

## Desarrollo y diagnóstico

Los cambios en este repositorio se montan dentro de los contenedores. El core y el gateway ejecutan Composer al iniciar; `realtime` ejecuta `npm ci`. Tras cambiar dependencias o configuración, reconstruye con `sudo docker compose up -d --build --wait` desde infraestructura.

```bash
sudo docker compose logs --tail=100 backend gateway websocket
sudo docker compose exec backend php artisan migrate:status
sudo docker compose down
```

Ejecuta estos comandos desde `rutas-en-tiempo-real-infraestructura`. `docker compose down` detiene los contenedores y conserva la base de datos en su volumen. Evita `down -v` si quieres conservar los datos.

Para trabajar **sin Docker**, prepara PostgreSQL 16 con PostGIS, PHP con `sodium` y `pdo_pgsql`, Composer y Node. Configura por separado los `.env` de `gotacna-backend-core`, `gotacna-api-gateway` y `realtime`; el core y realtime deben compartir `JWT_SECRET`. En ese modo inicia core en `:8000`, gateway en `:8001` y Node en `:3001`. El gateway Laravel por sí solo no sirve `/socket.io/`; el proxy de Compose sí lo hace.

## Estado funcional

La API gestiona rutas con **un trazado** `LineString` por ruta. El esquema actual no contiene paraderos ni trazados separados de ida y vuelta. El servicio `realtime` conserva posiciones y muestras de velocidad en memoria; `route:joined` y `micro:position` publican solo los campos del contrato `Micro`. El ETA es aproximado y no considera tráfico ni recorrido por calles.

Contratos: [documentación del proyecto](../rutas-en-tiempo-real-docs/contratos/).
