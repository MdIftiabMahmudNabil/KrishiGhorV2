/**
 * Dashboard Module
 * Handles role-specific dashboard functionality and region-aware widgets
 */

const Dashboard = {
    currentUser: null,
    
    // Initialize farmer dashboard
    initFarmer() {
        this.currentUser = Forms.getCurrentUser();
        this.loadFarmerData();
        this.initSidebarNavigation();
        this.loadRegionAwareContent();
    },
    
    // Initialize buyer dashboard
    initBuyer() {
        this.currentUser = Forms.getCurrentUser();
        this.loadBuyerData();
        this.initSidebarNavigation();
        this.loadRegionAwareContent();
    },
    
    // Initialize admin dashboard
    initAdmin() {
        this.currentUser = Forms.getCurrentUser();
        this.loadAdminData();
        this.initSidebarNavigation();
        this.loadSystemOverview();
    },
    
    // Load farmer-specific data
    async loadFarmerData() {
        try {
            // Load farmer stats
            const stats = await Forms.apiRequest('/products/farmer/' + this.currentUser.user_id);
            if (stats && stats.ok) {
                const data = await stats.json();
                this.updateFarmerStats(data);
            }
            
            // Load recent orders
            const orders = await Forms.apiRequest('/orders/farmer/' + this.currentUser.user_id + '?limit=5');
            if (orders && orders.ok) {
                const orderData = await orders.json();
                this.displayRecentOrders(orderData.orders);
            }
            
            // Load user name
            this.updateUserName();
            
        } catch (error) {
            console.error('Error loading farmer data:', error);
        }
    },
    
    // Load buyer-specific data
    async loadBuyerData() {
        try {
            // Load buyer stats
            const orders = await Forms.apiRequest('/orders/buyer/' + this.currentUser.user_id);
            if (orders && orders.ok) {
                const data = await orders.json();
                this.updateBuyerStats(data);
            }
            
            // Load featured products
            this.loadFeaturedProducts();
            
            // Load user name
            this.updateUserName();
            
        } catch (error) {
            console.error('Error loading buyer data:', error);
        }
    },
    
    // Load admin-specific data
    async loadAdminData() {
        try {
            // Load system stats
            const stats = await Forms.apiRequest('/admin/stats');
            if (stats && stats.ok) {
                const data = await stats.json();
                this.updateAdminStats(data);
            }
            
            // Load recent activity
            this.loadRecentActivity();
            
            // Load user name
            this.updateUserName();
            
        } catch (error) {
            console.error('Error loading admin data:', error);
        }
    },
    
    // Update farmer statistics
    updateFarmerStats(data) {
        const totalProducts = document.getElementById('totalProducts');
        const pendingOrders = document.getElementById('pendingOrders');
        const monthlySales = document.getElementById('monthlySales');
        const farmerRating = document.getElementById('farmerRating');
        
        if (totalProducts) totalProducts.textContent = data.total_products || '0';
        if (pendingOrders) pendingOrders.textContent = data.pending_orders || '0';
        if (monthlySales) monthlySales.textContent = I18n.formatCurrency(data.monthly_sales || 0);
        if (farmerRating) farmerRating.textContent = data.rating || '4.8';
    },
    
    // Update buyer statistics
    updateBuyerStats(data) {
        const totalOrders = document.getElementById('totalOrders');
        const pendingDeliveries = document.getElementById('pendingDeliveries');
        const monthlySpending = document.getElementById('monthlySpending');
        const savedProducts = document.getElementById('savedProducts');
        
        if (totalOrders) totalOrders.textContent = data.total_orders || '0';
        if (pendingDeliveries) pendingDeliveries.textContent = data.pending_deliveries || '0';
        if (monthlySpending) monthlySpending.textContent = I18n.formatCurrency(data.monthly_spending || 0);
        if (savedProducts) savedProducts.textContent = data.saved_products || '0';
    },
    
    // Update admin statistics
    updateAdminStats(data) {
        const totalUsers = document.getElementById('totalUsers');
        const activeProducts = document.getElementById('activeProducts');
        const dailyOrders = document.getElementById('dailyOrders');
        const totalRevenue = document.getElementById('totalRevenue');
        
        if (totalUsers) totalUsers.textContent = I18n.formatNumber(data.total_users || 0);
        if (activeProducts) activeProducts.textContent = I18n.formatNumber(data.active_products || 0);
        if (dailyOrders) dailyOrders.textContent = data.daily_orders || '0';
        if (totalRevenue) totalRevenue.textContent = I18n.formatCurrency(data.total_revenue || 0);
    },
    
    // Load region-aware content
    loadRegionAwareContent() {
        if (this.currentUser && this.currentUser.region) {
            // Load region-specific market prices
            this.loadRegionalMarketPrices(this.currentUser.region);
            
            // Load regional weather if applicable
            this.loadRegionalWeather(this.currentUser.region);
            
            // Customize content based on region
            this.customizeRegionalContent(this.currentUser.region);
        }
    },
    
    // Load regional market prices
    async loadRegionalMarketPrices(region) {
        try {
            const response = await Forms.apiRequest('/prices/region/' + encodeURIComponent(region));
            if (response && response.ok) {
                const data = await response.json();
                this.displayRegionalPrices(data.prices);
            }
        } catch (error) {
            console.debug('Regional price data not available:', error);
        }
    },
    
    // Load regional weather
    async loadRegionalWeather(region) {
        try {
            const response = await Forms.apiRequest('/weather/region/' + encodeURIComponent(region));
            if (response && response.ok) {
                const data = await response.json();
                this.displayWeatherWidget(data.weather);
            }
        } catch (error) {
            console.debug('Weather data not available:', error);
        }
    },
    
    // Customize content based on region
    customizeRegionalContent(region) {
        // Update location filters to prioritize local areas
        const locationFilters = document.querySelectorAll('#locationFilter');
        locationFilters.forEach(filter => {
            // Move user's region to top of list
            const userRegionOption = filter.querySelector(`option[value="${region.toLowerCase()}"]`);
            if (userRegionOption) {
                userRegionOption.selected = true;
                filter.insertBefore(userRegionOption, filter.firstChild.nextSibling);
            }
        });
        
        // Update welcome messages with region
        const welcomeMessages = document.querySelectorAll('[data-i18n*="welcome_message"]');
        welcomeMessages.forEach(element => {
            if (element.textContent.includes('আপনার')) {
                element.textContent = element.textContent.replace('আপনার', `${region} অঞ্চলে আপনার`);
            }
        });
    },
    
    // Display recent orders
    displayRecentOrders(orders) {
        const container = document.getElementById('recentOrdersList');
        if (!container || !orders) return;
        
        container.innerHTML = '';
        
        if (orders.length === 0) {
            container.innerHTML = '<p class="text-gray-500 text-sm">কোনো সাম্প্রতিক অর্ডার নেই</p>';
            return;
        }
        
        orders.forEach(order => {
            const orderElement = document.createElement('div');
            orderElement.className = 'flex items-center justify-between p-3 bg-gray-50 rounded-lg';
            orderElement.innerHTML = `
                <div>
                    <p class="font-medium text-gray-900">${order.product_name}</p>
                    <p class="text-sm text-gray-600">${order.buyer_first_name} ${order.buyer_last_name}</p>
                </div>
                <div class="text-right">
                    <p class="font-medium text-gray-900">${I18n.formatCurrency(order.total_amount)}</p>
                    <span class="px-2 py-1 text-xs rounded ${this.getStatusColor(order.order_status)}">${order.order_status}</span>
                </div>
            `;
            container.appendChild(orderElement);
        });
    },
    
    // Display featured products
    async loadFeaturedProducts() {
        try {
            const response = await Forms.apiRequest('/products?limit=5&featured=true');
            if (response && response.ok) {
                const data = await response.json();
                this.displayFeaturedProducts(data.products);
            }
        } catch (error) {
            console.error('Error loading featured products:', error);
        }
    },
    
    // Display featured products
    displayFeaturedProducts(products) {
        const container = document.getElementById('featuredProductsList');
        if (!container || !products) return;
        
        container.innerHTML = '';
        
        if (products.length === 0) {
            container.innerHTML = '<p class="text-gray-500 text-sm">কোনো বৈশিষ্ট্যযুক্ত পণ্য নেই</p>';
            return;
        }
        
        products.forEach(product => {
            const productElement = document.createElement('div');
            productElement.className = 'flex items-center justify-between p-3 bg-gray-50 rounded-lg cursor-pointer hover:bg-gray-100';
            productElement.innerHTML = `
                <div>
                    <p class="font-medium text-gray-900">${product.name}</p>
                    <p class="text-sm text-gray-600">${product.farmer_first_name} ${product.farmer_last_name}</p>
                </div>
                <div class="text-right">
                    <p class="font-medium text-gray-900">${I18n.formatCurrency(product.price_per_unit)}/${product.unit}</p>
                    <p class="text-sm text-gray-600">${product.location}</p>
                </div>
            `;
            
            productElement.addEventListener('click', () => {
                this.showProductDetails(product.id);
            });
            
            container.appendChild(productElement);
        });
    },
    
    // Get status color classes
    getStatusColor(status) {
        const colors = {
            'pending': 'bg-yellow-100 text-yellow-800',
            'confirmed': 'bg-blue-100 text-blue-800',
            'processing': 'bg-purple-100 text-purple-800',
            'shipped': 'bg-indigo-100 text-indigo-800',
            'delivered': 'bg-green-100 text-green-800',
            'cancelled': 'bg-red-100 text-red-800'
        };
        return colors[status] || 'bg-gray-100 text-gray-800';
    },
    
    // Initialize sidebar navigation
    initSidebarNavigation() {
        const sidebarLinks = document.querySelectorAll('.sidebar-link');
        const contentSections = document.querySelectorAll('.content-section');
        
        sidebarLinks.forEach(link => {
            link.addEventListener('click', (e) => {
                e.preventDefault();
                
                const sectionId = link.getAttribute('data-section');
                
                // Update active link
                sidebarLinks.forEach(l => l.classList.remove('active', 'bg-green-100', 'bg-blue-100', 'bg-purple-100', 'text-green-700', 'text-blue-700', 'text-purple-700'));
                link.classList.add('active');
                
                // Add role-specific colors
                if (this.currentUser) {
                    if (this.currentUser.role === 'farmer') {
                        link.classList.add('bg-green-100', 'text-green-700');
                    } else if (this.currentUser.role === 'buyer') {
                        link.classList.add('bg-blue-100', 'text-blue-700');
                    } else if (this.currentUser.role === 'admin') {
                        link.classList.add('bg-purple-100', 'text-purple-700');
                    }
                }
                
                // Show corresponding section
                contentSections.forEach(section => {
                    if (section.id === sectionId + 'Section') {
                        section.classList.remove('hidden');
                    } else {
                        section.classList.add('hidden');
                    }
                });
                
                // Load section-specific data
                this.loadSectionData(sectionId);
            });
        });
        
        // Initialize user menu dropdown
        this.initUserMenu();
    },
    
    // Load section-specific data
    loadSectionData(sectionId) {
        switch (sectionId) {
            case 'products':
                this.loadProductsSection();
                break;
            case 'orders':
                this.loadOrdersSection();
                break;
            case 'analytics':
                this.loadAnalyticsSection();
                break;
            case 'browse':
                this.loadBrowseSection();
                break;
            default:
                break;
        }
    },
    
    // Load products section (for farmers)
    async loadProductsSection() {
        if (this.currentUser.role !== 'farmer') return;
        
        try {
            const response = await Forms.apiRequest('/products/farmer/' + this.currentUser.user_id);
            if (response && response.ok) {
                const data = await response.json();
                this.displayProductsGrid(data.products);
            }
        } catch (error) {
            console.error('Error loading products:', error);
        }
    },
    
    // Display products grid
    displayProductsGrid(products) {
        const grid = document.getElementById('productsGrid');
        if (!grid) return;
        
        grid.innerHTML = '';
        
        if (!products || products.length === 0) {
            grid.innerHTML = '<div class="col-span-full text-center text-gray-500 py-8">কোনো পণ্য নেই</div>';
            return;
        }
        
        products.forEach(product => {
            const productCard = document.createElement('div');
            productCard.className = 'bg-white rounded-lg shadow p-6 hover:shadow-lg transition-shadow';
            productCard.innerHTML = `
                <div class="flex justify-between items-start mb-4">
                    <h3 class="text-lg font-semibold text-gray-900">${product.name}</h3>
                    <span class="px-2 py-1 text-xs rounded ${this.getStatusColor(product.status)}">${product.status}</span>
                </div>
                <p class="text-gray-600 mb-2">${product.category}</p>
                <p class="text-2xl font-bold text-green-600 mb-2">${I18n.formatCurrency(product.price_per_unit)}/${product.unit}</p>
                <p class="text-gray-500 mb-4">${product.quantity} ${product.unit} উপলব্ধ</p>
                <div class="flex space-x-2">
                    <button class="flex-1 bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700" onclick="Dashboard.editProduct(${product.id})">
                        সম্পাদনা
                    </button>
                    <button class="flex-1 bg-red-600 text-white px-4 py-2 rounded hover:bg-red-700" onclick="Dashboard.deleteProduct(${product.id})">
                        মুছুন
                    </button>
                </div>
            `;
            grid.appendChild(productCard);
        });
    },
    
    // Initialize user menu
    initUserMenu() {
        const userMenuButton = document.getElementById('userMenuButton');
        const userDropdown = document.getElementById('userDropdown');
        const logoutBtn = document.getElementById('logoutBtn');
        
        if (userMenuButton && userDropdown) {
            userMenuButton.addEventListener('click', () => {
                userDropdown.classList.toggle('hidden');
            });
            
            // Close dropdown when clicking outside
            document.addEventListener('click', (e) => {
                if (!userMenuButton.contains(e.target) && !userDropdown.contains(e.target)) {
                    userDropdown.classList.add('hidden');
                }
            });
        }
        
        if (logoutBtn) {
            logoutBtn.addEventListener('click', (e) => {
                e.preventDefault();
                Forms.logout();
            });
        }
    },
    
    // Update user name in navigation
    updateUserName() {
        const userNameElement = document.getElementById('userName');
        if (userNameElement && this.currentUser) {
            const name = this.currentUser.first_name || this.currentUser.email;
            userNameElement.textContent = name;
        }
    },
    
    // Show product details modal
    showProductDetails(productId) {
        // Implementation for product details modal
        console.log('Show product details for:', productId);
    },
    
    // Edit product
    editProduct(productId) {
        console.log('Edit product:', productId);
    },
    
    // Delete product
    deleteProduct(productId) {
        if (confirm('আপনি কি নিশ্চিত যে এই পণ্যটি মুছে ফেলতে চান?')) {
            console.log('Delete product:', productId);
        }
    }
};

// Export for use in other modules
if (typeof module !== 'undefined' && module.exports) {
    module.exports = Dashboard;
}
