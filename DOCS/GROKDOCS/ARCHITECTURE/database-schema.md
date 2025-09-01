# ACF Quiz System - Database Schema & Queries

## 📊 Database Architecture

The ACF Quiz System uses a custom WordPress table `wp_quiz_submissions` to store all quiz-related data. This document provides comprehensive information about the database structure, relationships, and query patterns.

## 🗄️ Table Schema

### Primary Table: `wp_quiz_submissions`

```sql
CREATE TABLE `wp_quiz_submissions` (
    `id` mediumint(9) NOT NULL AUTO_INCREMENT,

    -- Step 1: Basic Personal Information
    `first_name` varchar(100) NOT NULL,
    `last_name` varchar(100) NOT NULL,
    `user_phone` varchar(20) NOT NULL,
    `user_email` varchar(100) NOT NULL,

    -- Step 2: Detailed Personal Information
    `id_number` varchar(20) DEFAULT '',
    `gender` varchar(10) DEFAULT '',
    `birth_date` date DEFAULT NULL,
    `citizenship` varchar(50) DEFAULT 'ישראלית',
    `address` text DEFAULT '',
    `marital_status` varchar(20) DEFAULT '',
    `employment_status` varchar(50) DEFAULT '',
    `education` varchar(50) DEFAULT '',
    `profession` varchar(100) DEFAULT '',

    -- Legacy Fields (for compatibility)
    `user_name` varchar(100) DEFAULT '',
    `contact_consent` tinyint(1) DEFAULT 0,

    -- Package & Pricing Information
    `package_name` varchar(100) DEFAULT '',
    `package_price` decimal(10,2) DEFAULT 0.00,
    `package_source` varchar(100) DEFAULT '',

    -- Quiz Results & Scoring
    `answers` longtext NOT NULL,
    `score` int(11) NOT NULL,
    `max_score` int(11) NOT NULL DEFAULT 40,
    `score_percentage` decimal(5,2) NOT NULL,
    `passed` tinyint(1) NOT NULL,

    -- Form Completion Tracking
    `current_step` int(1) DEFAULT 1,
    `completed` tinyint(1) DEFAULT 0,
    `declaration_accepted` tinyint(1) DEFAULT 0,

    -- Metadata & Tracking
    `submission_time` datetime DEFAULT CURRENT_TIMESTAMP,
    `ip_address` varchar(45) DEFAULT '',
    `user_agent` text DEFAULT '',

    PRIMARY KEY (`id`),
    KEY `passed` (`passed`),
    KEY `completed` (`completed`),
    KEY `submission_time` (`submission_time`),
    KEY `user_email` (`user_email`),
    KEY `package_name` (`package_name`),
    KEY `score` (`score`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

## 🔑 Indexes & Performance

### Database Indexes

```sql
-- Primary Key
PRIMARY KEY (`id`),

-- Performance Indexes
KEY `passed` (`passed`),
KEY `completed` (`completed`),
KEY `submission_time` (`submission_time`),
KEY `user_email` (`user_email`),

-- Query Optimization Indexes
KEY `package_name` (`package_name`),
KEY `score` (`score`),
KEY `idx_email_completed` (`user_email`, `completed`),
KEY `idx_time_passed` (`submission_time`, `passed`),
KEY `idx_package_source` (`package_name`, `package_source`)
```

### Index Usage Patterns

| Query Type | Index Used | Purpose |
|------------|------------|---------|
| Admin Dashboard | `completed`, `passed` | Filter submissions |
| User Lookup | `user_email` | Find user submissions |
| Analytics | `submission_time` | Time-based reports |
| Package Reports | `package_name` | Package performance |

## 📝 Data Types & Constraints

### Field Specifications

#### Personal Information Fields
- **Names**: `varchar(100)` - Supports Hebrew characters, allows up to 100 chars
- **Phone**: `varchar(20)` - International format support
- **Email**: `varchar(100)` - Standard email length
- **ID Number**: `varchar(20)` - Israeli ID format support

#### Date & Selection Fields
- **Birth Date**: `date` - MySQL date format (YYYY-MM-DD)
- **Gender**: `varchar(10)` - Single word values
- **Marital Status**: `varchar(20)` - Hebrew terms support
- **Education**: `varchar(50)` - Degree names in Hebrew

#### Financial & Package Data
- **Package Price**: `decimal(10,2)` - Supports prices up to 99,999,999.99
- **Package Name**: `varchar(100)` - Marketing package names

#### Quiz Data
- **Answers**: `longtext` - JSON-encoded quiz responses
- **Score**: `int(11)` - Range: 0-40 (10 questions × 4 points max)
- **Score Percentage**: `decimal(5,2)` - Calculated percentage (0.00-100.00)

#### Status Fields
- **Passed**: `tinyint(1)` - 0 = Failed, 1 = Passed
- **Completed**: `tinyint(1)` - 0 = Lead/Incomplete, 1 = Full Submission
- **Current Step**: `int(1)` - Values: 1-4 (form steps)

## 🔄 Data Relationships

### Record States

#### Lead Records (`completed = 0`)
- Contains Step 1 data only
- Created when user proceeds from Step 1 to Step 2
- May be updated as user progresses through form
- Converted to completed submission when quiz finishes

#### Complete Records (`completed = 1`)
- Contains all form data (Steps 1-4)
- Includes quiz answers and scoring
- Final state - no further updates

### Data Flow States

```sql
-- Lead Creation (Step 1 → Step 2)
INSERT INTO wp_quiz_submissions
(first_name, last_name, user_phone, user_email, package_name, completed)
VALUES (?, ?, ?, ?, ?, 0)

-- Lead Update (Step 2 → Step 3)
UPDATE wp_quiz_submissions
SET id_number = ?, gender = ?, birth_date = ?
WHERE id = ? AND completed = 0

-- Final Submission (Step 4 completion)
UPDATE wp_quiz_submissions
SET answers = ?, score = ?, passed = ?, completed = 1
WHERE id = ?
```

## 📊 Common Queries

### Dashboard Statistics

```sql
-- Total Statistics
SELECT
    COUNT(*) as total_submissions,
    SUM(CASE WHEN completed = 1 THEN 1 ELSE 0 END) as completed_quizzes,
    SUM(CASE WHEN passed = 1 THEN 1 ELSE 0 END) as passed_quizzes,
    SUM(CASE WHEN completed = 0 THEN 1 ELSE 0 END) as leads_only,
    ROUND(AVG(CASE WHEN completed = 1 THEN score END), 1) as avg_score,
    ROUND(SUM(CASE WHEN passed = 1 THEN 1 ELSE 0 END) / SUM(CASE WHEN completed = 1 THEN 1 ELSE 0 END) * 100, 1) as pass_rate
FROM wp_quiz_submissions;

-- Recent Activity (Last 7 days)
SELECT
    DATE(submission_time) as date,
    COUNT(*) as daily_submissions,
    SUM(completed) as completed_quizzes,
    SUM(passed) as passed_quizzes,
    ROUND(AVG(CASE WHEN completed = 1 THEN score END), 1) as avg_score
FROM wp_quiz_submissions
WHERE submission_time >= DATE_SUB(NOW(), INTERVAL 7 DAY)
GROUP BY DATE(submission_time)
ORDER BY date DESC;
```

### User Management

```sql
-- Find user submissions
SELECT * FROM wp_quiz_submissions
WHERE user_email = ?
ORDER BY submission_time DESC;

-- Check for duplicate emails
SELECT user_email, COUNT(*) as submission_count
FROM wp_quiz_submissions
WHERE user_email != ''
GROUP BY user_email
HAVING submission_count > 1
ORDER BY submission_count DESC;
```

### Package Analytics

```sql
-- Package performance
SELECT
    package_name,
    COUNT(*) as total_submissions,
    SUM(completed) as completed_quizzes,
    SUM(passed) as passed_quizzes,
    ROUND(SUM(passed) / SUM(completed) * 100, 1) as conversion_rate,
    ROUND(AVG(CASE WHEN completed = 1 THEN score END), 1) as avg_score
FROM wp_quiz_submissions
WHERE package_name != ''
GROUP BY package_name
ORDER BY total_submissions DESC;

-- Revenue tracking
SELECT
    package_name,
    SUM(package_price) as total_revenue,
    COUNT(*) as sales_count,
    ROUND(AVG(package_price), 2) as avg_price
FROM wp_quiz_submissions
WHERE passed = 1 AND package_price > 0
GROUP BY package_name;
```

### Data Quality Queries

```sql
-- Check for incomplete records
SELECT
    COUNT(*) as incomplete_records,
    SUM(CASE WHEN first_name = '' THEN 1 ELSE 0 END) as missing_first_name,
    SUM(CASE WHEN last_name = '' THEN 1 ELSE 0 END) as missing_last_name,
    SUM(CASE WHEN user_email = '' THEN 1 ELSE 0 END) as missing_email,
    SUM(CASE WHEN user_phone = '' THEN 1 ELSE 0 END) as missing_phone
FROM wp_quiz_submissions
WHERE completed = 0;

-- Validate email formats
SELECT id, user_email
FROM wp_quiz_submissions
WHERE user_email NOT REGEXP '^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}$'
  AND user_email != '';
```

## 🛠️ Maintenance Queries

### Data Cleanup

```sql
-- Remove old incomplete leads (older than 30 days)
DELETE FROM wp_quiz_submissions
WHERE completed = 0
  AND submission_time < DATE_SUB(NOW(), INTERVAL 30 DAY);

-- Archive old completed submissions (older than 1 year)
CREATE TABLE wp_quiz_submissions_archive AS
SELECT * FROM wp_quiz_submissions
WHERE completed = 1
  AND submission_time < DATE_SUB(NOW(), INTERVAL 1 YEAR);

DELETE FROM wp_quiz_submissions
WHERE completed = 1
  AND submission_time < DATE_SUB(NOW(), INTERVAL 1 YEAR);
```

### Performance Optimization

```sql
-- Add missing indexes
ALTER TABLE wp_quiz_submissions ADD INDEX idx_package_completed (package_name, completed);
ALTER TABLE wp_quiz_submissions ADD INDEX idx_time_score (submission_time, score);
ALTER TABLE wp_quiz_submissions ADD INDEX idx_email_time (user_email, submission_time);

-- Analyze table for optimization
ANALYZE TABLE wp_quiz_submissions;

-- Check for table fragmentation
SHOW TABLE STATUS LIKE 'wp_quiz_submissions';
```

### Backup & Recovery

```sql
-- Create backup
mysqldump wp_database wp_quiz_submissions > quiz_submissions_backup.sql;

-- Restore from backup
mysql wp_database < quiz_submissions_backup.sql;

-- Verify backup integrity
SELECT COUNT(*) as record_count, MAX(submission_time) as latest_submission
FROM wp_quiz_submissions;
```

## 🔍 Query Performance Analysis

### Slow Query Identification

```sql
-- Enable slow query log
SET GLOBAL slow_query_log = 'ON';
SET GLOBAL long_query_time = 1; -- Log queries taking > 1 second

-- Check slow queries
SELECT * FROM mysql.slow_log
WHERE sql_text LIKE '%wp_quiz_submissions%'
ORDER BY query_time DESC;

-- Query execution plan analysis
EXPLAIN SELECT * FROM wp_quiz_submissions
WHERE user_email = 'test@example.com'
ORDER BY submission_time DESC;
```

### Index Optimization

```sql
-- Show index usage
SELECT
    TABLE_NAME,
    INDEX_NAME,
    CARDINALITY,
    PAGES,
    FILTER_CONDITION
FROM INFORMATION_SCHEMA.STATISTICS
WHERE TABLE_NAME = 'wp_quiz_submissions'
ORDER BY SEQ_IN_INDEX;

-- Rebuild indexes if needed
ALTER TABLE wp_quiz_submissions ENGINE = InnoDB;
```

## 📈 Analytics Queries

### Conversion Funnel Analysis

```sql
-- Step completion rates
SELECT
    'Step 1' as step,
    COUNT(*) as started,
    COUNT(*) as completed_step
FROM wp_quiz_submissions
WHERE current_step >= 1

UNION ALL

SELECT
    'Step 2' as step,
    COUNT(*) as started,
    SUM(CASE WHEN current_step >= 2 OR completed = 1 THEN 1 ELSE 0 END) as completed_step
FROM wp_quiz_submissions
WHERE current_step >= 1

UNION ALL

SELECT
    'Step 3' as step,
    COUNT(*) as started,
    SUM(CASE WHEN current_step >= 3 OR completed = 1 THEN 1 ELSE 0 END) as completed_step
FROM wp_quiz_submissions
WHERE current_step >= 1

UNION ALL

SELECT
    'Step 4' as step,
    COUNT(*) as started,
    SUM(CASE WHEN completed = 1 THEN 1 ELSE 0 END) as completed_step
FROM wp_quiz_submissions
WHERE current_step >= 1;
```

### Time-based Analytics

```sql
-- Hourly submission patterns
SELECT
    HOUR(submission_time) as hour,
    COUNT(*) as submissions,
    ROUND(AVG(score), 1) as avg_score,
    SUM(passed) as passed_count
FROM wp_quiz_submissions
WHERE completed = 1
  AND submission_time >= DATE_SUB(NOW(), INTERVAL 30 DAY)
GROUP BY HOUR(submission_time)
ORDER BY hour;

-- Weekly trends
SELECT
    YEARWEEK(submission_time) as week,
    COUNT(*) as submissions,
    SUM(completed) as completed,
    SUM(passed) as passed,
    ROUND(SUM(passed) / SUM(completed) * 100, 1) as conversion_rate
FROM wp_quiz_submissions
WHERE submission_time >= DATE_SUB(NOW(), INTERVAL 12 WEEK)
GROUP BY YEARWEEK(submission_time)
ORDER BY week DESC;
```

## 🔧 Data Migration Scripts

### Schema Updates

```sql
-- Add new fields safely
ALTER TABLE wp_quiz_submissions
ADD COLUMN new_field_name VARCHAR(100) DEFAULT '' AFTER existing_field;

-- Update existing records
UPDATE wp_quiz_submissions
SET new_field_name = 'default_value'
WHERE new_field_name = '';

-- Add constraints
ALTER TABLE wp_quiz_submissions
ADD CONSTRAINT chk_score CHECK (score >= 0 AND score <= 40);
```

### Data Transformation

```sql
-- Convert legacy user_name to first_name/last_name
UPDATE wp_quiz_submissions
SET first_name = SUBSTRING_INDEX(user_name, ' ', 1),
    last_name = SUBSTRING(user_name, LOCATE(' ', user_name) + 1)
WHERE first_name = '' AND user_name != '';

-- Normalize phone numbers
UPDATE wp_quiz_submissions
SET user_phone = REPLACE(REPLACE(REPLACE(user_phone, '-', ''), ' ', ''), '(', '') ,
    user_phone = REPLACE(REPLACE(user_phone, ')', ''), '+', '')
WHERE user_phone != '';
```

## 📋 Data Validation Rules

### Business Logic Constraints

| Field | Validation Rule | Error Message |
|-------|----------------|----------------|
| `first_name` | Required, 2-50 chars | שם פרטי נדרש |
| `last_name` | Required, 2-50 chars | שם משפחה נדרש |
| `user_email` | Valid email format | כתובת אימייל לא תקינה |
| `user_phone` | 9-15 digits | מספר טלפון לא תקין |
| `score` | 0-40 range | ציון לא תקין |
| `passed` | Based on score ≥ 21 | סטטוס מעבר לא תקין |

### Data Integrity Checks

```sql
-- Check score consistency
SELECT id, score, passed
FROM wp_quiz_submissions
WHERE (score >= 21 AND passed = 0)
   OR (score < 21 AND passed = 1);

-- Validate email uniqueness (optional business rule)
SELECT user_email, COUNT(*) as duplicates
FROM wp_quiz_submissions
WHERE user_email != ''
GROUP BY user_email
HAVING duplicates > 1;

-- Check for data anomalies
SELECT id, submission_time, current_step, completed
FROM wp_quiz_submissions
WHERE (completed = 1 AND current_step < 4)
   OR (completed = 0 AND current_step > 4);
```

This comprehensive database documentation provides everything needed to understand, maintain, and optimize the ACF Quiz System's data layer.
