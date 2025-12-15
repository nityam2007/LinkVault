<template>
  <div class="bookmark-card bg-white dark:bg-gray-800 rounded-lg shadow border border-gray-200 dark:border-gray-700 overflow-hidden">
    <!-- Thumbnail -->
    <div class="relative h-40 bg-gray-100 dark:bg-gray-700">
      <img 
        v-if="bookmark.screenshot_url || bookmark.og_image"
        :src="bookmark.screenshot_url || bookmark.og_image"
        :alt="bookmark.title"
        class="w-full h-full object-cover"
        loading="lazy"
      />
      <div v-else class="w-full h-full flex items-center justify-center">
        <svg class="w-12 h-12 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" />
        </svg>
      </div>
      
      <!-- Archive status badge -->
      <div class="absolute top-2 left-2">
        <span 
          v-if="bookmark.archive_status === 'completed'"
          class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800"
        >
          <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4" />
          </svg>
          Archived
        </span>
        <span 
          v-else-if="bookmark.archive_status === 'pending' || bookmark.archive_status === 'processing'"
          class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-yellow-100 text-yellow-800"
        >
          <svg class="w-3 h-3 mr-1 animate-spin" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
          </svg>
          Archiving...
        </span>
      </div>
      
      <!-- Favorite button -->
      <button 
        @click.stop="toggleFavorite"
        class="absolute top-2 right-2 p-1.5 rounded-full bg-white/80 hover:bg-white dark:bg-gray-800/80 dark:hover:bg-gray-800 transition-colors"
      >
        <svg 
          class="w-5 h-5"
          :class="bookmark.is_favorite ? 'text-yellow-500 fill-yellow-500' : 'text-gray-400'"
          fill="none" 
          stroke="currentColor" 
          viewBox="0 0 24 24"
        >
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z" />
        </svg>
      </button>
    </div>
    
    <!-- Content -->
    <div class="p-4">
      <!-- Domain -->
      <div class="flex items-center text-xs text-gray-500 mb-2">
        <img 
          v-if="favicon"
          :src="favicon"
          class="favicon mr-1.5"
          @error="faviconError = true"
        />
        <span class="truncate">{{ domain }}</span>
      </div>
      
      <!-- Title -->
      <h3 class="font-medium text-gray-900 dark:text-white line-clamp-2 mb-2 cursor-pointer hover:text-blue-600" @click="$emit('click')">
        {{ bookmark.title || bookmark.url }}
      </h3>
      
      <!-- Description -->
      <p v-if="bookmark.description" class="text-sm text-gray-500 line-clamp-2 mb-3">
        {{ bookmark.description }}
      </p>
      
      <!-- Tags -->
      <div v-if="bookmark.tags?.length" class="flex flex-wrap gap-1 mb-3">
        <span 
          v-for="tag in bookmark.tags.slice(0, 3)" 
          :key="tag.id"
          class="tag-chip"
          :style="{ backgroundColor: (tag.color || '#6B7280') + '20', color: tag.color || '#6B7280' }"
        >
          {{ tag.name }}
        </span>
        <span v-if="bookmark.tags.length > 3" class="tag-chip bg-gray-100 text-gray-600">
          +{{ bookmark.tags.length - 3 }}
        </span>
      </div>
      
      <!-- Footer -->
      <div class="flex items-center justify-between pt-2 border-t border-gray-100 dark:border-gray-700">
        <span class="text-xs text-gray-400">
          {{ formatDate(bookmark.created_at) }}
        </span>
        
        <!-- Actions menu -->
        <div class="relative">
          <button 
            @click.stop="showMenu = !showMenu"
            class="p-1 rounded hover:bg-gray-100 dark:hover:bg-gray-700"
          >
            <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z" />
            </svg>
          </button>
          
          <div 
            v-if="showMenu"
            class="dropdown-menu"
            @click.stop
          >
            <a :href="bookmark.url" target="_blank" class="dropdown-item">
              <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
              </svg>
              Open Link
            </a>
            <button v-if="bookmark.archive_status === 'completed'" @click="viewArchive" class="dropdown-item w-full">
              <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
              </svg>
              View Archive
            </button>
            <button v-else-if="bookmark.archive_status !== 'pending' && bookmark.archive_status !== 'processing'" @click="archive" class="dropdown-item w-full">
              <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4" />
              </svg>
              Archive
            </button>
            <button @click="$emit('edit', bookmark)" class="dropdown-item w-full">
              <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
              </svg>
              Edit
            </button>
            <button @click="$emit('delete', bookmark.id)" class="dropdown-item w-full text-red-600 hover:bg-red-50">
              <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
              </svg>
              Delete
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import { useBookmarkStore } from '../stores/bookmarks';

const props = defineProps({
  bookmark: {
    type: Object,
    required: true,
  },
});

const emit = defineEmits(['click', 'edit', 'delete']);

const bookmarkStore = useBookmarkStore();
const showMenu = ref(false);
const faviconError = ref(false);

const domain = computed(() => {
  try {
    return new URL(props.bookmark.url).hostname.replace('www.', '');
  } catch {
    return props.bookmark.url;
  }
});

const favicon = computed(() => {
  if (faviconError.value) return null;
  return `https://www.google.com/s2/favicons?domain=${domain.value}&sz=32`;
});

const formatDate = (date) => {
  return new Date(date).toLocaleDateString('en-US', {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
  });
};

const toggleFavorite = async () => {
  await bookmarkStore.updateBookmark(props.bookmark.id, {
    is_favorite: !props.bookmark.is_favorite,
  });
};

const archive = async () => {
  await bookmarkStore.archiveBookmark(props.bookmark.id);
  showMenu.value = false;
};

const viewArchive = () => {
  emit('click');
  showMenu.value = false;
};

// Close menu when clicking outside
if (typeof window !== 'undefined') {
  document.addEventListener('click', () => {
    showMenu.value = false;
  });
}
</script>
