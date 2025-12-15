<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
      <div>
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Bookmarks</h1>
        <p class="text-gray-500">{{ pagination.total }} bookmarks</p>
      </div>
      
      <div class="flex items-center gap-3">
        <!-- Keyboard Shortcuts Help -->
        <button 
          @click="showShortcuts = true"
          class="btn btn-ghost text-gray-500 hover:text-gray-700"
          title="Keyboard shortcuts (?)"
        >
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
        </button>
        
        <!-- Bulk Selection Toggle -->
        <button 
          @click="toggleSelectionMode"
          class="btn"
          :class="selectionMode ? 'btn-primary' : 'btn-ghost'"
          title="Toggle selection mode (Ctrl+Shift+S)"
        >
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
          </svg>
        </button>
        
        <!-- View toggle -->
        <div class="flex items-center bg-gray-100 dark:bg-gray-700 rounded-lg p-1">
          <button 
            @click="viewMode = 'grid'"
            class="p-2 rounded"
            :class="viewMode === 'grid' ? 'bg-white dark:bg-gray-600 shadow' : ''"
            title="Grid view (G)"
          >
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" />
            </svg>
          </button>
          <button 
            @click="viewMode = 'list'"
            class="p-2 rounded"
            :class="viewMode === 'list' ? 'bg-white dark:bg-gray-600 shadow' : ''"
            title="List view (L)"
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
          <option value="domain">Domain</option>
        </select>
        
        <!-- Filter dropdown -->
        <div class="relative">
          <button 
            @click="showFilterMenu = !showFilterMenu"
            class="btn btn-ghost"
            :class="{ 'text-blue-600': hasActiveFilters }"
          >
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
            </svg>
            <span v-if="hasActiveFilters" class="ml-1 px-1.5 py-0.5 text-xs bg-blue-100 text-blue-600 rounded-full">
              {{ activeFilterCount }}
            </span>
          </button>
          
          <div v-if="showFilterMenu" class="absolute right-0 mt-2 w-64 bg-white dark:bg-gray-800 rounded-lg shadow-lg border border-gray-200 dark:border-gray-700 z-20">
            <div class="p-3 space-y-3">
              <h4 class="font-medium text-gray-900 dark:text-white text-sm">Filters</h4>
              
              <!-- Quick filters -->
              <div class="space-y-2">
                <label class="flex items-center">
                  <input 
                    type="checkbox" 
                    :checked="filters.is_favorite"
                    @change="toggleFilter('is_favorite')"
                    class="form-checkbox"
                  />
                  <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Favorites only</span>
                </label>
                <label class="flex items-center">
                  <input 
                    type="checkbox" 
                    :checked="filters.is_archived"
                    @change="toggleFilter('is_archived')"
                    class="form-checkbox"
                  />
                  <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Archived only</span>
                </label>
              </div>
              
              <!-- Archive status -->
              <div>
                <label class="text-xs text-gray-500 mb-1 block">Archive Status</label>
                <select v-model="filters.archive_status" @change="fetchBookmarks" class="form-input w-full text-sm">
                  <option value="">All</option>
                  <option value="none">Not archived</option>
                  <option value="pending">Pending</option>
                  <option value="completed">Completed</option>
                  <option value="failed">Failed</option>
                </select>
              </div>
              
              <!-- Collection filter -->
              <div>
                <label class="text-xs text-gray-500 mb-1 block">Collection</label>
                <select v-model="filters.collection_id" @change="fetchBookmarks" class="form-input w-full text-sm">
                  <option value="">All collections</option>
                  <option value="none">Uncategorized</option>
                  <option v-for="col in collections" :key="col.id" :value="col.id">
                    {{ col.name }}
                  </option>
                </select>
              </div>
              
              <button 
                v-if="hasActiveFilters"
                @click="clearAllFilters" 
                class="w-full text-sm text-red-600 hover:text-red-500 py-1"
              >
                Clear all filters
              </button>
            </div>
          </div>
        </div>
        
        <!-- Add button -->
        <button @click="showAddBookmark = true" class="btn btn-primary" title="Add bookmark (N)">
          <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
          </svg>
          Add
        </button>
      </div>
    </div>

    <!-- Bulk Actions Bar -->
    <Transition name="slide-down">
      <div v-if="selectedBookmarks.length > 0" class="bg-blue-50 dark:bg-blue-900/30 border border-blue-200 dark:border-blue-800 rounded-xl p-4 flex items-center justify-between">
        <div class="flex items-center gap-4">
          <span class="font-medium text-blue-800 dark:text-blue-200">
            {{ selectedBookmarks.length }} selected
          </span>
          <button @click="selectAll" class="text-sm text-blue-600 hover:text-blue-500">
            {{ selectedBookmarks.length === bookmarks.length ? 'Deselect all' : 'Select all' }}
          </button>
        </div>
        <div class="flex items-center gap-2">
          <button @click="bulkArchive" class="btn btn-secondary btn-sm">
            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4" />
            </svg>
            Archive
          </button>
          <button @click="showBulkMove = true" class="btn btn-secondary btn-sm">
            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z" />
            </svg>
            Move
          </button>
          <button @click="showBulkTag = true" class="btn btn-secondary btn-sm">
            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />
            </svg>
            Tag
          </button>
          <button @click="bulkDelete" class="btn btn-danger btn-sm">
            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
            </svg>
            Delete
          </button>
          <button @click="clearSelection" class="btn btn-ghost btn-sm">Cancel</button>
        </div>
      </div>
    </Transition>

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
      <div 
        v-for="bookmark in bookmarks" 
        :key="bookmark.id"
        class="relative"
        :class="{ 'ring-2 ring-blue-500 ring-offset-2 rounded-lg': isSelected(bookmark.id) }"
      >
        <!-- Selection checkbox (shown in selection mode) -->
        <div 
          v-if="selectionMode"
          class="absolute top-2 left-2 z-10"
          @click.stop="toggleSelection(bookmark.id)"
        >
          <div 
            class="w-6 h-6 rounded-full border-2 flex items-center justify-center cursor-pointer transition-all"
            :class="isSelected(bookmark.id) 
              ? 'bg-blue-600 border-blue-600 text-white' 
              : 'bg-white/90 border-gray-300 hover:border-blue-500'"
          >
            <svg v-if="isSelected(bookmark.id)" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" />
            </svg>
          </div>
        </div>
        
        <BookmarkCard 
          :bookmark="bookmark"
          @click="selectionMode ? toggleSelection(bookmark.id) : viewBookmark(bookmark)"
          @edit="editBookmark"
          @delete="deleteBookmark"
        />
      </div>
    </div>

    <!-- List view -->
    <div v-else class="bg-white dark:bg-gray-800 rounded-lg shadow">
      <ul class="bookmark-list">
        <li 
          v-for="bookmark in bookmarks" 
          :key="bookmark.id"
          class="bookmark-list-item px-4 cursor-pointer"
          :class="{ 'bg-blue-50 dark:bg-blue-900/30': isSelected(bookmark.id) }"
          @click="selectionMode ? toggleSelection(bookmark.id) : viewBookmark(bookmark)"
        >
          <!-- Selection checkbox -->
          <div v-if="selectionMode" class="mr-3" @click.stop="toggleSelection(bookmark.id)">
            <div 
              class="w-5 h-5 rounded border-2 flex items-center justify-center cursor-pointer transition-all"
              :class="isSelected(bookmark.id) 
                ? 'bg-blue-600 border-blue-600 text-white' 
                : 'border-gray-300 hover:border-blue-500'"
            >
              <svg v-if="isSelected(bookmark.id)" class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" />
              </svg>
            </div>
          </div>
          
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
    
    <!-- Keyboard Shortcuts Modal -->
    <Teleport to="body">
      <Transition name="modal">
        <div v-if="showShortcuts" class="fixed inset-0 z-50 flex items-center justify-center p-4" @click.self="showShortcuts = false">
          <div class="fixed inset-0 bg-black/50" @click="showShortcuts = false"></div>
          <div class="relative bg-white dark:bg-gray-800 rounded-xl shadow-xl max-w-md w-full p-6 z-10">
            <div class="flex items-center justify-between mb-4">
              <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Keyboard Shortcuts</h3>
              <button @click="showShortcuts = false" class="text-gray-400 hover:text-gray-600">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
              </button>
            </div>
            
            <div class="space-y-3">
              <div class="flex items-center justify-between py-2 border-b border-gray-100 dark:border-gray-700">
                <span class="text-gray-600 dark:text-gray-300">Add new bookmark</span>
                <kbd class="px-2 py-1 bg-gray-100 dark:bg-gray-700 rounded text-sm font-mono">N</kbd>
              </div>
              <div class="flex items-center justify-between py-2 border-b border-gray-100 dark:border-gray-700">
                <span class="text-gray-600 dark:text-gray-300">Grid view</span>
                <kbd class="px-2 py-1 bg-gray-100 dark:bg-gray-700 rounded text-sm font-mono">G</kbd>
              </div>
              <div class="flex items-center justify-between py-2 border-b border-gray-100 dark:border-gray-700">
                <span class="text-gray-600 dark:text-gray-300">List view</span>
                <kbd class="px-2 py-1 bg-gray-100 dark:bg-gray-700 rounded text-sm font-mono">L</kbd>
              </div>
              <div class="flex items-center justify-between py-2 border-b border-gray-100 dark:border-gray-700">
                <span class="text-gray-600 dark:text-gray-300">Toggle selection mode</span>
                <kbd class="px-2 py-1 bg-gray-100 dark:bg-gray-700 rounded text-sm font-mono">Ctrl+Shift+S</kbd>
              </div>
              <div class="flex items-center justify-between py-2 border-b border-gray-100 dark:border-gray-700">
                <span class="text-gray-600 dark:text-gray-300">Select all (in selection mode)</span>
                <kbd class="px-2 py-1 bg-gray-100 dark:bg-gray-700 rounded text-sm font-mono">Ctrl+A</kbd>
              </div>
              <div class="flex items-center justify-between py-2 border-b border-gray-100 dark:border-gray-700">
                <span class="text-gray-600 dark:text-gray-300">Clear selection / Close</span>
                <kbd class="px-2 py-1 bg-gray-100 dark:bg-gray-700 rounded text-sm font-mono">Escape</kbd>
              </div>
              <div class="flex items-center justify-between py-2">
                <span class="text-gray-600 dark:text-gray-300">Show shortcuts</span>
                <kbd class="px-2 py-1 bg-gray-100 dark:bg-gray-700 rounded text-sm font-mono">?</kbd>
              </div>
            </div>
            
            <p class="mt-4 text-xs text-gray-400 text-center">Press ? anytime to show this help</p>
          </div>
        </div>
      </Transition>
    </Teleport>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, onUnmounted, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useBookmarkStore } from '../stores/bookmarks';
import BookmarkCard from '../components/BookmarkCard.vue';
import BookmarkModal from '../components/BookmarkModal.vue';
import axios from 'axios';

const route = useRoute();
const router = useRouter();
const bookmarkStore = useBookmarkStore();

const loading = ref(true);
const showAddBookmark = ref(false);
const editingBookmark = ref(null);
const viewMode = ref(localStorage.getItem('viewMode') || 'grid');
const sortBy = ref('created_at');
const showShortcuts = ref(false);
const showFilterMenu = ref(false);

// Bulk selection state
const selectionMode = ref(false);
const selectedBookmarks = ref([]);
const showBulkMove = ref(false);
const showBulkTag = ref(false);

// Initialize state before any watchers use them
const bookmarks = ref([]);
const collections = ref([]);
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
  archive_status: '',
});

const hasActiveFilters = computed(() => {
  return filters.search || filters.is_favorite || filters.is_archived || filters.archive_status || filters.collection_id || (filters.tags && filters.tags.length > 0);
});

const activeFilterCount = computed(() => {
  let count = 0;
  if (filters.search) count++;
  if (filters.is_favorite) count++;
  if (filters.is_archived) count++;
  if (filters.archive_status) count++;
  if (filters.collection_id) count++;
  if (filters.tags?.length) count += filters.tags.length;
  return count;
});

const toggleFilter = (key) => {
  const query = { ...route.query };
  if (filters[key]) {
    delete query[key];
  } else {
    query[key] = 'true';
  }
  router.push({ query });
};

// Close filter menu when clicking outside
const handleClickOutside = (e) => {
  if (showFilterMenu.value && !e.target.closest('.relative')) {
    showFilterMenu.value = false;
  }
};

// Keyboard shortcuts
const handleKeyDown = (e) => {
  // Don't trigger if typing in input
  if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') return;
  
  // ? - Show shortcuts help
  if (e.key === '?' || (e.key === '/' && e.shiftKey)) {
    e.preventDefault();
    showShortcuts.value = true;
  }
  
  // N - New bookmark
  if (e.key === 'n' && !e.ctrlKey && !e.metaKey) {
    e.preventDefault();
    showAddBookmark.value = true;
  }
  
  // G - Grid view
  if (e.key === 'g' && !e.ctrlKey && !e.metaKey) {
    viewMode.value = 'grid';
  }
  
  // L - List view
  if (e.key === 'l' && !e.ctrlKey && !e.metaKey) {
    viewMode.value = 'list';
  }
  
  // Ctrl+Shift+S - Toggle selection mode
  if (e.key === 'S' && e.ctrlKey && e.shiftKey) {
    e.preventDefault();
    toggleSelectionMode();
  }
  
  // Escape - Clear selection or close modal
  if (e.key === 'Escape') {
    if (showShortcuts.value) {
      showShortcuts.value = false;
    } else if (selectedBookmarks.value.length > 0) {
      clearSelection();
    } else if (selectionMode.value) {
      selectionMode.value = false;
    }
  }
  
  // Ctrl+A - Select all (when in selection mode)
  if (e.key === 'a' && (e.ctrlKey || e.metaKey) && selectionMode.value) {
    e.preventDefault();
    selectAll();
  }
};

// Selection functions
const toggleSelectionMode = () => {
  selectionMode.value = !selectionMode.value;
  if (!selectionMode.value) {
    clearSelection();
  }
};

const toggleSelection = (bookmarkId) => {
  const index = selectedBookmarks.value.indexOf(bookmarkId);
  if (index === -1) {
    selectedBookmarks.value.push(bookmarkId);
  } else {
    selectedBookmarks.value.splice(index, 1);
  }
};

const isSelected = (bookmarkId) => selectedBookmarks.value.includes(bookmarkId);

const selectAll = () => {
  if (selectedBookmarks.value.length === bookmarks.value.length) {
    selectedBookmarks.value = [];
  } else {
    selectedBookmarks.value = bookmarks.value.map(b => b.id);
  }
};

const clearSelection = () => {
  selectedBookmarks.value = [];
};

// Bulk actions
const bulkArchive = async () => {
  if (!confirm(`Archive ${selectedBookmarks.value.length} bookmarks?`)) return;
  
  try {
    await axios.post('/api/v1/bookmarks/batch-archive', { ids: selectedBookmarks.value });
    // Update local state
    bookmarks.value.forEach(b => {
      if (selectedBookmarks.value.includes(b.id)) {
        b.archive_status = 'pending';
      }
    });
    clearSelection();
  } catch (error) {
    alert('Failed to archive bookmarks');
  }
};

const bulkDelete = async () => {
  if (!confirm(`Delete ${selectedBookmarks.value.length} bookmarks? This cannot be undone.`)) return;
  
  try {
    await axios.post('/api/v1/bookmarks/bulk-delete', { ids: selectedBookmarks.value });
    bookmarks.value = bookmarks.value.filter(b => !selectedBookmarks.value.includes(b.id));
    pagination.total -= selectedBookmarks.value.length;
    clearSelection();
  } catch (error) {
    alert('Failed to delete bookmarks');
  }
};

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
    await bookmarkStore.setFilter('archiveStatus', filters.archive_status || null);
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
  filters.archive_status = query.archive_status || '';
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
  showFilterMenu.value = false;
  router.push({ path: '/bookmarks' });
};

const onBookmarkSaved = () => {
  editingBookmark.value = null;
  fetchBookmarks(pagination.currentPage);
};

onMounted(async () => {
  await bookmarkStore.fetchCollections();
  await bookmarkStore.fetchTags();
  collections.value = bookmarkStore.collections;
  document.addEventListener('click', handleClickOutside);
  document.addEventListener('keydown', handleKeyDown);
});

onUnmounted(() => {
  document.removeEventListener('click', handleClickOutside);
  document.removeEventListener('keydown', handleKeyDown);
});
</script>
