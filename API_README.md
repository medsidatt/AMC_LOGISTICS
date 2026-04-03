# AMC API Documentation

This document provides information for frontend developers on how to access and use the AMC API.

## Base URL

The API is accessible at:
`http://your-domain.com/api/v1`

(Replace `your-domain.com` with the actual development or production domain, e.g., `localhost:8000` or `amc.test`)

## Authentication

The API uses Laravel Sanctum for authentication. Most endpoints require a valid Bearer Token.

### Login
To obtain a token, send a POST request to `/login` (or the specific auth endpoint defined in your routes) with valid credentials.

**Header:**
`Authorization: Bearer <your_token>`

## Swagger Documentation

Interactive API documentation is available via Swagger UI.

**URL:** `/api/documentation`

This UI allows you to:
- Explore all available endpoints.
- See request and response schemas.
- Test endpoints directly from the browser.

## Common Endpoints

### Currencies
- **GET** `/api/v1/currencies` - List all currencies (paginated).
- **POST** `/api/v1/currencies` - Create a new currency.
- **GET** `/api/v1/currencies/{id}` - Get details of a specific currency.
- **PUT** `/api/v1/currencies/{id}` - Update a currency.
- **DELETE** `/api/v1/currencies/{id}` - Delete a currency.

### Donors
- **GET** `/api/v1/donors` - List donors.
- **POST** `/api/v1/donors` - Create a donor.

### Beneficiaries
- **GET** `/api/v1/beneficiaries` - List beneficiaries.

*(Check the Swagger UI for the full list of endpoints and their details)*

## Error Handling

The API generally returns standard HTTP status codes:
- `200` OK - Request succeeded.
- `201` Created - Resource created successfully.
- `400` Bad Request - Invalid input.
- `401` Unauthorized - Missing or invalid authentication token.
- `403` Forbidden - You do not have permission to access this resource.
- `404` Not Found - The requested resource does not exist.
- `422` Unprocessable Entity - Validation error (check `errors` field in response).
- `500` Internal Server Error - Something went wrong on the server.

## Pagination

List endpoints typically return paginated results. Look for `meta` and `links` in the response to handle pagination.

## Data Format

All requests and responses are in JSON format.
**Headers:**
- `Accept: application/json`
- `Content-Type: application/json`
