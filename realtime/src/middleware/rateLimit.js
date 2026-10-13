import rateLimit from 'express-rate-limit';
import { ipKeyGenerator } from 'express-rate-limit';

export const trackingRateLimit = rateLimit({
  windowMs: 60_000,
  max: 60,
  keyGenerator: (req) => req.conductor?.microId ?? ipKeyGenerator(req),
  message: { error: 'Demasiadas peticiones, límite 60/min' },
  standardHeaders: true,
  legacyHeaders: false
});