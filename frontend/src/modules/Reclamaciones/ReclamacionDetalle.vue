<template>
  <div
    v-if="hoja"
    class="rec-detalle"
  >
    <header class="rec-detalle__cabecera">
      <div>
        <div class="rec-detalle__codigo text-mono">
          {{ hoja.codigo }}
        </div>
        <div class="rec-detalle__meta">
          {{ TIPOS[hoja.tipo] }} · presentada el {{ formatearFechaHora(hoja.presentado_at) }}
        </div>
      </div>
      <AppChip
        v-bind="estado"
      />
    </header>

    <!-- La misma vista que se imprime. -->
    <div class="rec-detalle__papel">
      <HojaReclamacion :hoja="hoja" />
    </div>

    <!-- Responder: una sola vez; el backend la envía al correo del cliente. -->
    <section
      v-if="puedeResponder"
      class="rec-detalle__respuesta"
    >
      <label
        for="rec-respuesta"
        class="rec-detalle__titulo"
      >
        Respuesta
      </label>
      <p class="rec-detalle__ayuda">
        {{ hoja.consumidor.email
          ? `Se envía a ${hoja.consumidor.email} al guardarla.`
          : 'El cliente no dejó correo: comunicale la respuesta a su domicilio o teléfono.' }}
      </p>
      <q-input
        v-model="respuesta"
        type="textarea"
        outlined
        autogrow
        :input-style="{ minHeight: '120px' }"
        maxlength="5000"
        placeholder="Qué se hizo o qué se le ofrece al cliente."
        for="rec-respuesta"
        :error="Boolean(error)"
        :error-message="error"
      />
    </section>
  </div>

  <div
    v-else
    class="rec-detalle__cargando"
  >
    <q-spinner size="24px" />
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import AppChip from '@/components/AppChip.vue'
import ReclamacionService from '@/services/ReclamacionService'
import { useUserStore } from '@/stores/user-store'
import { formatearFechaHora } from '@/utils/fechas'
import HojaReclamacion from './HojaReclamacion.vue'
import { ESTADOS, TIPOS, plazo } from './constantes'

const props = defineProps({
  id: {
    type: Number,
    required: true
  }
})

const userStore = useUserStore()
const hoja = ref(null)
const respuesta = ref('')
const error = ref('')

onMounted(async () => {
  hoja.value = await ReclamacionService.get(props.id)
})

const pendiente = computed(() => hoja.value?.estado === 'pendiente')
const puedeResponder = computed(() => pendiente.value && userStore.hasPermission('reclamaciones.responder'))
const estado = computed(() => pendiente.value
  ? plazo(hoja.value.fecha_limite)
  : ESTADOS.respondida)

// La acción la dispara la lista (dueña del diálogo); acá se refresca.
function actualizar (nueva) {
  hoja.value = nueva
  respuesta.value = ''
  error.value = ''
}

defineExpose({ hoja, respuesta, error, puedeResponder, actualizar })
</script>

<style lang="scss" scoped>
.rec-detalle {
  display: flex;
  flex-direction: column;
  gap: 14px;
}

.rec-detalle__cabecera {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 12px;
}

.rec-detalle__codigo {
  font-size: 18px;
  font-weight: 700;
  color: var(--app-ink);
}

.rec-detalle__meta,
.rec-detalle__ayuda {
  margin: 0;
  font-size: 12px;
  color: var(--app-ink-2);
}

// Vista previa del papel: siempre blanca, también en tema oscuro.
.rec-detalle__papel {
  overflow-x: auto;
  padding: 20px;
  border: 1px solid var(--app-border-subtle);
  border-radius: 8px;
  background: #FFFFFF;
}

.rec-detalle__respuesta {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.rec-detalle__titulo {
  font-size: 14px;
  font-weight: 600;
  color: var(--app-ink);
}

.rec-detalle__cargando {
  display: flex;
  justify-content: center;
  padding: 32px;
}
</style>
