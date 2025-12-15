<template>
  <div v-if="loading" class="flex items-center justify-center py-24">
    <svg class="animate-spin h-8 w-8 text-blue-600" fill="none" viewBox="0 0 24 24">
      <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
      <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
    </svg>
  </div>

  <div v-else-if="!collection" class="text-center py-24">
    <h2 class="text-xl font-semibold text-gray-900 dark:text-white mb-2">Collection not found</h2>
    <router-link to="/collections" class="btn btn-primary mt-4">Back to Collections</router-link>
  </div>

  <div v-else class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
      <div class="flex items-center">
        <button @click="$router.back()" class="mr-4 p-2 rounded hover:bg-gray-100 dark:hover:bg-gray-700">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
          </svg>
        </button>
        <div>
          <div class="flex items-center">
            <span 
              class="w-4 h-4 rounded-full mr-3"
              :style="{ backgroundColor: collection.color || '#6B7280' }"
            ></span>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ collection.name }}</h1>
          </div>
          <p v-if="collection.description" class="text-gray-500 mt-1">{{ collection.description }}</p>
        </div>
      </div>
      
      <button @click="showAddBookmark = true" class="btn btn-primary">
        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
        </svg>
        Add Bookmark
      </button>
    </div>

    <!-- Bookmarks -->
    <div v-if="bookmarks.length === 0" class="text-center py-16 bg-white dark:bg-gray-800 rounded-lg">
      <svg class="w-12 h-12 mx-auto text-gray-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z" />
      </svg>
      <p class="text-gray-500">No bookmarks in this collection</p>
    </div>

    <div v-else class="bookmark-grid">
      <BookmarkCard 
        v-for="bookmark in bookmarks" 
        :key="bookmark.id"
        :bookmark="bookmark"
        @click="$router.push(`/bookmarks/${bookmark.id}`)"
        @delete="deleteBookmark"
      />
    </div>

    <!-- Pagination -->
    <div v-if="pagination.lastPage > 1" class="flex items-center justify-center gap-2">
      <button 
        @click="fetchBookmarks(pagination.currentPage - 1)"
        :disabled="pagination.currentPage === 1"
        class="btn btn-secondary"
      >Previous</button>
      <span class="text-sm text-gray-500">Page {{ pagination.currentPage }} of {{ pagination.lastPage }}</span>
      <button 
        @click="fetchBookmarks(pagination.currentPage + 1)"
        :disabled="pagination.currentPage === pagination.lastPage"
        class="btn btn-secondary"
      >Next</button>
    </div>

    <BookmarkModal 
      v-model="showAddBookmark" 
      :bookmark="{ collection_id: collection.id }"
      @saved="onBookmarkSaved" 
    />
  </div>
</template>

<script setup>
import { ref, reactive, onMounted, watch } from 'vue';
import { useRoute } from 'vue-router';
import { useBookmarkStore } from '../stores/bookmarks';
import BookmarkCard from '../components/BookmarkCard.vue';
import BookmarkModal from '../components/BookmarkModal.vue';
import axios from 'axios';

const route = useRoute();
const bookmarkStore = useBookmarkStore();

const loading = ref(true);
const showAddBookmark = ref(false);
const collection = ref(null);
const bookmarks = ref([]);
const pagination = reactive({ currentPage: 1, lastPage: 1, total: 0 });

const fetchCollection = async () => {
  try {
    const response = await axios.get(`/api/v1/collections/${route.params.id}`);
    collection.value = response.data.collection || response.data.data || response.data;
  } catch (error) {
    console.error('Failed to fetch collection:', error);
  }
};

const fetchBookmarks = async (page = 1) => {
  loading.value = true;
  try {
    const response = await axios.get('/api/v1/bookmarks', {
      params: { collection_id: route.params.id, page, per_page: 50 }
    });
    bookmarks.value = response.data.bookmarks || response.data.data || [];
    const paginationData = response.data.pagination || response.data.meta || {};
    pagination.currentPage = paginationData.current_page || 1;
    pagination.lastPage = paginationData.last_page || 1;
    pagination.total = paginationData.total || 0;
  } finally {
    loading.value = false;
  }
};

const deleteBookmark = async (id) => {
  if (confirm('Delete this bookmark?')) {
    await bookmarkStore.deleteBookmark(id);
    bookmarks.value = bookmarks.value.filter(b => b.id !== id);
  }
};

const onBookmarkSaved = () => {
  fetchBookmarks(pagination.currentPage);
};

const loadCollectionData = async () => {
  loading.value = true;
  collection.value = null;
  bookmarks.value = [];
  pagination.currentPage = 1;
  pagination.lastPage = 1;
  pagination.total = 0;
  
  await fetchCollection();
  await fetchBookmarks();
};

// Watch for route changes to reload data when navigating between collections
watch(
  () => route.params.id,
  (newId, oldId) => {
    if (newId && newId !== oldId) {
      loadCollectionData();
    }
  }
);

onMounted(() => {
  loadCollectionData();
});
</script>
