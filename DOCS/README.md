# ACF Quiz System - Comprehensive Documentation

## 📚 Documentation Overview

This documentation provides detailed technical information about the ACF Quiz System, a multi-step Hebrew quiz system with WooCommerce integration. The documentation is organized into several key areas:

### 📁 Documentation Structure

```
DOCS/
├── README.md                    # This overview document
├── ARCHITECTURE/               # System architecture documentation
│   ├── system-overview.md      # High-level system design
│   ├── database-schema.md      # Database structure and relationships
│   └── data-flow.md           # Data flow and processing logic
├── API/                        # API documentation
│   ├── ajax-endpoints.md      # WordPress AJAX endpoints
│   └── wordpress-hooks.md     # WordPress hooks and filters
├── COMPONENTS/                 # Component documentation
│   ├── php-classes.md         # PHP class documentation
│   ├── javascript-modules.md  # JavaScript module documentation
│   └── css-styling.md         # CSS and styling documentation
├── RISKS/                      # Risk analysis and solutions
│   ├── security-risks.md      # Security vulnerabilities and mitigations
│   ├── performance-risks.md   # Performance issues and optimizations
│   └── reliability-risks.md   # Reliability concerns and failover systems
├── TASKS/                      # Task and issue tracking
│   ├── bug-fixes.md           # Bug fixes and patches
│   ├── feature-requests.md    # Planned features and enhancements
│   └── testing.md             # Testing procedures and results
└── DEPLOYMENT/                 # Deployment and maintenance
    ├── installation.md        # Installation procedures
    ├── configuration.md       # Configuration options
    └── monitoring.md          # Monitoring and maintenance
```

## 🎯 Quick Start

### Prerequisites
- WordPress 5.0+
- Advanced Custom Fields PRO
- WooCommerce (for checkout integration)
- PHP 7.4+
- MySQL 5.7+

### Installation
1. Copy plugin files to `/wp-content/mu-plugins/`
2. Activate ACF PRO and WooCommerce
3. Configure quiz questions in ACF options
4. Test the complete quiz flow

### Basic Usage
```php
// Shortcode implementation
[acf_quiz]

// URL parameters for package selection
/your-quiz-page/?trial   // Trial package (99₪)
/your-quiz-page/?monthly // Monthly package (199₪)
/your-quiz-page/?yearly  // Yearly package (1999₪)
```

## 🏗️ System Architecture

### Core Components
- **Frontend**: Multi-step form with Hebrew RTL support
- **Backend**: WordPress AJAX processing and WooCommerce integration
- **Database**: Custom table for submission tracking
- **Admin Interface**: ACF-powered configuration and analytics

### Key Features
- 4-step progressive quiz form
- Lead capture with fallback systems
- Dynamic WooCommerce product creation
- Real-time validation and error handling
- Comprehensive admin dashboard

## 📊 Database Schema

The system uses a custom `wp_quiz_submissions` table to store:

| Field | Type | Description |
|-------|------|-------------|
| id | BIGINT | Primary key |
| first_name, last_name | VARCHAR | Personal information |
| user_phone, user_email | VARCHAR | Contact details |
| answers | LONGTEXT | JSON-encoded quiz responses |
| score | INT | Calculated quiz score (0-40) |
| passed | TINYINT | Pass/fail status |
| completed | TINYINT | Form completion status |

## 🔧 Configuration

### ACF Settings
Configure through **Quiz System → Quiz System**:
- Package pricing (Trial/Monthly/Yearly)
- Quiz questions and scoring
- Content blocks and legal notices
- Passing threshold (default: 21/40)

### WooCommerce Integration
Automatic product creation for:
- Trial Package (99₪)
- Monthly Package (199₪)
- Yearly Package (1999₪)

## 🚨 Critical Issues & Solutions

### 1. Lead Capture Reliability
**Problem**: AJAX nonce issues causing lead capture failures
**Solution**: Dual-capture system with fallback mechanisms

### 2. WooCommerce Redirection
**Problem**: Cart redirection failing due to hardcoded product IDs
**Solution**: Dynamic product ID management with fallback logic

### 3. Form Validation
**Problem**: Step progression blocked by validation issues
**Solution**: Progressive validation with visual feedback

## 📈 Performance Considerations

- AJAX optimization for minimal data transfer
- Database indexing on key fields
- Session management efficiency
- Asset loading optimization

## 🔒 Security Features

- WordPress nonce verification
- Data sanitization and escaping
- SQL injection protection
- XSS prevention
- Capability checks for admin functions

## 🧪 Testing Procedures

### End-to-End Testing
1. Complete quiz flow (Step 1 → 4)
2. Lead capture verification
3. WooCommerce checkout integration
4. Admin dashboard functionality

### Edge Cases
- Browser back/forward navigation
- Network connectivity issues
- Form data persistence
- Error state recovery

## 📞 Support & Maintenance

### Monitoring
- Regular database health checks
- Error log monitoring
- Performance metric tracking
- User experience analytics

### Backup Procedures
- Database backup before deployments
- Configuration export/import
- Emergency rollback procedures

---

## 📖 Documentation Sections

Choose the appropriate section based on your needs:

- **New Developers**: Start with `ARCHITECTURE/system-overview.md`
- **Troubleshooting**: Check `RISKS/` and `TASKS/bug-fixes.md`
- **Customization**: Review `COMPONENTS/` and `API/`
- **Deployment**: Use `DEPLOYMENT/` documentation

For technical support or feature requests, refer to the development team documentation in the respective sections.
