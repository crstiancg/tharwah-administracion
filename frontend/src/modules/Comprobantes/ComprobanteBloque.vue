<template>
  <section class="comprobante-bloque">
    <div class="comprobante-bloque__cabecera">
      <q-icon
        name="receipt"
        size="18px"
      />
      <span class="comprobante-bloque__titulo">Comprobante electrónico</span>
    </div>

    <!-- Ya tiene uno -->
    <template v-if="comprobante">
      <div class="comprobante-bloque__fila">
        <div>
          <div class="comprobante-bloque__numero text-mono">
            {{ comprobante.numero }}
          </div>
          <div class="comprobante-bloque__meta">
            {{ comprobante.tipo_label }} · {{ formatearFecha(comprobante.fecha_emision) }}
          </div>
        </div>
        <AppChip
          :status="ESTADOS_SUNAT[comprobante.estado]?.status ?? 'info'"
          :label="ESTADOS_SUNAT[comprobante.estado]?.label ?? comprobante.estado"
        />
      </div>

      <p
        v-if="comprobante.sunat_mensaje"
        :class="['comprobante-bloque__mensaje', `comprobante-bloque__mensaje--${comprobante.estado}`]"
      >
        <template v-if="comprobante.sunat_codigo && comprobante.sunat_codigo !== '0'">
          {{ comprobante.sunat_codigo }} ·
        </template>{{ comprobante.sunat_mensaje }}
      </p>

      <div class="comprobante-bloque__acciones">
        <AppButton
          v-if="comprobante.estado === 'error' && puedeEmitir"
          variant="primary"
          label="Reenviar a SUNAT"
          icon="send"
          :loading="trabajando === 'reenviar'"
          @click="reenviar"
        />
        <AppButton
          v-if="comprobante.tiene_xml"
          variant="tertiary"
          label="XML"
          icon="code"
          @click="descargar('xml')"
        />
        <AppButton
          v-if="comprobante.tiene_cdr"
          variant="tertiary"
          label="CDR"
          icon="verified"
          @click="descargar('cdr')"
        />
      </div>

      <!-- Rechazado: el número quedó usado; se emite otro corrigiendo los datos. -->
      <p
        v-if="comprobante.estado === 'rechazado'"
        class="comprobante-bloque__meta"
      >
        Corregí lo que indica SUNAT (por ejemplo, los datos del cliente) y emití uno nuevo:
      </p>
    </template>

    <p
      v-else
      class="comprobante-bloque__meta"
    >
      Sólo ticket: todavía no se emitió boleta ni factura.
    </p>

    <!-- Emitir: sin comprobante vigente y con el pedido ya vendido. -->
    <div
      v-if="puedeEmitir && vendido && (!comprobante || comprobante.estado === 'rechazado')"
      class="comprobante-bloque__acciones"
    >
      <AppButton
        variant="secondary"
        label="Emitir boleta"
        icon="receipt_long"
        :disable="Boolean(motivoNoPermitido('boleta', pedido.cliente, pedido.total))"
        :loading="trabajando === BOLETA"
        @click="emitir(BOLETA)"
      >
        <q-tooltip v-if="motivoNoPermitido('boleta', pedido.cliente, pedido.total)">
          {{ motivoNoPermitido('boleta', pedido.cliente, pedido.total) }}
        </q-tooltip>
      </AppButton>
      <AppButton
        variant="secondary"
        label="Emitir factura"
        icon="request_page"
        :disable="Boolean(motivoNoPermitido('factura', pedido.cliente, pedido.total))"
        :loading="trabajando === FACTURA"
        @click="emitir(FACTURA)"
      >
        <q-tooltip v-if="motivoNoPermitido('factura', pedido.cliente, pedido.total)">
          {{ motivoNoPermitido('factura', pedido.cliente, pedido.total) }}
        </q-tooltip>
      </AppButton>
    </div>
    <p
      v-else-if="puedeEmitir && !vendido && !comprobante"
      class="comprobante-bloque__meta"
    >
      Se emite cuando el pedido está confirmado.
    </p>
  </section>
</template>

<script setup>
import { computed, ref } from 'vue'
import { useQuasar } from 'quasar'
import AppButton from '@/components/AppButton.vue'
import AppChip from '@/components/AppChip.vue'
import ComprobanteService from '@/services/ComprobanteService'
import { useUserStore } from '@/stores/user-store'
import { BOLETA, ESTADOS_SUNAT, FACTURA, motivoNoPermitido } from './constantes'

/**
 * El comprobante electrónico de un pedido en su detalle: estado ante SUNAT,
 * reenviar el que no llegó, descargar XML y CDR, o emitir boleta / factura si
 * todavía no tiene. Avisa con `cambio` para que el detalle se recargue.
 */
const props = defineProps({
  // PedidoResource con `comprobante` (null = sólo ticket) y `cliente`.
  pedido: {
    type: Object,
    required: true
  }
})

const emit = defineEmits(['cambio'])

const $q = useQuasar()
const userStore = useUserStore()

const comprobante = computed(() => props.pedido.comprobante ?? null)
const vendido = computed(() => ['confirmado', 'entregado'].includes(props.pedido.estado))
// Lo emite y reenvía quien vende o cobra (comprobantes.store es implícito de estos).
const puedeEmitir = computed(() => ['ventas.store', 'pedidos.pagos', 'comprobantes.store'].some((p) => userStore.hasPermission(p)))

const trabajando = ref(null)

const formatoFecha = new Intl.DateTimeFormat('es-PE', { dateStyle: 'short', timeStyle: 'short' })
function formatearFecha (iso) {
  return iso ? formatoFecha.format(new Date(iso)) : ''
}

function avisar (c) {
  const ok = c.estado === 'aceptado'
  $q.notify({
    type: ok ? 'positive' : (c.estado === 'error' ? 'warning' : 'negative'),
    message: ok ? `${c.numero}: aceptada por SUNAT.` : `${c.numero}: ${ESTADOS_SUNAT[c.estado]?.label ?? c.estado}. ${c.sunat_mensaje ?? ''}`,
    position: 'top-right',
    timeout: ok ? 2500 : 6000,
    multiLine: !ok
  })
}

function errorDe (error) {
  const { data } = error.response ?? {}
  return Object.values(data?.errors ?? {})[0]?.[0] ?? data?.message ?? 'No se pudo completar.'
}

async function emitir (tipo) {
  trabajando.value = tipo
  try {
    avisar(await ComprobanteService.emitir(props.pedido.id, tipo))
    emit('cambio')
  } catch (error) {
    $q.notify({ type: 'negative', message: errorDe(error), position: 'top-right', timeout: 5000 })
  } finally {
    trabajando.value = null
  }
}

async function reenviar () {
  trabajando.value = 'reenviar'
  try {
    avisar(await ComprobanteService.reenviar(comprobante.value.id))
    emit('cambio')
  } catch (error) {
    $q.notify({ type: 'negative', message: errorDe(error), position: 'top-right', timeout: 5000 })
  } finally {
    trabajando.value = null
  }
}

async function descargar (archivo) {
  try {
    await ComprobanteService.descargar(comprobante.value, archivo)
  } catch {
    $q.notify({ type: 'negative', message: 'El archivo no está disponible.', position: 'top-right' })
  }
}
</script>

<style lang="scss" scoped>
.comprobante-bloque {
  display: flex;
  flex-direction: column;
  gap: 8px;
  padding: 12px 14px;
  border: 1px solid var(--app-border-subtle);
  border-radius: 10px;
}

.comprobante-bloque__cabecera {
  display: flex;
  align-items: center;
  gap: 6px;
  color: var(--app-ink-2);
}

.comprobante-bloque__titulo {
  font-size: 13px;
  font-weight: 700;
  color: var(--app-ink);
}

.comprobante-bloque__fila {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 10px;
}

.comprobante-bloque__numero {
  font-size: 15px;
  font-weight: 700;
  color: var(--app-ink);
}

.comprobante-bloque__meta {
  margin: 0;
  font-size: 12.5px;
  color: var(--app-ink-2);
}

.comprobante-bloque__mensaje {
  margin: 0;
  padding: 6px 10px;
  border-radius: 8px;
  background: var(--app-page);
  font-size: 12px;
  line-height: 1.45;
  color: var(--app-ink-2);

  &--rechazado {
    background: var(--app-negative-soft);
    color: var(--app-ink);
  }
}

.comprobante-bloque__acciones {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
}
</style>
