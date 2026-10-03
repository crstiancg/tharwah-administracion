<template>
  <div class="app-list-page">
    <AppPageHeader
      title="Por reponer"
      :subtitle="`${pagination.rowsNumber} presentaciones por debajo de su stock mínimo`"
    />

    <AppFilterBar
      v-model:search="search"
      search-placeholder="Buscar por producto o SKU"
      :has-active-filters="hayFiltros"
      @clear="limpiarFiltros"
    >
      <AppFilterPill
        v-if="sedes.length > 1"
        v-model="sedeFilter"
        label="Sede"
        :options="sedeOptions"
      />
    </AppFilterBar>

    <AppTable
      ref="tableRef"
      v-model:pagination="pagination"
      :rows="rows"
      :columns="columns"
      :loading="loading"
      :filter="filtroTabla"
      no-data-label="Nada por reponer: todo está sobre su stock mínimo."
      @request="onRequest"
    >
      <template #body-cell-producto="props">
        <q-td :props="props">
          <div class="reponer-producto">
            {{ props.row.producto?.nombre }}
          </div>
          <div class="reponer-detalle">
            {{ props.row.presentacion }}<template v-if="props.row.color">
              · {{ props.row.color.nombre }}
            </template> ·
            <span class="text-mono">{{ props.row.sku }}</span>
          </div>
        </q-td>
      </template>

      <template #body-cell-stock="props">
        <q-td
          :props="props"
          class="text-right text-mono"
        >
          <span :class="props.row.stock <= 0 ? 'reponer-agotado' : 'reponer-bajo'">
            {{ formatearCantidad(props.row.stock) }}
          </span>
          {{ props.row.unidad?.abreviatura }}
        </q-td>
      </template>
    </AppTable>

    <p class="reponer-ayuda">
      El mínimo se define por presentación en el formulario del producto. Reponé con una compra o con un traslado desde otra sede
      (la columna "En la empresa" muestra si hay stock en otras sedes).
    </p>
  </div>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import AppFilterBar from '@/components/AppFilterBar.vue'
import AppFilterPill from '@/components/AppFilterPill.vue'
import AppPageHeader from '@/components/AppPageHeader.vue'
import AppTable from '@/components/AppTable.vue'
import InventarioService from '@/services/InventarioService'
import SedeService from '@/services/SedeService'
import { useUserStore } from '@/stores/user-store'
import { formatearCantidad } from '@/utils/cantidad'
import { formatearPrecio } from '@/utils/moneda'

const userStore = useUserStore()

// Lo que hay que traer para llegar al mínimo.
function faltante (row) {
  return Math.max(0, Math.round((row.stock_minimo - row.stock) * 1000) / 1000)
}

const columns = [
  { name: 'producto', label: 'Producto', field: (row) => row.producto?.nombre, align: 'left' },
  { name: 'stock', label: 'Stock aquí', field: 'stock', align: 'right' },
  { name: 'stock_minimo', label: 'Mínimo', field: 'stock_minimo', align: 'right', classes: 'text-mono', format: formatearCantidad },
  { name: 'faltante', label: 'Faltan', field: faltante, align: 'right', classes: 'text-mono reponer-faltan', format: formatearCantidad },
  { name: 'stock_total', label: 'En la empresa', field: 'stock_total', align: 'right', classes: 'text-mono', format: formatearCantidad },
  { name: 'costo', label: 'Costo prom.', field: (row) => row.costo_promedio === null ? '—' : formatearPrecio(row.costo_promedio), align: 'right', classes: 'text-mono' }
]

// ── Filtros ──
const search = ref('')
const busqueda = ref('')
let searchTimer
watch(search, (value) => {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(() => { busqueda.value = value.trim() }, 400)
})

const sedeFilter = ref(userStore.sedeId)
const sedes = ref([])
const sedeOptions = computed(() => sedes.value.map((s) => ({ label: s.nombre, value: s.id })))

const hayFiltros = computed(() => Boolean(search.value || sedeFilter.value !== userStore.sedeId))

function limpiarFiltros () {
  search.value = ''
  sedeFilter.value = userStore.sedeId
}

const filtroTabla = computed(() => JSON.stringify({ search: busqueda.value, sede: sedeFilter.value }))

// ── Tabla ──
const tableRef = ref()
const rows = ref([])
const loading = ref(false)
const pagination = ref({ page: 1, rowsPerPage: 20, rowsNumber: 0 })

async function onRequest ({ pagination: requested }) {
  const { page, rowsPerPage } = requested
  loading.value = true

  try {
    const params = { page, rowsPerPage, search: busqueda.value, bajo_minimo: 1 }
    if (sedeFilter.value) params.sede_id = sedeFilter.value

    const { data, total = 0 } = await InventarioService.variantes({ params })
    rows.value = data
    pagination.value = { ...requested, rowsNumber: total }
  } finally {
    loading.value = false
  }
}

onMounted(async () => {
  tableRef.value.requestServerInteraction()
  if (userStore.hasPermission('sedes.index')) {
    sedes.value = await SedeService.activas()
  }
})
</script>

<style lang="scss" scoped>
.reponer-producto {
  font-weight: 600;
  color: var(--app-ink);
}

.reponer-detalle {
  font-size: 12px;
  color: var(--app-ink-2);
}

.reponer-agotado {
  font-weight: 700;
  color: var(--q-negative);
}

.reponer-bajo {
  font-weight: 700;
  color: var(--q-warning);
}

:deep(.reponer-faltan) {
  font-weight: 700;
}

.reponer-ayuda {
  margin: 12px 0 0;
  font-size: 12px;
  line-height: 1.5;
  color: var(--app-ink-2);
}
</style>
