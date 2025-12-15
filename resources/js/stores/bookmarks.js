import { defineStore } from 'pinia';
import axios from 'axios';

export const useBookmarkStore = defineStore('bookmarks', {
    state: () => ({
        bookmarks: [],
        currentBookmark: null,
        collections: [],
        tags: [],
        popularTags: [],
        pagination: {
            currentPage: 1,
            lastPage: 1,
            perPage: 50,
            total: 0,
        },
        filters: {
            search: '',
            collectionId: null,
            tags: [],
            isFavorite: null,
            isArchived: null,
            sortBy: 'created_at',
            sortDir: 'desc',
        },
        viewMode: localStorage.getItem('viewMode') || 'grid',
        loading: false,
        error: null,
    }),

    getters: {
        getBookmarks: (state) => state.bookmarks,
        getCollections: (state) => state.collections,
        getTags: (state) => state.tags,
        isLoading: (state) => state.loading,
    },

    actions: {
        // Bookmarks
        async fetchBookmarks(page = 1) {
            this.loading = true;
            this.error = null;

            try {
                const params = {
                    page,
                    per_page: this.pagination.perPage,
                    ...this.filters,
                };

                // Clean up null values
                Object.keys(params).forEach(key => {
                    if (params[key] === null || params[key] === '') {
                        delete params[key];
                    }
                });

                if (params.tags?.length) {
                    params.tags = params.tags.join(',');
                }

                const response = await axios.get('/api/v1/bookmarks', { params });
                
                // Handle both response formats (data.data or data.bookmarks)
                this.bookmarks = response.data.data || response.data.bookmarks || [];
                
                // Handle both pagination formats (meta or pagination)
                const paginationData = response.data.meta || response.data.pagination || {};
                this.pagination = {
                    currentPage: paginationData.current_page || 1,
                    lastPage: paginationData.last_page || 1,
                    perPage: paginationData.per_page || 50,
                    total: paginationData.total || 0,
                };
            } catch (error) {
                this.error = error.response?.data?.message || 'Failed to fetch bookmarks';
            } finally {
                this.loading = false;
            }
        },

        async fetchBookmark(id) {
            this.loading = true;

            try {
                const response = await axios.get(`/api/v1/bookmarks/${id}`);
                this.currentBookmark = response.data.data;
                return this.currentBookmark;
            } catch (error) {
                this.error = error.response?.data?.message || 'Failed to fetch bookmark';
                return null;
            } finally {
                this.loading = false;
            }
        },

        async createBookmark(data) {
            this.loading = true;

            try {
                const response = await axios.post('/api/v1/bookmarks', data);
                this.bookmarks.unshift(response.data.data);
                return response.data.data;
            } catch (error) {
                this.error = error.response?.data?.message || 'Failed to create bookmark';
                return null;
            } finally {
                this.loading = false;
            }
        },

        async updateBookmark(id, data) {
            this.loading = true;

            try {
                const response = await axios.put(`/api/v1/bookmarks/${id}`, data);
                const index = this.bookmarks.findIndex(b => b.id === id);
                if (index !== -1) {
                    this.bookmarks[index] = response.data.data;
                }
                if (this.currentBookmark?.id === id) {
                    this.currentBookmark = response.data.data;
                }
                return response.data.data;
            } catch (error) {
                this.error = error.response?.data?.message || 'Failed to update bookmark';
                return null;
            } finally {
                this.loading = false;
            }
        },

        async deleteBookmark(id) {
            try {
                await axios.delete(`/api/v1/bookmarks/${id}`);
                this.bookmarks = this.bookmarks.filter(b => b.id !== id);
                return true;
            } catch (error) {
                this.error = error.response?.data?.message || 'Failed to delete bookmark';
                return false;
            }
        },

        async bulkAction(action, ids) {
            try {
                await axios.post('/api/v1/bookmarks/bulk', { action, ids });
                await this.fetchBookmarks(this.pagination.currentPage);
                return true;
            } catch (error) {
                this.error = error.response?.data?.message || 'Bulk action failed';
                return false;
            }
        },

        async archiveBookmark(id) {
            try {
                await axios.post(`/api/v1/bookmarks/${id}/archive`);
                const bookmark = this.bookmarks.find(b => b.id === id);
                if (bookmark) {
                    bookmark.archive_status = 'pending';
                }
                return true;
            } catch (error) {
                this.error = error.response?.data?.message || 'Archive failed';
                return false;
            }
        },

        // Collections
        async fetchCollections() {
            try {
                const response = await axios.get('/api/v1/collections');
                this.collections = response.data.collections || response.data.data || [];
            } catch (error) {
                this.error = error.response?.data?.message || 'Failed to fetch collections';
            }
        },

        async createCollection(data) {
            try {
                const response = await axios.post('/api/v1/collections', data);
                this.collections.push(response.data.data);
                return response.data.data;
            } catch (error) {
                this.error = error.response?.data?.message || 'Failed to create collection';
                return null;
            }
        },

        async updateCollection(id, data) {
            try {
                const response = await axios.put(`/api/v1/collections/${id}`, data);
                const index = this.collections.findIndex(c => c.id === id);
                if (index !== -1) {
                    this.collections[index] = response.data.data;
                }
                return response.data.data;
            } catch (error) {
                this.error = error.response?.data?.message || 'Failed to update collection';
                return null;
            }
        },

        async deleteCollection(id) {
            try {
                await axios.delete(`/api/v1/collections/${id}`);
                this.collections = this.collections.filter(c => c.id !== id);
                return true;
            } catch (error) {
                this.error = error.response?.data?.message || 'Failed to delete collection';
                return false;
            }
        },

        // Tags
        async fetchTags() {
            try {
                const response = await axios.get('/api/v1/tags');
                // API may return tags in response.data.tags, response.data.data, or as array directly
                this.tags = response.data.tags || response.data.data || (Array.isArray(response.data) ? response.data : []);
            } catch (error) {
                this.error = error.response?.data?.message || 'Failed to fetch tags';
            }
        },

        async fetchPopularTags(limit = 20) {
            try {
                const response = await axios.get('/api/v1/tags', { 
                    params: { sort_by: 'bookmarks_count', per_page: limit } 
                });
                // API may return tags in response.data.tags, response.data.data, or as array directly
                this.popularTags = response.data.tags || response.data.data || (Array.isArray(response.data) ? response.data : []);
            } catch (error) {
                // Silently fail for popular tags
            }
        },

        async createTag(data) {
            try {
                const response = await axios.post('/api/v1/tags', data);
                this.tags.push(response.data.data);
                return response.data.data;
            } catch (error) {
                this.error = error.response?.data?.message || 'Failed to create tag';
                return null;
            }
        },

        // Search
        async search(query) {
            this.loading = true;

            try {
                const response = await axios.get('/api/v1/bookmarks/search', {
                    params: { q: query }
                });
                return response.data.data;
            } catch (error) {
                this.error = error.response?.data?.message || 'Search failed';
                return [];
            } finally {
                this.loading = false;
            }
        },

        // Filters
        setFilter(key, value) {
            this.filters[key] = value;
        },

        resetFilters() {
            this.filters = {
                search: '',
                collectionId: null,
                tags: [],
                isFavorite: null,
                isArchived: null,
                sortBy: 'created_at',
                sortDir: 'desc',
            };
        },

        setViewMode(mode) {
            this.viewMode = mode;
            localStorage.setItem('viewMode', mode);
        },
    },
});
