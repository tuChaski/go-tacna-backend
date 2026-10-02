```mermaid
architecture-beta
    group api_gateway(cloud)[API Gateway & Seguridad]
    group backend_services(server)[Servicios Backend]
    group database_layer(database)[Almacenamiento]

    %% Componentes del Cliente
    node usuario(user)[Usuario / Cliente Web]

    %% Componentes del API Gateway
    node gateway(internet)[Nginx / Gateway] in api_gateway
    node auth(lock)[Autenticación JWT] in api_gateway

    %% Componentes del Backend
    node api_pedidos(cog)[API Pedidos] in backend_services
    node api_usuarios(users)[API Usuarios] in backend_services

    %% Componentes de Base de Datos
    node db_principal(database)[PostgreSQL] in database_layer
    node db_cache(clock)[Redis Cache] in database_layer

    %% Flujos y Conexiones del Sistema
    usuario:right -- Petición HTTPS --> gateway
    gateway:down --> auth
    auth:right -- Token Válido --> api_pedidos
    auth:right --> api_usuarios
    
    api_pedidos:down --> db_principal
    api_usuarios:down --> db_principal
    api_pedidos:right --> db_cache
```
