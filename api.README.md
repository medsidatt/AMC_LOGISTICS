```markdown
# AMC API — Frontend Developer Reference

This document serves as a concise reference for all API endpoints available to the frontend application. It includes authentication details and data endpoints.

**Base URL (Local Development):** `http://127.0.0.1:8000/api/v1`  
**Production:** Replace with the deployed server URL provided by the backend team.

All requests and responses utilize JSON format.

---

## Authentication Endpoints

### POST /login
**Public** — No authentication required.

**Purpose:** Authenticate a user and establish a session via Laravel Sanctum (cookie-based for SPAs).

**Request Body (JSON):**
```json
{
  "email": "user@example.com",
  "password": "password"
}
```

**Success Response (200):**  
Session cookie is set automatically. The response may include user data.

**Error Response (401):**  
Invalid credentials.

### POST /logout
**Authenticated** — Requires a valid Sanctum session.

**Purpose:** Log out the current user and invalidate the session.

**Success Response (200):**  
User successfully logged out.

---

## Data Endpoints (Authenticated)

All endpoints listed below require a valid authenticated session (established via `/login`).

### RESTful Resources
Standard CRUD operations are provided. List endpoints return paginated results.

| Resource            | List (GET)                  | Create (POST)              | Show (GET)                  | Update (PUT)                | Delete (DELETE)             |
|---------------------|-----------------------------|----------------------------|-----------------------------|-----------------------------|-----------------------------|
| Donors              | `/donors`                   | `/donors`                  | `/donors/{id}`              | `/donors/{id}`              | `/donors/{id}`              |
| Beneficiaries       | `/beneficiaries`            | `/beneficiaries`           | `/beneficiaries/{id}`       | `/beneficiaries/{id}`       | `/beneficiaries/{id}`       |
| Banks               | `/banks`                    | `/banks`                   | `/banks/{id}`               | `/banks/{id}`               | `/banks/{id}`               |
| Bank Accounts       | `/bank-accounts`            | `/bank-accounts`           | `/bank-accounts/{id}`       | `/bank-accounts/{id}`       | `/bank-accounts/{id}`       |
| Currencies          | `/currencies`               | `/currencies`              | `/currencies/{id}`          | `/currencies/{id}`          | `/currencies/{id}`          |
| Forms               | `/forms`                    | `/forms`                   | `/forms/{id}`               | `/forms/{id}`               | `/forms/{id}`               |

**Pagination Note:**  
List endpoints return a paginated structure containing `data` (array of items), `links` (pagination URLs), and `meta` (pagination metadata). Navigate pages using `?page=2` or the provided `links.next`.

### Additional Endpoints

- **GET /transport_trackings**  
  Retrieves a complete list of transport trackings, including related data (truck, driver, provider, transporter).

#### Dropdown / Select Option Endpoints (Lightweight Responses)
These endpoints return simple arrays of objects suitable for populating select inputs.

- **GET /trucks**  
  Response: `[{ "id": number, "matricule": string }, ...]`

- **GET /drivers**  
  Response: `[{ "id": number, "name": string }, ...]`

- **GET /providers**  
  Response: `[{ "id": number, "name": string }, ...]`

- **GET /transporters**  
  Response: `[{ "id": number, "name": string }, ...]`

---

## Important Notes for Frontend Integration
- Authentication is cookie-based (Laravel Sanctum SPA mode). Ensure requests include credentials (`withCredentials: true` in Axios or fetch).
- Fetch the CSRF cookie from `/sanctum/csrf-cookie` before performing state-changing requests (POST, PUT, DELETE) if required by your setup.
- Session cookies are sent automatically for authenticated endpoints when credentials are included.
- Common error responses:
    - **401 Unauthorized:** Session expired or invalid — redirect to login.
    - **422 Unprocessable Entity:** Validation errors (details in response).
    - **404 Not Found:** Resource does not exist.

This reference encompasses all endpoints accessible to the frontend application as of December 23, 2025.
```
