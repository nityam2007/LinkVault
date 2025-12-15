<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
      <div>
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Bookmarks</h1>
        <p class="text-gray-500">{{ pagination.total }} bookmarks</p>
      </div>
      
      <div class="flex items-center gap-3">
        <!-- View toggle -->
        <div class="flex items-center bg-gray-100 dark:bg-gray-700 rounded-lg p-1">
          <button 
            @click="viewMode = 'grid'"
            class="p-2 rounded"
            :class="viewMode === 'grid' ? 'bg-white dark:bg-gray-600 shadow' : ''"
          >
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" />
            </svg>
          </button>
          <button 
            @click="viewMode = 'list'"
            class="p-2 rounded"
            :class="viewMode === 'list' ? 'bg-white dark:bg-gray-600 shadow' : ''"
          >
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16" />
            </svg>
          </button>
        </div>
        
        <!-- Sort -->
        <select v-model="sortBy" class="form-input w-auto" @change="fetchBookmarks">
          <option value="created_at">Date Added</option>
          <option value="title">Title</option>
          <option value="url">URL</option>
        </select>
        
        <!-- Add button -->
        <button @click="showAddBookmark = true" class="btn btn-primary">
          <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
          </svg>
          Add
        </button>
      </div>
    </div>

    <!-- Filters -->
    <div v-if="hasActiveFilters" class="flex items-center gap-2 flex-wrap">
      <span class="text-sm text-gray-500">Filters:</span>
      <span 
        v-if="filters.search"
        class="inline-flex items-center px-3 py-1 rounded-full text-sm bg-blue-100 text-blue-800"
      >
        Search: "{{ filters.search }}"
        <button @click="clearFilter('search')" class="ml-2 hover:text-blue-600">×</button>
      </span>
      <span 
        v-if="filters.is_favorite"
        class="inline-flex items-center px-3 py-1 rounded-full text-sm bg-yellow-100 text-yellow-800"
      >
        Favorites
        <button @click="clearFilter('is_favorite')" class="ml-2 hover:text-yellow-600">×</button>
      </span>
      <span 
        v-if="filters.is_archived"
        class="inline-flex items-center px-3 py-1 rounded-full text-sm bg-green-100 text-green-800"
      >
        Archived
        <button @click="clearFilter('is_archived')" class="ml-2 hover:text-green-600">×</button>
      </span>
      <span 
        v-for="tagId in filters.tags" 
        :key="tagId"
        class="inline-flex items-center px-3 py-1 rounded-full text-sm bg-purple-100 text-purple-800"
      >
        Tag: {{ getTagName(tagId) }}
        <button @click="removeTagFilter(tagId)" class="ml-2 hover:text-purple-600">×</button>
      </span>
      <button @click="clearAllFilters" class="text-sm text-gray-500 hover:text-gray-700">
        Clear all
      </button>
    </div>

    <!-- Loading state -->
    <div v-if="loading" class="bookmark-grid">
      <div v-for="i in 8" :key="i" class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden">
        <div class="skeleton h-40"></div>
        <div class="p-4 space-y-3">
          <div class="skeleton h-4 w-3/4"></div>
          <div class="skeleton h-3 w-1/2"></div>
        </div>
      </div>
    </div>

    <!-- Empty state -->
    <div v-else-if="!bookmarks || bookmarks.length === 0" class="text-center py-16">
      <svg class="w-16 h-16 mx-auto text-gray-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z" />
      </svg>
      <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-2">No bookmarks found</h3>
      <p class="text-gray-500 mb-4">
        {{ hasActiveFilters ? 'Try adjusting your filters' : 'Add your first bookmark to get started' }}
      </p>
      <button @click="showAddBookmark = true" class="btn btn-primary">
        Add Bookmark
      </button>
    </div>

    <!-- Grid view -->
    <div v-else-if="viewMode === 'grid'" class="bookmark-grid">
      <BookmarkCard 
        v-for="bookmark in bookmarks" 
        :key="bookmark.id"
        :bookmark="bookmark"
        @click="viewBookmark(bookmark)"
        @edit="editBookmark"
        @delete="deleteBookmark"
      />
    </div>

    <!-- List view -->
    <div v-else class="bg-white dark:bg-gray-800 rounded-lg shadow">
      <ul class="bookmark-list">
        <li 
          v-for="bookmark in bookmarks" 
          :key="bookmark.id"
          class="bookmark-list-item px-4 cursor-pointer"
          @click="viewBookmark(bookmark)"
        >
          <img 
            :src="`https://www.google.com/s2/favicons?domain=${getDomain(bookmark.url)}&sz=32`"
            class="w-6 h-6 rounded mr-3"
          />
          <div class="flex-1 min-w-0">
            <p class="font-medium text-gray-900 dark:text-white truncate">{{ bookmark.title || bookmark.url }}</p>
            <p class="text-sm text-gray-500 truncate">{{ getDomain(bookmark.url) }}</p>
          </div>
          <div class="flex items-center gap-2">
            <button 
              v-if="bookmark.is_favorite"
              class="text-yellow-500"
            >
              <svg class="w-5 h-5 fill-current" viewBox="0 0 24 24">
                <path d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z" />
              </svg>
            </button>
            <span 
              v-if="bookmark.archive_status === 'completed'"
              class="text-green-500"
            >
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4" />
              </svg>
            </span>
            <span class="text-xs text-gray-400">{{ formatDate(bookmark.created_at) }}</span>
          </div>
        </li>
      </ul>
    </div>

    <!-- Pagination -->
    <div v-if="pagination.lastPage > 1" class="flex items-center justify-center gap-2">
      <button 
        @click="goToPage(pagination.currentPage - 1)"
        :disabled="pagination.currentPage === 1"
        class="btn btn-secondary"
      >
        Previous
      </button>
      <span class="text-sm text-gray-500">
        Page {{ pagination.currentPage }} of {{ pagination.lastPage }}
      </span>
      <button 
        @click="goToPage(pagination.currentPage + 1)"
        :disabled="pagination.currentPage === pagination.lastPage"
        class="btn btn-secondary"
      >
        Next
      </button>
    </div>

    <!-- Modals -->
    <BookmarkModal 
      v-model="showAddBookmark" 
      :bookmark="editingBookmark"
      @saved="onBookmarkSaved" 
    />
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useBookmarkStore } from '../stores/bookmarks';
import BookmarkCard from '../components/BookmarkCard.vue';
import BookmarkModal from '../components/BookmarkModal.vue';

const route = useRoute();
const router = useRouter();
const bookmarkStore = useBookmarkStore();

const loading = ref(true);
const showAddBookmark = ref(false);
const editingBookmark = ref(null);
const viewMode = ref(localStorage.getItem('viewMode') || 'grid');
const sortBy = ref('created_at');

// Initialize state before any watchers use them
const bookmarks = ref([]);
const pagination = reactive({
  currentPage: 1,
  lastPage: 1,
  perPage: 50,
  total: 0,
});

const filters = reactive({
  search: '',
  collection_id: null,
  tags: [],
  is_favorite: false,
  is_archived: false,
});

const hasActiveFilters = computed(() => {
  return filters.search || filters.is_favorite || filters.is_archived || (filters.tags && filters.tags.length > 0);
});

// Define functions BEFORE watchers that use them
const getDomain = (url) => {
  try {
    return new URL(url).hostname.replace('www.', '');
  } catch {
    return url;
  }
};

const formatDate = (date) => {
  return new Date(date).toLocaleDateString();
};

const fetchBookmarks = async (page = 1) => {
  loading.value = true;
  
  try {
    await bookmarkStore.setFilter('search', filters.search);
    await bookmarkStore.setFilter('collectionId', filters.collection_id);
    await bookmarkStore.setFilter('tags', filters.tags.length > 0 ? filters.tags : null);
    await bookmarkStore.setFilter('isFavorite', filters.is_favorite || null);
    await bookmarkStore.setFilter('isArchived', filters.is_archived || null);
    await bookmarkStore.setFilter('sortBy', sortBy.value);
    
    await bookmarkStore.fetchBookmarks(page);
    
    bookmarks.value = bookmarkStore.bookmarks;
    Object.assign(pagination, bookmarkStore.pagination);
  } finally {
    loading.value = false;
  }
};

// Watch route query changes (fetchBookmarks must be defined above)
watch(() => route.query, (query) => {
  filters.search = query.q || '';
  filters.is_favorite = query.is_favorite === 'true';
  filters.is_archived = query.is_archived === 'true';
  filters.collection_id = query.collection_id || null;
  filters.tags = query.tags ? query.tags.split(',') : [];
  fetchBookmarks();
}, { immediate: true });

// Watch view mode
watch(viewMode, (mode) => {
  localStorage.setItem('viewMode', mode);
});

const goToPage = (page) => {
  fetchBookmarks(page);
};

const viewBookmark = (bookmark) => {
  router.push(`/bookmarks/${bookmark.id}`);
};

const editBookmark = (bookmark) => {
  editingBookmark.value = bookmark;
  showAddBookmark.value = true;
};

const deleteBookmark = async (id) => {
  if (confirm('Are you sure you want to delete this bookmark?')) {
    await bookmarkStore.deleteBookmark(id);
    bookmarks.value = bookmarks.value.filter(b => b.id !== id);
  }
};

const clearFilter = (key) => {
  const query = { ...route.query };
  delete query[key === 'search' ? 'q' : key];
  router.push({ query });
};

const removeTagFilter = (tagId) => {
  const newTags = filters.tags.filter(t => t !== tagId && t !== String(tagId));
  const query = { ...route.query };
  if (newTags.length > 0) {
    query.tags = newTags.join(',');
  } else {
    delete query.tags;
  }
  router.push({ query });
};

const getTagName = (tagId) => {
  const tag = bookmarkStore.tags.find(t => t.id === Number(tagId) || t.id === tagId);
  return tag ? tag.name : `#${tagId}`;
};

const clearAllFilters = () => {
  router.push({ path: '/bookmarks' });
};

const onBookmarkSaved = () => {
  editingBookmark.value = null;
  fetchBookmarks(pagination.currentPage);
};

onMounted(async () => {
  await bookmarkStore.fetchCollections();
  await bookmarkStore.fetchTags();
});
</script>
