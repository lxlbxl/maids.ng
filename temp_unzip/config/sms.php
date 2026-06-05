<?php

return [

    /*
    |--------------------------------------------------------------------------
    | SMS Templates
    |--------------------------------------------------------------------------
    |
    | Parameterized SMS templates used by the NotificationService.
    | Placeholders: {name}, {amount}, {date}, {maid_name}, {employer_name},
    |               {assignment_id}, {due_date}, {days}, {platform}
    |
    */

    'templates' => [

        // ── Assignment Lifecycle ──

        'assignment_matched' => 'Hi {employer_name}, we found a match for you! {maid_name} is available and fits your requirements. Log in to review: {url}',

        'assignment_accepted' => 'Great news {employer_name}! {maid_name} has been assigned to you. Employment starts on {date}. Salary: N{amount}/month.',

        'assignment_rejected' => 'Hi {employer_name}, your assignment with {maid_name} was declined. We are searching for another match for you.',

        'assignment_completed' => 'Hi {employer_name}, the assignment with {maid_name} has been completed. Please leave a review on the platform.',

        'maid_new_assignment' => 'Hi {maid_name}, you have a new assignment with {employer_name}! Log in to view details and accept: {url}',

        'maid_assignment_confirmed' => 'Congratulations {maid_name}! Your assignment with {employer_name} is confirmed. Start date: {date}.',

        // ── Salary Reminders ──

        'salary_reminder_3day' => 'Hi {employer_name}, salary of N{amount} for {maid_name} is due in 3 days ({due_date}). Please ensure your wallet is funded.',

        'salary_reminder_1day' => 'Reminder: {employer_name}, salary of N{amount} for {maid_name} is due tomorrow ({due_date}). Fund your wallet now to avoid delays.',

        'salary_due_today' => 'Hi {employer_name}, salary of N{amount} for {maid_name} is due today. If your wallet is funded, payment will be processed automatically.',

        'salary_overdue' => 'OVERDUE: {employer_name}, salary of N{amount} for {maid_name} is {days} days overdue. Please fund your wallet immediately to avoid escalation.',

        'salary_paid_employer' => 'Payment confirmed: N{amount} has been deducted from your wallet for {maid_name}\'s salary.',

        'salary_paid_maid' => 'Hi {maid_name}, your salary of N{amount} from {employer_name} has been credited to your wallet. Thank you!',

        // ── Wallet & Payments ──

        'wallet_funded' => 'Hi {name}, your wallet has been credited with N{amount}. New balance: N{balance}.',

        'withdrawal_requested' => 'Hi {name}, your withdrawal request of N{amount} has been submitted and is pending approval.',

        'withdrawal_approved' => 'Hi {name}, your withdrawal of N{amount} has been approved and is being processed to your bank account.',

        'withdrawal_completed' => 'Hi {name}, N{amount} has been sent to your bank account. Please allow 24hrs for settlement.',

        'withdrawal_rejected' => 'Hi {name}, your withdrawal of N{amount} was declined. Reason: {reason}. Contact support if you need help.',

        // ── Verification ──

        'verification_complete' => 'Hi {name}, your NIN verification is complete. Status: {status}. Log in to view your full report.',

        'verification_failed' => 'Hi {name}, your verification could not be completed. Please ensure your NIN details are correct and try again.',

        // ── General ──

        'welcome' => 'Welcome to {platform}, {name}! Your account has been created. Log in to get started: {url}',

        'otp' => 'Your {platform} verification code is {code}. Valid for 10 minutes. Do not share this code.',

        'escalation_admin' => 'ADMIN ALERT: Salary for assignment #{assignment_id} is {days} days overdue. Employer: {employer_name}. Amount: N{amount}. Escalation level: {level}.',
    ],

];
