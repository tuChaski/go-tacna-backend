import express from 'express';
import {createServer} from 'http';
import {Server} from 'socket.io';
import {env} from './config/env.js';

const app = express();
const httpServer = createServer(app);
const io = new Server(httpServer,{
    cors:{
        origin: env.corsOrigin,
        methods: ["GET", "POST"]
    }
})

app.get("/", (req,res)=>{
    res.send("Hello world");
} )

httpServer.listen(env.port,()=>{
    console.log(`Realtime server on port ${env.port}`);
})

