<?php
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

require 'vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

class User {
    private $conn;

    public function __construct() {
        // Set timezone to Asia/Manila
        date_default_timezone_set('Asia/Manila');
        include 'connection-pdo.php'; 
        $this->conn = $conn;
    }    private function generateOTP($length = 6) {
        // Ensure only numeric characters
        $digits = '0123456789';
        $otp = '';
        for ($i = 0; $i < $length; $i++) {
            $otp .= $digits[rand(0, 9)];
        }
        return $otp;
    }

    private function findEmailById($user_id) {
        try {
            $sql = "SELECT users_email as email FROM tbl_users WHERE users_id = :id";
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':id', $user_id, PDO::PARAM_STR);
            $stmt->execute();

            if ($result = $stmt->fetch(PDO::FETCH_ASSOC)) {
                return $result['email'];
            }

            return null;
        } catch (PDOException $e) {
            error_log("Error finding email: " . $e->getMessage());
            return null;
        }
    }
    
    private function getPasswordResetEmailTemplate($otp) {
        return '
        <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #ddd; border-radius: 5px;">
            <div style="text-align: center; margin-bottom: 20px;">
                <h2 style="color: #2C3E50;">Password Reset Request</h2>
            </div>
            <div style="margin-bottom: 20px; color: #555;">
                <p>You have requested to reset your password. Use the following OTP code to proceed:</p>
                <div style="background-color: #f8f9fa; padding: 15px; text-align: center; border-radius: 5px;">
                    <h1 style="color: #2C3E50; letter-spacing: 5px; margin: 0;">' . $otp . '</h1>
                </div>
                <p style="color: #e74c3c; margin-top: 15px;"><strong>This OTP will expire in 3 minutes.</strong></p>
            </div>
            <div style="border-top: 1px solid #ddd; padding-top: 15px; font-size: 12px; color: #777;">
                <p>If you did not request this password reset, please ignore this email.</p>
                <p>This is an automated message, please do not reply.</p>
            </div>
        </div>';
    }

    private function getLoginOTPEmailTemplate($otp) {
        return '
        <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #ddd; border-radius: 5px;">
            <div style="text-align: center; margin-bottom: 20px;">
                <h2 style="color: #2C3E50;">Login Authentication Code</h2>
            </div>
            <div style="margin-bottom: 20px; color: #555;">
                <p>Here is your login authentication code, DO NOT SHARE WITH ANYONE:</p>
                <div style="background-color: #f8f9fa; padding: 15px; text-align: center; border-radius: 5px;">
                    <h1 style="color: #2C3E50; letter-spacing: 5px; margin: 0;">' . $otp . '</h1>
                </div>
                <p style="color: #e74c3c; margin-top: 15px;"><strong>This code will expire in 3 minutes.</strong></p>
            </div>
            <div style="border-top: 1px solid #ddd; padding-top: 15px; font-size: 12px; color: #777;">
                <p>If you did not attempt to log in, please secure your account immediately.</p>
                <p>This is an automated message, please do not reply.</p>
            </div>
        </div>';
    }

    private function getEmailVerificationTemplate($token, $user_id) {
        $verificationLink = "http://localhost/coc/gsd/verify.php?token=" . $token . "&user_id=" . $user_id;
        return '
        <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #ddd; border-radius: 5px;">
            <div style="text-align: center; margin-bottom: 20px;">
                <h2 style="color: #2C3E50;">Email Verification</h2>
            </div>
            <div style="margin-bottom: 20px; color: #555;">
                <p>Please click the button below to verify your email address:</p>
                <div style="text-align: center; margin: 30px 0;">
                    <a href="' . $verificationLink . '" style="background-color: #2C3E50; color: white; padding: 12px 25px; text-decoration: none; border-radius: 5px;">Verify Email</a>
                </div>
                <p>Or copy and paste this link in your browser:</p>
                <p style="background-color: #f8f9fa; padding: 10px; border-radius: 5px; word-break: break-all;">' . $verificationLink . '</p>
                <p style="color: #e74c3c; margin-top: 15px;"><strong>This link will expire in 24 hours.</strong></p>
            </div>
            <div style="border-top: 1px solid #ddd; padding-top: 15px; font-size: 12px; color: #777;">
                <p>If you did not create an account, please ignore this email.</p>
                <p>This is an automated message, please do not reply.</p>
            </div>
        </div>';
    }

    public function sendPasswordResetOTP($email) {
        try {
            // Generate OTP
            $otp = $this->generateOTP();
            
            // Calculate expiration time (current time + 3 minutes)
            $current_time = new DateTime();
            $expiration = $current_time->modify('+3 minutes')->format('Y-m-d H:i:s');

            // Check if email already exists in the table
            $checkStmt = $this->conn->prepare("SELECT password_reset_id FROM password_reset_otp 
                WHERE password_otp_email_address = ?
                ORDER BY password_otp_expiration DESC 
                LIMIT 1");
            $checkStmt->execute([$email]);
            $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);

            if ($existing) {
                // Update existing record
                $stmt = $this->conn->prepare("UPDATE password_reset_otp 
                    SET password_reset_otp = ?, 
                        password_otp_expiration = ?,
                        password_otp_isActive = 1
                    WHERE password_reset_id = ?");
                $stmt->execute([$otp, $expiration, $existing['password_reset_id']]);
                $password_reset_id = $existing['password_reset_id'];
            } else {
                // Insert new record
                $stmt = $this->conn->prepare("INSERT INTO password_reset_otp 
                    (password_reset_otp, password_otp_expiration, password_otp_email_address) 
                    VALUES (?, ?, ?)");
                $stmt->execute([$otp, $expiration, $email]);
                $password_reset_id = $this->conn->lastInsertId();
            }

            // Configure Gmail SMTP
            $mail = new PHPMailer(true);
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username = 'noreplygsd12@gmail.com';
            $mail->Password = 'ckfo wpow pfmq ziwd'; // Using App Password instead of regular password
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = 587;

            // Fix SSL certificate verification
            $mail->SMTPOptions = array(
                'ssl' => array(
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                )
            );

            $mail->setFrom('vallechristianmark@gmail.com', 'GSD System');
            $mail->addAddress($email);
            $mail->isHTML(true);
            $mail->Subject = 'Password Reset OTP';
            $mail->Body = $this->getPasswordResetEmailTemplate($otp);

            $mail->send();
            return ["status" => "success", "message" => "OTP sent successfully", "reset_id" => $password_reset_id];
        } catch (Exception $e) {
            return ["status" => "error", "message" => "Failed to send OTP: " . $e->getMessage()];
        }
    }    public function sendLoginOTP($user_id) {
        try {
            // First check if the user_id exists in any table
            $email = $this->findEmailById($user_id);
            if (!$email) {
                return ["status" => "error", "message" => "User ID not found in records"];
            }

            $current_time = new DateTime();
            
            // First update any expired OTPs to inactive
            $updateExpiredStmt = $this->conn->prepare("
                UPDATE tbl_user_2fa 
                SET is_active = 0 
                WHERE user_id = :user_id 
                AND expires_at < NOW()");
            $updateExpiredStmt->bindParam(':user_id', $user_id);
            $updateExpiredStmt->execute();

            // Check if user exists in 2FA table with active status
            $stmt = $this->conn->prepare("
                SELECT id, otp_code, expires_at, otp_until, is_active 
                FROM tbl_user_2fa 
                WHERE user_id = :user_id
                LIMIT 1");
            $stmt->bindParam(':user_id', $user_id);
            $stmt->execute();
            $existingRecord = $stmt->fetch(PDO::FETCH_ASSOC);

            // Generate new OTP and expiration
            $otp = $this->generateOTP();
            $expiration = $current_time->modify('+3 minutes')->format('Y-m-d H:i:s');

            if ($existingRecord) {
                // Only proceed if the record is active
                if (!$existingRecord['is_active']) {
                    return [
                        "status" => "error",
                        "message" => "2FA is not active for this user",
                        "requires_verification" => true
                    ];
                }

                // Update existing record with new OTP
                $updateStmt = $this->conn->prepare("
                    UPDATE tbl_user_2fa 
                    SET otp_code = :otp,
                        otp_until = :expiration,
                        is_active = 1
                    WHERE id = :id AND is_active = 1");
                $updateStmt->bindParam(':otp', $otp);
                $updateStmt->bindParam(':expiration', $expiration);
                $updateStmt->bindParam(':id', $existingRecord['id']);
                $updateStmt->execute();

                if ($updateStmt->rowCount() === 0) {
                    return [
                        "status" => "error",
                        "message" => "Failed to update OTP. 2FA might be inactive.",
                        "requires_verification" => true
                    ];
                }                // Send email only if update was successful
                $mail = new PHPMailer(true);
                $mail->isSMTP();
                $mail->Host = 'smtp.gmail.com';
                $mail->SMTPAuth = true;
                $mail->Username = 'noreplygsd12@gmail.com';
                $mail->Password = 'ckfo wpow pfmq ziwd';
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port = 587;

                $mail->SMTPOptions = array(
                    'ssl' => array(
                        'verify_peer' => false,
                        'verify_peer_name' => false,
                        'allow_self_signed' => true
                    )
                );

                $mail->setFrom('vallechristianmark@gmail.com', 'General Services Department');
                $mail->addAddress($email);
                $mail->isHTML(true);
                $mail->Subject = 'Login Authentication Code';
                $mail->Body = $this->getLoginOTPEmailTemplate($otp);

                $mail->send();
                return [
                    "status" => "success",
                    "message" => "Login OTP sent successfully",
                    "authenticated" => false,
                    "expires" => $expiration
                ];
            }
            
            return [
                "status" => "error",
                "message" => "No active 2FA record found for this user",
                "requires_verification" => true
            ];

        } catch (Exception $e) {
            return [
                "status" => "error", 
                "message" => "Failed to process login OTP: " . $e->getMessage(),
                "requires_verification" => true
            ];
        }
    }

    public function validateOTPKey($otp, $email) {
        try {
            // Check if OTP exists, not expired, matches the email, and is active
            $stmt = $this->conn->prepare("SELECT * FROM password_reset_otp 
                WHERE password_reset_otp = ? 
                AND password_otp_email_address = ?
                AND password_otp_expiration > NOW()
                AND password_otp_isActive = 1
                ORDER BY password_otp_expiration DESC 
            ");
            $stmt->execute([$otp, $email]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($result) {
                // Update the OTP to inactive after successful validation
                $updateStmt = $this->conn->prepare("UPDATE password_reset_otp 
                    SET password_otp_isActive = 0 
                    WHERE password_reset_otp = ? 
                    AND password_otp_email_address = ?");
                $updateStmt->execute([$otp, $email]);

                return [
                    "status" => "success", 
                    "message" => "Valid OTP",
                    "email" => $result['password_otp_email_address']
                ];
            } else {
                return ["status" => "error", "message" => "Invalid, expired, or already used OTP"];
            }
        } catch (Exception $e) {
            return ["status" => "error", "message" => "Validation error: " . $e->getMessage()];
        }
    }

    public function updatePassword($email, $newPassword) {
        try {
            // Check if the email exists in tbl_users
            $stmt = $this->conn->prepare("SELECT users_password FROM tbl_users WHERE users_email = :email");
            $stmt->bindParam(':email', $email);
            $stmt->execute();
            
            if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                // Check if new password matches current password
                if (password_verify($newPassword, $row['users_password'])) {
                    return [
                        "status" => "error",
                        "message" => "Cannot use the current password. Please choose a different password."
                    ];
                }

                // Update password
                $hashedNewPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                $updateStmt = $this->conn->prepare("UPDATE tbl_users SET users_password = :password WHERE users_email = :email");
                $updateStmt->bindParam(':password', $hashedNewPassword);
                $updateStmt->bindParam(':email', $email);
                $updateStmt->execute();

                if ($updateStmt->rowCount() > 0) {
                    // Delete used OTP after successful password update
                    $deleteStmt = $this->conn->prepare("DELETE FROM password_reset_otp WHERE password_otp_email_address = ?");
                    $deleteStmt->execute([$email]);

                    return [
                        "status" => "success",
                        "message" => "Password successfully updated"
                    ];
                }
            }

            return [
                "status" => "error",
                "message" => "Email not found in users table"
            ];

        } catch (Exception $e) {
            return [
                "status" => "error",
                "message" => "Failed to update password: " . $e->getMessage()
            ];
        }
    }

    public function validateLoginOTP($user_id, $otp) {
        try {
            // Check if OTP exists, not expired, and matches the user_id
            $stmt = $this->conn->prepare("SELECT * FROM tbl_user_2fa 
                WHERE user_id = :user_id 
                AND otp_code = :otp 
                AND otp_until > NOW()
                AND is_active = 1 
                LIMIT 1");
            $stmt->bindParam(':user_id', $user_id, PDO::PARAM_STR);
            $stmt->bindParam(':otp', $otp);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($result) {
                // Update the OTP to used (deactivate it)
                $updateStmt = $this->conn->prepare("
                    UPDATE tbl_user_2fa 
                    SET otp_code = NULL 
                    WHERE id = :id");
                $updateStmt->bindParam(':id', $result['id']);
                $updateStmt->execute();

                $expires_at = $result['expires_at'];
                return [
                    "status" => "success",
                    "message" => "Valid OTP",
                    "authenticated" => true,
                    "user_id" => $result['user_id'],
                    "auth_status" => "authenticated",
                    "auth_until" => $expires_at
                ];
            } else {
                // Check if user has an active 2FA record but invalid OTP
                $checkStmt = $this->conn->prepare("
                    SELECT 1 FROM tbl_user_2fa 
                    WHERE user_id = :user_id 
                    AND is_active = 1 
                    AND expires_at > NOW()");
                $checkStmt->bindParam(':user_id', $user_id);
                $checkStmt->execute();
                
                if ($checkStmt->fetchColumn()) {
                    return [
                        "status" => "error",
                        "message" => "Invalid or expired OTP",
                        "authenticated" => false
                    ];
                } else {
                    return [
                        "status" => "error",
                        "message" => "No active 2FA record found",
                        "authenticated" => false,
                        "requires_verification" => true
                    ];
                }
            }
        } catch (Exception $e) {
            return [
                "status" => "error",
                "message" => "Validation error: " . $e->getMessage(),
                "authenticated" => false
            ];
        }
    }

    public function updateAuthenticationPeriod($user_id) {
        try {
            // Calculate new authentication period (7 days from now)
            $auth_until = (new DateTime())->modify('+7 days')->format('Y-m-d H:i:s');
            
            // Update the authentication period for the user
            $stmt = $this->conn->prepare("UPDATE user_authenticate 
                SET user_authenticate_until = :auth_until 
                WHERE user_id = :user_id
                AND authenticate_otp_exp > NOW()
                ORDER BY authenticate_otp_exp DESC
                LIMIT 1");
            
            $stmt->bindParam(':auth_until', $auth_until);
            $stmt->bindParam(':user_id', $user_id);
            $stmt->execute();

            if ($stmt->rowCount() > 0) {
                return [
                    "status" => "success",
                    "message" => "Authentication period updated successfully",
                    "authenticated_until" => $auth_until
                ];
            } else {
                return [
                    "status" => "error",
                    "message" => "No valid authentication record found for this user"
                ];
            }
        } catch (Exception $e) {
            return [
                "status" => "error",
                "message" => "Failed to update authentication period: " . $e->getMessage()
            ];
        }
    }

    public function sendEmailVerification($user_id) {
        try {
            // Get user email from tbl_users
            $stmt = $this->conn->prepare("SELECT users_email, users_fname FROM tbl_users WHERE users_id = :user_id");
            $stmt->bindParam(':user_id', $user_id, PDO::PARAM_STR);
            $stmt->execute();
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user) {
                return ["status" => "error", "message" => "User not found"];
            }

            // Generate 6-digit verification code
            $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

            // Set expiration time to 5 minutes from now
            $expires_at = (new DateTime())->modify('+5 minutes')->format('Y-m-d H:i:s');

            // Check if there's an existing verification record
            $checkStmt = $this->conn->prepare("SELECT id FROM tbl_email_verification WHERE user_id = ? LIMIT 1");
            $checkStmt->execute([$user_id]);
            $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);

            if ($existing) {
                // Update existing record
                $updateStmt = $this->conn->prepare("UPDATE tbl_email_verification 
                    SET verification_token = ?, expires_at = ?, created_at = NOW()
                    WHERE user_id = ?");
                $updateStmt->execute([$code, $expires_at, $user_id]);
            } else {
                // Insert new verification record
                $insertStmt = $this->conn->prepare("INSERT INTO tbl_email_verification 
                    (user_id, verification_token, expires_at, created_at) 
                    VALUES (?, ?, ?, NOW())");
                $insertStmt->execute([$user_id, $code, $expires_at]);
            }

            // Send verification email (code only, no link)
            $mail = new PHPMailer(true);
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username = 'noreplygsd12@gmail.com';
            $mail->Password = 'ckfo wpow pfmq ziwd';
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = 587;

            $mail->SMTPOptions = array(
                'ssl' => array(
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                )
            );

            $mail->setFrom('vallechristianmark@gmail.com', 'General Services Department');
            $mail->addAddress($user['users_email']);
            $mail->isHTML(true);
            $mail->Subject = 'Your Email Verification Code';
            $mail->Body = '
                <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #ddd; border-radius: 5px;">
                    <div style="text-align: center; margin-bottom: 20px;">
                        <h2 style="color: #2C3E50;">Email Verification Code</h2>
                    </div>
                    <div style="margin-bottom: 20px; color: #555;">
                        <p>Use the following code to verify your email address:</p>
                        <div style="background-color: #f8f9fa; padding: 15px; text-align: center; border-radius: 5px;">
                            <h1 style="color: #2C3E50; letter-spacing: 5px; margin: 0;">' . $code . '</h1>
                        </div>
                        <p style="color: #e74c3c; margin-top: 15px;"><strong>This code will expire in 5 minutes.</strong></p>
                    </div>
                    <div style="border-top: 1px solid #ddd; padding-top: 15px; font-size: 12px; color: #777;">
                        <p>If you did not create an account, please ignore this email.</p>
                        <p>This is an automated message, please do not reply.</p>
                    </div>
                </div>';

            $mail->send();

            return [
                "status" => "success",
                "message" => "Verification code sent successfully",
                "expires" => $expires_at
            ];

        } catch (Exception $e) {
            return ["status" => "error", "message" => "Failed to send verification code: " . $e->getMessage()];
        }
    }

   public function validateEmailVerification($user_id, $token, $duration = '') {
    try {
        // Calculate 2FA expiration (using provided duration, defaults to 30 days)
        $duration = empty($duration) ? '30' : $duration;
        $expires_at = (new DateTime())->modify('+' . $duration . ' days')->format('Y-m-d H:i:s');
        $current_time = (new DateTime())->format('Y-m-d H:i:s');

        // Check if verification token exists, matches user_id, and has not expired
        $stmt = $this->conn->prepare("
            SELECT v.*, u.users_email, u.users_fname 
            FROM tbl_email_verification v
            INNER JOIN tbl_users u ON v.user_id = u.users_id
            WHERE v.user_id = :user_id 
            AND v.verification_token = :token
            AND v.expires_at > :current_time
            LIMIT 1
        ");
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_STR);
        $stmt->bindParam(':token', $token);
        $stmt->bindParam(':current_time', $current_time);
        $stmt->execute();
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($result) {
            // Begin transaction
            $this->conn->beginTransaction();

            try {
                // Delete the verification token
                $deleteStmt = $this->conn->prepare("DELETE FROM tbl_email_verification WHERE user_id = ?");
                $deleteStmt->execute([$user_id]);

                // Insert into 2FA table with is_active = 1 and expiration
                $insertStmt = $this->conn->prepare("
                    INSERT INTO tbl_user_2fa (user_id, is_active, expires_at)
                    VALUES (?, 1, ?)
                    ON DUPLICATE KEY UPDATE is_active = 1, expires_at = ?
                ");
                $insertStmt->execute([$user_id, $expires_at, $expires_at]);

                $this->conn->commit();

                return [
                    "status" => "success",
                    "message" => "Email verified successfully",
                    "user" => [
                        "id" => $user_id,
                        "email" => $result['users_email'],
                        "name" => $result['users_fname']
                    ],
                    "2fa_expires_at" => $expires_at
                ];
            } catch (Exception $e) {
                $this->conn->rollBack();
                throw $e;
            }
        } else {
            $stmt = $this->conn->prepare("
                SELECT v.expires_at 
                FROM tbl_email_verification v 
                WHERE v.user_id = :user_id 
                AND v.verification_token = :token
                LIMIT 1
            ");
            $stmt->bindParam(':user_id', $user_id, PDO::PARAM_STR);
            $stmt->bindParam(':token', $token);
            $stmt->execute();
            $tokenInfo = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($tokenInfo && $tokenInfo['expires_at'] <= $current_time) {
                return [
                    "status" => "error",
                    "message" => "Verification token has expired"
                ];
            }
            
            return [
                "status" => "error",
                "message" => "Invalid verification token or user ID"
            ];
        }
    } catch (Exception $e) {
        return [
            "status" => "error",
            "message" => "Validation error: " . $e->getMessage()
        ];
    }
}

public function fetch2FA($user_id) {
    try {
        $current_time = (new DateTime())->format('Y-m-d H:i:s');
        
        // Check 2FA status in tbl_user_2fa
        $stmt = $this->conn->prepare("
            SELECT user_id, is_active, expires_at 
            FROM tbl_user_2fa 
            WHERE user_id = :user_id 
            LIMIT 1
        ");
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_STR);
        $stmt->execute();
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result) {
            // Check if 2FA is expired
            if ($result['expires_at'] < $current_time) {
                return [
                    "status" => "expired",
                    "message" => "2FA has expired",
                    "requires_verification" => true
                ];
            }
            
            return [
                "status" => "success",
                "is_active" => (bool)$result['is_active'],
                "expires_at" => $result['expires_at'],
                "requires_verification" => false
            ];
        }
        
        return [
            "status" => "not_found",
            "message" => "No 2FA record found",
            "requires_verification" => true
        ];
        
    } catch (Exception $e) {
        return [
            "status" => "error",
            "message" => "Error fetching 2FA status: " . $e->getMessage(),
            "requires_verification" => true
        ];
    }
}

// ...existing code...
}

// Handle the request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $operation = $input['operation'] ?? '';
    $json = $input['json'] ?? '';

    error_log("Operation: $operation");
    error_log("JSON: " . json_encode($input));

    $user = new User();
    
    switch($operation) {
        case 'fetch2FA':
            $user_id = isset($input['json']['user_id']) ? $input['json']['user_id'] : '';
            
            if ($user_id === '') {
                echo json_encode(["status" => "error", "message" => "User ID is required"]);
                exit;
            }
            $result = $user->fetch2FA($user_id);
            echo json_encode($result);
            break;
            
        case 'send_password_reset_otp':
            $email = $input['email'] ?? '';
            if (empty($email)) {
                echo json_encode(["status" => "error", "message" => "Email is required"]);
                exit;
            }
            $result = $user->sendPasswordResetOTP($email);
            echo json_encode($result);
            break;
        case 'validate_otp':
            $otp = $input['otp'] ?? '';
            $email = $input['email'] ?? '';
            
            if (empty($otp) || empty($email)) {
                echo json_encode(["status" => "error", "message" => "OTP and email are required"]);
                exit;
            }
            $result = $user->validateOTPKey($otp, $email);
            echo json_encode($result);
            break;
        case 'update_password':
            $email = $input['email'] ?? '';
            $newPassword = $input['password'] ?? '';
            
            if (empty($email) || empty($newPassword)) {
                echo json_encode(["status" => "error", "message" => "Email and password are required"]);
                exit;
            }
            
            $result = $user->updatePassword($email, $newPassword);
            echo json_encode($result);
            break;
        case 'sendLoginOTP':
            $user_id = isset($input['json']['id']) ? $input['json']['id'] : '';  // Updated to get ID from json object
            if ($user_id === '') {  // Changed condition to check for empty string
                echo json_encode(["status" => "error", "message" => "User ID is required"]);
                exit;
            }
            $result = $user->sendLoginOTP($user_id);
            echo json_encode($result);
            break;
        case 'validateLoginOTP':
            $username = isset($input['json']['id']) ? $input['json']['id'] : '';
            $otp = isset($input['json']['otp']) ? $input['json']['otp'] : '';
            
            if ($username === '' || $otp === '') {
                echo json_encode(["status" => "error", "message" => "Username and OTP are required"]);
                exit;
            }
            $result = $user->validateLoginOTP($username, $otp);
            echo json_encode($result);
            break;
        case 'updateAuthPeriod':
            $user_id = isset($input['json']['user_id']) ? $input['json']['user_id'] : '';
            
            if ($user_id === '') {
                echo json_encode(["status" => "error", "message" => "User ID is required"]);
                exit;
            }
            $result = $user->updateAuthenticationPeriod($user_id);
            echo json_encode($result);
            break;
        case 'sendEmailVerification':
            $user_id = isset($input['json']['user_id']) ? $input['json']['user_id'] : '';
            
            if ($user_id === '') {
                echo json_encode(["status" => "error", "message" => "User ID is required"]);
                exit;
            }
            $result = $user->sendEmailVerification($user_id);
            echo json_encode($result);
            break;
        case 'validateEmailVerification':
            $user_id = isset($input['json']['user_id']) ? $input['json']['user_id'] : '';
            $token = isset($input['json']['token']) ? $input['json']['token'] : '';
            $duration = isset($input['json']['duration']) ? intval($input['json']['duration']) : 30;
            
            if ($user_id === '' || $token === '') {
                echo json_encode(["status" => "error", "message" => "User ID and token are required"]);
                exit;
            }
            $result = $user->validateEmailVerification($user_id, $token, $duration);
            echo json_encode($result);
            break;
        case 'fetch2FA':
            $user_id = isset($input['json']['user_id']) ? $input['json']['user_id'] : '';
            
            if ($user_id === '') {
                echo json_encode(["status" => "error", "message" => "User ID is required"]);
                exit;
            }
            $result = $user->fetch2FA($user_id);
            echo json_encode($result);
            break;
        default:
            echo json_encode(["status" => "error", "message" => "Invalid operation"]);
            break;
    }
}
