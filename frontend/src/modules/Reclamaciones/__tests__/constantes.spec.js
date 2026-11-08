import { describe, it, expect } from 'vitest'
import { diasParaVencer, fechaCorta, plazo } from '@/modules/Reclamaciones/constantes'

describe('plazo de respuesta', () => {
  it('cuenta los días hasta la fecha límite', () => {
    expect(diasParaVencer('2026-11-30', '2026-11-09')).toBe(21)
    expect(diasParaVencer('2026-11-30', '2026-12-02')).toBe(-2)
  })

  it('en rojo los últimos 3 días y al vencer', () => {
    expect(plazo('2026-11-30', '2026-11-09')).toEqual({ label: 'Vence en 21 días', status: 'warning' })
    expect(plazo('2026-11-30', '2026-11-29')).toEqual({ label: 'Vence mañana', status: 'negative' })
    expect(plazo('2026-11-30', '2026-11-30')).toEqual({ label: 'Vence hoy', status: 'negative' })
    expect(plazo('2026-11-30', '2026-12-01')).toEqual({ label: 'Venció hace 1 día', status: 'negative' })
  })

  it('formatea la fecha corta', () => {
    expect(fechaCorta('2026-11-30')).toBe('30/11/2026')
  })
})
