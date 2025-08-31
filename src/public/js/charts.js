/**
 * Charts Module
 * Handles chart initialization and data visualization for dashboards
 */

const Charts = {
    // Initialize farmer charts
    initFarmerCharts() {
        this.initSalesChart();
        this.initIncomeChart();
        this.initCategoryChart();
        this.initPriceChart();
    },
    
    // Initialize buyer charts
    initBuyerCharts() {
        this.initPriceComparisonChart();
    },
    
    // Initialize admin charts
    initAdminCharts() {
        this.initGrowthChart();
        this.initUserGrowthChart();
        this.initRevenueChart();
        this.initPopularCategoriesChart();
        this.initRegionDistributionChart();
    },
    
    // Sales overview chart for farmers
    initSalesChart() {
        const ctx = document.getElementById('salesChart');
        if (!ctx) return;
        
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: ['জানুয়ারি', 'ফেব্রুয়ারি', 'মার্চ', 'এপ্রিল', 'মে', 'জুন'],
                datasets: [{
                    label: 'বিক্রয় (৳)',
                    data: [12000, 19000, 15000, 25000, 22000, 30000],
                    borderColor: 'rgb(34, 197, 94)',
                    backgroundColor: 'rgba(34, 197, 94, 0.1)',
                    tension: 0.1,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return '৳' + value.toLocaleString();
                            }
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    }
                }
            }
        });
    },
    
    // Income chart for farmers
    initIncomeChart() {
        const ctx = document.getElementById('incomeChart');
        if (!ctx) return;
        
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: ['জানুয়ারি', 'ফেব্রুয়ারি', 'মার্চ', 'এপ্রিল', 'মে', 'জুন'],
                datasets: [{
                    label: 'মাসিক আয়',
                    data: [15000, 23000, 18000, 31000, 28000, 35000],
                    backgroundColor: 'rgba(34, 197, 94, 0.8)',
                    borderColor: 'rgb(34, 197, 94)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return '৳' + value.toLocaleString();
                            }
                        }
                    }
                }
            }
        });
    },
    
    // Category distribution chart
    initCategoryChart() {
        const ctx = document.getElementById('categoryChart');
        if (!ctx) return;
        
        new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: ['ধান', 'গম', 'আলু', 'টমেটো', 'পেঁয়াজ'],
                datasets: [{
                    data: [30, 20, 25, 15, 10],
                    backgroundColor: [
                        '#10B981',
                        '#3B82F6',
                        '#F59E0B',
                        '#EF4444',
                        '#8B5CF6'
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    },
    
    // Price trends chart with real data
    initPriceChart() {
        const ctx = document.getElementById('priceChart');
        if (!ctx) return;
        
        this.loadPriceData().then(data => {
            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: data.labels,
                    datasets: data.datasets
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
                    interaction: {
                        intersect: false,
                        mode: 'index'
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
        }).catch(error => {
            console.error('Failed to load price data:', error);
            // Fallback to sample data
            this.initPriceChartFallback(ctx);
        });
    },
    
    // Fallback price chart with sample data
    initPriceChartFallback(ctx) {
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: Array.from({length: 30}, (_, i) => {
                    const date = new Date();
                    date.setDate(date.getDate() - 29 + i);
                    return date.toLocaleDateString('bn-BD');
                }),
                datasets: [{
                    label: 'ধানের দাম',
                    data: this.generatePriceData(30, 50, 70),
                    borderColor: 'rgb(34, 197, 94)',
                    backgroundColor: 'rgba(34, 197, 94, 0.1)',
                    tension: 0.1
                }, {
                    label: 'গমের দাম',
                    data: this.generatePriceData(30, 45, 65),
                    borderColor: 'rgb(59, 130, 246)',
                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                    tension: 0.1
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
                                return '৳' + value;
                            }
                        }
                    }
                },
                interaction: {
                    intersect: false,
                    mode: 'index'
                }
            }
        });
    },
    
    // Load real price data from API
    async loadPriceData() {
        const category = document.getElementById('priceCategory')?.value || 'rice';
        
        try {
            const response = await fetch(`/api/prices/trends?category=${category}&days=30`);
            if (!response.ok) throw new Error('Failed to fetch');
            
            const data = await response.json();
            const trends = data.trends || [];
            
            // Process data for Chart.js
            const dates = [...new Set(trends.map(t => t.price_date))].sort();
            const locations = [...new Set(trends.map(t => t.market_location))];
            
            const datasets = locations.map((location, index) => {
                const locationData = trends.filter(t => t.market_location === location);
                const colors = [
                    'rgb(34, 197, 94)', 'rgb(59, 130, 246)', 'rgb(245, 158, 11)',
                    'rgb(239, 68, 68)', 'rgb(139, 92, 246)', 'rgb(6, 182, 212)'
                ];
                
                return {
                    label: location,
                    data: dates.map(date => {
                        const dayData = locationData.find(d => d.price_date === date);
                        return dayData ? parseFloat(dayData.avg_price) : null;
                    }),
                    borderColor: colors[index % colors.length],
                    backgroundColor: colors[index % colors.length] + '20',
                    tension: 0.1,
                    spanGaps: true
                };
            });
            
            return {
                labels: dates.map(date => new Date(date).toLocaleDateString('bn-BD')),
                datasets: datasets
            };
            
        } catch (error) {
            throw error;
        }
    },
    
    // Price comparison chart for buyers
    initPriceComparisonChart() {
        const ctx = document.getElementById('priceComparisonChart');
        if (!ctx) return;
        
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: ['ঢাকা', 'চট্টগ্রাম', 'সিলেট', 'রাজশাহী', 'খুলনা'],
                datasets: [{
                    label: 'আজকের দাম',
                    data: [65, 62, 58, 55, 60],
                    backgroundColor: 'rgba(34, 197, 94, 0.8)'
                }, {
                    label: 'গত সপ্তাহের দাম',
                    data: [68, 65, 60, 58, 63],
                    backgroundColor: 'rgba(156, 163, 175, 0.8)'
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
                                return '৳' + value;
                            }
                        }
                    }
                }
            }
        });
    },
    
    // Growth chart for admin
    initGrowthChart() {
        const ctx = document.getElementById('growthChart');
        if (!ctx) return;
        
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: ['জানুয়ারি', 'ফেব্রুয়ারি', 'মার্চ', 'এপ্রিল', 'মে', 'জুন'],
                datasets: [{
                    label: 'নতুন ব্যবহারকারী',
                    data: [65, 78, 92, 108, 134, 156],
                    borderColor: 'rgb(59, 130, 246)',
                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                    tension: 0.1,
                    fill: true
                }, {
                    label: 'নতুন পণ্য',
                    data: [28, 35, 42, 58, 72, 89],
                    borderColor: 'rgb(34, 197, 94)',
                    backgroundColor: 'rgba(34, 197, 94, 0.1)',
                    tension: 0.1,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    },
    
    // User growth chart for admin
    initUserGrowthChart() {
        const ctx = document.getElementById('userGrowthChart');
        if (!ctx) return;
        
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: ['কৃষক', 'ক্রেতা', 'অ্যাডমিন'],
                datasets: [{
                    label: 'ব্যবহারকারীর সংখ্যা',
                    data: [756, 432, 12],
                    backgroundColor: [
                        'rgba(34, 197, 94, 0.8)',
                        'rgba(59, 130, 246, 0.8)',
                        'rgba(147, 51, 234, 0.8)'
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    },
    
    // Revenue chart for admin
    initRevenueChart() {
        const ctx = document.getElementById('revenueChart');
        if (!ctx) return;
        
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: ['জানুয়ারি', 'ফেব্রুয়ারি', 'মার্চ', 'এপ্রিল', 'মে', 'জুন'],
                datasets: [{
                    label: 'রেভিনিউ (৳)',
                    data: [125000, 152000, 178000, 203000, 234000, 267000],
                    borderColor: 'rgb(245, 158, 11)',
                    backgroundColor: 'rgba(245, 158, 11, 0.1)',
                    tension: 0.1,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return '৳' + (value / 1000) + 'K';
                            }
                        }
                    }
                }
            }
        });
    },
    
    // Popular categories chart for admin
    initPopularCategoriesChart() {
        const ctx = document.getElementById('popularCategoriesChart');
        if (!ctx) return;
        
        new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: ['ধান', 'গম', 'আলু', 'সবজি', 'ফল'],
                datasets: [{
                    data: [35, 25, 20, 15, 5],
                    backgroundColor: [
                        '#10B981',
                        '#3B82F6',
                        '#F59E0B',
                        '#EF4444',
                        '#8B5CF6'
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    },
    
    // Region distribution chart for admin
    initRegionDistributionChart() {
        const ctx = document.getElementById('regionDistributionChart');
        if (!ctx) return;
        
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: ['ঢাকা', 'চট্টগ্রাম', 'সিলেট', 'রাজশাহী', 'খুলনা', 'বরিশাল', 'রংপুর', 'ময়মনসিংহ'],
                datasets: [{
                    label: 'ব্যবহারকারী',
                    data: [325, 189, 147, 178, 134, 98, 112, 156],
                    backgroundColor: 'rgba(99, 102, 241, 0.8)'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    },
    
    // Helper function to generate sample price data
    generatePriceData(count, min, max) {
        const data = [];
        let current = (min + max) / 2;
        
        for (let i = 0; i < count; i++) {
            // Add some randomness to simulate price fluctuations
            const change = (Math.random() - 0.5) * 5;
            current = Math.max(min, Math.min(max, current + change));
            data.push(Math.round(current * 100) / 100);
        }
        
        return data;
    }
};

// Export for use in other modules
if (typeof module !== 'undefined' && module.exports) {
    module.exports = Charts;
}
