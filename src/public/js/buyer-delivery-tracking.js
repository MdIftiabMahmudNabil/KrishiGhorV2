/**
 * Buyer Delivery Tracking Module
 * Handles delivery tracking for buyers
 */

const BuyerDeliveryTracking = {
    // API base URL
    apiBase: '/api',
    
    // Tracking update interval (30 seconds)
    trackingInterval: 30000,
    
    // Active tracking intervals
    activeTracking: new Map(),
    
    // Initialize delivery tracking
    init() {
        this.initDeliveryTracking();
        this.loadActiveDeliveries();
        this.loadDeliveryHistory();
    },
    
    // Initialize delivery tracking functionality
    initDeliveryTracking() {
        const statusFilter = document.getElementById('deliveryStatusFilter');
        if (statusFilter) {
            statusFilter.addEventListener('change', () => {
                this.loadActiveDeliveries();
            });
        }
        
        const refreshBtn = document.getElementById('refreshDeliveriesBtn');
        if (refreshBtn) {
            refreshBtn.addEventListener('click', () => {
                this.loadActiveDeliveries();
                this.loadDeliveryHistory();
            });
        }
    },
    
    // Load active deliveries
    async loadActiveDeliveries() {
        const container = document.getElementById('activeDeliveriesContainer');
        if (!container) return;
        
        const statusFilter = document.getElementById('deliveryStatusFilter');
        const status = statusFilter ? statusFilter.value : '';
        
        try {
            const params = new URLSearchParams();
            if (status) params.append('status', status);
            params.append('buyer', 'true'); // Mark as buyer request
            
            const response = await this.apiRequest(`/transport?${params}`);
            
            if (response.ok) {
                const data = await response.json();
                this.displayActiveDeliveries(data.transports);
            } else {
                throw new Error('Failed to load deliveries');
            }
            
        } catch (error) {
            console.error('Load active deliveries error:', error);
            container.innerHTML = '<p class="text-red-500 text-center py-8">ডেলিভারি তথ্য লোড করতে সমস্যা হয়েছে</p>';
        }
    },
    
    // Display active deliveries
    displayActiveDeliveries(deliveries) {
        const container = document.getElementById('activeDeliveriesContainer');
        if (!container) return;
        
        if (!deliveries || deliveries.length === 0) {
            container.innerHTML = '<p class="text-gray-500 text-center py-8">কোনো সক্রিয় ডেলিভারি নেই</p>';
            return;
        }
        
        container.innerHTML = '';
        
        deliveries.forEach(delivery => {
            const deliveryCard = this.createDeliveryCard(delivery);
            container.appendChild(deliveryCard);
        });
        
        // Start tracking for active deliveries
        this.startTrackingForActiveDeliveries(deliveries);
    },
    
    // Create delivery card
    createDeliveryCard(delivery) {
        const card = document.createElement('div');
        card.className = 'bg-white rounded-lg shadow hover:shadow-lg transition-shadow border-l-4 border-blue-500';
        card.setAttribute('data-delivery-id', delivery.id);
        
        const statusColor = this.getDeliveryStatusColor(delivery.status);
        const progressPercentage = this.calculateProgress(delivery.status);
        
        card.innerHTML = `
            <div class="p-6">
                <div class="flex justify-between items-start mb-4">
                    <div>
                        <div class="flex items-center space-x-2 mb-2">
                            <h3 class="text-lg font-semibold text-gray-900">অর্ডার #${delivery.order_id}</h3>
                            <span class="px-2 py-1 text-xs rounded ${statusColor}">${this.translateDeliveryStatus(delivery.status)}</span>
                        </div>
                        <p class="text-sm text-gray-600">ট্র্যাকিং: ${delivery.tracking_number || 'N/A'}</p>
                        <p class="text-sm text-gray-600">পণ্য: ${delivery.product_name}</p>
                    </div>
                    <div class="text-right">
                        <div class="text-sm text-gray-500">${new Date(delivery.created_at).toLocaleDateString('bn-BD')}</div>
                        ${delivery.eta ? `<div class="text-sm font-medium text-blue-600">ETA: ${new Date(delivery.eta).toLocaleString('bn-BD')}</div>` : ''}
                    </div>
                </div>
                
                <!-- Progress Bar -->
                <div class="mb-4">
                    <div class="flex justify-between text-sm text-gray-600 mb-1">
                        <span>অগ্রগতি</span>
                        <span>${progressPercentage}%</span>
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-2">
                        <div class="bg-blue-600 h-2 rounded-full transition-all duration-300" style="width: ${progressPercentage}%"></div>
                    </div>
                </div>
                
                <!-- Delivery Steps -->
                <div class="mb-4">
                    <div class="flex justify-between text-xs">
                        <div class="text-center ${this.isStepCompleted('assigned', delivery.status) ? 'text-green-600' : 'text-gray-400'}">
                            <div class="w-4 h-4 rounded-full mx-auto mb-1 ${this.isStepCompleted('assigned', delivery.status) ? 'bg-green-600' : 'bg-gray-300'}"></div>
                            <div>বরাদ্দ</div>
                        </div>
                        <div class="text-center ${this.isStepCompleted('picked_up', delivery.status) ? 'text-green-600' : 'text-gray-400'}">
                            <div class="w-4 h-4 rounded-full mx-auto mb-1 ${this.isStepCompleted('picked_up', delivery.status) ? 'bg-green-600' : 'bg-gray-300'}"></div>
                            <div>পিকআপ</div>
                        </div>
                        <div class="text-center ${this.isStepCompleted('in_transit', delivery.status) ? 'text-green-600' : 'text-gray-400'}">
                            <div class="w-4 h-4 rounded-full mx-auto mb-1 ${this.isStepCompleted('in_transit', delivery.status) ? 'bg-green-600' : 'bg-gray-300'}"></div>
                            <div>পরিবহনে</div>
                        </div>
                        <div class="text-center ${this.isStepCompleted('delivered', delivery.status) ? 'text-green-600' : 'text-gray-400'}">
                            <div class="w-4 h-4 rounded-full mx-auto mb-1 ${this.isStepCompleted('delivered', delivery.status) ? 'bg-green-600' : 'bg-gray-300'}"></div>
                            <div>ডেলিভার</div>
                        </div>
                    </div>
                </div>
                
                ${delivery.current_location ? `
                    <div class="mb-4 bg-gray-50 rounded-lg p-3">
                        <div class="flex justify-between items-center">
                            <div>
                                <p class="text-sm font-medium text-gray-900">বর্তমান অবস্থান</p>
                                <p class="text-xs text-gray-500">সর্বশেষ আপডেট: ${new Date(delivery.current_location.updated_at).toLocaleString('bn-BD')}</p>
                            </div>
                            <div class="text-right">
                                <p class="text-sm text-gray-600">গতি: ${delivery.current_speed || 0} km/h</p>
                                <p class="text-xs text-gray-500">${delivery.current_location.latitude?.toFixed(4)}, ${delivery.current_location.longitude?.toFixed(4)}</p>
                            </div>
                        </div>
                    </div>
                ` : ''}
                
                <div class="flex space-x-2">
                    <button onclick="BuyerDeliveryTracking.viewLiveTracking(${delivery.id})" 
                            class="flex-1 bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
                        <i class="fas fa-map-marker-alt mr-2"></i>
                        লাইভ ট্র্যাকিং
                    </button>
                    
                    ${delivery.status === 'delivered' ? `
                        <button onclick="BuyerDeliveryTracking.rateDelivery(${delivery.id})" 
                                class="flex-1 bg-yellow-600 text-white px-4 py-2 rounded hover:bg-yellow-700">
                            <i class="fas fa-star mr-2"></i>
                            রেটিং দিন
                        </button>
                    ` : ''}
                    
                    <button onclick="BuyerDeliveryTracking.contactDriver(${delivery.id})" 
                            class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700">
                        <i class="fas fa-phone mr-2"></i>
                        যোগাযোগ
                    </button>
                </div>
                
                ${delivery.alerts && delivery.alerts.length > 0 ? `
                    <div class="mt-4 bg-yellow-50 border border-yellow-200 rounded-lg p-3">
                        <div class="flex items-center">
                            <i class="fas fa-exclamation-triangle text-yellow-500 mr-2"></i>
                            <span class="text-sm text-yellow-800">${delivery.alerts.length} টি সতর্কতা</span>
                        </div>
                    </div>
                ` : ''}
            </div>
        `;
        
        return card;
    },
    
    // View live tracking
    async viewLiveTracking(deliveryId) {
        try {
            const response = await this.apiRequest(`/transport/${deliveryId}/tracking`);
            
            if (response.ok) {
                const data = await response.json();
                this.showLiveTrackingModal(data);
            } else {
                throw new Error('Failed to load tracking data');
            }
            
        } catch (error) {
            console.error('View live tracking error:', error);
            this.showError('ট্র্যাকিং তথ্য লোড করতে সমস্যা হয়েছে');
        }
    },
    
    // Show live tracking modal
    showLiveTrackingModal(trackingData) {
        const modal = document.createElement('div');
        modal.className = 'fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50';
        modal.innerHTML = `
            <div class="relative top-10 mx-auto p-5 border w-full max-w-4xl shadow-lg rounded-md bg-white">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-xl font-bold text-gray-900">লাইভ ট্র্যাকিং - অর্ডার #${trackingData.order_id}</h3>
                    <button onclick="this.closest('.fixed').remove()" class="text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
                
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- Current Status -->
                    <div class="bg-blue-50 rounded-lg p-4">
                        <h4 class="font-semibold mb-3">বর্তমান অবস্থা</h4>
                        <div class="space-y-2 text-sm">
                            <div><span class="font-medium">স্ট্যাটাস:</span> ${this.translateDeliveryStatus(trackingData.current_status)}</div>
                            <div><span class="font-medium">গতি:</span> ${trackingData.current_speed || 0} km/h</div>
                            ${trackingData.current_location ? `
                                <div><span class="font-medium">অবস্থান:</span> ${trackingData.current_location.latitude?.toFixed(4)}, ${trackingData.current_location.longitude?.toFixed(4)}</div>
                            ` : ''}
                            <div><span class="font-medium">সর্বশেষ আপডেট:</span> ${trackingData.last_updated ? new Date(trackingData.last_updated).toLocaleString('bn-BD') : 'N/A'}</div>
                        </div>
                    </div>
                    
                    <!-- Driver Information -->
                    ${trackingData.driver_info ? `
                        <div class="bg-green-50 rounded-lg p-4">
                            <h4 class="font-semibold mb-3">চালকের তথ্য</h4>
                            <div class="space-y-2 text-sm">
                                <div><span class="font-medium">নাম:</span> ${trackingData.driver_info.name || 'N/A'}</div>
                                <div><span class="font-medium">ফোন:</span> ${trackingData.driver_info.phone || 'N/A'}</div>
                                <div><span class="font-medium">গাড়ির নম্বর:</span> ${trackingData.driver_info.vehicle_number || 'N/A'}</div>
                                <button onclick="BuyerDeliveryTracking.callDriver('${trackingData.driver_info.phone}')" 
                                        class="w-full mt-2 bg-green-600 text-white px-3 py-2 rounded hover:bg-green-700">
                                    <i class="fas fa-phone mr-2"></i>
                                    চালককে কল করুন
                                </button>
                            </div>
                        </div>
                    ` : ''}
                </div>
                
                <!-- Estimated Arrival -->
                ${trackingData.eta_prediction ? `
                    <div class="mt-6 bg-purple-50 rounded-lg p-4">
                        <h4 class="font-semibold mb-3">আনুমানিক পৌঁছানোর সময়</h4>
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                            <div>
                                <div class="font-medium">ETA</div>
                                <div class="text-lg">${trackingData.eta_prediction.estimated_arrival ? new Date(trackingData.eta_prediction.estimated_arrival).toLocaleString('bn-BD') : 'N/A'}</div>
                            </div>
                            <div>
                                <div class="font-medium">অবশিষ্ট দূরত্ব</div>
                                <div class="text-lg">${trackingData.eta_prediction.remaining_distance || 0} km</div>
                            </div>
                            <div>
                                <div class="font-medium">অবশিষ্ট সময়</div>
                                <div class="text-lg">${trackingData.eta_prediction.estimated_duration_minutes || 0} মিনিট</div>
                            </div>
                            <div>
                                <div class="font-medium">নির্ভুলতা</div>
                                <div class="text-lg">${trackingData.eta_prediction.confidence_level || 0}%</div>
                            </div>
                        </div>
                    </div>
                ` : ''}
                
                <!-- Recent Updates -->
                ${trackingData.recent_updates && trackingData.recent_updates.length > 0 ? `
                    <div class="mt-6 bg-gray-50 rounded-lg p-4">
                        <h4 class="font-semibold mb-3">সাম্প্রতিক আপডেট</h4>
                        <div class="space-y-2 max-h-40 overflow-y-auto">
                            ${trackingData.recent_updates.map(update => `
                                <div class="flex justify-between items-center text-sm border-b border-gray-200 pb-2">
                                    <span>${update.message}</span>
                                    <span class="text-xs text-gray-500">${new Date(update.timestamp).toLocaleString('bn-BD')}</span>
                                </div>
                            `).join('')}
                        </div>
                    </div>
                ` : ''}
                
                <div class="mt-6 flex justify-end space-x-3">
                    <button onclick="BuyerDeliveryTracking.reportIssue(${trackingData.transport_id})" 
                            class="px-4 py-2 bg-red-600 text-white rounded hover:bg-red-700">
                        <i class="fas fa-exclamation-triangle mr-2"></i>
                        সমস্যা রিপোর্ট করুন
                    </button>
                    <button onclick="this.closest('.fixed').remove()" 
                            class="px-4 py-2 bg-gray-600 text-white rounded hover:bg-gray-700">
                        বন্ধ করুন
                    </button>
                </div>
            </div>
        `;
        
        document.body.appendChild(modal);
        
        // Start real-time updates for this modal
        const updateInterval = setInterval(async () => {
            if (!document.body.contains(modal)) {
                clearInterval(updateInterval);
                return;
            }
            
            try {
                const response = await this.apiRequest(`/transport/${trackingData.transport_id}/tracking`);
                if (response.ok) {
                    const updatedData = await response.json();
                    // Update modal content with new data
                    // Implementation would update specific elements
                }
            } catch (error) {
                console.error('Real-time update error:', error);
            }
        }, this.trackingInterval);
    },
    
    // Rate delivery
    rateDelivery(deliveryId) {
        const modal = document.createElement('div');
        modal.className = 'fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50';
        modal.innerHTML = `
            <div class="relative top-20 mx-auto p-5 border w-full max-w-lg shadow-lg rounded-md bg-white">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-bold text-gray-900">ডেলিভারি রেটিং</h3>
                    <button onclick="this.closest('.fixed').remove()" class="text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                
                <form id="ratingForm">
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">সামগ্রিক রেটিং</label>
                        <div class="flex space-x-1">
                            ${[1,2,3,4,5].map(star => `
                                <button type="button" onclick="BuyerDeliveryTracking.setRating(${star})" 
                                        class="star-btn text-2xl text-gray-300 hover:text-yellow-400 focus:outline-none" data-star="${star}">
                                    <i class="fas fa-star"></i>
                                </button>
                            `).join('')}
                        </div>
                        <input type="hidden" name="rating" id="selectedRating" required>
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">মন্তব্য (ঐচ্ছিক)</label>
                        <textarea name="comment" rows="3" class="w-full border border-gray-300 rounded px-3 py-2" placeholder="আপনার অভিজ্ঞতা শেয়ার করুন"></textarea>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-4 mb-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">সময়ানুবর্তিতা</label>
                            <select name="timeliness_rating" class="w-full border border-gray-300 rounded px-3 py-2">
                                <option value="5">খুবই ভাল</option>
                                <option value="4">ভাল</option>
                                <option value="3">গড়</option>
                                <option value="2">খারাপ</option>
                                <option value="1">খুবই খারাপ</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">পণ্যের অবস্থা</label>
                            <select name="condition_rating" class="w-full border border-gray-300 rounded px-3 py-2">
                                <option value="5">চমৎকার</option>
                                <option value="4">ভাল</option>
                                <option value="3">গড়</option>
                                <option value="2">খারাপ</option>
                                <option value="1">খুবই খারাপ</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="flex justify-end space-x-3">
                        <button type="button" onclick="this.closest('.fixed').remove()" class="px-4 py-2 text-gray-500 hover:text-gray-700">
                            বাতিল
                        </button>
                        <button type="submit" class="px-6 py-2 bg-yellow-600 text-white rounded hover:bg-yellow-700">
                            রেটিং জমা দিন
                        </button>
                    </div>
                </form>
            </div>
        `;
        
        document.body.appendChild(modal);
        
        // Handle form submission
        const form = document.getElementById('ratingForm');
        form.addEventListener('submit', (e) => {
            e.preventDefault();
            this.submitRating(deliveryId, form);
        });
    },
    
    // Set rating stars
    setRating(rating) {
        document.getElementById('selectedRating').value = rating;
        
        // Update star display
        document.querySelectorAll('.star-btn').forEach((btn, index) => {
            if (index < rating) {
                btn.classList.remove('text-gray-300');
                btn.classList.add('text-yellow-400');
            } else {
                btn.classList.remove('text-yellow-400');
                btn.classList.add('text-gray-300');
            }
        });
    },
    
    // Submit rating
    async submitRating(deliveryId, form) {
        const formData = new FormData(form);
        const ratingData = {
            delivery_id: deliveryId,
            overall_rating: parseInt(formData.get('rating')),
            timeliness_rating: parseInt(formData.get('timeliness_rating')),
            condition_rating: parseInt(formData.get('condition_rating')),
            comment: formData.get('comment') || null
        };
        
        try {
            const response = await this.apiRequest(`/transport/${deliveryId}/rate`, 'POST', ratingData);
            
            if (response.ok) {
                this.showSuccess('রেটিং সফলভাবে জমা দেওয়া হয়েছে!');
                form.closest('.fixed').remove();
                this.loadDeliveryHistory(); // Refresh history
            } else {
                const error = await response.json();
                this.showError(error.error || 'রেটিং জমা দিতে সমস্যা হয়েছে');
            }
            
        } catch (error) {
            console.error('Submit rating error:', error);
            this.showError('নেটওয়ার্ক সমস্যা হয়েছে');
        }
    },
    
    // Contact driver
    contactDriver(deliveryId) {
        // Implementation would show driver contact options
        this.showInfo('চালকের যোগাযোগের তথ্য লোড হচ্ছে...');
    },
    
    // Call driver
    callDriver(phoneNumber) {
        if (phoneNumber) {
            window.open(`tel:${phoneNumber}`, '_self');
        } else {
            this.showError('চালকের ফোন নম্বর পাওয়া যায়নি');
        }
    },
    
    // Report issue
    reportIssue(deliveryId) {
        // Implementation would show issue reporting form
        this.showInfo('সমস্যা রিপোর্ট ফর্ম খোলা হচ্ছে...');
    },
    
    // Load delivery history
    async loadDeliveryHistory() {
        const tableBody = document.getElementById('deliveryHistoryTableBody');
        if (!tableBody) return;
        
        try {
            const response = await this.apiRequest('/transport?history=true&buyer=true');
            
            if (response.ok) {
                const data = await response.json();
                this.displayDeliveryHistory(data.transports);
            } else {
                throw new Error('Failed to load delivery history');
            }
            
        } catch (error) {
            console.error('Load delivery history error:', error);
            tableBody.innerHTML = '<tr><td colspan="6" class="text-center py-4 text-red-500">ডেলিভারি ইতিহাস লোড করতে সমস্যা হয়েছে</td></tr>';
        }
    },
    
    // Display delivery history
    displayDeliveryHistory(deliveries) {
        const tableBody = document.getElementById('deliveryHistoryTableBody');
        if (!tableBody) return;
        
        if (!deliveries || deliveries.length === 0) {
            tableBody.innerHTML = '<tr><td colspan="6" class="text-center py-4 text-gray-500">কোনো ডেলিভারি ইতিহাস নেই</td></tr>';
            return;
        }
        
        tableBody.innerHTML = '';
        
        deliveries.forEach(delivery => {
            const row = document.createElement('tr');
            row.className = 'hover:bg-gray-50';
            
            row.innerHTML = `
                <td class="px-6 py-4 whitespace-nowrap">
                    <div class="text-sm font-medium text-gray-900">#${delivery.order_id}</div>
                    <div class="text-sm text-gray-500">${delivery.tracking_number || 'N/A'}</div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                    <div class="text-sm text-gray-900">${delivery.product_name}</div>
                    <div class="text-sm text-gray-500">${delivery.quantity} ${delivery.unit}</div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                    ${delivery.delivered_at ? new Date(delivery.delivered_at).toLocaleDateString('bn-BD') : 'N/A'}
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full ${this.getDeliveryStatusColor(delivery.status)}">
                        ${this.translateDeliveryStatus(delivery.status)}
                    </span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                    ${delivery.rating ? `
                        <div class="flex items-center">
                            <div class="text-yellow-400">
                                ${'★'.repeat(delivery.rating)}${'☆'.repeat(5 - delivery.rating)}
                            </div>
                            <span class="ml-1 text-sm text-gray-600">(${delivery.rating})</span>
                        </div>
                    ` : '<span class="text-gray-400 text-sm">রেটিং নেই</span>'}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                    ${delivery.status === 'delivered' && !delivery.rating ? `
                        <button onclick="BuyerDeliveryTracking.rateDelivery(${delivery.id})" 
                                class="text-yellow-600 hover:text-yellow-900">রেটিং দিন</button>
                    ` : ''}
                    <button onclick="BuyerDeliveryTracking.viewDeliveryDetails(${delivery.id})" 
                            class="text-blue-600 hover:text-blue-900 ml-2">বিস্তারিত</button>
                </td>
            `;
            
            tableBody.appendChild(row);
        });
    },
    
    // View delivery details
    async viewDeliveryDetails(deliveryId) {
        try {
            const response = await this.apiRequest(`/transport/${deliveryId}`);
            
            if (response.ok) {
                const delivery = await response.json();
                this.showDeliveryDetailsModal(delivery);
            } else {
                throw new Error('Failed to load delivery details');
            }
            
        } catch (error) {
            console.error('View delivery details error:', error);
            this.showError('ডেলিভারি বিস্তারিত তথ্য লোড করতে সমস্যা হয়েছে');
        }
    },
    
    // Show delivery details modal
    showDeliveryDetailsModal(delivery) {
        // Implementation would show detailed delivery information
        this.showInfo('ডেলিভারি বিস্তারিত তথ্য দেখানো হচ্ছে...');
    },
    
    // Start tracking for active deliveries
    startTrackingForActiveDeliveries(deliveries) {
        // Stop existing tracking
        this.activeTracking.forEach((interval, deliveryId) => {
            clearInterval(interval);
        });
        this.activeTracking.clear();
        
        // Start tracking for active deliveries
        deliveries.forEach(delivery => {
            if (['assigned', 'pickup_pending', 'picked_up', 'in_transit'].includes(delivery.status)) {
                const interval = setInterval(() => {
                    this.updateDeliveryTracking(delivery.id);
                }, this.trackingInterval);
                
                this.activeTracking.set(delivery.id, interval);
            }
        });
    },
    
    // Update delivery tracking
    async updateDeliveryTracking(deliveryId) {
        try {
            const response = await this.apiRequest(`/transport/${deliveryId}/tracking`);
            
            if (response.ok) {
                const data = await response.json();
                // Update UI with new tracking data
                this.updateDeliveryCardTracking(deliveryId, data);
            }
            
        } catch (error) {
            console.error('Update delivery tracking error:', error);
        }
    },
    
    // Update delivery card tracking info
    updateDeliveryCardTracking(deliveryId, trackingData) {
        const card = document.querySelector(`[data-delivery-id="${deliveryId}"]`);
        if (card && trackingData.current_location) {
            // Update speed and location info
            // Implementation would update specific elements
        }
    },
    
    // Utility methods
    
    getDeliveryStatusColor(status) {
        const colors = {
            'requested': 'bg-yellow-100 text-yellow-800',
            'assigned': 'bg-blue-100 text-blue-800',
            'pickup_pending': 'bg-orange-100 text-orange-800',
            'picked_up': 'bg-purple-100 text-purple-800',
            'in_transit': 'bg-indigo-100 text-indigo-800',
            'delivered': 'bg-green-100 text-green-800',
            'cancelled': 'bg-red-100 text-red-800',
            'delayed': 'bg-red-100 text-red-800'
        };
        return colors[status] || 'bg-gray-100 text-gray-800';
    },
    
    translateDeliveryStatus(status) {
        const translations = {
            'requested': 'অনুরোধকৃত',
            'assigned': 'বরাদ্দকৃত',
            'pickup_pending': 'পিকআপ অপেক্ষমাণ',
            'picked_up': 'পিকআপ সম্পন্ন',
            'in_transit': 'পরিবহনে',
            'delivered': 'ডেলিভার সম্পন্ন',
            'cancelled': 'বাতিল',
            'delayed': 'বিলম্বিত'
        };
        return translations[status] || status;
    },
    
    calculateProgress(status) {
        const progressMap = {
            'requested': 10,
            'assigned': 25,
            'pickup_pending': 40,
            'picked_up': 60,
            'in_transit': 80,
            'delivered': 100,
            'cancelled': 0
        };
        return progressMap[status] || 0;
    },
    
    isStepCompleted(step, currentStatus) {
        const statusOrder = ['assigned', 'pickup_pending', 'picked_up', 'in_transit', 'delivered'];
        const stepIndex = statusOrder.indexOf(step);
        const currentIndex = statusOrder.indexOf(currentStatus);
        return currentIndex >= stepIndex;
    },
    
    getCurrentUser() {
        const userStr = localStorage.getItem('user');
        return userStr ? JSON.parse(userStr) : null;
    },
    
    async apiRequest(endpoint, method = 'GET', data = null) {
        const token = localStorage.getItem('authToken');
        const headers = {
            'Content-Type': 'application/json'
        };
        
        if (token) {
            headers['Authorization'] = `Bearer ${token}`;
        }
        
        const config = {
            method,
            headers
        };
        
        if (data && method !== 'GET') {
            config.body = JSON.stringify(data);
        }
        
        return fetch(this.apiBase + endpoint, config);
    },
    
    showSuccess(message) {
        this.showNotification(message, 'success');
    },
    
    showError(message) {
        this.showNotification(message, 'error');
    },
    
    showInfo(message) {
        this.showNotification(message, 'info');
    },
    
    showNotification(message, type = 'info') {
        const notification = document.createElement('div');
        notification.className = `fixed top-4 right-4 p-4 rounded-lg shadow-lg z-50 ${
            type === 'success' ? 'bg-green-500 text-white' :
            type === 'error' ? 'bg-red-500 text-white' :
            'bg-blue-500 text-white'
        }`;
        notification.innerHTML = `
            <div class="flex items-center">
                <span>${message}</span>
                <button onclick="this.parentElement.parentElement.remove()" class="ml-4 text-white hover:text-gray-200">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        `;
        
        document.body.appendChild(notification);
        
        // Auto-remove after 5 seconds
        setTimeout(() => {
            if (notification.parentNode) {
                notification.remove();
            }
        }, 5000);
    }
};

// Auto-initialize when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => BuyerDeliveryTracking.init());
} else {
    BuyerDeliveryTracking.init();
}

// Export for use in other modules
if (typeof module !== 'undefined' && module.exports) {
    module.exports = BuyerDeliveryTracking;
}
