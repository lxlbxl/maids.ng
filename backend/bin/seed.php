<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use App\Database\Connection;

// Load environment variables
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

// Get database config
$config = require __DIR__ . '/../config/database.php';

// Create connection
$connection = new Connection($config);
$pdo = $connection->getPdo();

echo "Seeding database...\n";

// Create default super admin
$email = 'admin@maids.ng';
$password = password_hash('admin123', PASSWORD_DEFAULT);
$name = 'Super Admin';

$stmt = $pdo->prepare("SELECT id FROM admin_users WHERE email = ?");
$stmt->execute([$email]);

if (!$stmt->fetch()) {
    $stmt = $pdo->prepare("INSERT INTO admin_users (email, password_hash, name, role_id, status) VALUES (?, ?, ?, 1, 'active')");
    $stmt->execute([$email, $password, $name]);
    echo "Created default admin: {$email} / admin123\n";
} else {
    echo "Default admin already exists\n";
}

// Create sample helpers
$helpers = [
    [
        'name' => 'Aisha Mohammed',
        'phone' => '08012345001',
        'work_type' => 'Fulltime Maid',
        'accommodation' => 'Live-in',
        'location' => 'Lagos Mainland',
        'salary_min' => 40000,
        'salary_max' => 50000,
        'experience' => '5 years experience in household management',
        'skills' => ['Cooking', 'Cleaning', 'Childcare', 'Laundry'],
        'rating' => 4.8,
        'verified' => true
    ],
    [
        'name' => 'Blessing Okonkwo',
        'phone' => '08012345002',
        'work_type' => 'Nanny',
        'accommodation' => 'Live-out',
        'location' => 'Ikeja',
        'salary_min' => 35000,
        'salary_max' => 45000,
        'experience' => '3 years childcare experience',
        'skills' => ['Childcare', 'Cooking', 'First Aid'],
        'rating' => 4.6,
        'verified' => true
    ],
    [
        'name' => 'Fatima Abdullahi',
        'phone' => '08012345003',
        'work_type' => 'Cook',
        'accommodation' => 'Live-out',
        'location' => 'Victoria Island',
        'salary_min' => 50000,
        'salary_max' => 70000,
        'experience' => '7 years professional cooking',
        'skills' => ['Cooking', 'Meal Planning', 'Baking'],
        'rating' => 4.9,
        'verified' => true
    ],
    [
        'name' => 'Grace Adeyemi',
        'phone' => '08012345004',
        'work_type' => 'Cleaner',
        'accommodation' => 'Live-out',
        'location' => 'Lekki',
        'salary_min' => 30000,
        'salary_max' => 40000,
        'experience' => '2 years cleaning experience',
        'skills' => ['Cleaning', 'Laundry', 'Ironing'],
        'rating' => 4.5,
        'verified' => false
    ],
    [
        'name' => 'Mary Johnson',
        'phone' => '08012345005',
        'work_type' => 'Fulltime Maid',
        'accommodation' => 'Either',
        'location' => 'Surulere',
        'salary_min' => 35000,
        'salary_max' => 45000,
        'experience' => '4 years household experience',
        'skills' => ['Cooking', 'Cleaning', 'Childcare', 'Pet Care'],
        'rating' => 4.7,
        'verified' => true
    ]
];

foreach ($helpers as $h) {
    // Check if phone exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE phone = ?");
    $stmt->execute([$h['phone']]);

    if (!$stmt->fetch()) {
        // Create user
        $stmt = $pdo->prepare("INSERT INTO users (phone, pin_hash, user_type, status) VALUES (?, ?, 'helper', 'active')");
        $stmt->execute([$h['phone'], password_hash('1234', PASSWORD_DEFAULT)]);
        $userId = $pdo->lastInsertId();

        // Create helper
        $names = explode(' ', $h['name']);
        $stmt = $pdo->prepare("
            INSERT INTO helpers (
                user_id, full_name, first_name, last_name, work_type, accommodation,
                location, salary_min, salary_max, availability, experience,
                skills, rating_avg, rating_count, verification_status, badge_level, status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'Immediately', ?, ?, ?, ?, ?, ?, 'active')
        ");
        $stmt->execute([
            $userId,
            $h['name'],
            $names[0],
            $names[1] ?? '',
            $h['work_type'],
            $h['accommodation'],
            $h['location'],
            $h['salary_min'],
            $h['salary_max'],
            $h['experience'],
            json_encode($h['skills']),
            $h['rating'],
            rand(5, 50),
            $h['verified'] ? 'verified' : 'pending',
            $h['verified'] ? 'silver' : 'bronze'
        ]);

        echo "Created helper: {$h['name']}\n";
    }
}

// Create sample employer
$stmt = $pdo->prepare("SELECT id FROM users WHERE phone = '08011111111'");
$stmt->execute();
if (!$stmt->fetch()) {
    $stmt = $pdo->prepare("INSERT INTO users (phone, pin_hash, user_type, status) VALUES ('08011111111', ?, 'employer', 'active')");
    $stmt->execute([password_hash('1234', PASSWORD_DEFAULT)]);
    $userId = $pdo->lastInsertId();

    $stmt = $pdo->prepare("INSERT INTO employers (user_id, full_name, location, help_type) VALUES (?, 'John Doe', 'Lekki', 'Fulltime Maid')");
    $stmt->execute([$userId]);
    echo "Created sample employer: 08011111111 / 1234\n";
}

// Create sample agency
$agencyPhone = '08022222222';
$stmt = $pdo->prepare("SELECT id FROM users WHERE phone = ?");
$stmt->execute([$agencyPhone]);
if (!$stmt->fetch()) {
    $stmt = $pdo->prepare("INSERT INTO users (phone, pin_hash, user_type, status) VALUES (?, ?, 'agency', 'active')");
    $stmt->execute([$agencyPhone, password_hash('1234', PASSWORD_DEFAULT)]);
    $userId = $pdo->lastInsertId();

    $stmt = $pdo->prepare("INSERT INTO agency_profiles (user_id, agency_name, email, state, city, description) VALUES (?, 'TopAgency Ltd', 'agency@example.com', 'Lagos', 'Ikeja', 'We provide premium maids and helpers.')");
    $stmt->execute([$userId]);
    echo "Created sample agency: $agencyPhone / 1234\n";
}

echo "\nSeeding complete!\n";
echo "\nDefault credentials:\n";
echo "Admin: admin@maids.ng / admin123\n";
echo "Employer: 08011111111 / 1234\n";
echo "Agency: 08022222222 / 1234\n";
echo "Helpers: 0801234500X / 1234\n";
