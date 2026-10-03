<template>
  <form
    class="unidad-form"
    novalidate
    @submit.prevent="submit"
  >
    <AppTextField
      v-model="form.unidad.nombre"
      label="Nombre"
      icon="straighten"
      placeholder="Bolsa, Galón, Kilogramo…"
      maxlength="40"
      :error="form.errors[`${PATH}.nombre`]"
      :loading="form.validating"
      autofocus
      @change="form.validate(`${PATH}.nombre`)"
    />

    <AppTextField
      v-model="form.unidad.abreviatura"
      label="Abreviatura"
      icon="short_text"
      placeholder="BLS, GL, KG…"
      maxlength="10"
      :error="form.errors[`${PATH}.abreviatura`]"
      @change="form.validate(`${PATH}.abreviatura`)"
    />

    <q-toggle
      v-model="form.unidad.fraccionable"
      label="Se vende en fracciones (kg, metros…)"
      color="primary"
      class="unidad-form__toggle"
    />

    <p class="unidad-form__hint">
      La abreviatura sale en tickets y etiquetas. Las unidades fraccionables permiten vender cantidades con decimales.
    </p>

    <button
      type="submit"
      hidden
    />
  </form>
</template>

<script setup>
import { onMounted } from 'vue'
import { useForm } from 'laravel-precognition-vue'
import AppTextField from '@/components/AppTextField.vue'
import UnidadService from '@/services/UnidadService'

const PATH = 'unidad'

const props = defineProps({
  // null = crear; con id = editar.
  id: {
    type: Number,
    default: null
  }
})

const emit = defineEmits(['save'])

// Función y no objeto: useForm guarda estos datos como "originales" para el reset().
const inicial = () => ({ unidad: { nombre: '', abreviatura: '', fraccionable: false } })

const form = props.id
  ? useForm('put', `api/unidades/${props.id}`, inicial)
  : useForm('post', 'api/unidades', inicial)

onMounted(async () => {
  if (!props.id) return

  const { nombre, abreviatura, fraccionable } = await UnidadService.get(props.id)
  form.setData({ [PATH]: { nombre, abreviatura, fraccionable } })
})

async function submit () {
  try {
    await form.submit()
    form.reset()
    emit('save')
  } catch {
    // 422: los errores quedan en form.errors y se ven debajo de cada campo.
  }
}

defineExpose({ form, submit })
</script>

<style lang="scss" scoped>
.unidad-form {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.unidad-form__toggle {
  font-size: 13.5px;
  color: var(--app-ink);
}

.unidad-form__hint {
  margin: -8px 0 0;
  font-size: 12px;
  line-height: 1.5;
  color: var(--app-ink-2);
}
</style>
