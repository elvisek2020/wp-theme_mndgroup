<?php
// Lokální vývoj mndgroup.eu — NENASAZOVAT na produkci.
define('DB_NAME', 'mndgroup');
define('DB_USER', 'wp');
define('DB_PASSWORD', 'wp');
define('DB_HOST', 'db');
define('DB_CHARSET', 'utf8mb4');
define('DB_COLLATE', '');
$table_prefix = 'wp_';

define('AUTH_KEY', 'mj3Ob%]K]#f..zY+?0RePlZ=i#}MMxk|a7Jk)M*ck0NqRG87|cd5X(bGViGi_p~t');
define('SECURE_AUTH_KEY', ')eh}J{8[1d5;;%.RU5A05Bd!hnQ]+:rGoi4uK9}m}6^>9NY@wm1V)L~S(tc3hU>3');
define('LOGGED_IN_KEY', 'vrm3cMp%%Z!ZzfOZHE.vAI?#P|H=ZtMn}!x{VzQz[mscD4ng{:n4OMRGQ*4.~fk;');
define('NONCE_KEY', '{F2(WbsEIaXhSJ)kwPv,PWWKRUC2ed*T]PZ*60Hs)dU|BUW?{21??B)N6p8<CNOX');
define('AUTH_SALT', 'wR+ErwX]73hmi4~>uOD+hO^Hq)O<S6H4J[*bFv?]Z~ae[2N9t~=PpLZHxF?=:OAb');
define('SECURE_AUTH_SALT', 'MKPd)w6h|Qgb-i4|i4pDvO5rb8hu@IquRh7f;UB4[iJP9@5GX.YtU59j.jKy~{oH');
define('LOGGED_IN_SALT', 'OwV,dk::1px9!kRqJXp~m[(Pfk5%2=GJe#fGm5!;U7e}]4:rCAA{0e>uG9Bei=rC');
define('NONCE_SALT', '7+XGHDm)btEv4-1vUA>_^ot.(xStOtRJ_|+v[#(b^n1hJrjSKO6PH,VV,O]p%TXC');

// Adresa podle toho, odkud přistupuješ.
$mnd_allowed_hosts = array( 'localhost:8321', '127.0.0.1:8321' );
$mnd_host = strtolower( $_SERVER['HTTP_HOST'] ?? 'localhost:8321' );
if ( ! in_array( $mnd_host, $mnd_allowed_hosts, true ) ) {
	$mnd_host = 'localhost:8321';
}
define('WP_HOME', 'http://' . $mnd_host);
define('WP_SITEURL', 'http://' . $mnd_host);
define('WP_ENVIRONMENT_TYPE', 'local');
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
define('SCRIPT_DEBUG', false);
define('DISALLOW_FILE_EDIT', true);
define('DISABLE_WP_CRON', true);
define('AUTOMATIC_UPDATER_DISABLED', true);
define('WP_AUTO_UPDATE_CORE', false);

if ( ! defined('ABSPATH') ) {
	define('ABSPATH', __DIR__ . '/');
}
require_once ABSPATH . 'wp-settings.php';
