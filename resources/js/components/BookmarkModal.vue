<template>
  <Teleport to="body">
    <Transition name="modal">
      <div v-if="modelValue" class="modal-overlay" @click="close">
        <div class="modal-content" @click.stop>
          <div class="modal-panel">
            <!-- Header -->
            <div class="flex items-center justify-between p-4 border-b border-gray-200 dark:border-gray-700">
              <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                {{ isEditing ? 'Edit Bookmark' : 'Add Bookmark' }}
              </h3>
              <button @click="close" class="p-1 rounded hover:bg-gray-100 dark:hover:bg-gray-700">
                <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
              </button>
            </div>
            
            <!-- Form -->
            <form @submit.prevent="submit" class="p-4 space-y-4">
              <!-- URL -->
              <div>
                <label class="form-label">URL *</label>
                <div class="flex gap-2">
                  <input 
                    v-model="form.url"
                    type="url"
                    class="form-input flex-1"
                    placeholder="https://example.com"
                    required
                    :disabled="isEditing"
                  />
                  <button 
                    v-if="!isEditing"
                    type="button"
                    @click="fetchMetadata"
                    :disabled="fetchingMetadata || !form.url"
                    class="btn btn-secondary"
                  >
                    <svg v-if="fetchingMetadata" class="w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24">
                      <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                      <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <span v-else>Fetch</span>
                  </button>
                </div>
              </div>
              
              <!-- Title -->
              <div>
                <label class="form-label">Title</label>
                <input 
                  v-model="form.title"
                  type="text"
                  class="form-input"
                  placeholder="Page title"
                />
              </div>
              
              <!-- Description -->
              <div>
                <label class="form-label">Description</label>
                <textarea 
                  v-model="form.description"
                  rows="2"
                  class="form-input"
                  placeholder="Brief description..."
                ></textarea>
              </div>
              
              <!-- Collection -->
              <div>
                <label class="form-label">Collection</label>
                <select v-model="form.collection_id" class="form-input">
                  <option :value="null">No collection</option>
                  <option 
                    v-for="collection in collections" 
                    :key="collection.id"
                    :value="collection.id"
                  >
                    {{ collection.name }}
                  </option>
                </select>
              </div>
              
              <!-- Tags -->
              <div>
                <label class="form-label">Tags</label>
                <div class="flex flex-wrap gap-2 mb-2">
                  <span 
                    v-for="(tag, index) in form.tags" 
                    :key="index"
                    class="inline-flex items-center px-2 py-1 rounded-full text-sm bg-blue-100 text-blue-800"
                  >
                    {{ tag }}
                    <button type="button" @click="removeTag(index)" class="ml-1 hover:text-blue-600">
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                      </svg>
                    </button>
                  </span>
                </div>
                <div class="flex gap-2">
                  <input 
                    v-model="newTag"
                    type="text"
                    class="form-input flex-1"
                    placeholder="Add tag..."
                    @keydown.enter.prevent="addTag"
                  />
                  <button type="button" @click="addTag" class="btn btn-secondary">Add</button>
                </div>
              </div>
              
              <!-- Options -->
              <div class="flex items-center gap-4">
                <label class="flex items-center cursor-pointer">
                  <input type="checkbox" v-model="form.is_favorite" class="rounded border-gray-300 text-blue-600 mr-2" />
                  <span class="text-sm text-gray-700 dark:text-gray-300">Favorite</span>
                </label>
                <label class="flex items-center cursor-pointer">
                  <input type="checkbox" v-model="archiveAfterSave" class="rounded border-gray-300 text-blue-600 mr-2" />
                  <span class="text-sm text-gray-700 dark:text-gray-300">Archive page</span>
                </label>
              </div>
              
              <!-- Notes -->
              <div>
                <label class="form-label">Notes</label>
                <textarea 
                  v-model="form.notes"
                  rows="3"
                  class="form-input"
                  placeholder="Personal notes..."
                ></textarea>
              </div>
              
              <!-- Actions -->
              <div class="flex justify-end gap-3 pt-4 border-t border-gray-200 dark:border-gray-700">
                <button type="button" @click="close" class="btn btn-secondary">
                  Cancel
                </button>
                <button type="submit" :disabled="loading" class="btn btn-primary">
                  <svg v-if="loading" class="w-5 h-5 animate-spin mr-2" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                  </svg>
                  {{ isEditing ? 'Update' : 'Save' }}
                </button>
              </div>
            </form>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup>
import { ref, reactive, computed, watch } from 'vue';
import { useBookmarkStore } from '../stores/bookmarks';
import axios from 'axios';

const props = defineProps({
  modelValue: Boolean,
  bookmark: {
    type: Object,
    default: null,
  },
});

const emit = defineEmits(['update:modelValue', 'saved']);

const bookmarkStore = useBookmarkStore();

const loading = ref(false);
const fetchingMetadata = ref(false);
const newTag = ref('');
const archiveAfterSave = ref(false);

const form = reactive({
  url: '',
  title: '',
  description: '',
  collection_id: null,
  tags: [],
  is_favorite: false,
  notes: '',
});

const isEditing = computed(() => !!props.bookmark);
const collections = computed(() => bookmarkStore.collections || []);

const resetForm = () => {
  form.url = '';
  form.title = '';
  form.description = '';
  form.collection_id = null;
  form.tags = [];
  form.is_favorite = false;
  form.notes = '';
  archiveAfterSave.value = false;
};

// Watch for bookmark changes (when editing)
watch(() => props.bookmark, (bookmark) => {
  if (bookmark) {
    form.url = bookmark.url;
    form.title = bookmark.title || '';
    form.description = bookmark.description || '';
    form.collection_id = bookmark.collection_id;
    form.tags = bookmark.tags?.map(t => t.name) || [];
    form.is_favorite = bookmark.is_favorite || false;
    form.notes = bookmark.notes || '';
  } else {
    resetForm();
  }
}, { immediate: true });

const fetchMetadata = async () => {
  if (!form.url) return;
  
  fetchingMetadata.value = true;
  try {
    const response = await axios.post('/api/v1/bookmarks/metadata', { url: form.url });
    const metadata = response.data.data;
    
    form.title = metadata.title || form.title;
    form.description = metadata.description || form.description;
  } catch (error) {
    console.error('Failed to fetch metadata:', error);
  } finally {
    fetchingMetadata.value = false;
  }
};

const addTag = () => {
  const tag = newTag.value.trim();
  if (tag && !form.tags.includes(tag)) {
    form.tags.push(tag);
  }
  newTag.value = '';
};

const removeTag = (index) => {
  form.tags.splice(index, 1);
};

const submit = async () => {
  loading.value = true;
  
  try {
    let bookmark;
    
    if (isEditing.value) {
      bookmark = await bookmarkStore.updateBookmark(props.bookmark.id, {
        title: form.title,
        description: form.description,
        collection_id: form.collection_id,
        tags: form.tags,
        is_favorite: form.is_favorite,
        notes: form.notes,
      });
    } else {
      bookmark = await bookmarkStore.createBookmark({
        url: form.url,
        title: form.title,
        description: form.description,
        collection_id: form.collection_id,
        tags: form.tags,
        is_favorite: form.is_favorite,
        notes: form.notes,
      });
      
      if (archiveAfterSave.value && bookmark) {
        await bookmarkStore.archiveBookmark(bookmark.id);
      }
    }
    
    emit('saved', bookmark);
    close();
  } catch (error) {
    console.error('Failed to save bookmark:', error);
  } finally {
    loading.value = false;
  }
};

const close = () => {
  emit('update:modelValue', false);
  setTimeout(resetForm, 300);
};
</script>

<style scoped>
.modal-enter-active,
.modal-leave-active {
  transition: opacity 0.3s ease;
}

.modal-enter-from,
.modal-leave-to {
  opacity: 0;
}

.modal-enter-active .modal-panel,
.modal-leave-active .modal-panel {
  transition: transform 0.3s ease;
}

.modal-enter-from .modal-panel,
.modal-leave-to .modal-panel {
  transform: scale(0.95);
}
</style>
