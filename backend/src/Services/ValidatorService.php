<?php

declare(strict_types=1);

namespace App\Services;

use App\Middleware\ValidationMiddleware;

/**
 * Validator Service
 * Provides validation functionality for use in controllers
 */
class ValidatorService
{
    private ValidationMiddleware $validator;

    public function __construct()
    {
        $this->validator = new ValidationMiddleware();
    }

    /**
     * Validate data against rules
     *
     * @param array $data Data to validate
     * @param array $rules Validation rules
     * @return array Validation errors (empty if valid)
     */
    public function validate(array $data, array $rules): array
    {
        return $this->validator->validate($data, $rules);
    }

    /**
     * Check if data is valid
     *
     * @param array $data Data to validate
     * @param array $rules Validation rules
     * @return bool True if valid
     */
    public function isValid(array $data, array $rules): bool
    {
        return empty($this->validate($data, $rules));
    }

    /**
     * Predefined validation rules for common endpoints
     */

    // User Registration
    public static function userRegistrationRules(): array
    {
        return [
            'phone' => 'required|phone',
            'pin' => 'required|pin',
            'type' => 'required|in:helper,employer'
        ];
    }

    // User Login
    public static function loginRules(): array
    {
        return [
            'phone' => 'required|phone',
            'pin' => 'required|min:4'
        ];
    }

    // Admin Login
    public static function adminLoginRules(): array
    {
        return [
            'email' => 'required|email',
            'password' => 'required|min:6'
        ];
    }

    // Helper Profile
    public static function helperProfileRules(): array
    {
        return [
            'full_name' => 'required|min:2|max:100',
            'date_of_birth' => 'required|date',
            'gender' => 'required|in:male,female',
            'marital_status' => 'in:single,married,divorced,widowed',
            'work_type' => 'required|work_type',
            'accommodation' => 'in:live-in,live-out',
            'location' => 'required',
            'salary_min' => 'required|salary',
            'salary_max' => 'salary',
            'experience_years' => 'integer|min:0|max:50',
            'phone' => 'phone',
            'nin' => 'nin'
        ];
    }

    // Booking Creation
    public static function bookingRules(): array
    {
        return [
            'helper_id' => 'required|integer',
            'start_date' => 'required|date|after:today',
            'work_type' => 'work_type',
            'monthly_rate' => 'required|salary',
            'notes' => 'max:1000'
        ];
    }

    // Payment Initialization
    public static function paymentInitRules(): array
    {
        return [
            'booking_id' => 'required|integer',
            'gateway' => 'in:flutterwave,paystack'
        ];
    }

    // Payment Verification
    public static function paymentVerifyRules(): array
    {
        return [
            'tx_ref' => 'required|max:100',
            'transaction_id' => 'max:100',
            'gateway' => 'in:flutterwave,paystack'
        ];
    }

    // Rating
    public static function ratingRules(): array
    {
        return [
            'helper_id' => 'required|integer',
            'booking_id' => 'required|integer',
            'rating' => 'required|integer|between:1,5',
            'review' => 'max:1000'
        ];
    }

    // Lead Capture
    public static function leadCaptureRules(): array
    {
        return [
            'phone' => 'required|phone',
            'name' => 'max:100',
            'email' => 'email',
            'source' => 'max:50',
            'work_type' => 'work_type',
            'location' => 'max:100',
            'budget_min' => 'integer|min:0',
            'budget_max' => 'integer|min:0'
        ];
    }

    // NIN Verification
    public static function ninVerificationRules(): array
    {
        return [
            'helper_id' => 'required|integer',
            'nin' => 'required|nin',
            'first_name' => 'required|min:2|max:50',
            'last_name' => 'required|min:2|max:50',
            'date_of_birth' => 'required|date'
        ];
    }

    // OTP Send
    public static function otpSendRules(): array
    {
        return [
            'phone' => 'phone',
            'email' => 'email',
            'type' => 'required|in:verification,pin_reset,login'
        ];
    }

    // OTP Verify
    public static function otpVerifyRules(): array
    {
        return [
            'phone' => 'phone',
            'email' => 'email',
            'code' => 'required|min:4|max:6'
        ];
    }

    // PIN Reset
    public static function pinResetRules(): array
    {
        return [
            'phone' => 'required|phone',
            'code' => 'required|min:4|max:6',
            'new_pin' => 'required|pin'
        ];
    }

    // Bank Details
    public static function bankDetailsRules(): array
    {
        return [
            'bank_code' => 'required|max:10',
            'account_number' => 'required|regex:/^\d{10}$/',
            'account_name' => 'required|min:2|max:100'
        ];
    }

    // Admin User Creation
    public static function adminUserRules(): array
    {
        return [
            'name' => 'required|min:2|max:100',
            'email' => 'required|email',
            'password' => 'required|min:8',
            'role_id' => 'required|integer'
        ];
    }

    // Settings Update
    public static function settingsRules(): array
    {
        return [
            'key' => 'required|alpha_dash|max:50',
            'value' => 'required',
            'category' => 'required|alpha_dash|max:50'
        ];
    }

    // Helper Search/Filter
    public static function helperSearchRules(): array
    {
        return [
            'location' => 'max:100',
            'work_type' => 'work_type',
            'salary_min' => 'integer|min:0',
            'salary_max' => 'integer|min:0',
            'gender' => 'in:male,female',
            'accommodation' => 'in:live-in,live-out',
            'verification' => 'in:verified,pending,all',
            'badge' => 'in:bronze,silver,gold,all',
            'page' => 'integer|min:1',
            'limit' => 'integer|min:1|max:100'
        ];
    }

    // Contact Form
    public static function contactFormRules(): array
    {
        return [
            'name' => 'required|min:2|max:100',
            'email' => 'required|email',
            'phone' => 'phone',
            'subject' => 'required|min:5|max:200',
            'message' => 'required|min:10|max:5000'
        ];
    }
}
