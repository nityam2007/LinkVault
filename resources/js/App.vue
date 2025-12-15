<template>
  <div class="min-h-screen bg-gray-50 dark:bg-gray-900">
    <!-- Navigation -->
    <nav v-if="isAuthenticated" class="sidebar">
      <div class="p-4">
        <h1 class="text-xl font-bold text-gray-800 dark:text-white">📚 Linkwarden</h1>
      </div>
      
      <!-- Search -->
      <div class="px-4 mb-4">
        <div class="relative">
          <svg class="absolute left-3 top-2.5 w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
          </svg>
          <input 
            type="text" 
            placeholder="Search bookmarks..." 
            class="search-input"
            v-model="searchQuery"
            @keyup.enter="handleSearch"
          />
        </div>
      </div>

      <!-- Navigation Links -->
      <div class="px-2 space-y-1">
        <router-link to="/" class="collection-item" active-class="active">
          <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
          </svg>
          Dashboard
        </router-link>
        
        <router-link to="/bookmarks" class="collection-item" :class="{ 'active': isExactBookmarksRoute }">
          <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z" />
          </svg>
          All Bookmarks
        </router-link>

        <router-link to="/bookmarks?is_favorite=true" class="collection-item" :class="{ 'active': isFavoritesRoute }">
          <svg class="w-5 h-5 mr-3 text-yellow-500" fill="currentColor" viewBox="0 0 24 24">
            <path d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z" />
          </svg>
          Favorites
        </router-link>

        <router-link to="/bookmarks?is_archived=true" class="collection-item" :class="{ 'active': isArchivedRoute }">
          <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4" />
          </svg>
          Archived
        </router-link>
      </div>

      <!-- Collections -->
      <div class="px-4 mt-6">
        <div class="flex items-center justify-between mb-2">
          <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Collections</h3>
          <button @click="showNewCollection = true" class="text-gray-400 hover:text-gray-600">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
            </svg>
          </button>
        </div>
        <CollectionTree :collections="collections || []" />
      </div>

      <!-- Tags -->
      <div class="px-4 mt-6">
        <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">Tags</h3>
        <div class="flex flex-wrap gap-1">
          <span 
            v-for="tag in popularTags" 
            :key="tag.id"
            class="tag-chip cursor-pointer"
            :style="{ backgroundColor: tag.color + '20', color: tag.color }"
            @click="filterByTag(tag)"
          >
            {{ tag.name }}
          </span>
        </div>
      </div>

      <!-- User Menu -->
      <div class="absolute bottom-0 left-0 right-0 p-4 border-t border-gray-200 dark:border-gray-700">
        <div class="flex items-center">
          <div class="w-8 h-8 rounded-full bg-blue-500 flex items-center justify-center text-white font-medium">
            {{ userInitials }}
          </div>
          <div class="ml-3 flex-1">
            <p class="text-sm font-medium text-gray-700 dark:text-gray-200">{{ user?.username }}</p>
          </div>
          <button @click="logout" class="text-gray-400 hover:text-gray-600">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
            </svg>
          </button>
        </div>
      </div>
    </nav>

    <!-- Main Content -->
    <main :class="isAuthenticated ? 'main-content' : ''">
      <router-view />
    </main>

    <!-- Toast Notifications -->
    <div class="toast">
      <TransitionGroup name="toast">
        <div 
          v-for="toast in toasts" 
          :key="toast.id"
          class="toast-item"
          :class="{
            'bg-green-500 text-white': toast.type === 'success',
            'bg-red-500 text-white': toast.type === 'error',
            'bg-blue-500 text-white': toast.type === 'info'
          }"
        >
          {{ toast.message }}
        </div>
      </TransitionGroup>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import { useAuthStore } from './stores/auth';
import { useBookmarkStore } from './stores/bookmarks';
import CollectionTree from './components/CollectionTree.vue';

const router = useRouter();
const route = useRoute();
const authStore = useAuthStore();
const bookmarkStore = useBookmarkStore();

const searchQuery = ref('');
const showNewCollection = ref(false);
const toasts = ref([]);

const isAuthenticated = computed(() => authStore.isAuthenticated);
const user = computed(() => authStore.user);
const collections = computed(() => bookmarkStore.collections);
const popularTags = computed(() => bookmarkStore.popularTags);

// Route active state computed properties
const isExactBookmarksRoute = computed(() => {
  return route.path === '/bookmarks' && !route.query.is_favorite && !route.query.is_archived;
});

const isFavoritesRoute = computed(() => {
  return route.path === '/bookmarks' && route.query.is_favorite === 'true';
});

const isArchivedRoute = computed(() => {
  return route.path === '/bookmarks' && route.query.is_archived === 'true';
});

const userInitials = computed(() => {
  if (!user.value?.username) return '?';
  return user.value.username.substring(0, 2).toUpperCase();
});

const handleSearch = () => {
  if (searchQuery.value) {
    router.push({ path: '/bookmarks', query: { q: searchQuery.value } });
  }
};

const filterByTag = (tag) => {
  router.push({ path: '/bookmarks', query: { tags: tag.id } });
};

const logout = async () => {
  await authStore.logout();
  router.push('/login');
};

onMounted(async () => {
  if (isAuthenticated.value) {
    await bookmarkStore.fetchCollections();
    await bookmarkStore.fetchPopularTags();
  }
});
</script>

<style>
.toast-enter-active,
.toast-leave-active {
  transition: all 0.3s ease;
}

.toast-enter-from {
  opacity: 0;
  transform: translateX(100%);
}

.toast-leave-to {
  opacity: 0;
  transform: translateY(-20px);
}
</style>
