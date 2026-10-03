import { api } from '@/boot/axios'

/**
 * Crear y editar no pasan por acá: los hace el form con useForm() de
 * Precognition. Los cambios de estado sí: no llevan datos.
 */
class CotizacionService {
  static async getData (config) {
    return (await api.get('api/cotizaciones', config)).data
  }

  static async get (id) {
    return (await api.get(`api/cotizaciones/${id}`)).data
  }

  // Devuelve la cotización ya convertida, con `pedido` { id, codigo }.
  static async convertir (id) {
    return (await api.post(`api/cotizaciones/${id}/convertir`)).data
  }

  static async rechazar (id) {
    return (await api.post(`api/cotizaciones/${id}/rechazar`)).data
  }
}

export default CotizacionService
