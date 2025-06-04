# SPREADLY

## What is that ?

At HelloAsso we are used to sending each other little kind words for christmas period.

This repo contains a single page to collect words and a script to send mail.

## 🚀 Architecture - MVC with Slim Framework

This version has been completely refactored with a clean MVC architecture using Slim Framework:

### **Architecture Overview**
- **Framework**: Slim 4 with PSR-7, PSR-11 (DI Container)
- **Template Engine**: Twig
- **Architecture**: Model-View-Controller (MVC)
- **Dependency Injection**: PHP-DI
- **Autoloading**: PSR-4

### **Directory Structure**
```
├── src/
│   ├── Controllers/     # Application controllers
│   ├── Models/         # Data models
│   ├── Services/       # Business logic services
│   └── Middleware/     # Custom middleware
├── templates/          # Twig templates
├── public/            # Web root (index.php, assets)
├── config/            # Configuration files
└── vendor/            # Composer dependencies
```

### **Key Features**
- **Clean separation of concerns**: Models, Views, Controllers
- **Dependency Injection**: All dependencies properly injected
- **Type safety**: PHP 8+ type declarations
- **Template inheritance**: Twig templates with layouts
- **Middleware**: Authentication middleware
- **PSR standards**: Following PHP-FIG standards

## Account Management Features

- **User Authentication**: Login with unique codes sent to email (no passwords needed)
- **User Management**: Renamed `receiver` table to `user` with additional fields
- **Sender Tracking**: Messages now track both anonymous and authenticated senders
- **Admin Interface**: Manage users through `/admin`

### Database Changes

- `receiver` table renamed to `user` with new fields:
  - `receiver` (boolean): Whether user can receive messages
  - `login_code` & `login_code_expires`: For email-based authentication
  - `created_at`: Timestamp
- `love` table updated with:
  - `id_sender`: Links to authenticated user who sent the message
  - `created_at`: Timestamp

## 🚀 Quick Start Guide

### 1. Installation

```bash
composer install
```

### 2. Database Setup

1. Create your MySQL database
2. Import the schema:
```bash
mysql -u your_user -p your_database < db.sql
```

### 3. Admin System Migration

If you're upgrading from a previous version, run the admin migration:
```bash
php migrate-admin.php
```

This will:
- Add the `admin` field to existing users
- Make the first user an administrator

### 4. Configuration

#### Option A: Environment Variables
Set these environment variables in your system or web server:

```bash
export DbUrl="localhost"
export DbName="spreadly"
export DbUser="your_db_user"
export DbPassword="your_db_password"

# For email functionality (optional in development)
export MailjetPublicKey="your_mailjet_public_key"
export MailjetPrivateKey="your_mailjet_private_key"
export MailFromEmail="noreply@yourdomain.com"
export MailFromName="Spreadly"
```

#### Option B: .env File
Copy `env.example` to `.env` and fill in your values:

```bash
cp env.example .env
# Edit .env with your configuration
```

### 5. Development Mode

If you don't configure Mailjet, the app runs in **development mode**:
- ✅ Login codes are logged to error log
- ✅ Login codes are displayed on screen
- ✅ No emails are actually sent

### 6. First Steps

1. **Visit `/login`** - Login page
2. **Enter any email from your user table**
3. **In development mode**: The login code will be shown on screen
4. **Login as admin and visit `/admin`** - Manage users
5. **Create more users and assign admin rights**
6. **Start sending love messages!**
7. **Edit or delete your messages** - See your progress in the dropdown

## Message Management

### For Senders
- ✅ **Visual indicators** in dropdown - see who you've already messaged
- ✅ **Edit existing messages** - select a recipient to modify your message
- ✅ **Delete messages** - remove messages you've sent
- ✅ **Progress tracking** - ✉️ icon shows sent messages
- ✅ **Smart interface** - button changes to "Modify message" when editing

### Message Features
- 📝 **Create new messages** for recipients without existing messages
- ✏️ **Edit existing messages** by selecting the recipient
- 🗑️ **Delete messages** with confirmation dialog
- 📅 **Timestamp display** shows when message was originally sent
- 🔒 **Security** - can only edit/delete your own messages

### User Experience
- **Dropdown indicators**: Recipients with messages show ✉️ (message envoyé)
- **Auto-loading**: Selecting a recipient loads existing message for editing
- **Dynamic buttons**: Submit button changes based on action (send/modify)
- **Status messages**: Clear feedback on message creation date
- **Confirmation dialogs**: Prevent accidental deletions

## Admin System

### Admin Rights
- ✅ Only admins can access `/admin` routes
- ✅ Only admins see the "Administration" button
- ✅ Admins can create users and assign admin rights
- ✅ First user is automatically made admin during migration

### User Management
- **Create users** with receiver and admin flags
- **Edit users** - modify name, email, receiver and admin status
- **Delete users** - remove users (cannot delete yourself)
- **View all users** with their permissions
- **Admin users** are marked with a warning badge

### CSV Import
- **Bulk import** users from CSV file
- **Format**: `nom, email, receiver (true/false), admin (true/false)`
- **Example CSV**:
  ```csv
  Jean Dupont,jean.dupont@example.com,true,false
  Marie Martin,marie.martin@example.com,true,false
  Pierre Admin,pierre.admin@example.com,false,true
  ```
- **Smart handling**: Skips existing emails, reports results
- **Validation**: Checks email format and required fields

### Admin Interface Features
- 🔧 **Edit button** (✏️) for each user
- 🗑️ **Delete button** with confirmation dialog
- 📁 **CSV import** section with file upload
- 📊 **Results reporting** for batch operations
- 🛡️ **Self-protection** - cannot delete your own account

## Routes

### **Public Routes**
- `GET /login` - Login page
- `POST /auth/request` - Request login code
- `POST /auth/verify` - Verify login code

### **Protected Routes** (require authentication)
- `GET /` - Home page (send messages)
- `POST /send` - Send/edit love message
- `POST /message/delete/{id}` - Delete message
- `POST /auth/logout` - Logout

### **Admin Routes** (require admin privileges)
- `GET /admin` - User management
- `POST /admin/create` - Create new user
- `GET /admin/edit/{id}` - Edit user form
- `POST /admin/edit/{id}` - Update user
- `POST /admin/delete/{id}` - Delete user
- `POST /admin/import` - Import CSV users

### **Utility Routes**
- `GET /sender` - Send emails to all receivers

## Development

### **Adding New Features**
1. **Models**: Add to `src/Models/` for data access
2. **Controllers**: Add to `src/Controllers/` for request handling
3. **Services**: Add to `src/Services/` for business logic
4. **Templates**: Add to `templates/` for views
5. **Routes**: Register in `public/index.php`

### **Dependency Injection**
All dependencies are managed through PHP-DI container in `public/index.php`

### **Configuration**
All settings centralized in `config/config.php`

## Deployment Process

### With Account Management
- Deploy the application
- Create users via `/admin` (requires login)
- Users login via `/login` using email + unique code
- Authenticated users send messages with their identity
- Give deadline to people to write words
- Close website by updating end date
- Visit `/sender` to send all emails

### Production Setup

For production:
1. Configure Mailjet API keys
2. Set up your email template in Mailjet
3. Update `EndDate` in configuration
4. Deploy to your web server
5. Ensure at least one admin user exists

## Migration from old version

If you have an existing installation:

1. Run the new `db.sql` to create the updated schema
2. The old files (`index.php`, `login.php`, etc.) are still compatible via `loader.php`
3. New routes are available through the Slim application

## Legacy Compatibility

The old files are still functional for backward compatibility:
- `index.php`, `login.php`, `admin.php` work through `loader.php`
- `sender.php` works with the new models
- Gradual migration possible

## 🎉 You're ready to spread the love with admin control!