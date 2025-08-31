# 🚀 KrishiGhor Setup Guide

## Overview
KrishiGhor is a comprehensive Digital Agricultural Cooperation Platform that connects farmers, buyers, and transporters in Bangladesh. The platform features modern UI/UX with emerald/sky color scheme, glass morphism effects, and AI-powered features.

## 🗄️ Database Configuration (Supabase)

### Current Configuration
The application is configured to use **Supabase PostgreSQL** with the following credentials:

```php
// src/config/database.php
$host = 'db.moozvhfbkhbepmjadijj.supabase.co';
$port = '5432';
$dbname = 'postgres';
$username = 'postgres';
$password = 'system307projectG7';
```

### Database Connection Details
- **Host**: `db.moozvhfbkhbepmjadijj.supabase.co`
- **Port**: `5432`
- **Database**: `postgres`
- **Username**: `postgres`
- **Password**: `system307projectG7`
- **SSL Mode**: `require`

### Testing Database Connection
Visit `/test-db.php` in your browser to test the database connection and see:
- Connection status
- Database version and details
- Existing tables
- Timezone settings

## 🎨 Design System

### Color Palette
- **Primary**: Emerald-600 (#059669)
- **Secondary**: Sky-600 (#0284c7)
- **Background**: Slate-50 (#f8fafc)
- **Text**: Slate-900 for headers, Slate-600 for body

### Typography
- **Font Family**: Inter (Google Fonts)
- **Weights**: 400, 500, 600, 700, 800

### Visual Effects
- **Glass Morphism**: Backdrop blur with transparency
- **Soft Shadows**: Subtle depth effects
- **Rounded Corners**: Consistent xl (12px) and 2xl (16px) radius
- **Smooth Animations**: Fade-in transitions and hover effects

## 🏗️ Application Structure

### Frontend
```
public/
├── assets/           # Images, videos, logo
├── css/
│   └── app.css      # Main CSS framework
├── dashboard/
│   ├── farmer.html  # Farmer dashboard
│   ├── buyer.html   # Buyer dashboard
│   └── admin.html   # Admin dashboard
├── js/
│   ├── dashboard-navigation.js  # Navigation system
│   ├── i18n.js                  # Internationalization
│   ├── forms.js                 # Form validation
│   └── ...                     # Other JS modules
├── home.html        # Landing page
├── login.html       # Login page
└── register.html    # Registration page
```

### Backend
```
src/
├── config/
│   └── database.php     # Database configuration
├── controllers/          # API controllers
├── models/              # Data models
├── services/            # Business logic services
└── middlewares/         # Request processing
```

## 🚀 Getting Started

### 1. Prerequisites
- PHP 8.0 or higher
- Web server (Apache/Nginx)
- PostgreSQL support in PHP
- Composer (for PHP dependencies)

### 2. Installation
```bash
# Clone the repository
git clone <repository-url>
cd KrishiGhor

# Install PHP dependencies
composer install

# Install Node.js dependencies (for Tailwind CSS)
npm install

# Build CSS
npm run build
```

### 3. Database Setup
The application automatically connects to Supabase. No additional setup required.

### 4. Web Server Configuration
Configure your web server to serve from the `public/` directory.

#### Apache (.htaccess)
```apache
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ index.php [QSA,L]
```

#### Nginx
```nginx
location / {
    try_files $uri $uri/ /index.php?$query_string;
}
```

## 🎯 Dashboard Navigation

### How It Works
The dashboard navigation system uses JavaScript to handle section switching without page reloads:

1. **Sidebar Links**: All navigation links use `href="#"` and `data-section` attributes
2. **Content Sections**: Each section has an ID like `dashboardSection`, `productsSection`, etc.
3. **JavaScript Handler**: `dashboard-navigation.js` manages the navigation logic
4. **URL Updates**: Browser URL updates to reflect current section
5. **State Management**: Active states and transitions are handled automatically

### Navigation Features
- ✅ **Smooth Transitions**: Fade-in animations between sections
- ✅ **URL Persistence**: Browser back/forward buttons work correctly
- ✅ **Active States**: Visual feedback for current section
- ✅ **Section Initialization**: Each section can have custom initialization logic
- ✅ **Error Handling**: Graceful fallbacks for missing sections

## 🔧 Configuration

### Environment Variables
While `.env` file creation is blocked, you can configure the application by modifying:

1. **Database**: `src/config/database.php`
2. **Application**: `src/config/app.php`
3. **Frontend**: `public/js/config.js`

### Customization
- **Colors**: Modify CSS custom properties in `public/css/app.css`
- **Fonts**: Update Google Fonts import in CSS
- **Animations**: Adjust transition timings in CSS variables
- **Layout**: Modify Tailwind classes in HTML files

## 📱 Responsive Design

### Breakpoints
- **Mobile**: < 768px
- **Tablet**: 768px - 1024px
- **Desktop**: > 1024px

### Mobile Features
- Collapsible sidebar
- Touch-friendly buttons
- Optimized spacing
- Mobile-specific navigation

## 🌐 Internationalization (i18n)

### Supported Languages
- **English** (en)
- **Bengali** (bn)

### Implementation
- Language detection from browser
- Persistent language selection
- Dynamic content switching
- Placeholder text support

## 🔒 Security Features

### Authentication
- JWT-based authentication
- Secure password handling
- Session management
- Role-based access control

### Data Protection
- SQL injection prevention
- XSS protection
- CSRF tokens
- Input validation

## 📊 AI Features

### Implemented Features
- **Price Forecasting**: ML models for price prediction
- **Anomaly Detection**: Fraud and risk detection
- **Smart Matching**: AI-powered user matching
- **Content Validation**: Image and text validation

### Services
- `ForecastingService`: Price prediction algorithms
- `SecurityService`: Fraud detection
- `ProductSearchService`: Intelligent search
- `ImageContentService`: Content validation

## 🧪 Testing

### Database Connection Test
```bash
# Visit in browser
http://localhost:8000/test-db.php
```

### Manual Testing
1. **Navigation**: Test all sidebar links
2. **Forms**: Test login/registration
3. **Responsiveness**: Test on different screen sizes
4. **Language**: Test language switching

## 🚨 Troubleshooting

### Common Issues

#### Database Connection Failed
- Check Supabase credentials
- Verify SSL requirements
- Check firewall settings
- Test with `test-db.php`

#### Navigation Not Working
- Check browser console for errors
- Verify `dashboard-navigation.js` is loaded
- Check HTML structure for section IDs
- Ensure JavaScript is enabled

#### Styling Issues
- Check Tailwind CSS is loaded
- Verify custom CSS is included
- Check for CSS conflicts
- Clear browser cache

### Debug Mode
Enable debug mode by setting:
```php
// src/config/app.php
define('APP_DEBUG', true);
```

## 📈 Performance Optimization

### Frontend
- Minified CSS and JavaScript
- Optimized images
- Lazy loading for charts
- Efficient DOM manipulation

### Backend
- Database connection pooling
- Query optimization
- Caching strategies
- Efficient routing

## 🔄 Updates and Maintenance

### Regular Tasks
- Database connection monitoring
- Error log review
- Performance monitoring
- Security updates

### Backup Strategy
- Regular database backups
- Code version control
- Configuration backups
- Disaster recovery plan

## 📞 Support

### Documentation
- This setup guide
- Code comments
- API documentation
- User manuals

### Contact
For technical support or questions, refer to the project documentation or contact the development team.

---

**Last Updated**: December 2024
**Version**: 4.0
**Status**: Production Ready ✅
