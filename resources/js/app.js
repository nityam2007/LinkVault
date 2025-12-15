import './bootstrap';
import { createApp } from 'vue';
import { createRouter, createWebHistory } from 'vue-router';
import { createPinia } from 'pinia';
import App from './App.vue';

// Import routes
import routes from './routes';

// Create router
const router = createRouter({
    history: createWebHistory(),
    routes,
});

// Create Pinia store
const pinia = createPinia();

// Create app
const app = createApp(App);

app.use(router);
app.use(pinia);

app.mount('#app');
