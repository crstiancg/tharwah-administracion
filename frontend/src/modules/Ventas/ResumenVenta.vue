<template>
  <!-- Lo que se va a cobrar, completo y legible, antes de descontar stock. -->
  <div class="resumen">
    <div class="resumen__datos">
      <div>
        <span class="resumen__etiqueta">Cliente</span>
        <strong>{{ pos.cliente?.nombre ?? 'Cliente varios' }}</strong>
        <span
          v-if="pos.cliente?.numero_documento"
          class="resumen__meta"
        >{{ pos.cliente.tipo_documento }} {{ pos.cliente.numero_documento }}</span>
      </div>
      <div>
        <span class="resumen__etiqueta">Comprobante</span>
        <strong>{{ COMPROBANTES[pos.comprobante] }}</strong>
      </div>
      <div>
        <span class="resumen__etiqueta">Sede</span>
        <strong>{{ sede }}</strong>
      </div>
    </div>

    <div class="resumen__tablaWrap">
      <table class="resumen__tabla">
        <thead>
          <tr>
            <th>Producto</th>
            <th class="text-right">
              Cant.
            </th>
            <th class="text-right">
              P. unit.
            </th>
            <th class="text-right">
              Importe
            </th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="item in pos.items"
            :key="item.variante_id"
          >
            <td>
              <div class="resumen__producto">
                {{ item.nombre }}
              </div>
              <div class="resumen__meta">
                {{ item.presentacion }}<template v-if="item.color">
                  · {{ item.color.nombre }}
                </template> · <span class="text-mono">{{ item.sku }}</span>
              </div>
            </td>
            <td class="text-right text-mono resumen__cant">
              {{ item.cantidad }}
            </td>
            <td class="text-right text-mono">
              {{ formatearPrecio(item.precio_unitario) }}
            </td>
            <td class="text-right text-mono resumen__importe">
              {{ formatearPrecio(item.cantidad * Number(item.precio_unitario || 0)) }}
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <div class="resumen__pie">
      <!-- Cómo paga -->
      <div class="resumen__pagos">
        <span class="resumen__etiqueta">Pago</span>
        <div
          v-for="pago in pos.pagos"
          :key="pago.uid"
          class="resumen__pago"
        >
          <span>{{ METODO[pago.metodo] }}</span>
          <span class="text-mono">{{ formatearPrecio(pago.monto) }}</span>
          <template v-if="pago.metodo === 'efectivo' && pago.recibido !== '' && pago.recibido !== null">
            <span class="resumen__meta">Paga con {{ formatearPrecio(pago.recibido) }}</span>
            <span
              v-if="Number(pago.recibido) > Number(pago.monto)"
              class="resumen__vuelto"
            >Vuelto <strong class="text-mono">{{ formatearPrecio(Number(pago.recibido) - Number(pago.monto)) }}</strong></span>
          </template>
          <span
            v-else-if="pago.referencia"
            class="resumen__meta"
          >Op. {{ pago.referencia }}</span>
        </div>
      </div>

      <dl class="resumen__totales">
        <template v-if="Number(pos.descuento)">
          <dt>Subtotal</dt>
          <dd class="text-mono">
            {{ formatearPrecio(pos.subtotal) }}
          </dd>
          <dt>Descuento</dt>
          <dd class="text-mono">
            −{{ formatearPrecio(pos.descuento) }}
          </dd>
        </template>
        <dt>Op. gravada</dt>
        <dd class="text-mono">
          {{ formatearPrecio(igv.opGravada) }}
        </dd>
        <dt>{{ ETIQUETA_IGV }}</dt>
        <dd class="text-mono">
          {{ formatearPrecio(igv.igv) }}
        </dd>
        <dt class="resumen__total">
          Total
        </dt>
        <dd class="resumen__total text-mono">
          {{ formatearPrecio(pos.total) }}
        </dd>
      </dl>
    </div>

    <p class="resumen__aviso">
      Al confirmar se registra la venta y se descuenta el stock de {{ sede }}.
      <strong>Enter</strong> o <strong>F9</strong> confirma · <strong>Esc</strong> vuelve.
    </p>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { METODOS } from '@/modules/Caja/constantes'
import { usePosStore } from '@/stores/pos-store'
import { useUserStore } from '@/stores/user-store'
import { formatearPrecio } from '@/utils/moneda'
import { desglosarIgv, ETIQUETA_IGV } from '@/utils/igv'

/**
 * El resumen de la venta del punto de venta antes de cobrar: todos los
 * productos (en el carrito a veces no entran), cliente, comprobante, pago y
 * vuelto. Lo muestra CarritoPanel; confirmar es suyo.
 */
const pos = usePosStore()
const userStore = useUserStore()

const sede = computed(() => userStore.sede?.nombre ?? 'tu sede')
const igv = computed(() => desglosarIgv(pos.total))

const COMPROBANTES = { ticket: 'Ticket', boleta: 'Boleta electrónica', factura: 'Factura electrónica' }
const METODO = Object.fromEntries(METODOS.map((m) => [m.value, m.label]))
</script>

<style lang="scss" scoped>
.resumen {
  display: flex;
  flex-direction: column;
  gap: 14px;
}

.resumen__datos {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
  gap: 10px;

  > div {
    display: flex;
    flex-direction: column;
    padding: 8px 12px;
    border-radius: 8px;
    background: var(--app-page);
    font-size: 14px;
    color: var(--app-ink);
  }
}

.resumen__etiqueta {
  font-size: 11px;
  font-weight: 600;
  letter-spacing: 0.4px;
  text-transform: uppercase;
  color: var(--app-ink-2);
}

.resumen__meta {
  font-size: 12px;
  color: var(--app-ink-2);
}

.resumen__tablaWrap {
  overflow-y: auto;
  max-height: 46vh;
  border: 1px solid var(--app-border-subtle);
  border-radius: 10px;
}

.resumen__tabla {
  width: 100%;
  border-collapse: collapse;

  th {
    position: sticky;
    top: 0;
    padding: 8px 12px;
    background: var(--app-page);
    font-size: 12px;
    font-weight: 600;
    text-align: left;
    color: var(--app-ink-2);
  }

  td {
    padding: 8px 12px;
    border-top: 1px solid var(--app-border-subtle);
    font-size: 14px;
    color: var(--app-ink);
  }

  .text-right {
    text-align: right;
  }
}

.resumen__producto {
  font-weight: 600;
}

.resumen__cant {
  font-size: 16px !important;
  font-weight: 700;
}

.resumen__importe {
  font-weight: 700;
}

.resumen__pie {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-start;
  justify-content: space-between;
  gap: 16px;
}

.resumen__pagos {
  display: flex;
  flex-direction: column;
  gap: 4px;
  font-size: 14px;
  color: var(--app-ink);
}

.resumen__pago {
  display: flex;
  flex-wrap: wrap;
  align-items: baseline;
  gap: 4px 12px;
}

.resumen__vuelto {
  font-size: 14px;

  strong {
    font-size: 18px;
    color: $primary;
  }
}

.resumen__totales {
  display: grid;
  grid-template-columns: auto 140px;
  align-items: baseline;
  gap: 4px 16px;
  margin: 0;
  font-size: 13px;

  dt {
    color: var(--app-ink-2);
  }

  dd {
    margin: 0;
    text-align: right;
    color: var(--app-ink);
  }
}

.resumen__total {
  padding-top: 6px;
  font-size: 15px;
  font-weight: 700;
  color: var(--app-ink) !important;

  &.text-mono {
    font-size: 26px;
    font-weight: 800;
  }
}

.resumen__aviso {
  margin: 0;
  font-size: 12.5px;
  color: var(--app-ink-2);
}
</style>
