import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';
export default defineConfig({plugins:[vue()],build:{outDir:'assets/dist',emptyOutDir:true,manifest:true,rollupOptions:{input:'assets/src/js/app.js'}}});
