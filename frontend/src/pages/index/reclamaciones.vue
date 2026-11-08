<template>
  <q-page>
    <ReclamacionesList />
  </q-page>
</template>

<script setup>
import ReclamacionesList from '@/modules/Reclamaciones/ReclamacionesList.vue'

definePage({ meta: { permiso: 'reclamaciones.index' } })
</script>
