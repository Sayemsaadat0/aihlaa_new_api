# Twilio Integration Prompt for Laravel App

## Integration Overview
Integrate Twilio messaging (WhatsApp/SMS) into a Laravel application without webhooks. The integration should provide API endpoints to send messages directly, test connections, and handle notifications programmatically.

## Step-by-Step Implementation Process

### Step 1: Install Twilio PHP SDK
```bash
composer require twilio/sdk
```

### Step 2: Configure Environment Variables
Add to `.env` file:
```env
TWILIO_SID=your_twilio_account_sid
TWILIO_AUTH_TOKEN=your_twilio_auth_token
TWILIO_WHATSAPP_FROM=whatsapp:+14155238886
TWILIO_WHATSAPP_TO=whatsapp:+1234567890
```

### Step 3: Add Twilio Config to services.php
Add Twilio configuration array to `config/services.php` with keys: `sid`, `auth_token`, `whatsapp_from`, `whatsapp_to`.

### Step 4: Create TwilioService Class
Create `app/Services/TwilioService.php` that:
- Initializes Twilio Client in constructor using config values
- Handles graceful degradation if SDK/credentials missing
- Provides methods: `sendMessage($to, $message)`, `sendOrderNotification($orderData)`, `sendCustomMessage($message)`, `testConnection()`
- Formats phone numbers (add country code if needed)
- Logs all operations (success/error)
- Returns boolean for send operations, array for test

### Step 5: Create TwilioController
Create `app/Http/Controllers/API/TwilioController.php` with endpoints:
- `POST /api/twilio/send` - Send custom message or order notification
  - Accepts: `message` (string) OR `order_data` (array)
  - Validates input
  - Returns JSON with success, message, data
- `POST /api/twilio/test` - Test Twilio connection
  - Returns JSON with success, message, message_sid or error

### Step 6: Add API Routes
Add routes in `routes/api.php`:
- `POST /api/twilio/send` → TwilioController@send
- `POST /api/twilio/test` → TwilioController@test

### Step 7: Response Format
All endpoints return JSON:
- Success: `{success: true, message: "...", data: {...}}`
- Error: `{success: false, message: "...", error: "..."}`

## API Endpoints & Payloads

### 1. Send Message
**Endpoint:** `POST /api/twilio/send`

**Payload Option A (Custom Message):**
```json
{
  "message": "Hello, this is a test message"
}
```

**Payload Option B (Order Notification):**
```json
{
  "order_data": {
    // Your project-specific order data structure
    // The service will format this into a readable message
  }
}
```

**Success Response:**
```json
{
  "success": true,
  "message": "Custom message sent successfully",
  "data": {
    "timestamp": "2024-01-15T10:30:00.000000Z",
    "type": "custom_message"
  }
}
```

**Error Response:**
```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "message": ["The message field is required when order data is not present."]
  }
}
```

### 2. Test Connection
**Endpoint:** `POST /api/twilio/test`

**Payload:** None (empty body)

**Success Response:**
```json
{
  "success": true,
  "message": "WhatsApp connection test successful",
  "data": {
    "timestamp": "2024-01-15T10:30:00.000000Z",
    "message_sid": "SM1234567890abcdef"
  }
}
```

**Error Response:**
```json
{
  "success": false,
  "message": "WhatsApp connection test failed",
  "data": {
    "timestamp": "2024-01-15T10:30:00.000000Z",
    "error": "Invalid phone number format"
  }
}
```

## Implementation Notes
- No webhook handling required
- All operations are synchronous API calls
- Service class handles Twilio client initialization with error handling
- Phone numbers should be formatted with country code
- WhatsApp format: `whatsapp:+1234567890`
- SMS format: `+1234567890` (without whatsapp: prefix)
- Log all operations for debugging
- Return appropriate HTTP status codes (200, 422, 500)

