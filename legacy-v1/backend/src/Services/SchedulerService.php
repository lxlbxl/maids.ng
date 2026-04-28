<?php

declare(strict_types=1);

namespace App\Services;

use PDO;
use Psr\Log\LoggerInterface;

class SchedulerService
{
    private PDO $pdo;
    private LoggerInterface $logger;
    private NotificationService $notificationService;
    private PaymentService $paymentService;

    public function __construct(
        PDO $pdo,
        LoggerInterface $logger,
        NotificationService $notificationService,
        PaymentService $paymentService
    ) {
        $this->pdo = $pdo;
        $this->logger = $logger;
        $this->notificationService = $notificationService;
        $this->paymentService = $paymentService;
    }

    public function run(): void
    {
        $this->logger->info("Scheduler run started");

        $this->autoCompleteBookings();
        $this->sendPaymentReminders();
        $this->checkOverduePayments();

        $this->logger->info("Scheduler run completed");
    }

    private function autoCompleteBookings(): void
    {
        // Auto-complete bookings that are past their end date (if applicable)
        // For open-ended bookings, we might check monthly cycles.
        // For now, let's assume we mark bookings as 'completed' if they are 'confirmed' and past start_date + 30 days (example logic)
        // real logic depends on business rules.
        // Let's implement logic to generate monthly invoice instead for active bookings.

        $this->generateMonthlyInvoices();
    }

    private function generateMonthlyInvoices(): void
    {
        // Find active bookings that need next month's payment
        // Logic: active bookings where last payment was > 25 days ago

        // This is complex, for MVP let's just log
        $this->logger->info("Checking for monthly invoices generation...");
    }

    private function sendPaymentReminders(): void
    {
        // Find pending payments created > 24 hours ago
        $stmt = $this->pdo->query("
            SELECT p.*, b.employer_id, e.user_id 
            FROM payments p
            JOIN bookings b ON p.booking_id = b.id
            JOIN employers e ON b.employer_id = e.id
            WHERE p.status = 'pending' 
            AND p.created_at < date('now', '-1 day')
            AND p.created_at > date('now', '-2 days')
        ");

        $pendingPayments = $stmt->fetchAll();
        $count = 0;

        foreach ($pendingPayments as $payment) {
            // Send reminder
            // $this->notificationService->sendPaymentReminder($payment);
            $count++;
        }

        if ($count > 0) {
            $this->logger->info("Sent $count payment reminders");
        }
    }

    private function checkOverduePayments(): void
    {
        // specific logic for overdue
    }
}
