<?php

declare(strict_types=1);

namespace App\Services;

use PDO;
use Psr\Log\LoggerInterface;

class MatchingService
{
    private PDO $pdo;
    private LoggerInterface $logger;

    public function __construct(PDO $pdo, LoggerInterface $logger)
    {
        $this->pdo = $pdo;
        $this->logger = $logger;
    }

    public function findMatches(array $criteria, int $limit = 3, int $page = 1): array
    {
        $where = ["h.status = 'active'", "h.verification_status = 'verified'"];
        $params = [];

        // Pagination
        $offset = ($page - 1) * $limit;

        // Filter by work type
        if (!empty($criteria['help_type']) || !empty($criteria['work_type'])) {
            $workType = $criteria['help_type'] ?? $criteria['work_type'];

            // Map frontend naming to DB values
            $mapping = [
                'Nanny / Childcare' => 'Nanny',
                'House Cleaning' => ['Cleaner', 'Fulltime Maid'],
                'Cook' => 'Cook',
                'Driver' => 'Driver',
                'Gardener' => 'Gardener'
            ];

            if (isset($mapping[$workType])) {
                $mappedType = $mapping[$workType];
                if (is_array($mappedType)) {
                    $placeholders = implode(',', array_fill(0, count($mappedType), '?'));
                    $where[] = "h.work_type IN ($placeholders)";
                    foreach ($mappedType as $type) {
                        $params[] = $type;
                    }
                } else {
                    $where[] = "h.work_type = ?";
                    $params[] = $mappedType;
                }
            } else {
                // Fallback for direct matches (e.g. 'Fulltime Maid', 'Cleaner' passed directly)
                $where[] = "h.work_type = ?";
                $params[] = $workType;
            }
        }

        // Filter by accommodation preference
        if (!empty($criteria['accommodation'])) {
            if ($criteria['accommodation'] !== 'Either') {
                $where[] = "(h.accommodation = ? OR h.accommodation = 'Either')";
                $params[] = $criteria['accommodation'];
            }
        }

        // Filter by location
        if (!empty($criteria['location'])) {
            $where[] = "(h.location LIKE ? OR h.location_state LIKE ? OR h.location_lga LIKE ?)";
            $locationSearch = '%' . $criteria['location'] . '%';
            $params[] = $locationSearch;
            $params[] = $locationSearch;
            $params[] = $locationSearch;
        }

        // Filter by budget
        if (!empty($criteria['budget_min'])) {
            $where[] = "h.salary_min >= ?";
            $params[] = $this->parseBudget($criteria['budget_min']);
        }

        if (!empty($criteria['budget_max'])) {
            $where[] = "h.salary_max <= ?";
            $params[] = $this->parseBudget($criteria['budget_max']);
        }

        // Filter by availability
        if (!empty($criteria['start_date']) && $criteria['start_date'] === 'Immediately') {
            $where[] = "h.availability = 'Immediately'";
        }

        // Filter by work type
        if (!empty($criteria['help_type']) || !empty($criteria['work_type'])) {
            $workType = $criteria['help_type'] ?? $criteria['work_type'];

            // Map frontend naming to DB values
            $mapping = [
                'Nanny / Childcare' => 'Nanny',
                'House Cleaning' => ['Cleaner', 'Fulltime Maid'],
                'Cook' => 'Cook',
                'Driver' => 'Driver',
                'Gardener' => 'Gardener'
            ];

            if (isset($mapping[$workType])) {
                $mappedType = $mapping[$workType];
                if (is_array($mappedType)) {
                    $placeholders = implode(',', array_fill(0, count($mappedType), '?'));
                    $where[] = "h.work_type IN ($placeholders)";
                    foreach ($mappedType as $type) {
                        $params[] = $type;
                    }
                } else {
                    $where[] = "h.work_type = ?";
                    $params[] = $mappedType;
                }
            } else {
                // Fallback for direct matches (e.g. 'Fulltime Maid', 'Cleaner' passed directly)
                $where[] = "h.work_type = ?";
                $params[] = $workType;
            }
        }

        // Filter by accommodation preference
        if (!empty($criteria['accommodation'])) {
            if ($criteria['accommodation'] !== 'Either') {
                $where[] = "(h.accommodation = ? OR h.accommodation = 'Either')";
                $params[] = $criteria['accommodation'];
            }
        }

        // Filter by location (state/LGA/city)
        if (!empty($criteria['location'])) {
            $where[] = "(h.location LIKE ? OR h.location_state LIKE ? OR h.location_lga LIKE ?)";
            $locationSearch = '%' . $criteria['location'] . '%';
            $params[] = $locationSearch;
            $params[] = $locationSearch;
            $params[] = $locationSearch;
        }

        // Filter by salary budget
        if (!empty($criteria['budget_min'])) {
            $where[] = "h.salary_min >= ?";
            $params[] = $this->parseBudget($criteria['budget_min']);
        }

        if (!empty($criteria['budget_max'])) {
            $where[] = "h.salary_max <= ?";
            $params[] = $this->parseBudget($criteria['budget_max']);
        }

        // Filter by availability
        if (!empty($criteria['start_date'])) {
            if ($criteria['start_date'] === 'Immediately') {
                $where[] = "h.availability = 'Immediately'";
            } else {
                $where[] = "h.availability <= ?";
                $params[] = $criteria['start_date'];
            }
        }

        // Filter by experience years
        if (!empty($criteria['experience_years_min'])) {
            $where[] = "h.experience_years >= ?";
            $params[] = (int)$criteria['experience_years_min'];
        }

        // Filter by gender
        if (!empty($criteria['gender'])) {
            $where[] = "h.gender = ?";
            $params[] = $criteria['gender'];
        }

        // Filter by languages
        if (!empty($criteria['languages'])) {
            $langs = is_array($criteria['languages']) ? $criteria['languages'] : [$criteria['languages']];
            foreach ($langs as $lang) {
                $where[] = "h.languages LIKE ?";
                $params[] = "%\"$lang\"%";
            }
        }

        // Filter by skills (JSON contains)
        if (!empty($criteria['skills'])) {
            $skills = is_array($criteria['skills']) ? $criteria['skills'] : [$criteria['skills']];
            foreach ($skills as $skill) {
                $where[] = "h.skills LIKE ?";
                $params[] = "%\"$skill\"%";
            }
        }

        $whereClause = implode(' AND ', $where);

        // Build ORDER BY clause
        $orderBy = $this->buildOrderBy($criteria['sort'] ?? 'rating_desc');

        // Get total count for pagination
        $countSql = "SELECT COUNT(*) as total FROM helpers h WHERE {$whereClause}";
        $countStmt = $this->pdo->prepare($countSql);
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        // Main query with pagination
        $sql = "
            SELECT
                h.id,
                h.full_name as name,
                h.first_name,
                h.last_name,
                h.work_type,
                h.accommodation,
                h.location,
                h.location_state,
                h.location_lga,
                h.salary_min,
                h.salary_max,
                h.availability,
                h.availability_date,
                h.experience,
                h.experience_years,
                h.skills,
                h.profile_photo as image,
                h.bio,
                h.languages,
                h.gender,
                h.rating_avg as rating,
                h.rating_count,
                h.badge_level as badge,
                h.verification_status as verification,
                h.created_at
            FROM helpers h
            WHERE {$whereClause}
            {$orderBy}
            LIMIT ? OFFSET ?
        ";

        $params[] = $limit;
        $params[] = $offset;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $helpers = $stmt->fetchAll();

        // Format response
        foreach ($helpers as &$helper) {
            $helper['skills'] = json_decode($helper['skills'] ?? '[]', true) ?: [];
            $helper['languages'] = json_decode($helper['languages'] ?? '[]', true) ?: [];
            $helper['Rating'] = $helper['rating'] ? (string)round($helper['rating'], 1) : '0.0';
            $helper['Verification'] = $helper['verification'] === 'verified' ? 'Verified' : 'Pending';
            $helper['First_Name'] = $helper['first_name'] ?? explode(' ', $helper['name'])[0];
            $helper['rate'] = '₦' . number_format($helper['salary_min']) . ' - ₦' . number_format($helper['salary_max']);

            if (empty($helper['image'])) {
                $initials = $this->getInitials($helper['name']);
                $helper['image'] = "https://ui-avatars.com/api/?name={$initials}&background=f59e0b&color=fff&size=200";
            }
        }

        $this->logger->info("Found matches", [
            'criteria' => $criteria,
            'count' => count($helpers),
            'total' => $total,
            'page' => $page,
            'pages' => ceil($total / $limit)
        ]);

        return [
            'helpers' => $helpers,
            'pagination' => [
                'total' => $total,
                'page' => $page,
                'per_page' => $limit,
                'pages' => (int)ceil($total / $limit)
            ]
        ];
    }

    /**
     * Get all helpers with pagination and filters (for admin/agency use)
     */
    public function getAllHelpers(array $criteria = [], int $page = 1, int $perPage = 20): array
    {
        $where = ["h.status != 'deleted'"];
        $params = [];

        // Apply same filters as findMatches but without verification requirement for admin view
        if (!empty($criteria['work_type'])) {
            $where[] = "h.work_type = ?";
            $params[] = $criteria['work_type'];
        }

        if (!empty($criteria['location'])) {
            $where[] = "(h.location LIKE ? OR h.location_state LIKE ? OR h.location_lga LIKE ?)";
            $search = '%' . $criteria['location'] . '%';
            $params[] = $search;
            $params[] = $search;
            $params[] = $search;
        }

        if (!empty($criteria['verification_status'])) {
            $where[] = "h.verification_status = ?";
            $params[] = $criteria['verification_status'];
        }

        if (!empty($criteria['gender'])) {
            $where[] = "h.gender = ?";
            $params[] = $criteria['gender'];
        }

        if (!empty($criteria['skills'])) {
            $skills = is_array($criteria['skills']) ? $criteria['skills'] : [$criteria['skills']];
            foreach ($skills as $skill) {
                $where[] = "h.skills LIKE ?";
                $params[] = "%\"$skill\"%";
            }
        }

        $whereClause = implode(' AND ', $where);
        $orderBy = $this->buildOrderBy($criteria['sort'] ?? 'created_desc');
        $offset = ($page - 1) * $perPage;

        // Total count
        $countSql = "SELECT COUNT(*) as total FROM helpers h WHERE {$whereClause}";
        $countStmt = $this->pdo->prepare($countSql);
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        // Fetch helpers
        $sql = "
            SELECT
                h.*,
                u.phone,
                u.status as user_status
            FROM helpers h
            LEFT JOIN users u ON h.user_id = u.id
            WHERE {$whereClause}
            {$orderBy}
            LIMIT ? OFFSET ?
        ";

        $params[] = $perPage;
        $params[] = $offset;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $helpers = $stmt->fetchAll();

        // Format
        foreach ($helpers as &$helper) {
            $helper['skills'] = json_decode($helper['skills'] ?? '[]', true) ?: [];
            $helper['languages'] = json_decode($helper['languages'] ?? '[]', true) ?: [];
            if (empty($helper['image'])) {
                $initials = $this->getInitials($helper['full_name']);
                $helper['image'] = "https://ui-avatars.com/api/?name={$initials}&background=f59e0b&color=fff&size=200";
            }
        }

        return [
            'helpers' => $helpers,
            'pagination' => [
                'total' => $total,
                'page' => $page,
                'per_page' => $perPage,
                'pages' => (int)ceil($total / $perPage)
            ]
        ];
    }

    private function buildOrderBy(string $sort): string
    {
        return match ($sort) {
            'rating_asc' => "ORDER BY h.rating_avg ASC, h.created_at DESC",
            'price_asc' => "ORDER BY h.salary_min ASC, h.created_at DESC",
            'price_desc' => "ORDER BY h.salary_max DESC, h.created_at DESC",
            'exp_desc' => "ORDER BY h.experience_years DESC, h.created_at DESC",
            'exp_asc' => "ORDER BY h.experience_years ASC, h.created_at DESC",
            'newest' => "ORDER BY h.created_at DESC",
            'oldest' => "ORDER BY h.created_at ASC",
            default => "ORDER BY h.rating_avg DESC, h.verification_status DESC, h.created_at DESC"
        };
    }

    public function getHelper(int $helperId): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT
                h.*,
                u.phone,
                u.status as user_status
            FROM helpers h
            JOIN users u ON h.user_id = u.id
            WHERE h.id = ?
        ");
        $stmt->execute([$helperId]);
        $helper = $stmt->fetch();

        if ($helper) {
            $helper['skills'] = json_decode($helper['skills'] ?? '[]', true) ?: [];
            $helper['languages'] = json_decode($helper['languages'] ?? '[]', true) ?: [];
        }

        return $helper ?: null;
    }

    public function createHelper(int $userId, array $data): int
    {
        $skills = is_array($data['skills'] ?? null) ? json_encode($data['skills']) : ($data['skills'] ?? '[]');
        $languages = is_array($data['languages'] ?? null) ? json_encode($data['languages']) : ($data['languages'] ?? '[]');

        $stmt = $this->pdo->prepare("
            INSERT INTO helpers (
                user_id, full_name, first_name, last_name, work_type, accommodation,
                location, location_state, location_lga, salary_min, salary_max,
                availability, availability_date, experience, experience_years,
                skills, profile_photo, voice_intro, bio, languages,
                nin_number, date_of_birth, gender, marital_status
            ) VALUES (
                ?, ?, ?, ?, ?, ?,
                ?, ?, ?, ?, ?,
                ?, ?, ?, ?,
                ?, ?, ?, ?, ?,
                ?, ?, ?, ?
            )
        ");

        $stmt->execute([
            $userId,
            $data['full_name'] ?? '',
            $data['first_name'] ?? null,
            $data['last_name'] ?? null,
            $data['work_type'] ?? 'Fulltime Maid',
            $data['accommodation'] ?? null,
            $data['location'] ?? null,
            $data['location_state'] ?? null,
            $data['location_lga'] ?? null,
            $this->parseBudget($data['salary_min'] ?? 30000),
            $this->parseBudget($data['salary_max'] ?? 60000),
            $data['availability'] ?? null,
            $data['availability_date'] ?? null,
            $data['experience'] ?? null,
            (int) ($data['experience_years'] ?? 0),
            $skills,
            $data['profile_photo'] ?? null,
            $data['voice_intro'] ?? null,
            $data['bio'] ?? null,
            $languages,
            $data['nin_number'] ?? null,
            $data['date_of_birth'] ?? null,
            $data['gender'] ?? null,
            $data['marital_status'] ?? null
        ]);

        $helperId = (int) $this->pdo->lastInsertId();
        $this->logger->info("Helper created", ['helper_id' => $helperId]);

        return $helperId;
    }

    public function updateHelper(int $helperId, array $data): bool
    {
        $fields = [];
        $params = [];

        $allowedFields = [
            'full_name',
            'first_name',
            'last_name',
            'work_type',
            'accommodation',
            'location',
            'location_state',
            'location_lga',
            'salary_min',
            'salary_max',
            'availability',
            'availability_date',
            'experience',
            'experience_years',
            'skills',
            'profile_photo',
            'voice_intro',
            'bio',
            'languages',
            'verification_status',
            'badge_level',
            'status',
            'nin_number',
            'date_of_birth',
            'gender',
            'marital_status',
            'bank_code',
            'account_number',
            'account_name',
            'subaccount_id'
        ];

        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $value = $data[$field];

                if (in_array($field, ['skills', 'languages']) && is_array($value)) {
                    $value = json_encode($value);
                }

                if (in_array($field, ['salary_min', 'salary_max'])) {
                    $value = $this->parseBudget($value);
                }

                $fields[] = "{$field} = ?";
                $params[] = $value;
            }
        }

        if (empty($fields)) {
            return false;
        }

        $fields[] = "updated_at = CURRENT_TIMESTAMP";
        $params[] = $helperId;

        $sql = "UPDATE helpers SET " . implode(', ', $fields) . " WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);

        return $stmt->execute($params);
    }

    public function updateRating(int $helperId): void
    {
        $stmt = $this->pdo->prepare("
            SELECT AVG(rating) as avg_rating, COUNT(*) as count
            FROM ratings
            WHERE helper_id = ?
        ");
        $stmt->execute([$helperId]);
        $result = $stmt->fetch();

        $stmt = $this->pdo->prepare("
            UPDATE helpers SET
                rating_avg = ?,
                rating_count = ?,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");
        $stmt->execute([
            round($result['avg_rating'] ?? 0, 2),
            $result['count'] ?? 0,
            $helperId
        ]);

        // Update badge level based on ratings and jobs
        $this->updateBadgeLevel($helperId);
    }

    private function updateBadgeLevel(int $helperId): void
    {
        $stmt = $this->pdo->prepare("SELECT rating_avg, rating_count, jobs_completed, invites_count FROM helpers WHERE id = ?");
        $stmt->execute([$helperId]);
        $helper = $stmt->fetch();

        $badge = 'bronze';

        if ($helper['rating_count'] >= 50 && $helper['rating_avg'] >= 4.5 && $helper['invites_count'] >= 50) {
            $badge = 'gold';
        } elseif ($helper['rating_count'] >= 5 && $helper['jobs_completed'] >= 10) {
            $badge = 'silver';
        }

        $stmt = $this->pdo->prepare("UPDATE helpers SET badge_level = ? WHERE id = ?");
        $stmt->execute([$badge, $helperId]);
    }

    private function parseBudget($value): int
    {
        if (is_int($value)) {
            return $value;
        }

        // Remove currency symbols and commas
        $value = preg_replace('/[₦,\s]/', '', (string) $value);

        // Handle ranges like "30000-50000" - take the first value
        if (str_contains($value, '-')) {
            $value = explode('-', $value)[0];
        }

        // Handle text like "30k" or "50k"
        if (stripos($value, 'k') !== false) {
            $value = (float) str_ireplace('k', '', $value) * 1000;
        }

        return (int) $value;
    }

    private function getInitials(string $name): string
    {
        $words = explode(' ', trim($name));
        $initials = '';

        foreach (array_slice($words, 0, 2) as $word) {
            if (!empty($word)) {
                $initials .= strtoupper($word[0]);
            }
        }

        return $initials ?: 'U';
    }

    public function updateAvailability(int $helperId, array $schedule): void
    {
        $this->pdo->beginTransaction();

        try {
            // Remove existing schedule
            $this->pdo->prepare("DELETE FROM helper_availability WHERE helper_id = ?")->execute([$helperId]);

            // Insert new schedule
            $stmt = $this->pdo->prepare("
                INSERT INTO helper_availability (helper_id, day_of_week, start_time, end_time, is_active)
                VALUES (?, ?, ?, ?, ?)
            ");

            foreach ($schedule as $slot) {
                // Ensure valid inputs
                if (!isset($slot['day']) || !isset($slot['start']) || !isset($slot['end'])) {
                    continue;
                }

                $stmt->execute([
                    $helperId,
                    (int) $slot['day'],
                    $slot['start'],
                    $slot['end'],
                    isset($slot['active']) && $slot['active'] ? 1 : 0
                ]);
            }

            $this->pdo->commit();
        } catch (\Exception $e) {
            $this->pdo->rollBack();
            $this->logger->error('Failed to update availability', [
                'helper_id' => $helperId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    public function getAvailability(int $helperId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT day_of_week as day, start_time as start, end_time as end, is_active as active
            FROM helper_availability
            WHERE helper_id = ?
            ORDER BY day_of_week
        ");
        $stmt->execute([$helperId]);

        $schedule = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Cast types for consistency
        foreach ($schedule as &$slot) {
            $slot['day'] = (int) $slot['day'];
            $slot['active'] = (bool) $slot['active'];
        }

        return $schedule;
    }
}
