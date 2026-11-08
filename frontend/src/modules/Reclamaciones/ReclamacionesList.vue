<template>
  <div class="app-list-page">
    <AppPageHeader
      title="Libro de reclamaciones"
      :subtitle="`${pagination.rowsNumber} hojas · se responden en un plazo de 15 días hábiles`"
    />

    <AppFilterBar
      v-model:search="search"
      search-placeholder="Buscar por N.° de hoja, nombre o documento"
      :has-active-filters="hayFiltros"
      :refreshing="loading"
      @clear="limpiarFiltros"
      @refresh="tableRef.actualizar()"
    >
      <AppFilterPill
        v-model="estadoFilter"
        label="Estado"
        :options="estadoOptions"
      />
      <AppFilterPill
        v-model="tipoFilter"
        label="Tipo"
        :options="tipoOptions"
      />
    </AppFilterBar>

    <AppTable
      ref="tableRef"
      v-model:pagination="pagination"
      :rows="rows"
      :columns="columns"
      :loading="loading"
      :filter="filtroTabla"
      no-data-label="No hay hojas de reclamación que coincidan."
      @request="onRequest"
    >
      <template #body-cell-codigo="props">
        <q-td :props="props">
          <button
            type="button"
            class="rec-codigo text-mono"
            @click="ver(props.row)"
          >
            {{ props.row.codigo }}
          </button>
          <div class="rec-meta">
            {{ formatearFechaHora(props.row.presentado_at) }}
          </div>
        </q-td>
      </template>

      <template #body-cell-consumidor="props">
        <q-td :props="props">
          <div>{{ props.row.consumidor.nombres }} {{ props.row.consumidor.apellidos }}</div>
          <div class="rec-meta">
            {{ props.row.consumidor.tipo_documento }} {{ props.row.consumidor.numero_documento }}
          </div>
        </q-td>
      </template>

      <template #body-cell-bien="props">
        <q-td :props="props">
          <div class="rec-bien">
            {{ props.row.bien.descripcion }}
          </div>
        </q-td>
      </template>

      <template #body-cell-estado="props">
        <q-td :props="props">
          <AppChip v-bind="estadoDe(props.row)" />
        </q-td>
      </template>

      <template #body-cell-acciones="props">
        <q-td
          :props="props"
          class="text-right"
        >
          <q-btn
            flat
            dense
            round
            icon="visibility"
            size="sm"
            color="grey-7"
            :aria-label="`Ver ${props.row.codigo}`"
            @click="ver(props.row)"
          />
        </q-td>
      </template>
    </AppTable>

    <!-- ── Detalle: la hoja y, si falta, su respuesta ── -->
    <AppDialog
      v-model="detalleDialog"
      title="Hoja de reclamación"
      size="lg"
    >
      <ReclamacionDetalle
        v-if="detalleId"
        :id="detalleId"
        ref="detalleRef"
        :key="detalleId"
      />

      <template #actions>
        <AppButton
          v-if="detalle"
          variant="tertiary"
          label="Imprimir / PDF"
          icon="print"
          @click="impresionRef.imprimir(detalle)"
        />
        <AppButton
          v-if="detalleRef?.puedeResponder"
          variant="primary"
          label="Enviar respuesta"
          icon="send"
          @click="pedirConfirmacion"
        />
      </template>
    </AppDialog>

    <!-- ── Confirmar: la respuesta no se puede cambiar después ── -->
    <AppDialog
      v-model="confirmDialog"
      title="Enviar respuesta"
    >
      <p class="confirm-text">
        La respuesta a la hoja <strong class="text-mono">{{ detalle?.codigo }}</strong> se guarda
        y se envía al cliente. No se puede cambiar después.
      </p>

      <template #actions>
        <AppButton
          variant="tertiary"
          label="Cancelar"
          @click="confirmDialog = false"
        />
        <AppButton
          variant="primary"
          label="Enviar"
          data-test="confirmar-respuesta"
          :loading="respondiendo"
          @click="responder"
        />
      </template>
    </AppDialog>

    <ImpresionReclamacion ref="impresionRef" />
  </div>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useQuasar } from 'quasar'
import { useRoute, useRouter } from 'vue-router'
import AppButton from '@/components/AppButton.vue'
import AppChip from '@/components/AppChip.vue'
import AppDialog from '@/components/AppDialog.vue'
import AppFilterBar from '@/components/AppFilterBar.vue'
import AppFilterPill from '@/components/AppFilterPill.vue'
import AppPageHeader from '@/components/AppPageHeader.vue'
import AppTable from '@/components/AppTable.vue'
import ReclamacionService from '@/services/ReclamacionService'
import { formatearFechaHora } from '@/utils/fechas'
import ImpresionReclamacion from './ImpresionReclamacion.vue'
import ReclamacionDetalle from './ReclamacionDetalle.vue'
import { ESTADOS, TIPOS, plazo } from './constantes'

const $q = useQuasar()
const route = useRoute()
const router = useRouter()

// Sin orden por columna: el backend ya pone las pendientes que vencen antes arriba.
const columns = [
  { name: 'codigo', label: 'Hoja', field: 'codigo', align: 'left' },
  { name: 'consumidor', label: 'Cliente', field: (row) => row.consumidor.apellidos, align: 'left' },
  { name: 'tipo', label: 'Tipo', field: (row) => TIPOS[row.tipo], align: 'left' },
  { name: 'bien', label: 'Producto o servicio', field: (row) => row.bien.descripcion, align: 'left' },
  { name: 'estado', label: 'Plazo', field: 'estado', align: 'left' },
  { name: 'acciones', label: '', field: 'id', align: 'right' }
]

// Pendiente: lo que falta del plazo (rojo los últimos 3 días).
function estadoDe (row) {
  return row.estado === 'pendiente' ? plazo(row.fecha_limite) : ESTADOS.respondida
}

// ── Filtros ──
const search = ref('')
const busqueda = ref('')
const estadoFilter = ref(null)
const tipoFilter = ref(null)

let searchTimer
watch(search, (value) => {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(() => { busqueda.value = value.trim() }, 400)
})

const estadoOptions = [
  { label: 'Todos', value: null },
  ...Object.entries(ESTADOS).map(([value, { label }]) => ({ label, value }))
]
const tipoOptions = [
  { label: 'Todos', value: null },
  ...Object.entries(TIPOS).map(([value, label]) => ({ label, value }))
]

const hayFiltros = computed(() => Boolean(search.value || estadoFilter.value || tipoFilter.value))

function limpiarFiltros () {
  search.value = ''
  estadoFilter.value = null
  tipoFilter.value = null
}

const filtroTabla = computed(() => JSON.stringify({ search: busqueda.value, estado: estadoFilter.value, tipo: tipoFilter.value }))

// ── Tabla (paginación en el servidor) ──
const tableRef = ref()
const rows = ref([])
const loading = ref(false)
const pagination = ref({ page: 1, rowsPerPage: 20, rowsNumber: 0 })

async function onRequest ({ pagination: requested }) {
  const { page, rowsPerPage } = requested
  loading.value = true

  try {
    const params = { rowsPerPage, page, search: busqueda.value }
    if (estadoFilter.value) params.estado = estadoFilter.value
    if (tipoFilter.value) params.tipo = tipoFilter.value

    const { data, total = 0 } = await ReclamacionService.getData({ params })
    rows.value = data
    pagination.value = { ...requested, rowsNumber: total }
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  tableRef.value.requestServerInteraction()
})

// ── Detalle ──
const detalleDialog = ref(false)
const detalleRef = ref()
const detalleId = ref(null)
const detalle = computed(() => detalleRef.value?.hoja ?? null)
const impresionRef = ref()

function ver (hoja) {
  detalleId.value = hoja.id
  detalleDialog.value = true
}

// ?ver=ID (desde la búsqueda global o la campana) abre esa hoja.
watch(() => route.query.ver, (id) => {
  if (!id) return
  ver({ id: Number(id) })
  router.replace({ query: { ...route.query, ver: undefined } })
}, { immediate: true })

// ── Responder ──
const confirmDialog = ref(false)
const respondiendo = ref(false)

function pedirConfirmacion () {
  const texto = detalleRef.value.respuesta.trim()
  if (texto.length < 10) {
    detalleRef.value.error = 'Escribí la respuesta (al menos 10 caracteres).'
    return
  }
  detalleRef.value.error = ''
  confirmDialog.value = true
}

async function responder () {
  respondiendo.value = true
  try {
    const respondida = await ReclamacionService.responder(detalle.value.id, detalleRef.value.respuesta.trim())
    confirmDialog.value = false
    detalleRef.value.actualizar(respondida)
    tableRef.value.requestServerInteraction()
    $q.notify({
      type: 'positive',
      message: respondida.consumidor.email
        ? `Respuesta enviada a ${respondida.consumidor.email}.`
        : 'Respuesta guardada. Comunicásela al cliente: no dejó correo.',
      position: 'top-right',
      timeout: 3500
    })
  } catch (error) {
    confirmDialog.value = false
    const { status, data } = error.response ?? {}
    if (status === 422) detalleRef.value.error = data?.errors?.['reclamacion.respuesta']?.[0] ?? data?.message
    if (status === 409 || status === 422) {
      $q.notify({ type: 'negative', message: data?.message, position: 'top-right', timeout: 4500 })
    }
  } finally {
    respondiendo.value = false
  }
}
</script>

<style lang="scss" scoped>
.rec-codigo {
  padding: 0;
  border: 0;
  background: none;
  font-weight: 600;
  color: $primary;
  cursor: pointer;

  &:hover {
    text-decoration: underline;
  }

  &:focus-visible {
    outline: 2px solid $primary;
    outline-offset: 2px;
  }
}

.rec-meta {
  font-size: 12px;
  color: var(--app-ink-2);
}

.rec-bien {
  max-width: 320px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.confirm-text {
  margin: 0;
  font-size: 14px;
  line-height: 1.5;
  color: var(--app-ink);
}
</style>
