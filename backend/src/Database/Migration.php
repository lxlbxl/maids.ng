<?php

declare(strict_types=1);

namespace App\Database;

use PDO;

class Migration
{
    private PDO $pdo;
    private bool $isSqlite;

    public function __construct(PDO $pdo, bool $isSqlite = true)
    {
        $this->pdo = $pdo;
        $this->isSqlite = $isSqlite;
    }

    public function run(): void
    {
        $this->createMigrationsTable();
        $this->runMigrations();
    }

    private function createMigrationsTable(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS migrations (
            id INTEGER PRIMARY KEY " . ($this->isSqlite ? "AUTOINCREMENT" : "AUTO_INCREMENT") . ",
            migration VARCHAR(255) NOT NULL,
            batch INTEGER NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )";
        $this->pdo->exec($sql);
    }

    private function runMigrations(): void
    {
        $migrations = $this->getMigrations();
        $executed = $this->getExecutedMigrations();
        $batch = $this->getNextBatch();

        foreach ($migrations as $migration) {
            if (!in_array($migration, $executed)) {
                $this->executeMigration($migration, $batch);
                echo "Migrated: {$migration}\n";
            }
        }
    }

    private function getMigrations(): array
    {
        return [
            '001_create_users_table',
            '002_create_helpers_table',
            '003_create_employers_table',
            '004_create_bookings_table',
            '005_create_payments_table',
            '006_create_ratings_table',
            '007_create_verifications_table',
            '008_create_leads_table',
            '009_create_settings_table',
            '010_create_admin_users_table',
            '011_create_roles_table',
            '012_create_permissions_table',
            '013_create_webhook_logs_table',
            '014_add_bank_details_to_helpers',
            '015_update_payments_table',
            '016_update_settings_for_commission',
            '017_create_agency_profiles_table',
            '018_create_disputes_table',
            '019_create_messages_table',
            '020_add_checkout_url_to_payments',
            '021_create_helper_availability_table',
            '022_create_service_requests_table',
        ];
    }

    private function migrate_022_create_service_requests_table(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS service_requests (
            id INTEGER PRIMARY KEY {$this->autoIncrement()},
            user_id INTEGER,
            phone VARCHAR(20) NOT NULL,
            full_name VARCHAR(255),
            help_type VARCHAR(50),
            location VARCHAR(255),
            accommodation_preference VARCHAR(20),
            budget_min INTEGER,
            budget_max INTEGER,
            start_date DATE,
            additional_notes TEXT,
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            matched_helper_ids TEXT,
            admin_notes TEXT,
            ip_address VARCHAR(45),
            created_at {$this->timestamp()},
            updated_at {$this->timestamp()},
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
        )";
        $this->pdo->exec($sql);

        $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_service_requests_phone ON service_requests(phone)");
        $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_service_requests_status ON service_requests(status)");
        $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_service_requests_user_id ON service_requests(user_id)");
    }

    private function migrate_021_create_helper_availability_table(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS helper_availability (
            id INTEGER PRIMARY KEY {$this->autoIncrement()},
            helper_id INTEGER NOT NULL,
            day_of_week INTEGER NOT NULL CHECK(day_of_week BETWEEN 0 AND 6), -- 0=Sunday, 6=Saturday
            start_time TIME NOT NULL,
            end_time TIME NOT NULL,
            is_active INTEGER DEFAULT 1,
            created_at {$this->timestamp()},
            updated_at {$this->timestamp()},
            FOREIGN KEY (helper_id) REFERENCES helpers(id) ON DELETE CASCADE
        )";
        $this->pdo->exec($sql);

        $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_availability_helper_day ON helper_availability(helper_id, day_of_week)");
    }

    private function getExecutedMigrations(): array
    {
        $stmt = $this->pdo->query("SELECT migration FROM migrations");
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    private function getNextBatch(): int
    {
        $stmt = $this->pdo->query("SELECT MAX(batch) as batch FROM migrations");
        $result = $stmt->fetch();
        return ($result['batch'] ?? 0) + 1;
    }

    private function executeMigration(string $migration, int $batch): void
    {
        $method = 'migrate_' . $migration;
        if (method_exists($this, $method)) {
            $this->$method();

            $stmt = $this->pdo->prepare("INSERT INTO migrations (migration, batch) VALUES (?, ?)");
            $stmt->execute([$migration, $batch]);
        }
    }

    private function autoIncrement(): string
    {
        return $this->isSqlite ? "AUTOINCREMENT" : "AUTO_INCREMENT";
    }

    private function timestamp(): string
    {
        return $this->isSqlite ? "DATETIME DEFAULT CURRENT_TIMESTAMP" : "TIMESTAMP DEFAULT CURRENT_TIMESTAMP";
    }

    private function json(): string
    {
        return $this->isSqlite ? "TEXT" : "JSON";
    }

    private function migrate_001_create_users_table(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY {$this->autoIncrement()},
            phone VARCHAR(20) NOT NULL UNIQUE,
            pin_hash VARCHAR(255),
            user_type VARCHAR(20) NOT NULL DEFAULT 'employer',
            status VARCHAR(20) NOT NULL DEFAULT 'active',
            created_at {$this->timestamp()},
            updated_at {$this->timestamp()}
        )";
        $this->pdo->exec($sql);

        $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_users_phone ON users(phone)");
        $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_users_type ON users(user_type)");
    }

    private function migrate_002_create_helpers_table(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS helpers (
            id INTEGER PRIMARY KEY {$this->autoIncrement()},
            user_id INTEGER NOT NULL,
            full_name VARCHAR(255) NOT NULL,
            first_name VARCHAR(100),
            last_name VARCHAR(100),
            work_type VARCHAR(50) NOT NULL,
            accommodation VARCHAR(20),
            location VARCHAR(255),
            location_state VARCHAR(100),
            location_lga VARCHAR(100),
            salary_min INTEGER DEFAULT 30000,
            salary_max INTEGER DEFAULT 60000,
            availability VARCHAR(50),
            availability_date DATE,
            experience TEXT,
            experience_years INTEGER DEFAULT 0,
            skills {$this->json()},
            profile_photo VARCHAR(255),
            voice_intro VARCHAR(255),
            bio TEXT,
            languages {$this->json()},
            verification_status VARCHAR(20) DEFAULT 'pending',
            badge_level VARCHAR(20) DEFAULT 'bronze',
            rating_avg DECIMAL(3,2) DEFAULT 0.00,
            rating_count INTEGER DEFAULT 0,
            invites_count INTEGER DEFAULT 0,
            jobs_completed INTEGER DEFAULT 0,
            status VARCHAR(20) DEFAULT 'active',
            nin_number VARCHAR(20),
            date_of_birth DATE,
            gender VARCHAR(10),
            marital_status VARCHAR(20),
            created_at {$this->timestamp()},
            updated_at {$this->timestamp()},
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )";
        $this->pdo->exec($sql);

        $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_helpers_work_type ON helpers(work_type)");
        $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_helpers_location ON helpers(location)");
        $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_helpers_status ON helpers(status)");
        $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_helpers_verification ON helpers(verification_status)");
    }

    private function migrate_003_create_employers_table(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS employers (
            id INTEGER PRIMARY KEY {$this->autoIncrement()},
            user_id INTEGER NOT NULL,
            full_name VARCHAR(255),
            location VARCHAR(255),
            location_state VARCHAR(100),
            location_lga VARCHAR(100),
            help_type VARCHAR(50),
            accommodation_preference VARCHAR(20),
            budget_min INTEGER,
            budget_max INTEGER,
            start_date DATE,
            start_urgency VARCHAR(50),
            additional_requirements TEXT,
            created_at {$this->timestamp()},
            updated_at {$this->timestamp()},
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )";
        $this->pdo->exec($sql);
    }

    private function migrate_004_create_bookings_table(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS bookings (
            id INTEGER PRIMARY KEY {$this->autoIncrement()},
            reference VARCHAR(50) NOT NULL UNIQUE,
            employer_id INTEGER NOT NULL,
            helper_id INTEGER NOT NULL,
            status VARCHAR(20) DEFAULT 'pending',
            service_fee INTEGER NOT NULL,
            monthly_rate INTEGER,
            start_date DATE,
            notes TEXT,
            cancelled_reason TEXT,
            completed_at DATETIME,
            created_at {$this->timestamp()},
            updated_at {$this->timestamp()},
            FOREIGN KEY (employer_id) REFERENCES employers(id) ON DELETE CASCADE,
            FOREIGN KEY (helper_id) REFERENCES helpers(id) ON DELETE CASCADE
        )";
        $this->pdo->exec($sql);

        $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_bookings_reference ON bookings(reference)");
        $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_bookings_status ON bookings(status)");
    }

    private function migrate_005_create_payments_table(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS payments (
            id INTEGER PRIMARY KEY {$this->autoIncrement()},
            booking_id INTEGER NOT NULL,
            amount INTEGER NOT NULL,
            currency VARCHAR(10) DEFAULT 'NGN',
            gateway VARCHAR(20) NOT NULL,
            tx_ref VARCHAR(100) NOT NULL UNIQUE,
            gateway_ref VARCHAR(100),
            status VARCHAR(20) DEFAULT 'pending',
            payment_method VARCHAR(50),
            metadata {$this->json()},
            paid_at DATETIME,
            created_at {$this->timestamp()},
            updated_at {$this->timestamp()},
            FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE
        )";
        $this->pdo->exec($sql);

        $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_payments_tx_ref ON payments(tx_ref)");
        $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_payments_status ON payments(status)");
    }

    private function migrate_006_create_ratings_table(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS ratings (
            id INTEGER PRIMARY KEY {$this->autoIncrement()},
            helper_id INTEGER NOT NULL,
            employer_id INTEGER NOT NULL,
            booking_id INTEGER,
            rating INTEGER NOT NULL CHECK(rating >= 1 AND rating <= 5),
            review TEXT,
            is_verified INTEGER DEFAULT 0,
            created_at {$this->timestamp()},
            FOREIGN KEY (helper_id) REFERENCES helpers(id) ON DELETE CASCADE,
            FOREIGN KEY (employer_id) REFERENCES employers(id) ON DELETE CASCADE,
            FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE SET NULL
        )";
        $this->pdo->exec($sql);
    }

    private function migrate_007_create_verifications_table(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS verifications (
            id INTEGER PRIMARY KEY {$this->autoIncrement()},
            helper_id INTEGER NOT NULL,
            document_type VARCHAR(50) NOT NULL,
            document_path VARCHAR(255),
            nin_number VARCHAR(20),
            status VARCHAR(20) DEFAULT 'pending',
            qoreid_response {$this->json()},
            verified_by INTEGER,
            verified_at DATETIME,
            rejection_reason TEXT,
            created_at {$this->timestamp()},
            updated_at {$this->timestamp()},
            FOREIGN KEY (helper_id) REFERENCES helpers(id) ON DELETE CASCADE
        )";
        $this->pdo->exec($sql);

        $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_verifications_status ON verifications(status)");
    }

    private function migrate_008_create_leads_table(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS leads (
            id INTEGER PRIMARY KEY {$this->autoIncrement()},
            phone VARCHAR(20) NOT NULL,
            source VARCHAR(50),
            step VARCHAR(50),
            flow_type VARCHAR(20),
            user_agent TEXT,
            ip_address VARCHAR(45),
            converted INTEGER DEFAULT 0,
            converted_at DATETIME,
            created_at {$this->timestamp()}
        )";
        $this->pdo->exec($sql);

        $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_leads_phone ON leads(phone)");
    }

    private function migrate_009_create_settings_table(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS settings (
            id INTEGER PRIMARY KEY {$this->autoIncrement()},
            key_name VARCHAR(100) NOT NULL UNIQUE,
            value {$this->json()},
            category VARCHAR(50) DEFAULT 'general',
            updated_at {$this->timestamp()}
        )";
        $this->pdo->exec($sql);

        // Insert default settings
        $defaults = [
            ['service_fee', '{"amount": 10000, "currency": "NGN"}', 'payments'],
            ['contact', '{"phone": "+234-XXX-XXX-XXXX", "whatsapp": "+234-XXX-XXX-XXXX", "email": "support@maids.ng"}', 'general'],
            ['locations', '["Lagos Mainland", "Lagos Island", "Ikeja", "Victoria Island", "Lekki", "Ajah", "Ikoyi", "Surulere", "Yaba", "Abuja"]', 'general'],
            ['work_types', '["Fulltime Maid", "Cleaner", "Cook", "Nanny", "Housekeeper", "Driver", "Gardener"]', 'general'],
            ['skills', '["Cooking", "Cleaning", "Childcare", "Laundry", "Ironing", "Driving", "Gardening", "Pet Care", "Elderly Care"]', 'general'],
        ];

        $stmt = $this->pdo->prepare("INSERT OR IGNORE INTO settings (key_name, value, category) VALUES (?, ?, ?)");
        foreach ($defaults as $setting) {
            $stmt->execute($setting);
        }
    }

    private function migrate_010_create_admin_users_table(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS admin_users (
            id INTEGER PRIMARY KEY {$this->autoIncrement()},
            email VARCHAR(255) NOT NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL,
            name VARCHAR(100) NOT NULL,
            role_id INTEGER,
            status VARCHAR(20) DEFAULT 'active',
            last_login DATETIME,
            login_attempts INTEGER DEFAULT 0,
            locked_until DATETIME,
            created_at {$this->timestamp()},
            updated_at {$this->timestamp()}
        )";
        $this->pdo->exec($sql);
    }

    private function migrate_011_create_roles_table(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS roles (
            id INTEGER PRIMARY KEY {$this->autoIncrement()},
            name VARCHAR(50) NOT NULL UNIQUE,
            description TEXT,
            is_system INTEGER DEFAULT 0,
            created_at {$this->timestamp()},
            updated_at {$this->timestamp()}
        )";
        $this->pdo->exec($sql);

        // Insert default roles
        $roles = [
            ['super_admin', 'Full system access', 1],
            ['admin', 'Standard admin access', 1],
            ['staff', 'Limited staff access', 1],
            ['viewer', 'Read-only access', 1],
        ];

        $stmt = $this->pdo->prepare("INSERT OR IGNORE INTO roles (name, description, is_system) VALUES (?, ?, ?)");
        foreach ($roles as $role) {
            $stmt->execute($role);
        }
    }

    private function migrate_012_create_permissions_table(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS permissions (
            id INTEGER PRIMARY KEY {$this->autoIncrement()},
            role_id INTEGER NOT NULL,
            resource VARCHAR(50) NOT NULL,
            action VARCHAR(20) NOT NULL,
            created_at {$this->timestamp()},
            FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
            UNIQUE(role_id, resource, action)
        )";
        $this->pdo->exec($sql);

        // Insert default permissions for super_admin (role_id = 1)
        $resources = ['helpers', 'employers', 'bookings', 'payments', 'verifications', 'users', 'roles', 'settings', 'reports'];
        $actions = ['create', 'read', 'update', 'delete'];

        $stmt = $this->pdo->prepare("INSERT OR IGNORE INTO permissions (role_id, resource, action) VALUES (?, ?, ?)");

        // Super admin gets all permissions
        foreach ($resources as $resource) {
            foreach ($actions as $action) {
                $stmt->execute([1, $resource, $action]);
            }
        }

        // Admin gets most permissions (no roles/users delete)
        foreach ($resources as $resource) {
            foreach ($actions as $action) {
                if (!in_array($resource, ['roles']) || $action !== 'delete') {
                    $stmt->execute([2, $resource, $action]);
                }
            }
        }

        // Staff gets limited permissions
        $staffResources = ['helpers', 'employers', 'bookings', 'verifications'];
        foreach ($staffResources as $resource) {
            $stmt->execute([3, $resource, 'read']);
            $stmt->execute([3, $resource, 'update']);
        }

        // Viewer gets read-only
        foreach ($resources as $resource) {
            $stmt->execute([4, $resource, 'read']);
        }
    }

    private function migrate_013_create_webhook_logs_table(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS webhook_logs (
            id INTEGER PRIMARY KEY {$this->autoIncrement()},
            event_type VARCHAR(50) NOT NULL,
            endpoint VARCHAR(255),
            payload {$this->json()},
            response {$this->json()},
            status_code INTEGER,
            status VARCHAR(20) DEFAULT 'pending',
            attempts INTEGER DEFAULT 1,
            created_at {$this->timestamp()}
        )";
        $this->pdo->exec($sql);

        $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_webhook_logs_event ON webhook_logs(event_type)");
        $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_webhook_logs_status ON webhook_logs(status)");
    }

    private function migrate_014_add_bank_details_to_helpers(): void
    {
        $sql = "ALTER TABLE helpers ADD COLUMN bank_code VARCHAR(10);
                ALTER TABLE helpers ADD COLUMN account_number VARCHAR(20);
                ALTER TABLE helpers ADD COLUMN account_name VARCHAR(255);
                ALTER TABLE helpers ADD COLUMN subaccount_id VARCHAR(100);";

        // SQLite doesn't support multiple ADD COLUMN in one statement easily or multiple statements effectively in one exec usually depending on driver, 
        // but PDO exec might support it if the driver allows. 
        // Safer to do one by one for SQLite compatibility if that's the DB.
        // The existing code uses $this->isSqlite.

        $columns = [
            'bank_code VARCHAR(10)',
            'account_number VARCHAR(20)',
            'account_name VARCHAR(255)',
            'subaccount_id VARCHAR(100)'
        ];

        foreach ($columns as $column) {
            try {
                $this->pdo->exec("ALTER TABLE helpers ADD COLUMN $column");
            } catch (\Exception $e) {
                // Ignore if exists
            }
        }
    }

    private function migrate_015_update_payments_table(): void
    {
        $columns = [
            "payment_type VARCHAR(50) DEFAULT 'service_fee'",
            "commission_amount INTEGER DEFAULT 0",
            "helper_amount INTEGER DEFAULT 0"
        ];

        foreach ($columns as $column) {
            try {
                $this->pdo->exec("ALTER TABLE payments ADD COLUMN $column");
            } catch (\Exception $e) {
                // Ignore if exists
            }
        }

        try {
            $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_payments_type ON payments(payment_type)");
        } catch (\Exception $e) {
        }
    }

    private function migrate_016_update_settings_for_commission(): void
    {
        $stmt = $this->pdo->prepare("INSERT OR IGNORE INTO settings (key_name, value, category) VALUES (?, ?, ?)");
        $stmt->execute(['commission_percent', '10', 'payments']);
    }

    private function migrate_017_create_agency_profiles_table(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS agency_profiles (
            id INTEGER PRIMARY KEY {$this->autoIncrement()},
            user_id INTEGER NOT NULL UNIQUE,
            agency_name VARCHAR(255) NOT NULL,
            email VARCHAR(255),
            address TEXT,
            city VARCHAR(100),
            state VARCHAR(100),
            business_type VARCHAR(50) DEFAULT 'agency',
            description TEXT,
            logo VARCHAR(255),
            website VARCHAR(255),
            created_at {$this->timestamp()},
            updated_at {$this->timestamp()},
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )";
        $this->pdo->exec($sql);

        $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_agency_profiles_user_id ON agency_profiles(user_id)");
    }

    private function migrate_018_create_disputes_table(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS disputes (
            id INTEGER PRIMARY KEY {$this->autoIncrement()},
            booking_id INTEGER NOT NULL,
            raised_by INTEGER NOT NULL,
            reason VARCHAR(255) NOT NULL,
            description TEXT,
            status VARCHAR(20) DEFAULT 'open',
            resolution_notes TEXT,
            resolved_at DATETIME,
            created_at {$this->timestamp()},
            updated_at {$this->timestamp()},
            FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
            FOREIGN KEY (raised_by) REFERENCES users(id) ON DELETE CASCADE
        )";
        $this->pdo->exec($sql);

        $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_disputes_booking_id ON disputes(booking_id)");
        $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_disputes_status ON disputes(status)");
    }

    private function migrate_019_create_messages_table(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS messages (
            id INTEGER PRIMARY KEY {$this->autoIncrement()},
            booking_id INTEGER NOT NULL,
            sender_id INTEGER NOT NULL,
            receiver_id INTEGER NOT NULL,
            message TEXT NOT NULL,
            is_read INTEGER DEFAULT 0,
            created_at {$this->timestamp()},
            FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
            FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE
        )";
        $this->pdo->exec($sql);

        $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_messages_booking_id ON messages(booking_id)");
        $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_messages_sender_id ON messages(sender_id)");
        $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_messages_receiver_id ON messages(receiver_id)");
    }

    private function migrate_020_add_checkout_url_to_payments(): void
    {
        try {
            $this->pdo->exec("ALTER TABLE payments ADD COLUMN checkout_url VARCHAR(255)");
        } catch (\Exception $e) {
            // Ignore if exists
        }
    }
}
