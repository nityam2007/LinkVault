<template>
  <div class="max-w-2xl mx-auto space-y-6">
    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Import & Export</h1>

    <!-- Import Section -->
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
      <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Import Bookmarks</h2>
      
      <div 
        class="border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-lg p-8 text-center"
        :class="{ 'border-blue-500 bg-blue-50 dark:bg-blue-900/20': isDragging }"
        @dragover.prevent="isDragging = true"
        @dragleave="isDragging = false"
        @drop.prevent="handleDrop"
      >
        <svg class="w-12 h-12 mx-auto text-gray-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
        </svg>
        <p class="text-gray-600 dark:text-gray-400 mb-2">Drag and drop your bookmarks file here</p>
        <p class="text-sm text-gray-500 mb-4">Supports HTML (browser export), JSON, CSV</p>
        <input type="file" ref="fileInput" @change="handleFileSelect" accept=".html,.json,.csv" class="hidden" />
        <button @click="$refs.fileInput.click()" class="btn btn-primary">
          Choose File
        </button>
      </div>

      <!-- Import progress -->
      <div v-if="importing" class="mt-6">
        <div class="flex items-center justify-between mb-2">
          <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Importing...</span>
          <span class="text-sm text-gray-500">{{ importProgress }}%</span>
        </div>
        <div class="import-progress">
          <div class="import-progress-bar" :style="{ width: importProgress + '%' }"></div>
        </div>
      </div>

      <!-- Import result -->
      <div v-if="importResult" class="mt-6 p-4 rounded-lg" :class="importResult.success ? 'bg-green-50 text-green-800' : 'bg-red-50 text-red-800'">
        <p class="font-medium">{{ importResult.message }}</p>
        <p v-if="importResult.imported" class="text-sm mt-1">
          Imported: {{ importResult.imported }} | Skipped: {{ importResult.skipped }} | Failed: {{ importResult.failed }}
        </p>
      </div>
    </div>

    <!-- Export Section -->
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
      <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Export Bookmarks</h2>
      
      <div class="space-y-4">
        <div>
          <label class="form-label">Format</label>
          <select v-model="exportFormat" class="form-input">
            <option value="html">HTML (Browser compatible)</option>
            <option value="json">JSON</option>
            <option value="csv">CSV</option>
            <option value="markdown">Markdown</option>
          </select>
        </div>

        <div>
          <label class="form-label">Collection (optional)</label>
          <select v-model="exportCollection" class="form-input">
            <option :value="null">All Bookmarks</option>
            <option v-for="c in collections" :key="c.id" :value="c.id">{{ c.name }}</option>
          </select>
        </div>

        <div class="flex items-center">
          <input type="checkbox" v-model="includeArchives" id="includeArchives" class="rounded border-gray-300 text-blue-600 mr-2" />
          <label for="includeArchives" class="text-sm text-gray-700 dark:text-gray-300">
            Include archived content (larger file size)
          </label>
        </div>

        <button @click="exportBookmarks" :disabled="exporting" class="btn btn-primary">
          <svg v-if="exporting" class="animate-spin -ml-1 mr-2 h-5 w-5" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
          </svg>
          {{ exporting ? 'Exporting...' : 'Export' }}
        </button>
      </div>
    </div>

    <!-- Import Jobs -->
    <div v-if="importJobs && importJobs.length" class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
      <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Recent Imports</h2>
      <ul class="divide-y divide-gray-200 dark:divide-gray-700">
        <li v-for="job in importJobs" :key="job.id" class="py-3">
          <div class="flex items-center justify-between">
            <div>
              <p class="font-medium text-gray-900 dark:text-white">{{ job.filename }}</p>
              <p class="text-sm text-gray-500">{{ formatDate(job.created_at) }}</p>
            </div>
            <span 
              class="px-2 py-1 rounded text-xs font-medium"
              :class="{
                'bg-green-100 text-green-800': job.status === 'completed',
                'bg-yellow-100 text-yellow-800': job.status === 'processing',
                'bg-red-100 text-red-800': job.status === 'failed',
                'bg-gray-100 text-gray-800': job.status === 'pending'
              }"
            >
              {{ job.status }}
            </span>
          </div>
        </li>
      </ul>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useBookmarkStore } from '../stores/bookmarks';
import axios from 'axios';

const bookmarkStore = useBookmarkStore();

const isDragging = ref(false);
const importing = ref(false);
const importProgress = ref(0);
const importResult = ref(null);
const exporting = ref(false);
const exportFormat = ref('html');
const exportCollection = ref(null);
const includeArchives = ref(false);
const importJobs = ref([]);
const collections = ref([]);

const handleDrop = (e) => {
  isDragging.value = false;
  const files = e.dataTransfer.files;
  if (files.length) {
    processFile(files[0]);
  }
};

const handleFileSelect = (e) => {
  const files = e.target.files;
  if (files.length) {
    processFile(files[0]);
  }
};

const processFile = async (file) => {
  importing.value = true;
  importProgress.value = 0;
  importResult.value = null;

  const formData = new FormData();
  formData.append('file', file);

  try {
    const response = await axios.post('/api/v1/import', formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
      onUploadProgress: (e) => {
        importProgress.value = Math.round((e.loaded / e.total) * 50);
      }
    });

    // Poll for job status
    const jobId = response.data.job?.id || response.data.data?.job_id;
    if (jobId) {
      await pollJobStatus(jobId);
    } else {
      importResult.value = {
        success: true,
        message: 'Import started. Processing in background.'
      };
    }
  } catch (error) {
    importResult.value = {
      success: false,
      message: error.response?.data?.message || 'Import failed'
    };
  } finally {
    importing.value = false;
  }
};

const pollJobStatus = async (jobId) => {
  const poll = async () => {
    try {
      const response = await axios.get(`/api/v1/import/${jobId}/status`);
      const job = response.data.job || response.data.data;

      if (job.status === 'processing') {
        const total = job.total_items || job.total || 1;
        const processed = job.processed_items || job.processed || 0;
        importProgress.value = 50 + Math.round((processed / total) * 50);
        setTimeout(poll, 1000);
      } else if (job.status === 'completed') {
        importProgress.value = 100;
        importResult.value = {
          success: true,
          message: 'Import completed successfully!',
          imported: job.success_count || job.imported || 0,
          skipped: job.skipped_count || job.skipped || 0,
          failed: job.failed_count || job.failed || 0
        };
        fetchImportJobs();
      } else if (job.status === 'failed') {
        importResult.value = {
          success: false,
          message: job.error_log || job.error_message || 'Import failed'
        };
      }
    } catch (err) {
      importResult.value = {
        success: false,
        message: 'Could not check import status'
      };
    }
  };

  await poll();
};

const exportBookmarks = async () => {
  exporting.value = true;

  try {
    const response = await axios.get('/api/v1/export', {
      params: {
        format: exportFormat.value,
        collection_id: exportCollection.value,
        include_archives: includeArchives.value
      },
      responseType: 'blob'
    });

    // Download file
    const url = window.URL.createObjectURL(new Blob([response.data]));
    const link = document.createElement('a');
    link.href = url;
    link.setAttribute('download', `bookmarks.${exportFormat.value}`);
    document.body.appendChild(link);
    link.click();
    link.remove();
  } catch (error) {
    alert('Export failed');
  } finally {
    exporting.value = false;
  }
};

const fetchImportJobs = async () => {
  try {
    const response = await axios.get('/api/v1/import/jobs');
    importJobs.value = response.data.jobs || response.data.data || [];
  } catch (error) {
    // Ignore
  }
};

const formatDate = (date) => {
  return new Date(date).toLocaleString();
};

onMounted(async () => {
  await bookmarkStore.fetchCollections();
  collections.value = bookmarkStore.collections || [];
  fetchImportJobs();
});
</script>
