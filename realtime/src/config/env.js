import 'dotenv/config';

export const env = {
    port: Number(process.env.PORT) || 3001,
    jwtSecret: process.env.JWT_SECRET,
    coreApiUrl: process.env.CORE_API_URL || 'http://127.0.0.1:8000',
    corsOrigin: process.env.CORS_ORIGIN || "*",
    offlineAfterMs: Number(process.env.OFFLINE_AFTER_MS) || 120_000,
    adminRecheckMs: Number(process.env.ADMIN_RECHECK_MS) || 5000,
}

if(!env.jwtSecret) {
    throw new Error("JWT_SECRET es requerido en .env");
}
