/**
 * Estados: label y status del AppChip nacen juntos.
 */
export const ESTADOS = {
  pendiente: { label: 'Pendiente', status: 'warning' },
  enviada: { label: 'Enviada al proveedor', status: 'info' },
  recibida: { label: 'Recibida', status: 'positive' },
  anulada: { label: 'Anulada', status: 'negative' }
}

export { fechaCorta, hoyLocal } from '@/modules/Cotizaciones/constantes'
