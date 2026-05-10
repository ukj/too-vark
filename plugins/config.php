<?php
/**
 * PLUGIN: config — config key-value store + year archive.
 * Extracted from: api_handlers.php (api_config_*, api_archive_year, api_can_archive).
 *
 * Handlers follow P11: explicit params, [code, body] return, no globals.
 */

/** Check if archiving is available (late December only). */
function api_can_archive(): bool
{
    return idate("m") === 12 && idate("d") > 20;
}

/** Config route — GET lists all, POST upserts. Admin only. */
function plugin_config(
    PDO $pdo,
    array $d,
    int $uid,
    bool $is_admin,
    DateContext $dc,
    string $method = "GET",
): array {
    if (!$is_admin) {
        return [403, ["error" => "forbidden"]];
    }
    return match ($method) {
        "GET" => (function () {
            global $cfg;
            return [
                200,
                array_map(
                    fn($k, $v) => ["key" => $k, "val" => $v],
                    array_keys($cfg),
                    array_values($cfg),
                ),
            ];
        })(),
        "POST" => plugin_config_save($pdo, $d, $uid, $is_admin, $dc),
        default => [405, ["error" => "method_not_allowed"]],
    };
}

/** Upsert a config key-value pair. Admin only. */
function plugin_config_save(
    PDO $pdo,
    array $d,
    int $uid,
    bool $is_admin,
    DateContext $dc,
    string $method = "POST",
): array {
    if (!$is_admin) {
        return [403, ["error" => "forbidden"]];
    }
    $key = trim($d["key"] ?? "");
    if ($key === "") {
        return [400, ["error" => "key is required"]];
    }
    return db_try(
        "config/save",
        fn() => $pdo
            ->prepare(
                "INSERT INTO config (key, val) VALUES (?, ?) ON CONFLICT(key) DO UPDATE SET val=excluded.val",
            )
            ->execute([$key, $d["val"] ?? ""])
            ? [200, ["msg" => "ok"]]
            : [500, ["error" => "Database error"]],
    );
}

/** Delete a config key. Admin only. */
function plugin_config_delete(
    PDO $pdo,
    array $d,
    int $uid,
    bool $is_admin,
    DateContext $dc,
    string $method = "POST",
): array {
    if (!$is_admin) {
        return [403, ["error" => "forbidden"]];
    }
    $key = trim($d["key"] ?? "");
    if ($key === "") {
        return [400, ["error" => "key is required"]];
    }
    return db_try("config/delete", function () use ($pdo, $key) {
        $stmt = $pdo->prepare("DELETE FROM config WHERE key = ?");
        $stmt->execute([$key]);
        return $stmt->rowCount() === 0
            ? [404, ["error" => "not_found"]]
            : [200, ["msg" => "ok"]];
    });
}

/** Archive current year's tasks table. Admin only. */
function plugin_archive_year(
    PDO $pdo,
    array $d,
    int $uid,
    bool $is_admin,
    DateContext $dc,
    string $method = "POST",
): array {
    if (!$is_admin) {
        return [403, ["error" => "forbidden"]];
    }
    if (!api_can_archive()) {
        return [400, ["error" => "not_available"]];
    }
    $tbl = "tasks_" . date("Y");
    return db_try("archive_year", function () use ($pdo, $tbl) {
        if (
            $pdo
                ->query(
                    "SELECT 1 FROM sqlite_master WHERE type='table' AND name='$tbl'",
                )
                ->fetchColumn()
        ) {
            return [400, ["error" => "archive_already_exists"]];
        }
        $pdo->exec("ALTER TABLE tasks RENAME TO $tbl");
        ensure_tasks_table($pdo);
        return [200, ["msg" => "ok"]];
    });
}

$_preg = [
    "id" => "config",
    "routes" => [
        "config" => "plugin_config",
        "config/delete" => "plugin_config_delete",
        "archive_year" => "plugin_archive_year",
    ],
    "etag_routes" => ["config"],
    "write_routes" => ["config", "config/delete", "archive_year"],
];
