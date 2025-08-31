/**
 * Price Analytics Module
 * Handles advanced price analytics, forecasting, and "where to sell" recommendations
 */

const PriceAnalytics = {
    // API base URL
    apiBase: '/api',
    
    // Current chart instances
    charts: {},
    
    // Initialize price analytics
    init() {
        this.initPriceCategory();
        this.initWhereToSellForm();
        this.initAnomalyMonitoring();
        this.loadInitialData();
    },
    
    // Initialize price category selector
    initPriceCategory() {
        const categorySelect = document.getElementById('priceCategory');
        if (categorySelect) {
            categorySelect.addEventListener('change', () => {
                this.loadPriceData(categorySelect.value);
            });
        }
    },
    
    // Initialize "where to sell" form
    initWhereToSellForm() {
        const form = document.getElementById('whereToSellForm');
        if (form) {
            form.addEventListener('submit', (e) => {
                e.preventDefault();
                this.generateWhereToSellRecommendations();
            });
        }
    },
    
    // Initialize anomaly monitoring
    initAnomalyMonitoring() {
        // Auto-refresh anomaly alerts every 5 minutes
        setInterval(() => {
            this.checkForAnomalies();
        }, 5 * 60 * 1000);
        
        // Initial check
        this.checkForAnomalies();
    },
    
    // Load initial data
    loadInitialData() {
        this.loadCurrentPrices();
        this.loadRegionalComparison();
        this.loadForecastChart();
    },
    
    // Load current market prices
    async loadCurrentPrices() {
        try {
            const response = await fetch(`${this.apiBase}/prices/current`);
            if (!response.ok) throw new Error('Failed to fetch current prices');
            
            const data = await response.json();
            this.displayCurrentPrices(data.current_prices);
            
        } catch (error) {
            console.error('Error loading current prices:', error);
            this.showError('Failed to load current market prices');
        }
    },
    
    // Display current prices in cards
    displayCurrentPrices(prices) {
        const container = document.getElementById('currentPricesContainer');
        if (!container) return;
        
        container.innerHTML = '';
        
        if (!prices || prices.length === 0) {
            container.innerHTML = '<p class="text-gray-500">No current price data available</p>';
            return;
        }
        
        // Group by category
        const pricesByCategory = {};
        prices.forEach(price => {
            if (!pricesByCategory[price.category]) {
                pricesByCategory[price.category] = [];
            }
            pricesByCategory[price.category].push(price);
        });
        
        Object.entries(pricesByCategory).forEach(([category, categoryPrices]) => {
            const avgPrice = categoryPrices.reduce((sum, p) => sum + parseFloat(p.price_per_unit), 0) / categoryPrices.length;
            
            const card = document.createElement('div');
            card.className = 'bg-white rounded-lg shadow p-4 hover:shadow-lg transition-shadow';
            card.innerHTML = `
                <div class="flex items-center justify-between mb-2">
                    <h4 class="text-lg font-semibold text-gray-900">${this.translateCategory(category)}</h4>
                    <span class="text-2xl font-bold text-green-600">৳${avgPrice.toFixed(2)}</span>
                </div>
                <div class="text-sm text-gray-600">
                    <p>${categoryPrices.length} বাজার থেকে তথ্য</p>
                    <p class="text-xs text-gray-400">সর্বশেষ আপডেট: ${new Date().toLocaleString('bn-BD')}</p>
                </div>
                <div class="mt-2">
                    <button onclick="PriceAnalytics.showPriceDetails('${category}')" 
                            class="text-blue-600 text-sm hover:text-blue-800">
                        বিস্তারিত দেখুন
                    </button>
                </div>
            `;
            container.appendChild(card);
        });
    },
    
    // Load regional price comparison
    async loadRegionalComparison() {
        try {
            const category = document.getElementById('priceCategory')?.value || 'rice';
            const response = await fetch(`${this.apiBase}/prices/regional-comparison?category=${category}`);
            if (!response.ok) throw new Error('Failed to fetch regional comparison');
            
            const data = await response.json();
            this.displayRegionalComparison(data.regional_comparison);
            
        } catch (error) {
            console.error('Error loading regional comparison:', error);
            this.showError('Failed to load regional price comparison');
        }
    },
    
    // Display regional comparison chart
    displayRegionalComparison(regionalData) {
        const ctx = document.getElementById('regionalComparisonChart');
        if (!ctx) return;
        
        if (this.charts.regionalComparison) {
            this.charts.regionalComparison.destroy();
        }
        
        const locations = regionalData.map(d => d.location);
        const avgPrices = regionalData.map(d => d.avg_price);
        const minPrices = regionalData.map(d => d.min_price);
        const maxPrices = regionalData.map(d => d.max_price);
        
        this.charts.regionalComparison = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: locations,
                datasets: [{
                    label: 'গড় দাম',
                    data: avgPrices,
                    backgroundColor: 'rgba(34, 197, 94, 0.8)',
                    borderColor: 'rgb(34, 197, 94)',
                    borderWidth: 1
                }, {
                    label: 'সর্বনিম্ন দাম',
                    data: minPrices,
                    backgroundColor: 'rgba(59, 130, 246, 0.5)',
                    borderColor: 'rgb(59, 130, 246)',
                    borderWidth: 1
                }, {
                    label: 'সর্বোচ্চ দাম',
                    data: maxPrices,
                    backgroundColor: 'rgba(239, 68, 68, 0.5)',
                    borderColor: 'rgb(239, 68, 68)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: false,
                        ticks: {
                            callback: function(value) {
                                return '৳' + value.toFixed(2);
                            }
                        }
                    }
                },
                plugins: {
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return context.dataset.label + ': ৳' + context.parsed.y.toFixed(2);
                            }
                        }
                    }
                }
            }
        });
    },
    
    // Load price forecast with confidence bands
    async loadForecastChart() {
        try {
            const category = document.getElementById('priceCategory')?.value || 'rice';
            const location = 'Dhaka'; // Default location
            
            const response = await fetch(`${this.apiBase}/prices/forecast?category=${category}&location=${location}&days=7`);
            if (!response.ok) throw new Error('Failed to fetch forecast');
            
            const data = await response.json();
            this.displayForecastChart(data.forecast);
            
        } catch (error) {
            console.error('Error loading forecast:', error);
            this.showError('Failed to load price forecast');
        }
    },
    
    // Display forecast chart with confidence bands
    displayForecastChart(forecastData) {
        const ctx = document.getElementById('forecastChart');
        if (!ctx) return;
        
        if (this.charts.forecast) {
            this.charts.forecast.destroy();
        }
        
        const labels = forecastData.forecasts.map(f => new Date(f.date).toLocaleDateString('bn-BD'));
        const predictions = forecastData.forecasts.map(f => f.predicted_price);
        const lowerBounds = forecastData.forecasts.map(f => f.lower_bound);
        const upperBounds = forecastData.forecasts.map(f => f.upper_bound);
        const confidences = forecastData.forecasts.map(f => f.confidence);
        
        this.charts.forecast = new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'পূর্বাভাস',
                    data: predictions,
                    borderColor: 'rgb(34, 197, 94)',
                    backgroundColor: 'rgba(34, 197, 94, 0.1)',
                    borderWidth: 3,
                    tension: 0.1
                }, {
                    label: 'নিম্ন সীমা',
                    data: lowerBounds,
                    borderColor: 'rgb(156, 163, 175)',
                    backgroundColor: 'transparent',
                    borderDash: [5, 5],
                    borderWidth: 1,
                    pointStyle: false
                }, {
                    label: 'উচ্চ সীমা',
                    data: upperBounds,
                    borderColor: 'rgb(156, 163, 175)',
                    backgroundColor: 'rgba(156, 163, 175, 0.1)',
                    borderDash: [5, 5],
                    borderWidth: 1,
                    pointStyle: false,
                    fill: '-1'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: false,
                        ticks: {
                            callback: function(value) {
                                return '৳' + value.toFixed(2);
                            }
                        }
                    }
                },
                plugins: {
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const datasetLabel = context.dataset.label;
                                const value = context.parsed.y.toFixed(2);
                                
                                if (datasetLabel === 'পূর্বাভাস') {
                                    const confidence = (confidences[context.dataIndex] * 100).toFixed(0);
                                    return `${datasetLabel}: ৳${value} (আস্থা: ${confidence}%)`;
                                }
                                
                                return `${datasetLabel}: ৳${value}`;
                            }
                        }
                    }
                }
            }
        });
    },
    
    // Generate "where to sell" recommendations
    async generateWhereToSellRecommendations() {
        const form = document.getElementById('whereToSellForm');
        const loadingDiv = document.getElementById('whereToSellLoading');
        const resultsDiv = document.getElementById('whereToSellResults');
        
        if (!form) return;
        
        const formData = new FormData(form);
        const category = formData.get('category');
        const farmerLocation = formData.get('farmer_location');
        const quantity = formData.get('quantity');
        
        if (!category || !farmerLocation || !quantity) {
            this.showError('Please fill in all required fields');
            return;
        }
        
        // Show loading
        if (loadingDiv) loadingDiv.classList.remove('hidden');
        if (resultsDiv) resultsDiv.innerHTML = '';
        
        try {
            const params = new URLSearchParams({
                category,
                farmer_location: farmerLocation,
                quantity
            });
            
            const response = await fetch(`${this.apiBase}/prices/where-to-sell?${params}`);
            if (!response.ok) throw new Error('Failed to get recommendations');
            
            const data = await response.json();
            this.displayWhereToSellResults(data.recommendations);
            
        } catch (error) {
            console.error('Error generating recommendations:', error);
            this.showError('Failed to generate selling recommendations');
        } finally {
            if (loadingDiv) loadingDiv.classList.add('hidden');
        }
    },
    
    // Display "where to sell" results
    displayWhereToSellResults(recommendations) {
        const container = document.getElementById('whereToSellResults');
        if (!container) return;
        
        container.innerHTML = '';
        
        if (!recommendations || recommendations.length === 0) {
            container.innerHTML = '<p class="text-gray-500">No recommendations available</p>';
            return;
        }
        
        recommendations.forEach((rec, index) => {
            const profitClass = rec.net_profit > 0 ? 'text-green-600' : 'text-red-600';
            const rankBadge = index === 0 ? 'bg-green-100 text-green-800' : 
                           index === 1 ? 'bg-blue-100 text-blue-800' : 'bg-gray-100 text-gray-800';
            
            const card = document.createElement('div');
            card.className = 'bg-white rounded-lg shadow p-6 border-l-4 border-l-blue-500';
            card.innerHTML = `
                <div class="flex justify-between items-start mb-4">
                    <div>
                        <h4 class="text-lg font-semibold text-gray-900">${rec.market_location}</h4>
                        <span class="inline-block px-2 py-1 text-xs rounded ${rankBadge}">
                            #${index + 1} সুপারিশ
                        </span>
                    </div>
                    <div class="text-right">
                        <p class="text-2xl font-bold ${profitClass}">৳${rec.net_profit.toLocaleString()}</p>
                        <p class="text-sm text-gray-600">নিট লাভ</p>
                    </div>
                </div>
                
                <div class="grid grid-cols-2 gap-4 mb-4">
                    <div>
                        <p class="text-sm text-gray-600">বর্তমান দাম</p>
                        <p class="font-semibold">৳${rec.current_price}</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600">পূর্বাভাসিত দাম</p>
                        <p class="font-semibold">৳${rec.forecasted_price}</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600">পরিবহন খরচ</p>
                        <p class="font-semibold">৳${rec.transport_cost.toLocaleString()}</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600">লাভের হার</p>
                        <p class="font-semibold ${profitClass}">${rec.profit_margin.toFixed(1)}%</p>
                    </div>
                </div>
                
                <div class="flex justify-between items-center text-sm text-gray-600">
                    <span>দূরত্ব: ${rec.transport_distance} কিমি</span>
                    <span>সময়: ${rec.transport_time} ঘন্টা</span>
                    <span>আস্থা: ${(rec.confidence * 100).toFixed(0)}%</span>
                </div>
                
                <div class="mt-4">
                    <div class="w-full bg-gray-200 rounded-full h-2">
                        <div class="bg-blue-600 h-2 rounded-full" 
                             style="width: ${rec.confidence * 100}%"></div>
                    </div>
                </div>
            `;
            
            container.appendChild(card);
        });
    },
    
    // Check for price anomalies
    async checkForAnomalies() {
        try {
            const category = 'rice'; // Check for rice anomalies as default
            const response = await fetch(`${this.apiBase}/prices/anomalies?category=${category}`);
            if (!response.ok) return; // Fail silently for background checks
            
            const data = await response.json();
            if (data.anomalies && data.anomalies.anomalies.length > 0) {
                this.displayAnomalyAlert(data.anomalies);
            }
            
        } catch (error) {
            console.debug('Anomaly check failed:', error);
        }
    },
    
    // Display anomaly alert
    displayAnomalyAlert(anomalyData) {
        const alertContainer = document.getElementById('anomalyAlerts');
        if (!alertContainer) return;
        
        const severityClass = anomalyData.anomalies.some(a => a.severity === 'high') ? 
            'bg-red-100 border-red-500 text-red-700' : 'bg-yellow-100 border-yellow-500 text-yellow-700';
        
        const alert = document.createElement('div');
        alert.className = `border-l-4 p-4 mb-4 ${severityClass}`;
        alert.innerHTML = `
            <div class="flex">
                <div class="flex-shrink-0">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <div class="ml-3">
                    <p class="text-sm font-medium">
                        দামের অস্বাভাবিকতা সনাক্ত হয়েছে
                    </p>
                    <p class="text-sm mt-1">
                        ${anomalyData.anomalies.length}টি দামের অস্বাভাবিকতা পাওয়া গেছে। 
                        <button onclick="PriceAnalytics.showAnomalyDetails()" class="underline">
                            বিস্তারিত দেখুন
                        </button>
                    </p>
                </div>
                <div class="ml-auto">
                    <button onclick="this.parentElement.parentElement.parentElement.remove()" 
                            class="text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
        `;
        
        alertContainer.appendChild(alert);
    },
    
    // Show price details modal
    showPriceDetails(category) {
        // Implementation for price details modal
        console.log('Show price details for:', category);
    },
    
    // Show anomaly details modal
    showAnomalyDetails() {
        // Implementation for anomaly details modal
        console.log('Show anomaly details');
    },
    
    // Load price data for specific category
    async loadPriceData(category) {
        try {
            // Reload regional comparison
            await this.loadRegionalComparison();
            
            // Reload forecast
            await this.loadForecastChart();
            
            // Update charts
            if (Charts.initPriceChart) {
                Charts.initPriceChart();
            }
            
        } catch (error) {
            console.error('Error loading price data:', error);
        }
    },
    
    // Translate category to Bengali
    translateCategory(category) {
        const translations = {
            'rice': 'ধান',
            'wheat': 'গম',
            'potato': 'আলু',
            'tomato': 'টমেটো',
            'onion': 'পেঁয়াজ',
            'eggplant': 'বেগুন',
            'carrot': 'গাজর',
            'cabbage': 'বাঁধাকপি',
            'cauliflower': 'ফুলকপি'
        };
        return translations[category] || category;
    },
    
    // Show error message
    showError(message) {
        // Simple error display - in production, use a proper notification system
        console.error(message);
        alert(message);
    }
};

// Auto-initialize when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => PriceAnalytics.init());
} else {
    PriceAnalytics.init();
}

// Export for use in other modules
if (typeof module !== 'undefined' && module.exports) {
    module.exports = PriceAnalytics;
}
