<template>
  <div class="space-y-6">
    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Tags</h1>

    <div v-if="loading" class="flex flex-wrap gap-3">
      <div v-for="i in 12" :key="i" class="skeleton h-8 w-24 rounded-full"></div>
    </div>

    <div v-else-if="tags.length === 0" class="text-center py-16">
      <svg class="w-16 h-16 mx-auto text-gray-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />
      </svg>
      <p class="text-gray-500">No tags yet. Add tags when creating bookmarks.</p>
    </div>

    <div v-else class="flex flex-wrap gap-3">
      <router-link 
        v-for="tag in tags" 
        :key="tag.id"
        :to="`/bookmarks?tags=${tag.id}`"
        class="inline-flex items-center px-4 py-2 rounded-full text-sm font-medium transition-transform hover:scale-105"
        :style="{ backgroundColor: (tag.color || '#6B7280') + '20', color: tag.color || '#6B7280' }"
      >
        #{{ tag.name }}
        <span class="ml-2 px-2 py-0.5 rounded-full bg-white/50 text-xs">
          {{ tag.bookmarks_count }}
        </span>
      </router-link>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useBookmarkStore } from '../stores/bookmarks';

const bookmarkStore = useBookmarkStore();
const loading = ref(true);
const tags = ref([]);

onMounted(async () => {
  await bookmarkStore.fetchTags();
  tags.value = bookmarkStore.tags;
  loading.value = false;
});
</script>
