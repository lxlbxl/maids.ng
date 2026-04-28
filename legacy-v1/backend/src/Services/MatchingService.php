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

    public function findMatches(array $criteria, int $limit = 3): array
    {
        $where = ["h.status = 'active'", "h.verification_status = 'verified'"];
        $params = [];

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

        $whereClause = implode(' AND ', $where);

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
                h.salary_min,
                h.salary_max,
                '₦' || h.salary_min || ' - ₦' || h.salary_max as rate,
                h.availability,
                h.experience,
                h.experience_years,
                h.skills,
                h.profile_photo as image,
                h.bio,
                h.rating_avg as rating,
                h.rating_count,
                h.badge_level as badge,
                h.verification_status as verification,
                h.physical_verification_status as physical_verification,
                h.created_at
            FROM helpers h
            WHERE {$whereClause}
            ORDER BY
                h.physical_verification_status DESC,
                h.rating_avg DESC,
                h.verification_status DESC,
                h.jobs_completed DESC,
                h.created_at DESC
            LIMIT ?
        ";

        $params[] = $limit;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $helpers = $stmt->fetchAll();

        // Parse skills JSON and format for frontend
        foreach ($helpers as &$helper) {
            $helper['skills'] = json_decode($helper['skills'] ?? '[]', true) ?: [];
            $helper['Rating'] = (string) $helper['rating'];
            $helper['Verification'] = $helper['verification'] === 'verified' ? 'Verified' : 'Pending';
            $helper['Physical_Verification'] = $helper['physical_verification'] === 'verified' ? 'Physically Verified' : 'Pending';
            $helper['First_Name'] = $helper['first_name'] ?? explode(' ', $helper['name'])[0];

            // Generate placeholder image if none exists
            if (empty($helper['image'])) {
                $initials = $this->getInitials($helper['name']);
                $helper['image'] = "https://ui-avatars.com/api/?name={$initials}&background=f59e0b&color=fff&size=200";
            }
        }

        $this->logger->info("Found matches", [
            'criteria' => $criteria,
            'count' => count($helpers)
        ]);

        return $helpers;
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
                nin_number, date_of_birth, gender, marital_status,
                guarantor_name, guarantor_phone, guarantor_relationship, guarantor_address, guarantor_nin,
                emergency_contact_name, emergency_contact_phone
            ) VALUES (
                ?, ?, ?, ?, ?, ?,
                ?, ?, ?, ?, ?,
                ?, ?, ?, ?,
                ?, ?, ?, ?, ?,
                ?, ?, ?, ?,
                ?, ?, ?, ?, ?,
                ?, ?
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
            $data['marital_status'] ?? null,
            $data['guarantor_name'] ?? null,
            $data['guarantor_phone'] ?? null,
            $data['guarantor_relationship'] ?? null,
            $data['guarantor_address'] ?? null,
            $data['guarantor_nin'] ?? null,
            $data['emergency_contact_name'] ?? null,
            $data['emergency_contact_phone'] ?? null
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
            'subaccount_id',
            'guarantor_name',
            'guarantor_phone',
            'guarantor_relationship',
            'guarantor_address',
            'guarantor_nin',
            'emergency_contact_name',
            'emergency_contact_phone',
            'physical_verification_status',
            'field_officer_notes'
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
