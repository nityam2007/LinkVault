<template>
  <div v-if="loading" class="flex items-center justify-center py-24">
    <svg class="animate-spin h-8 w-8 text-blue-600" fill="none" viewBox="0 0 24 24">
      <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
      <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
    </svg>
  </div>

  <div v-else-if="!bookmark" class="text-center py-24">
    <h2 class="text-xl font-semibold text-gray-900 dark:text-white mb-2">Bookmark not found</h2>
    <router-link to="/bookmarks" class="btn btn-primary mt-4">Back to Bookmarks</router-link>
  </div>

  <!-- Reader View -->
  <ReaderView 
    v-else-if="showReader"
    :bookmark="bookmark"
    :archive="archive"
    @close="showReader = false"
  />

  <!-- Detail View -->
  <div v-else class="max-w-4xl mx-auto space-y-6">
    <!-- Back button -->
    <button @click="$router.back()" class="btn btn-ghost">
      <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
      </svg>
      Back
    </button>

    <!-- Main card -->
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden">
      <!-- Image -->
      <div v-if="bookmark.og_image || bookmark.screenshot_url" class="relative h-64 bg-gray-100">
        <img 
          :src="bookmark.og_image || bookmark.screenshot_url"
          :alt="bookmark.title"
          class="w-full h-full object-cover"
        />
      </div>

      <div class="p-6 space-y-4">
        <!-- Header -->
        <div class="flex items-start justify-between">
          <div class="flex-1">
            <div class="flex items-center text-sm text-gray-500 mb-2">
              <img 
                :src="`https://www.google.com/s2/favicons?domain=${domain}&sz=32`"
                class="w-4 h-4 rounded mr-2"
              />
              {{ domain }}
            </div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
              {{ bookmark.title || bookmark.url }}
            </h1>
          </div>
          
          <div class="flex items-center gap-2">
            <button 
              @click="toggleFavorite"
              class="p-2 rounded-full hover:bg-gray-100 dark:hover:bg-gray-700"
            >
              <svg 
                class="w-6 h-6"
                :class="bookmark.is_favorite ? 'text-yellow-500 fill-yellow-500' : 'text-gray-400'"
                fill="none" 
                stroke="currentColor" 
                viewBox="0 0 24 24"
              >
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z" />
              </svg>
            </button>
            <button @click="showEdit = true" class="btn btn-secondary">
              Edit
            </button>
          </div>
        </div>

        <!-- URL -->
        <a 
          :href="bookmark.url" 
          target="_blank"
          class="inline-flex items-center text-blue-600 hover:text-blue-500 break-all"
        >
          {{ bookmark.url }}
          <svg class="w-4 h-4 ml-1 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
          </svg>
        </a>

        <!-- Description -->
        <p v-if="bookmark.description" class="text-gray-600 dark:text-gray-300">
          {{ bookmark.description }}
        </p>

        <!-- Tags -->
        <div v-if="bookmark.tags?.length" class="flex flex-wrap gap-2">
          <router-link 
            v-for="tag in bookmark.tags" 
            :key="tag.id"
            :to="`/bookmarks?tags=${tag.id}`"
            class="tag-chip"
            :style="{ backgroundColor: (tag.color || '#6B7280') + '20', color: tag.color || '#6B7280' }"
          >
            #{{ tag.name }}
          </router-link>
        </div>

        <!-- Collection -->
        <div v-if="bookmark.collection" class="flex items-center text-sm">
          <span class="text-gray-500 mr-2">Collection:</span>
          <router-link 
            :to="`/collections/${bookmark.collection.id}`"
            class="inline-flex items-center text-gray-700 dark:text-gray-300 hover:text-blue-600"
          >
            <span 
              class="w-3 h-3 rounded-full mr-2"
              :style="{ backgroundColor: bookmark.collection.color || '#6B7280' }"
            ></span>
            {{ bookmark.collection.name }}
          </router-link>
        </div>

        <!-- Metadata -->
        <div class="flex flex-wrap gap-4 text-sm text-gray-500 pt-4 border-t border-gray-200 dark:border-gray-700">
          <span>Added {{ formatDate(bookmark.created_at) }}</span>
          <span v-if="bookmark.updated_at !== bookmark.created_at">
            Updated {{ formatDate(bookmark.updated_at) }}
          </span>
        </div>
      </div>
    </div>

    <!-- Archive section -->
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
      <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Archive</h2>
      
      <!-- Archive completed -->
      <div v-if="bookmark.archive_status === 'completed' && archive" class="space-y-4">
        <div class="flex items-center justify-between">
          <div class="flex items-center text-green-600">
            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
            </svg>
            Archived on {{ formatDate(archive.created_at) }}
          </div>
          <button @click="showReader = true" class="btn btn-primary">
            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
            </svg>
            Read Article
          </button>
        </div>
        
        <div class="grid grid-cols-3 gap-4 text-sm">
          <div>
            <span class="text-gray-500">Words</span>
            <p class="font-medium">{{ archive.word_count || 'N/A' }}</p>
          </div>
          <div>
            <span class="text-gray-500">Images</span>
            <p class="font-medium">{{ bookmark.images?.length || 0 }}</p>
          </div>
          <div>
            <span class="text-gray-500">Size</span>
            <p class="font-medium">{{ formatBytes(archive.content_size) }}</p>
          </div>
        </div>
      </div>
      
      <!-- Archive pending/processing -->
      <div v-else-if="bookmark.archive_status === 'pending' || bookmark.archive_status === 'processing'" class="flex items-center text-yellow-600">
        <svg class="w-5 h-5 mr-2 animate-spin" fill="none" viewBox="0 0 24 24">
          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
          <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg>
        Archiving in progress...
      </div>
      
      <!-- Archive failed -->
      <div v-else-if="bookmark.archive_status === 'failed'" class="space-y-3">
        <div class="flex items-center text-red-600">
          <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
          Archive failed
        </div>
        <button @click="retryArchive" class="btn btn-secondary">
          Retry Archive
        </button>
      </div>
      
      <!-- Not archived -->
      <div v-else class="space-y-3">
        <p class="text-gray-500">This page has not been archived yet.</p>
        <button @click="startArchive" class="btn btn-primary">
          <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4" />
          </svg>
          Archive Now
        </button>
      </div>
    </div>

    <!-- Notes section -->
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
      <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Notes</h2>
      <div v-if="!editingNotes">
        <p v-if="bookmark.notes" class="text-gray-600 dark:text-gray-300 whitespace-pre-wrap">
          {{ bookmark.notes }}
        </p>
        <p v-else class="text-gray-400 italic">No notes</p>
        <button @click="editingNotes = true" class="btn btn-ghost mt-3">
          {{ bookmark.notes ? 'Edit Notes' : 'Add Notes' }}
        </button>
      </div>
      <div v-else class="space-y-3">
        <textarea 
          v-model="notes"
          rows="4"
          class="form-input"
          placeholder="Add your notes..."
        ></textarea>
        <div class="flex gap-2">
          <button @click="saveNotes" class="btn btn-primary">Save</button>
          <button @click="editingNotes = false" class="btn btn-secondary">Cancel</button>
        </div>
      </div>
    </div>

    <!-- Danger zone -->
    <div class="bg-red-50 dark:bg-red-900/20 rounded-lg p-6">
      <h2 class="text-lg font-semibold text-red-800 dark:text-red-200 mb-4">Danger Zone</h2>
      <button @click="deleteBookmark" class="btn btn-danger">
        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
        </svg>
        Delete Bookmark
      </button>
    </div>

    <!-- Edit Modal -->
    <BookmarkModal 
      v-model="showEdit" 
      :bookmark="bookmark"
      @saved="onBookmarkUpdated" 
    />
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useBookmarkStore } from '../stores/bookmarks';
import BookmarkModal from '../components/BookmarkModal.vue';
import ReaderView from '../components/ReaderView.vue';
import axios from 'axios';

const route = useRoute();
const router = useRouter();
const bookmarkStore = useBookmarkStore();

const loading = ref(true);
const bookmark = ref(null);
const archive = ref(null);
const showEdit = ref(false);
const showReader = ref(false);
const editingNotes = ref(false);
const notes = ref('');

const domain = computed(() => {
  if (!bookmark.value?.url) return '';
  try {
    return new URL(bookmark.value.url).hostname.replace('www.', '');
  } catch {
    return bookmark.value.url;
  }
});

const formatDate = (date) => {
  return new Date(date).toLocaleDateString('en-US', {
    month: 'long',
    day: 'numeric',
    year: 'numeric',
  });
};

const formatBytes = (bytes) => {
  if (!bytes) return 'N/A';
  const sizes = ['B', 'KB', 'MB', 'GB'];
  const i = Math.floor(Math.log(bytes) / Math.log(1024));
  return `${(bytes / Math.pow(1024, i)).toFixed(1)} ${sizes[i]}`;
};

const fetchBookmark = async () => {
  loading.value = true;
  try {
    const response = await axios.get(`/api/v1/bookmarks/${route.params.id}`);
    bookmark.value = response.data.bookmark || response.data.data;
    notes.value = bookmark.value?.notes || '';
    
    // Fetch archive if exists
    if (bookmark.value?.archive_status === 'completed') {
      const archiveResponse = await axios.get(`/api/v1/bookmarks/${route.params.id}/archive`);
      archive.value = archiveResponse.data.archive || archiveResponse.data.data;
    }
  } catch (error) {
    console.error('Failed to fetch bookmark:', error);
  } finally {
    loading.value = false;
  }
};

const toggleFavorite = async () => {
  await bookmarkStore.updateBookmark(bookmark.value.id, {
    is_favorite: !bookmark.value.is_favorite,
  });
  bookmark.value.is_favorite = !bookmark.value.is_favorite;
};

const startArchive = async () => {
  await bookmarkStore.archiveBookmark(bookmark.value.id);
  bookmark.value.archive_status = 'pending';
};

const retryArchive = async () => {
  await startArchive();
};

const saveNotes = async () => {
  await bookmarkStore.updateBookmark(bookmark.value.id, { notes: notes.value });
  bookmark.value.notes = notes.value;
  editingNotes.value = false;
};

const deleteBookmark = async () => {
  if (confirm('Are you sure you want to delete this bookmark? This action cannot be undone.')) {
    await bookmarkStore.deleteBookmark(bookmark.value.id);
    router.push('/bookmarks');
  }
};

const onBookmarkUpdated = (updated) => {
  bookmark.value = updated;
};

onMounted(() => {
  fetchBookmark();
});
</script>
