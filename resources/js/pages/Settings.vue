<template>
  <div class="max-w-2xl mx-auto space-y-6">
    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Settings</h1>

    <!-- Profile -->
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
      <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Profile</h2>
      <form @submit.prevent="updateProfile" class="space-y-4">
        <div>
          <label class="form-label">Username</label>
          <input v-model="profile.username" type="text" class="form-input" />
        </div>
        <div>
          <label class="form-label">Email</label>
          <input v-model="profile.email" type="email" class="form-input" />
        </div>
        <button type="submit" :disabled="saving" class="btn btn-primary">
          {{ saving ? 'Saving...' : 'Update Profile' }}
        </button>
      </form>
    </div>

    <!-- Password -->
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
      <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Change Password</h2>
      <form @submit.prevent="updatePassword" class="space-y-4">
        <div>
          <label class="form-label">Current Password</label>
          <input v-model="password.current" type="password" class="form-input" />
        </div>
        <div>
          <label class="form-label">New Password</label>
          <input v-model="password.new" type="password" class="form-input" />
        </div>
        <div>
          <label class="form-label">Confirm New Password</label>
          <input v-model="password.confirm" type="password" class="form-input" />
        </div>
        <button type="submit" :disabled="savingPassword" class="btn btn-primary">
          {{ savingPassword ? 'Saving...' : 'Update Password' }}
        </button>
      </form>
    </div>

    <!-- Preferences -->
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
      <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Preferences</h2>
      <div class="space-y-4">
        <div class="flex items-center justify-between">
          <div>
            <p class="font-medium text-gray-900 dark:text-white">Auto-archive new bookmarks</p>
            <p class="text-sm text-gray-500">Automatically archive pages when adding bookmarks</p>
          </div>
          <label class="relative inline-flex items-center cursor-pointer">
            <input type="checkbox" v-model="preferences.auto_archive" class="sr-only peer" @change="updatePreferences">
            <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 dark:peer-focus:ring-blue-800 rounded-full peer dark:bg-gray-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-gray-600 peer-checked:bg-blue-600"></div>
          </label>
        </div>

        <div class="flex items-center justify-between">
          <div>
            <p class="font-medium text-gray-900 dark:text-white">Dark mode</p>
            <p class="text-sm text-gray-500">Use dark theme</p>
          </div>
          <label class="relative inline-flex items-center cursor-pointer">
            <input type="checkbox" v-model="preferences.dark_mode" class="sr-only peer" @change="toggleDarkMode">
            <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 dark:peer-focus:ring-blue-800 rounded-full peer dark:bg-gray-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-gray-600 peer-checked:bg-blue-600"></div>
          </label>
        </div>

        <div>
          <label class="form-label">Default view mode</label>
          <select v-model="preferences.view_mode" class="form-input" @change="updatePreferences">
            <option value="grid">Grid</option>
            <option value="list">List</option>
          </select>
        </div>

        <div>
          <label class="form-label">Bookmarks per page</label>
          <select v-model="preferences.per_page" class="form-input" @change="updatePreferences">
            <option :value="25">25</option>
            <option :value="50">50</option>
            <option :value="100">100</option>
          </select>
        </div>
      </div>
    </div>

    <!-- API Token -->
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
      <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">API Access</h2>
      <p class="text-sm text-gray-500 mb-4">Use this token to access the API from external applications.</p>
      <div class="flex gap-2">
        <input 
          :type="showToken ? 'text' : 'password'"
          :value="apiToken"
          readonly
          class="form-input flex-1 font-mono text-sm"
        />
        <button @click="showToken = !showToken" class="btn btn-secondary">
          {{ showToken ? 'Hide' : 'Show' }}
        </button>
        <button @click="regenerateToken" class="btn btn-secondary">
          Regenerate
        </button>
      </div>
    </div>

    <!-- Danger Zone -->
    <div class="bg-red-50 dark:bg-red-900/20 rounded-lg p-6">
      <h2 class="text-lg font-semibold text-red-800 dark:text-red-200 mb-4">Danger Zone</h2>
      <div class="space-y-4">
        <div class="flex items-center justify-between">
          <div>
            <p class="font-medium text-red-800 dark:text-red-200">Delete all bookmarks</p>
            <p class="text-sm text-red-600 dark:text-red-300">This will permanently delete all your bookmarks</p>
          </div>
          <button @click="deleteAllBookmarks" class="btn btn-danger">Delete All</button>
        </div>
        <div class="flex items-center justify-between pt-4 border-t border-red-200 dark:border-red-800">
          <div>
            <p class="font-medium text-red-800 dark:text-red-200">Delete account</p>
            <p class="text-sm text-red-600 dark:text-red-300">Permanently delete your account and all data</p>
          </div>
          <button @click="deleteAccount" class="btn btn-danger">Delete Account</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '../stores/auth';
import axios from 'axios';

const router = useRouter();
const authStore = useAuthStore();

const saving = ref(false);
const savingPassword = ref(false);
const showToken = ref(false);
const apiToken = ref('••••••••••••••••••••••••••••••••');

const profile = reactive({
  username: '',
  email: '',
});

const password = reactive({
  current: '',
  new: '',
  confirm: '',
});

const preferences = reactive({
  auto_archive: true,
  dark_mode: false,
  view_mode: 'grid',
  per_page: 50,
});

onMounted(() => {
  if (authStore.user) {
    profile.username = authStore.user.username;
    profile.email = authStore.user.email;
  }
  
  // Load preferences from localStorage
  preferences.dark_mode = document.documentElement.classList.contains('dark');
  preferences.view_mode = localStorage.getItem('viewMode') || 'grid';
  preferences.per_page = parseInt(localStorage.getItem('perPage') || '50');
});

const updateProfile = async () => {
  saving.value = true;
  try {
    await authStore.updateProfile(profile);
    alert('Profile updated successfully');
  } catch (error) {
    alert('Failed to update profile');
  } finally {
    saving.value = false;
  }
};

const updatePassword = async () => {
  if (password.new !== password.confirm) {
    alert('Passwords do not match');
    return;
  }

  savingPassword.value = true;
  try {
    await axios.put('/api/v1/auth/password', {
      current_password: password.current,
      password: password.new,
      password_confirmation: password.confirm,
    });
    alert('Password updated successfully');
    password.current = '';
    password.new = '';
    password.confirm = '';
  } catch (error) {
    alert(error.response?.data?.message || 'Failed to update password');
  } finally {
    savingPassword.value = false;
  }
};

const updatePreferences = () => {
  localStorage.setItem('viewMode', preferences.view_mode);
  localStorage.setItem('perPage', preferences.per_page.toString());
};

const toggleDarkMode = () => {
  document.documentElement.classList.toggle('dark', preferences.dark_mode);
  localStorage.setItem('darkMode', preferences.dark_mode ? 'true' : 'false');
};

const regenerateToken = async () => {
  if (confirm('Are you sure? This will invalidate your current token.')) {
    try {
      const response = await axios.post('/api/v1/auth/token/regenerate');
      apiToken.value = response.data.data.token;
      showToken.value = true;
      alert('New token generated. Make sure to copy it now.');
    } catch (error) {
      alert('Failed to regenerate token');
    }
  }
};

const deleteAllBookmarks = async () => {
  if (confirm('Are you sure you want to delete ALL bookmarks? This cannot be undone.')) {
    if (confirm('This is your last chance. Are you absolutely sure?')) {
      try {
        await axios.delete('/api/v1/bookmarks/all');
        alert('All bookmarks deleted');
      } catch (error) {
        alert('Failed to delete bookmarks');
      }
    }
  }
};

const deleteAccount = async () => {
  if (confirm('Are you sure you want to delete your account? This cannot be undone.')) {
    const email = prompt('Type your email to confirm:');
    if (email === profile.email) {
      try {
        await axios.delete('/api/v1/auth/account');
        authStore.logout();
        router.push('/login');
      } catch (error) {
        alert('Failed to delete account');
      }
    }
  }
};
</script>
