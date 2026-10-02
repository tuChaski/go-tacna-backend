%%{init: {'theme': 'dark'}}%%
flowchart LR
    %% Definición de subgrafos (Capas)
    subgraph Capa_Seguridad [API Gateway & Seguridad]
        gateway[Nginx / Gateway]
        auth[Autenticación JWT]
    end

    subgraph Capa_Backend [Servicios Backend]
        api_pedidos[API Pedidos]
        api_usuarios[API Usuarios]
    end

    subgraph Capa_Datos [Almacenamiento]
        db_principal[(PostgreSQL)]
        db_cache[(Redis Cache)]
    end

    %% Componente Cliente sin estilos conflictivos
    usuario[Usuario / Cliente Web]

    %% Flujos y Conexiones
    usuario -- Petición HTTPS --> gateway
    gateway --> auth
    auth -- Token Válido --> api_pedidos
    auth -- Token Válido --> api_usuarios
    
    api_pedidos --> db_principal
    api_usuarios --> db_principal
    api_pedidos --> db_cache
