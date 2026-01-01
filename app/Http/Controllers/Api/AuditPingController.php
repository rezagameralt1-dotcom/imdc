<?php

namespace App\Http\Controllers\Api;

use App\Support\ApiResponse;
use Illuminate\Http\Request;

/**
 * Audit Ping Controller - Health check endpoint for Admin or Auditor role
 * 
 * Endpoint: GET /api/audit/ping
 * Middleware: auth:sanctum, role:Admin,Auditor
 * 
 * POLICY: Broad access - requires Admin OR Auditor role.
 * Both Admin and Auditor users can access this endpoint.
 */
class AuditPingController
{
    public function __invoke(Request $request)
    {
        return ApiResponse::ok([
            'pong' => true,
            'scope' => 'audit',
        ]);
    }
}
