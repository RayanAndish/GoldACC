<?php

namespace App\Utils;

use Exception;
use Monolog\Logger;
use RuntimeException;

/**
 * Utility class for encrypting and decrypting files using OpenSSL AES-256-CBC.
 */
class Encryption {
    private Logger $logger;
    private string $encryption_key;
    private const CIPHER_ALGO = 'aes-256-cbc';
    // Increased buffer size for potentially better performance on large files
    private const int|float FILE_BUFFER_SIZE = 8192 * 2; // 16KB buffer

    /**
     * Constructor.
     *
     * @param Logger $logger
     * @param array $config Application configuration, expects ['encryption']['key_path'] or ['encryption']['key'].
     * @throws Exception If encryption key cannot be loaded or is invalid.
     */
    public function __construct(Logger $logger, array $config) {
        $this->logger = $logger;
        $this->encryption_key = $this->loadEncryptionKey($config);
        $this->logger->debug("Encryption service initialized.");
    }

    /**
     * Loads the encryption key from configuration.
     * Prefers key_path over a raw key in config for better security practice.
     *
     * @param array $config
     * @return string The encryption key.
     * @throws Exception If the key cannot be loaded or is invalid.
     */
    private function loadEncryptionKey(array $config): string {
        $keySource = "config";
        $key = null;

        if (!empty($config['encryption']['key_path'])) {
            $keyPath = $config['encryption']['key_path'];
            $keySource = $keyPath;
             // If key_path is relative, resolve it based on root_dir
             if (!self::isAbsolutePath($keyPath) && isset($config['paths']['root'])) {
                  $keyPath = $config['paths']['root'] . DIRECTORY_SEPARATOR . $keyPath;
             }

            $this->logger->debug("Attempting to load encryption key from file.", ['path' => $keyPath]);
            if (!file_exists($keyPath) || !is_readable($keyPath)) {
                $this->logger->error("Encryption key file not found or not readable.", ['path' => $keyPath]);
                throw new Exception("Encryption key file not found or not readable: {$keyPath}");
            }
            $key = trim(file_get_contents($keyPath));
        } elseif (!empty($config['encryption']['key'])) {
            $this->logger->warning("Loading encryption key directly from config array. Consider using 'key_path' for better security.");
            $key = $config['encryption']['key'];
        } else {
            $this->logger->error("Encryption key configuration is missing ('key_path' or 'key').");
            throw new Exception("Encryption key configuration is missing.");
        }

        if (empty($key)) {
            $this->logger->error("Loaded encryption key is empty.", ['source' => $keySource]);
            throw new Exception("Loaded encryption key is empty from source: {$keySource}");
        }

        // Basic validation: Check key length for AES-256 (32 bytes / 256 bits)
        // This assumes the key stored is the raw binary key or hex encoded.
        // If it's a password/passphrase, it should be hashed/derived first (outside this simple example).
        // For simplicity here, we'll assume the key is directly usable but check length if it looks like hex.
        $keyLength = strlen($key);
        if ($keyLength !== 32 && $keyLength !== 64) { // 32 bytes raw, 64 chars hex
             $this->logger->warning("Encryption key length is not typical for AES-256 (expected 32 bytes raw or 64 hex chars). Ensure it's correct.", ['length' => $keyLength]);
        }
        // If key looks like hex, decode it.
         if ($keyLength === 64 && ctype_xdigit($key)) {
             $decodedKey = hex2bin($key);
             if ($decodedKey === false) {
                 $this->logger->error("Failed to hex-decode the encryption key.", ['source' => $keySource]);
                 throw new Exception("Failed to hex-decode encryption key from source: {$keySource}");
             }
             $key = $decodedKey;
             $this->logger->info("Successfully loaded and hex-decoded encryption key.", ['source' => $keySource]);
         } else {
              $this->logger->info("Encryption key loaded successfully.", ['source' => $keySource]);
         }

         // Final check after potential decoding
         if (strlen($key) !== 32) {
             $this->logger->error("Final encryption key length is not 32 bytes (required for AES-256).", ['length' => strlen($key)]);
             throw new Exception("Final encryption key must be exactly 32 bytes long for AES-256.");
         }


        return $key;
    }

     /**
      * Checks if a path is absolute.
      * Handles both Windows (C:\, \\server) and Unix-like (/) paths.
      *
      * @param string $path
      * @return bool
      */
     private static function isAbsolutePath(string $path): bool {
         if (empty($path)) return false;
         // Check for *nix root /
         if ($path[0] === '/') return true;
         // Check for Windows drive letter C:\ or UNC path \\server
         if (preg_match('/^[a-zA-Z]:[\\\\\\/]/', $path)) return true; // Drive letter
         if (strpos($path, '\\\\') === 0) return true; // UNC path
         return false;
     }


    /**
     * Encrypts a file using AES-256-CBC.
     * Writes IV as the first 16 bytes of the output file.
     *
     * @param string $sourcePath Path to the source file.
     * @param string $destinationPath Path to the encrypted output file.
     * @throws RuntimeException If encryption fails.
     */
    public function encryptFile(string $sourcePath, string $destinationPath): void {
        $this->logger->debug("Starting file encryption.", ['source' => $sourcePath, 'destination' => $destinationPath]);

        if (!file_exists($sourcePath) || !is_readable($sourcePath)) {
            $this->logger->error("Encryption source file not found or not readable.", ['path' => $sourcePath]);
            throw new RuntimeException("Encryption source file not found or not readable: {$sourcePath}");
        }

        $ivLength = openssl_cipher_iv_length(self::CIPHER_ALGO);
        if ($ivLength === false) {
            $this->logger->error("Could not get IV length for cipher.", ['cipher' => self::CIPHER_ALGO]);
            throw new RuntimeException("Could not get IV length for cipher: " . self::CIPHER_ALGO);
        }
        $iv = openssl_random_pseudo_bytes($ivLength);
        if ($iv === false) {
             $this->logger->error("Failed to generate initialization vector (IV).");
             throw new RuntimeException("Failed to generate initialization vector (IV).");
        }

        $sourceHandle = fopen($sourcePath, 'rb');
        if (!$sourceHandle) {
            $this->logger->error("Failed to open source file for reading.", ['path' => $sourcePath]);
            throw new RuntimeException("Failed to open source file for reading: {$sourcePath}");
        }

        $destHandle = fopen($destinationPath, 'wb');
        if (!$destHandle) {
            fclose($sourceHandle); // Close source handle if dest fails
            $this->logger->error("Failed to open destination file for writing.", ['path' => $destinationPath]);
            throw new RuntimeException("Failed to open destination file for writing: {$destinationPath}");
        }

        // Write the IV to the beginning of the destination file
        if (fwrite($destHandle, $iv) !== $ivLength) {
             fclose($sourceHandle);
             fclose($destHandle);
             unlink($destinationPath); // Attempt cleanup
             $this->logger->error("Failed to write IV to destination file.", ['path' => $destinationPath]);
             throw new RuntimeException("Failed to write IV to destination file: {$destinationPath}");
        }


        $bytesEncrypted = 0;
        try {
            while (!feof($sourceHandle)) {
                $plaintext = fread($sourceHandle, self::FILE_BUFFER_SIZE);
                if ($plaintext === false) {
                     throw new RuntimeException("Failed to read from source file during encryption.");
                }

                $ciphertext = openssl_encrypt($plaintext, self::CIPHER_ALGO, $this->encryption_key, OPENSSL_RAW_DATA, $iv);
                if ($ciphertext === false) {
                     throw new RuntimeException("openssl_encrypt failed during file encryption. Error: " . openssl_error_string());
                }

                if (fwrite($destHandle, $ciphertext) === false) {
                    throw new RuntimeException("Failed to write ciphertext to destination file.");
                }
                $bytesEncrypted += strlen($plaintext); // Track original bytes processed
            }

            // Finalize encryption (handle potential padding with OPENSSL_RAW_DATA)
            // Note: For stream encryption like this, explicit finalization isn't needed like in block modes w/ padding if OPENSSL_ZERO_PADDING wasn't used.
            // AES-CBC with PKCS#7 padding (default) handles padding automatically.

            $this->logger->info("File encryption completed successfully.", ['source' => $sourcePath, 'destination' => $destinationPath, 'bytes_processed' => $bytesEncrypted]);

        } catch (Throwable $e) {
             $this->logger->error("Error during file encryption stream processing.", ['exception' => $e]);
             // Rethrow as RuntimeException
             throw new RuntimeException("Encryption stream error: " . $e->getMessage(), $e->getCode(), $e);
        } finally {
            // Ensure handles are closed even on error
             if ($sourceHandle) fclose($sourceHandle);
             if ($destHandle) fclose($destHandle);

             // If an error occurred after starting write, delete the potentially corrupted destination file
             if (isset($e) && file_exists($destinationPath)) {
                  $this->logger->warning("Deleting potentially corrupted destination file due to encryption error.", ['path' => $destinationPath]);
                  unlink($destinationPath);
             }
        }
    }

    /**
     * Decrypts a file encrypted with encryptFile().
     * Reads IV from the first 16 bytes of the source file.
     *
     * @param string $sourcePath Path to the encrypted source file.
     * @param string $destinationPath Path to the decrypted output file.
     * @throws RuntimeException If decryption fails.
     */
    public function decryptFile(string $sourcePath, string $destinationPath): void {
        $this->logger->debug("Starting file decryption.", ['source' => $sourcePath, 'destination' => $destinationPath]);

        if (!file_exists($sourcePath) || !is_readable($sourcePath)) {
            $this->logger->error("Decryption source file not found or not readable.", ['path' => $sourcePath]);
            throw new RuntimeException("Decryption source file not found or not readable: {$sourcePath}");
        }

        $ivLength = openssl_cipher_iv_length(self::CIPHER_ALGO);
        if ($ivLength === false) {
             $this->logger->error("Could not get IV length for cipher.", ['cipher' => self::CIPHER_ALGO]);
            throw new RuntimeException("Could not get IV length for cipher: " . self::CIPHER_ALGO);
        }

        $sourceHandle = fopen($sourcePath, 'rb');
        if (!$sourceHandle) {
            $this->logger->error("Failed to open source file for reading.", ['path' => $sourcePath]);
            throw new RuntimeException("Failed to open source file for reading: {$sourcePath}");
        }

        // Read the IV from the beginning of the source file
        $iv = fread($sourceHandle, $ivLength);
        if ($iv === false || strlen($iv) !== $ivLength) {
            fclose($sourceHandle);
            $this->logger->error("Failed to read IV from source file or IV length mismatch.", ['path' => $sourcePath, 'expected_length' => $ivLength]);
            throw new RuntimeException("Failed to read IV from source file or IV length mismatch: {$sourcePath}");
        }

        $destHandle = fopen($destinationPath, 'wb');
        if (!$destHandle) {
            fclose($sourceHandle);
            $this->logger->error("Failed to open destination file for writing.", ['path' => $destinationPath]);
            throw new RuntimeException("Failed to open destination file for writing: {$destinationPath}");
        }

        $bytesDecrypted = 0;
        try {
            while (!feof($sourceHandle)) {
                // Read slightly more than buffer size to potentially include padding for last block
                $ciphertext = fread($sourceHandle, self::FILE_BUFFER_SIZE + $ivLength); // Read buffer + possible block size
                if ($ciphertext === false) {
                    throw new RuntimeException("Failed to read ciphertext from source file during decryption.");
                }
                if (empty($ciphertext)) {
                    break; // End of file
                }

                $plaintext = openssl_decrypt($ciphertext, self::CIPHER_ALGO, $this->encryption_key, OPENSSL_RAW_DATA, $iv);
                if ($plaintext === false) {
                     // It's possible the last block failed due to padding errors if the file is corrupt/tampered
                     // Log the openssl error
                     $openSSLError = openssl_error_string();
                     throw new RuntimeException("openssl_decrypt failed during file decryption. Possible data corruption or incorrect key. Error: " . $openSSLError);
                }

                if (fwrite($destHandle, $plaintext) === false) {
                    throw new RuntimeException("Failed to write plaintext to destination file.");
                }
                 $bytesDecrypted += strlen($plaintext);
            }

            $this->logger->info("File decryption completed successfully.", ['source' => $sourcePath, 'destination' => $destinationPath, 'bytes_processed' => $bytesDecrypted]);

        } catch (Throwable $e) {
             $this->logger->error("Error during file decryption stream processing.", ['exception' => $e]);
             // Rethrow as RuntimeException
             throw new RuntimeException("Decryption stream error: " . $e->getMessage(), $e->getCode(), $e);
        } finally {
            // Ensure handles are closed even on error
             if ($sourceHandle) fclose($sourceHandle);
             if ($destHandle) fclose($destHandle);

             // If an error occurred after starting write, delete the potentially corrupted destination file
             if (isset($e) && file_exists($destinationPath)) {
                  $this->logger->warning("Deleting potentially corrupted destination file due to decryption error.", ['path' => $destinationPath]);
                  unlink($destinationPath);
             }
        }
    }
} // End Encryption class
