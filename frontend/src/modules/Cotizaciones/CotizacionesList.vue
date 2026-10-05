<template>
  <div class="app-list-page">
    <AppPageHeader
      title="Cotizaciones"
      :subtitle="`${pagination.rowsNumber} cotizaciones`"
    >
      <template #actions>
        <AppButton
          v-if="userStore.hasPermission('cotizaciones.store')"
          variant="primary"
          label="Nueva cotización"
          icon="request_quote"
          @click="crear"
        />
      </template>
    </AppPageHeader>

    <AppFilterBar
      v-model:search="search"
      search-placeholder="Buscar por código, cliente o documento"
      :has-active-filters="hayFiltros"
      @clear="limpiarFiltros"
    >
      <AppFilterPill
        v-model="estadoFilter"
        label="Estado"
        :options="estadoOptions"
      />
    </AppFilterBar>

    <AppTable
      ref="tableRef"
      v-model:pagination="pagination"
      :rows="rows"
      :columns="columns"
      :loading="loading"
      :filter="filtroTabla"
      no-data-label="No hay cotizaciones que coincidan."
      @request="onRequest"
    >
      <template #body-cell-codigo="props">
        <q-td :props="props">
          <button
            type="button"
            class="cot-codigo text-mono"
            @click="ver(props.row)"
          >
            {{ props.row.codigo }}
          </button>
          <div class="cot-meta">
            {{ fechaCorta(props.row.fecha) }}
          </div>
        </q-td>
      </template>

      <template #body-cell-cliente="props">
        <q-td :props="props">
          <div>{{ props.row.cliente?.nombre }}</div>
          <div
            v-if="props.row.cliente?.numero_documento"
            class="cot-meta"
          >
            {{ props.row.cliente.tipo_documento }} {{ props.row.cliente.numero_documento }}
          </div>
        </q-td>
      </template>

      <template #body-cell-estado="props">
        <q-td :props="props">
          <AppChip
            :status="ESTADOS[props.row.estado].status"
            :label="ESTADOS[props.row.estado].label"
          />
          <div
            v-if="props.row.pedido"
            class="cot-meta text-mono"
          >
            → {{ props.row.pedido.codigo }}
          </div>
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
          <q-btn
            v-if="props.row.editable && userStore.hasPermission('cotizaciones.update')"
            flat
            dense
            round
            icon="edit"
            size="sm"
            color="grey-7"
            :aria-label="`Editar ${props.row.codigo}`"
            @click="editar(props.row)"
          />
        </q-td>
      </template>
    </AppTable>

    <!-- ── Crear / editar ── -->
    <AppDialog
      v-model="formDialog"
      :title="editId ? 'Editar cotización' : 'Nueva cotización'"
      size="lg"
      persistent
    >
      <CotizacionForm
        :id="editId"
        ref="formRef"
        :key="editId ?? `nueva-${aperturas}`"
        @save="guardada"
      />

      <template #actions>
        <AppButton
          variant="tertiary"
          label="Cancelar"
          @click="formDialog = false"
        />
        <AppButton
          variant="primary"
          label="Guardar"
          :loading="formRef?.form.processing"
          @click="formRef.submit()"
        />
      </template>
    </AppDialog>

    <!-- ── Detalle con acciones ── -->
    <AppDialog
      v-model="detalleDialog"
      title="Cotización"
      size="lg"
    >
      <CotizacionDetalle
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
          v-if="pendiente && userStore.hasPermission('cotizaciones.rechazar')"
          variant="tertiary"
          label="Rechazada"
          icon="thumb_down"
          :loading="accionando === 'rechazar'"
          @click="ejecutar('rechazar')"
        />
        <AppButton
          v-if="pendiente && userStore.hasPermission('cotizaciones.update')"
          variant="secondary"
          :label="detalle.estado === 'vencida' ? 'Renovar' : 'Editar'"
          icon="edit"
          @click="editar(detalle)"
        />
        <AppButton
          v-if="detalle?.estado === 'pendiente' && userStore.hasPermission('cotizaciones.convertir')"
          variant="primary"
          label="Convertir en pedido"
          icon="shopping_cart_checkout"
          :loading="accionando === 'convertir'"
          @click="ejecutar('convertir')"
        />
      </template>
    </AppDialog>

    <ImpresionCotizacion ref="impresionRef" />
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
import CotizacionService from '@/services/CotizacionService'
import { useUserStore } from '@/stores/user-store'
import { formatearPrecio } from '@/utils/moneda'
import CotizacionDetalle from './CotizacionDetalle.vue'
import CotizacionForm from './CotizacionForm.vue'
import ImpresionCotizacion from './ImpresionCotizacion.vue'
import { ESTADOS, fechaCorta } from './constantes'

const $q = useQuasar()
const route = useRoute()
const router = useRouter()
const userStore = useUserStore()

const columns = [
  { name: 'codigo', label: 'Cotización', field: 'codigo', align: 'left', sortable: true },
  { name: 'cliente', label: 'Cliente', field: (row) => row.cliente?.nombre, align: 'left' },
  { name: 'valida_hasta', label: 'Válida hasta', field: 'valida_hasta', align: 'left', sortable: true, format: fechaCorta, classes: 'text-mono' },
  { name: 'items_count', label: 'Ítems', field: 'items_count', align: 'right', classes: 'text-mono' },
  { name: 'total', label: 'Total', field: (row) => formatearPrecio(row.total), align: 'right', sortable: true, classes: 'text-mono' },
  { name: 'estado', label: 'Estado', field: 'estado', align: 'left' },
  { name: 'acciones', label: '', field: 'id', align: 'right' }
]

// ── Filtros ──
const search = ref('')
const busqueda = ref('')
const estadoFilter = ref(null)

let searchTimer
watch(search, (value) => {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(() => { busqueda.value = value.trim() }, 400)
})

const estadoOptions = [
  { label: 'Todos', value: null },
  ...Object.entries(ESTADOS).map(([value, { label }]) => ({ label, value }))
]

const hayFiltros = computed(() => Boolean(search.value || estadoFilter.value))

function limpiarFiltros () {
  search.value = ''
  estadoFilter.value = null
}

const filtroTabla = computed(() => JSON.stringify({ search: busqueda.value, estado: estadoFilter.value }))

// ── Tabla (paginación en el servidor) ──
const tableRef = ref()
const rows = ref([])
const loading = ref(false)
const pagination = ref({ sortBy: 'codigo', descending: true, page: 1, rowsPerPage: 20, rowsNumber: 0 })

async function onRequest ({ pagination: requested }) {
  const { page, rowsPerPage, sortBy, descending } = requested
  loading.value = true

  try {
    const columna = sortBy === 'codigo' ? 'id' : sortBy
    const params = { rowsPerPage, page, search: busqueda.value, order_by: descending ? `-${columna}` : columna }
    if (estadoFilter.value) params.estado = estadoFilter.value

    const { data, total = 0 } = await CotizacionService.getData({ params })
    rows.value = data
    pagination.value = { ...requested, rowsNumber: total }
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  tableRef.value.requestServerInteraction()
})

function refrescar () {
  tableRef.value.requestServerInteraction()
}

// ── Crear / editar ──
const formDialog = ref(false)
const formRef = ref()
const editId = ref(null)
const aperturas = ref(0)

function crear () {
  editId.value = null
  aperturas.value++
  formDialog.value = true
}

function editar (cotizacion) {
  detalleDialog.value = false
  editId.value = cotizacion.id
  formDialog.value = true
}

function guardada (cotizacion) {
  formDialog.value = false
  refrescar()
  $q.notify({ type: 'positive', message: `Cotización ${cotizacion?.codigo ?? ''} guardada.`, position: 'top-right', timeout: 1500 })
  if (cotizacion) ver(cotizacion)
}

const impresionRef = ref()

// ── Detalle y acciones ──
const detalleDialog = ref(false)
const detalleRef = ref()
const detalleId = ref(null)
const detalle = computed(() => detalleRef.value?.cotizacion ?? null)
const pendiente = computed(() => ['pendiente', 'vencida'].includes(detalle.value?.estado))

function ver (cotizacion) {
  detalleId.value = cotizacion.id
  detalleDialog.value = true
}

// ?ver=ID (desde la búsqueda global o la campana) abre ese detalle, también
// si ya estábamos en esta pantalla.
watch(() => route.query.ver, (id) => {
  if (!id) return
  ver({ id: Number(id) })
  router.replace({ query: { ...route.query, ver: undefined } })
}, { immediate: true })

const accionando = ref(null)

async function ejecutar (accion) {
  accionando.value = accion
  try {
    const actualizada = await CotizacionService[accion](detalle.value.id)
    detalleRef.value.actualizar(actualizada)
    refrescar()
    $q.notify({
      type: 'positive',
      message: accion === 'convertir'
        ? `Se creó el pedido ${actualizada.pedido?.codigo}: confirmalo desde Pedidos.`
        : 'Cotización marcada como rechazada.',
      position: 'top-right',
      timeout: 3500
    })
  } catch (error) {
    const { status, data } = error.response ?? {}
    if (status === 422 || status === 409) {
      const primero = Object.values(data?.errors ?? {})[0]?.[0]
      $q.notify({ type: 'negative', message: primero ?? data?.message, position: 'top-right', timeout: 4500 })
    }
  } finally {
    accionando.value = null
  }
}
</script>

<style lang="scss" scoped>
.cot-codigo {
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

.cot-meta {
  font-size: 12px;
  color: var(--app-ink-2);
}
</style>
