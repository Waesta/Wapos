# WAPOS UNIFIED POS SYSTEM - API CONTRACT
**Version:** 1.0  
**Date:** October 27, 2025  
**Base URL:** `/api/v1`  
**Authentication:** Bearer Token (JWT)

## 1. AUTHENTICATION ENDPOINTS

### POST /auth/login
**Description:** User authentication  
**Request:**
```json
{
  "username": "string",
  "password": "string",
  "pin_code": "string" // Optional for quick POS login
}
```
**Response:**
```json
{
  "success": true,
  "data": {
    "token": "jwt_token_string",
    "user": {
      "id": 1,
      "username": "admin",
      "first_name": "John",
      "last_name": "Doe",
      "role": "manager",
      "permissions": ["sales.*", "reports.*"],
      "location_id": 1
    },
    "expires_at": "2025-10-28T12:00:00Z"
  }
}
```

### POST /auth/logout
**Description:** User logout and token invalidation  
**Request:** Headers only (Authorization: Bearer token)  
**Response:**
```json
{
  "success": true,
  "message": "Logged out successfully"
}
```

### POST /auth/refresh
**Description:** Refresh JWT token  
**Response:** Same as login response with new token

## 2. PRODUCT MANAGEMENT

### GET /products
**Description:** Get products with pagination and filters  
**Query Parameters:**
- `page` (int): Page number (default: 1)
- `limit` (int): Items per page (default: 50)
- `category_id` (int): Filter by category
- `search` (string): Search in name/barcode
- `active_only` (bool): Show only active products

**Response:**
```json
{
  "success": true,
  "data": {
    "products": [
      {
        "id": 1,
        "product_code": "PROD001",
        "barcode": "1234567890",
        "product_name": "Coffee Latte",
        "selling_price": 4.50,
        "category": "Beverages",
        "stock_quantity": 100,
        "image_url": "/images/latte.jpg",
        "variants": []
      }
    ],
    "pagination": {
      "current_page": 1,
      "total_pages": 5,
      "total_items": 250
    }
  }
}
```

### POST /products
**Description:** Create new product  
**Request:**
```json
{
  "product_code": "PROD002",
  "barcode": "1234567891",
  "product_name": "Cappuccino",
  "description": "Rich espresso with steamed milk",
  "category_id": 1,
  "selling_price": 4.00,
  "cost_price": 1.50,
  "is_stockable": true,
  "reorder_level": 10
}
```

### PUT /products/{id}
**Description:** Update existing product  
**Request:** Same as POST with updated fields

### DELETE /products/{id}
**Description:** Soft delete product (set is_active = false)

### GET /products/{id}/stock
**Description:** Get stock levels for specific product across locations

## 3. SALES TRANSACTIONS

### POST /transactions
**Description:** Create new sales transaction  
**Request:**
```json
{
  "transaction_type": "sale",
  "module_type": "retail",
  "customer_id": 123,
  "table_id": null,
  "items": [
    {
      "product_id": 1,
      "variant_id": null,
      "quantity": 2,
      "unit_price": 4.50,
      "modifiers": {"size": "large", "extra_shot": true},
      "kitchen_notes": "Extra hot"
    }
  ],
  "payments": [
    {
      "payment_method_id": 1,
      "amount": 9.00,
      "reference_number": "CASH001"
    }
  ],
  "discount_amount": 0.00,
  "notes": "Customer order",
  "is_offline_transaction": false
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "transaction_id": 1001,
    "transaction_number": "TXN-2025-001001",
    "total_amount": 9.00,
    "payment_status": "paid",
    "receipt_url": "/receipts/TXN-2025-001001.pdf"
  }
}
```

### GET /transactions
**Description:** Get transactions with filters  
**Query Parameters:**
- `date_from` (date): Start date filter
- `date_to` (date): End date filter
- `cashier_id` (int): Filter by cashier
- `customer_id` (int): Filter by customer
- `status` (string): Filter by status

### GET /transactions/{id}
**Description:** Get specific transaction details

### POST /transactions/{id}/void
**Description:** Void a transaction  
**Request:**
```json
{
  "reason": "Customer request",
  "manager_authorization": "MGR123"
}
```

### POST /transactions/{id}/refund
**Description:** Process refund for transaction

## 4. CUSTOMER MANAGEMENT

### GET /customers
**Description:** Get customers with search and pagination  
**Query Parameters:**
- `search` (string): Search name, phone, email
- `page`, `limit`: Pagination

### POST /customers
**Description:** Create new customer  
**Request:**
```json
{
  "first_name": "John",
  "last_name": "Doe",
  "email": "john@example.com",
  "phone": "+1234567890",
  "customer_group_id": 1,
  "addresses": [
    {
      "address_type": "delivery",
      "address_line_1": "123 Main St",
      "city": "New York",
      "postal_code": "10001",
      "is_default": true
    }
  ]
}
```

### PUT /customers/{id}
**Description:** Update customer information

### GET /customers/{id}/transactions
**Description:** Get customer transaction history

## 5. RESTAURANT MANAGEMENT

### GET /tables
**Description:** Get all tables with current status  
**Response:**
```json
{
  "success": true,
  "data": {
    "areas": [
      {
        "id": 1,
        "area_name": "Main Dining",
        "tables": [
          {
            "id": 1,
            "table_number": "T01",
            "capacity": 4,
            "status": "occupied",
            "current_transaction_id": 1001,
            "position_x": 100,
            "position_y": 150
          }
        ]
      }
    ]
  }
}
```

### PUT /tables/{id}/status
**Description:** Update table status  
**Request:**
```json
{
  "status": "available",
  "notes": "Cleaned and ready"
}
```

### POST /tables/{id}/orders
**Description:** Create order for specific table  
**Request:** Similar to POST /transactions with table context

### GET /kitchen/orders
**Description:** Get pending kitchen orders  
**Response:**
```json
{
  "success": true,
  "data": {
    "orders": [
      {
        "transaction_id": 1001,
        "table_number": "T01",
        "order_time": "2025-10-27T14:30:00Z",
        "items": [
          {
            "item_name": "Grilled Chicken",
            "quantity": 1,
            "modifiers": {"cooking": "medium"},
            "kitchen_notes": "No salt",
            "status": "preparing"
          }
        ]
      }
    ]
  }
}
```

### PUT /kitchen/orders/{transaction_id}/items/{item_id}
**Description:** Update kitchen order item status  
**Request:**
```json
{
  "status": "ready"
}
```

## 6. ROOM MANAGEMENT

### GET /rooms
**Description:** Get all rooms with availability  
**Query Parameters:**
- `check_in` (date): Check availability from date
- `check_out` (date): Check availability to date

**Response:**
```json
{
  "success": true,
  "data": {
    "room_types": [
      {
        "id": 1,
        "type_name": "Standard Room",
        "base_rate": 120.00,
        "available_rooms": [
          {
            "id": 101,
            "room_number": "101",
            "status": "available"
          }
        ]
      }
    ]
  }
}
```

### POST /bookings
**Description:** Create room booking  
**Request:**
```json
{
  "room_id": 101,
  "customer_id": 123,
  "guest_name": "John Doe",
  "check_in_date": "2025-10-28",
  "check_out_date": "2025-10-30",
  "adults": 2,
  "children": 0,
  "room_rate": 120.00,
  "special_requests": "Late check-in"
}
```

### PUT /bookings/{id}/check-in
**Description:** Process guest check-in  
**Request:**
```json
{
  "actual_check_in_time": "2025-10-28T15:30:00Z",
  "id_verification": true,
  "deposit_amount": 50.00
}
```

### PUT /bookings/{id}/check-out
**Description:** Process guest check-out and generate final bill

## 7. DELIVERY MANAGEMENT

### GET /delivery/zones
**Description:** Get delivery zones with fees

### POST /delivery/orders
**Description:** Create delivery order  
**Request:**
```json
{
  "transaction_id": 1001,
  "delivery_address": "456 Oak St, New York, NY 10002",
  "delivery_phone": "+1234567890",
  "delivery_zone_id": 1,
  "estimated_delivery_time": "2025-10-27T16:00:00Z",
  "delivery_notes": "Ring doorbell twice"
}
```

### GET /delivery/orders
**Description:** Get delivery orders with status filters

### PUT /delivery/orders/{id}/assign
**Description:** Assign order to driver  
**Request:**
```json
{
  "driver_id": 5
}
```

### PUT /delivery/orders/{id}/status
**Description:** Update delivery status  
**Request:**
```json
{
  "status": "delivered",
  "delivered_at": "2025-10-27T15:45:00Z",
  "customer_rating": 5
}
```

## 8. INVENTORY MANAGEMENT

### GET /inventory/stock
**Description:** Get current stock levels  
**Query Parameters:**
- `location_id` (int): Filter by location
- `low_stock_only` (bool): Show only low stock items

### POST /inventory/adjustments
**Description:** Create stock adjustment  
**Request:**
```json
{
  "adjustment_type": "manual",
  "reason": "Physical count correction",
  "items": [
    {
      "product_id": 1,
      "variant_id": null,
      "quantity_change": -5,
      "new_cost": 1.75,
      "notes": "Damaged items removed"
    }
  ]
}
```

### POST /inventory/transfers
**Description:** Transfer stock between locations

## 9. REPORTING

### GET /reports/sales
**Description:** Sales reports with date range and grouping  
**Query Parameters:**
- `date_from`, `date_to`: Date range
- `group_by`: day|week|month|product|category|cashier

### GET /reports/inventory
**Description:** Inventory reports

### GET /reports/customers
**Description:** Customer analysis reports

### GET /reports/dashboard
**Description:** Dashboard KPIs  
**Response:**
```json
{
  "success": true,
  "data": {
    "today_sales": 1250.00,
    "today_transactions": 45,
    "top_selling_items": [],
    "low_stock_alerts": 3,
    "occupied_tables": 8,
    "occupied_rooms": 12,
    "pending_deliveries": 5
  }
}
```

## 10. OFFLINE SYNC

### POST /sync/upload
**Description:** Upload offline transactions for sync  
**Request:**
```json
{
  "device_id": "POS-001",
  "transactions": [
    {
      "local_id": "offline_001",
      "transaction_data": {},
      "created_at": "2025-10-27T14:00:00Z"
    }
  ],
  "stock_adjustments": [],
  "customer_updates": []
}
```

### GET /sync/download
**Description:** Download latest data for offline cache  
**Query Parameters:**
- `last_sync`: Last sync timestamp
- `tables`: Comma-separated list of tables to sync

### POST /sync/resolve-conflicts
**Description:** Resolve data conflicts during sync

## 11. SYSTEM MANAGEMENT

### GET /system/settings
**Description:** Get system configuration

### PUT /system/settings
**Description:** Update system settings

### GET /system/health
**Description:** System health check

### GET /system/audit-log
**Description:** Get audit trail with filters

## ERROR RESPONSES

All endpoints return consistent error format:
```json
{
  "success": false,
  "error": {
    "code": "VALIDATION_ERROR",
    "message": "Invalid input data",
    "details": {
      "field_name": ["Field is required"]
    }
  }
}
```

## COMMON HTTP STATUS CODES
- `200`: Success
- `201`: Created
- `400`: Bad Request
- `401`: Unauthorized
- `403`: Forbidden
- `404`: Not Found
- `422`: Validation Error
- `500`: Internal Server Error
