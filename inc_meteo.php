<?php
$meteoHours = array();
$meteoNow = null;
$meteoFile = __DIR__ . '/API/meteo2.json';
$pack = json_decode(@file_get_contents($meteoFile), true);
$packDate = $pack['current_condition']['date'] ?? '';
$today = date('d.m.Y');
if (!$pack || $packDate !== $today) {
    $fresh = @file_get_contents('https://www.prevision-meteo.ch/services/json/chessy');
    $decoded = json_decode($fresh ?: '', true);
    if ($decoded && isset($decoded['current_condition'])) {
        $pack = $decoded;
        @file_put_contents($meteoFile, json_encode($decoded));
    }
}
$nowH = (int) date('G');
foreach (array('fcst_day_0', 'fcst_day_1') as $dayKey) {
    if (empty($pack[$dayKey]['hourly_data'])) continue;
    foreach ($pack[$dayKey]['hourly_data'] as $label => $row) {
        $h = (int) str_replace('H00', '', $label);
        if ($dayKey === 'fcst_day_0' && $h < $nowH) continue;
        $item = array(
            'time' => sprintf('%02d:00', $h),
            'temp' => isset($row['TMP2m']) ? round($row['TMP2m']) : '--',
            'icon' => isset($row['ICON']) ? $row['ICON'] : '',
            'cap' => isset($row['CONDITION']) ? $row['CONDITION'] : '',
            'wind' => isset($row['WNDSPD10m']) ? $row['WNDSPD10m'] : ''
        );
        if ($meteoNow === null) $meteoNow = $item;
        $meteoHours[] = $item;
        if (count($meteoHours) >= 12) break 2;
    }
    if ($meteoHours) break;
}
if (!$meteoNow && isset($pack['current_condition'])) {
    $c = $pack['current_condition'];
    $meteoNow = array('time' => $c['hour'], 'temp' => $c['tmp'], 'icon' => $c['icon'], 'cap' => $c['condition'], 'wind' => $c['wnd_spd']);
    $meteoHours[] = $meteoNow;
}
?>
<div class="meteo-inline">
    <?php if ($meteoNow): ?>
    <div class="meteo-now">
        <?php if (!empty($meteoNow['icon'])): ?><img src="<?php echo htmlspecialchars($meteoNow['icon']); ?>" alt=""><?php endif; ?>
        <div>
            <strong><?php echo htmlspecialchars($meteoNow['temp']); ?>°</strong>
            <span><?php echo htmlspecialchars($meteoNow['cap']); ?> · <?php echo htmlspecialchars((string)$meteoNow['wind']); ?> km/h</span>
        </div>
    </div>
    <?php endif; ?>
    <div class="meteo-strip" id="meteoScroll">
        <?php foreach ($meteoHours as $h): ?>
        <div class="meteo-chip">
            <em><?php echo htmlspecialchars($h['time']); ?></em>
            <?php if (!empty($h['icon'])): ?><img src="<?php echo htmlspecialchars($h['icon']); ?>" alt=""><?php endif; ?>
            <b><?php echo htmlspecialchars($h['temp']); ?>°</b>
            <small><?php echo htmlspecialchars($h['cap']); ?></small>
        </div>
        <?php endforeach; ?>
        <?php if (!$meteoHours): ?><div class="meteo-chip"><em>Météo</em><b>--</b><small>indisponible</small></div><?php endif; ?>
    </div>
</div>
