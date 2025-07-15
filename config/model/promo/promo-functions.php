<?php
// Model: promo_functions.php

namespace App\Promo;

use PDO;
use PDOException;
use Exception;

class PromoService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function updateStatus(int $promoId, string $newStatus, int $adminId, array $config, string $env): void
    {
        $envConfig = getEnvironmentConfig();

        $normalizedStatus = strtolower(trim($newStatus));
        $allowedStatuses = ['active', 'inactive', 'scheduled', 'expired'];

        if (!in_array($normalizedStatus, $allowedStatuses, true)) {
            throw new Exception('Invalid status value: ' . htmlspecialchars($newStatus));
        }

        try {
            $this->pdo->beginTransaction();

            $sql = "UPDATE promos SET status = :status WHERE promo_id = :promo_id";
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindParam(':status', $normalizedStatus, PDO::PARAM_STR);
            $stmt->bindParam(':promo_id', $promoId, PDO::PARAM_INT);
            $stmt->execute();

            if ($stmt->rowCount() === 0) {
                $checkStmt = $this->pdo->prepare("SELECT COUNT(*) FROM promos WHERE promo_id = :promo_id");
                $checkStmt->bindParam(':promo_id', $promoId, PDO::PARAM_INT);
                $checkStmt->execute();
                $exists = $checkStmt->fetchColumn();

                $this->pdo->rollBack();

                if (!$exists) {
                    throw new Exception('Promo not found.');
                }

                throw new Exception('Status is already set to "' . $normalizedStatus . '".');
            }

            // Logging admin activity setelah berhasil update
            logAdminAction(
                admin_id: $adminId,
                action: 'update_promo_status',
                config: $envConfig,
                env: isLive() ? 'live' : 'local',
                table_name: 'promos',
                record_id: $promoId,
                details: 'Updated status to "' . $normalizedStatus . '"',
            );

            $this->pdo->commit();
        } catch (PDOException $e) {
            $this->pdo->rollBack();
            throw new Exception('Database error: ' . $e->getMessage(), 0, $e);
        }
    }
}
