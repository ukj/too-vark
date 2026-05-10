<?php declare(strict_types=1);
?><!DOCTYPE html>
<head><meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
<style>
<?php include_once 'src/style.css'; ?>
a{font-size:var(--fs-2xl);margin:var(--sp-12); padding:var(--sp-7);border:1pt solid var(--btn-silver-border); background:var(--btn-silver-bg);line-height:3rem;}
a,a:active,a:visited{color:var(--btn-silver-text);}
</style>
<h3>Starting Single-File Compilation...</h3>
<?php
 /* USAGE:
 * php compile.php (or localhost)
 *	→ creates index_release.php in the same directory
 *
 * DEPLOYMENT:
 *	Copy index_release.php( + app.sqlite + icon.png) to your web server. Rename to "index.php".
 *	No src/ directory needed — everything is in the single file.
 *
 * DEBUG BUILD:
 *	Set define('APP_DEBUG',true) in index.php → compile produces index_debug.php instead.
 *	debug.js included, no minification.
 *
 * COMPRESSION (release only):
 *	PHP: php_strip_whitespace() per file, then newlines at top-level boundaries (function/class ends).
 *	CSS: comment + whitespace removal, split after } and ;
 *	JS:  as-is (safe choice — gzip handles the rest).
 *	Markers /* included from … * / survive because compression runs BEFORE wrapping.
 */

$source_file = './index.php';
$output_file = './index_release.php';
$output_dbg_file = './index_debug.php';
$str_included_from = 'included from';

// ─── COMPRESSION FUNCTIONS

/**
 * Compress PHP code using php_strip_whitespace (tokenizer-based, safe).
 * Then split the resulting giant line at safe breakpoints using token_get_all:
 *   • newline after }
 *   • newline after ; when NOT inside parentheses (protects for-loop semicolons)
 */
function php_compress(string $content): string {
	// php_strip_whitespace() needs a file path
	$tmp = tempnam(sys_get_temp_dir(), 'tvc_');
	file_put_contents($tmp, "<?php\n" . $content);
	$stripped = php_strip_whitespace($tmp);
	unlink($tmp);

	// Remove the <?php we prepended
	$stripped = preg_replace('/^<\?php\s*/', '', $stripped);
	if (trim($stripped) === '') return '';

	// Split at top-level boundaries only (function/class ends).
	// One function = one compact line. Avoids the single-giant-line problem
	// without over-splitting at every semicolon.
	$tokens = token_get_all('<?php ' . $stripped);
	$out = '';
	$depth = 0;
	$first_tag = true;

	foreach ($tokens as $t) {
		if (is_array($t)) {
			// Skip only the < ? php we prepended, keep subsequent ones (views.php has ? >...< ? php)
			if ($t[0] === T_OPEN_TAG && $first_tag) { $first_tag = false; continue; }
			// Newline before top-level function/class definition
			if ($depth === 0 && in_array($t[0], [T_FUNCTION, T_CLASS], true)) $out .= "\n";
			$out .= $t[1];
		} else {
			$out .= $t;
			if ($t === '{') $depth++;
			elseif ($t === '}') { $depth--; if ($depth === 0) $out .= "\n"; }
		}
	}
	return trim($out);
}

/**
 * Compress CSS: strip comments, collapse whitespace, split at } and ;
 */
function css_compress(string $css): string {
	$css = preg_replace('!/\*.*?\*/!s', '', $css);
	$css = preg_replace('/\s+/', ' ', $css);
	$css = str_replace([' { ',' } ','; '], ['{','}',';'], $css);
	// Split into readable lines
	$css = str_replace('}', "}\n", $css);
	$css = str_replace(';', ";\n", $css);
	return trim($css);
}


// ─── TEMPLATE

function include_tpl_hfa($file,$content){
	global $str_included_from;
	return "

/* {$str_included_from} $file [[[*/
$content
/* EOF $file */

";
}


// ─── MAIN LOOP

$result_lines=array();
$source_code = file_get_contents($source_file);
$source_lines = explode("\n", $source_code);
$tag0=false;
$APP_DEBUG=false;


foreach ($source_lines as $line_num => $line){
	
	// Detect debug mode from index.php define
	if(str_contains($line,"define('APP_DEBUG',true")){
		$APP_DEBUG=true;
		$output_file=$output_dbg_file;
	}

	if( str_contains($line,'include_once ') ) {

		preg_match("/include_once\s+['\"]([^'\"]+)['\"]/", $line, $matches);
		$file = $matches[1] ?? '';
		
		echo "$file";

	if(file_exists($file)){
	$content = file_get_contents($file);
	$orig_size = strlen($content);

	// CSS: compress in release, as-is in debug
	if (str_ends_with($file, '.css')) {
		if(!$APP_DEBUG) $content = css_compress($content);
		$result_lines[] = include_tpl_hfa($file,$content);
	}
	// JS: include or skip debug.js based on APP_DEBUG
	elseif (str_ends_with($file, '.js')) {
		if (str_ends_with($file, '/debug.js')) {
			if($APP_DEBUG) $result_lines[] = include_tpl_hfa($file,$content);
			// release build: skip debug.js entirely
		} else {
			$result_lines[] = include_tpl_hfa($file,$content);
		}
	}
	// PHP: strip tags, compress in release
	else {
		$content = preg_replace('/^<\?php\s*/i', '', $content);
		$content = preg_replace('/declare\s*\(\s*strict_types\s*=\s*1\s*\)\s*;\s*/i', '', $content);
		$content = preg_replace('/\?>\s*$/', '', $content);

		if(!$APP_DEBUG) $content = php_compress($content);

		$result_lines[] = include_tpl_hfa($file,$content);
	}

	$ratio = $orig_size > 0 ? round(strlen($content) / $orig_size * 100) : 0;
	echo " ({$ratio}%)";

	}else {
	echo "<span style='color:var(--c-red)'>Warning: Could not find {$file}</span>";
	$result_lines[]=$line;
	}
	echo "<br>";

	} //line with include_once
else {
	$line = preg_replace('/declare\s*\(\s*strict_types\s*=\s*1\s*\)\s*;\s*/i', '', $line);
	$result_lines[]=$line;

	if($tag0===false && str_contains($line,'<?php') ){$tag0=true;
// Grab version from first ## heading in CHANGELOG.md
		$app_ver = 'dev';
		if (file_exists('CHANGELOG.md')) {
			preg_match('/^## (v\S+)/m', file_get_contents('CHANGELOG.md', false, null, 0, 512), $vm);
			if (!empty($vm[1])) $app_ver = $vm[1];
		}
		$result_lines[]="declare(strict_types=1);\ndefine('APP_VERSION', '$app_ver — " . date('Y-m-d H:i') . "');";
	}
}
}//lines


// ─── EMBED PLUGIN FILES
// The plugins.php loader uses glob() at runtime. For single-file deploy,
// we inline plugin PHP/JS/CSS directly so no plugins/ directory is needed.
$plugin_dir = './plugins/';
$plugin_ids = array();
if (is_dir($plugin_dir)) {
	echo "<br><b>Plugins:</b><br>";

	// PHP plugins — inline after plugins.php (which is already inlined above)
	// The glob() in plugins.php will find nothing in production, but
	// these inlined files define the same functions and return arrays.
	// We wrap each in a self-executing closure that feeds $plugin_routes etc.
	$plugin_php_block = "\n// ─── INLINED PLUGINS\n";
	foreach (glob($plugin_dir . '*.php') as $pf) {
		$plugin_ids[]=pathinfo($pf,PATHINFO_FILENAME);
		$content = file_get_contents($pf);
		$orig_size = strlen($content);
		$content = preg_replace('/^<\?php\s*/i', '', $content);
		$content = preg_replace('/\?>\s*$/', '', $content);

		// Split: extract function definitions (keep global) and the return [...] (wrap in loader)
		// The return statement registers the plugin — we need to feed it to the loader vars
		$content = preg_replace(
			'/^return\s+\[/m',
			'$_preg = [',
			$content
		);
		$content .= "\n" . '$_pid = $_preg["id"] ?? "";'
			. "\n" . 'if ($_pid) { $plugins[$_pid] = $_preg;'
			. ' if (isset($_preg["routes"])) foreach ($_preg["routes"] as $_r => $_h) $plugin_routes[$_r] = $_h;'
			. ' if (isset($_preg["views"])) foreach ($_preg["views"] as $_vk => $_vfn) $plugin_views[$_vk] = $_vfn;'
			. ' if (isset($_preg["nav"])) $plugin_nav = array_merge($plugin_nav, $_preg["nav"]);'
			. ' if (isset($_preg["etag_routes"])) $plugin_etag_routes = array_merge($plugin_etag_routes, $_preg["etag_routes"]);'
			. ' if (isset($_preg["write_routes"])) $plugin_write_routes = array_merge($plugin_write_routes, $_preg["write_routes"]);'
			. ' }' . "\n";

		if (!$APP_DEBUG) $content = php_compress($content);
		$plugin_php_block .= include_tpl_hfa($pf, $content);

		$ratio = $orig_size > 0 ? round(strlen($content) / $orig_size * 100) : 0;
		echo basename($pf) . " ({$ratio}%)<br>";
	}

// Insert PHP plugins right after the plugins.php EOF marker
;
	
	$compiled_str = implode("\n", $result_lines);
	
	// plugins not to reload
	$compiled_str=str_replace(
	'$plugins = [];', "\$plugins=array('".implode("'=>[], '", $plugin_ids)."'=>[]);", $compiled_str);

	$marker = '/* EOF src/plugins.php */';
	$pos = strpos($compiled_str, $marker);
	if ($pos !== false) {
		$insert_at = $pos + strlen($marker);
		$compiled_str = substr($compiled_str, 0, $insert_at) . $plugin_php_block . substr($compiled_str, $insert_at);
		$result_lines = explode("\n", $compiled_str);
	}

	// JS plugins — append after app.js
	foreach (glob($plugin_dir . '*.js') as $jf) {
		$content = file_get_contents($jf);
		$result_lines[] = include_tpl_hfa($jf, $content);
		echo basename($jf) . "<br>";
	}

	// CSS plugins — inject before </style>
	$css_block = '';
	foreach (glob($plugin_dir . '*.css') as $cf) {
		$content = file_get_contents($cf);
		if (!$APP_DEBUG) $content = css_compress($content);
		$css_block .= include_tpl_hfa($cf, $content);
		echo basename($cf) . "<br>";
	}
	if ($css_block) {
		$compiled_str = implode("\n", $result_lines);
		$compiled_str = str_replace('</style>', $css_block . "\n</style>", $compiled_str);
		$result_lines = explode("\n", $compiled_str);
	}
}


clearstatcache(false,$output_file);
$output_fs0 = intval(filesize($output_file));

if (!file_exists($source_file)) {
	die("Error: index.php not found.");
}

$compiled_code = implode("\n", $result_lines);
$bytes = file_put_contents($output_file, $compiled_code);

echo "
<h3 style='color:green'>Success!</h3>";

echo "<a href=\"./tests/run.php?verbose=1\" target=\"_blank\">Run PHP Tests</a> ";
echo "<a href=\"./tests/run_js.html\" target=\"_blank\">Run JS Tests</a><br> ";
echo "<a href=\"./tests/audit_plugin.html\" target=\"_blank\">audit JS Tests</a><br> ";


echo "<a href=\"./Styleguide.html\" target=\"_blank\"><span class=\"status-2\">*</span>Styleguide<span class=\"status-0\">*</span></a> ";
echo "<a href=\"tests/demo-db.php\" target=\"_blank\">GenDemoDB</a><br> ";


echo "<a href=\"$source_file\" target=\"_blank\">Source: $source_file</a> <br>";
echo "<a href=\"$output_file\" target=\"_blank\">Output: $output_file</a> <br><br> ";

echo "Created $app_ver <b>$output_file</b> (" . number_format($bytes / 1024, 2) . " KB) ". ($output_fs0 - $bytes).'b';

?>