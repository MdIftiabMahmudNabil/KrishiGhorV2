# 🌾 KrishiGhor - Digital Agricultural Cooperation Platform

[![PHP](https://img.shields.io/badge/PHP-8.0+-777BB4?style=for-the-badge&logo=php&logoColor=white)](https://php.net)
[![PostgreSQL](https://img.shields.io/badge/PostgreSQL-316192?style=for-the-badge&logo=postgresql&logoColor=white)](https://postgresql.org)
[![Tailwind CSS](https://img.shields.io/badge/Tailwind_CSS-38B2AC?style=for-the-badge&logo=tailwind-css&logoColor=white)](https://tailwindcss.com)
[![Docker](https://img.shields.io/badge/Docker-2496ED?style=for-the-badge&logo=docker&logoColor=white)](https://docker.com)
[![Supabase](https://img.shields.io/badge/Supabase-3ECF8E?style=for-the-badge&logo=supabase&logoColor=white)](https://supabase.com)

## 📖 **Project Overview**

**KrishiGhor** is a comprehensive digital agricultural cooperation platform that connects farmers, buyers, and administrators in a seamless ecosystem. Built with modern web technologies, it provides real-time market insights, AI-powered analytics, and efficient order management for agricultural products.

###  **Mission**
To digitize and streamline agricultural commerce, making it easier for farmers to sell their products and buyers to source quality agricultural goods with transparency and efficiency.

###  **Key Features**
- **Multi-role User Management** (Farmer, Buyer, Admin)
- **AI-Powered Market Analytics** & Price Forecasting
- **Real-time Order Management** & Payment Processing
- **Transport & Delivery Tracking**
- **Regional Product Filtering** & Smart Search
- **Responsive Modern UI** with Glass Morphism Design

##  **Architecture Overview**

### **Backend Architecture**
```
src/
├── config/          # Configuration files
├── controllers/     # MVC Controllers
├── models/         # Data models
├── services/       # Business logic services
├── database/       # Database migrations
└── utils/          # Utility functions
```

### **Frontend Structure**
```
public/
├── admin/          # Admin dashboard pages
├── buyer/          # Buyer dashboard pages
├── farmer/         # Farmer dashboard pages
├── assets/         # Images, videos, logo
├── css/            # Tailwind CSS styles
└── js/             # JavaScript functionality
```

### **Technology Stack**
- **Backend**: PHP 8.0+ (MVC Architecture)
- **Database**: PostgreSQL (Supabase)
- **Frontend**: HTML5, Tailwind CSS, Vanilla JavaScript
- **Containerization**: Docker
- **Authentication**: JWT-based
- **AI Features**: Machine Learning models for analytics

##  **Quick Start**

### **Prerequisites**
- PHP 8.0 or higher
- PostgreSQL database access
- Composer (for PHP dependencies)
- Git

### **Installation Steps**

1. **Clone the Repository**
   ```bash
   git clone https://github.com/YOUR_USERNAME/krishighor.git
   cd krishighor
   ```

2. **Install Dependencies**
   ```bash
   composer install
   ```

3. **Configure Environment**
   ```bash
   # Copy the environment configuration
   cp src/config/env.php.example src/config/env.php
   
   # Edit with your database credentials
   nano src/config/env.php
   ```

4. **Database Setup**
   ```bash
   # Run the initial schema migration
   psql -h your-db-host -U your-username -d your-database -f src/database/migrations/001_initial_schema.sql
   ```

5. **Start Development Server**
   ```bash
   php -S localhost:8000 -t public
   ```

6. **Access Application**
   - Open: http://localhost:8000
   - Default Admin: `admin@krishighor.com` / `password`

##  **Database Configuration**

### **Supabase Setup**
1. Create a new project at [supabase.com](https://supabase.com)
2. Get your database connection details
3. Update `src/config/env.php` with your credentials:

```php
define('DB_HOST', 'your-project.supabase.co');
define('DB_USER', 'postgres');
define('DB_PASSWORD', 'your-password');
define('DB_NAME', 'postgres');
define('DB_SSL_MODE', 'require');
```

### **Database Schema**
The application includes these core tables:
- `users` - User accounts and profiles
- `products` - Agricultural products
- `orders` - Order management
- `transport_requests` - Delivery tracking
- `price_history` - Market price analytics
- `reviews` - Product feedback
- `notifications` - System alerts

##  **Configuration**

### **Environment Variables**
Key configuration options in `src/config/env.php`:

```php
// Database
DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASSWORD, DB_SSL_MODE

// Application
APP_NAME, APP_ENV, APP_DEBUG, APP_TIMEZONE

// Security
JWT_SECRET, ENCRYPTION_KEY

// File Upload
MAX_FILE_SIZE, ALLOWED_EXTENSIONS

// Email
SMTP_HOST, SMTP_PORT, SMTP_USERNAME, SMTP_PASSWORD
```

### **Web Server Configuration**
For production deployment, ensure your web server:
- Points to the `public/` directory
- Has URL rewriting enabled (`.htaccess` for Apache)
- Supports PHP 8.0+

##  **Deployment**

### **Render (Recommended)**
1. Push your code to GitHub
2. Connect your repository to Render
3. Configure as a PHP web service
4. Set environment variables
5. Deploy!

### **Other Platforms**
- **Railway**: `railway up`
- **Heroku**: `git push heroku main`
- **VPS**: Traditional server deployment

See [DEPLOYMENT.md](DEPLOYMENT.md) for detailed instructions.

##  **Testing**

### **Database Connection Test**
```bash
# Test PostgreSQL connection
php public/test-postgresql.php
```

### **Unit Tests**
```bash
# Run PHPUnit tests
composer test
```

##  **Security Features**

- **JWT Authentication** with secure token management
- **Password Hashing** using bcrypt
- **SQL Injection Protection** via PDO prepared statements
- **XSS Protection** with input sanitization
- **CSRF Protection** for form submissions
- **Rate Limiting** on authentication endpoints
- **SSL/TLS Enforcement** for database connections

##  **AI Features**

### **Market Analytics**
- **Price Forecasting**: Short-term price predictions using time-series models
- **Anomaly Detection**: Identifies unusual price spikes/drops
- **Regional Optimization**: "Where to sell" recommendations based on transport costs

### **User Experience**
- **Fuzzy Search**: Intelligent product search with typo tolerance
- **Content Validation**: Image content verification
- **Smart Reminders**: Automated payment and delivery notifications

### **Risk Management**
- **Payment Risk Scoring**: Identifies high-risk transactions
- **Order Anomaly Detection**: Flags unusual order patterns
- **Delivery Risk Assessment**: ETA predictions and delay warnings

##  **User Roles & Dashboards**

### ** Farmer Dashboard**
- Product listing and management
- Order acceptance/rejection
- Transport request creation
- Real-time delivery tracking
- Market price insights

### ** Buyer Dashboard**
- Product browsing and search
- Order placement and tracking
- Payment processing
- Delivery status monitoring
- Price comparison tools

### ** Admin Dashboard**
- User management and role assignment
- Market analytics and reports
- System monitoring
- Content moderation
- Performance metrics

##  **Internationalization (i18n)**

The application supports multiple languages:
- **English** (Primary)
- **Bengali** (বাংলা) - Regional support

Language switching is available throughout the interface.

##  **Performance & Optimization**

- **Database Indexing**: Optimized queries with proper indexes
- **Asset Compression**: GZIP compression for static files
- **Browser Caching**: Efficient caching strategies
- **Connection Pooling**: Database connection optimization
- **CDN Ready**: Static asset delivery optimization

##  **Troubleshooting**

### **Common Issues**

1. **Database Connection Failed**
   - Verify Supabase credentials
   - Check IP whitelist
   - Ensure SSL is enabled

2. **PHP Not Recognized**
   - Install PHP and add to PATH
   - Use XAMPP or similar package

3. **Assets Not Loading**
   - Check `.htaccess` configuration
   - Verify file permissions
   - Ensure proper file paths

### **Debug Mode**
Enable debug mode in `src/config/env.php`:
```php
define('APP_DEBUG', true);
```

##  **Contributing**

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Add tests if applicable
5. Submit a pull request

##  **License**

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

##  **Support**

- **Documentation**: [DEPLOYMENT.md](DEPLOYMENT.md)
- **Database Guide**: [POSTGRESQL_SETUP.md](POSTGRESQL_SETUP.md)
- **Issues**: GitHub Issues page
- **Email**: support@krishighor.com

##  **Acknowledgments**

- **Tailwind CSS** for the beautiful UI framework
- **Supabase** for the robust database infrastructure
- **PHP Community** for the excellent ecosystem
- **Agricultural Community** for inspiration and feedback

---

**Made with ❤️ for the Agricultural Community**

*Empowering farmers, connecting buyers, building the future of agriculture.*
