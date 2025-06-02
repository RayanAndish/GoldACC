<?php

namespace App\Repositories;

use PDO;
use Monolog\Logger;
use Throwable; // Catch potential errors

class UserRepository {

    protected PDO $db;
    protected Logger $logger;

    public function __construct(PDO $db, Logger $logger) {
        $this->db = $db;
        $this->logger = $logger;
    }

    // ... (Other methods like getAllUsersWithRole, getUserById, saveUser, etc.) ...

    /**
     * Finds a user by their username.
     *
     * @param string $username The username to search for.
     * @return array|null User data as an associative array if found, null otherwise.
     */
    public function getUserByUsername(string $username): ?array {
        $this->logger->debug("Attempting to fetch user by username.", ['username' => $username]);
        try {
            // Ensure you select all necessary fields (id, username, name, password_hash, is_active, role_id etc.)
            $sql = "SELECT u.*, r.role_name
                    FROM users u
                    LEFT JOIN roles r ON u.role_id = r.id
                    WHERE u.username = :username";
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':username', $username, PDO::PARAM_STR);
            $stmt->execute();
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user) {
                $this->logger->debug("User found by username.", ['username' => $username, 'user_id' => $user['id']]);
                // Convert is_active to integer if needed
                $user['is_active'] = isset($user['is_active']) ? (int)$user['is_active'] : 0;
                return $user;
            } else {
                $this->logger->debug("User not found by username.", ['username' => $username]);
                return null;
            }
        } catch (Throwable $e) {
            $this->logger->error("Database error fetching user by username.", ['username' => $username, 'exception' => $e]);
            // Rethrow or return null based on desired error handling
            // throw new Exception("Error retrieving user data.", 0, $e);
            return null; // Return null on error to indicate user not found/accessible
        }
    }

     /**
      * Placeholder for logging failed login attempts. Needs implementation.
      * @param string $username
      * @param string $ip
      */
     public function logFailedLoginAttempt(string $username, string $ip): void {
          $this->logger->debug("Placeholder: Logging failed login attempt", ['username' => $username, 'ip' => $ip]);
          // Implement SQL INSERT INTO login_attempts ...
          try {
               $sql = "INSERT INTO login_attempts (username, ip_address, success, attempt_time) VALUES (:username, :ip, 0, NOW())";
               $stmt = $this->db->prepare($sql);
               $stmt->execute([':username' => $username, ':ip' => $ip]);
          } catch (Throwable $e) {
               $this->logger->error("Failed to log failed login attempt.", ['exception' => $e]);
          }
     }

     /**
     * Placeholder for getting user password hash. Needs implementation.
     * @param int $userId
     * @return string|null
     */
    public function getUserPasswordHash(int $userId): ?string {
         $this->logger->debug("Fetching password hash for user.", ['user_id' => $userId]);
         try {
              $stmt = $this->db->prepare("SELECT password_hash FROM users WHERE id = :id");
              $stmt->bindParam(':id', $userId, PDO::PARAM_INT);
              $stmt->execute();
              $result = $stmt->fetch(PDO::FETCH_ASSOC);
              return $result ? $result['password_hash'] : null;
         } catch (Throwable $e) {
              $this->logger->error("Error fetching password hash.", ['user_id' => $userId, 'exception' => $e]);
              return null;
         }
    }

     /**
     * Placeholder for updating user password hash. Needs implementation.
     * @param int $userId
     * @param string $newHash
     * @return bool
     */
     public function updateUserPassword(int $userId, string $newHash): bool {
          $this->logger->info("Updating password hash for user.", ['user_id' => $userId]);
          try {
               $stmt = $this->db->prepare("UPDATE users SET password_hash = :hash WHERE id = :id");
               $stmt->bindParam(':hash', $newHash);
               $stmt->bindParam(':id', $userId, PDO::PARAM_INT);
               return $stmt->execute();
          } catch (Throwable $e) {
               $this->logger->error("Error updating password hash.", ['user_id' => $userId, 'exception' => $e]);
               return false;
          }
     }

     /**
     * Placeholder for activating user. Needs implementation.
     * @param string $activationCode
     * @return string ('success', 'not_found', 'already_active')
     */
      public function activateUserByCode(string $activationCode): string {
          $this->logger->info("Attempting to activate user by code.", ['code_prefix' => substr($activationCode, 0, 5)]);
          try {
              $stmt = $this->db->prepare("SELECT id, is_active FROM users WHERE activation_code = :code");
              $stmt->bindParam(':code', $activationCode);
              $stmt->execute();
              $user = $stmt->fetch(PDO::FETCH_ASSOC);

              if (!$user) {
                   return 'not_found';
              }
              if ($user['is_active'] == 1) {
                   return 'already_active';
              }

              $updateStmt = $this->db->prepare("UPDATE users SET is_active = 1, activation_code = NULL WHERE id = :id AND is_active = 0");
              $updateStmt->bindParam(':id', $user['id'], PDO::PARAM_INT);
              $success = $updateStmt->execute();

              return $success ? 'success' : 'not_found'; // Or throw exception on update failure

          } catch (Throwable $e) {
              $this->logger->error("Error activating user by code.", ['exception' => $e]);
               throw $e; // Rethrow DB errors
          }
      }
    /**
     * Counts the number of active users.
     *
     * @return int The count of active users.
     * @throws \PDOException On database error.
     */
    public function countActiveUsers(): int
    {
        $this->logger->debug("Counting active users.");
        try {
            $sql = "SELECT COUNT(*) FROM users WHERE is_active = 1";
            $stmt = $this->db->query($sql); // Simple query, no parameters needed
            return (int)$stmt->fetchColumn();
        } catch (Throwable $e) {
            $this->logger->error("Database error counting active users.", ['exception' => $e]);
            throw new \Exception("Error counting active users.", 0, $e); // Rethrow
        }
    }

    /**
     * Finds a user by their ID.
     *
     * @param int $id The user ID.
     * @return array|null User data as an associative array if found, null otherwise.
     */
    public function getUserById(int $id): ?array
    {
        $this->logger->debug("Attempting to fetch user by ID.", ['user_id' => $id]);
        try {
            $sql = "SELECT u.*, r.role_name
                    FROM users u
                    LEFT JOIN roles r ON u.role_id = r.id
                    WHERE u.id = :id";
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user) {
                $this->logger->debug("User found by ID.", ['user_id' => $id]);
                $user['is_active'] = isset($user['is_active']) ? (int)$user['is_active'] : 0;
                return $user;
            } else {
                $this->logger->debug("User not found by ID.", ['user_id' => $id]);
                return null;
            }
        } catch (Throwable $e) {
            $this->logger->error("Database error fetching user by ID.", ['user_id' => $id, 'exception' => $e]);
            return null;
        }
    }

    /**
     * دریافت همه کاربران به همراه نقش آنها
     * @return array
     */
    public function getAllUsersWithRole(): array
    {
        $this->logger->debug("Fetching all users with their roles.");
        try {
            $sql = "SELECT u.*, r.role_name
                    FROM users u
                    LEFT JOIN roles r ON u.role_id = r.id
                    ORDER BY u.id DESC";
            $stmt = $this->db->query($sql);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            $this->logger->error("Database error fetching all users with roles.", ['exception' => $e]);
            return [];
        }
    }

    /**
     * دریافت همه نقش‌های موجود در سیستم
     * @return array
     */
    public function getAllRoles(): array
    {
        $this->logger->debug("Fetching all roles.");
        try {
            $sql = "SELECT id, role_name FROM roles ORDER BY id ASC";
            $stmt = $this->db->query($sql);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            $this->logger->error("Database error fetching roles.", ['exception' => $e]);
            return [];
        }
    }

    // Add other required methods like saveUser, deleteUser, updateUserStatus, getAllRoles etc. here
    // Ensure these methods exist and have the correct signature based on Controller calls.

} // End UserRepository