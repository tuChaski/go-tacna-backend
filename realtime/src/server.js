import express from 'express';
import { createServer } from 'http';
import { Server } from 'socket.io';
import cors from 'cors';
import { env } from './config/env.js';
import gpsRouter from './handlers/gps.js';
import { passengerHandler } from './handlers/passenger.js';
import { authMiddleware } from './middleware/auth.js';
import { trackingRateLimit } from './middleware/rateLimit.js';
import { startOfflineChecker } from './services/fleetState.js';

const app = express();
const httpServer = createServer(app);
const io = new Server(httpServer, {
  cors: { origin: env.corsOrigin }
});

app.use(cors({ origin: env.corsOrigin }));
app.use(express.json());

app.get('/health', (req, res) => res.json({ status: 'ok' }));

app.use('/tracking', authMiddleware, trackingRateLimit, (req, res, next) => {
  req.io = io;
  next();
}, gpsRouter);

io.on('connection', (socket) => {
  passengerHandler(socket, io);
});

startOfflineChecker(io, env.offlineAfterMs);

httpServer.listen(env.port, () => {
  console.log(`Realtime server on port ${env.port}`);
});