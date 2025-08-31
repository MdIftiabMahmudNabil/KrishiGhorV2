/**
 * Order Management Module
 * Handles order placement, status tracking, and payment processing
 */

const OrderManagement = {
    // API base URL
    apiBase: '/api',
    
    // Initialize order management
    init() {
        this.initOrderPlacement();
        this.initOrderListing();
        this.initPaymentMethods();
        this.loadOrders();
    },
    
    // Initialize order placement form
    initOrderPlacement() {
        const form = document.getElementById('orderForm');
        if (form) {
            form.addEventListener('submit', (e) => {
                e.preventDefault();
                this.placeOrder(form);
            });
        }
        
        // Payment method change handler
        const paymentMethodSelect = document.getElementById('paymentMethod');
        if (paymentMethodSelect) {
            paymentMethodSelect.addEventListener('change', (e) => {
                this.handlePaymentMethodChange(e.target.value);
            });
        }
    },
    
    // Initialize order listing and filters
    initOrderListing() {
        const statusFilter = document.getElementById('orderStatusFilter');
        if (statusFilter) {
            statusFilter.addEventListener('change', () => {
                this.loadOrders();
            });
        }
        
        const refreshBtn = document.getElementById('refreshOrdersBtn');
        if (refreshBtn) {
            refreshBtn.addEventListener('click', () => {
                this.loadOrders();
            });
        }
    },
    
    // Initialize payment methods
    async initPaymentMethods() {
        try {
            // In a real implementation, this would load available payment methods from API
            const paymentMethods = [
                { code: 'bkash', name: 'bKash', type: 'mobile_banking' },
                { code: 'nagad', name: 'Nagad', type: 'mobile_banking' },
                { code: 'rocket', name: 'Rocket', type: 'mobile_banking' },
                { code: 'card', name: 'Card Payment', type: 'credit_card' },
                { code: 'bank_transfer', name: 'Bank Transfer', type: 'bank_transfer' },
                { code: 'cod', name: 'Cash on Delivery', type: 'cash_on_delivery' }
            ];
            
            this.populatePaymentMethods(paymentMethods);
            
        } catch (error) {
            console.error('Error loading payment methods:', error);
        }
    },
    
    // Populate payment method dropdown
    populatePaymentMethods(methods) {
        const select = document.getElementById('paymentMethod');
        if (!select) return;
        
        select.innerHTML = '<option value="">পেমেন্ট পদ্ধতি নির্বাচন করুন</option>';
        
        methods.forEach(method => {
            const option = document.createElement('option');
            option.value = method.code;
            option.textContent = method.name;
            select.appendChild(option);
        });
    },
    
    // Handle payment method change
    handlePaymentMethodChange(method) {
        const paymentInfo = document.getElementById('paymentMethodInfo');
        if (!paymentInfo) return;
        
        let infoHTML = '';
        
        switch (method) {
            case 'bkash':
                infoHTML = '<div class="text-sm text-blue-600"><i class="fas fa-mobile-alt mr-2"></i>আপনার bKash অ্যাকাউন্ট ব্যবহার করে পেমেন্ট করুন</div>';
                break;
            case 'nagad':
                infoHTML = '<div class="text-sm text-orange-600"><i class="fas fa-mobile-alt mr-2"></i>আপনার Nagad অ্যাকাউন্ট ব্যবহার করে পেমেন্ট করুন</div>';
                break;
            case 'rocket':
                infoHTML = '<div class="text-sm text-purple-600"><i class="fas fa-mobile-alt mr-2"></i>আপনার Rocket অ্যাকাউন্ট ব্যবহার করে পেমেন্ট করুন</div>';
                break;
            case 'card':
                infoHTML = '<div class="text-sm text-green-600"><i class="fas fa-credit-card mr-2"></i>ক্রেডিট/ডেবিট কার্ড ব্যবহার করে পেমেন্ট করুন</div>';
                break;
            case 'bank_transfer':
                infoHTML = '<div class="text-sm text-gray-600"><i class="fas fa-university mr-2"></i>ব্যাংক ট্রান্সফার - ব্যাংক বিবরণ প্রদান করা হবে</div>';
                break;
            case 'cod':
                infoHTML = '<div class="text-sm text-yellow-600"><i class="fas fa-hand-holding-usd mr-2"></i>ডেলিভারির সময় নগদ পেমেন্ট করুন</div>';
                break;
        }
        
        paymentInfo.innerHTML = infoHTML;
    },
    
    // Place an order
    async placeOrder(form) {
        const submitBtn = form.querySelector('button[type="submit"]');
        const spinner = document.getElementById('orderSpinner');
        
        // Show loading state
        if (submitBtn) submitBtn.disabled = true;
        if (spinner) spinner.classList.remove('hidden');
        
        try {
            const formData = new FormData(form);
            const orderData = {
                product_id: formData.get('product_id'),
                quantity: parseInt(formData.get('quantity')),
                delivery_address: formData.get('delivery_address'),
                delivery_date: formData.get('delivery_date'),
                payment_method: formData.get('payment_method'),
                notes: formData.get('notes')
            };
            
            const response = await this.apiRequest('/orders', 'POST', orderData);
            
            if (response.ok) {
                const result = await response.json();
                
                this.showSuccess('অর্ডার সফলভাবে প্লেস করা হয়েছে!');
                
                // Show order details
                this.showOrderResult(result);
                
                // Reset form
                form.reset();
                
                // Reload orders if on the same page
                if (document.getElementById('ordersContainer')) {
                    this.loadOrders();
                }
                
            } else {
                const error = await response.json();
                this.showError(error.error || 'অর্ডার প্লেস করতে সমস্যা হয়েছে');
            }
            
        } catch (error) {
            console.error('Place order error:', error);
            this.showError('নেটওয়ার্ক সমস্যা হয়েছে');
        } finally {
            if (submitBtn) submitBtn.disabled = false;
            if (spinner) spinner.classList.add('hidden');
        }
    },
    
    // Show order placement result
    showOrderResult(result) {
        const modal = document.createElement('div');
        modal.className = 'fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50';
        modal.innerHTML = `
            <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
                <div class="mt-3">
                    <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-green-100">
                        <i class="fas fa-check text-green-600 text-xl"></i>
                    </div>
                    <div class="mt-3 text-center">
                        <h3 class="text-lg font-medium text-gray-900">অর্ডার সফল!</h3>
                        <div class="mt-2 px-7 py-3">
                            <p class="text-sm text-gray-500">অর্ডার নম্বর: #${result.order.id}</p>
                            <p class="text-sm text-gray-500">মোট পরিমাণ: ৳${result.order.total_amount}</p>
                            ${result.payment && result.payment.payment_url ? 
                                `<div class="mt-4">
                                    <a href="${result.payment.payment_url}" target="_blank" 
                                       class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
                                        পেমেন্ট করুন
                                    </a>
                                </div>` : ''
                            }
                            ${result.anomaly_detected ? 
                                '<div class="mt-2 text-xs text-yellow-600">⚠️ অস্বাভাবিক অর্ডার প্যাটার্ন সনাক্ত হয়েছে</div>' : ''
                            }
                        </div>
                        <div class="items-center px-4 py-3">
                            <button class="px-4 py-2 bg-gray-600 text-white text-base font-medium rounded-md hover:bg-gray-700"
                                    onclick="this.closest('.fixed').remove()">
                                বন্ধ করুন
                            </button>
                        </div>
                    </div>
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
    
    // Load orders
    async loadOrders() {
        const container = document.getElementById('ordersContainer');
        if (!container) return;
        
        const statusFilter = document.getElementById('orderStatusFilter');
        const status = statusFilter ? statusFilter.value : '';
        
        try {
            const params = new URLSearchParams();
            if (status) params.append('status', status);
            
            const response = await this.apiRequest(`/orders?${params}`);
            
            if (response.ok) {
                const data = await response.json();
                this.displayOrders(data.orders);
            } else {
                throw new Error('Failed to load orders');
            }
            
        } catch (error) {
            console.error('Load orders error:', error);
            container.innerHTML = '<p class="text-red-500">অর্ডার লোড করতে সমস্যা হয়েছে</p>';
        }
    },
    
    // Display orders
    displayOrders(orders) {
        const container = document.getElementById('ordersContainer');
        if (!container) return;
        
        if (!orders || orders.length === 0) {
            container.innerHTML = '<p class="text-gray-500 text-center py-8">কোনো অর্ডার নেই</p>';
            return;
        }
        
        container.innerHTML = '';
        
        orders.forEach(order => {
            const orderCard = this.createOrderCard(order);
            container.appendChild(orderCard);
        });
    },
    
    // Create order card
    createOrderCard(order) {
        const card = document.createElement('div');
        card.className = 'bg-white rounded-lg shadow p-6 hover:shadow-lg transition-shadow';
        
        const statusColor = this.getOrderStatusColor(order.order_status);
        const paymentStatusColor = this.getPaymentStatusColor(order.payment_status);
        
        card.innerHTML = `
            <div class="flex justify-between items-start mb-4">
                <div>
                    <h3 class="text-lg font-semibold text-gray-900">অর্ডার #${order.id}</h3>
                    <p class="text-sm text-gray-600">${order.product_name}</p>
                </div>
                <div class="text-right">
                    <span class="px-2 py-1 text-xs rounded ${statusColor}">${this.translateOrderStatus(order.order_status)}</span>
                    <p class="text-sm text-gray-500 mt-1">${new Date(order.created_at).toLocaleDateString('bn-BD')}</p>
                </div>
            </div>
            
            <div class="grid grid-cols-2 gap-4 mb-4">
                <div>
                    <p class="text-sm text-gray-600">পরিমাণ</p>
                    <p class="font-semibold">${order.quantity} ${order.unit}</p>
                </div>
                <div>
                    <p class="text-sm text-gray-600">মোট পরিমাণ</p>
                    <p class="font-semibold text-green-600">৳${order.total_amount}</p>
                </div>
                <div>
                    <p class="text-sm text-gray-600">পেমেন্ট পদ্ধতি</p>
                    <p class="font-semibold">${this.translatePaymentMethod(order.payment_method)}</p>
                </div>
                <div>
                    <p class="text-sm text-gray-600">পেমেন্ট স্ট্যাটাস</p>
                    <span class="px-2 py-1 text-xs rounded ${paymentStatusColor}">${this.translatePaymentStatus(order.payment_status)}</span>
                </div>
            </div>
            
            ${order.delivery_address ? `
                <div class="mb-4">
                    <p class="text-sm text-gray-600">ডেলিভারি ঠিকানা</p>
                    <p class="text-sm">${order.delivery_address}</p>
                </div>
            ` : ''}
            
            <div class="flex space-x-2">
                <button onclick="OrderManagement.viewOrderDetails(${order.id})" 
                        class="flex-1 bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
                    বিস্তারিত
                </button>
                
                ${this.getOrderActions(order)}
            </div>
        `;
        
        return card;
    },
    
    // Get order actions based on role and status
    getOrderActions(order) {
        const user = this.getCurrentUser();
        if (!user) return '';
        
        let actions = '';
        
        if (user.role === 'farmer' && order.order_status === 'pending') {
            actions += `
                <button onclick="OrderManagement.respondToOrder(${order.id}, 'accept')" 
                        class="flex-1 bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700">
                    গ্রহণ
                </button>
                <button onclick="OrderManagement.respondToOrder(${order.id}, 'reject')" 
                        class="flex-1 bg-red-600 text-white px-4 py-2 rounded hover:bg-red-700">
                    প্রত্যাখ্যান
                </button>
            `;
        } else if (user.role === 'buyer' && order.payment_status === 'pending' && order.payment_method !== 'cod') {
            actions += `
                <button onclick="OrderManagement.processPayment(${order.id})" 
                        class="flex-1 bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700">
                    পেমেন্ট করুন
                </button>
            `;
        }
        
        if (order.order_status === 'pending' || order.order_status === 'confirmed') {
            actions += `
                <button onclick="OrderManagement.cancelOrder(${order.id})" 
                        class="flex-1 bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700">
                    বাতিল
                </button>
            `;
        }
        
        return actions;
    },
    
    // View order details
    async viewOrderDetails(orderId) {
        try {
            const response = await this.apiRequest(`/orders/${orderId}`);
            
            if (response.ok) {
                const data = await response.json();
                this.showOrderDetailsModal(data.order);
            } else {
                throw new Error('Failed to load order details');
            }
            
        } catch (error) {
            console.error('View order details error:', error);
            this.showError('অর্ডার বিস্তারিত লোড করতে সমস্যা হয়েছে');
        }
    },
    
    // Show order details modal
    showOrderDetailsModal(order) {
        const modal = document.createElement('div');
        modal.className = 'fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50';
        modal.innerHTML = `
            <div class="relative top-10 mx-auto p-5 border w-full max-w-4xl shadow-lg rounded-md bg-white">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-xl font-bold text-gray-900">অর্ডার #${order.id} - বিস্তারিত</h3>
                    <button onclick="this.closest('.fixed').remove()" class="text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Order Info -->
                    <div>
                        <h4 class="font-semibold mb-3">অর্ডার তথ্য</h4>
                        <div class="space-y-2 text-sm">
                            <div><span class="font-medium">পণ্য:</span> ${order.product_name}</div>
                            <div><span class="font-medium">পরিমাণ:</span> ${order.quantity} ${order.unit}</div>
                            <div><span class="font-medium">একক দাম:</span> ৳${order.unit_price}</div>
                            <div><span class="font-medium">মোট:</span> ৳${order.total_amount}</div>
                            <div><span class="font-medium">অর্ডার স্ট্যাটাস:</span> ${this.translateOrderStatus(order.order_status)}</div>
                            <div><span class="font-medium">পেমেন্ট স্ট্যাটাস:</span> ${this.translatePaymentStatus(order.payment_status)}</div>
                        </div>
                    </div>
                    
                    <!-- Customer Info -->
                    <div>
                        <h4 class="font-semibold mb-3">ক্রেতার তথ্য</h4>
                        <div class="space-y-2 text-sm">
                            <div><span class="font-medium">নাম:</span> ${order.buyer_first_name} ${order.buyer_last_name}</div>
                            <div><span class="font-medium">ইমেইল:</span> ${order.buyer_email}</div>
                            <div><span class="font-medium">ফোন:</span> ${order.buyer_phone}</div>
                            ${order.delivery_address ? `<div><span class="font-medium">ঠিকানা:</span> ${order.delivery_address}</div>` : ''}
                        </div>
                    </div>
                </div>
                
                <!-- Timeline -->
                ${order.timeline && order.timeline.length > 0 ? `
                    <div class="mt-6">
                        <h4 class="font-semibold mb-3">অর্ডার টাইমলাইন</h4>
                        <div class="space-y-3">
                            ${order.timeline.map(entry => `
                                <div class="flex items-center space-x-3">
                                    <div class="flex-shrink-0 w-3 h-3 bg-blue-500 rounded-full"></div>
                                    <div class="flex-1">
                                        <p class="text-sm font-medium">${this.translateOrderStatus(entry.status)}</p>
                                        <p class="text-xs text-gray-500">${new Date(entry.created_at).toLocaleString('bn-BD')}</p>
                                        ${entry.notes ? `<p class="text-xs text-gray-600">${entry.notes}</p>` : ''}
                                    </div>
                                </div>
                            `).join('')}
                        </div>
                    </div>
                ` : ''}
                
                <!-- Payment Info -->
                ${order.payment_info ? `
                    <div class="mt-6">
                        <h4 class="font-semibold mb-3">পেমেন্ট তথ্য</h4>
                        <div class="bg-gray-50 p-4 rounded">
                            <div class="grid grid-cols-2 gap-4 text-sm">
                                <div><span class="font-medium">পদ্ধতি:</span> ${this.translatePaymentMethod(order.payment_info.payment_method)}</div>
                                <div><span class="font-medium">স্ট্যাটাস:</span> ${this.translatePaymentStatus(order.payment_info.status)}</div>
                                ${order.payment_info.transaction_id ? `<div><span class="font-medium">ট্রানজেকশন ID:</span> ${order.payment_info.transaction_id}</div>` : ''}
                                ${order.payment_info.risk_score ? `<div><span class="font-medium">ঝুঁকি স্কোর:</span> ${(order.payment_info.risk_score * 100).toFixed(1)}%</div>` : ''}
                            </div>
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
    },
    
    // Respond to order (farmer accept/reject)
    async respondToOrder(orderId, action) {
        if (!confirm(`আপনি কি এই অর্ডার ${action === 'accept' ? 'গ্রহণ' : 'প্রত্যাখ্যান'} করতে চান?`)) {
            return;
        }
        
        try {
            const response = await this.apiRequest(`/orders/${orderId}/respond`, 'POST', { action });
            
            if (response.ok) {
                this.showSuccess(`অর্ডার ${action === 'accept' ? 'গ্রহণ' : 'প্রত্যাখ্যান'} করা হয়েছে`);
                this.loadOrders(); // Refresh orders
            } else {
                const error = await response.json();
                this.showError(error.error || 'সমস্যা হয়েছে');
            }
            
        } catch (error) {
            console.error('Respond to order error:', error);
            this.showError('নেটওয়ার্ক সমস্যা হয়েছে');
        }
    },
    
    // Process payment
    async processPayment(orderId) {
        try {
            // In a real implementation, this would open payment gateway
            const response = await this.apiRequest(`/orders/${orderId}/payment`, 'POST', {});
            
            if (response.ok) {
                const result = await response.json();
                
                if (result.payment && result.payment.payment_url) {
                    window.open(result.payment.payment_url, '_blank');
                } else {
                    this.showSuccess('পেমেন্ট প্রক্রিয়া শুরু হয়েছে');
                }
                
                this.loadOrders(); // Refresh orders
            } else {
                const error = await response.json();
                this.showError(error.error || 'পেমেন্ট সমস্যা হয়েছে');
            }
            
        } catch (error) {
            console.error('Process payment error:', error);
            this.showError('নেটওয়ার্ক সমস্যা হয়েছে');
        }
    },
    
    // Cancel order
    async cancelOrder(orderId) {
        const reason = prompt('অর্ডার বাতিলের কারণ লিখুন:');
        if (!reason) return;
        
        try {
            const response = await this.apiRequest(`/orders/${orderId}`, 'DELETE', { reason });
            
            if (response.ok) {
                this.showSuccess('অর্ডার বাতিল করা হয়েছে');
                this.loadOrders(); // Refresh orders
            } else {
                const error = await response.json();
                this.showError(error.error || 'অর্ডার বাতিল করতে সমস্যা হয়েছে');
            }
            
        } catch (error) {
            console.error('Cancel order error:', error);
            this.showError('নেটওয়ার্ক সমস্যা হয়েছে');
        }
    },
    
    // Utility methods
    
    getOrderStatusColor(status) {
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
    
    getPaymentStatusColor(status) {
        const colors = {
            'pending': 'bg-yellow-100 text-yellow-800',
            'processing': 'bg-blue-100 text-blue-800',
            'completed': 'bg-green-100 text-green-800',
            'failed': 'bg-red-100 text-red-800',
            'refunded': 'bg-gray-100 text-gray-800'
        };
        return colors[status] || 'bg-gray-100 text-gray-800';
    },
    
    translateOrderStatus(status) {
        const translations = {
            'pending': 'অপেক্ষমাণ',
            'confirmed': 'নিশ্চিত',
            'processing': 'প্রস্তুতি',
            'shipped': 'পাঠানো হয়েছে',
            'delivered': 'ডেলিভার হয়েছে',
            'cancelled': 'বাতিল',
            'created': 'তৈরি',
            'payment_completed': 'পেমেন্ট সম্পন্ন',
            'auto_cancelled': 'স্বয়ংক্রিয় বাতিল'
        };
        return translations[status] || status;
    },
    
    translatePaymentStatus(status) {
        const translations = {
            'pending': 'অপেক্ষমাণ',
            'processing': 'প্রক্রিয়াধীন',
            'completed': 'সম্পন্ন',
            'failed': 'ব্যর্থ',
            'refunded': 'ফেরত'
        };
        return translations[status] || status;
    },
    
    translatePaymentMethod(method) {
        const translations = {
            'bkash': 'bKash',
            'nagad': 'Nagad',
            'rocket': 'Rocket',
            'card': 'কার্ড',
            'bank_transfer': 'ব্যাংক ট্রান্সফার',
            'cod': 'ক্যাশ অন ডেলিভারি'
        };
        return translations[method] || method;
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
    document.addEventListener('DOMContentLoaded', () => OrderManagement.init());
} else {
    OrderManagement.init();
}

// Export for use in other modules
if (typeof module !== 'undefined' && module.exports) {
    module.exports = OrderManagement;
}
