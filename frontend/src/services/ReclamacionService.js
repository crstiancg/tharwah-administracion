import { api } from '@/boot/axios'

/**
 * Libro de Reclamaciones: las hojas llegan desde la tienda web y acá sólo se
 * consultan y se responden (no se editan ni se borran).
 */
class ReclamacionService {
  static async getData (config) {
    return (await api.get('api/reclamaciones', config)).data
  }

  static async get (id) {
    return (await api.get(`api/reclamaciones/${id}`)).data
  }

  // Devuelve la hoja ya respondida (el backend envía la respuesta al correo).
  static async responder (id, respuesta) {
    return (await api.post(`api/reclamaciones/${id}/respuesta`, { reclamacion: { respuesta } })).data
  }
}

export default ReclamacionService
