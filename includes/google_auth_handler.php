<?php
/**
 * Google Authentication Handler
 * 
 * This file handles Google OAuth authentication and token management.
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/google_api_config.php';
require_once __DIR__ . '/config.php';

// Import Google Client
use Google\Client as GoogleClient;
use Google\Service\Oauth2;

class GoogleAuthHandler {
    private $client;
    
    /**
     * Constructor - initializes the Google API client
     */
    public function __construct() {
        $this->client = new GoogleClient();
        $this->client->setApplicationName(GOOGLE_APPLICATION_NAME);
        $this->client->setClientId(GOOGLE_CLIENT_ID);
        $this->client->setClientSecret(GOOGLE_CLIENT_SECRET);
        $this->client->setRedirectUri(GOOGLE_REDIRECT_URI);
        $this->client->setScopes(GOOGLE_SCOPES);
        $this->client->setAccessType('offline');
        $this->client->setPrompt('consent');
        $this->client->setIncludeGrantedScopes(true);
        
        // Log the redirect URI for debugging
        error_log("GoogleAuthHandler: Using redirect URI: " . GOOGLE_REDIRECT_URI);
    }
    
    /**
     * Get the Google API client
     * 
     * @param int|null $userId User ID to load token for (optional)
     * @return Google_Client The Google API client
     */
    public function getClient($userId = null) {
        // Use the existing client instance instead of creating a new one
        $client = $this->client;
        
        // Load token if user ID is provided
        if ($userId !== null) {
            $token = $this->loadToken($userId);
            if ($token) {
                $client->setAccessToken($token);
                
                // Refresh token if expired
                if ($client->isAccessTokenExpired() && isset($token['refresh_token'])) {
                    try {
                        $newToken = $client->fetchAccessTokenWithRefreshToken($token['refresh_token']);
                        if (!isset($newToken['error'])) {
                            $this->saveToken($userId, $newToken);
                        } else {
                            error_log('Error refreshing token: ' . $newToken['error']);
                        }
                    } catch (Exception $e) {
                        error_log('Exception refreshing token: ' . $e->getMessage());
                    }
                }
            }
        }
        
        return $client;
    }
    
    /**
     * Create an authorization URL for Google OAuth
     * 
     * @return string The authorization URL
     */
    public function createAuthUrl() {
        return $this->client->createAuthUrl();
    }
    
    /**
     * Exchange authorization code for access token
     * 
     * @param string $authCode Authorization code from Google
     * @return array Access token
     */
    public function fetchAccessTokenWithAuthCode($authCode) {
        try {
            error_log("GoogleAuthHandler: Exchanging authorization code for access token");
            
            // Exchange the authorization code for an access token
            $accessToken = $this->client->fetchAccessTokenWithAuthCode($authCode);
            
            // Check for errors
            if (isset($accessToken['error'])) {
                error_log("GoogleAuthHandler: Error exchanging authorization code: " . ($accessToken['error_description'] ?? $accessToken['error']));
                return $accessToken; // Return the error for handling by the caller
            }
            
            // Set the access token on the client
            $this->client->setAccessToken($accessToken);
            
            error_log("GoogleAuthHandler: Successfully exchanged authorization code for access token");
            return $accessToken;
        } catch (\Exception $e) {
            error_log("GoogleAuthHandler: Exception when exchanging authorization code: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Save the token to the database for a specific user
     * 
     * @param int $userId The user ID
     * @param array $token The token data to save
     * @return bool Whether the token was saved successfully
     */
    public function saveToken($userId, $token) {
        global $conn;
        
        // Check if token table exists, create if not
        $checkTable = "SHOW TABLES LIKE 'google_tokens'";
        $tableExists = $conn->query($checkTable);
        
        if ($tableExists->num_rows == 0) {
            // Create the table
            $createTable = "CREATE TABLE google_tokens (
                id INT(11) AUTO_INCREMENT PRIMARY KEY,
                user_id INT(11) NOT NULL,
                access_token TEXT NOT NULL,
                refresh_token TEXT,
                expires_in INT(11),
                created TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY user_id (user_id)
            )";
            
            if (!$conn->query($createTable)) {
                error_log("Error creating google_tokens table: " . $conn->error);
                return false;
            }
        }
        
        // Prepare token data
        $userId = (int)$userId;
        $accessToken = $conn->real_escape_string($token['access_token']);
        $refreshToken = isset($token['refresh_token']) ? $conn->real_escape_string($token['refresh_token']) : '';
        $expiresIn = isset($token['expires_in']) ? (int)$token['expires_in'] : 3600;
        
        // Check if token already exists for this user
        $checkToken = "SELECT id FROM google_tokens WHERE user_id = $userId";
        $result = $conn->query($checkToken);
        
        if ($result->num_rows > 0) {
            // Update existing token
            $updateToken = "UPDATE google_tokens SET 
                access_token = '$accessToken',
                expires_in = $expiresIn,
                created = NOW()";
            
            // Only update refresh token if we have a new one
            if (!empty($refreshToken)) {
                $updateToken .= ", refresh_token = '$refreshToken'";
            }
            
            $updateToken .= " WHERE user_id = $userId";
            
            return $conn->query($updateToken);
        } else {
            // Insert new token
            $insertToken = "INSERT INTO google_tokens (user_id, access_token, refresh_token, expires_in)
                VALUES ($userId, '$accessToken', '$refreshToken', $expiresIn)";
            
            return $conn->query($insertToken);
        }
    }
    
    /**
     * Load a token for a specific user
     * 
     * @param int $userId The user ID
     * @return array|null The token data or null if not found
     */
    public function loadToken($userId) {
        global $conn;
        
        $userId = (int)$userId;
        $query = "SELECT * FROM google_tokens WHERE user_id = $userId";
        $result = $conn->query($query);
        
        if ($result && $result->num_rows > 0) {
            $tokenData = $result->fetch_assoc();
            
            // Format token data properly
            $token = [
                'access_token' => $tokenData['access_token'],
                'expires_in' => (int)$tokenData['expires_in'],
                'created' => strtotime($tokenData['created'])
            ];
            
            // Add refresh token if available
            if (!empty($tokenData['refresh_token'])) {
                $token['refresh_token'] = $tokenData['refresh_token'];
            }
            
            // Check if token is expired
            $expiresAt = $token['created'] + $token['expires_in'];
            
            if (time() > $expiresAt && !empty($token['refresh_token'])) {
                // Token is expired, refresh it
                try {
                    $this->client->setAccessToken($token);
                    $newToken = $this->client->fetchAccessTokenWithRefreshToken($token['refresh_token']);
                    
                    // Preserve the refresh token if it's not in the new token
                    if (!isset($newToken['refresh_token']) && isset($token['refresh_token'])) {
                        $newToken['refresh_token'] = $token['refresh_token'];
                    }
                    
                    $this->saveToken($userId, $newToken);
                    return $newToken;
                } catch (Exception $e) {
                    error_log('Error refreshing token in loadToken: ' . $e->getMessage());
                    // Return the original token, let the caller handle expiration
                }
            }
            
            return $token;
        }
        
        return null;
    }
    
    /**
     * Check if the user has a valid token
     * 
     * @param int $userId User ID
     * @return bool True if the user has a valid token, false otherwise
     */
    public function hasValidToken($userId) {
        try {
            // Log the attempt to validate token
            error_log("GoogleAuthHandler: Checking if user ID $userId has a valid token");
            
            // Load token
            $token = $this->loadToken($userId);
            if (!$token) {
                error_log("GoogleAuthHandler: No token found for user ID $userId");
                return false;
            }
            
            // Set access token
            $this->client->setAccessToken($token);
            
            // Check if token is expired
            if ($this->client->isAccessTokenExpired()) {
                error_log("GoogleAuthHandler: Token expired for user ID $userId");
                
                // Try to refresh token if refresh token is available
                if (isset($token['refresh_token'])) {
                    error_log("GoogleAuthHandler: Attempting to refresh token for user ID $userId");
                    
                    try {
                        $newToken = $this->client->fetchAccessTokenWithRefreshToken($token['refresh_token']);
                        
                        // Check if refresh was successful
                        if (!isset($newToken['error'])) {
                            // Save new token
                            $this->saveToken($userId, $newToken);
                            error_log("GoogleAuthHandler: Successfully refreshed token for user ID $userId");
                            
                            // Make a test API call to verify the token works
                            try {
                                $oauth2 = new \Google\Service\Oauth2($this->client);
                                $userInfo = $oauth2->userinfo->get();
                                error_log("GoogleAuthHandler: Successfully verified token with API call");
                                return true;
                            } catch (\Exception $e) {
                                error_log("GoogleAuthHandler: Token verification failed: " . $e->getMessage());
                                return false;
                            }
                        } else {
                            error_log("GoogleAuthHandler: Failed to refresh token: " . $newToken['error']);
                            return false;
                        }
                    } catch (Exception $e) {
                        error_log("GoogleAuthHandler: Exception when refreshing token: " . $e->getMessage());
                        return false;
                    }
                } else {
                    error_log("GoogleAuthHandler: No refresh token available for user ID $userId");
                    return false;
                }
            }
            
            // Make a test API call to verify the token works
            try {
                $oauth2 = new \Google\Service\Oauth2($this->client);
                $userInfo = $oauth2->userinfo->get();
                error_log("GoogleAuthHandler: Successfully verified token with API call");
                // Token is valid
                error_log("GoogleAuthHandler: User ID $userId has a valid token");
                return true;
            } catch (\Exception $e) {
                error_log("GoogleAuthHandler: Token verification failed: " . $e->getMessage());
                return false;
            }
        } catch (Exception $e) {
            error_log("GoogleAuthHandler: Error checking token validity: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Revoke a user's token
     * 
     * @param int $userId The user ID
     * @return bool Whether the token was revoked successfully
     */
    public function revokeToken($userId) {
        global $conn;
        
        $token = $this->loadToken($userId);
        
        if ($token) {
            $this->client->setAccessToken($token);
            $this->client->revokeToken();
            
            $userId = (int)$userId;
            $query = "DELETE FROM google_tokens WHERE user_id = $userId";
            return $conn->query($query);
        }
        
        return false;
    }
}
