<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Services\MatchingService;
use App\Services\VerificationService;
use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class AdminHelperController
{
    private PDO $pdo;
    private MatchingService $matchingService;
    private VerificationService $verificationService;

    public function __construct(
        PDO $pdo,
        MatchingService $matchingService,
        VerificationService $verificationService
    ) {
        $this->pdo = $pdo;
        $this->matchingService = $matchingService;
        $this->verificationService = $verificationService;
    }

    public function index(Request $request, Response $response): Response
    {
        $params = $request->getQueryParams();
        $page = max(1, (int) ($params['page'] ?? 1));
        $limit = min(100, max(1, (int) ($params['limit'] ?? 20)));
        $offset = ($page - 1) * $limit;

        $where = [];
        $queryParams = [];

        if (!empty($params['search'])) {
            $where[] = "(h.full_name LIKE ? OR u.phone LIKE ?)";
            $searchTerm = '%' . $params['search'] . '%';
            $queryParams[] = $searchTerm;
            $queryParams[] = $searchTerm;
        }
        if (!empty($params['status'])) {
            $where[] = "h.status = ?";
            $queryParams[] = $params['status'];
        }
        if (!empty($params['verification'])) {
            $where[] = "h.verification_status = ?";
            $queryParams[] = $params['verification'];
        }
        if (!empty($params['work_type'])) {
            $where[] = "h.work_type = ?";
            $queryParams[] = $params['work_type'];
        }
        if (!empty($params['location'])) {
            $where[] = "h.location LIKE ?";
            $queryParams[] = '%' . $params['location'] . '%';
        }

        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        $countSql = "SELECT COUNT(*) as total FROM helpers h JOIN users u ON h.user_id = u.id {$whereClause}";
        $stmt = $this->pdo->prepare($countSql);
        $stmt->execute($queryParams);
        $total = (int) $stmt->fetch()['total'];

        $sql = "
            SELECT h.*, u.phone, u.status as user_status,
                   (SELECT COUNT(*) FROM bookings WHERE helper_id = h.id) as booking_count,
                   (SELECT COUNT(*) FROM ratings WHERE helper_id = h.id) as rating_count
            FROM helpers h
            JOIN users u ON h.user_id = u.id
            {$whereClause}
            ORDER BY h.created_at DESC
            LIMIT ? OFFSET ?
        ";
        $queryParams[] = $limit;
        $queryParams[] = $offset;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($queryParams);
        $helpers = $stmt->fetchAll();

        foreach ($helpers as &$helper) {
            $helper['skills'] = json_decode($helper['skills'] ?? '[]', true);
            $helper['languages'] = json_decode($helper['languages'] ?? '[]', true);
        }

        return $this->jsonResponse($response, [
            'success' => true,
            'data' => $helpers,
            'meta' => [
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'pages' => ceil($total / $limit)
            ]
        ]);
    }

    public function show(Request $request, Response $response, array $args): Response
    {
        $helperId = (int) $args['id'];
        $stmt = $this->pdo->prepare("
            SELECT h.*, u.phone, u.email, u.status as user_status, u.created_at as user_created_at
            FROM helpers h
            JOIN users u ON h.user_id = u.id
            WHERE h.id = ?
        ");
        $stmt->execute([$helperId]);
        $helper = $stmt->fetch();

        if (!$helper) {
            return $this->jsonResponse($response, ['success'=>false,'error'=>'Helper not found'], 404);
        }

        $helper['skills'] = json_decode($helper['skills'] ?? '[]', true);
        $helper['languages'] = json_decode($helper['languages'] ?? '[]', true);

        $stmt = $this->pdo->prepare("
             SELECT SUM(amount) as balance 
             FROM payments p 
             JOIN bookings b ON p.booking_id = b.id 
             WHERE b.helper_id = ? AND p.status = 'completed' AND p.payment_type = 'salary'
        ");
        $stmt->execute([$helperId]);
        $helper['wallet_balance'] = $stmt->fetchColumn() ?: 0;

        $stmt = $this->pdo->prepare("
            SELECT DISTINCT e.id, e.full_name, e.location, b.start_date, b.status as booking_status
            FROM bookings b
            JOIN employers e ON b.employer_id = e.id
            WHERE b.helper_id = ?
            ORDER BY b.created_at DESC
        ");
        $stmt->execute([$helperId]);
        $helper['employers'] = $stmt->fetchAll();

        $stmt = $this->pdo->prepare("
            SELECT b.*, e.full_name as employer_name
            FROM bookings b
            JOIN employers e ON b.employer_id = e.id
            WHERE b.helper_id = ?
            ORDER BY b.created_at DESC
            LIMIT 10
        ");
        $stmt->execute([$helperId]);
        $helper['bookings'] = $stmt->fetchAll();

        $stmt = $this->pdo->prepare("
            SELECT p.*, b.reference as booking_ref
            FROM payments p
            JOIN bookings b ON p.booking_id = b.id
            WHERE b.helper_id = ?
            ORDER BY p.created_at DESC
            LIMIT 20
        ");
        $stmt->execute([$helperId]);
        $helper['payments'] = $stmt->fetchAll();

        $stmt = $this->pdo->prepare("
            SELECT r.*, e.full_name as employer_name
            FROM ratings r
            JOIN employers e ON r.employer_id = e.id
            WHERE r.helper_id = ?
            ORDER BY r.created_at DESC
            LIMIT 10
        ");
        $stmt->execute([$helperId]);
        $helper['ratings'] = $stmt->fetchAll();

        $stmt = $this->pdo->prepare("SELECT * FROM verifications WHERE helper_id = ? ORDER BY created_at DESC");
        $stmt->execute([$helperId]);
        $helper['verifications'] = $stmt->fetchAll();

        return $this->jsonResponse($response, [
            'success' => true,
            'helper' => $helper
        ]);
    }

    public function update(Request $request, Response $response, array $args): Response
    {
        $helperId = (int) $args['id'];
        $data = $request->getParsedBody();
        $result = $this->matchingService->updateHelper($helperId, $data);
        if (!$result) {
            return $this->jsonResponse($response, ['success'=>false,'error'=>'Update failed'], 400);
        }
        return $this->jsonResponse($response, ['success'=>true,'message'=>'Helper updated']);
    }

    public function delete(Request $request, Response $response, array $args): Response
    {
        $helperId = (int) $args['id'];
        $stmt = $this->pdo->prepare("UPDATE helpers SET status = 'inactive', updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->execute([$helperId]);
        return $this->jsonResponse($response, ['success'=>true,'message'=>'Helper deactivated']);
    }

    public function verify(Request $request, Response $response, array $args): Response
    {
        $helperId = (int) $args['id'];
        $adminId = $request->getAttribute('admin_id');
        $data = $request->getParsedBody();

        $stmt = $this->pdo->prepare("
            SELECT id FROM verifications
            WHERE helper_id = ? AND status IN ('pending', 'pending_review')
            ORDER BY created_at DESC
            LIMIT 1
        ");
        $stmt->execute([$helperId]);
        $verification = $stmt->fetch();

        if (!$verification) {
            $stmt = $this->pdo->prepare("
                INSERT INTO verifications (helper_id, document_type, status, verified_by, verified_at)
                VALUES (?, 'manual', 'verified', ?, CURRENT_TIMESTAMP)
            ");
            $stmt->execute([$helperId, $adminId]);
            $this->matchingService->updateHelper($helperId, ['verification_status' => 'verified']);
        } else {
            $action = $data['action'] ?? 'approve';
            if ($action === 'approve') {
                $this->verificationService->approveVerification($verification['id'], $adminId);
            } else {
                $reason = $data['reason'] ?? 'Rejected by admin';
                $this->verificationService->rejectVerification($verification['id'], $adminId, $reason);
            }
        }

        return $this->jsonResponse($response, ['success'=>true,'message'=>'Verification updated']);
    }

    public function updateBadge(Request $request, Response $response, array $args): Response
    {
        $helperId = (int) $args['id'];
        $data = $request->getParsedBody();
        $badge = $data['badge_level'] ?? 'bronze';
        if (!in_array($badge, ['bronze', 'silver', 'gold'])) {
            return $this->jsonResponse($response, ['success'=>false,'error'=>'Invalid badge level'], 400);
        }
        $this->matchingService->updateHelper($helperId, ['badge_level' => $badge]);
        return $this->jsonResponse($response, ['success'=>true,'message'=>'Badge updated']);
    }

    public function bulkUpload(Request $request, Response $response): Response
    {
        $uploadedFiles = $request->getUploadedFiles();
        if (empty($uploadedFiles['csv'])) {
            return $this->jsonResponse($response, ['success'=>false,'error'=>'CSV file required'], 400);
        }

        $file = $uploadedFiles['csv'];
        if ($file->getError() !== UPLOAD_ERR_OK) {
            return $this->jsonResponse($response, ['success'=>false,'error'=>'File upload error'], 400);
        }

        $tempPath = sys_get_temp_dir() . '/bulk_' . uniqid() . '.csv';
        $file->moveTo($tempPath);

        $handle = fopen($tempPath, 'r');
        if (!$handle) {
            return $this->jsonResponse($response, ['success'=>false,'error'=>'Cannot read CSV file'], 400);
        }

        $headers = fgetcsv($handle);
        $results = ['created' => 0, 'failed' => 0, 'errors' => []];

        $headerMap = [
            'phone' => 'phone',
            'full_name' => 'full_name',
            'first_name' => 'first_name',
            'last_name' => 'last_name',
            'work_type' => 'work_type',
            'accommodation' => 'accommodation',
            'location' => 'location',
            'location_state' => 'location_state',
            'location_lga' => 'location_lga',
            'salary_min' => 'salary_min',
            'salary_max' => 'salary_max',
            'availability' => 'availability',
            'availability_date' => 'availability_date',
            'experience' => 'experience',
            'experience_years' => 'experience_years',
            'skills' => 'skills',
            'bio' => 'bio',
            'languages' => 'languages',
            'gender' => 'gender',
            'nin_number' => 'nin_number',
            'date_of_birth' => 'date_of_birth',
            'marital_status' => 'marital_status',
            'bank_code' => 'bank_code',
            'account_number' => 'account_number',
            'account_name' => 'account_name'
        ];

        while (($row = fgetcsv($handle)) !== false) {
            $data = array_combine($headers, $row) ?: [];
            $pin = $this->generateRandomPin();
            $pinHash = password_hash($pin, PASSWORD_BCRYPT);

            $skills = isset($data['skills']) ? explode(',', $data['skills']) : [];
            $languages = isset($data['languages']) ? explode(',', $data['languages']) : [];

            $this->pdo->beginTransaction();
            try {
                $phone = $data['phone'] ?? '';
                if (empty($phone)) {
                    throw new \Exception('Phone number required');
                }

                $stmt = $this->pdo->prepare("INSERT INTO users (phone, pin_hash, user_type, status) VALUES (?, ?, 'helper', 'active')");
                $stmt->execute([$phone, $pinHash]);
                $userId = (int)$this->pdo->lastInsertId();

                $stmt = $this->pdo->prepare("
                    INSERT INTO helpers (
                        user_id, full_name, first_name, last_name, work_type,
                        accommodation, location, location_state, location_lga,
                        salary_min, salary_max, availability, availability_date,
                        experience, experience_years, skills, bio, languages,
                        gender, nin_number, date_of_birth, marital_status,
                        bank_code, account_number, account_name, verification_status
                    ) VALUES (
                        ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending'
                    )
                ");
                $stmt->execute([
                    $userId,
                    $data['full_name'] ?? null,
                    $data['first_name'] ?? null,
                    $data['last_name'] ?? null,
                    $data['work_type'] ?? null,
                    $data['accommodation'] ?? null,
                    $data['location'] ?? null,
                    $data['location_state'] ?? null,
                    $data['location_lga'] ?? null,
                    $data['salary_min'] ?? 30000,
                    $data['salary_max'] ?? 60000,
                    $data['availability'] ?? 'Immediately',
                    $data['availability_date'] ?? null,
                    $data['experience'] ?? null,
                    $data['experience_years'] ?? 0,
                    json_encode($skills),
                    $data['bio'] ?? null,
                    json_encode($languages),
                    $data['gender'] ?? null,
                    $data['nin_number'] ?? null,
                    $data['date_of_birth'] ?? null,
                    $data['marital_status'] ?? null,
                    $data['bank_code'] ?? null,
                    $data['account_number'] ?? null,
                    $data['account_name'] ?? null
                ]);
                $helperId = (int)$this->pdo->lastInsertId();

                $this->pdo->commit();
                $results['created']++;
                $results['pins'][$phone] = substr($pin, -4);
            } catch (\Exception $e) {
                $this->pdo->rollBack();
                $results['failed']++;
                $results['errors'][] = ['row' => $row, 'message' => $e->getMessage()];
            }
        }

        fclose($handle);
        unlink($tempPath);

        return $this->jsonResponse($response, ['success'=>true,'summary'=>$results]);
    }

    private function generateRandomPin(): string
    {
        return (string)random_int(1000, 9999);
    }

    private function jsonResponse(Response $response, array $data, int $status = 200): Response
    {
        $payload = json_encode($data, JSON_INVALID_UTF8_SUBSTITUTE);
        $response->getBody()->write($payload);
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($status);
    }
}
