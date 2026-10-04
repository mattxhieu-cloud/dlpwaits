<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
require_once __DIR__ . '/inc_today_hours.php';
require_once __DIR__ . '/inc_fr_names.php';

$shortWaits = array();
foreach (array(array('API/DLPwaits.json','DLP'), array('API/Studioswaits.json','DAW')) as $src) {
    $pack = json_decode(@file_get_contents(__DIR__ . '/' . $src[0]), true);
    if (!$pack) continue;
    foreach (($pack['attractions'] ?? $pack) as $att) {
        if (!is_array($att)) continue;
        $type = $att['meta']['type'] ?? $att['entityType'] ?? '';
        $wait = $att['waitTime'] ?? $att['wait'] ?? null;
        $status = strtolower((string)($att['status'] ?? ''));
        if (stripos($type, 'RESTAURANT') !== false) continue;
        if ($wait === null || $wait === '' || !is_numeric($wait)) $wait = null;
        $srRaw = $att['meta']['singleRiderWaitTime'] ?? ($att['singleRiderWaitTime'] ?? null);
        $srOn = !empty($att['meta']['singleRider']) || !empty($att['singleRider']);
        $shortWaits[] = array(
            'name' => dland_fr_name($att['name'] ?? 'Attraction', $att['externalId'] ?? ''),
            'wait' => is_numeric($wait) ? (int)$wait : null,
            'singleRider' => ($srOn && is_numeric($srRaw)) ? (int)$srRaw : null,
            'park' => $src[1],
            'status' => $att['status'] ?? ''
        );
    }
}
usort($shortWaits, function ($a, $b) {
    $aw = is_numeric($a['wait']) ? $a['wait'] : 9999;
    $bw = is_numeric($b['wait']) ? $b['wait'] : 9999;
    return $aw - $bw;
});
$open = array_values(array_filter($shortWaits, function ($a) {
    $st = strtolower((string)$a['status']);
    return is_numeric($a['wait']) && in_array($st, array('operating','open','ouvert',''), true);
}));

$soonShows = array();
$laterShows = array();
$showsPack = json_decode(@file_get_contents(__DIR__ . '/API/Shows2.json'), true);
if (isset($showsPack['liveData'])) {
    $allShows = array();
    foreach ($showsPack['liveData'] as $show) {
        $etype = $show['entityType'] ?? '';
        if ($etype && $etype !== 'SHOW' && $etype !== 'ENTERTAINMENT') continue;
        $raw = $show['name'] ?? '';
        if (strpos($raw, 'Reserved') === 0) continue;
        if (stripos($raw, 'Restaurant') !== false || stripos($raw, 'Café') !== false || stripos($raw, 'Cafe') !== false) continue;
        foreach ($show['showtimes'] ?? array() as $st) {
            $ts = strtotime($st['startTime'] ?? '');
            if (!$ts || $ts < time() - 180) continue;
            $allShows[] = array(
                'name' => dland_fr_name($raw, $show['externalId'] ?? ''),
                'hh' => date('H:i', $ts),
                'ts' => $ts,
                'park' => (strpos($show['externalId'] ?? '', 'P1') === 0 ? 'DLP' : 'DAW')
            );
        }
    }
    usort($allShows, function ($a, $b) { return $a['ts'] - $b['ts']; });
    $limitSoon = time() + 90 * 60;
    foreach ($allShows as $s) {
        if ($s['ts'] <= $limitSoon && count($soonShows) < 8) $soonShows[] = $s;
        elseif ($s['ts'] > $limitSoon && count($laterShows) < 10) $laterShows[] = $s;
    }
}

echo json_encode(array('waits' => array_slice($open, 0, 12), 'rides' => $shortWaits, 'soon' => $soonShows, 'later' => $laterShows), JSON_UNESCAPED_UNICODE);
