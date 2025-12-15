<template>
  <div class="reader-view bg-white dark:bg-gray-900 min-h-screen">
    <!-- Header -->
    <header class="sticky top-0 z-10 bg-white dark:bg-gray-900 border-b border-gray-200 dark:border-gray-700">
      <div class="max-w-4xl mx-auto px-4 py-4 flex items-center justify-between">
        <button @click="$emit('close')" class="btn btn-ghost">
          <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
          </svg>
          Back
        </button>
        
        <div class="flex items-center gap-2">
          <a :href="bookmark.url" target="_blank" class="btn btn-ghost">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
            </svg>
          </a>
          <button @click="showSettings = !showSettings" class="btn btn-ghost">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
            </svg>
          </button>
        </div>
      </div>
      
      <!-- Settings panel -->
      <Transition name="slide-down">
        <div v-if="showSettings" class="border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800">
          <div class="max-w-4xl mx-auto px-4 py-3 flex items-center gap-6">
            <div class="flex items-center gap-2">
              <span class="text-sm text-gray-600 dark:text-gray-400">Font Size:</span>
              <button @click="fontSize = Math.max(14, fontSize - 2)" class="btn btn-ghost p-1">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4" />
                </svg>
              </button>
              <span class="text-sm">{{ fontSize }}px</span>
              <button @click="fontSize = Math.min(24, fontSize + 2)" class="btn btn-ghost p-1">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
              </button>
            </div>
            
            <div class="flex items-center gap-2">
              <span class="text-sm text-gray-600 dark:text-gray-400">Width:</span>
              <select v-model="maxWidth" class="text-sm border rounded px-2 py-1">
                <option value="640">Narrow</option>
                <option value="768">Medium</option>
                <option value="1024">Wide</option>
              </select>
            </div>
            
            <div class="flex items-center gap-2">
              <span class="text-sm text-gray-600 dark:text-gray-400">Theme:</span>
              <button 
                @click="theme = theme === 'light' ? 'dark' : 'light'"
                class="btn btn-ghost p-1"
              >
                <svg v-if="theme === 'light'" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" />
                </svg>
                <svg v-else class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z" />
                </svg>
              </button>
            </div>
          </div>
        </div>
      </Transition>
    </header>
    
    <!-- Content -->
    <article class="py-8 px-4" :style="{ fontSize: fontSize + 'px' }">
      <div :style="{ maxWidth: maxWidth + 'px' }" class="mx-auto">
        <!-- Article header -->
        <header class="mb-8">
          <h1 class="text-3xl font-bold text-gray-900 dark:text-white mb-4">
            {{ bookmark.title }}
          </h1>
          
          <div class="flex items-center gap-4 text-sm text-gray-500">
            <span class="flex items-center">
              <img 
                :src="`https://www.google.com/s2/favicons?domain=${domain}&sz=32`"
                class="w-4 h-4 rounded mr-2"
              />
              {{ domain }}
            </span>
            <span>{{ formatDate(bookmark.created_at) }}</span>
            <span v-if="archive?.word_count">{{ archive.word_count }} words</span>
            <span v-if="readingTime">{{ readingTime }} min read</span>
          </div>
        </header>
        
        <!-- Featured image -->
        <img 
          v-if="bookmark.og_image || bookmark.screenshot_url"
          :src="bookmark.og_image || bookmark.screenshot_url"
          :alt="bookmark.title"
          class="w-full rounded-lg mb-8 shadow-lg"
        />
        
        <!-- Article content -->
        <div 
          v-if="archive?.article_html || archive?.article_text"
          class="reader-content"
          v-html="archive.article_html || `<p>${archive.article_text}</p>`"
        ></div>
        
        <!-- Fallback: No archive -->
        <div v-else class="text-center py-12">
          <svg class="w-16 h-16 mx-auto text-gray-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
          </svg>
          <p class="text-gray-500 mb-4">No archived content available</p>
          <a :href="bookmark.url" target="_blank" class="btn btn-primary">
            View Original Page
          </a>
        </div>
        
        <!-- Notes -->
        <div v-if="bookmark.notes" class="mt-12 p-4 bg-yellow-50 dark:bg-yellow-900/20 rounded-lg border border-yellow-200 dark:border-yellow-800">
          <h3 class="font-semibold text-yellow-800 dark:text-yellow-200 mb-2">Notes</h3>
          <p class="text-yellow-700 dark:text-yellow-300 whitespace-pre-wrap">{{ bookmark.notes }}</p>
        </div>
        
        <!-- Tags -->
        <div v-if="bookmark.tags?.length" class="mt-8 flex flex-wrap gap-2">
          <span 
            v-for="tag in bookmark.tags" 
            :key="tag.id"
            class="tag-chip text-sm"
            :style="{ backgroundColor: (tag.color || '#6B7280') + '20', color: tag.color || '#6B7280' }"
          >
            #{{ tag.name }}
          </span>
        </div>
      </div>
    </article>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue';

const props = defineProps({
  bookmark: {
    type: Object,
    required: true,
  },
  archive: {
    type: Object,
    default: null,
  },
});

defineEmits(['close']);

const showSettings = ref(false);
const fontSize = ref(18);
const maxWidth = ref('768');
const theme = ref('light');

const domain = computed(() => {
  try {
    return new URL(props.bookmark.url).hostname.replace('www.', '');
  } catch {
    return props.bookmark.url;
  }
});

const readingTime = computed(() => {
  if (!props.archive?.word_count) return null;
  return Math.ceil(props.archive.word_count / 200);
});

const formatDate = (date) => {
  return new Date(date).toLocaleDateString('en-US', {
    month: 'long',
    day: 'numeric',
    year: 'numeric',
  });
};
</script>

<style scoped>
.slide-down-enter-active,
.slide-down-leave-active {
  transition: all 0.2s ease;
}

.slide-down-enter-from,
.slide-down-leave-to {
  opacity: 0;
  transform: translateY(-10px);
}
</style>
