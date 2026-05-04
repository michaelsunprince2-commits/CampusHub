# CampusNest - PHP Version Setup Guide

## Overview

CampusNest is a comprehensive student housing marketplace platform. This is the complete PHP/MySQL conversion of the original React application.

## Project Structure

```
php/
├── config/
│   └── database.php          # Database connection and configuration
├── includes/
│   └── functions.php         # Helper functions and utilities
├── models/
│   ├── User.php             # User management logic
│   ├── Property.php         # Property management logic
│   ├── Booking.php          # Booking logic
│   ├── Message.php          # Messaging logic
│   ├── Payment.php          # Payment logic
│   └── Review.php           # Review logic
├── api/
│   ├── auth.php             # Authentication endpoints
│   ├── properties.php       # Property API endpoints
│   ├── bookings.php         # Booking API endpoints
│   ├── messages.php         # Messaging API endpoints
│   ├── payments.php         # Payment API endpoints
│   └── reviews.php          # Review API endpoints
├── public/
│   ├── index.php            # Landing page
│   ├── login.php            # Login page
│   ├── register.php         # Registration page
│   ├── properties.php       # Browse properties
│   ├── property-details.php # Property details page
│   ├── book.php             # Booking page
│   ├── payment.php          # Payment page
│   ├── bookings.php         # My bookings (student)
│   ├── profile.php          # User profile
│   ├── messages.php         # Messaging interface
│   ├── landlord-dashboard.php # Landlord dashboard
│   └── admin-dashboard.php  # Admin dashboard
└── templates/
    ├── header.php           # Header template
    └── footer.php           # Footer template
```

## System Requirements

- PHP 7.4 or higher
- MySQL 5.7 or higher
- Web server (Apache, Nginx, etc.)
- Modern web browser

## Installation Steps

### 1. Database Setup

```bash
# Import the database schema
mysql -u root -p < database.sql
```

Or manually:
1. Log into phpMyAdmin or MySQL CLI
2. Create a new database: `CREATE DATABASE campusnest;`
3. Import the [database.sql](database.sql) file

### 2. Configure Database Connection

Edit `php/config/database.php`:

```php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', 'your_password_here');  // Update this
define('DB_NAME', 'campusnest');
define('DB_PORT', 3306);
```

### 3. Web Server Configuration

**For Apache (with .htaccess support):**

Create `php/.htaccess`:
```
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteBase /
    RewriteRule ^index\.php$ - [L]
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteRule . /index.php [L]
</IfModule>
```

**For local development:**
```bash
# Using PHP built-in server (from php directory)
php -S localhost:8000
```

Then access: `http://localhost:8000/public/index.php`

### 4. File Permissions

Ensure web server can read/write to necessary directories:

```bash
chmod 755 php/
chmod 755 php/public/
chmod 755 php/config/
```

## Features

### User Roles

1. **Student**
   - Browse and search properties
   - Make bookings
   - Process payments
   - Leave reviews
   - Message landlords

2. **Landlord**
   - List properties for rent
   - Manage bookings
   - View tenant information
   - Receive messages from students
   - Track payments

3. **Admin/Committee**
   - Verify properties and users
   - Manage all listings
   - View reports and analytics
   - Handle disputes and refunds

### Core Features

- **Property Management**: List, edit, delete properties with amenities and rules
- **Booking System**: Students can book properties with date selection
- **Payment Processing**: Integrated payment gateway with multiple methods
- **Messaging**: Real-time messaging between students and landlords
- **Reviews**: Property ratings and reviews from verified tenants
- **User Verification**: Admin verification of users and properties
- **Search & Filter**: Advanced search with multiple filters
- **Dashboard**: Role-specific dashboards with analytics

## API Endpoints

### Authentication
- `POST /api/auth.php?action=register` - Register new user
- `POST /api/auth.php?action=login` - Login user
- `POST /api/auth.php?action=logout` - Logout user
- `GET /api/auth.php?action=check` - Check authentication status

### Properties
- `GET /api/properties.php?action=list` - List all properties
- `GET /api/properties.php?action=get&id=1` - Get property details
- `POST /api/properties.php?action=create` - Create new property
- `PUT /api/properties.php?action=update&id=1` - Update property
- `DELETE /api/properties.php?action=delete&id=1` - Delete property

### Bookings
- `POST /api/bookings.php?action=create` - Create booking
- `GET /api/bookings.php?action=get&id=1` - Get booking details
- `GET /api/bookings.php?action=my-bookings` - Get user's bookings
- `PUT /api/bookings.php?action=confirm&id=1` - Confirm booking
- `PUT /api/bookings.php?action=cancel&id=1` - Cancel booking

### Messages
- `POST /api/messages.php?action=send` - Send message
- `GET /api/messages.php?action=conversation&user_id=1` - Get conversation
- `GET /api/messages.php?action=conversations` - Get all conversations
- `GET /api/messages.php?action=unread-count` - Get unread message count

### Payments
- `POST /api/payments.php?action=create` - Create payment
- `GET /api/payments.php?action=get&id=1` - Get payment details
- `PUT /api/payments.php?action=complete&id=1` - Complete payment
- `PUT /api/payments.php?action=refund&id=1` - Refund payment

### Reviews
- `POST /api/reviews.php?action=create` - Create review
- `GET /api/reviews.php?action=list&property_id=1` - Get reviews
- `PUT /api/reviews.php?action=helpful&id=1` - Mark review helpful
- `DELETE /api/reviews.php?action=delete&id=1` - Delete review

## Database Schema

### Main Tables
- `users` - User accounts
- `landlord_profiles` - Landlord specific info
- `student_profiles` - Student specific info
- `properties` - Property listings
- `bookings` - Property bookings
- `payments` - Payment records
- `messages` - User messages
- `reviews` - Property reviews
- `verification_requests` - Verification queue
- `favorites` - Bookmarked properties
- `sessions` - User sessions
- `audit_logs` - Activity logs

## Security Features

1. **Password Security**: Bcrypt hashing with strong salt rounds
2. **Session Management**: Token-based session tracking
3. **Input Validation**: Sanitization of all user inputs
4. **SQL Injection Prevention**: Prepared statements for all queries
5. **CSRF Protection**: Session-based token validation
6. **Access Control**: Role-based route protection
7. **Audit Logging**: All admin actions logged

## Default Test Accounts

After running the database setup, you can create test users:

**Student Account:**
- Email: student@test.com
- Password: password123
- Role: student

**Landlord Account:**
- Email: landlord@test.com
- Password: password123
- Role: landlord

**Admin Account:**
- Email: admin@test.com
- Password: password123
- Role: admin

## Configuration

### Environment Variables

Create `.env` file in root directory:
```
DB_HOST=localhost
DB_USER=root
DB_PASS=password
DB_NAME=campusnest
ENVIRONMENT=development
```

### Email Configuration

For email notifications (optional), configure SMTP:
```php
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USER', 'your-email@gmail.com');
define('SMTP_PASS', 'your-password');
```

## Troubleshooting

### Database Connection Error

1. Verify MySQL is running
2. Check credentials in `config/database.php`
3. Ensure database exists: `CREATE DATABASE campusnest;`

### Session Not Working

1. Check PHP `session.save_path` is writable
2. Verify cookies are enabled in browser
3. Clear browser cache and cookies

### Page Not Found (404)

1. Verify file paths in URLs
2. Check web server error logs
3. Ensure `.htaccess` is properly configured

### Payment Not Processing

1. Verify payment method selection
2. Check payment model and API endpoints
3. Review error logs in `/php/api/`

## Performance Optimization

1. **Database Indexing**: Already configured in schema
2. **Caching**: Implement Redis for session caching
3. **Pagination**: Limited results per page (10-50 items)
4. **Query Optimization**: Use prepared statements
5. **Lazy Loading**: Load images on demand

## Deployment Checklist

- [ ] Set `ENVIRONMENT` to 'production'
- [ ] Update database credentials
- [ ] Configure HTTPS/SSL
- [ ] Set up automated backups
- [ ] Enable error logging
- [ ] Disable debug output
- [ ] Configure email service
- [ ] Set up monitoring
- [ ] Test all payment flows
- [ ] Verify user authentication

## Support & Documentation

For detailed documentation on each module, see:
- [Database Schema](database.sql)
- [API Reference](php/api/)
- [Model Documentation](php/models/)

## License

This project is proprietary and for educational purposes.

## Contact

For support or questions, contact the development team.
