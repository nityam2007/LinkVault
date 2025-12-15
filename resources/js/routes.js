// Vue Router routes configuration

export default [
    {
        path: '/',
        name: 'dashboard',
        component: () => import('./pages/Dashboard.vue'),
        meta: { requiresAuth: true }
    },
    {
        path: '/login',
        name: 'login',
        component: () => import('./pages/Login.vue'),
        meta: { guest: true }
    },
    {
        path: '/register',
        name: 'register',
        component: () => import('./pages/Register.vue'),
        meta: { guest: true }
    },
    {
        path: '/bookmarks',
        name: 'bookmarks',
        component: () => import('./pages/Bookmarks.vue'),
        meta: { requiresAuth: true }
    },
    {
        path: '/bookmarks/:id',
        name: 'bookmark-detail',
        component: () => import('./pages/BookmarkDetail.vue'),
        meta: { requiresAuth: true }
    },
    {
        path: '/collections',
        name: 'collections',
        component: () => import('./pages/Collections.vue'),
        meta: { requiresAuth: true }
    },
    {
        path: '/collections/:id',
        name: 'collection-detail',
        component: () => import('./pages/CollectionDetail.vue'),
        meta: { requiresAuth: true }
    },
    {
        path: '/tags',
        name: 'tags',
        component: () => import('./pages/Tags.vue'),
        meta: { requiresAuth: true }
    },
    {
        path: '/import',
        name: 'import',
        component: () => import('./pages/Import.vue'),
        meta: { requiresAuth: true }
    },
    {
        path: '/settings',
        name: 'settings',
        component: () => import('./pages/Settings.vue'),
        meta: { requiresAuth: true }
    },
    {
        path: '/public/collections/:slug',
        name: 'public-collection',
        component: () => import('./pages/PublicCollection.vue')
    },
    {
        path: '/:pathMatch(.*)*',
        name: 'not-found',
        component: () => import('./pages/NotFound.vue')
    }
];
