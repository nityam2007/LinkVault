import { defineStore } from 'pinia';
import axios from 'axios';

export const useAuthStore = defineStore('auth', {
    state: () => ({
        user: null,
        token: localStorage.getItem('auth_token'),
        loading: false,
        error: null,
    }),

    getters: {
        isAuthenticated: (state) => !!state.token,
        getUser: (state) => state.user,
    },

    actions: {
        async login(credentials) {
            this.loading = true;
            this.error = null;

            try {
                const response = await axios.post('/api/v1/auth/login', credentials);
                const { user, token } = response.data;

                this.user = user;
                this.token = token;
                localStorage.setItem('auth_token', token);
                axios.defaults.headers.common['Authorization'] = `Bearer ${token}`;

                return true;
            } catch (error) {
                this.error = error.response?.data?.message || 'Login failed';
                return false;
            } finally {
                this.loading = false;
            }
        },

        async register(userData) {
            this.loading = true;
            this.error = null;

            try {
                const response = await axios.post('/api/v1/auth/register', userData);
                const { user, token } = response.data;

                this.user = user;
                this.token = token;
                localStorage.setItem('auth_token', token);
                axios.defaults.headers.common['Authorization'] = `Bearer ${token}`;

                return true;
            } catch (error) {
                this.error = error.response?.data?.message || 'Registration failed';
                return false;
            } finally {
                this.loading = false;
            }
        },

        async logout() {
            try {
                await axios.post('/api/v1/auth/logout');
            } catch (error) {
                // Ignore errors during logout
            }

            this.user = null;
            this.token = null;
            localStorage.removeItem('auth_token');
            delete axios.defaults.headers.common['Authorization'];
        },

        async fetchUser() {
            if (!this.token) return;

            try {
                const response = await axios.get('/api/v1/auth/user');
                this.user = response.data.data;
            } catch (error) {
                this.logout();
            }
        },

        async updateProfile(data) {
            this.loading = true;
            this.error = null;

            try {
                const response = await axios.put('/api/v1/auth/profile', data);
                this.user = response.data.data;
                return true;
            } catch (error) {
                this.error = error.response?.data?.message || 'Update failed';
                return false;
            } finally {
                this.loading = false;
            }
        },
    },
});
