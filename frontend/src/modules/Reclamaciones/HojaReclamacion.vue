<template>
  <!-- La Hoja de Reclamación tal como se imprime (A4) o se guarda en PDF.
       Blanco y negro a propósito, como la cotización. -->
  <article class="rec-doc">
    <header class="rec-doc__cabecera">
      <div>
        <div class="rec-doc__empresa">
          {{ hoja.proveedor.razon_social }}
        </div>
        <div>RUC {{ hoja.proveedor.ruc }}</div>
        <div>{{ hoja.proveedor.direccion }}</div>
      </div>
      <div class="rec-doc__caja">
        <div class="rec-doc__titulo">
          HOJA DE RECLAMACIÓN
        </div>
        <div class="rec-doc__codigo">
          N.° {{ hoja.codigo }}
        </div>
        <div>{{ formatearFechaHora(hoja.presentado_at) }}</div>
      </div>
    </header>

    <section class="rec-doc__bloque">
      <h3>1. Identificación del consumidor</h3>
      <dl>
        <dt>Nombre</dt>
        <dd>{{ c.nombres }} {{ c.apellidos }}</dd>
        <dt>Documento</dt>
        <dd>{{ DOCUMENTOS[c.tipo_documento] }} {{ c.numero_documento }}</dd>
        <template v-if="c.menor_de_edad">
          <dt>Padre, madre o apoderado</dt>
          <dd>{{ c.apoderado }}</dd>
        </template>
        <template v-if="domicilio">
          <dt>Domicilio</dt>
          <dd>{{ domicilio }}</dd>
        </template>
        <template v-if="c.email">
          <dt>Correo</dt>
          <dd>{{ c.email }}</dd>
        </template>
        <template v-if="c.telefono">
          <dt>Teléfono</dt>
          <dd>{{ c.telefono }}</dd>
        </template>
      </dl>
    </section>

    <section class="rec-doc__bloque">
      <h3>2. Identificación del bien contratado</h3>
      <dl>
        <dt>Tipo</dt>
        <dd>{{ b.tipo === 'servicio' ? 'Servicio' : 'Producto' }}</dd>
        <template v-if="b.monto">
          <dt>Monto pagado</dt>
          <dd>{{ formatearPrecio(b.monto) }}</dd>
        </template>
        <dt>Descripción</dt>
        <dd>{{ b.descripcion }}</dd>
        <template v-if="b.canal">
          <dt>Canal de compra</dt>
          <dd>{{ CANALES[b.canal] }}</dd>
        </template>
        <template v-if="b.tienda">
          <dt>Tienda</dt>
          <dd>{{ b.tienda }}</dd>
        </template>
        <template v-if="b.fecha_incidente">
          <dt>Fecha del incidente</dt>
          <dd>{{ fechaCorta(b.fecha_incidente) }}</dd>
        </template>
        <template v-if="b.comprobante_tipo">
          <dt>Comprobante</dt>
          <dd>{{ COMPROBANTES[b.comprobante_tipo] }} {{ b.comprobante_numero ?? '' }}</dd>
        </template>
      </dl>
    </section>

    <section class="rec-doc__bloque">
      <h3>3. Detalle de la reclamación: {{ TIPOS[hoja.tipo] }}</h3>
      <p class="rec-doc__texto">
        {{ hoja.detalle }}
      </p>
      <h4>Pedido del consumidor</h4>
      <p class="rec-doc__texto">
        {{ hoja.pedido }}
      </p>
    </section>

    <section class="rec-doc__bloque">
      <h3>4. Respuesta del proveedor</h3>
      <template v-if="hoja.respuesta">
        <p class="rec-doc__texto">
          {{ hoja.respuesta }}
        </p>
        <p class="rec-doc__pie">
          Respondida el {{ formatearFechaHora(hoja.respondido_at) }}<template v-if="hoja.respondido_por">
            por {{ hoja.respondido_por.name }}
          </template>.
        </p>
      </template>
      <p v-else>
        Pendiente. Fecha límite: <strong>{{ fechaCorta(hoja.fecha_limite) }}</strong> (15 días hábiles, improrrogable).
      </p>
    </section>

    <footer class="rec-doc__pie">
      La formulación del reclamo no impide acudir a otras vías de solución de controversias ni es
      requisito previo para interponer una denuncia ante el INDECOPI.
    </footer>
  </article>
</template>

<script setup>
import { computed } from 'vue'
import { formatearFechaHora } from '@/utils/fechas'
import { formatearPrecio } from '@/utils/moneda'
import { CANALES, COMPROBANTES, DOCUMENTOS, TIPOS, fechaCorta } from './constantes'

const props = defineProps({
  hoja: {
    type: Object,
    required: true
  }
})

const c = computed(() => props.hoja.consumidor)
const b = computed(() => props.hoja.bien)
const domicilio = computed(() => [c.value.direccion, c.value.distrito, c.value.provincia, c.value.departamento].filter(Boolean).join(', '))
</script>

<style lang="scss" scoped>
.rec-doc {
  font-family: Arial, Helvetica, sans-serif;
  font-size: 11pt;
  line-height: 1.4;
  color: #000000;
  background: #FFFFFF;
}

.rec-doc__cabecera {
  display: flex;
  justify-content: space-between;
  gap: 16px;
  padding-bottom: 12px;
  border-bottom: 2px solid #000000;
}

.rec-doc__empresa {
  font-size: 16pt;
  font-weight: 700;
}

.rec-doc__caja {
  min-width: 220px;
  padding: 8px 12px;
  border: 1px solid #000000;
  text-align: center;
}

.rec-doc__titulo {
  font-size: 12pt;
  font-weight: 700;
  letter-spacing: 1px;
}

.rec-doc__codigo {
  font-family: monospace;
  font-size: 13pt;
  font-weight: 700;
}

.rec-doc__bloque {
  padding: 10px 0;
  border-bottom: 1px solid #000000;
  break-inside: avoid;

  h3 {
    margin: 0 0 6px;
    font-size: 11pt;
    font-weight: 700;
  }

  h4 {
    margin: 8px 0 2px;
    font-size: 10pt;
    font-weight: 700;
  }

  p {
    margin: 0;
  }

  dl {
    display: grid;
    grid-template-columns: 190px 1fr;
    gap: 2px 12px;
    margin: 0;
  }

  dt {
    color: #444444;
  }

  dd {
    margin: 0;
  }
}

.rec-doc__texto {
  white-space: pre-line;
}

.rec-doc__pie {
  margin-top: 8px;
  font-size: 9pt;
  color: #444444;
}
</style>
