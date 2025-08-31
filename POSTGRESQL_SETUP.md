# 🐘 PostgreSQL Setup Guide for KrishiGhor

## Overview
KrishiGhor is now configured to use **PostgreSQL** with **Supabase** instead of MySQL. This provides better performance, advanced features, and cloud-native capabilities.

## 🚀 Quick Start

### 1. Database Connection
Your Supabase PostgreSQL connection is already configured:

```php
// Connection Details
Host: db.moozvhfbkhbepmjadijj.supabase.co
Port: 5432
Database: postgres
Username: postgres
Password: system307projectG7
SSL: Required
```

### 2. Test Your Connection
Visit: `http://localhost:8000/test-postgresql.php`

This will show you:
- ✅ Connection status
- 📊 Database information
- 🔒 SSL configuration
- ⚡ Performance metrics
- 📋 Table structure

## 🔧 Configuration Files

### Environment Configuration
- **File**: `src/config/env.php`
- **Purpose**: Contains all environment variables and database settings
- **Security**: Credentials are centralized and easily manageable

### Database Configuration
- **File**: `src/config/database.php`
- **Purpose**: Handles PostgreSQL connection with PDO
- **Features**: Connection pooling, SSL support, error handling

## 📊 Database Schema

### Core Tables
1. **`users`** - User accounts (farmers, buyers, admins)
2. **`products`** - Agricultural products
3. **`orders`** - Purchase orders
4. **`transport_requests`** - Delivery management
5. **`price_history`** - Market price tracking
6. **`reviews`** - User ratings
7. **`notifications`** - System alerts

### PostgreSQL Features Used
- **UUIDs** for primary keys
- **JSONB** for flexible data storage
- **GIN indexes** for full-text search
- **Triggers** for automatic timestamps
- **Views** for complex queries
- **Point geometry** for location data

## 🗄️ Database Migration

### Run Initial Schema
```sql
-- Connect to your Supabase database and run:
\i src/database/migrations/001_initial_schema.sql
```

### Or Use psql Command Line
```bash
psql "postgresql://postgres:system307projectG7@db.moozvhfbkhbepmjadijj.supabase.co:5432/postgres" -f src/database/migrations/001_initial_schema.sql
```

## 🔍 Testing Your Setup

### 1. Connection Test
```bash
# Test basic connection
php public/test-postgresql.php
```

### 2. Database Operations
```php
// In your PHP code
$db = Database::getInstance();
$connection = $db->getConnection();

// Test query
$stmt = $connection->query('SELECT version()');
$version = $stmt->fetchColumn();
echo "PostgreSQL Version: " . $version;
```

### 3. Sample Data
The migration includes sample users:
- **Admin**: `admin@krishighor.com` / `password`
- **Farmer**: `farmer1@krishighor.com` / `password`
- **Buyer**: `buyer1@krishighor.com` / `password`

## 🛠️ Development Tools

### Recommended Database Clients
1. **pgAdmin** - Official PostgreSQL admin tool
2. **DBeaver** - Universal database tool
3. **Supabase Dashboard** - Web-based interface

### Connection String
```
postgresql://postgres:system307projectG7@db.moozvhfbkhbepmjadijj.supabase.co:5432/postgres
```

## 🔒 Security Features

### SSL Connection
- **Required**: `sslmode=require`
- **Encryption**: All data is encrypted in transit
- **Authentication**: Secure password-based access

### Data Protection
- **Password hashing**: Bcrypt with salt
- **Input validation**: PDO prepared statements
- **Access control**: Role-based permissions

## 📈 Performance Optimizations

### Indexes
- **Primary keys**: UUID with auto-generation
- **Search indexes**: GIN for full-text search
- **Foreign keys**: Referential integrity
- **Composite indexes**: Multi-column queries

### Query Optimization
- **Views**: Pre-computed complex queries
- **Triggers**: Automatic data updates
- **Connection pooling**: Reuse connections
- **Prepared statements**: Query caching

## 🚨 Troubleshooting

### Common Issues

#### 1. Connection Failed
```bash
# Check if Supabase is accessible
ping db.moozvhfbkhbepmjadijj.supabase.co

# Test port connectivity
telnet db.moozvhfbkhbepmjadijj.supabase.co 5432
```

#### 2. SSL Error
```php
// Ensure SSL is required
$dsn = "pgsql:host={$host};port={$port};dbname={$dbname};sslmode=require";
```

#### 3. Authentication Failed
- Verify username/password
- Check IP whitelist in Supabase
- Ensure database exists

### Debug Mode
```php
// Enable debug logging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check connection status
$db = Database::getInstance();
$info = $db->getConnectionInfo();
var_dump($info);
```

## 🔄 Migration from MySQL

### Key Differences
1. **Syntax**: `LIMIT` vs `FETCH FIRST`
2. **Functions**: `NOW()` vs `CURRENT_TIMESTAMP`
3. **Data types**: `TEXT` vs `LONGTEXT`
4. **Auto-increment**: `SERIAL` vs `AUTO_INCREMENT`

### Code Changes
```php
// Old MySQL
$stmt = $connection->query("SELECT * FROM users LIMIT 10");

// New PostgreSQL
$stmt = $connection->query("SELECT * FROM users FETCH FIRST 10 ROWS ONLY");
```

## 📚 Additional Resources

### PostgreSQL Documentation
- [Official Docs](https://www.postgresql.org/docs/)
- [PHP PDO](https://www.php.net/manual/en/book.pdo.php)
- [Supabase Guide](https://supabase.com/docs)

### KrishiGhor Specific
- Database models in `src/models/`
- Controllers in `src/controllers/`
- Migration files in `src/database/migrations/`

## ✅ Verification Checklist

- [ ] Database connection successful
- [ ] All tables created
- [ ] Sample data inserted
- [ ] Indexes created
- [ ] Triggers working
- [ ] Views accessible
- [ ] SSL connection verified
- [ ] Performance acceptable

## 🎯 Next Steps

1. **Run the migration** to create tables
2. **Test the connection** with the test file
3. **Verify sample data** is accessible
4. **Start developing** with the new schema
5. **Monitor performance** and optimize as needed

---

**Need Help?** Check the troubleshooting section or run the test file to diagnose any issues.
