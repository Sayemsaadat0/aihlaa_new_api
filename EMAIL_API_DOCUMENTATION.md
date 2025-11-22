# Email API Documentation

## ⚠️ IMPORTANT: Email Configuration Issue

**Your emails are not being sent because `MAIL_MAILER` is set to `log` instead of `smtp`.**

### Fix Required:

Update your `.env` file:

```env
MAIL_MAILER=smtp
SMTP_HOST=premium216.web-hosting.com
SMTP_PORT=587
SMTP_USER=your-username
SMTP_PASS=your-password
SMTP_FROM=order@mamaspizzalondon.com
MAIL_ENCRYPTION=tls
```

After updating, clear config cache:
```bash
php artisan config:clear
```

---

## Email API System Overview

A comprehensive email sending system has been implemented following the PaymentConfirmationMail pattern.

### Components Created:

1. **EmailService** (`app/Services/EmailService.php`)
   - Handles mail class mapping and instantiation
   - Supports queuing for Mail classes implementing `ShouldQueue`
   - Comprehensive error handling and logging

2. **EmailController** (`app/Http/Controllers/Api/EmailController.php`)
   - REST API endpoints for sending emails
   - Validation and error handling

3. **Mail Classes:**
   - `OrderConfirmation` - Order confirmation emails
   - `WelcomeMail` - Welcome emails for new users
   - `NotificationMail` - General notifications

4. **Email Templates:**
   - `resources/views/emails/order-confirmation.blade.php`
   - `resources/views/emails/welcome.blade.php`
   - `resources/views/emails/notification.blade.php`

---

## API Endpoints

### 1. Send Email
**POST** `/api/v1/emails/send`

**Request Body:**
```json
{
  "to": "user@example.com",
  "type": "order_confirmation",
  "data": {
    "order": { /* Order object */ },
    "orderData": { /* Order data array */ }
  }
}
```

**Response (Success):**
```json
{
  "success": true,
  "status": 200,
  "message": "Email sent successfully",
  "data": {
    "to": "user@example.com",
    "subject": "Order Confirmation - Order #1",
    "type": "order_confirmation",
    "method": "sent"
  }
}
```

**Response (Error):**
```json
{
  "success": false,
  "status": 400,
  "message": "Invalid email type"
}
```

### 2. Get Available Email Types
**GET** `/api/v1/emails/types`

**Response:**
```json
{
  "success": true,
  "status": 200,
  "message": "Available email types retrieved successfully",
  "data": {
    "types": [
      "order_confirmation",
      "welcome",
      "notification"
    ],
    "total": 3
  }
}
```

---

## Available Email Types

### 1. `order_confirmation`
Sends order confirmation email.

**Required Data:**
```json
{
  "order": { /* Order model instance */ },
  "orderData": { /* Order response data array */ }
}
```

**Example:**
```json
{
  "to": "customer@example.com",
  "type": "order_confirmation",
  "data": {
    "order": { /* Order object */ },
    "orderData": {
      "id": 1,
      "cart_details": { /* ... */ },
      "payable_amount": 71.00
    }
  }
}
```

### 2. `welcome`
Sends welcome email to new users.

**Required Data:**
```json
{
  "user": { /* User object or array */ },
  "data": { /* Optional additional data */ }
}
```

**Example:**
```json
{
  "to": "newuser@example.com",
  "type": "welcome",
  "data": {
    "user": {
      "name": "John Doe",
      "email": "newuser@example.com"
    },
    "data": {
      "message": "Thank you for joining us!"
    }
  }
}
```

### 3. `notification`
Sends general notification email.

**Required Data:**
```json
{
  "title": "Notification Title",
  "message": "Notification message content",
  "data": { /* Optional additional data */ }
}
```

**Example:**
```json
{
  "to": "user@example.com",
  "type": "notification",
  "data": {
    "title": "Order Status Update",
    "message": "Your order #123 has been shipped!",
    "data": {
      "order_id": 123,
      "tracking_number": "TRACK123"
    }
  }
}
```

---

## Adding New Email Types

### Step 1: Create Mail Class
```bash
php artisan make:mail YourMailClass
```

### Step 2: Update Mail Class
```php
<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class YourMailClass extends Mailable
{
    public $data;

    public function __construct($data)
    {
        $this->data = $data;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            from: config('mail.from.address'),
            subject: 'Your Subject',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.your-template',
            with: ['data' => $this->data],
        );
    }
}
```

### Step 3: Add to EmailService
Update `app/Services/EmailService.php`:

```php
private const MAIL_CLASS_MAP = [
    'order_confirmation' => OrderConfirmation::class,
    'welcome' => WelcomeMail::class,
    'notification' => NotificationMail::class,
    'your_type' => YourMailClass::class, // Add here
];
```

### Step 4: Create Email Template
Create `resources/views/emails/your-template.blade.php`

---

## Queue Support

To enable queuing for an email type, make the Mail class implement `ShouldQueue`:

```php
use Illuminate\Contracts\Queue\ShouldQueue;

class YourMailClass extends Mailable implements ShouldQueue
{
    // ...
}
```

The EmailService will automatically detect this and use `Mail::queue()` instead of `Mail::send()`.

---

## Usage in Controllers

### After Order Creation
```php
use App\Services\EmailService;

// In your controller
$emailService = app(EmailService::class);
$result = $emailService->send($email, 'order_confirmation', [
    'order' => $order,
    'orderData' => $response,
]);

if (!$result['success']) {
    \Log::error('Email failed', ['error' => $result['error']]);
}
```

### After User Registration
```php
$emailService = app(EmailService::class);
$emailService->send($user->email, 'welcome', [
    'user' => $user,
    'data' => ['message' => 'Welcome to our platform!'],
]);
```

---

## Error Handling

- All email sending is wrapped in try-catch blocks
- Errors are logged but don't break main operations
- Email failures are logged with full context
- API returns appropriate error responses

---

## Logging

All email attempts are logged:
- **Success**: `Log::info('Email sent successfully', [...])`
- **Failure**: `Log::error('Failed to send email', [...])`

Check logs at: `storage/logs/laravel.log`

---

## Response Format

All responses follow the existing API format:

**Success:**
```json
{
  "success": true,
  "status": 200,
  "message": "Email sent successfully",
  "data": { /* ... */ }
}
```

**Error:**
```json
{
  "success": false,
  "status": 400,
  "message": "Error message"
}
```

---

## Notes

1. **MAIL_MAILER must be set to `smtp`** in `.env` for emails to be sent
2. Email sending failures don't break main operations (order creation, etc.)
3. All email types are validated before sending
4. Queue support is automatic if Mail class implements `ShouldQueue`
5. Email templates use Blade syntax with HTML/CSS styling

