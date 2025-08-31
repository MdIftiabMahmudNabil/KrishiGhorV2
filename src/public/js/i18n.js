/**
 * Internationalization (i18n) Module
 * Handles Bengali ↔ English translations for KrishiGhor
 */

const I18n = {
    currentLanguage: 'bn',
    translations: {
        bn: {
            // Login page
            'login.subtitle': 'আপনার কৃষি সহযোগীতার ডিজিটাল প্ল্যাটফর্ম',
            'login.email': 'ইমেইল',
            'login.email_placeholder': 'আপনার ইমেইল ঠিকানা',
            'login.password': 'পাসওয়ার্ড',
            'login.password_placeholder': 'আপনার পাসওয়ার্ড',
            'login.remember': 'আমাকে মনে রাখুন',
            'login.forgot': 'পাসওয়ার্ড ভুলে গেছেন?',
            'login.submit': 'লগইন করুন',
            'login.no_account': 'কোনো অ্যাকাউন্ট নেই?',
            'login.register_link': 'এখানে নিবন্ধন করুন',
            
            // Register page
            'register.subtitle': 'নতুন অ্যাকাউন্ট তৈরি করুন',
            'register.role': 'আপনি কে?',
            'register.farmer': 'কৃষক',
            'register.farmer_desc': 'ফসল বিক্রয় ও বাজার তথ্য',
            'register.buyer': 'ক্রেতা',
            'register.buyer_desc': 'কৃষি পণ্য ক্রয়',
            'register.first_name': 'প্রথম নাম',
            'register.first_name_placeholder': 'আপনার প্রথম নাম',
            'register.last_name': 'শেষ নাম',
            'register.last_name_placeholder': 'আপনার শেষ নাম',
            'register.email': 'ইমেইল',
            'register.email_placeholder': 'আপনার ইমেইল ঠিকানা',
            'register.phone': 'মোবাইল নম্বর',
            'register.phone_placeholder': '+880 1XXX-XXXXXX',
            'register.region': 'অঞ্চল',
            'register.region_placeholder': 'অঞ্চল নির্বাচন করুন',
            'register.password': 'পাসওয়ার্ড',
            'register.password_placeholder': 'কমপক্ষে ৬ অক্ষরের পাসওয়ার্ড',
            'register.password_strength': 'পাসওয়ার্ড শক্তি:',
            'register.confirm_password': 'পাসওয়ার্ড নিশ্চিত করুন',
            'register.confirm_password_placeholder': 'পাসওয়ার্ড পুনরায় লিখুন',
            'register.agree': 'আমি',
            'register.terms': 'নিয়মাবলী ও শর্তাবলী',
            'register.agree_end': 'সম্মত',
            'register.submit': 'নিবন্ধন করুন',
            'register.have_account': 'ইতিমধ্যে অ্যাকাউন্ট আছে?',
            'register.login_link': 'এখানে লগইন করুন',
            
            // Features
            'features.farmers': 'কৃষক সম্প্রদায়',
            'features.market': 'বাজার বিশ্লেষণ',
            'features.ai': 'AI সহায়তা',
            
            // Dashboard
            'dashboard.welcome': 'স্বাগতম',
            'dashboard.logout': 'লগআউট',
            'dashboard.profile': 'প্রোফাইল',
            'dashboard.settings': 'সেটিংস',
            
            // Farmer Dashboard
            'farmer.my_products': 'আমার পণ্য',
            'farmer.add_product': 'নতুন পণ্য যোগ করুন',
            'farmer.orders': 'অর্ডার',
            'farmer.sales': 'বিক্রয়',
            'farmer.analytics': 'পরিসংখ্যান',
            'farmer.price_trends': 'দামের প্রবণতা',
            
            // Buyer Dashboard
            'buyer.browse_products': 'পণ্য ব্রাউজ করুন',
            'buyer.my_orders': 'আমার অর্ডার',
            'buyer.wishlist': 'পছন্দের তালিকা',
            'buyer.market_prices': 'বাজার দর',
            
            // Admin Dashboard
            'admin.users': 'ব্যবহারকারী',
            'admin.products': 'পণ্য',
            'admin.orders': 'অর্ডার',
            'admin.reports': 'রিপোর্ট',
            'admin.system': 'সিস্টেম',
            
            // Common
            'common.loading': 'লোড হচ্ছে...',
            'common.save': 'সংরক্ষণ করুন',
            'common.cancel': 'বাতিল',
            'common.delete': 'মুছে ফেলুন',
            'common.edit': 'সম্পাদনা',
            'common.view': 'দেখুন',
            'common.search': 'অনুসন্ধান',
            'common.filter': 'ফিল্টার',
            'common.sort': 'সাজান',
            'common.export': 'এক্সপোর্ট',
            'common.import': 'ইমপোর্ট',
            'common.print': 'প্রিন্ট',
            'common.refresh': 'রিফ্রেশ',
            'common.back': 'ফিরে যান',
            'common.next': 'পরবর্তী',
            'common.previous': 'পূর্ববর্তী',
            'common.yes': 'হ্যাঁ',
            'common.no': 'না',
            'common.ok': 'ঠিক আছে',
            'common.error': 'ত্রুটি',
            'common.success': 'সফল',
            'common.warning': 'সতর্কতা',
            'common.info': 'তথ্য',
            
            // Products
            'product.name': 'পণ্যের নাম',
            'product.category': 'শ্রেণী',
            'product.price': 'দাম',
            'product.quantity': 'পরিমাণ',
            'product.unit': 'একক',
            'product.location': 'অবস্থান',
            'product.quality': 'গুণমান',
            'product.organic': 'জৈব',
            'product.description': 'বিবরণ',
            'product.images': 'ছবি',
            'product.harvest_date': 'ফসল কাটার তারিখ',
            'product.expiry_date': 'মেয়াদ শেষের তারিখ',
            
            // Categories
            'category.rice': 'ধান',
            'category.wheat': 'গম',
            'category.potato': 'আলু',
            'category.onion': 'পেঁয়াজ',
            'category.tomato': 'টমেটো',
            'category.carrot': 'গাজর',
            'category.cabbage': 'বাঁধাকপি',
            'category.cauliflower': 'ফুলকপি',
            'category.beans': 'শিম',
            'category.peas': 'মটর',
            'category.spinach': 'পালং শাক',
            'category.lettuce': 'লেটুস',
            'category.cucumber': 'শসা',
            'category.pumpkin': 'কুমড়া',
            'category.eggplant': 'বেগুন',
            'category.okra': 'ঢেঁড়স',
            'category.chili': 'মরিচ',
            'category.ginger': 'আদা',
            'category.garlic': 'রসুন',
            'category.coriander': 'ধনিয়া',
            
            // Units
            'unit.kg': 'কেজি',
            'unit.g': 'গ্রাম',
            'unit.ton': 'টন',
            'unit.piece': 'পিস',
            'unit.dozen': 'ডজন',
            'unit.bundle': 'বান্ডিল',
            'unit.bag': 'বস্তা',
            'unit.maund': 'মণ',
            'unit.seer': 'সের',
            
            // Error messages
            'error.required_field': 'এই ক্ষেত্রটি আবশ্যক',
            'error.invalid_email': 'অবৈধ ইমেইল ঠিকানা',
            'error.password_mismatch': 'পাসওয়ার্ড মিলছে না',
            'error.weak_password': 'পাসওয়ার্ড খুব দুর্বল',
            'error.login_failed': 'লগইন ব্যর্থ হয়েছে',
            'error.registration_failed': 'নিবন্ধন ব্যর্থ হয়েছে',
            'error.network_error': 'নেটওয়ার্ক ত্রুটি',
            'error.server_error': 'সার্ভার ত্রুটি',
            'error.unauthorized': 'অনুমতি নেই',
            'error.not_found': 'খুঁজে পাওয়া যায়নি',
            
            // Success messages
            'success.login': 'সফলভাবে লগইন হয়েছে',
            'success.registration': 'সফলভাবে নিবন্ধন হয়েছে',
            'success.logout': 'সফলভাবে লগআউট হয়েছে',
            'success.profile_updated': 'প্রোফাইল আপডেট হয়েছে',
            'success.product_added': 'পণ্য যোগ করা হয়েছে',
            'success.product_updated': 'পণ্য আপডেট হয়েছে',
            'success.order_placed': 'অর্ডার দেওয়া হয়েছে',
        },
        
        en: {
            // Login page
            'login.subtitle': 'Your Digital Agricultural Cooperation Platform',
            'login.email': 'Email',
            'login.email_placeholder': 'Your email address',
            'login.password': 'Password',
            'login.password_placeholder': 'Your password',
            'login.remember': 'Remember me',
            'login.forgot': 'Forgot password?',
            'login.submit': 'Login',
            'login.no_account': 'Don\'t have an account?',
            'login.register_link': 'Register here',
            
            // Register page
            'register.subtitle': 'Create New Account',
            'register.role': 'Who are you?',
            'register.farmer': 'Farmer',
            'register.farmer_desc': 'Sell crops & market info',
            'register.buyer': 'Buyer',
            'register.buyer_desc': 'Purchase agricultural products',
            'register.first_name': 'First Name',
            'register.first_name_placeholder': 'Your first name',
            'register.last_name': 'Last Name',
            'register.last_name_placeholder': 'Your last name',
            'register.email': 'Email',
            'register.email_placeholder': 'Your email address',
            'register.phone': 'Phone Number',
            'register.phone_placeholder': '+880 1XXX-XXXXXX',
            'register.region': 'Region',
            'register.region_placeholder': 'Select your region',
            'register.password': 'Password',
            'register.password_placeholder': 'At least 6 characters',
            'register.password_strength': 'Password strength:',
            'register.confirm_password': 'Confirm Password',
            'register.confirm_password_placeholder': 'Repeat your password',
            'register.agree': 'I agree to the',
            'register.terms': 'Terms & Conditions',
            'register.agree_end': '',
            'register.submit': 'Register',
            'register.have_account': 'Already have an account?',
            'register.login_link': 'Login here',
            
            // Features
            'features.farmers': 'Farmer Community',
            'features.market': 'Market Analysis',
            'features.ai': 'AI Assistance',
            
            // Dashboard
            'dashboard.welcome': 'Welcome',
            'dashboard.logout': 'Logout',
            'dashboard.profile': 'Profile',
            'dashboard.settings': 'Settings',
            
            // Farmer Dashboard
            'farmer.my_products': 'My Products',
            'farmer.add_product': 'Add New Product',
            'farmer.orders': 'Orders',
            'farmer.sales': 'Sales',
            'farmer.analytics': 'Analytics',
            'farmer.price_trends': 'Price Trends',
            
            // Buyer Dashboard
            'buyer.browse_products': 'Browse Products',
            'buyer.my_orders': 'My Orders',
            'buyer.wishlist': 'Wishlist',
            'buyer.market_prices': 'Market Prices',
            
            // Admin Dashboard
            'admin.users': 'Users',
            'admin.products': 'Products',
            'admin.orders': 'Orders',
            'admin.reports': 'Reports',
            'admin.system': 'System',
            
            // Common
            'common.loading': 'Loading...',
            'common.save': 'Save',
            'common.cancel': 'Cancel',
            'common.delete': 'Delete',
            'common.edit': 'Edit',
            'common.view': 'View',
            'common.search': 'Search',
            'common.filter': 'Filter',
            'common.sort': 'Sort',
            'common.export': 'Export',
            'common.import': 'Import',
            'common.print': 'Print',
            'common.refresh': 'Refresh',
            'common.back': 'Back',
            'common.next': 'Next',
            'common.previous': 'Previous',
            'common.yes': 'Yes',
            'common.no': 'No',
            'common.ok': 'OK',
            'common.error': 'Error',
            'common.success': 'Success',
            'common.warning': 'Warning',
            'common.info': 'Info',
            
            // Products
            'product.name': 'Product Name',
            'product.category': 'Category',
            'product.price': 'Price',
            'product.quantity': 'Quantity',
            'product.unit': 'Unit',
            'product.location': 'Location',
            'product.quality': 'Quality',
            'product.organic': 'Organic',
            'product.description': 'Description',
            'product.images': 'Images',
            'product.harvest_date': 'Harvest Date',
            'product.expiry_date': 'Expiry Date',
            
            // Categories
            'category.rice': 'Rice',
            'category.wheat': 'Wheat',
            'category.potato': 'Potato',
            'category.onion': 'Onion',
            'category.tomato': 'Tomato',
            'category.carrot': 'Carrot',
            'category.cabbage': 'Cabbage',
            'category.cauliflower': 'Cauliflower',
            'category.beans': 'Beans',
            'category.peas': 'Peas',
            'category.spinach': 'Spinach',
            'category.lettuce': 'Lettuce',
            'category.cucumber': 'Cucumber',
            'category.pumpkin': 'Pumpkin',
            'category.eggplant': 'Eggplant',
            'category.okra': 'Okra',
            'category.chili': 'Chili',
            'category.ginger': 'Ginger',
            'category.garlic': 'Garlic',
            'category.coriander': 'Coriander',
            
            // Units
            'unit.kg': 'kg',
            'unit.g': 'g',
            'unit.ton': 'ton',
            'unit.piece': 'piece',
            'unit.dozen': 'dozen',
            'unit.bundle': 'bundle',
            'unit.bag': 'bag',
            'unit.maund': 'maund',
            'unit.seer': 'seer',
            
            // Error messages
            'error.required_field': 'This field is required',
            'error.invalid_email': 'Invalid email address',
            'error.password_mismatch': 'Passwords do not match',
            'error.weak_password': 'Password is too weak',
            'error.login_failed': 'Login failed',
            'error.registration_failed': 'Registration failed',
            'error.network_error': 'Network error',
            'error.server_error': 'Server error',
            'error.unauthorized': 'Unauthorized',
            'error.not_found': 'Not found',
            
            // Success messages
            'success.login': 'Successfully logged in',
            'success.registration': 'Successfully registered',
            'success.logout': 'Successfully logged out',
            'success.profile_updated': 'Profile updated',
            'success.product_added': 'Product added',
            'success.product_updated': 'Product updated',
            'success.order_placed': 'Order placed',
        }
    },
    
    init() {
        // Get language from localStorage or browser
        this.currentLanguage = localStorage.getItem('language') || 
                              (navigator.language.includes('bn') ? 'bn' : 'en');
        this.updatePage();
    },
    
    setLanguage(lang) {
        if (lang in this.translations) {
            this.currentLanguage = lang;
            localStorage.setItem('language', lang);
            this.updatePage();
        }
    },
    
    get(key, fallback = key) {
        return this.translations[this.currentLanguage][key] || 
               this.translations['en'][key] || 
               fallback;
    },
    
    updatePage() {
        // Update all elements with data-i18n attribute
        document.querySelectorAll('[data-i18n]').forEach(element => {
            const key = element.getAttribute('data-i18n');
            element.textContent = this.get(key);
        });
        
        // Update placeholders
        document.querySelectorAll('[data-i18n-placeholder]').forEach(element => {
            const key = element.getAttribute('data-i18n-placeholder');
            element.placeholder = this.get(key);
        });
        
        // Update document direction for RTL languages
        document.documentElement.dir = this.currentLanguage === 'ar' ? 'rtl' : 'ltr';
        
        // Update language selector if present
        const languageSelect = document.getElementById('languageSelect');
        if (languageSelect) {
            languageSelect.value = this.currentLanguage;
        }
    },
    
    formatNumber(number, options = {}) {
        const formatter = new Intl.NumberFormat(
            this.currentLanguage === 'bn' ? 'bn-BD' : 'en-US', 
            options
        );
        return formatter.format(number);
    },
    
    formatCurrency(amount, currency = 'BDT') {
        return this.formatNumber(amount, {
            style: 'currency',
            currency: currency,
            minimumFractionDigits: 0,
            maximumFractionDigits: 2
        });
    },
    
    formatDate(date, options = {}) {
        const formatter = new Intl.DateTimeFormat(
            this.currentLanguage === 'bn' ? 'bn-BD' : 'en-US',
            {
                year: 'numeric',
                month: 'long',
                day: 'numeric',
                ...options
            }
        );
        return formatter.format(new Date(date));
    },
    
    formatTime(date, options = {}) {
        const formatter = new Intl.DateTimeFormat(
            this.currentLanguage === 'bn' ? 'bn-BD' : 'en-US',
            {
                hour: '2-digit',
                minute: '2-digit',
                ...options
            }
        );
        return formatter.format(new Date(date));
    }
};

// Export for use in other modules
if (typeof module !== 'undefined' && module.exports) {
    module.exports = I18n;
}
