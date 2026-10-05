<template>
  <form
    class="producto-form"
    novalidate
    @submit.prevent="submit"
  >
    <!-- ── Datos generales ── -->
    <div class="producto-form__row">
      <AppTextField
        v-model="form.producto.nombre"
        label="Nombre"
        icon="inventory_2"
        placeholder="Sikaflex 1A Plus"
        maxlength="120"
        class="producto-form__grow"
        :error="form.errors[`${PATH}.nombre`]"
        autofocus
        @change="form.validate(`${PATH}.nombre`)"
      />

      <div class="producto-form__field producto-form__grow">
        <label
          :id="`${uid}-categoria`"
          class="producto-form__label"
        >Categoría</label>
        <q-select
          v-model="form.producto.categoria_id"
          :options="categoriasFiltradas"
          :aria-labelledby="`${uid}-categoria`"
          :loading="cargando"
          :error="Boolean(form.errors[`${PATH}.categoria_id`])"
          :error-message="form.errors[`${PATH}.categoria_id`]"
          placeholder="Elegí una categoría"
          dense
          outlined
          hide-bottom-space
          no-error-icon
          emit-value
          map-options
          use-input
          fill-input
          hide-selected
          input-debounce="0"
          class="producto-form__control"
          @filter="filtrarCategorias"
          @update:model-value="form.validate(`${PATH}.categoria_id`)"
        >
          <template #prepend>
            <q-icon
              name="category"
              class="producto-form__icon"
            />
          </template>
          <template #no-option>
            <q-item>
              <q-item-section class="text-grey">
                No hay categorías que coincidan.
              </q-item-section>
            </q-item>
          </template>
        </q-select>
      </div>
    </div>

    <div class="producto-form__row producto-form__row--end">
      <div class="producto-form__field producto-form__grow">
        <label
          :id="`${uid}-marca`"
          class="producto-form__label"
        >Marca</label>
        <q-select
          v-model="form.producto.marca_id"
          :options="opcionesMarcas"
          :aria-labelledby="`${uid}-marca`"
          :loading="cargando"
          :error="Boolean(form.errors[`${PATH}.marca_id`])"
          :error-message="form.errors[`${PATH}.marca_id`]"
          placeholder="Elegí una marca"
          dense
          outlined
          hide-bottom-space
          no-error-icon
          emit-value
          map-options
          class="producto-form__control"
          @update:model-value="form.validate(`${PATH}.marca_id`)"
        >
          <template #prepend>
            <q-icon
              name="verified"
              class="producto-form__icon"
            />
          </template>
        </q-select>
      </div>

      <AppTextField
        v-model="form.producto.precio"
        label="Precio base"
        icon="sell"
        type="number"
        min="0"
        step="0.01"
        placeholder="0.00"
        class="producto-form__precio"
        :error="form.errors[`${PATH}.precio`]"
        @change="form.validate(`${PATH}.precio`)"
      />

      <q-toggle
        v-model="form.producto.activo"
        label="Activo (se puede vender)"
        color="primary"
        class="producto-form__toggle"
      />

      <!-- Cambiarlo con stock dejaría unidades sin lote: el backend lo
           rechaza y acá se avisa antes. -->
      <q-toggle
        v-model="form.producto.maneja_lotes"
        label="Maneja lotes y vencimiento"
        color="primary"
        class="producto-form__toggle"
        :disable="tieneStock"
        @update:model-value="form.validate(`${PATH}.maneja_lotes`)"
      >
        <q-tooltip v-if="tieneStock">
          Sólo se puede cambiar con el producto sin stock
        </q-tooltip>
      </q-toggle>
    </div>

    <p
      v-if="form.errors[`${PATH}.maneja_lotes`]"
      class="producto-form__error"
      role="alert"
    >
      {{ form.errors[`${PATH}.maneja_lotes`] }}
    </p>

    <AppTextField
      v-model="form.producto.descripcion"
      label="Descripción (opcional)"
      type="textarea"
      autogrow
      maxlength="1000"
      :error="form.errors[`${PATH}.descripcion`]"
      @change="form.validate(`${PATH}.descripcion`)"
    />

    <div class="producto-form__field">
      <span class="producto-form__label">Fotos del producto</span>
      <AppFotos
        v-model="form.producto.archivos"
        label="Fotos del producto"
        :errores="erroresDe(`${PATH}.archivos`)"
      />
      <p class="producto-form__hint">
        Fotos generales (la primera es la portada). Las de cada presentación van en su fila.
      </p>
    </div>

    <!-- ── Presentaciones ── -->
    <section class="producto-form__seccion">
      <header class="producto-form__seccionHead">
        <div>
          <h3 class="producto-form__title">
            Presentaciones
            <span class="producto-form__count">{{ form.producto.variantes.length }}</span>
          </h3>
          <p class="producto-form__hint">
            Cada presentación (cartucho, galón, balde, bolsa…) tiene su SKU, su precio, sus fotos y su stock. El color es opcional.
          </p>
        </div>
        <AppButton
          label="Agregar presentación"
          icon="add"
          @click="agregarPresentacion"
        />
      </header>

      <p
        v-if="form.errors[`${PATH}.variantes`]"
        class="producto-form__error"
        role="alert"
      >
        {{ form.errors[`${PATH}.variantes`] }}
      </p>

      <div
        v-if="form.producto.variantes.length"
        class="producto-form__tablaWrap"
      >
        <div class="producto-form__tabla">
          <div class="producto-form__fila producto-form__fila--head">
            <span>Presentación</span>
            <span>Unidad</span>
            <span>Color</span>
            <span>SKU</span>
            <span>Precio</span>
            <span>Fotos</span>
            <span class="text-right">{{ hayNuevas ? 'Stock / inicial' : 'Stock' }}</span>
            <span />
          </div>

          <template
            v-for="(variante, i) in form.producto.variantes"
            :key="variante.uid"
          >
            <div class="producto-form__fila">
              <q-input
                v-model="variante.presentacion"
                :aria-label="`Presentación ${i + 1}`"
                :error="Boolean(errorDe(i, 'presentacion'))"
                :error-message="errorDe(i, 'presentacion')"
                placeholder="Balde 4 gl"
                maxlength="60"
                dense
                outlined
                hide-bottom-space
                no-error-icon
                class="producto-form__control"
                @update:model-value="refrescarSku(variante)"
                @change="form.validate(`${PATH}.variantes.${i}.presentacion`)"
              />

              <q-select
                v-model="variante.unidad_id"
                :options="opcionesUnidades"
                :aria-label="`Unidad de la presentación ${i + 1}`"
                :error="Boolean(errorDe(i, 'unidad_id'))"
                :error-message="errorDe(i, 'unidad_id')"
                dense
                outlined
                hide-bottom-space
                no-error-icon
                emit-value
                map-options
                class="producto-form__control"
                @update:model-value="form.validate(`${PATH}.variantes.${i}.unidad_id`)"
              />

              <q-select
                v-model="variante.color_id"
                :options="opcionesColores"
                :aria-label="`Color de la presentación ${i + 1}`"
                :error="Boolean(errorDe(i, 'color_id'))"
                :error-message="errorDe(i, 'color_id')"
                placeholder="—"
                clearable
                dense
                outlined
                hide-bottom-space
                no-error-icon
                emit-value
                map-options
                class="producto-form__control"
                @update:model-value="cambioColor(variante, i)"
              >
                <template
                  v-if="variante.color_id"
                  #prepend
                >
                  <span
                    class="producto-form__swatch"
                    :style="{ background: colorPorId.get(variante.color_id)?.hexadecimal ?? 'transparent' }"
                  />
                </template>
                <template #option="scope">
                  <q-item v-bind="scope.itemProps">
                    <q-item-section side>
                      <span
                        class="producto-form__swatch"
                        :style="{ background: scope.opt.hexadecimal }"
                      />
                    </q-item-section>
                    <q-item-section>{{ scope.opt.label }}</q-item-section>
                  </q-item>
                </template>
              </q-select>

              <q-input
                :model-value="variante.sku"
                :aria-label="`SKU de la presentación ${i + 1}`"
                :error="Boolean(errorDe(i, 'sku'))"
                :error-message="errorDe(i, 'sku')"
                dense
                outlined
                hide-bottom-space
                no-error-icon
                maxlength="40"
                class="producto-form__control producto-form__sku"
                @update:model-value="escribirSku(variante, $event)"
                @change="form.validate(`${PATH}.variantes.${i}.sku`)"
              >
                <template
                  v-if="variante.skuManual"
                  #append
                >
                  <q-btn
                    flat
                    dense
                    round
                    size="xs"
                    icon="autorenew"
                    tabindex="-1"
                    :aria-label="`Volver al SKU sugerido en la presentación ${i + 1}`"
                    @click="restaurarSku(variante, i)"
                  >
                    <q-tooltip>Volver al SKU sugerido</q-tooltip>
                  </q-btn>
                </template>
              </q-input>

              <q-input
                v-model="variante.precio"
                :aria-label="`Precio de la presentación ${i + 1}`"
                :placeholder="formatearPrecio(form.producto.precio) || 'Base'"
                :error="Boolean(errorDe(i, 'precio'))"
                :error-message="errorDe(i, 'precio')"
                type="number"
                min="0"
                step="0.01"
                dense
                outlined
                hide-bottom-space
                no-error-icon
                class="producto-form__control"
                @change="form.validate(`${PATH}.variantes.${i}.precio`)"
              />

              <!-- Miniatura + cantidad; abre el panel de fotos debajo de la fila. -->
              <button
                type="button"
                :class="['producto-form__fotosBtn', {
                  'producto-form__fotosBtn--abierto': abierta === variante.uid,
                  'producto-form__fotosBtn--error': erroresDe(`${PATH}.variantes.${i}.archivos`).length
                }]"
                :aria-expanded="String(abierta === variante.uid)"
                :aria-label="`Fotos de la presentación ${i + 1} (${variante.archivos.length})`"
                @click="abierta = abierta === variante.uid ? null : variante.uid"
              >
                <img
                  v-if="variante.archivos.length"
                  :src="variante.archivos[0].miniatura_url ?? variante.archivos[0].url"
                  alt=""
                  width="40"
                  height="40"
                  class="producto-form__fotosThumb"
                >
                <q-icon
                  v-else
                  name="add_a_photo"
                  size="16px"
                />
                <span
                  v-if="variante.archivos.length"
                  class="producto-form__fotosCount"
                >{{ variante.archivos.length }}</span>
              </button>

              <!-- Existente: su stock real (se mueve desde Inventario). Nueva:
                   las unidades con que entra y, si difiere, su costo. -->
              <span
                v-if="variante.id"
                class="producto-form__stock text-mono"
              >
                {{ formatearCantidad(variante.stock) }}
                <q-tooltip v-if="variante.stocks.some((s) => s.cantidad)">
                  <div
                    v-for="s in variante.stocks.filter((s) => s.cantidad)"
                    :key="s.sede_id"
                  >
                    {{ s.sede }}: {{ formatearCantidad(s.cantidad) }}
                  </div>
                </q-tooltip>
              </span>
              <div
                v-else
                class="producto-form__inicial"
              >
                <q-input
                  v-model="variante.stock_inicial"
                  :aria-label="`Stock inicial de la presentación ${i + 1}`"
                  placeholder="0"
                  type="number"
                  min="0"
                  :step="unidadPorId.get(variante.unidad_id)?.fraccionable ? '0.001' : '1'"
                  dense
                  outlined
                  hide-bottom-space
                  no-error-icon
                  :error="Boolean(errorDe(i, 'stock_inicial'))"
                  class="producto-form__control"
                />
                <q-input
                  v-if="Number(variante.stock_inicial) > 0"
                  v-model="variante.costo_unitario"
                  :aria-label="`Costo unitario de la presentación ${i + 1}`"
                  :placeholder="form.producto.costo_compra || 'costo'"
                  type="number"
                  min="0"
                  step="0.01"
                  dense
                  outlined
                  hide-bottom-space
                  no-error-icon
                  :error="Boolean(errorDe(i, 'costo_unitario'))"
                  class="producto-form__control producto-form__costo"
                >
                  <q-tooltip>Costo propio (vacío = el costo de compra general)</q-tooltip>
                </q-input>
              </div>

              <!-- Con stock o con historial de inventario no se quita: se
                   perdería mercadería o su trazabilidad (el backend también
                   lo rechaza). -->
              <q-btn
                flat
                dense
                round
                icon="close"
                size="sm"
                color="grey-7"
                :disable="variante.stock !== 0 || variante.con_movimientos"
                :aria-label="`Quitar presentación ${i + 1}`"
                @click="quitar(i)"
              >
                <q-tooltip v-if="variante.stock !== 0 || variante.con_movimientos">
                  {{ variante.stock !== 0 ? 'Tiene stock' : 'Tiene historial de inventario' }}: no se puede quitar
                </q-tooltip>
              </q-btn>
            </div>

            <p
              v-if="errorDe(i, 'stock_inicial') || errorDe(i, 'costo_unitario')"
              class="producto-form__error"
            >
              {{ errorDe(i, 'stock_inicial') || errorDe(i, 'costo_unitario') }}
            </p>

            <div
              v-if="abierta === variante.uid"
              class="producto-form__panelFotos"
            >
              <AppFotos
                v-model="variante.archivos"
                :label="`Fotos de ${etiquetaDe(variante)}`"
                :errores="erroresDe(`${PATH}.variantes.${i}.archivos`)"
              />
              <AppButton
                v-if="variante.archivos.length && form.producto.variantes.length > 1"
                variant="tertiary"
                icon="content_copy"
                :label="`Usar estas fotos en las otras ${form.producto.variantes.length - 1} presentaciones`"
                @click="copiarFotosATodas(variante)"
              />
            </div>
          </template>
        </div>
      </div>

      <!-- ── Venta por sede ── -->
      <div
        v-if="sedes.length && form.producto.variantes.length"
        class="producto-form__sedes"
      >
        <div class="producto-form__stockTitulo">
          <q-icon
            name="storefront"
            size="16px"
          />
          Venta por sede
          <span class="producto-form__hint">
            Qué sedes venden cada presentación, a qué precio (vacío = el general) y desde qué stock avisar para reponer.
          </span>
        </div>

        <div
          v-for="sede in sedes"
          :key="sede.id"
          class="producto-form__sede"
        >
          <div class="producto-form__sedeHead">
            <q-checkbox
              :model-value="estadoSede(sede.id)"
              toggle-indeterminate
              dense
              :label="sede.nombre"
              class="producto-form__sedeNombre"
              @update:model-value="venderTodoEn(sede.id, $event)"
            />
            <span
              v-if="sede.id === userStore.sedeId"
              class="producto-form__hint"
            >(tu sede)</span>
          </div>

          <div class="producto-form__sedeFilas">
            <div class="producto-form__sedeFila producto-form__sedeFila--head">
              <span>Presentación</span>
              <span>Precio</span>
              <span>Stock mín.</span>
              <span class="text-right">Stock</span>
            </div>
            <div
              v-for="(variante, i) in form.producto.variantes"
              :key="variante.uid"
              class="producto-form__sedeFila"
            >
              <q-checkbox
                v-model="configDe(variante, sede.id).activo"
                dense
                :label="etiquetaDe(variante)"
                :aria-label="`Vender ${etiquetaDe(variante)} en ${sede.nombre}`"
              />
              <q-input
                v-model="configDe(variante, sede.id).precio"
                :aria-label="`Precio de ${etiquetaDe(variante)} en ${sede.nombre}`"
                :placeholder="formatearPrecio(variante.precio || form.producto.precio) || 'General'"
                :disable="!configDe(variante, sede.id).activo"
                :error="Boolean(errorSede(i, sede.id, 'precio'))"
                :error-message="errorSede(i, sede.id, 'precio')"
                type="number"
                min="0"
                step="0.01"
                dense
                outlined
                hide-bottom-space
                no-error-icon
                class="producto-form__control"
              />
              <q-input
                v-model="configDe(variante, sede.id).stock_minimo"
                :aria-label="`Stock mínimo de ${etiquetaDe(variante)} en ${sede.nombre}`"
                :disable="!configDe(variante, sede.id).activo"
                :error="Boolean(errorSede(i, sede.id, 'stock_minimo'))"
                :error-message="errorSede(i, sede.id, 'stock_minimo')"
                placeholder="0"
                type="number"
                min="0"
                dense
                outlined
                hide-bottom-space
                no-error-icon
                class="producto-form__control"
              />
              <span class="text-right text-mono producto-form__sedeStock">
                {{ formatearCantidad(stockEn(variante, sede.id)) ?? 0 }}
              </span>
            </div>
          </div>
        </div>

        <p
          v-for="mensaje in erroresSedes"
          :key="mensaje"
          class="producto-form__error"
          role="alert"
        >
          {{ mensaje }}
        </p>
      </div>

      <div
        v-if="hayNuevas"
        class="producto-form__stockInicial"
      >
        <div class="producto-form__stockTitulo">
          <q-icon
            name="inventory"
            size="16px"
          />
          Stock inicial de las presentaciones nuevas
          <span class="producto-form__hint">(entra al inventario de {{ userStore.sede?.nombre ?? 'tu sede' }} como "Alta de producto")</span>
        </div>
        <div class="producto-form__row">
          <AppTextField
            v-model="form.producto.costo_compra"
            label="Costo de compra por unidad (S/)"
            icon="payments"
            type="number"
            min="0"
            step="0.01"
            placeholder="0.00"
            class="producto-form__grow"
            :error="form.errors[`${PATH}.costo_compra`]"
          />
          <AppTextField
            v-model="form.producto.referencia_compra"
            label="Factura o guía (opcional)"
            icon="receipt"
            maxlength="60"
            placeholder="F001-2345"
            class="producto-form__grow"
            :error="form.errors[`${PATH}.referencia_compra`]"
          />
        </div>
        <div class="producto-form__rellenar">
          <span>Poner</span>
          <q-input
            v-model="cantidadParaTodas"
            aria-label="Unidades para todas las presentaciones nuevas"
            type="number"
            min="0"
            step="1"
            dense
            outlined
            hide-bottom-space
            class="producto-form__control producto-form__rellenarInput"
            @keydown.enter.prevent="ponerATodas"
          />
          <span>unidades a todas las nuevas</span>
          <AppButton
            variant="tertiary"
            label="Aplicar"
            :disable="cantidadParaTodas === ''"
            @click="ponerATodas"
          />
          <span
            v-if="unidadesIniciales"
            class="producto-form__hint"
          >
            Total: {{ unidadesIniciales }} unid.<template v-if="Number(form.producto.costo_compra)">
              · {{ formatearPrecio(costoTotal) }}
            </template>
          </span>
        </div>

        <!-- Con lotes: cada presentación con stock inicial dice a qué lote
             entra y cuándo vence (lo mismo que pide una entrada). -->
        <div
          v-if="form.producto.maneja_lotes && conStockInicial.length"
          class="producto-form__lotes"
        >
          <div class="producto-form__lotesCabecera">
            <span>Presentación</span>
            <span>Lote</span>
            <span>Vence</span>
          </div>
          <div
            v-for="{ variante, i } in conStockInicial"
            :key="i"
            class="producto-form__loteFila"
          >
            <span class="producto-form__loteNombre">
              {{ etiquetaDe(variante) }}
              <span class="producto-form__hint">· {{ variante.stock_inicial }} unid.</span>
            </span>
            <q-input
              v-model="variante.lote"
              :aria-label="`Lote de ${etiquetaDe(variante)}`"
              placeholder="L-2301"
              maxlength="40"
              dense
              outlined
              hide-bottom-space
              no-error-icon
              :error="Boolean(errorDe(i, 'lote'))"
              :error-message="errorDe(i, 'lote')"
              class="producto-form__control"
            />
            <q-input
              v-model="variante.vence_at"
              :aria-label="`Vencimiento de ${etiquetaDe(variante)}`"
              type="date"
              :min="hoy"
              dense
              outlined
              hide-bottom-space
              no-error-icon
              :error="Boolean(errorDe(i, 'vence_at'))"
              :error-message="errorDe(i, 'vence_at')"
              class="producto-form__control"
            />
          </div>
        </div>
      </div>

      <p
        v-else-if="!form.producto.variantes.length"
        class="producto-form__vacio"
      >
        Todavía no hay presentaciones. Tocá “Agregar presentación”.
      </p>
    </section>

    <button
      type="submit"
      hidden
    />
  </form>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref, useId, watch } from 'vue'
import { useForm } from 'laravel-precognition-vue'
import AppButton from '@/components/AppButton.vue'
import AppFotos from '@/components/AppFotos.vue'
import AppTextField from '@/components/AppTextField.vue'
import CategoriaService from '@/services/CategoriaService'
import ColorService from '@/services/ColorService'
import ProductoService from '@/services/ProductoService'
import MarcaService from '@/services/MarcaService'
import SedeService from '@/services/SedeService'
import UnidadService from '@/services/UnidadService'
import { useUserStore } from '@/stores/user-store'
import { formatearCantidad } from '@/utils/cantidad'
import { formatearPrecio } from '@/utils/moneda'
import { opcionesPadre } from '@/modules/Categorias/arbol'
import formProducto, { nuevaVariante } from './FormProducto'
import { sugerirSku } from './sku'

const PATH = 'producto'

const props = defineProps({
  // null = crear; con id = editar.
  id: {
    type: Number,
    default: null
  }
})

const emit = defineEmits(['save'])

const uid = `producto-${useId()}`

// Editar va por POST + _method=PUT (ver FormProducto.js).
const form = props.id
  ? useForm('post', `api/productos/${props.id}`, () => formProducto(true))
  : useForm('post', 'api/productos', () => formProducto())

// ── Catálogos ──
const categorias = ref([])
const marcas = ref([])
const unidades = ref([])
const colores = ref([])
const cargando = ref(true)

const colorPorId = computed(() => new Map(colores.value.map((c) => [c.id, c])))
const unidadPorId = computed(() => new Map(unidades.value.map((u) => [u.id, u])))
const userStore = useUserStore()

// Todas las categorías, con la ruta completa ("Impermeabilizantes › Techos y cubiertas").
const opcionesCategorias = computed(() => opcionesPadre(categorias.value))
const busquedaCategoria = ref('')
const categoriasFiltradas = computed(() => {
  const termino = busquedaCategoria.value.toLowerCase()
  return termino
    ? opcionesCategorias.value.filter((o) => o.label.toLowerCase().includes(termino))
    : opcionesCategorias.value
})

function filtrarCategorias (valor, update) {
  update(() => { busquedaCategoria.value = valor })
}

// Las marcas inactivas no se ofrecen, salvo la que ya tiene el producto.
const opcionesMarcas = computed(() => marcas.value
  .filter((m) => m.activo || m.id === form.producto.marca_id)
  .map((m) => ({ value: m.id, label: m.nombre })))
const opcionesUnidades = computed(() => unidades.value.map((u) => ({ value: u.id, label: `${u.nombre} (${u.abreviatura})` })))
const opcionesColores = computed(() => colores.value.map((c) => ({ value: c.id, label: c.nombre, hexadecimal: c.hexadecimal })))

// ── Errores ──
function errorDe (i, campo) {
  return form.errors[`${PATH}.variantes.${i}.${campo}`]
}

// Todos los mensajes bajo un prefijo (la galería y cada una de sus fotos).
function erroresDe (prefijo) {
  return [...new Set(Object.entries(form.errors)
    .filter(([clave]) => clave === prefijo || clave.startsWith(`${prefijo}.`))
    .map(([, mensaje]) => mensaje))]
}

// ── SKU sugerido ──
function skuSugerido (variante) {
  return sugerirSku(
    form.producto.nombre,
    variante.presentacion,
    colorPorId.value.get(variante.color_id)?.nombre
  )
}

function refrescarSku (variante) {
  if (!variante.skuManual) variante.sku = skuSugerido(variante)
}

// Renombrar el producto arrastra los SKU que no se tocaron a mano.
watch(() => form.producto.nombre, () => form.producto.variantes.forEach(refrescarSku))

function escribirSku (variante, valor) {
  variante.sku = String(valor ?? '').toUpperCase()
  // Borrarlo del todo vuelve al sugerido.
  variante.skuManual = variante.sku !== '' && variante.sku !== skuSugerido(variante)
  if (!variante.sku) refrescarSku(variante)
}

function restaurarSku (variante, i) {
  variante.skuManual = false
  refrescarSku(variante)
  form.validate(`${PATH}.variantes.${i}.sku`)
}

function cambioColor (variante, i) {
  refrescarSku(variante)
  form.validate(`${PATH}.variantes.${i}.color_id`)
}

// ── Venta por sede ──
const sedes = ref([])

// La fila de la sede en la presentación (completarSedes() asegura que exista).
function configDe (variante, sedeId) {
  return variante.sedes.find((s) => s.sede_id === sedeId) ?? { activo: false, precio: '', stock_minimo: '' }
}

function stockEn (variante, sedeId) {
  return variante.stocks.find((s) => s.sede_id === sedeId)?.cantidad ?? 0
}

// Cada presentación con una fila por sede activa. Una NUEVA se vende de
// entrada en la sede de quien la carga (o en la única que haya); una que ya
// existía y no tenía fila en esa sede, no.
function completarSedes () {
  form.producto.variantes.forEach((variante) => {
    sedes.value.forEach((sede) => {
      if (variante.sedes.some((s) => s.sede_id === sede.id)) return
      variante.sedes.push({
        sede_id: sede.id,
        activo: !variante.id && (sede.id === userStore.sedeId || sedes.value.length === 1),
        precio: '',
        stock_minimo: '0'
      })
    })
  })
}
watch(() => [sedes.value.length, form.producto.variantes.length], completarSedes)

// Casilla de la sede: tildada si vende todas, a medias si algunas.
function estadoSede (sedeId) {
  const activas = form.producto.variantes.filter((v) => configDe(v, sedeId).activo).length
  if (activas === 0) return false
  return activas === form.producto.variantes.length ? true : null
}

function venderTodoEn (sedeId, valor) {
  form.producto.variantes.forEach((v) => { configDe(v, sedeId).activo = valor !== false })
}

function errorSede (i, sedeId, campo) {
  const j = form.producto.variantes[i]?.sedes.findIndex((s) => s.sede_id === sedeId)
  return j >= 0 ? form.errors[`${PATH}.variantes.${i}.sedes.${j}.${campo}`] : undefined
}

// Los de la lista entera (stock en una sede que se apaga, sede repetida).
const erroresSedes = computed(() => [...new Set(Object.entries(form.errors)
  .filter(([clave]) => /\.variantes\.\d+\.sedes$/.test(clave))
  .map(([, mensaje]) => mensaje))])

function etiquetaDe (variante) {
  const color = colorPorId.value.get(variante.color_id)?.nombre
  return [variante.presentacion || 'la presentación', color].filter(Boolean).join(' · ')
}

// Una fila nueva con la unidad de la última (lo normal es cargar varias
// presentaciones parecidas seguidas).
function agregarPresentacion () {
  const ultima = form.producto.variantes.at(-1)
  const variante = nuevaVariante({ unidad_id: ultima?.unidad_id ?? null })
  refrescarSku(variante)
  form.producto.variantes.push(variante)
}

function quitar (i) {
  if (abierta.value === form.producto.variantes[i].uid) abierta.value = null
  form.producto.variantes.splice(i, 1)
}

watch(() => form.producto.maneja_lotes, (conLotes) => {
  if (conLotes) form.producto.variantes.forEach((v) => { if (!v.id) v.stock_inicial = '' })
})

const tieneStock = computed(() => form.producto.variantes.some((v) => Number(v.stock) !== 0))

// ── Stock inicial (sólo variantes nuevas) ──
const hayNuevas = computed(() => form.producto.variantes.some((v) => !v.id))

// Nuevas con stock inicial (con su índice, para los errores por fila).
const conStockInicial = computed(() => form.producto.variantes
  .map((variante, i) => ({ variante, i }))
  .filter(({ variante }) => !variante.id && Number(variante.stock_inicial) > 0))

// Mínimo del selector de fecha, en la fecha local (no la UTC de toISOString).
const hoy = (() => {
  const d = new Date()
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`
})()
const cantidadParaTodas = ref('')

function ponerATodas () {
  if (cantidadParaTodas.value === '') return
  form.producto.variantes.filter((v) => !v.id).forEach((v) => { v.stock_inicial = String(cantidadParaTodas.value) })
}

const unidadesIniciales = computed(() =>
  form.producto.variantes.filter((v) => !v.id).reduce((s, v) => s + (Number(v.stock_inicial) || 0), 0))

// Con el costo propio de cada variante o, si no tiene, el general.
const costoTotal = computed(() => form.producto.variantes
  .filter((v) => !v.id && Number(v.stock_inicial) > 0)
  .reduce((s, v) => s + Number(v.stock_inicial) * Number(v.costo_unitario || form.producto.costo_compra || 0), 0))

// ── Fotos por variante ──
// La variante con el panel de fotos abierto (una a la vez).
const abierta = ref(null)

// Una guardada viaja por id (el backend duplica el archivo); una nueva viaja
// como el mismo File otra vez. Objetos nuevos: cada galería edita su lista.
function copiarFotos (archivos) {
  return archivos.map((foto) => ({ ...foto }))
}

function copiarFotosATodas (variante) {
  form.producto.variantes
    .filter((v) => v !== variante)
    .forEach((otra) => { otra.archivos = copiarFotos(variante.archivos) })
}

// ── Carga ──
// Lo que da ArchivoResource; al guardar viaja sólo el id (lo demás se ignora).
function aFoto ({ id, url, miniatura_url: miniaturaUrl, nombre, ancho, alto }) {
  return { id, url, miniatura_url: miniaturaUrl, nombre, ancho, alto }
}

onMounted(async () => {
  const todos = { params: { rowsPerPage: 0 } }

  const [catalogoCategorias, catalogoMarcas, catalogoUnidades, catalogoColores, catalogoSedes, producto] = await Promise.all([
    CategoriaService.getData(todos),
    MarcaService.getData({ params: { rowsPerPage: 0, order_by: 'nombre' } }),
    UnidadService.getData({ params: { rowsPerPage: 0, order_by: 'nombre' } }),
    ColorService.getData({ params: { rowsPerPage: 0, order_by: 'nombre' } }),
    SedeService.activas(),
    props.id ? ProductoService.get(props.id) : null
  ])

  categorias.value = catalogoCategorias.data
  marcas.value = catalogoMarcas.data
  unidades.value = catalogoUnidades.data
  colores.value = catalogoColores.data
  sedes.value = catalogoSedes
  cargando.value = false

  if (!producto) {
    // Con una sola marca (lo normal al empezar), ya viene elegida.
    if (opcionesMarcas.value.length === 1) form.producto.marca_id = opcionesMarcas.value[0].value
    return
  }

  const { nombre, categoria_id: categoriaId, marca_id: marcaId, descripcion, precio, activo, maneja_lotes: manejaLotes, archivos, variantes } = producto

  form.setData({
    _method: 'PUT',
    [PATH]: {
      nombre,
      categoria_id: categoriaId,
      marca_id: marcaId,
      descripcion: descripcion ?? '',
      precio: String(precio),
      activo,
      maneja_lotes: manejaLotes,
      costo_compra: '',
      referencia_compra: '',
      archivos: archivos.map(aFoto),
      variantes: variantes.map((v) => nuevaVariante({
        id: v.id,
        presentacion: v.presentacion,
        unidad_id: v.unidad_id,
        color_id: v.color_id,
        sku: v.sku,
        precio: v.precio ?? '',
        stock: v.stock,
        stocks: v.stocks ?? [],
        // Lo que viaja: cómo la vende cada sede (las que faltan las completa
        // completarSedes(), sin vender).
        sedes: (v.stocks ?? []).map((s) => ({
          sede_id: s.sede_id,
          activo: s.activo,
          precio: s.precio ?? '',
          stock_minimo: formatearCantidad(s.stock_minimo) ?? '0'
        })),
        con_movimientos: v.con_movimientos ?? false,
        archivos: v.archivos.map(aFoto),
        // Si el SKU guardado no es el que se sugeriría, lo escribieron a mano.
        skuManual: v.sku !== sugerirSku(nombre, v.presentacion, v.color?.nombre)
      }))
    }
  })
})

// Las vistas previas de fotos nuevas (blob:) se liberan al cerrar el form.
// Acá y no en AppFotos: la misma foto puede estar copiada en varias galerías.
onBeforeUnmount(() => {
  const todas = [...form.producto.archivos, ...form.producto.variantes.flatMap((v) => v.archivos)]
  new Set(todas.map((f) => f.url).filter((url) => url?.startsWith('blob:')))
    .forEach((url) => URL.revokeObjectURL(url))
})

async function submit () {
  try {
    const respuesta = await form.submit()
    form.reset()
    emit('save', respuesta?.data)
  } catch {
    // 422: los errores quedan en form.errors y se ven en cada campo.
  }
}

defineExpose({ form, submit })
</script>

<style lang="scss" scoped>
.producto-form {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.producto-form__row {
  display: flex;
  flex-wrap: wrap;
  gap: 16px;

  &--end {
    align-items: flex-end;
  }
}

.producto-form__grow {
  flex: 1 1 240px;
  min-width: 0;
}

.producto-form__precio {
  flex: 0 1 200px;
}

.producto-form__toggle {
  padding-bottom: 4px;
  font-size: 13.5px;
  color: var(--app-ink);
}

.producto-form__field {
  display: flex;
  flex-direction: column;
  gap: 7px;
}

.producto-form__label {
  font-size: 12.5px;
  font-weight: 600;
  letter-spacing: -0.1px;
  color: var(--app-ink);
}

.producto-form__icon {
  color: var(--app-ink-2);
}

// Mismo radio, borde y foco que AppTextField.
.producto-form__control {
  :deep(.q-field__control) {
    border-radius: 10px;
    background: var(--app-surface);
    transition: border-color 0.15s ease, box-shadow 0.15s ease;
  }

  :deep(.q-field__control):before {
    border-color: var(--app-border-control);
  }

  :deep(.q-field__control):hover:before {
    border-color: var(--app-border-control-hover);
  }

  &.q-field--focused :deep(.q-field__control) {
    box-shadow: 0 0 0 3px rgba($primary, 0.12);
  }
}

// ── Secciones ──
.producto-form__seccion {
  display: flex;
  flex-direction: column;
  gap: 12px;
  padding-top: 16px;
  border-top: 1px solid var(--app-border-subtle);
}

.producto-form__title {
  display: flex;
  align-items: center;
  gap: 8px;
  margin: 0;
  font-size: 14px;
  font-weight: 600;
  line-height: 1.3;
  color: var(--app-ink);
}

.producto-form__count {
  padding: 1px 8px;
  border-radius: 999px;
  font-size: 12px;
  background: var(--app-border-subtle);
  color: var(--app-ink-2);
}

.producto-form__hint,
.producto-form__vacio {
  margin: 4px 0 0;
  font-size: 12px;
  line-height: 1.5;
  color: var(--app-ink-2);
}

.producto-form__vacio {
  padding: 16px;
  border: 1px dashed var(--app-border-control);
  border-radius: 10px;
  text-align: center;
}

.producto-form__error {
  margin: 0;
  font-size: 12px;
  color: var(--q-negative);
}

.producto-form__seccionHead {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-start;
  justify-content: space-between;
  gap: 12px;
}

// En pantallas angostas la tabla se desplaza sola en vez de apretarse.
.producto-form__tablaWrap {
  overflow-x: auto;
}

.producto-form__tabla {
  display: flex;
  flex-direction: column;
  gap: 8px;
  min-width: 880px;
}

.producto-form__fila {
  display: grid;
  grid-template-columns: 1.6fr 1.2fr 1.2fr 1.6fr 1fr 48px 92px 36px;
  align-items: start;
  gap: 8px;

  &--head {
    font-size: 12px;
    font-weight: 600;
    color: var(--app-ink-2);
  }
}

.producto-form__sku :deep(input) {
  font-family: $font-mono;
  text-transform: uppercase;
}

.producto-form__inicial {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.producto-form__costo :deep(input) {
  font-size: 11.5px;
}

.producto-form__sedes {
  display: flex;
  flex-direction: column;
  gap: 12px;
  padding: 14px;
  border: 1px solid var(--app-border-subtle);
  border-radius: 12px;
}

.producto-form__sede {
  padding-top: 10px;
  border-top: 1px solid var(--app-border-subtle);
}

.producto-form__sedeHead {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 6px;
}

.producto-form__sedeNombre {
  font-size: 13.5px;
  font-weight: 600;
  color: var(--app-ink);
}

.producto-form__sedeFilas {
  display: flex;
  flex-direction: column;
  gap: 6px;
  overflow-x: auto;
}

.producto-form__sedeFila {
  display: grid;
  grid-template-columns: minmax(200px, 2fr) 120px 110px 70px;
  align-items: center;
  gap: 8px;
  min-width: 520px;
  padding-left: 22px;

  &--head {
    font-size: 12px;
    font-weight: 600;
    color: var(--app-ink-2);
  }
}

.producto-form__sedeStock {
  color: var(--app-ink-2);
}

.producto-form__stockInicial {
  display: flex;
  flex-direction: column;
  gap: 12px;
  padding: 14px;
  border: 1px dashed rgba($primary, 0.45);
  border-radius: 12px;
  background: rgba($primary, 0.03);
}

.producto-form__stockTitulo {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 6px;
  font-size: 13px;
  font-weight: 600;
  color: var(--app-ink);
}

.producto-form__lotes {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.producto-form__lotesCabecera,
.producto-form__loteFila {
  display: grid;
  grid-template-columns: minmax(0, 1.4fr) minmax(0, 1fr) 170px;
  align-items: center;
  gap: 10px;
}

.producto-form__lotesCabecera {
  font-size: 12px;
  font-weight: 600;
  color: var(--app-ink-2);
}

.producto-form__loteNombre {
  font-size: 13px;
  font-weight: 600;
  color: var(--app-ink);
}

@media (max-width: 599px) {
  .producto-form__lotesCabecera {
    display: none;
  }

  .producto-form__loteFila {
    grid-template-columns: 1fr 1fr;

    .producto-form__loteNombre {
      grid-column: 1 / -1;
    }
  }
}

.producto-form__rellenar {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 8px;
  font-size: 13px;
  color: var(--app-ink-2);
}

.producto-form__rellenarInput {
  width: 80px;
}

.producto-form__stock {
  padding-top: 9px;
  text-align: right;
  color: var(--app-ink-2);
}

.producto-form__swatch {
  display: inline-block;
  width: 16px;
  height: 16px;
  border: 1px solid var(--app-border-subtle);
  border-radius: 4px;
}

// ── Fotos por variante ──
.producto-form__fotosBtn {
  position: relative;
  display: flex;
  align-items: center;
  justify-content: center;
  width: 40px;
  height: 40px;
  padding: 0;
  border: 1px solid var(--app-border-control);
  border-radius: 10px;
  overflow: hidden;
  background: var(--app-surface);
  color: var(--app-ink-2);
  cursor: pointer;

  &:hover {
    border-color: var(--app-border-control-hover);
  }

  &:focus-visible {
    outline: 2px solid $primary;
    outline-offset: 2px;
  }

  &--abierto {
    border-color: $primary;
    box-shadow: 0 0 0 3px rgba($primary, 0.12);
  }

  &--error {
    border-color: var(--q-negative);
  }
}

.producto-form__fotosThumb {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.producto-form__fotosCount {
  position: absolute;
  right: 2px;
  bottom: 2px;
  min-width: 16px;
  padding: 0 4px;
  border-radius: 999px;
  font-size: 10px;
  font-weight: 600;
  line-height: 16px;
  background: rgba(0, 0, 0, 0.6);
  color: #FFFFFF;
}

.producto-form__panelFotos {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 8px;
  padding: 12px;
  border: 1px solid var(--app-border-subtle);
  border-radius: 10px;
}

</style>
