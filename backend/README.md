# EcoTransit PHP Backend Setup

## Prerequisites
- **XAMPP/WAMP/MAMP** or any local server with PHP and MySQL
- **PHP 7.4+**
- **MySQL 5.7+**

## Setup Instructions

### 1. Database Setup
1. Start your MySQL server
2. Create database using phpMyAdmin or MySQL command line:
   ```sql
   CREATE DATABASE ecotransit;
   ```
3. Import the schema:
   ```bash
   mysql -u root -p ecotransit < database/schema.sql
   ```

### 2. Configure Database Connection
Edit `backend/config/database.php` and update these values:
```php
private $host = 'localhost';
private $db_name = 'ecotransit';
private $username = 'root';        // Your MySQL username
private $password = '';            // Your MySQL password
```

### 3. Server Setup
1. Copy the entire project to your web server directory:
   - **XAMPP**: `C:/xampp/htdocs/ecotransit/`
   - **WAMP**: `C:/wamp64/www/ecotransit/`
   - **MAMP**: `/Applications/MAMP/htdocs/ecotransit/`

2. Start Apache and MySQL services

3. Test the setup by visiting:
   ```
   http://localhost/ecotransit/frontend/dashboard.html
   ```

## API Endpoints

### Authentication
- **Register**: `POST /backend/api/auth.php?action=register`
- **Login**: `POST /backend/api/auth.php?action=login`

### Trips
- **Log Trip**: `POST /backend/api/trips.php`
- **Get Analytics**: `GET /backend/api/trips.php?action=analytics&user_id=1`
- **Get History**: `GET /backend/api/trips.php?action=history&user_id=1`

### Carpools
- **Create Carpool**: `POST /backend/api/carpool.php?action=create`
- **Search Carpools**: `GET /backend/api/carpool.php?action=search&from=Mumbai&to=Pune`
- **User Carpools**: `GET /backend/api/carpool.php?action=user_carpools&user_id=1`

## Testing APIs

You can test the APIs using tools like Postman or curl:

### Register User
```bash
curl -X POST http://localhost/ecotransit/backend/api/auth.php?action=register \
  -H "Content-Type: application/json" \
  -d '{"username":"testuser","email":"test@example.com","password":"password123","full_name":"Test User"}'
```

### Login User
```bash
curl -X POST http://localhost/ecotransit/backend/api/auth.php?action=login \
  -H "Content-Type: application/json" \
  -d '{"username":"testuser","password":"password123"}'
```

### Log Trip
```bash
curl -X POST http://localhost/ecotransit/backend/api/trips.php \
  -H "Content-Type: application/json" \
  -d '{"user_id":1,"transport_type":"bike","distance":5.2}'
```

## Directory Structure
```
backend/
├── config/
│   └── database.php      # Database configuration
├── api/
│   ├── auth.php         # Authentication endpoints
│   ├── trips.php        # Trip logging and analytics
│   └── carpool.php      # Carpool management
└── README.md           # This file
```

## Next Steps
1. Update your frontend JavaScript to call these APIs
2. Add proper error handling
3. Implement session management
4. Add input validation and sanitization
