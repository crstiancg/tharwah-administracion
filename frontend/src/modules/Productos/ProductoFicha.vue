<template>
  <div class="app-list-page">
    <!-- ── Cabecera: volver, saltar a otro producto ── -->
    <div class="ficha__barra">
      <router-link
        to="/productos"
        class="ficha__volver"
      >
        <q-icon
          name="arrow_back"
          size="18px"
        />
        Productos
      </router-link>

      <q-select rounded
        v-model="saltarA"
        :options="opcionesSalto"
        use-input
        hide-dropdown-icon
        input-debounce="300"
        dense
        outlined
        emit-value
        map-options
        placeholder="Ir a otro producto: nombre, SKU o código…"
        class="ficha__salto"
        @filter="buscarProductos"
        @update:model-value="irA"
      >
        <template #option="scope">
          <q-item
            v-bind="scope.itemProps"
            class="ficha__opcion"
          >
            <q-item-section avatar>
              <img
                v-if="scope.opt.producto.portada"
                :src="scope.opt.producto.portada.miniatura_url"
                alt=""
                class="ficha__opcionFoto"
                loading="lazy"
              >
              <span
                v-else
                class="ficha__opcionFoto ficha__opcionFoto--vacia"
              >
                <q-icon
                  name="image"
                  size="16px"
                />
              </span>
            </q-item-section>
            <q-item-section>
              <q-item-label class="ficha__opcionNombre">
                {{ scope.opt.producto.nombre }}
              </q-item-label>
              <q-item-label caption>
                {{ [scope.opt.producto.marca?.nombre, scope.opt.producto.categoria?.nombre].filter(Boolean).join(' · ') || 'Sin marca ni categoría' }}
              </q-item-label>
              <q-item-label caption>
                {{ scope.opt.producto.variantes_count }} {{ scope.opt.producto.variantes_count === 1 ? 'presentación' : 'presentaciones' }}
                <template v-if="!scope.opt.producto.activo"> · <span class="text-negative">Inactivo</span></template>
              </q-item-label>
            </q-item-section>
            <q-item-section
              side
              class="ficha__opcionLado"
            >
              <span class="text-mono ficha__opcionPrecio">{{ formatearPrecio(scope.opt.producto.precio) }}</span>
              <span :class="['ficha__opcionStock', { 'ficha__opcionStock--cero': !Number(scope.opt.producto.stock_total) }]">
                Stock {{ formatearCantidad(scope.opt.producto.stock_total ?? 0) }}
              </span>
            </q-item-section>
          </q-item>
        </template>
        <template #prepend>
          <q-icon
            name="search"
            size="18px"
          />
        </template>
        <template #no-option>
          <q-item>
            <q-item-section class="text-grey">
              Ningún producto coincide.
            </q-item-section>
          </q-item>
        </template>
      </q-select>
    </div>

    <div
      v-if="cargando && !producto"
      class="ficha__cargando"
    >
      <q-spinner size="28px" />
    </div>

    <template v-else-if="producto">
      <header class="ficha__cabecera">
        <img
          v-if="portada"
          :src="portada"
          alt=""
          class="ficha__foto"
        >
        <span
          v-else
          class="ficha__foto ficha__foto--vacia"
        >
          <q-icon
            name="image"
            size="26px"
          />
        </span>

        <div class="ficha__titulo">
          <h1>{{ producto.nombre }}</h1>
          <p>
            {{ producto.marca?.nombre ?? 'Sin marca' }} · {{ producto.categoria?.nombre ?? 'Sin categoría' }}
          </p>
          <div class="ficha__chips">
            <AppChip
              :status="producto.activo ? 'positive' : 'negative'"
              :label="producto.activo ? 'Activo' : 'Inactivo'"
            />
            <AppChip
              v-if="producto.maneja_lotes"
              status="info"
              label="Maneja lotes"
            />
          </div>
        </div>

        <div class="ficha__acciones">
          <AppButton
            v-for="accion in acciones"
            :key="accion.tipo"
            :variant="accion.tipo === 'entrada' ? 'primary' : 'secondary'"
            :label="accion.label"
            :icon="accion.icon"
            :disable="!variantesSede.length"
            @click="abrirMovimiento(accion.tipo)"
          >
            <q-tooltip v-if="!variantesSede.length">
              {{ producto.activo ? 'Ninguna presentación se vende en tu sede' : 'El producto está inactivo' }}
            </q-tooltip>
          </AppButton>
          <AppButton
            v-if="userStore.hasPermission('productos.update')"
            variant="tertiary"
            label="Editar"
            icon="edit"
            @click="editarDialog = true"
          />
        </div>
      </header>

      <!-- ── Resumen ── -->
      <div class="ficha__stats">
        <AppStatTile
          :label="`Stock en ${userStore.sede?.nombre ?? 'tu sede'}`"
          :value="formatearCantidad(stockSede)"
          featured
        />
        <AppStatTile
          label="Stock en la empresa"
          :value="formatearCantidad(stockEmpresa)"
        />
        <AppStatTile
          label="Precio base"
          :value="formatearPrecio(producto.precio)"
        />
        <AppStatTile
          label="Presentaciones"
          :value="String(producto.variantes.length)"
        />
      </div>

      <!-- ── Presentaciones ── -->
      <AppCard class="ficha__bloque">
        <h2 class="ficha__subtitulo">
          Presentaciones
        </h2>
        <div class="ficha__tablaScroll">
          <table class="ficha__tabla">
            <thead>
              <tr>
                <th>Presentación</th>
                <th>SKU</th>
                <th class="text-right">
                  Precio
                </th>
                <th class="text-right">
                  En tu sede
                </th>
                <th class="text-right">
                  Mínimo
                </th>
                <th class="text-right">
                  Empresa
                </th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="v in presentaciones"
                :key="v.id"
              >
                <td>
                  <div class="ficha__presentacion">
                    <span
                      v-if="v.color"
                      class="ficha__swatch"
                      :style="{ background: v.color.hexadecimal }"
                    />
                    <span>
                      {{ v.presentacion }}<template v-if="v.color"> · {{ v.color.nombre }}</template>
                    </span>
                    <AppChip
                      v-if="!v.sede?.activo"
                      status="warning"
                      label="No se vende acá"
                    />
                    <AppChip
                      v-else-if="v.bajoMinimo"
                      status="negative"
                      label="Bajo mínimo"
                    />
                  </div>
                </td>
                <td class="text-mono">
                  {{ v.sku }}
                </td>
                <td class="text-right text-mono">
                  {{ formatearPrecio(v.precioSede) }}
                </td>
                <td class="text-right text-mono ficha__fuerte">
                  {{ formatearCantidad(v.sede?.cantidad ?? 0) }}
                </td>
                <td class="text-right text-mono">
                  {{ formatearCantidad(v.sede?.stock_minimo ?? 0) }}
                </td>
                <td class="text-right text-mono">
                  {{ formatearCantidad(v.stock) }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </AppCard>

      <!-- ── Lotes en la sede ── -->
      <AppCard
        v-if="producto.maneja_lotes && userStore.hasPermission('inventario.lotes')"
        class="ficha__bloque"
      >
        <h2 class="ficha__subtitulo">
          Lotes en tu sede
        </h2>
        <p
          v-if="!lotes.length"
          class="ficha__vacio"
        >
          No hay lotes con stock. Cada entrada de mercadería crea (o suma a) un lote con su vencimiento.
        </p>
        <div
          v-else
          class="ficha__tablaScroll"
        >
          <table class="ficha__tabla">
            <thead>
              <tr>
                <th>Lote</th>
                <th>Presentación</th>
                <th>Vence</th>
                <th class="text-right">
                  Cantidad
                </th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="lote in lotes"
                :key="lote.id"
              >
                <td class="text-mono">
                  {{ lote.codigo }}
                </td>
                <td>{{ lote.variante?.presentacion }}</td>
                <td>
                  <span v-if="lote.vence_at">{{ fechaCorta(lote.vence_at) }}</span>
                  <span v-else>—</span>
                  <AppChip
                    v-if="lote.estado === 'vencido' || lote.estado === 'por_vencer'"
                    :status="lote.estado === 'vencido' ? 'negative' : 'warning'"
                    :label="lote.estado === 'vencido' ? 'Vencido' : 'Por vencer'"
                    class="q-ml-sm"
                  />
                </td>
                <td class="text-right text-mono">
                  {{ formatearCantidad(lote.cantidad) }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </AppCard>

      <!-- ── Movimientos ── -->
      <template v-if="userStore.hasPermission('inventario.index')">
        <div class="ficha__movTitulo">
          <h2 class="ficha__subtitulo">
            Movimientos de inventario
          </h2>
          <AppFilterPill
            v-model="tipoFilter"
            label="Tipo"
            :options="tipoOptions"
          />
          <AppFilterPill
            v-if="sedes.length > 1"
            v-model="sedeFilter"
            label="Sede"
            :options="sedeOptions"
          />
        </div>

        <AppTable
          ref="tableRef"
          v-model:pagination="pagination"
          :rows="movimientos"
          :columns="columns"
          :loading="cargandoMovimientos"
          :filter="filtroTabla"
          no-data-label="Este producto todavía no tiene movimientos."
          @request="onRequest"
        >
          <template #body-cell-fecha="props">
            <q-td
              :props="props"
              class="text-mono ficha__fecha"
            >
              {{ formatearFecha(props.row.fecha) }}
            </q-td>
          </template>

          <template #body-cell-tipo="props">
            <q-td :props="props">
              <AppChip
                :status="TIPOS[props.row.tipo].status"
                :label="TIPOS[props.row.tipo].label"
              />
            </q-td>
          </template>

          <template #body-cell-variante="props">
            <q-td :props="props">
              <div>{{ props.row.variante.presentacion }}</div>
              <div class="ficha__detalle text-mono">
                {{ props.row.variante.sku }}
              </div>
            </q-td>
          </template>

          <template #body-cell-cantidad="props">
            <q-td
              :props="props"
              :class="['text-right', 'text-mono', props.row.cantidad > 0 ? 'ficha__mas' : 'ficha__menos']"
            >
              {{ props.row.cantidad > 0 ? '+' : '' }}{{ formatearCantidad(props.row.cantidad) }}
            </q-td>
          </template>

          <template #body-cell-detalle="props">
            <q-td :props="props">
              <div v-if="props.row.motivo_label">
                {{ props.row.motivo_label }}
                <template v-if="props.row.sede_relacionada">
                  {{ props.row.cantidad < 0 ? 'a' : 'desde' }} {{ props.row.sede_relacionada.nombre }}
                </template>
              </div>
              <div
                v-if="props.row.referencia"
                class="ficha__detalle"
              >
                Ref. {{ props.row.referencia }}
              </div>
              <div
                v-for="lote in props.row.lotes ?? []"
                :key="lote.codigo"
                class="ficha__detalle"
              >
                Lote {{ lote.codigo }}<template v-if="lote.vence_at">
                  · vence {{ fechaCorta(lote.vence_at) }}
                </template>
              </div>
              <div
                v-if="props.row.costo_unitario !== null"
                class="ficha__detalle"
              >
                {{ formatearPrecio(props.row.costo_unitario) }} c/u
              </div>
            </q-td>
          </template>
        </AppTable>
      </template>
    </template>

    <!-- ── Registrar movimiento con las presentaciones ya cargadas ── -->
    <AppDialog
      v-model="movimientoDialog"
      :title="tituloMovimiento"
      size="lg"
      persistent
    >
      <MovimientoForm
        v-if="tipoAbierto"
        ref="movimientoRef"
        :key="`${tipoAbierto}-${aperturas}`"
        :tipo="tipoAbierto"
        :iniciales="variantesSede"
        @save="movimientoGuardado"
      />
      <template #actions>
        <AppButton
          variant="tertiary"
          label="Cancelar"
          @click="movimientoDialog = false"
        />
        <AppButton
          variant="primary"
          label="Registrar"
          :loading="movimientoRef?.form.processing"
          @click="movimientoRef.submit()"
        />
      </template>
    </AppDialog>

    <AppDialog
      v-model="editarDialog"
      :title="`Editar ${producto?.nombre ?? ''}`"
      size="lg"
      persistent
    >
      <ProductosForm
        v-if="editarDialog && producto"
        :id="producto.id"
        ref="editarRef"
        @save="editado"
      />
      <template #actions>
        <AppButton
          variant="tertiary"
          label="Cancelar"
          @click="editarDialog = false"
        />
        <AppButton
          variant="primary"
          label="Guardar"
          :loading="editarRef?.form.processing"
          @click="editarRef.submit()"
        />
      </template>
    </AppDialog>
  </div>
</template>

<script setup>
import { computed, nextTick, onMounted, ref, watch } from 'vue'
import { useQuasar } from 'quasar'
import { useRoute, useRouter } from 'vue-router'
import AppButton from '@/components/AppButton.vue'
import AppCard from '@/components/AppCard.vue'
import AppChip from '@/components/AppChip.vue'
import AppDialog from '@/components/AppDialog.vue'
import AppFilterPill from '@/components/AppFilterPill.vue'
import AppStatTile from '@/components/AppStatTile.vue'
import AppTable from '@/components/AppTable.vue'
import MovimientoForm from '@/modules/Inventario/MovimientoForm.vue'
import { TIPOS } from '@/modules/Inventario/constantes'
import InventarioService from '@/services/InventarioService'
import ProductoService from '@/services/ProductoService'
import SedeService from '@/services/SedeService'
import { useUserStore } from '@/stores/user-store'
import { formatearCantidad } from '@/utils/cantidad'
import { formatearPrecio } from '@/utils/moneda'
import ProductosForm from './ProductosForm.vue'

/**
 * Ficha de un producto: cómo está en la sede (stock por presentación, lotes),
 * su historial de inventario y las acciones de inventario con sus
 * presentaciones ya cargadas. `?entrada=1` abre la entrada al llegar (recién
 * creado: el stock inicial —y su lote— se carga con la primera entrada).
 */
const props = defineProps({
  id: {
    type: Number,
    required: true
  }
})

const $q = useQuasar()
const route = useRoute()
const router = useRouter()
const userStore = useUserStore()

const producto = ref(null)
const cargando = ref(true)
// Presentaciones vendibles en la sede, con su stock ahí: las líneas de los
// movimientos (misma forma que el buscador de inventario).
const variantesSede = ref([])
const lotes = ref([])

async function cargar () {
  cargando.value = true
  try {
    const [p, v] = await Promise.all([
      ProductoService.get(props.id),
      acciones.value.length || userStore.hasPermission('inventario.index')
        ? InventarioService.variantes({ params: { producto_id: props.id, rowsPerPage: 0 } })
        : Promise.resolve({ data: [] })
    ])
    producto.value = p
    variantesSede.value = v.data
    await cargarLotes()
  } finally {
    cargando.value = false
  }
}

async function cargarLotes () {
  if (!producto.value?.maneja_lotes || !userStore.hasPermission('inventario.lotes')) {
    lotes.value = []
    return
  }
  lotes.value = (await InventarioService.lotes({ params: { producto_id: props.id, rowsPerPage: 0 } })).data
}

const portada = computed(() => producto.value?.archivos?.[0]?.miniatura_url ?? null)

// Cada presentación con lo de la sede del usuario (stock, mínimo, precio).
const presentaciones = computed(() => (producto.value?.variantes ?? []).map((v) => {
  const sede = v.stocks?.find((s) => s.sede_id === userStore.sedeId) ?? null
  const cantidad = Number(sede?.cantidad ?? 0)
  const minimo = Number(sede?.stock_minimo ?? 0)
  return {
    ...v,
    sede,
    precioSede: sede?.precio ?? v.precio ?? producto.value.precio,
    bajoMinimo: minimo > 0 && cantidad < minimo
  }
}))

const stockSede = computed(() => presentaciones.value.reduce((s, v) => s + Number(v.sede?.cantidad ?? 0), 0))
const stockEmpresa = computed(() => presentaciones.value.reduce((s, v) => s + Number(v.stock ?? 0), 0))

// ── Saltar a otro producto ──
const saltarA = ref(null)
const opcionesSalto = ref([])

async function buscarProductos (texto, update) {
  // Con la sede del usuario: el stock que se muestra es el de acá.
  const { data } = await ProductoService.getData({
    params: { search: texto.trim(), rowsPerPage: 15, order_by: 'nombre', sede_id: userStore.sedeId ?? 0 }
  })
  update(() => {
    opcionesSalto.value = data
      .filter((p) => p.id !== props.id)
      .map((p) => ({ label: p.nombre, value: p.id, producto: p }))
  })
}

function irA (id) {
  if (id) router.push(`/productos/${id}`)
  saltarA.value = null
}

// ── Movimientos (paginación en el servidor) ──
const columns = [
  { name: 'fecha', label: 'Fecha', field: 'fecha', align: 'left', sortable: true },
  { name: 'tipo', label: 'Tipo', field: 'tipo', align: 'left' },
  { name: 'sede', label: 'Sede', field: (row) => row.sede?.nombre ?? '—', align: 'left' },
  { name: 'variante', label: 'Presentación', field: (row) => row.variante?.sku, align: 'left' },
  { name: 'cantidad', label: 'Cantidad', field: 'cantidad', align: 'right' },
  { name: 'stock_resultante', label: 'Stock en sede', field: 'stock_resultante', align: 'right', classes: 'text-mono', format: (v) => formatearCantidad(v) },
  { name: 'detalle', label: 'Detalle', field: 'motivo', align: 'left' },
  { name: 'usuario', label: 'Usuario', field: (row) => row.usuario?.name ?? '—', align: 'left' }
]

const tableRef = ref()
const movimientos = ref([])
const cargandoMovimientos = ref(false)
const pagination = ref({ sortBy: 'fecha', descending: true, page: 1, rowsPerPage: 10, rowsNumber: 0 })

const tipoFilter = ref(null)
const tipoOptions = [
  { label: 'Todos', value: null },
  ...Object.entries(TIPOS).filter(([, t]) => !t.soloAccion).map(([value, { label }]) => ({ label, value }))
]
const sedeFilter = ref(userStore.sedeId)
const sedes = ref([])
const sedeOptions = computed(() => [
  { label: 'Todas', value: null },
  ...sedes.value.map((s) => ({ label: s.nombre, value: s.id }))
])

// El id va en el filtro: al saltar a otro producto, la tabla vuelve a la página 1.
const filtroTabla = computed(() => JSON.stringify({ id: props.id, tipo: tipoFilter.value, sede: sedeFilter.value }))

async function onRequest ({ pagination: requested }) {
  const { page, rowsPerPage, sortBy, descending } = requested
  cargandoMovimientos.value = true
  try {
    const columna = sortBy === 'fecha' ? 'id' : sortBy
    const params = { producto_id: props.id, rowsPerPage, page, order_by: descending ? `-${columna}` : columna }
    if (tipoFilter.value) params.tipo = tipoFilter.value
    if (sedeFilter.value) params.sede_id = sedeFilter.value

    const { data, total = 0 } = await InventarioService.getData({ params })
    movimientos.value = data
    pagination.value = { ...requested, rowsNumber: total }
  } finally {
    cargandoMovimientos.value = false
  }
}

function recargarMovimientos () {
  tableRef.value?.requestServerInteraction()
}

const formatoFecha = new Intl.DateTimeFormat('es-PE', { dateStyle: 'short', timeStyle: 'short' })
function formatearFecha (iso) {
  return iso ? formatoFecha.format(new Date(iso)) : ''
}

function fechaCorta (iso) {
  return iso.split('-').reverse().join('/')
}

// ── Acciones de inventario ──
const acciones = computed(() => Object.entries(TIPOS)
  .filter(([, tipo]) => userStore.hasPermission(tipo.permiso))
  .map(([clave, tipo]) => ({ tipo: clave, ...tipo })))

const movimientoDialog = ref(false)
const movimientoRef = ref()
const tipoAbierto = ref(null)
const aperturas = ref(0)

const tituloMovimiento = computed(() => {
  if (!tipoAbierto.value) return ''
  return `${TIPOS[tipoAbierto.value].titulo} · ${producto.value?.nombre ?? ''}`
})

function abrirMovimiento (tipo) {
  tipoAbierto.value = tipo
  aperturas.value++
  movimientoDialog.value = true
}

async function movimientoGuardado (registrados) {
  movimientoDialog.value = false
  const n = registrados.length
  $q.notify({
    type: 'positive',
    message: n === 0
      ? 'El conteo coincide con el sistema: no hubo diferencias que registrar.'
      : `${n} ${n === 1 ? 'movimiento registrado' : 'movimientos registrados'}.`,
    position: 'top-right',
    timeout: 2500
  })
  await cargar()
  recargarMovimientos()
}

// ── Editar ──
const editarDialog = ref(false)
const editarRef = ref()

async function editado () {
  editarDialog.value = false
  $q.notify({ type: 'positive', message: 'Producto guardado.', position: 'top-right', timeout: 1500 })
  await cargar()
}

// ── Carga ──
// Recién creado (?entrada=1): se abre la entrada para cargar el stock
// inicial; el query se limpia para que recargar no la vuelva a abrir.
async function iniciar () {
  await cargar()
  if (route.query.entrada && variantesSede.value.length && userStore.hasPermission('inventario.entradas')) {
    router.replace({ query: {} })
    abrirMovimiento('entrada')
  }
}

// Al saltar a otro producto los movimientos se recargan solos: el id está en
// `filtroTabla` y AppTable pide datos cuando cambia.
watch(() => props.id, iniciar)

onMounted(async () => {
  iniciar().then(async () => {
    await nextTick()
    recargarMovimientos()
  })
  if (userStore.hasPermission('sedes.index')) sedes.value = await SedeService.activas()
})
</script>

<style lang="scss" scoped>
.ficha__barra {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  flex-wrap: wrap;
}

.ficha__volver {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: 14px;
  font-weight: 600;
  color: var(--app-ink-2);
  text-decoration: none;

  &:hover {
    color: $primary;
  }
}

.ficha__salto {
  width: 380px;
  max-width: 100%;
}

// El menú se teletransporta a <body>: el scope llega a lo que está escrito en
// este template (las clases de adentro), no a la clase del popup.
.ficha__opcion {
  min-width: 360px;
  padding: 8px 12px;
}

.ficha__opcionFoto {
  width: 40px;
  height: 40px;
  border-radius: 8px;
  object-fit: cover;

  &--vacia {
    display: flex;
    align-items: center;
    justify-content: center;
    border: 1px solid var(--app-border-subtle);
    color: var(--app-ink-2);
  }
}

.ficha__opcionNombre {
  font-weight: 600;
}

.ficha__opcionLado {
  align-items: flex-end;
  gap: 2px;
}

.ficha__opcionPrecio {
  font-size: 13px;
  font-weight: 600;
  color: var(--app-ink);
}

.ficha__opcionStock {
  font-size: 12px;
  color: var(--app-ink-2);

  &--cero {
    color: var(--q-negative);
  }
}

.ficha__cargando {
  display: flex;
  justify-content: center;
  padding: 48px;
}

.ficha__cabecera {
  display: flex;
  align-items: center;
  gap: 16px;
  flex-wrap: wrap;
}

.ficha__foto {
  width: 72px;
  height: 72px;
  border-radius: 12px;
  object-fit: cover;
  flex-shrink: 0;

  &--vacia {
    display: flex;
    align-items: center;
    justify-content: center;
    border: 1px solid var(--app-border-subtle);
    background: var(--app-surface);
    color: var(--app-ink-2);
  }
}

.ficha__titulo {
  flex: 1;
  min-width: 220px;

  h1 {
    margin: 0;
    font-size: 25px;
    font-weight: 700;
    line-height: 1.2;
    letter-spacing: -0.5px;
  }

  p {
    margin: 4px 0 8px;
    font-size: 14px;
    color: var(--app-ink-2);
  }
}

.ficha__chips {
  display: flex;
  gap: 6px;
  flex-wrap: wrap;
}

.ficha__acciones {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
}

.ficha__stats {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
  gap: 14px;
}

.ficha__bloque {
  padding: 18px 20px;
}

.ficha__subtitulo {
  margin: 0 0 12px;
  font-size: 16px;
  font-weight: 700;
  line-height: 1.3;
  color: var(--app-ink);
}

.ficha__movTitulo {
  display: flex;
  align-items: center;
  gap: 10px;
  flex-wrap: wrap;

  .ficha__subtitulo {
    flex: 1;
    margin: 0;
  }
}

.ficha__vacio {
  margin: 0;
  font-size: 13px;
  color: var(--app-ink-2);
}

.ficha__tablaScroll {
  overflow-x: auto;
}

.ficha__tabla {
  width: 100%;
  border-collapse: collapse;
  font-size: 14px;

  th {
    padding: 8px 10px;
    border-bottom: 1px solid var(--app-border-subtle);
    font-size: 11px;
    font-weight: 600;
    letter-spacing: 0.04em;
    text-align: left;
    text-transform: uppercase;
    color: var(--app-ink-2);
    white-space: nowrap;
  }

  td {
    padding: 10px;
    border-bottom: 1px solid var(--app-border-subtle);
    color: var(--app-ink);
  }

  tr:last-child td {
    border-bottom: 0;
  }

  .text-right {
    text-align: right;
  }
}

.ficha__presentacion {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
}

.ficha__swatch {
  width: 14px;
  height: 14px;
  border: 1px solid var(--app-border-subtle);
  border-radius: 4px;
}

.ficha__fuerte {
  font-weight: 700;
}

.ficha__fecha {
  white-space: nowrap;
  color: var(--app-ink-2);
}

.ficha__detalle {
  font-size: 12px;
  color: var(--app-ink-2);
}

.ficha__mas {
  font-weight: 600;
  color: var(--q-positive);
}

.ficha__menos {
  font-weight: 600;
  color: var(--q-negative);
}
</style>
