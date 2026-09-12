<?php
declare(strict_types=1);

/**
 * OTP Helper Functions
 * Handles generation, storage, verification, and consumption of OTP codes
 */

/**
 * Generate a 6-digit OTP code
 */
function generate_otp_code(): string {
    return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
}

/**
 * Hash an OTP code for storage (using password_hash for security)
 */
function hash_otp(string $code): string {
    return password_hash($code, PASSWORD_DEFAULT);
}

/**
 * Generate a new OTP, store it (hashed) in the database, and return the plaintext code
 * Invalidates any prior unconsumed OTPs of the same purpose for the same email
 * 
 * @param PDO $pdo Database connection
 * @param int|null $userId User ID (optional, can be null for pre-registration)
 * @param string $email Email address
 * @param string $purpose 'activation' or 'login'
 * @return string The plaintext 6-digit OTP code
 */
function generate_and_store_otp(PDO $pdo, ?int $userId, string $email, string $purpose): string {
    $code = generate_otp_code();
    $hash = hash_otp($code);
    $expiresAt = (new DateTime('+5 minutes'))->format('Y-m-d H:i:s');
    
    $pdo->beginTransaction();
    try {
        // Invalidate any prior unconsumed OTPs for this email and purpose
        $stmt = $pdo->prepare("
            UPDATE email_otps 
            SET consumed_at = NOW() 
            WHERE email = :email AND purpose = :purpose AND consumed_at IS NULL
        ");
        $stmt->execute(['email' => $email, 'purpose' => $purpose]);
        
        // Insert new OTP
        $stmt = $pdo->prepare("
            INSERT INTO email_otps (user_id, email, otp_hash, purpose, expires_at, created_at)
            VALUES (:user_id, :email, :hash, :purpose, :expires_at, NOW())
        ");
        $stmt->execute([
            'user_id' => $userId,
            'email' => $email,
            'hash' => $hash,
            'purpose' => $purpose,
            'expires_at' => $expiresAt,
        ]);
        
        $pdo->commit();
        return $code;
    } catch (PDOException $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/**
 * Verify and consume an OTP code
 * Checks the newest unconsumed, unexpired OTP for the given email and purpose
 * On successful match, marks it as consumed and returns true
 * 
 * @param PDO $pdo Database connection
 * @param string $email Email address
 * @param string $purpose 'activation' or 'login'
 * @param string $code Plaintext 6-digit code to verify
 * @return bool True if valid and consumed, false otherwise
 */
function verify_and_consume_otp(PDO $pdo, string $email, string $purpose, string $code): bool {
    try {
        // Find the newest unconsumed, unexpired OTP for this email and purpose
        $stmt = $pdo->prepare("
            SELECT id, otp_hash 
            FROM email_otps 
            WHERE email = :email 
            AND purpose = :purpose 
            AND consumed_at IS NULL 
            AND expires_at > NOW()
            ORDER BY created_at DESC
            LIMIT 1
        ");
        $stmt->execute(['email' => $email, 'purpose' => $purpose]);
        $otp = $stmt->fetch();
        
        if (!$otp) {
            return false;
        }
        
        if (!password_verify($code, $otp['otp_hash'])) {
            return false;
        }
        
        // Mark as consumed
        $stmt = $pdo->prepare("UPDATE email_otps SET consumed_at = NOW() WHERE id = :id");
        $stmt->execute(['id' => $otp['id']]);
        
        return true;
    } catch (PDOException $e) {
        error_log('OTP verification error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Resend OTP by generating a new one and sending via email
 * 
 * @param PDO $pdo Database connection
 * @param string $email Email address
 * @param string $toName Recipient name
 * @param string $purpose 'activation' or 'login'
 * @return array ['success' => bool, 'message' => string]
 */
function resend_otp(PDO $pdo, string $email, string $toName, string $purpose): array {
    // Get user_id for this email
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = :email");
    $stmt->execute(['email' => $email]);
    $user = $stmt->fetch();
    $userId = $user ? (int) $user['id'] : null;
    
    try {
        $code = generate_and_store_otp($pdo, $userId, $email, $purpose);
        $sent = send_otp_email($email, $toName, $code, $purpose);
        
        if ($sent) {
            return ['success' => true, 'message' => 'A new code has been sent to your email.'];
        } else {
            return ['success' => false, 'message' => 'Failed to send email. Please try again later.'];
        }
    } catch (PDOException $e) {
        error_log('Resend OTP error: ' . $e->getMessage());
        return ['success' => false, 'message' => 'A database error occurred. Please try again.'];
    }
}