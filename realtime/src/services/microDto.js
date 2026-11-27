// Los datos usados para calcular el ETA permanecen en memoria del servidor.
// Los eventos públicos solo exponen los campos definidos en el contrato Micro.
export function toMicroDto(micro) {
  return {
    microId: micro.microId,
    rutaId: micro.rutaId,
    lat: micro.lat,
    lng: micro.lng,
    speed: micro.speed,
    status: micro.status,
    lastSeen: micro.lastSeen
  };
}
