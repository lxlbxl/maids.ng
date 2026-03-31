private function migrate_014_add_bank_details_to_helpers(): void
{
// Add bank details columns to helpers table
$columns = [
'bank_code VARCHAR(10)',
'account_number VARCHAR(20)',
'account_name VARCHAR(255)',
'subaccount_id VARCHAR(100)' // Flutterwave subaccount ID
];

foreach ($columns as $column) {
try {
$this->pdo->exec("ALTER TABLE helpers ADD COLUMN $column");
} catch (\Exception $e) {
// Column might already exist, ignore
}
}
}

private function migrate_015_update_payments_table(): void
{
// Add payment type and commission columns
$columns = [
"payment_type VARCHAR(50) DEFAULT 'service_fee'", // salary, service_fee, matching_fee
"commission_amount INTEGER DEFAULT 0",
"helper_amount INTEGER DEFAULT 0"
];

foreach ($columns as $column) {
try {
$this->pdo->exec("ALTER TABLE payments ADD COLUMN $column");
} catch (\Exception $e) {
// Column might already exist, ignore
}
}

$this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_payments_type ON payments(payment_type)");
}

private function migrate_016_update_settings_for_commission(): void
{
// Add commission percentage setting
$stmt = $this->pdo->prepare("INSERT OR IGNORE INTO settings (key_name, value, category) VALUES (?, ?, ?)");
$stmt->execute(['commission_percent', '10', 'payments']); // Default 10%
}
}