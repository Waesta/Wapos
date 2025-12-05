<?php
/**
 * WAPOS - Guest Authentication Service
 * Secure guest portal access with encrypted credentials
 * 
 * @copyright Waesta Enterprises U Ltd. All rights reserved.
 */

namespace App\Services;

use PDO;
use Exception;

class GuestAuthService
{
    private PDO $db;
    private string $encryptionKey;
    private string $cipher = 'aes-256-gcm';
    
    // Session timeout in seconds (24 hours default)
    private int $sessionTimeout = 86400;
    
    // Max failed login attempts before lockout
    private int $maxFailedAttempts = 5;
    
    // Lockout duration in seconds (30 minutes)
    private int $lockoutDuration = 1800;

    public function __construct(PDO $db)
    {
        $this->db = $db;
        $this->encryptionKey = $this->getEncryptionKey();
        $this->ensureTablesExist();
    }

    /**
     * Get or generate encryption key
     */
    private function getEncryptionKey(): string
    {
        $keyFile = dirname(__DIR__, 2) . '/storage/.guest_key';
        $storageDir = dirname($keyFile);
        
        if (!is_dir($storageDir)) {
            mkdir($storageDir, 0700, true);
        }
        
        if (file_exists($keyFile)) {
            return file_get_contents($keyFile);
        }
        
        // Generate a secure 256-bit key
        $key = bin2hex(random_bytes(32));
        file_put_contents($keyFile, $key);
        chmod($keyFile, 0600);
        
        return $key;
    }

    /**
     * Create guest credentials on check-in
     */
    public function createGuestAccess(array $bookingData): array
    {
        $guestName = trim($bookingData['guest_name'] ?? '');
        $roomNumber = trim($bookingData['room_number'] ?? '');
        $checkInDate = $bookingData['check_in_date'] ?? date('Y-m-d');
        $checkOutDate = $bookingData['check_out_date'] ?? date('Y-m-d', strtotime('+1 day'));
        $email = trim($bookingData['email'] ?? '');
        $phone = trim($bookingData['phone'] ?? '');
        $bookingId = $bookingData['booking_id'] ?? null;
        
        if (empty($guestName) || empty($roomNumber)) {
            throw new Exception('Guest name and room number are required.');
        }
        
        // Generate secure credentials
        $username = $this->generateUsername($roomNumber, $checkInDate);
        $password = $this->generateSecurePassword();
        $passwordHash = password_hash($password, PASSWORD_ARGON2ID, [
            'memory_cost' => 65536,
            'time_cost' => 4,
            'threads' => 3
        ]);
        
        // Generate unique access token for direct link access
        $accessToken = $this->generateAccessToken();
        $accessTokenHash = hash('sha256', $accessToken);
        
        // Calculate expiry (check-out date + 1 day buffer)
        $expiresAt = date('Y-m-d 23:59:59', strtotime($checkOutDate . ' +1 day'));
        
        // Encrypt sensitive data
        $encryptedEmail = $email ? $this->encrypt($email) : null;
        $encryptedPhone = $phone ? $this->encrypt($phone) : null;
        
        // Insert guest access record
        $stmt = $this->db->prepare("
            INSERT INTO guest_portal_access 
            (username, password_hash, access_token_hash, guest_name, room_number, 
             booking_id, email_encrypted, phone_encrypted, check_in_date, check_out_date, 
             expires_at, created_at, is_active)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), 1)
        ");
        
        $stmt->execute([
            $username,
            $passwordHash,
            $accessTokenHash,
            $guestName,
            $roomNumber,
            $bookingId,
            $encryptedEmail,
            $encryptedPhone,
            $checkInDate,
            $checkOutDate,
            $expiresAt
        ]);
        
        $guestAccessId = $this->db->lastInsertId();
        
        // Generate secure portal URL
        $portalUrl = $this->generatePortalUrl($accessToken);
        
        // Log the creation
        $this->logActivity($guestAccessId, 'access_created', 'Guest access credentials created');
        
        return [
            'guest_access_id' => $guestAccessId,
            'username' => $username,
            'password' => $password, // Only returned once, never stored in plain text
            'access_token' => $accessToken, // Only returned once
            'portal_url' => $portalUrl,
            'expires_at' => $expiresAt,
            'room_number' => $roomNumber,
            'guest_name' => $guestName
        ];
    }

    /**
     * Authenticate guest login
     */
    public function authenticate(string $username, string $password, string $ipAddress): array
    {
        // Check for lockout
        if ($this->isLockedOut($username, $ipAddress)) {
            $this->logActivity(null, 'login_blocked', "Locked out attempt: {$username}", $ipAddress);
            throw new Exception('Too many failed attempts. Please try again later.');
        }
        
        $stmt = $this->db->prepare("
            SELECT * FROM guest_portal_access 
            WHERE username = ? AND is_active = 1 AND expires_at > NOW()
        ");
        $stmt->execute([$username]);
        $guest = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$guest) {
            $this->recordFailedAttempt($username, $ipAddress);
            $this->logActivity(null, 'login_failed', "Invalid username: {$username}", $ipAddress);
            throw new Exception('Invalid credentials or access expired.');
        }
        
        if (!password_verify($password, $guest['password_hash'])) {
            $this->recordFailedAttempt($username, $ipAddress);
            $this->logActivity($guest['id'], 'login_failed', 'Invalid password', $ipAddress);
            throw new Exception('Invalid credentials or access expired.');
        }
        
        // Clear failed attempts on successful login
        $this->clearFailedAttempts($username, $ipAddress);
        
        // Create session
        $sessionToken = $this->createSession($guest['id'], $ipAddress);
        
        // Update last login
        $stmt = $this->db->prepare("
            UPDATE guest_portal_access SET last_login_at = NOW(), login_count = login_count + 1 WHERE id = ?
        ");
        $stmt->execute([$guest['id']]);
        
        $this->logActivity($guest['id'], 'login_success', 'Guest logged in', $ipAddress);
        
        return [
            'session_token' => $sessionToken,
            'guest_access_id' => $guest['id'],
            'guest_name' => $guest['guest_name'],
            'room_number' => $guest['room_number'],
            'expires_at' => $guest['expires_at']
        ];
    }

    /**
     * Authenticate via access token (direct link)
     */
    public function authenticateByToken(string $accessToken, string $ipAddress): array
    {
        $tokenHash = hash('sha256', $accessToken);
        
        $stmt = $this->db->prepare("
            SELECT * FROM guest_portal_access 
            WHERE access_token_hash = ? AND is_active = 1 AND expires_at > NOW()
        ");
        $stmt->execute([$tokenHash]);
        $guest = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$guest) {
            $this->logActivity(null, 'token_auth_failed', 'Invalid or expired token', $ipAddress);
            throw new Exception('Invalid or expired access link.');
        }
        
        // Create session
        $sessionToken = $this->createSession($guest['id'], $ipAddress);
        
        // Update last login
        $stmt = $this->db->prepare("
            UPDATE guest_portal_access SET last_login_at = NOW(), login_count = login_count + 1 WHERE id = ?
        ");
        $stmt->execute([$guest['id']]);
        
        $this->logActivity($guest['id'], 'token_login_success', 'Guest logged in via link', $ipAddress);
        
        return [
            'session_token' => $sessionToken,
            'guest_access_id' => $guest['id'],
            'guest_name' => $guest['guest_name'],
            'room_number' => $guest['room_number'],
            'expires_at' => $guest['expires_at']
        ];
    }

    /**
     * Validate session
     */
    public function validateSession(string $sessionToken, string $ipAddress): ?array
    {
        $tokenHash = hash('sha256', $sessionToken);
        
        $stmt = $this->db->prepare("
            SELECT s.*, g.guest_name, g.room_number, g.expires_at as access_expires, g.is_active
            FROM guest_portal_sessions s
            JOIN guest_portal_access g ON s.guest_access_id = g.id
            WHERE s.session_token_hash = ? 
            AND s.expires_at > NOW()
            AND s.is_valid = 1
        ");
        $stmt->execute([$tokenHash]);
        $session = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$session || !$session['is_active']) {
            return null;
        }
        
        // Verify IP hasn't changed dramatically (optional security)
        // For mobile users, we allow some IP flexibility
        
        // Update last activity
        $stmt = $this->db->prepare("
            UPDATE guest_portal_sessions SET last_activity_at = NOW() WHERE id = ?
        ");
        $stmt->execute([$session['id']]);
        
        return [
            'guest_access_id' => $session['guest_access_id'],
            'guest_name' => $session['guest_name'],
            'room_number' => $session['room_number'],
            'expires_at' => $session['access_expires']
        ];
    }

    /**
     * Logout / invalidate session
     */
    public function logout(string $sessionToken): void
    {
        $tokenHash = hash('sha256', $sessionToken);
        
        $stmt = $this->db->prepare("
            UPDATE guest_portal_sessions SET is_valid = 0 WHERE session_token_hash = ?
        ");
        $stmt->execute([$tokenHash]);
    }

    /**
     * Revoke guest access (on checkout or manually)
     */
    public function revokeAccess(int $guestAccessId, ?int $revokedBy = null): void
    {
        // Invalidate all sessions
        $stmt = $this->db->prepare("
            UPDATE guest_portal_sessions SET is_valid = 0 WHERE guest_access_id = ?
        ");
        $stmt->execute([$guestAccessId]);
        
        // Deactivate access
        $stmt = $this->db->prepare("
            UPDATE guest_portal_access SET is_active = 0, revoked_at = NOW(), revoked_by = ? WHERE id = ?
        ");
        $stmt->execute([$revokedBy, $guestAccessId]);
        
        $this->logActivity($guestAccessId, 'access_revoked', 'Guest access revoked');
    }

    /**
     * Get guest access by booking ID
     */
    public function getAccessByBooking(int $bookingId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM guest_portal_access WHERE booking_id = ? ORDER BY created_at DESC LIMIT 1
        ");
        $stmt->execute([$bookingId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Get guest access by room (active)
     */
    public function getActiveAccessByRoom(string $roomNumber): ?array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM guest_portal_access 
            WHERE room_number = ? AND is_active = 1 AND expires_at > NOW()
            ORDER BY created_at DESC LIMIT 1
        ");
        $stmt->execute([$roomNumber]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Regenerate credentials for existing access
     */
    public function regenerateCredentials(int $guestAccessId): array
    {
        $stmt = $this->db->prepare("SELECT * FROM guest_portal_access WHERE id = ?");
        $stmt->execute([$guestAccessId]);
        $access = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$access) {
            throw new Exception('Guest access not found.');
        }
        
        // Generate new credentials
        $password = $this->generateSecurePassword();
        $passwordHash = password_hash($password, PASSWORD_ARGON2ID, [
            'memory_cost' => 65536,
            'time_cost' => 4,
            'threads' => 3
        ]);
        
        $accessToken = $this->generateAccessToken();
        $accessTokenHash = hash('sha256', $accessToken);
        
        // Update credentials
        $stmt = $this->db->prepare("
            UPDATE guest_portal_access 
            SET password_hash = ?, access_token_hash = ?, updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$passwordHash, $accessTokenHash, $guestAccessId]);
        
        // Invalidate all existing sessions
        $stmt = $this->db->prepare("
            UPDATE guest_portal_sessions SET is_valid = 0 WHERE guest_access_id = ?
        ");
        $stmt->execute([$guestAccessId]);
        
        $this->logActivity($guestAccessId, 'credentials_regenerated', 'Credentials regenerated');
        
        return [
            'username' => $access['username'],
            'password' => $password,
            'access_token' => $accessToken,
            'portal_url' => $this->generatePortalUrl($accessToken)
        ];
    }

    /**
     * Get all active guest accesses
     */
    public function getActiveAccesses(): array
    {
        $stmt = $this->db->prepare("
            SELECT id, username, guest_name, room_number, check_in_date, check_out_date, 
                   expires_at, last_login_at, login_count, created_at
            FROM guest_portal_access 
            WHERE is_active = 1 AND expires_at > NOW()
            ORDER BY room_number
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ==================== Private Helper Methods ====================

    private function generateUsername(string $roomNumber, string $checkInDate): string
    {
        $date = date('md', strtotime($checkInDate));
        $random = strtoupper(bin2hex(random_bytes(2)));
        return "G{$roomNumber}{$date}{$random}";
    }

    private function generateSecurePassword(): string
    {
        // Generate a memorable but secure password
        $chars = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ23456789';
        $password = '';
        for ($i = 0; $i < 12; $i++) {
            $password .= $chars[random_int(0, strlen($chars) - 1)];
        }
        return $password;
    }

    private function generateAccessToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    private function generatePortalUrl(string $accessToken): string
    {
        $baseUrl = rtrim(defined('APP_URL') ? APP_URL : '', '/');
        return "{$baseUrl}/guest-portal.php?token={$accessToken}";
    }

    private function createSession(int $guestAccessId, string $ipAddress): string
    {
        $sessionToken = bin2hex(random_bytes(32));
        $sessionTokenHash = hash('sha256', $sessionToken);
        $expiresAt = date('Y-m-d H:i:s', time() + $this->sessionTimeout);
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        $stmt = $this->db->prepare("
            INSERT INTO guest_portal_sessions 
            (guest_access_id, session_token_hash, ip_address, user_agent, expires_at, created_at, last_activity_at, is_valid)
            VALUES (?, ?, ?, ?, ?, NOW(), NOW(), 1)
        ");
        $stmt->execute([$guestAccessId, $sessionTokenHash, $ipAddress, $userAgent, $expiresAt]);
        
        return $sessionToken;
    }

    private function isLockedOut(string $username, string $ipAddress): bool
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as attempts FROM guest_login_attempts 
            WHERE (username = ? OR ip_address = ?) 
            AND attempted_at > DATE_SUB(NOW(), INTERVAL ? SECOND)
            AND success = 0
        ");
        $stmt->execute([$username, $ipAddress, $this->lockoutDuration]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return ($result['attempts'] ?? 0) >= $this->maxFailedAttempts;
    }

    private function recordFailedAttempt(string $username, string $ipAddress): void
    {
        $stmt = $this->db->prepare("
            INSERT INTO guest_login_attempts (username, ip_address, attempted_at, success)
            VALUES (?, ?, NOW(), 0)
        ");
        $stmt->execute([$username, $ipAddress]);
    }

    private function clearFailedAttempts(string $username, string $ipAddress): void
    {
        $stmt = $this->db->prepare("
            DELETE FROM guest_login_attempts WHERE username = ? OR ip_address = ?
        ");
        $stmt->execute([$username, $ipAddress]);
    }

    private function encrypt(string $data): string
    {
        $iv = random_bytes(openssl_cipher_iv_length($this->cipher));
        $tag = '';
        $encrypted = openssl_encrypt($data, $this->cipher, hex2bin($this->encryptionKey), OPENSSL_RAW_DATA, $iv, $tag);
        return base64_encode($iv . $tag . $encrypted);
    }

    private function decrypt(string $data): string
    {
        $data = base64_decode($data);
        $ivLength = openssl_cipher_iv_length($this->cipher);
        $iv = substr($data, 0, $ivLength);
        $tag = substr($data, $ivLength, 16);
        $encrypted = substr($data, $ivLength + 16);
        return openssl_decrypt($encrypted, $this->cipher, hex2bin($this->encryptionKey), OPENSSL_RAW_DATA, $iv, $tag);
    }

    private function logActivity(?int $guestAccessId, string $action, string $details, ?string $ipAddress = null): void
    {
        $stmt = $this->db->prepare("
            INSERT INTO guest_portal_activity_log (guest_access_id, action, details, ip_address, created_at)
            VALUES (?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$guestAccessId, $action, $details, $ipAddress ?? ($_SERVER['REMOTE_ADDR'] ?? null)]);
    }

    private function ensureTablesExist(): void
    {
        // Guest Portal Access table
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS guest_portal_access (
                id INT AUTO_INCREMENT PRIMARY KEY,
                username VARCHAR(50) NOT NULL UNIQUE,
                password_hash VARCHAR(255) NOT NULL,
                access_token_hash VARCHAR(64) NOT NULL,
                guest_name VARCHAR(100) NOT NULL,
                room_number VARCHAR(20) NOT NULL,
                booking_id INT NULL,
                email_encrypted TEXT NULL,
                phone_encrypted TEXT NULL,
                check_in_date DATE NOT NULL,
                check_out_date DATE NOT NULL,
                expires_at DATETIME NOT NULL,
                last_login_at DATETIME NULL,
                login_count INT DEFAULT 0,
                is_active TINYINT(1) DEFAULT 1,
                revoked_at DATETIME NULL,
                revoked_by INT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NULL,
                INDEX idx_username (username),
                INDEX idx_room (room_number),
                INDEX idx_booking (booking_id),
                INDEX idx_active_expires (is_active, expires_at),
                INDEX idx_token (access_token_hash)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        // Guest Portal Sessions table
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS guest_portal_sessions (
                id INT AUTO_INCREMENT PRIMARY KEY,
                guest_access_id INT NOT NULL,
                session_token_hash VARCHAR(64) NOT NULL,
                ip_address VARCHAR(45) NULL,
                user_agent TEXT NULL,
                expires_at DATETIME NOT NULL,
                created_at DATETIME NOT NULL,
                last_activity_at DATETIME NOT NULL,
                is_valid TINYINT(1) DEFAULT 1,
                INDEX idx_token (session_token_hash),
                INDEX idx_guest (guest_access_id),
                INDEX idx_valid_expires (is_valid, expires_at),
                FOREIGN KEY (guest_access_id) REFERENCES guest_portal_access(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        // Login attempts for rate limiting
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS guest_login_attempts (
                id INT AUTO_INCREMENT PRIMARY KEY,
                username VARCHAR(50) NULL,
                ip_address VARCHAR(45) NOT NULL,
                attempted_at DATETIME NOT NULL,
                success TINYINT(1) DEFAULT 0,
                INDEX idx_username_time (username, attempted_at),
                INDEX idx_ip_time (ip_address, attempted_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        // Activity log for audit
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS guest_portal_activity_log (
                id INT AUTO_INCREMENT PRIMARY KEY,
                guest_access_id INT NULL,
                action VARCHAR(50) NOT NULL,
                details TEXT NULL,
                ip_address VARCHAR(45) NULL,
                created_at DATETIME NOT NULL,
                INDEX idx_guest (guest_access_id),
                INDEX idx_action (action),
                INDEX idx_created (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    }
}
