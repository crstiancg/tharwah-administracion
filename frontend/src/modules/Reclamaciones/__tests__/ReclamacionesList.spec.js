import { describe, it, expect, vi, beforeEach } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'

const ReclamacionService = vi.hoisted(() => ({ getData: vi.fn(), get: vi.fn(), responder: vi.fn() }))
vi.mock('@/services/ReclamacionService', () => ({ default: ReclamacionService }))
vi.mock('@/boot/axios', () => ({ api: {} }))
vi.mock('vue-router', () => ({ useRoute: () => ({ query: {} }), useRouter: () => ({ replace: vi.fn() }) }))

const notify = vi.fn()
vi.mock('quasar', async (importOriginal) => ({ ...(await importOriginal()), useQuasar: () => ({ notify }) }))

import ReclamacionesList from '@/modules/Reclamaciones/ReclamacionesList.vue'
import { useUserStore } from '@/stores/user-store'

const hoja = (cambios = {}) => ({
  id: 1,
  codigo: '2026-000001',
  tipo: 'reclamo',
  presentado_at: '2026-11-09T15:00:00Z',
  fecha_limite: '2026-11-30',
  estado: 'pendiente',
  proveedor: { razon_social: 'GRUPO THARWAH S.A.C.', ruc: '20616230949', direccion: 'Juliaca' },
  consumidor: { nombres: 'Ana', apellidos: 'Quispe', tipo_documento: 'DNI', numero_documento: '45678912', email: 'ana@example.com' },
  bien: { tipo: 'producto', descripcion: 'Sikaflex 1A, cartucho gris' },
  detalle: 'Vino reseco.',
  pedido: 'Cambio.',
  respuesta: null,
  respondido_at: null,
  ...cambios
})

function montar (permisos = ['reclamaciones.index', 'reclamaciones.show', 'reclamaciones.responder']) {
  setActivePinia(createPinia())
  useUserStore().permisos = permisos
  return mount(ReclamacionesList, { global: { stubs: { ImpresionReclamacion: true } } })
}

beforeEach(() => {
  Object.values(ReclamacionService).forEach((fn) => fn.mockReset())
  notify.mockReset()
  ReclamacionService.getData.mockResolvedValue({ data: [hoja(), hoja({ id: 2, codigo: '2026-000002', estado: 'respondida' })], total: 2 })
})

describe('ReclamacionesList', () => {
  it('al montar pide la primera página y muestra hoja, cliente y estado', async () => {
    const wrapper = montar()
    await flushPromises()

    expect(ReclamacionService.getData).toHaveBeenCalledWith({ params: { rowsPerPage: 20, page: 1, search: '' } })
    expect(wrapper.text()).toContain('2026-000001')
    expect(wrapper.text()).toContain('Ana Quispe')
    expect(wrapper.text()).toContain('DNI 45678912')
    expect(wrapper.text()).toContain('Respondida')
  })
})
