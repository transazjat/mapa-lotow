<?php

declare(strict_types=1);

namespace Transazja\MapaLotowApi\Service;

use PDO;

final class AdminAuditService
{
    public function __construct(private PDO $pdo)
    {
    }

    public function log(
        int $adminUserId,
        string $action,
        string $entityType,
        ?int $entityId,
        string $summary,
        ?array $before = null,
        ?array $after = null,
        ?string $ipAddress = null
    ): void {
        $stmt = $this->pdo->prepare(
            "
            INSERT INTO ml_admin_audit_log (
                admin_user_id,
                action,
                entity_type,
                entity_id,
                summary,
                before_json,
                after_json,
                ip_address
            ) VALUES (
                :admin_user_id,
                :action,
                :entity_type,
                :entity_id,
                :summary,
                :before_json,
                :after_json,
                :ip_address
            )
            "
        );

        $stmt->execute([
            'admin_user_id' => $adminUserId,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'summary' => mb_substr($summary, 0, 255, 'UTF-8'),
            'before_json' => $before === null ? null : json_encode(
                $before,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            ),
            'after_json' => $after === null ? null : json_encode(
                $after,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            ),
            'ip_address' => $ipAddress,
        ]);
    }
}
