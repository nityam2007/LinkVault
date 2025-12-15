<template>
  <div class="min-h-screen bg-gray-50 dark:bg-gray-900">
    <div v-if="loading" class="flex items-center justify-center py-24">
      <svg class="animate-spin h-8 w-8 text-blue-600" fill="none" viewBox="0 0 24 24">
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
      </svg>
    </div>

    <div v-else-if="!collection" class="text-center py-24">
      <h2 class="text-xl font-semibold text-gray-900 dark:text-white mb-2">Collection not found</h2>
      <p class="text-gray-500">This collection may be private or doesn't exist.</p>
    </div>

    <div v-else class="max-w-5xl mx-auto py-8 px-4">
      <!-- Header -->
      <div class="mb-8">
        <div class="flex items-center mb-2">
          <span 
            class="w-5 h-5 rounded-full mr-3"
            :style="{ backgroundColor: collection.color || '#6B7280' }"
          ></span>
          <h1 class="text-3xl font-bold text-gray-900 dark:text-white">{{ collection.name }}</h1>
        </div>
        <p v-if="collection.description" class="text-gray-600 dark:text-gray-400">{{ collection.description }}</p>
        <p class="text-sm text-gray-500 mt-2">{{ bookmarks.length }} bookmarks • Shared by {{ collection.user?.username }}</p>
      </div>

      <!-- Bookmarks -->
      <div v-if="bookmarks.length === 0" class="text-center py-16 bg-white dark:bg-gray-800 rounded-lg">
        <p class="text-gray-500">No bookmarks in this collection</p>
      </div>

      <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        <a 
          v-for="bookmark in bookmarks" 
          :key="bookmark.id"
          :href="bookmark.url"
          target="_blank"
          class="bg-white dark:bg-gray-800 rounded-lg shadow hover:shadow-md transition-shadow p-4"
        >
          <div class="flex items-start">
            <img 
              :src="`https://www.google.com/s2/favicons?domain=${getDomain(bookmark.url)}&sz=32`"
              class="w-6 h-6 rounded mr-3 mt-1"
            />
            <div class="flex-1 min-w-0">
              <h3 class="font-medium text-gray-900 dark:text-white line-clamp-2">{{ bookmark.title || bookmark.url }}</h3>
              <p class="text-sm text-gray-500 truncate">{{ getDomain(bookmark.url) }}</p>
              <p v-if="bookmark.description" class="text-sm text-gray-400 mt-1 line-clamp-2">{{ bookmark.description }}</p>
            </div>
          </div>
        </a>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import axios from 'axios';

const route = useRoute();

const loading = ref(true);
const collection = ref(null);
const bookmarks = ref([]);

const getDomain = (url) => {
  try {
    return new URL(url).hostname.replace('www.', '');
  } catch {
    return url;
  }
};

onMounted(async () => {
  try {
    const response = await axios.get(`/api/v1/public/collections/${route.params.slug}`);
    collection.value = response.data.data.collection;
    bookmarks.value = response.data.data.bookmarks;
  } catch (error) {
    // Collection not found
  } finally {
    loading.value = false;
  }
});
</script>
