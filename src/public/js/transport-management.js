/**
 * Transport Management Module
 * Handles transport requests, tracking, and delivery management
 */

const TransportManagement = {
    // API base URL
    apiBase: '/api',
    
    // Tracking update interval (30 seconds)
    trackingInterval: 30000,
    
    // Active tracking intervals
    activeTracking: new Map(),
    
    // Initialize transport management
    init() {
        this.initTransportRequests();
        this.initTrackingSystem();
        this.loadTransports();
        this.loadAnalytics();
    },
    
    // Initialize transport request functionality
    initTransportRequests() {
        const requestBtn = document.getElementById('requestTransportBtn');
        if (requestBtn) {
            requestBtn.addEventListener('click', () => {
                this.showTransportRequestModal();
            });
        }
        
        const statusFilter = document.getElementById('transportStatusFilter');
        if (statusFilter) {
            statusFilter.addEventListener('change', () => {
                this.loadTransports();
            });
        }
        
        const refreshBtn = document.getElementById('refreshTransportBtn');
        if (refreshBtn) {
            refreshBtn.addEventListener('click', () => {
                this.loadTransports();
            });
        }
    },
    
    // Initialize real-time tracking system
    initTrackingSystem() {
        // Start periodic tracking updates for active transports
        setInterval(() => {
            this.updateActiveTracking();
        }, this.trackingInterval);
    },
    
    // Show transport request modal
    showTransportRequestModal() {
        const modal = document.createElement('div');
        modal.className = 'fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50';
        modal.innerHTML = `
            <div class="relative top-10 mx-auto p-5 border w-full max-w-2xl shadow-lg rounded-md bg-white">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-xl font-bold text-gray-900">পরিবহন অনুরোধ</h3>
                    <button onclick="this.closest('.fixed').remove()" class="text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
                
                <form id="transportRequestForm">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">অর্ডার নির্বাচন করুন</label>
                            <select name="order_id" required class="w-full border border-gray-300 rounded px-3 py-2">
                                <option value="">অর্ডার নির্বাচন করুন</option>
                                <!-- Orders will be loaded here -->
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">পরিবহনের ধরন</label>
                            <select name="transport_type" required class="w-full border border-gray-300 rounded px-3 py-2">
                                <option value="">ধরন নির্বাচন করুন</option>
                                <option value="truck">ট্রাক</option>
                                <option value="van">ভ্যান</option>
                                <option value="pickup">পিকআপ</option>
                                <option value="motorbike">মোটরবাইক</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">পিকআপ ঠিকানা</label>
                        <textarea name="pickup_address" required rows="2" class="w-full border border-gray-300 rounded px-3 py-2" placeholder="বিস্তারিত পিকআপ ঠিকানা লিখুন"></textarea>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">পছন্দের পিকআপ তারিখ</label>
                            <input type="datetime-local" name="preferred_date" required class="w-full border border-gray-300 rounded px-3 py-2">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">ডেলিভারি তারিখ (ঐচ্ছিক)</label>
                            <input type="datetime-local" name="delivery_date" class="w-full border border-gray-300 rounded px-3 py-2">
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">বিশেষ নির্দেশনা</label>
                        <textarea name="notes" rows="3" class="w-full border border-gray-300 rounded px-3 py-2" placeholder="যেকোনো বিশেষ নির্দেশনা বা প্রয়োজনীয়তা"></textarea>
                    </div>
                    
                    <div class="flex justify-end space-x-3">
                        <button type="button" onclick="this.closest('.fixed').remove()" class="px-4 py-2 text-gray-500 hover:text-gray-700">
                            বাতিল
                        </button>
                        <button type="submit" class="px-6 py-2 bg-green-600 text-white rounded hover:bg-green-700">
                            <span class="submit-text">অনুরোধ পাঠান</span>
                            <i class="fas fa-spinner fa-spin hidden loading-icon"></i>
                        </button>
                    </div>
                </form>
            </div>
        `;
        
        document.body.appendChild(modal);
        
        // Load available orders
        this.loadAvailableOrders();
        
        // Handle form submission
        const form = document.getElementById('transportRequestForm');
        form.addEventListener('submit', (e) => {
            e.preventDefault();
            this.submitTransportRequest(form);
        });
    },
    
    // Load available orders for transport request
    async loadAvailableOrders() {
        try {
            const response = await this.apiRequest('/orders?status=confirmed');
            
            if (response.ok) {
                const data = await response.json();
                const select = document.querySelector('select[name="order_id"]');
                
                if (select) {
                    select.innerHTML = '<option value="">অর্ডার নির্বাচন করুন</option>';
                    
                    data.orders.forEach(order => {
                        const option = document.createElement('option');
                        option.value = order.id;
                        option.textContent = `অর্ডার #${order.id} - ${order.product_name} (${order.quantity} ${order.unit})`;
                        select.appendChild(option);
                    });
                }
            }
        } catch (error) {
            console.error('Load available orders error:', error);
        }
    },
    
    // Submit transport request
    async submitTransportRequest(form) {
        const submitBtn = form.querySelector('button[type="submit"]');
        const submitText = submitBtn.querySelector('.submit-text');
        const loadingIcon = submitBtn.querySelector('.loading-icon');
        
        // Show loading state
        submitBtn.disabled = true;
        submitText.textContent = 'প্রক্রিয়াকরণ...';
        loadingIcon.classList.remove('hidden');
        
        try {
            const formData = new FormData(form);
            const requestData = {
                order_id: parseInt(formData.get('order_id')),
                transport_type: formData.get('transport_type'),
                pickup_address: formData.get('pickup_address'),
                preferred_date: formData.get('preferred_date'),
                delivery_date: formData.get('delivery_date') || null,
                notes: formData.get('notes') || null
            };
            
            const response = await this.apiRequest('/transport/request', 'POST', requestData);
            
            if (response.ok) {
                const result = await response.json();
                
                this.showSuccess('পরিবহন অনুরোধ সফলভাবে পাঠানো হয়েছে!');
                
                // Close modal
                form.closest('.fixed').remove();
                
                // Reload transports
                this.loadTransports();
                
                // Show request result
                this.showTransportRequestResult(result);
                
            } else {
                const error = await response.json();
                this.showError(error.error || 'পরিবহন অনুরোধ পাঠাতে সমস্যা হয়েছে');
            }
            
        } catch (error) {
            console.error('Submit transport request error:', error);
            this.showError('নেটওয়ার্ক সমস্যা হয়েছে');
        } finally {
            submitBtn.disabled = false;
            submitText.textContent = 'অনুরোধ পাঠান';
            loadingIcon.classList.add('hidden');
        }
    },
    
    // Show transport request result
    showTransportRequestResult(result) {
        const modal = document.createElement('div');
        modal.className = 'fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50';
        modal.innerHTML = `
            <div class="relative top-20 mx-auto p-5 border w-full max-w-lg shadow-lg rounded-md bg-white">
                <div class="text-center">
                    <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-green-100 mb-4">
                        <i class="fas fa-truck text-green-600 text-xl"></i>
                    </div>
                    <h3 class="text-lg font-medium text-gray-900 mb-4">পরিবহন অনুরোধ সফল!</h3>
                    
                    <div class="text-left bg-gray-50 rounded-lg p-4 mb-4">
                        <div class="grid grid-cols-2 gap-2 text-sm">
                            <div><strong>পরিবহন ID:</strong></div>
                            <div>${result.transport.id}</div>
                            <div><strong>ট্র্যাকিং নম্বর:</strong></div>
                            <div>${result.transport.tracking_number || 'N/A'}</div>
                            <div><strong>আনুমানিক সময়:</strong></div>
                            <div>${result.eta_prediction?.estimated_duration_minutes || 'N/A'} মিনিট</div>
                            <div><strong>ঝুঁকি স্তর:</strong></div>
                            <div class="capitalize">${result.risk_assessment?.risk_level || 'N/A'}</div>
                        </div>
                    </div>
                    
                    ${result.risk_assessment?.risk_level === 'high' ? `
                        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-3 mb-4">
                            <div class="flex">
                                <i class="fas fa-exclamation-triangle text-yellow-400 mr-2"></i>
                                <div class="text-sm text-yellow-800">
                                    <strong>উচ্চ ঝুঁকি সনাক্ত:</strong> এই পণ্যের জন্য বিশেষ যত্ন প্রয়োজন
                                </div>
                            </div>
                        </div>
                    ` : ''}
                    
                    <button onclick="this.closest('.fixed').remove()" 
                            class="px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700">
                        ঠিক আছে
                    </button>
                </div>
            </div>
        `;
        
        document.body.appendChild(modal);
        
        // Auto-remove after 10 seconds
        setTimeout(() => {
            if (modal.parentNode) {
                modal.remove();
            }
        }, 10000);
    },
    
    // Load transports
    async loadTransports() {
        const container = document.getElementById('transportContainer');
        if (!container) return;
        
        const statusFilter = document.getElementById('transportStatusFilter');
        const status = statusFilter ? statusFilter.value : '';
        
        try {
            const params = new URLSearchParams();
            if (status) params.append('status', status);
            
            const response = await this.apiRequest(`/transport?${params}`);
            
            if (response.ok) {
                const data = await response.json();
                this.displayTransports(data.transports);
            } else {
                throw new Error('Failed to load transports');
            }
            
        } catch (error) {
            console.error('Load transports error:', error);
            container.innerHTML = '<p class="text-red-500 text-center py-8">পরিবহন তথ্য লোড করতে সমস্যা হয়েছে</p>';
        }
    },
    
    // Display transports
    displayTransports(transports) {
        const container = document.getElementById('transportContainer');
        if (!container) return;
        
        if (!transports || transports.length === 0) {
            container.innerHTML = '<p class="text-gray-500 text-center py-8">কোনো পরিবহন তথ্য নেই</p>';
            return;
        }
        
        container.innerHTML = '';
        
        transports.forEach(transport => {
            const transportCard = this.createTransportCard(transport);
            container.appendChild(transportCard);
        });
        
        // Start tracking for active transports
        this.startTrackingForActiveTransports(transports);
    },
    
    // Create transport card
    createTransportCard(transport) {
        const card = document.createElement('div');
        card.className = 'bg-white rounded-lg shadow hover:shadow-lg transition-shadow border-l-4 border-blue-500';
        
        const statusColor = this.getTransportStatusColor(transport.status);
        const riskColor = this.getRiskColor(transport.risk_level);
        
        card.innerHTML = `
            <div class="p-6">
                <div class="flex justify-between items-start mb-4">
                    <div>
                        <div class="flex items-center space-x-2 mb-2">
                            <h3 class="text-lg font-semibold text-gray-900">পরিবহন #${transport.id}</h3>
                            <span class="px-2 py-1 text-xs rounded ${statusColor}">${this.translateTransportStatus(transport.status)}</span>
                        </div>
                        <p class="text-sm text-gray-600">ট্র্যাকিং: ${transport.tracking_number || 'N/A'}</p>
                    </div>
                    <div class="text-right">
                        <div class="text-sm text-gray-500">${new Date(transport.created_at).toLocaleDateString('bn-BD')}</div>
                        ${transport.risk_level ? `<span class="px-2 py-1 text-xs rounded mt-1 inline-block ${riskColor}">ঝুঁকি: ${transport.risk_level}</span>` : ''}
                    </div>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                    <div>
                        <p class="text-sm text-gray-600">পরিবহনের ধরন</p>
                        <p class="font-semibold">${this.translateTransportType(transport.transport_type)}</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600">আনুমানিক ETA</p>
                        <p class="font-semibold">${transport.eta ? new Date(transport.eta).toLocaleString('bn-BD') : 'N/A'}</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600">বর্তমান গতি</p>
                        <p class="font-semibold">${transport.current_speed || 0} km/h</p>
                    </div>
                </div>
                
                ${transport.current_location ? `
                    <div class="mb-4">
                        <p class="text-sm text-gray-600">বর্তমান অবস্থান</p>
                        <p class="text-sm">${transport.current_location.latitude?.toFixed(4)}, ${transport.current_location.longitude?.toFixed(4)}</p>
                        <p class="text-xs text-gray-500">সর্বশেষ আপডেট: ${new Date(transport.current_location.updated_at).toLocaleString('bn-BD')}</p>
                    </div>
                ` : ''}
                
                <div class="flex space-x-2">
                    <button onclick="TransportManagement.viewTrackingDetails(${transport.id})" 
                            class="flex-1 bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
                        <i class="fas fa-map-marker-alt mr-2"></i>
                        ট্র্যাকিং দেখুন
                    </button>
                    
                    ${this.getTransportActions(transport)}
                </div>
                
                ${transport.alerts && transport.alerts.length > 0 ? `
                    <div class="mt-4 bg-yellow-50 border border-yellow-200 rounded-lg p-3">
                        <div class="flex items-center">
                            <i class="fas fa-exclamation-triangle text-yellow-500 mr-2"></i>
                            <span class="text-sm text-yellow-800">${transport.alerts.length} টি সতর্কতা</span>
                        </div>
                    </div>
                ` : ''}
            </div>
        `;
        
        return card;
    },
    
    // Get transport actions based on status
    getTransportActions(transport) {
        let actions = '';
        
        switch (transport.status) {
            case 'assigned':
            case 'pickup_pending':
                actions += `
                    <button onclick="TransportManagement.updateTransportStatus(${transport.id}, 'picked_up')" 
                            class="flex-1 bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700">
                        পিকআপ সম্পন্ন
                    </button>
                `;
                break;
                
            case 'picked_up':
                actions += `
                    <button onclick="TransportManagement.updateTransportStatus(${transport.id}, 'in_transit')" 
                            class="flex-1 bg-purple-600 text-white px-4 py-2 rounded hover:bg-purple-700">
                        যাত্রা শুরু
                    </button>
                `;
                break;
                
            case 'in_transit':
                actions += `
                    <button onclick="TransportManagement.updateTransportStatus(${transport.id}, 'delivered')" 
                            class="flex-1 bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700">
                        ডেলিভার সম্পন্ন
                    </button>
                `;
                break;
        }
        
        return actions;
    },
    
    // View tracking details
    async viewTrackingDetails(transportId) {
        try {
            const response = await this.apiRequest(`/transport/${transportId}/tracking`);
            
            if (response.ok) {
                const data = await response.json();
                this.showTrackingModal(data);
            } else {
                throw new Error('Failed to load tracking data');
            }
            
        } catch (error) {
            console.error('View tracking details error:', error);
            this.showError('ট্র্যাকিং তথ্য লোড করতে সমস্যা হয়েছে');
        }
    },
    
    // Show tracking modal
    showTrackingModal(trackingData) {
        const modal = document.createElement('div');
        modal.className = 'fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50';
        modal.innerHTML = `
            <div class="relative top-10 mx-auto p-5 border w-full max-w-4xl shadow-lg rounded-md bg-white">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-xl font-bold text-gray-900">রিয়েল-টাইম ট্র্যাকিং - পরিবহন #${trackingData.transport_id}</h3>
                    <button onclick="this.closest('.fixed').remove()" class="text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
                
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- Current Status -->
                    <div class="bg-gray-50 rounded-lg p-4">
                        <h4 class="font-semibold mb-3">বর্তমান অবস্থা</h4>
                        <div class="space-y-2 text-sm">
                            <div><span class="font-medium">স্ট্যাটাস:</span> ${this.translateTransportStatus(trackingData.current_status)}</div>
                            <div><span class="font-medium">গতি:</span> ${trackingData.current_speed || 0} km/h</div>
                            <div><span class="font-medium">দিক:</span> ${trackingData.bearing || 'N/A'}°</div>
                            ${trackingData.current_location ? `
                                <div><span class="font-medium">অবস্থান:</span> ${trackingData.current_location.latitude?.toFixed(4)}, ${trackingData.current_location.longitude?.toFixed(4)}</div>
                                <div><span class="font-medium">নির্ভুলতা:</span> ${trackingData.current_location.accuracy || 'N/A'}m</div>
                            ` : ''}
                            <div><span class="font-medium">সর্বশেষ আপডেট:</span> ${trackingData.last_updated ? new Date(trackingData.last_updated).toLocaleString('bn-BD') : 'N/A'}</div>
                        </div>
                    </div>
                    
                    <!-- Journey Statistics -->
                    <div class="bg-gray-50 rounded-lg p-4">
                        <h4 class="font-semibold mb-3">যাত্রার পরিসংখ্যান</h4>
                        <div class="space-y-2 text-sm">
                            <div><span class="font-medium">মোট দূরত্ব:</span> ${trackingData.journey_statistics?.total_distance || 0} km</div>
                            <div><span class="font-medium">গড় গতি:</span> ${trackingData.journey_statistics?.average_speed || 0} km/h</div>
                            <div><span class="font-medium">থামার সংখ্যা:</span> ${trackingData.journey_statistics?.stops_count || 0}</div>
                            <div><span class="font-medium">ট্র্যাকিং মান:</span> ${trackingData.tracking_quality || 'N/A'}</div>
                        </div>
                    </div>
                </div>
                
                <!-- Real-time Metrics -->
                ${trackingData.real_time_metrics ? `
                    <div class="mt-6 bg-blue-50 rounded-lg p-4">
                        <h4 class="font-semibold mb-3">রিয়েল-টাইম মেট্রিক্স</h4>
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                            <div>
                                <div class="font-medium">আপডেটেড ETA</div>
                                <div>${trackingData.real_time_metrics.eta_updated || 'N/A'}</div>
                            </div>
                            <div>
                                <div class="font-medium">অগ্রগতি</div>
                                <div>${trackingData.real_time_metrics.progress_percentage || 0}%</div>
                            </div>
                        </div>
                    </div>
                ` : ''}
                
                <!-- Active Alerts -->
                ${trackingData.active_alerts && trackingData.active_alerts.length > 0 ? `
                    <div class="mt-6 bg-red-50 rounded-lg p-4">
                        <h4 class="font-semibold mb-3 text-red-800">সক্রিয় সতর্কতা</h4>
                        <div class="space-y-2">
                            ${trackingData.active_alerts.map(alert => `
                                <div class="flex items-center text-sm">
                                    <i class="fas fa-exclamation-triangle text-red-500 mr-2"></i>
                                    <span>${alert.message}</span>
                                    <span class="ml-auto text-xs text-gray-500">${new Date(alert.created_at).toLocaleString('bn-BD')}</span>
                                </div>
                            `).join('')}
                        </div>
                    </div>
                ` : ''}
                
                <div class="mt-6 flex justify-end">
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
    
    // Update transport status
    async updateTransportStatus(transportId, newStatus) {
        if (!confirm(`আপনি কি পরিবহনের স্ট্যাটাস "${this.translateTransportStatus(newStatus)}" এ পরিবর্তন করতে চান?`)) {
            return;
        }
        
        try {
            const response = await this.apiRequest(`/transport/${transportId}/update`, 'POST', {
                status: newStatus,
                notes: `Status updated to ${newStatus} by farmer`
            });
            
            if (response.ok) {
                this.showSuccess('পরিবহনের স্ট্যাটাস আপডেট করা হয়েছে');
                this.loadTransports(); // Refresh transports
            } else {
                const error = await response.json();
                this.showError(error.error || 'স্ট্যাটাস আপডেট করতে সমস্যা হয়েছে');
            }
            
        } catch (error) {
            console.error('Update transport status error:', error);
            this.showError('নেটওয়ার্ক সমস্যা হয়েছে');
        }
    },
    
    // Start tracking for active transports
    startTrackingForActiveTransports(transports) {
        // Stop existing tracking
        this.activeTracking.forEach((interval, transportId) => {
            clearInterval(interval);
        });
        this.activeTracking.clear();
        
        // Start tracking for active transports
        transports.forEach(transport => {
            if (['assigned', 'pickup_pending', 'picked_up', 'in_transit'].includes(transport.status)) {
                const interval = setInterval(() => {
                    this.updateTransportTracking(transport.id);
                }, this.trackingInterval);
                
                this.activeTracking.set(transport.id, interval);
            }
        });
    },
    
    // Update transport tracking
    async updateTransportTracking(transportId) {
        try {
            const response = await this.apiRequest(`/transport/${transportId}/tracking`);
            
            if (response.ok) {
                const data = await response.json();
                // Update UI with new tracking data
                this.updateTransportCardTracking(transportId, data);
            }
            
        } catch (error) {
            console.error('Update transport tracking error:', error);
        }
    },
    
    // Update transport card tracking info
    updateTransportCardTracking(transportId, trackingData) {
        // Implementation would update specific elements in the transport card
        // This is a simplified version
        const card = document.querySelector(`[data-transport-id="${transportId}"]`);
        if (card && trackingData.current_location) {
            const speedElement = card.querySelector('.current-speed');
            if (speedElement) {
                speedElement.textContent = `${trackingData.current_speed || 0} km/h`;
            }
        }
    },
    
    // Update active tracking
    updateActiveTracking() {
        // This method is called periodically to refresh tracking data
        // Implementation would batch update all active transports
    },
    
    // Load analytics
    async loadAnalytics() {
        try {
            const response = await this.apiRequest('/transport/analytics');
            
            if (response.ok) {
                const data = await response.json();
                this.updateAnalyticsDisplay(data);
            }
            
        } catch (error) {
            console.error('Load analytics error:', error);
        }
    },
    
    // Update analytics display
    updateAnalyticsDisplay(analytics) {
        const elements = {
            totalTransports: document.getElementById('totalTransports'),
            onTimeDeliveries: document.getElementById('onTimeDeliveries'),
            avgDeliveryTime: document.getElementById('avgDeliveryTime'),
            customerSatisfaction: document.getElementById('customerSatisfaction')
        };
        
        if (elements.totalTransports) {
            elements.totalTransports.textContent = analytics.delivery_performance?.total_deliveries || 0;
        }
        
        if (elements.onTimeDeliveries) {
            elements.onTimeDeliveries.textContent = `${analytics.delivery_performance?.on_time_percentage?.toFixed(1) || 0}%`;
        }
        
        if (elements.avgDeliveryTime) {
            elements.avgDeliveryTime.textContent = `${analytics.delivery_performance?.avg_delivery_time?.toFixed(1) || 0}h`;
        }
        
        if (elements.customerSatisfaction) {
            elements.customerSatisfaction.textContent = analytics.delivery_performance?.avg_customer_satisfaction?.toFixed(1) || 'N/A';
        }
    },
    
    // Utility methods
    
    getTransportStatusColor(status) {
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
    
    getRiskColor(riskLevel) {
        const colors = {
            'low': 'bg-green-100 text-green-800',
            'medium': 'bg-yellow-100 text-yellow-800',
            'high': 'bg-red-100 text-red-800',
            'critical': 'bg-red-200 text-red-900'
        };
        return colors[riskLevel] || 'bg-gray-100 text-gray-800';
    },
    
    translateTransportStatus(status) {
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
    
    translateTransportType(type) {
        const translations = {
            'truck': 'ট্রাক',
            'van': 'ভ্যান',
            'pickup': 'পিকআপ',
            'motorbike': 'মোটরবাইক',
            'bicycle': 'সাইকেল'
        };
        return translations[type] || type;
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
    document.addEventListener('DOMContentLoaded', () => TransportManagement.init());
} else {
    TransportManagement.init();
}

// Export for use in other modules
if (typeof module !== 'undefined' && module.exports) {
    module.exports = TransportManagement;
}
