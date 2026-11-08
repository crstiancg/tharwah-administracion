/**
 * Libro de Reclamaciones. Las claves son las del backend (App\Models\Reclamacion).
 */
export const ESTADOS = {
  pendiente: { label: 'Por responder', status: 'warning' },
  respondida: { label: 'Respondida', status: 'positive' }
}

export const TIPOS = {
  reclamo: 'Reclamo',
  queja: 'Queja'
}

export const DOCUMENTOS = {
  DNI: 'DNI',
  CE: 'Carné de extranjería',
  RUC: 'RUC',
  PASAPORTE: 'Pasaporte'
}

export const CANALES = {
  presencial: 'En tienda',
  virtual: 'Por internet o WhatsApp',
  telefonico: 'Por teléfono',
  no_compre: 'No compró',
  otro: 'Otro'
}

export const COMPROBANTES = {
  boleta: 'Boleta',
  factura: 'Factura',
  pedido: 'N.° de pedido',
  orden_compra: 'Orden de compra',
  transaccion: 'N.° de transacción',
  otro: 'Otro'
}

/** "2026-11-30" → "30/11/2026". */
export function fechaCorta (iso) {
  return iso ? iso.slice(0, 10).split('-').reverse().join('/') : ''
}

/** Hoy en la zona local (no UTC), YYYY-MM-DD. */
export function hoyLocal () {
  const d = new Date()
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`
}

/** Días de calendario hasta la fecha límite (negativo: ya venció). */
export function diasParaVencer (fechaLimite, hoy = hoyLocal()) {
  const dia = (iso) => Date.UTC(+iso.slice(0, 4), +iso.slice(5, 7) - 1, +iso.slice(8, 10))
  return Math.round((dia(fechaLimite) - dia(hoy)) / 86_400_000)
}

/**
 * Cómo se muestra el plazo de una hoja por responder: en rojo cuando faltan
 * 3 días o menos (el plazo legal de 15 días hábiles es improrrogable).
 */
export function plazo (fechaLimite, hoy = hoyLocal()) {
  const dias = diasParaVencer(fechaLimite, hoy)
  const label = dias > 1
    ? `Vence en ${dias} días`
    : dias === 1
      ? 'Vence mañana'
      : dias === 0
        ? 'Vence hoy'
        : `Venció hace ${-dias} ${dias === -1 ? 'día' : 'días'}`
  return { label, status: dias <= 3 ? 'negative' : 'warning' }
}
