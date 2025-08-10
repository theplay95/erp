# ERP System

## Overview
This is a comprehensive Enterprise Resource Planning (ERP) system web application. It integrates point-of-sale (POS), cash register management, inventory tracking, delivery management, and audit logging. The system is designed for small to medium businesses requiring unified solutions for sales, inventory, financial management, and delivery operations. Key capabilities include an online catalog system with a 4-step checkout process, real-time stock validation, automated delivery order generation, and a robust configuration management system for company settings, catalog appearance, and delivery logistics.

## Recent Changes (August 9, 2025)
- **Complete Error Elimination ACHIEVED**: Successfully eliminated ALL PHP warnings, errors, and notices across all system modules
- **Delivery System Fixed**: Resolved delivery_orders table missing issue and logAudit function parameter errors
- **Database Schema Completion**: Created missing delivery_orders table with proper structure and relationships
- **Catalog System Optimization**: Cleaned up duplicate JavaScript functions and optimized performance
- **End-to-End Testing Success**: Full order process from catalog to delivery working perfectly (tested with real orders)
- **API Testing Success**: All critical APIs (delivery fee, product management, checkout) functioning without errors
- **Comprehensive System Validation**: All 12+ modules now run error-free (0 PHP errors detected in final testing)
- **JavaScript Code Cleanup**: Removed duplicate functions from catalog.js and improved error handling
- **Database Query Consistency**: Standardized column names (stock_quantity) across all modules

## User Preferences
Preferred communication style: Simple, everyday language.

## System Architecture

### Frontend Architecture
- **Technology Stack**: Vanilla JavaScript with Bootstrap for responsive UI.
- **Design Pattern**: Component-based architecture with modular JavaScript.
- **Styling**: CSS custom properties for theming; responsive sidebar layout.
- **User Interface**: Multi-page web application with AJAX for dynamic content.

### Backend Architecture
- **Database**: MySQL 9.4.0 with UTF8MB4 support.
- **API Layer**: PHP-based RESTful API for various operations.
- **Session Management**: Traditional PHP session handling.
- **Architecture Pattern**: MVC-like structure with a separate data access layer.

### Data Storage Design
- **Primary Database**: MySQL with a relational schema.
- **Key Tables**: `users`, `cash_registers`, `cash_movements`, `delivery_orders`, `audit_logs`, `company_settings`, `catalog_settings`, `neighborhoods`, `database_config`.
- **Data Integrity**: Foreign key constraints and indexing.
- **Audit System**: Comprehensive logging of data changes, user actions, and IP addresses using JSON field storage for old/new values.

### Security Architecture
- **Authentication**: User-based system with session management (email-based login).
- **Audit Trail**: Complete logging of user actions and data changes.
- **Data Validation**: Input sanitization and validation on frontend and backend.
- **Access Control**: Role-based access control (e.g., admin-only for settings).

### POS System Architecture
- **Real-time Cart Management**: JavaScript-based shopping cart.
- **Product Search**: Dynamic filtering with category organization.
- **Transaction Processing**: Integrated with cash register operations.
- **Inventory Integration**: Real-time stock level updates during sales.

### Delivery Management Architecture
- **Order Management**: Tracking delivery orders linked to sales.
- **Status Workflow**: Lifecycle tracking (pending to delivered).
- **Personnel Management**: Delivery person assignment and tracking.
- **Route Optimization**: Estimated delivery times and address management.
- **Integration**: Seamless connection between sales and delivery.
- **Real-time Updates**: Live status updates and notifications.
- **Dynamic Pricing**: Neighborhood-based delivery fees with real-time API lookup.

### Configuration Management Architecture
- **Settings Modules**: Management of company info, catalog appearance, and delivery areas.
- **Database Configuration**: Interface for different database types (MySQL, SQLite, PostgreSQL).
- **Backup/Restoration**: System for data backup and restoration.
- **Integration**: Settings apply across catalog and delivery systems.

## External Dependencies

### Frontend Dependencies
- **Bootstrap**: UI framework.
- **Custom CSS Framework**: Theme-based styling.
- **Vanilla JavaScript**: Native browser APIs.

### Backend Dependencies
- **MySQL**: Primary database engine (version 9.4.0).
- **PHP**: Server-side scripting language.
- **Web Server**: Apache/Nginx compatible.

### Development Tools
- **Adminer**: Database administration tool (version 4.8.1).
- **JSON Support**: MySQL JSON data type for audit logs.

### API Integration Points
- Product management API.
- Cash register API.
- User authentication endpoints.
- Delivery management and status update endpoints.
- Audit logging system integration.
- `catalog/api/get_delivery_fee.php` for dynamic delivery fee lookup.