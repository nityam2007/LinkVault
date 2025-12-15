<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
      <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Collections</h1>
      <button @click="showCreateModal = true" class="btn btn-primary">
        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
        </svg>
        New Collection
      </button>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
      <div v-for="i in 6" :key="i" class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
        <div class="skeleton h-6 w-2/3 mb-3"></div>
        <div class="skeleton h-4 w-1/2"></div>
      </div>
    </div>

    <!-- Empty state -->
    <div v-else-if="!collections || collections.length === 0" class="text-center py-16">
      <svg class="w-16 h-16 mx-auto text-gray-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
      </svg>
      <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-2">No collections yet</h3>
      <p class="text-gray-500 mb-4">Create collections to organize your bookmarks</p>
      <button @click="showCreateModal = true" class="btn btn-primary">
        Create Collection
      </button>
    </div>

    <!-- Collections grid -->
    <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
      <div 
        v-for="collection in collections" 
        :key="collection.id"
        class="bg-white dark:bg-gray-800 rounded-lg shadow hover:shadow-md transition-shadow cursor-pointer"
        @click="$router.push(`/collections/${collection.id}`)"
      >
        <div class="p-6">
          <div class="flex items-start justify-between">
            <div class="flex items-center">
              <span 
                class="w-4 h-4 rounded-full mr-3"
                :style="{ backgroundColor: collection.color || '#6B7280' }"
              ></span>
              <h3 class="font-semibold text-gray-900 dark:text-white">{{ collection.name }}</h3>
            </div>
            
            <div class="relative">
              <button 
                @click.stop="toggleMenu(collection.id)"
                class="p-1 rounded hover:bg-gray-100 dark:hover:bg-gray-700"
              >
                <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z" />
                </svg>
              </button>
              
              <div 
                v-if="activeMenu === collection.id"
                class="dropdown-menu"
                @click.stop
              >
                <button @click="editCollection(collection)" class="dropdown-item w-full">
                  <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                  </svg>
                  Edit
                </button>
                <button 
                  v-if="collection.is_public"
                  @click="copyShareLink(collection)"
                  class="dropdown-item w-full"
                >
                  <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z" />
                  </svg>
                  Copy Share Link
                </button>
                <button @click="deleteCollection(collection.id)" class="dropdown-item w-full text-red-600">
                  <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                  </svg>
                  Delete
                </button>
              </div>
            </div>
          </div>
          
          <p v-if="collection.description" class="text-sm text-gray-500 mt-2 line-clamp-2">
            {{ collection.description }}
          </p>
          
          <div class="flex items-center justify-between mt-4 pt-4 border-t border-gray-100 dark:border-gray-700">
            <span class="text-sm text-gray-500">
              {{ collection.bookmark_count || 0 }} bookmarks
            </span>
            <div class="flex items-center gap-2">
              <span 
                v-if="collection.is_public"
                class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800"
              >
                Public
              </span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Create/Edit Modal -->
    <Teleport to="body">
      <Transition name="modal">
        <div v-if="showCreateModal" class="modal-overlay" @click="closeModal">
          <div class="modal-content" @click.stop>
            <div class="modal-panel max-w-md">
              <div class="p-4 border-b border-gray-200 dark:border-gray-700">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                  {{ editingCollection ? 'Edit Collection' : 'New Collection' }}
                </h3>
              </div>
              
              <form @submit.prevent="saveCollection" class="p-4 space-y-4">
                <div>
                  <label class="form-label">Name *</label>
                  <input 
                    v-model="form.name"
                    type="text"
                    class="form-input"
                    required
                    placeholder="My Collection"
                  />
                </div>
                
                <div>
                  <label class="form-label">Description</label>
                  <textarea 
                    v-model="form.description"
                    rows="2"
                    class="form-input"
                    placeholder="Optional description..."
                  ></textarea>
                </div>
                
                <div>
                  <label class="form-label">Color</label>
                  <div class="flex gap-2 flex-wrap">
                    <button 
                      v-for="color in colors" 
                      :key="color"
                      type="button"
                      @click="form.color = color"
                      class="w-8 h-8 rounded-full border-2 transition-transform hover:scale-110"
                      :class="form.color === color ? 'border-gray-900 dark:border-white scale-110' : 'border-transparent'"
                      :style="{ backgroundColor: color }"
                    ></button>
                  </div>
                </div>
                
                <div>
                  <label class="form-label">Parent Collection</label>
                  <select v-model="form.parent_id" class="form-input">
                    <option :value="null">None (Root level)</option>
                    <option 
                      v-for="c in (collections || []).filter(c => c.id !== editingCollection?.id)" 
                      :key="c.id"
                      :value="c.id"
                    >
                      {{ c.name }}
                    </option>
                  </select>
                </div>
                
                <div class="flex items-center">
                  <input 
                    type="checkbox" 
                    v-model="form.is_public"
                    id="is_public"
                    class="rounded border-gray-300 text-blue-600 mr-2"
                  />
                  <label for="is_public" class="text-sm text-gray-700 dark:text-gray-300">
                    Make this collection public
                  </label>
                </div>
                
                <div class="flex justify-end gap-3 pt-4 border-t border-gray-200 dark:border-gray-700">
                  <button type="button" @click="closeModal" class="btn btn-secondary">
                    Cancel
                  </button>
                  <button type="submit" :disabled="saving" class="btn btn-primary">
                    {{ saving ? 'Saving...' : (editingCollection ? 'Update' : 'Create') }}
                  </button>
                </div>
              </form>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import { useBookmarkStore } from '../stores/bookmarks';

const bookmarkStore = useBookmarkStore();

const loading = ref(true);
const showCreateModal = ref(false);
const editingCollection = ref(null);
const activeMenu = ref(null);
const saving = ref(false);

const collections = ref([]);

const colors = [
  '#EF4444', '#F97316', '#F59E0B', '#84CC16', '#22C55E',
  '#14B8A6', '#06B6D4', '#3B82F6', '#6366F1', '#8B5CF6',
  '#A855F7', '#EC4899', '#F43F5E', '#6B7280',
];

const form = reactive({
  name: '',
  description: '',
  color: '#3B82F6',
  parent_id: null,
  is_public: false,
});

const resetForm = () => {
  form.name = '';
  form.description = '';
  form.color = '#3B82F6';
  form.parent_id = null;
  form.is_public = false;
  editingCollection.value = null;
};

const fetchCollections = async () => {
  loading.value = true;
  try {
    await bookmarkStore.fetchCollections();
    collections.value = bookmarkStore.collections || [];
  } finally {
    loading.value = false;
  }
};

const toggleMenu = (id) => {
  activeMenu.value = activeMenu.value === id ? null : id;
};

const editCollection = (collection) => {
  editingCollection.value = collection;
  form.name = collection.name;
  form.description = collection.description || '';
  form.color = collection.color || '#3B82F6';
  form.parent_id = collection.parent_id;
  form.is_public = collection.is_public || false;
  showCreateModal.value = true;
  activeMenu.value = null;
};

const saveCollection = async () => {
  saving.value = true;
  
  try {
    if (editingCollection.value) {
      await bookmarkStore.updateCollection(editingCollection.value.id, form);
    } else {
      await bookmarkStore.createCollection(form);
    }
    
    collections.value = bookmarkStore.collections;
    closeModal();
  } finally {
    saving.value = false;
  }
};

const deleteCollection = async (id) => {
  if (confirm('Are you sure you want to delete this collection? Bookmarks will be moved to "Uncategorized".')) {
    await bookmarkStore.deleteCollection(id);
    collections.value = bookmarkStore.collections;
    activeMenu.value = null;
  }
};

const copyShareLink = (collection) => {
  const link = `${window.location.origin}/public/collections/${collection.public_slug}`;
  navigator.clipboard.writeText(link);
  activeMenu.value = null;
  alert('Share link copied to clipboard!');
};

const closeModal = () => {
  showCreateModal.value = false;
  resetForm();
};

// Close menus when clicking outside
if (typeof window !== 'undefined') {
  document.addEventListener('click', () => {
    activeMenu.value = null;
  });
}

onMounted(() => {
  fetchCollections();
});
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
</style>
