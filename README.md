# Laravel Xero API Integration

A Laravel 11 project that provides RESTful API endpoints for managing Xero accounts with OAuth 2.0 authentication and multi-tenant support.

## Features

- OAuth 2.0 authentication with Xero
- Multi-tenant support for multiple Xero organizations
- Full CRUD operations for Xero accounts
- Automatic token refresh
- RESTful API design
- Session-based token storage

## Prerequisites

- PHP 8.2+
- Composer
- Xero Developer Account with Client ID and Client Secret

## Installation

1. Clone the repository
2. Install dependencies:
   ```bash
   composer install
   ```

3. Copy environment file:
   ```bash
   cp .env.example .env
   ```

4. Generate application key:
   ```bash
   php artisan key:generate
   ```

5. Configure your Xero credentials in `.env`:
   ```env
   XERO_CLIENT_ID=your_xero_client_id
   XERO_CLIENT_SECRET=your_xero_client_secret
   XERO_REDIRECT_URI=http://localhost:8000/oauth/callback
   XERO_SCOPE="accounting.transactions accounting.settings accounting.contacts accounting.reports.read offline_access"
   ```

6. Start the development server:
   ```bash
   php artisan serve
   ```

## API Endpoints

### Authentication

- `GET /api/oauth/connect` - Get Xero authorization URL
- `GET /api/oauth/callback` - Handle OAuth callback
- `GET /api/oauth/tenants` - Get available tenants
- `POST /api/oauth/logout` - Logout from Xero

### Accounts Management

All account endpoints require:
- `Xero-Tenant-ID` header with the tenant ID
- Valid Xero authentication session

- `GET /api/accounts` - List all accounts
- `GET /api/accounts/{id}` - Get specific account
- `POST /api/accounts` - Create new account
- `PUT /api/accounts/{id}` - Update account
- `DELETE /api/accounts/{id}` - Delete account

## Usage Example

### 1. Authenticate with Xero

```bash
# Get authorization URL
curl -X GET http://localhost:8000/api/oauth/connect

# Response:
{
    "success": true,
    "auth_url": "https://login.xero.com/identity/connect/authorize?response_type=code&client_id=your_client_id&redirect_uri=..."
}
```

### 2. Handle OAuth Callback

After user authorizes, Xero will redirect to your callback URL with a code parameter.

### 3. Get Available Tenants

```bash
curl -X GET http://localhost:8000/api/oauth/tenants

# Response:
{
    "success": true,
    "tenants": [
        {
            "tenantId": "tenant-uuid",
            "tenantName": "My Company",
            "tenantType": "ORGANISATION"
        }
    ]
}
```

### 4. Create Account

```bash
curl -X POST http://localhost:8000/api/accounts \
  -H "Content-Type: application/json" \
  -H "Xero-Tenant-ID: tenant-uuid" \
  -d '{
    "name": "Test Bank Account",
    "code": "090",
    "type": "BANK",
    "description": "Test bank account"
  }'

# Response:
{
    "success": true,
    "message": "Account created successfully",
    "data": {
        "accountID": "account-uuid",
        "name": "Test Bank Account",
        "code": "090",
        "type": "BANK",
        "status": "ACTIVE"
    }
}
```

### 5. List Accounts

```bash
curl -X GET http://localhost:8000/api/accounts \
  -H "Xero-Tenant-ID: tenant-uuid"

# Response:
{
    "success": true,
    "data": [
        {
            "accountID": "account-uuid",
            "name": "Test Bank Account",
            "code": "090",
            "type": "BANK",
            "status": "ACTIVE"
        }
    ]
}
```

## Account Types

Supported account types:
- `BANK` - Bank accounts
- `CURRENT` - Current assets
- `CURRLIAB` - Current liabilities
- `DEPRECIATN` - Depreciation
- `EQUITY` - Equity accounts
- `EXPENSE` - Expense accounts
- `INVENTORY` - Inventory
- `LIABILITY` - Liability accounts
- `NONCURRENT` - Non-current assets
- `OTHERINCOME` - Other income
- `OVERHEADS` - Overheads
- `PAYGLIABILITY` - PAYG liability
- `PREPAYMENT` - Prepayments
- `REVENUE` - Revenue accounts
- `SALES` - Sales accounts
- `TAX` - Tax accounts
- `TERMLIAB` - Term liabilities

## Error Handling

All API endpoints return consistent error responses:

```json
{
    "success": false,
    "message": "Error description"
}
```

Common HTTP status codes:
- `200` - Success
- `201` - Created
- `400` - Bad Request
- `401` - Unauthorized
- `403` - Forbidden
- `500` - Internal Server Error

## Security Notes

- Tokens are stored in session (for development)
- In production, consider using encrypted storage
- Always validate tenant access before operations
- Implement proper rate limiting
- Use HTTPS in production

## Development

This project uses:
- Laravel 11
- calcinai/xero-php SDK
- Session-based authentication
- RESTful API design

## License

This project is open-sourced software licensed under the MIT license.
