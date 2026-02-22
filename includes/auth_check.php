<?php
// Simple authentication check file
// This file is included by API endpoints to ensure the user is authenticated

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// For API endpoints, we'll allow either:
// 1. A valid session with user_id
// 2. A test_user_id parameter for testing purposes

// The actual authentication check is done in the API files themselves
// This file just ensures the session is started
