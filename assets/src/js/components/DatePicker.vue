<script setup>
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';
import flatpickr from 'flatpickr';
import 'flatpickr/dist/flatpickr.min.css';

const props = defineProps({ modelValue: String, disabled: Boolean });
const emit = defineEmits(['update:modelValue']);
const input = ref(null);
let picker;

onMounted(() => {
  picker = flatpickr(input.value, {
    dateFormat: 'Y-m-d',
    altInput: true,
    altFormat: 'j F Y',
    allowInput: false,
    disableMobile: true,
    clickOpens: !props.disabled,
    defaultDate: props.modelValue || null,
    onChange: (_, dateString) => emit('update:modelValue', dateString),
    onReady: (_, __, instance) => instance.altInput?.setAttribute('aria-label', 'Target launch date'),
  });
  if (picker.altInput) picker.altInput.disabled = props.disabled;
});

watch(() => props.modelValue, (value) => {
  if (picker && value !== input.value.value) picker.setDate(value || null, false);
});

watch(() => props.disabled, (disabled) => {
  if (!picker) return;
  picker.set('clickOpens', !disabled);
  if (picker.altInput) picker.altInput.disabled = disabled;
  if (disabled) picker.close();
});

function openPicker() {
  if (!props.disabled) picker?.open();
}

onBeforeUnmount(() => picker?.destroy());
</script>

<template>
  <div class="date-picker" :class="{ disabled }" @click="openPicker">
    <input ref="input" type="text">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M8 3v4M16 3v4M3 10h18"/></svg>
  </div>
</template>
