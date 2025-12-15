<template>
  <div>
    <router-link 
      :to="`/collections/${collection.id}`"
      class="collection-item"
      :style="{ paddingLeft: `${(level * 12) + 8}px` }"
      active-class="active"
    >
      <button 
        v-if="hasChildren"
        @click.prevent="toggleExpand"
        class="mr-1 p-0.5 rounded hover:bg-gray-200 dark:hover:bg-gray-600"
      >
        <svg 
          class="w-4 h-4 transition-transform" 
          :class="{ 'rotate-90': expanded }"
          fill="none" 
          stroke="currentColor" 
          viewBox="0 0 24 24"
        >
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
        </svg>
      </button>
      <span v-else class="w-5"></span>
      
      <span 
        class="w-3 h-3 rounded-full mr-2 flex-shrink-0"
        :style="{ backgroundColor: collection.color || '#6B7280' }"
      ></span>
      
      <span class="truncate flex-1 text-sm text-gray-700 dark:text-gray-200">
        {{ collection.name }}
      </span>
      
      <span class="text-xs text-gray-400 ml-2" :title="`${collection.bookmark_count || 0} direct, ${collection.total_bookmark_count || 0} total`">
        {{ collection.total_bookmark_count || collection.bookmark_count || 0 }}
      </span>
    </router-link>
    
    <Transition name="slide">
      <div v-if="expanded && hasChildren">
        <CollectionTreeItem
          v-for="child in children"
          :key="child.id"
          :collection="child"
          :level="level + 1"
        />
      </div>
    </Transition>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue';

const props = defineProps({
  collection: {
    type: Object,
    required: true,
  },
  level: {
    type: Number,
    default: 0,
  },
});

const expanded = ref(props.level === 0);

const children = computed(() => 
  props.collection.children || props.collection.descendants || []
);

const hasChildren = computed(() => children.value.length > 0);

const toggleExpand = () => {
  expanded.value = !expanded.value;
};
</script>

<style scoped>
.slide-enter-active,
.slide-leave-active {
  transition: all 0.2s ease;
}

.slide-enter-from,
.slide-leave-to {
  opacity: 0;
  max-height: 0;
}

.slide-enter-to,
.slide-leave-from {
  opacity: 1;
  max-height: 500px;
}
</style>
