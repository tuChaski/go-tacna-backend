import 'dotenv/config';

export const env = {
    port: Number(process.env.PORT) || 3001,
    jwtSecret: process.env.JWT_SECRET,
    corsOrigin: process.env.CORS_ORIGIN || "*",
    offlineAfterMs: Number(process.env.OFFLINE_AFTER_MS) || 120_000,
}

if(!env.jwtSecret) {
    throw new Error("JWT_SECRET es requerido en .env");
}