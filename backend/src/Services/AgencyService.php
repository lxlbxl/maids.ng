<?php

namespace App\Services;

use PDO;

class AgencyService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Get all agencies with pagination and search
     */
    public function getAllAgencies(int $page = 1, int $limit = 10, string $search = ''): array
    {
        $offset = ($page - 1) * $limit;
        $params = [];

        // Base query
        $query = "
            SELECT u.id, u.phone, u.email, u.status, u.is_verified, 
                   u.created_at, ap.agency_name, ap.logo, ap.address,
                   (SELECT COUNT(*) FROM helpers h WHERE h.agency_id = u.id) as maid_count
            FROM users u
            LEFT JOIN agency_profiles ap ON u.id = ap.user_id
            WHERE u.user_type = 'agency'
        ";

        // Add search condition
        if (!empty($search)) {
            $query .= " AND (ap.agency_name LIKE ? OR u.phone LIKE ? OR u.email LIKE ?)";
            $searchTerm = "%$search%";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }

        // Count total for pagination
        $countQuery = str_replace("SELECT u.id, u.phone, u.email, u.status, u.is_verified, 
                   u.created_at, ap.agency_name, ap.logo, ap.address,
                   (SELECT COUNT(*) FROM helpers h WHERE h.agency_id = u.id) as maid_count", "SELECT COUNT(*) as total", $query);

        $stmt = $this->pdo->prepare($countQuery);
        $stmt->execute($params);
        $total = (int) $stmt->fetchColumn();

        // Get data
        $query .= " ORDER BY u.created_at DESC LIMIT ? OFFSET ?";
        $stmt = $this->pdo->prepare($query);

        // Bind limit and offset safely
        // In PDO, LIMIT/OFFSET must be integers, not strings from execute array
        foreach ($params as $k => $v) {
            $stmt->bindValue($k + 1, $v);
        }
        $stmt->bindValue(count($params) + 1, $limit, PDO::PARAM_INT);
        $stmt->bindValue(count($params) + 2, $offset, PDO::PARAM_INT);

        $stmt->execute();
        $agencies = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'agencies' => $agencies,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'pages' => ceil($total / $limit)
        ];
    }

    /**
     * Get single agency details with maids and financials
     */
    public function getAgencyDetails(int $id): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT u.id, u.phone, u.email, u.status, u.is_verified, 
                   u.created_at, ap.agency_name, ap.logo, ap.address, ap.description,
                   ap.cac_number, ap.website,
                   (SELECT COUNT(*) FROM helpers h WHERE h.agency_id = u.id) as maid_count
            FROM users u
            LEFT JOIN agency_profiles ap ON u.id = ap.user_id
            WHERE u.id = ? AND u.user_type = 'agency'
        ");
        $stmt->execute([$id]);
        $agency = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$agency)
            return null;

        // Get Associated Maids
        $stmt = $this->pdo->prepare("
            SELECT h.id, h.full_name, h.profile_photo, h.status, h.verification_status, 
                   h.work_type, h.created_at,
                   (SELECT COUNT(*) FROM bookings b WHERE b.helper_id = h.id) as jobs_count
            FROM helpers h
            WHERE h.agency_id = ?
            ORDER BY h.created_at DESC
        ");
        $stmt->execute([$id]);
        $agency['maids'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Calculate Revenue (Commission from maids' jobs)
        // Assuming agency gets a cut of service fees or salaries
        // For now, let's sum up payments related to this agency's maids
        $stmt = $this->pdo->prepare("
            SELECT SUM(p.amount) as total_revenue
            FROM payments p
            JOIN bookings b ON p.booking_id = b.id
            JOIN helpers h ON b.helper_id = h.id
            WHERE h.agency_id = ? AND p.status = 'success'
        ");
        $stmt->execute([$id]);
        $agency['total_revenue'] = $stmt->fetchColumn() ?: 0;

        return $agency;
    }

    /**
     * Verify or update agency status
     */
    public function updateAgencyStatus(int $id, string $status): bool
    {
        // Status can be 'active', 'suspended', 'pending'
        // If status is 'active', we also set is_verified = 1

        $isVerified = ($status === 'active') ? 1 : 0;

        $stmt = $this->pdo->prepare("
            UPDATE users 
            SET status = ?, is_verified = ? 
            WHERE id = ? AND user_type = 'agency'
        ");

        return $stmt->execute([$status, $isVerified, $id]);
    }

    /**
     * Delete agency and associated data
     */
    public function deleteAgencyWithCleanup(int $id): bool
    {
        try {
            $this->pdo->beginTransaction();

            // 1. Delete agency profile
            $stmt = $this->pdo->prepare("DELETE FROM agency_profiles WHERE user_id = ?");
            $stmt->execute([$id]);

            // 2. Unlink or delete helpers? 
            // Better to unlink them (set agency_id = NULL) so they aren't lost, 
            // OR delete them if they strictly belong to the agency.
            // For now, let's unlink them to be safe.
            $stmt = $this->pdo->prepare("UPDATE helpers SET agency_id = NULL WHERE agency_id = ?");
            $stmt->execute([$id]);

            // 3. Delete user account
            $stmt = $this->pdo->prepare("DELETE FROM users WHERE id = ? AND user_type = 'agency'");
            $stmt->execute([$id]);

            $this->pdo->commit();
            return true;
        } catch (\Exception $e) {
            $this->pdo->rollBack();
            return false; // Or log error
        }
    }

    public function isValidStatus(string $status): bool
    {
        return in_array($status, ['active', 'suspended', 'pending', 'inactive']);
    }
}
