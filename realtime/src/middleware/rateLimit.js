import rateLimit from 'express-rate-limit';

export const trackingRateLimit = rateLimit({
  windowMs: 60_000,
  max: 60,
  keyGenerator: (req) => String(req.conductor.microId),
  message: { error: 'Demasiadas peticiones, límite 60/min' },
  standardHeaders: true,
  legacyHeaders: false
});
