<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
      <div>
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Tags</h1>
        <p class="text-gray-500">{{ allTags.length }} tags total</p>
      </div>
      
      <!-- Search and View Toggle -->
      <div class="flex items-center gap-3">
        <div class="relative">
          <svg class="absolute left-3 top-2.5 w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
          </svg>
          <input 
            v-model="searchQuery"
            type="text" 
            placeholder="Search tags..." 
            class="form-input pl-10 w-64"
          />
        </div>
        
        <!-- View mode toggle -->
        <div class="flex items-center bg-gray-100 dark:bg-gray-700 rounded-lg p-1">
          <button 
            @click="viewMode = 'cloud'"
            class="p-2 rounded"
            :class="viewMode === 'cloud' ? 'bg-white dark:bg-gray-600 shadow' : ''"
            title="Tag Cloud"
          >
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />
            </svg>
          </button>
          <button 
            @click="viewMode = 'list'"
            class="p-2 rounded"
            :class="viewMode === 'list' ? 'bg-white dark:bg-gray-600 shadow' : ''"
            title="List View"
          >
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16" />
            </svg>
          </button>
        </div>
        
        <!-- Sort -->
        <select v-model="sortBy" class="form-input w-auto">
          <option value="usage">Most Used</option>
          <option value="name">Alphabetical</option>
          <option value="recent">Recently Added</option>
        </select>
      </div>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="flex flex-wrap gap-3">
      <div v-for="i in 20" :key="i" class="skeleton h-8 w-24 rounded-full"></div>
    </div>

    <!-- Empty state -->
    <div v-else-if="allTags.length === 0" class="text-center py-16">
      <svg class="w-16 h-16 mx-auto text-gray-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />
      </svg>
      <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-2">No tags yet</h3>
      <p class="text-gray-500">Tags will appear here when you add them to bookmarks.</p>
    </div>

    <!-- No search results -->
    <div v-else-if="filteredTags.length === 0" class="text-center py-12">
      <svg class="w-12 h-12 mx-auto text-gray-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
      </svg>
      <p class="text-gray-500">No tags matching "{{ searchQuery }}"</p>
      <button @click="searchQuery = ''" class="btn btn-ghost mt-2">Clear search</button>
    </div>

    <!-- Tag Cloud View -->
    <div v-else-if="viewMode === 'cloud'" class="bg-white dark:bg-gray-800 rounded-xl shadow p-6">
      <div class="flex flex-wrap gap-2 justify-center">
        <router-link 
          v-for="tag in displayedTags" 
          :key="tag.id"
          :to="`/bookmarks?tags=${tag.id}`"
          class="inline-flex items-center px-3 py-1.5 rounded-full font-medium transition-all hover:scale-105 hover:shadow-md"
          :style="{ 
            backgroundColor: (tag.color || '#6B7280') + '20', 
            color: tag.color || '#6B7280',
            fontSize: getTagSize(tag) + 'px'
          }"
        >
          #{{ tag.name }}
          <span class="ml-1.5 px-1.5 py-0.5 rounded-full bg-white/50 dark:bg-black/20 text-xs">
            {{ tag.usage_count || tag.bookmarks_count || 0 }}
          </span>
        </router-link>
      </div>
      
      <!-- Load More -->
      <div v-if="hasMore" class="text-center mt-6">
        <button @click="loadMore" class="btn btn-secondary">
          Load More ({{ remainingCount }} remaining)
        </button>
      </div>
    </div>

    <!-- List View -->
    <div v-else class="bg-white dark:bg-gray-800 rounded-xl shadow overflow-hidden">
      <table class="w-full">
        <thead class="bg-gray-50 dark:bg-gray-700">
          <tr>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tag</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Bookmarks</th>
            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
          <tr 
            v-for="tag in displayedTags" 
            :key="tag.id"
            class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors"
          >
            <td class="px-6 py-4">
              <router-link 
                :to="`/bookmarks?tags=${tag.id}`"
                class="inline-flex items-center"
              >
                <span 
                  class="w-3 h-3 rounded-full mr-3"
                  :style="{ backgroundColor: tag.color || '#6B7280' }"
                ></span>
                <span class="font-medium text-gray-900 dark:text-white">{{ tag.name }}</span>
              </router-link>
            </td>
            <td class="px-6 py-4 text-gray-500">
              {{ tag.usage_count || tag.bookmarks_count || 0 }} bookmarks
            </td>
            <td class="px-6 py-4 text-right">
              <button @click="editTag(tag)" class="text-blue-600 hover:text-blue-500 mr-3">Edit</button>
              <button @click="deleteTag(tag.id)" class="text-red-600 hover:text-red-500">Delete</button>
            </td>
          </tr>
        </tbody>
      </table>
      
      <!-- Load More -->
      <div v-if="hasMore" class="p-4 text-center border-t border-gray-200 dark:border-gray-700">
        <button @click="loadMore" class="btn btn-secondary">
          Load More ({{ remainingCount }} remaining)
        </button>
      </div>
    </div>

    <!-- Edit Tag Modal -->
    <Teleport to="body">
      <Transition name="modal">
        <div v-if="showEditModal" class="modal-overlay" @click="showEditModal = false">
          <div class="modal-content" @click.stop>
            <div class="modal-panel max-w-md">
              <div class="p-4 border-b border-gray-200 dark:border-gray-700">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Edit Tag</h3>
              </div>
              <form @submit.prevent="saveTag" class="p-4 space-y-4">
                <div>
                  <label class="form-label">Name</label>
                  <input v-model="editForm.name" type="text" class="form-input" required />
                </div>
                <div>
                  <label class="form-label">Color</label>
                  <div class="flex gap-2 flex-wrap">
                    <button 
                      v-for="color in colors" 
                      :key="color"
                      type="button"
                      @click="editForm.color = color"
                      class="w-8 h-8 rounded-full border-2 transition-transform hover:scale-110"
                      :class="editForm.color === color ? 'border-gray-900 dark:border-white scale-110' : 'border-transparent'"
                      :style="{ backgroundColor: color }"
                    ></button>
                  </div>
                </div>
                <div class="flex justify-end gap-3 pt-4">
                  <button type="button" @click="showEditModal = false" class="btn btn-secondary">Cancel</button>
                  <button type="submit" class="btn btn-primary">Save</button>
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
import { ref, computed, onMounted, watch } from 'vue';
import { useBookmarkStore } from '../stores/bookmarks';
import axios from 'axios';

const bookmarkStore = useBookmarkStore();

const loading = ref(true);
const allTags = ref([]);
const searchQuery = ref('');
const viewMode = ref('cloud');
const sortBy = ref('usage');
const showEditModal = ref(false);
const editForm = ref({ id: null, name: '', color: '' });
const displayLimit = ref(100); // Initial load limit for performance
const incrementAmount = 100;

const colors = [
  '#EF4444', '#F97316', '#F59E0B', '#84CC16', '#22C55E',
  '#14B8A6', '#06B6D4', '#3B82F6', '#6366F1', '#8B5CF6',
  '#A855F7', '#EC4899', '#F43F5E', '#6B7280',
];

// Filter and sort tags
const filteredTags = computed(() => {
  let result = [...allTags.value];
  
  // Search filter
  if (searchQuery.value) {
    const query = searchQuery.value.toLowerCase();
    result = result.filter(tag => tag.name.toLowerCase().includes(query));
  }
  
  // Sort
  switch (sortBy.value) {
    case 'name':
      result.sort((a, b) => a.name.localeCompare(b.name));
      break;
    case 'recent':
      result.sort((a, b) => new Date(b.created_at) - new Date(a.created_at));
      break;
    case 'usage':
    default:
      result.sort((a, b) => (b.usage_count || 0) - (a.usage_count || 0));
  }
  
  return result;
});

// Paginated display
const displayedTags = computed(() => {
  return filteredTags.value.slice(0, displayLimit.value);
});

const hasMore = computed(() => filteredTags.value.length > displayLimit.value);
const remainingCount = computed(() => filteredTags.value.length - displayLimit.value);

// Calculate font size based on usage (for tag cloud)
const getTagSize = (tag) => {
  const count = tag.usage_count || tag.bookmarks_count || 0;
  const maxCount = Math.max(...allTags.value.map(t => t.usage_count || t.bookmarks_count || 1));
  const minSize = 12;
  const maxSize = 24;
  return Math.round(minSize + (count / maxCount) * (maxSize - minSize));
};

const loadMore = () => {
  displayLimit.value += incrementAmount;
};

// Reset limit when search changes
watch(searchQuery, () => {
  displayLimit.value = 100;
});

const editTag = (tag) => {
  editForm.value = { id: tag.id, name: tag.name, color: tag.color || '#6B7280' };
  showEditModal.value = true;
};

const saveTag = async () => {
  try {
    await axios.put(`/api/v1/tags/${editForm.value.id}`, {
      name: editForm.value.name,
      color: editForm.value.color
    });
    
    // Update local state
    const index = allTags.value.findIndex(t => t.id === editForm.value.id);
    if (index !== -1) {
      allTags.value[index].name = editForm.value.name;
      allTags.value[index].color = editForm.value.color;
    }
    
    showEditModal.value = false;
  } catch (error) {
    alert('Failed to update tag');
  }
};

const deleteTag = async (id) => {
  if (!confirm('Delete this tag? It will be removed from all bookmarks.')) return;
  
  try {
    await axios.delete(`/api/v1/tags/${id}`);
    allTags.value = allTags.value.filter(t => t.id !== id);
  } catch (error) {
    alert('Failed to delete tag');
  }
};

onMounted(async () => {
  try {
    await bookmarkStore.fetchTags();
    allTags.value = bookmarkStore.tags || [];
  } finally {
    loading.value = false;
  }
});
</script>
