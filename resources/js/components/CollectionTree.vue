<template>
  <div class="space-y-1">
    <div 
      v-for="collection in rootCollections" 
      :key="collection.id"
    >
      <CollectionTreeItem :collection="collection" :level="0" />
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';
import CollectionTreeItem from './CollectionTreeItem.vue';

const props = defineProps({
  collections: {
    type: Array,
    default: () => [],
  },
});

const rootCollections = computed(() => 
  (props.collections || []).filter(c => !c.parent_id)
);
</script>
