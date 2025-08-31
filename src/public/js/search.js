/**
 * Search Module
 * Handles advanced product search with fuzzy matching and suggestions
 */

const Search = {
    // Search configuration
    config: {
        minQueryLength: 2,
        debounceDelay: 300,
        maxSuggestions: 10,
        apiBase: '/api'
    },
    
    // Debounce timer
    debounceTimer: null,
    
    // Current search state
    currentQuery: '',
    currentFilters: {},
    
    // Initialize search functionality
    init() {
        this.initSearchBox();
        this.initFilters();
        this.initRegionPrioritization();
    },
    
    // Initialize search box with autocomplete
    initSearchBox() {
        const searchInput = document.getElementById('searchInput');
        const searchForm = document.getElementById('searchForm');
        
        if (!searchInput) return;
        
        // Create suggestions dropdown
        const suggestionsContainer = this.createSuggestionsContainer(searchInput);
        
        // Add event listeners
        searchInput.addEventListener('input', (e) => {
            this.handleSearchInput(e.target.value, suggestionsContainer);
        });
        
        searchInput.addEventListener('keydown', (e) => {
            this.handleSearchKeydown(e, suggestionsContainer);
        });
        
        // Hide suggestions when clicking outside
        document.addEventListener('click', (e) => {
            if (!searchInput.contains(e.target) && !suggestionsContainer.contains(e.target)) {
                this.hideSuggestions(suggestionsContainer);
            }
        });
        
        // Handle form submission
        if (searchForm) {
            searchForm.addEventListener('submit', (e) => {
                e.preventDefault();
                this.performSearch(searchInput.value);
            });
        }
    },
    
    // Create suggestions dropdown container
    createSuggestionsContainer(searchInput) {
        const container = document.createElement('div');
        container.className = 'absolute top-full left-0 right-0 bg-white border border-gray-200 rounded-lg shadow-lg z-50 hidden max-h-64 overflow-y-auto';
        container.id = 'searchSuggestions';
        
        // Insert after search input
        searchInput.parentNode.appendChild(container);
        
        return container;
    },
    
    // Handle search input with debouncing
    handleSearchInput(query, suggestionsContainer) {
        this.currentQuery = query;
        
        // Clear previous timer
        if (this.debounceTimer) {
            clearTimeout(this.debounceTimer);
        }
        
        // Hide suggestions if query too short
        if (query.length < this.config.minQueryLength) {
            this.hideSuggestions(suggestionsContainer);
            return;
        }
        
        // Debounce the API call
        this.debounceTimer = setTimeout(() => {
            this.fetchSuggestions(query, suggestionsContainer);
        }, this.config.debounceDelay);
    },
    
    // Handle keyboard navigation in search
    handleSearchKeydown(e, suggestionsContainer) {
        const suggestions = suggestionsContainer.querySelectorAll('.suggestion-item');
        const activeSuggestion = suggestionsContainer.querySelector('.suggestion-item.active');
        
        switch (e.key) {
            case 'ArrowDown':
                e.preventDefault();
                this.navigateSuggestions(suggestions, activeSuggestion, 'down');
                break;
                
            case 'ArrowUp':
                e.preventDefault();
                this.navigateSuggestions(suggestions, activeSuggestion, 'up');
                break;
                
            case 'Enter':
                if (activeSuggestion) {
                    e.preventDefault();
                    this.selectSuggestion(activeSuggestion.dataset.text);
                    this.hideSuggestions(suggestionsContainer);
                }
                break;
                
            case 'Escape':
                this.hideSuggestions(suggestionsContainer);
                break;
        }
    },
    
    // Navigate through suggestions with arrow keys
    navigateSuggestions(suggestions, activeSuggestion, direction) {
        let newIndex = -1;
        
        if (activeSuggestion) {
            const currentIndex = Array.from(suggestions).indexOf(activeSuggestion);
            newIndex = direction === 'down' ? currentIndex + 1 : currentIndex - 1;
            activeSuggestion.classList.remove('active');
        } else {
            newIndex = direction === 'down' ? 0 : suggestions.length - 1;
        }
        
        // Wrap around
        if (newIndex >= suggestions.length) newIndex = 0;
        if (newIndex < 0) newIndex = suggestions.length - 1;
        
        if (suggestions[newIndex]) {
            suggestions[newIndex].classList.add('active');
        }
    },
    
    // Fetch search suggestions from API
    async fetchSuggestions(query, suggestionsContainer) {
        try {
            const response = await fetch(`${this.config.apiBase}/products/search-suggestions?q=${encodeURIComponent(query)}&limit=${this.config.maxSuggestions}`);
            
            if (!response.ok) {
                throw new Error('Failed to fetch suggestions');
            }
            
            const data = await response.json();
            this.displaySuggestions(data.suggestions, suggestionsContainer, query);
            
        } catch (error) {
            console.error('Search suggestions error:', error);
            this.hideSuggestions(suggestionsContainer);
        }
    },
    
    // Display search suggestions
    displaySuggestions(suggestions, container, originalQuery) {
        if (!suggestions || suggestions.length === 0) {
            this.hideSuggestions(container);
            return;
        }
        
        container.innerHTML = '';
        
        suggestions.forEach((suggestion, index) => {
            const item = document.createElement('div');
            item.className = 'suggestion-item px-4 py-2 cursor-pointer hover:bg-gray-100 flex items-center justify-between';
            item.dataset.text = suggestion.text;
            item.dataset.type = suggestion.type;
            
            // Add type-specific styling
            const typeColor = this.getSuggestionTypeColor(suggestion.type);
            const typeLabel = this.getSuggestionTypeLabel(suggestion.type);
            
            item.innerHTML = `
                <div class="flex items-center space-x-2">
                    <span class="text-gray-900">${this.highlightQuery(suggestion.text, originalQuery)}</span>
                    <span class="text-xs px-2 py-1 rounded ${typeColor}">${typeLabel}</span>
                </div>
                <div class="text-xs text-gray-500">
                    ${suggestion.confidence ? Math.round(suggestion.confidence * 100) + '%' : ''}
                    ${suggestion.count ? suggestion.count + ' products' : ''}
                </div>
            `;
            
            // Add click handler
            item.addEventListener('click', () => {
                this.selectSuggestion(suggestion.text);
                this.hideSuggestions(container);
            });
            
            container.appendChild(item);
        });
        
        // Show suggestions
        container.classList.remove('hidden');
    },
    
    // Get color class for suggestion type
    getSuggestionTypeColor(type) {
        const colors = {
            'typo_correction': 'bg-yellow-100 text-yellow-800',
            'canonical_form': 'bg-blue-100 text-blue-800',
            'category': 'bg-green-100 text-green-800',
            'synonym': 'bg-purple-100 text-purple-800'
        };
        return colors[type] || 'bg-gray-100 text-gray-800';
    },
    
    // Get label for suggestion type
    getSuggestionTypeLabel(type) {
        const labels = {
            'typo_correction': 'Spelling',
            'canonical_form': 'Standard',
            'category': 'Category',
            'synonym': 'Related'
        };
        return labels[type] || type;
    },
    
    // Highlight query in suggestion text
    highlightQuery(text, query) {
        if (!query) return text;
        
        const regex = new RegExp(`(${query.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')})`, 'gi');
        return text.replace(regex, '<strong>$1</strong>');
    },
    
    // Select a suggestion
    selectSuggestion(text) {
        const searchInput = document.getElementById('searchInput');
        if (searchInput) {
            searchInput.value = text;
            this.performSearch(text);
        }
    },
    
    // Hide suggestions dropdown
    hideSuggestions(container) {
        container.classList.add('hidden');
    },
    
    // Initialize search filters
    initFilters() {
        const filterElements = document.querySelectorAll('[data-filter]');
        
        filterElements.forEach(element => {
            element.addEventListener('change', () => {
                this.updateFilters();
                this.performSearch(this.currentQuery);
            });
        });
    },
    
    // Initialize region-based prioritization
    initRegionPrioritization() {
        const userRegion = this.getCurrentUserRegion();
        if (userRegion) {
            this.prioritizeRegion(userRegion);
        }
    },
    
    // Get current user's region
    getCurrentUserRegion() {
        // Try to get from current user data
        const currentUser = Forms?.getCurrentUser?.();
        if (currentUser && currentUser.region) {
            return currentUser.region;
        }
        
        // Fallback to localStorage or other method
        return localStorage.getItem('userRegion') || null;
    },
    
    // Prioritize results from user's region
    prioritizeRegion(region) {
        const regionFilter = document.getElementById('regionFilter');
        if (regionFilter) {
            // Move user's region to top of list if not already selected
            const userRegionOption = regionFilter.querySelector(`option[value="${region}"]`);
            if (userRegionOption && !regionFilter.value) {
                regionFilter.value = region;
                this.updateFilters();
            }
        }
    },
    
    // Update current filters from form elements
    updateFilters() {
        this.currentFilters = {};
        
        // Category filter
        const categoryFilter = document.getElementById('categoryFilter');
        if (categoryFilter && categoryFilter.value) {
            this.currentFilters.category = categoryFilter.value;
        }
        
        // Location/Region filter
        const locationFilter = document.getElementById('locationFilter') || document.getElementById('regionFilter');
        if (locationFilter && locationFilter.value) {
            this.currentFilters.region = locationFilter.value;
        }
        
        // Price filters
        const minPriceFilter = document.getElementById('minPrice');
        const maxPriceFilter = document.getElementById('maxPrice');
        if (minPriceFilter && minPriceFilter.value) {
            this.currentFilters.min_price = parseFloat(minPriceFilter.value);
        }
        if (maxPriceFilter && maxPriceFilter.value) {
            this.currentFilters.max_price = parseFloat(maxPriceFilter.value);
        }
        
        // Organic filter
        const organicFilter = document.getElementById('organicFilter');
        if (organicFilter && organicFilter.checked) {
            this.currentFilters.organic_only = true;
        }
        
        // Available only filter
        const availableFilter = document.getElementById('availableFilter');
        if (availableFilter && availableFilter.checked) {
            this.currentFilters.available_only = true;
        }
    },
    
    // Perform product search
    async performSearch(query) {
        const resultsContainer = document.getElementById('searchResults') || document.getElementById('productsGrid');
        const loadingIndicator = document.getElementById('searchLoading');
        
        if (!resultsContainer) {
            console.error('Search results container not found');
            return;
        }
        
        // Show loading
        if (loadingIndicator) {
            loadingIndicator.classList.remove('hidden');
        }
        
        try {
            // Build query parameters
            const params = new URLSearchParams();
            if (query) {
                params.set('search', query);
            }
            
            // Add filters
            Object.entries(this.currentFilters).forEach(([key, value]) => {
                params.set(key, value);
            });
            
            params.set('limit', '20');
            params.set('offset', '0');
            
            // Fetch results
            const response = await fetch(`${this.config.apiBase}/products?${params.toString()}`);
            
            if (!response.ok) {
                throw new Error('Search failed');
            }
            
            const data = await response.json();
            this.displaySearchResults(data.products, resultsContainer);
            
        } catch (error) {
            console.error('Search error:', error);
            this.displaySearchError(resultsContainer);
        } finally {
            // Hide loading
            if (loadingIndicator) {
                loadingIndicator.classList.add('hidden');
            }
        }
    },
    
    // Display search results
    displaySearchResults(products, container) {
        if (!products || products.length === 0) {
            container.innerHTML = `
                <div class="col-span-full text-center py-8">
                    <i class="fas fa-search text-gray-400 text-4xl mb-4"></i>
                    <p class="text-gray-500 text-lg mb-2">কোনো পণ্য পাওয়া যায়নি</p>
                    <p class="text-gray-400 text-sm">অন্য কীওয়ার্ড দিয়ে খোঁজ করে দেখুন</p>
                </div>
            `;
            return;
        }
        
        container.innerHTML = '';
        
        products.forEach(product => {
            const productCard = this.createProductCard(product);
            container.appendChild(productCard);
        });
    },
    
    // Create product card element
    createProductCard(product) {
        const card = document.createElement('div');
        card.className = 'bg-white rounded-lg shadow hover:shadow-lg transition-shadow duration-200 overflow-hidden';
        
        // Format price
        const price = I18n ? I18n.formatCurrency(product.price_per_unit) : `৳${product.price_per_unit}`;
        
        // Get status color
        const statusColor = this.getStatusColor(product.status);
        
        card.innerHTML = `
            <div class="p-6">
                <div class="flex justify-between items-start mb-4">
                    <h3 class="text-lg font-semibold text-gray-900 line-clamp-2">${product.name}</h3>
                    <span class="px-2 py-1 text-xs rounded-full ${statusColor}">${product.status}</span>
                </div>
                
                <div class="space-y-2 mb-4">
                    <p class="text-gray-600 text-sm">${product.category}</p>
                    <p class="text-2xl font-bold text-green-600">${price}/${product.unit}</p>
                    <p class="text-gray-500 text-sm">${product.quantity} ${product.unit} উপলব্ধ</p>
                    ${product.location ? `<p class="text-gray-500 text-sm"><i class="fas fa-map-marker-alt"></i> ${product.location}</p>` : ''}
                    ${product.farmer_region ? `<p class="text-gray-400 text-xs">অঞ্চল: ${product.farmer_region}</p>` : ''}
                </div>
                
                <div class="flex items-center justify-between">
                    <div class="text-sm text-gray-600">
                        ${product.first_name} ${product.last_name}
                        ${product.organic_certified ? '<span class="ml-2 px-2 py-1 bg-green-100 text-green-800 text-xs rounded">জৈব</span>' : ''}
                    </div>
                    <button class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded text-sm transition-colors" 
                            onclick="Search.viewProduct(${product.id})">
                        বিস্তারিত দেখুন
                    </button>
                </div>
            </div>
        `;
        
        return card;
    },
    
    // Get status color class
    getStatusColor(status) {
        const colors = {
            'available': 'bg-green-100 text-green-800',
            'sold': 'bg-gray-100 text-gray-800',
            'expired': 'bg-red-100 text-red-800',
            'deleted': 'bg-red-100 text-red-800'
        };
        return colors[status] || 'bg-gray-100 text-gray-800';
    },
    
    // Display search error
    displaySearchError(container) {
        container.innerHTML = `
            <div class="col-span-full text-center py-8">
                <i class="fas fa-exclamation-triangle text-red-400 text-4xl mb-4"></i>
                <p class="text-red-500 text-lg mb-2">খোঁজে সমস্যা হয়েছে</p>
                <p class="text-gray-500 text-sm">পরে আবার চেষ্টা করুন</p>
            </div>
        `;
    },
    
    // View product details
    viewProduct(productId) {
        // Implement product details view
        if (typeof Dashboard !== 'undefined' && Dashboard.showProductDetails) {
            Dashboard.showProductDetails(productId);
        } else {
            // Fallback to simple alert or redirect
            window.location.href = `/product/${productId}`;
        }
    },
    
    // Clear search
    clearSearch() {
        const searchInput = document.getElementById('searchInput');
        if (searchInput) {
            searchInput.value = '';
            this.currentQuery = '';
        }
        
        // Reset filters
        this.currentFilters = {};
        
        // Reset filter UI
        const filterElements = document.querySelectorAll('[data-filter]');
        filterElements.forEach(element => {
            if (element.type === 'checkbox') {
                element.checked = false;
            } else {
                element.value = '';
            }
        });
        
        // Reload default results
        this.performSearch('');
    }
};

// Auto-initialize when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => Search.init());
} else {
    Search.init();
}

// Export for use in other modules
if (typeof module !== 'undefined' && module.exports) {
    module.exports = Search;
}
