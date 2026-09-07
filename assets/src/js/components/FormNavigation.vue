<script setup>defineProps({first:Boolean,last:Boolean,submitting:Boolean});defineEmits(['back','next']);</script>
<template><nav class="form-nav"><button v-if="!first" type="button" class="button ghost" @click="$emit('back')">← Back</button><span v-else></span><button type="button" class="button primary" :disabled="submitting" @click="$emit('next')">{{ submitting?'Submitting…':last?'Submit project details':'Continue' }} <span v-if="!last">→</span></button></nav></template>

