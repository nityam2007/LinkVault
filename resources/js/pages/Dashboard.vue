<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Dashboard</h1>
        <p class="text-gray-500">Welcome back, {{ user?.username }}!</p>
      </div>
      <button @click="showAddBookmark = true" class="btn btn-primary">
        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
        </svg>
        Add Bookmark
      </button>
    </div>

    <!-- Stats -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
      <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
        <div class="flex items-center">
          <div class="p-3 bg-blue-100 dark:bg-blue-900 rounded-lg">
            <svg class="w-6 h-6 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z" />
            </svg>
          </div>
          <div class="ml-4">
            <p class="text-sm text-gray-500 dark:text-gray-400">Total Bookmarks</p>
            <p class="text-2xl font-semibold text-gray-900 dark:text-white">{{ stats.totalBookmarks }}</p>
          </div>
        </div>
      </div>
      
      <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
        <div class="flex items-center">
          <div class="p-3 bg-green-100 dark:bg-green-900 rounded-lg">
            <svg class="w-6 h-6 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4" />
            </svg>
          </div>
          <div class="ml-4">
            <p class="text-sm text-gray-500 dark:text-gray-400">Archived</p>
            <p class="text-2xl font-semibold text-gray-900 dark:text-white">{{ stats.archivedBookmarks }}</p>
          </div>
        </div>
      </div>
      
      <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
        <div class="flex items-center">
          <div class="p-3 bg-purple-100 dark:bg-purple-900 rounded-lg">
            <svg class="w-6 h-6 text-purple-600 dark:text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
            </svg>
          </div>
          <div class="ml-4">
            <p class="text-sm text-gray-500 dark:text-gray-400">Collections</p>
            <p class="text-2xl font-semibold text-gray-900 dark:text-white">{{ stats.collections }}</p>
          </div>
        </div>
      </div>
      
      <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
        <div class="flex items-center">
          <div class="p-3 bg-yellow-100 dark:bg-yellow-900 rounded-lg">
            <svg class="w-6 h-6 text-yellow-600 dark:text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />
            </svg>
          </div>
          <div class="ml-4">
            <p class="text-sm text-gray-500 dark:text-gray-400">Tags</p>
            <p class="text-2xl font-semibold text-gray-900 dark:text-white">{{ stats.tags }}</p>
          </div>
        </div>
      </div>
    </div>

    <!-- Recent Bookmarks -->
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow">
      <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Recent Bookmarks</h2>
        <router-link to="/bookmarks" class="text-sm text-blue-600 hover:text-blue-500">
          View all →
        </router-link>
      </div>
      
      <div v-if="loading" class="p-6">
        <div class="space-y-4">
          <div v-for="i in 5" :key="i" class="flex items-center space-x-4">
            <div class="skeleton w-10 h-10 rounded"></div>
            <div class="flex-1 space-y-2">
              <div class="skeleton h-4 w-3/4"></div>
              <div class="skeleton h-3 w-1/2"></div>
            </div>
          </div>
        </div>
      </div>
      
      <div v-else-if="recentBookmarks.length === 0" class="p-12 text-center">
        <svg class="w-12 h-12 mx-auto text-gray-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z" />
        </svg>
        <p class="text-gray-500 mb-4">No bookmarks yet</p>
        <button @click="showAddBookmark = true" class="btn btn-primary">
          Add your first bookmark
        </button>
      </div>
      
      <ul v-else class="divide-y divide-gray-200 dark:divide-gray-700">
        <li 
          v-for="bookmark in recentBookmarks" 
          :key="bookmark.id"
          class="px-6 py-4 hover:bg-gray-50 dark:hover:bg-gray-700 cursor-pointer"
          @click="$router.push(`/bookmarks/${bookmark.id}`)"
        >
          <div class="flex items-center space-x-4">
            <img 
              :src="`https://www.google.com/s2/favicons?domain=${getDomain(bookmark.url)}&sz=32`"
              class="w-8 h-8 rounded"
            />
            <div class="flex-1 min-w-0">
              <p class="text-sm font-medium text-gray-900 dark:text-white truncate">
                {{ bookmark.title || bookmark.url }}
              </p>
              <p class="text-sm text-gray-500 truncate">
                {{ getDomain(bookmark.url) }}
              </p>
            </div>
            <div class="flex items-center space-x-2">
              <span 
                v-if="bookmark.archive_status === 'completed'"
                class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800"
              >
                Archived
              </span>
              <span class="text-xs text-gray-400">
                {{ formatDate(bookmark.created_at) }}
              </span>
            </div>
          </div>
        </li>
      </ul>
    </div>

    <!-- Quick Actions -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
      <router-link to="/import" class="bg-white dark:bg-gray-800 rounded-lg shadow p-6 hover:shadow-md transition-shadow">
        <div class="flex items-center">
          <div class="p-3 bg-indigo-100 dark:bg-indigo-900 rounded-lg">
            <svg class="w-6 h-6 text-indigo-600 dark:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
            </svg>
          </div>
          <div class="ml-4">
            <p class="font-medium text-gray-900 dark:text-white">Import Bookmarks</p>
            <p class="text-sm text-gray-500">From browser or file</p>
          </div>
        </div>
      </router-link>
      
      <router-link to="/collections" class="bg-white dark:bg-gray-800 rounded-lg shadow p-6 hover:shadow-md transition-shadow">
        <div class="flex items-center">
          <div class="p-3 bg-pink-100 dark:bg-pink-900 rounded-lg">
            <svg class="w-6 h-6 text-pink-600 dark:text-pink-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
            </svg>
          </div>
          <div class="ml-4">
            <p class="font-medium text-gray-900 dark:text-white">Manage Collections</p>
            <p class="text-sm text-gray-500">Organize your bookmarks</p>
          </div>
        </div>
      </router-link>
      
      <router-link to="/settings" class="bg-white dark:bg-gray-800 rounded-lg shadow p-6 hover:shadow-md transition-shadow">
        <div class="flex items-center">
          <div class="p-3 bg-gray-100 dark:bg-gray-700 rounded-lg">
            <svg class="w-6 h-6 text-gray-600 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
            </svg>
          </div>
          <div class="ml-4">
            <p class="font-medium text-gray-900 dark:text-white">Settings</p>
            <p class="text-sm text-gray-500">Account & preferences</p>
          </div>
        </div>
      </router-link>
    </div>

    <!-- Add Bookmark Modal -->
    <BookmarkModal v-model="showAddBookmark" @saved="onBookmarkSaved" />
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { useAuthStore } from '../stores/auth';
import { useBookmarkStore } from '../stores/bookmarks';
import BookmarkModal from '../components/BookmarkModal.vue';
import axios from 'axios';

const authStore = useAuthStore();
const bookmarkStore = useBookmarkStore();

const loading = ref(true);
const showAddBookmark = ref(false);
const recentBookmarks = ref([]);
const stats = reactive({
  totalBookmarks: 0,
  archivedBookmarks: 0,
  collections: 0,
  tags: 0,
});

const user = computed(() => authStore.user);

const getDomain = (url) => {
  try {
    return new URL(url).hostname.replace('www.', '');
  } catch {
    return url;
  }
};

const formatDate = (date) => {
  const d = new Date(date);
  const now = new Date();
  const diff = now - d;
  
  if (diff < 60000) return 'Just now';
  if (diff < 3600000) return `${Math.floor(diff / 60000)}m ago`;
  if (diff < 86400000) return `${Math.floor(diff / 3600000)}h ago`;
  if (diff < 604800000) return `${Math.floor(diff / 86400000)}d ago`;
  
  return d.toLocaleDateString();
};

const fetchDashboardData = async () => {
  loading.value = true;
  
  try {
    // Fetch stats
    const statsResponse = await axios.get('/api/v1/dashboard/stats');
    const statsData = statsResponse.data?.stats || statsResponse.data?.data || {};
    stats.totalBookmarks = statsData.total_bookmarks || statsData.totalBookmarks || 0;
    stats.archivedBookmarks = statsData.archived_count || statsData.archivedBookmarks || 0;
    stats.collections = statsData.collection_count || statsData.collections || 0;
    stats.tags = statsData.tag_count || statsData.tags || 0;
    
    // Fetch recent bookmarks
    const bookmarksResponse = await axios.get('/api/v1/bookmarks', {
      params: { per_page: 10, sort_by: 'created_at', sort_dir: 'desc' }
    });
    recentBookmarks.value = bookmarksResponse.data?.bookmarks || bookmarksResponse.data?.data || [];
  } catch (error) {
    console.error('Failed to fetch dashboard data:', error);
  } finally {
    loading.value = false;
  }
};

const onBookmarkSaved = () => {
  fetchDashboardData();
};

onMounted(() => {
  fetchDashboardData();
});
</script>
