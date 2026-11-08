<template>
  <!-- En <body> y oculto en pantalla: al imprimir es lo único que sale
       (reglas .documento-impresion en css/app.scss, hoja A4). -->
  <Teleport to="body">
    <div
      v-if="hoja"
      class="documento-impresion"
    >
      <HojaReclamacion :hoja="hoja" />
    </div>
  </Teleport>
</template>

<script setup>
import { nextTick, ref } from 'vue'
import HojaReclamacion from './HojaReclamacion.vue'

/** Imprime la hoja (o la guarda en PDF) con el diálogo del navegador, como la cotización. */
const hoja = ref(null)

async function imprimir (datos) {
  hoja.value = datos
  await nextTick()
  window.print()
}

defineExpose({ imprimir })
</script>
