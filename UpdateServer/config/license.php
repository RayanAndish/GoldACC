<?php

return [

    /*
    |--------------------------------------------------------------------------
    | License Secret Key
    |--------------------------------------------------------------------------
    |
    | This key is used for encrypting/decrypting communication with clients
    | and potentially for hashing license details. Keep it secret!
    |
    */

    'secret' => env('LICENSE_SECRET', 'change-this-default-secret'), // Fallback is important!

    /*
    |--------------------------------------------------------------------------
    | Hashing Iterations
    |--------------------------------------------------------------------------
    |
    | Number of iterations used for the hash_pbkdf2 function when hashing
    | license parameters like domain, hardware ID, etc., using the salt.
    | Higher numbers are more secure but slower.
    |
    */

    'iterations' => (int) env('LICENSE_HASH_ITERATIONS', 10000),

    /*
    |--------------------------------------------------------------------------
    | Display Key Length
    |--------------------------------------------------------------------------
    |
    | How many characters of the plain license key prefix to store in
    | the license_key_display column.
    |
    */
    'display_key_prefix_length' => 8,

    /*
    |--------------------------------------------------------------------------
    | Encryption Method
    |--------------------------------------------------------------------------
    |
    | The encryption cipher to use for API communication.
    | Ensure the client uses the same method.
    |
    */
    'encryption_cipher' => 'AES-256-CBC',

];
