<?php
/**
 * PLUGIN LOADER — convention-based file discovery.
 *
 * Scans plugins/ for .php files. Each returns an array:
 *   'id'             => string (required, unique)
 *   'routes'         => ['endpoint' => callable($pdo,$d,$uid,$is_admin,$dc)]
 *   'views'          => ['view_name' => callable():void]
 *   'nav'            => ['view_name' => 'Label']
 *   'admin_only'     => bool (applies to nav visibility)
 *   'schema_version' => int
 *   'schema'         => string[] (SQL statements)
 *   'etag_routes'    => string[] (GET endpoints that use ETag caching)
 *   'write_routes'   => string[] (POST endpoints that bust stat cache)
 *
 * Plugin handlers use the same P11 contract as core: (PDO,array,int,bool,DateContext) → [int,array]
 */

$plugins = []; // compiler.php depends exact format
$plugin_routes = [];
$plugin_views = [];
$plugin_nav = [];
$plugin_etag_routes = [];
$plugin_write_routes = [];

$plugin_dir = DATA_DIR . 'plugins/';
if (is_dir($plugin_dir)) {
	foreach (glob($plugin_dir . '*.php') as $pf) {

// overload protection
		if(!array_key_exists(
	pathinfo($pf,PATHINFO_FILENAME), $plugins)) $reg = include $pf;

		if (!is_array($reg) || empty($reg['id'])) continue;
		$plugins[$reg['id']] = $reg;

		if (isset($reg['routes']))
			foreach ($reg['routes'] as $route => $handler)
				$plugin_routes[$route] = $handler;

		if (isset($reg['views']))
			foreach ($reg['views'] as $vk => $vfn)
				$plugin_views[$vk] = $vfn;

		if (isset($reg['nav']))
			$plugin_nav = array_merge($plugin_nav, $reg['nav']);

		if (isset($reg['etag_routes']))
			$plugin_etag_routes = array_merge($plugin_etag_routes, $reg['etag_routes']);

		if (isset($reg['write_routes']))
			$plugin_write_routes = array_merge($plugin_write_routes, $reg['write_routes']);
	}
}

// Plugin schema migrations — uses config table for version tracking
foreach ($plugins as $id => $reg) {
	if (!isset($reg['schema_version']) || !isset($reg['schema'])) continue;
	$key = 'plugin_schema_' . $id;
	$cur = (int)($cfg[$key] ?? 0);
	if ($cur < $reg['schema_version']) {
		foreach ((array)$reg['schema'] as $sql) $pdo->exec($sql);
		$pdo->prepare("INSERT INTO config (key,val) VALUES (?,?) ON CONFLICT(key) DO UPDATE SET val=excluded.val")
			->execute([$key, (string)$reg['schema_version']]);
		$cfg[$key] = (string)$reg['schema_version'];
	}
}
