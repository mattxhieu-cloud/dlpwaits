<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
$in = json_decode(file_get_contents('php://input'), true);
$items = isset($in['items']) && is_array($in['items']) ? $in['items'] : array();
$clean = array();
foreach ($items as $it) {
    $sku = isset($it['sku']) ? preg_replace('/[^A-Z0-9]/', '', strtoupper($it['sku'])) : '';
    $id = isset($it['visualId']) ? preg_replace('/\D/', '', $it['visualId']) : '';
    if ($sku === '' || $id === '') continue;
    $clean[] = array('sku' => $sku, 'visualId' => $id, 'quantity' => 1);
}
if (!$clean) {
    echo json_encode(array('error' => 'Ajoute au moins un Pass avec son numéro.'));
    exit;
}
$body = json_encode(array(
    'startDate' => date('Y-m-d'),
    'endDate' => date('Y-m-d', strtotime('+4 months')),
    'enableNextYearPES' => false,
    'items' => $clean
));
$ch = curl_init('https://register.disneylandparis.com/availability/api/v3/availabilities');
curl_setopt_array($ch, array(
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $body,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 20,
    CURLOPT_HTTPHEADER => array(
        'Content-Type: application/json',
        'Accept: application/json',
        'Origin: https://www.disneylandparis.com',
        'Referer: https://www.disneylandparis.com/'
    )
));
$raw = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err = curl_error($ch);
curl_close($ch);
if ($raw === false || $code >= 400) {
    echo json_encode(array('error' => 'Disney n\'a pas renvoyé le calendrier (' . $code . '). ' . $err));
    exit;
}
echo $raw;
