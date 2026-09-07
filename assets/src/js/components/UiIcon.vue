<script setup>defineProps({ name: { type: String, default: 'check' } });</script>
<template><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
<template v-if="name==='business'"><path d="M3 21h18M5 21V7l7-4 7 4v14M9 10h.01M15 10h.01M9 14h.01M15 14h.01M10 21v-3h4v3"/></template>
<template v-else-if="name==='website'"><circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3c2.3 2.5 3.5 5.5 3.5 9S14.3 18.5 12 21M12 3c-2.3 2.5-3.5 5.5-3.5 9s1.2 6.5 3.5 9"/></template>
<template v-else-if="name==='pages'"><rect x="4" y="3" width="16" height="18" rx="2"/><path d="M8 8h8M8 12h8M8 16h5"/></template>
<template v-else-if="name==='content'"><rect x="3" y="5" width="18" height="14" rx="2"/><circle cx="8.5" cy="10" r="1.5"/><path d="m21 15-4.5-4.5L8 19"/></template>
<template v-else-if="name==='features'"><path d="M8.5 3.5 10 6l-2 2-2.5-1.5L3.5 10 6 11v2l-2.5 1 2 3.5L8 16l2 2-.5 3h5l-.5-3 2-2 2.5 1.5 2-3.5-2.5-1v-2l2.5-1-2-3.5L16 8l-2-2 1.5-2.5Z"/><circle cx="12" cy="12" r="2.5"/></template>
<template v-else-if="name==='hosting'"><rect x="3" y="4" width="18" height="6" rx="2"/><rect x="3" y="14" width="18" height="6" rx="2"/><path d="M7 7h.01M7 17h.01M11 7h6M11 17h6"/></template>
<template v-else-if="name==='final'"><rect x="4" y="5" width="16" height="16" rx="2"/><path d="M8 3v4M16 3v4M4 10h16M8 15l2 2 5-5"/></template>
<template v-else><path d="m5 12 4 4L19 6"/></template></svg></template>

