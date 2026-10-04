<?php
session_start();
$pageTitle = "Calendrier d'accès";
$savedPasses = array();
if (!empty($_SESSION['user']['email']) && file_exists('connect2/users.json')) {
    $users = json_decode(file_get_contents('connect2/users.json'), true) ?: array();
    foreach ($users as $u) {
        if (($u['email'] ?? '') === $_SESSION['user']['email']) {
            $savedPasses = $u['passes'] ?? array();
            break;
        }
    }
}
include 'menu.php';
?>
<main class="access-page">
  <h1>Calendrier d'accès</h1>
  <p class="access-lead">Décoche un Pass pour recalculer. Un jour ouvert ouvre la page Disney des Pass, puis la validation.</p>
  <div id="passList" class="pass-list"></div>
  <p id="disneyState" class="access-state">Chargement…</p>
  <div class="cal-head">
    <button type="button" id="prevMonth" aria-label="Mois précédent">‹</button>
    <strong id="monthLabel"></strong>
    <button type="button" id="nextMonth" aria-label="Mois suivant">›</button>
  </div>
  <div class="cal-week"><span>L</span><span>M</span><span>M</span><span>J</span><span>V</span><span>S</span><span>D</span></div>
  <div id="accessCalendar" class="cal-grid"></div>
  <p class="cal-legend"><i class="ok"></i> Ouvert <i class="no"></i> Complet</p>
</main>
<style>
.access-page { padding: 16px 14px 120px; max-width: 520px; margin: 0 auto; }
.access-page h1 { margin: 8px 0 6px; font-size: 1.35rem; }
.access-lead, .access-state { color: inherit; opacity: .8; font-size: 14px; }
.pass-list { display: flex; flex-direction: column; gap: 8px; margin: 12px 0; }
.pass-chip { display: flex; gap: 8px; align-items: center; padding: 10px; border-radius: 14px; background: rgba(255,255,255,.08); }
html[data-theme="light"] .pass-chip { background: #fff; border: 1px solid #eadfC8; }
.pass-chip input[type=checkbox] { width: 22px; height: 22px; flex: 0 0 22px; }
.pass-chip select, .pass-chip .who { min-width: 0; }
.pass-chip .who { font-weight: 700; font-size: 14px; }
.pass-chip select { margin-left: auto; max-width: 46%; border-radius: 10px; padding: 6px; }
.cal-head { display: flex; align-items: center; justify-content: space-between; margin-top: 8px; }
.cal-head button { width: 40px; height: 40px; border: 0; border-radius: 50%; background: rgba(255,255,255,.12); color: inherit; font-size: 22px; }
.cal-week, .cal-grid { display: grid; grid-template-columns: repeat(7, 1fr); gap: 4px; }
.cal-week span { text-align: center; font-size: 11px; opacity: .6; padding-top: 6px; }
.day { min-height: 46px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 13px; font-weight: 700; }
.day.open { background:#1f8a4c; color:#fff; cursor:pointer; }
.day.full { background: #8d2b2b; color: #fff; }
.day.empty { visibility: hidden; }
.cal-legend { display: flex; gap: 12px; align-items: center; font-size: 13px; }
.cal-legend i { width: 12px; height: 12px; border-radius: 4px; display: inline-block; }
.cal-legend .ok { background: #1f8a4c; }
.cal-legend .no { background: #8d2b2b; }
</style>
<script>
var saved = <?php echo json_encode($savedPasses, JSON_UNESCAPED_UNICODE); ?>;
var skus = [
  ['TKATGC0DIR00AZZR0001','Gold adulte'],
  ['TKATSC0DIR00AZZC0001','Silver adulte'],
  ['TKATSC0DIR00CZZC0001','Silver enfant']
];
var calendar = {};
var cursor = new Date();
cursor.setDate(1);
var box = document.getElementById('passList');
var prefs = {};
try { prefs = JSON.parse(localStorage.getItem('dland_pass_sku') || '{}'); } catch (e) {}
saved.forEach(function(p){
  var code = p.code || p.visualId || '';
  var opts = skus.map(function(s){ return '<option value="'+s[0]+'"'+(prefs[code]===s[0]?' selected':'')+'>'+s[1]+'</option>'; }).join('');
  box.insertAdjacentHTML('beforeend', '<label class="pass-chip"><input type="checkbox" checked data-code="'+code+'"><span class="who">'+(p.holder||'Pass')+'</span><select data-code="'+code+'">'+opts+'</select></label>');
});
if (!saved.length) document.getElementById('disneyState').textContent = 'Aucun Pass enregistré dans l’application.';
function selectedItems(){
  var items = [];
  document.querySelectorAll('.pass-chip input').forEach(function(c){
    if (!c.checked) return;
    var sku = document.querySelector('select[data-code="'+c.dataset.code+'"]').value;
    prefs[c.dataset.code] = sku;
    items.push({ sku: sku, visualId: c.dataset.code, quantity: 1 });
  });
  localStorage.setItem('dland_pass_sku', JSON.stringify(prefs));
  return items;
}
function render(){
  var y = cursor.getFullYear(), m = cursor.getMonth();
  var names = ['janvier','février','mars','avril','mai','juin','juillet','août','septembre','octobre','novembre','décembre'];
  document.getElementById('monthLabel').textContent = names[m] + ' ' + y;
  var first = new Date(y, m, 1).getDay();
  first = first === 0 ? 6 : first - 1;
  var days = new Date(y, m + 1, 0).getDate();
  var html = '';
  for (var i = 0; i < first; i++) html += '<div class="day empty"></div>';
  for (var d = 1; d <= days; d++) {
    var key = y + '-' + String(m+1).padStart(2,'0') + '-' + String(d).padStart(2,'0');
    var info = calendar[key];
    var cls = !info ? '' : (info.available && info.unavailableReason === 'NONE' ? 'open' : 'full');
    html += '<div class="day '+cls+'" data-date="'+key+'">'+d+'</div>';
  }
  document.getElementById('accessCalendar').innerHTML = html;
}
function loadCalendar(){
  var items = selectedItems();
  if (!items.length) { document.getElementById('disneyState').textContent = 'Coche au moins un Pass.'; return; }
  document.getElementById('disneyState').textContent = 'Mise à jour…';
  fetch('calendrier-api.php', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ items: items }) })
    .then(function(r){ return r.json(); })
    .then(function(data){
      if (data.error) { document.getElementById('disneyState').textContent = data.error; return; }
      calendar = data.calendar || {};
      document.getElementById('disneyState').textContent = items.length + ' Pass utilisé' + (items.length>1?'s':'') + '.';
      render();
    })
    .catch(function(){ document.getElementById('disneyState').textContent = 'Impossible de joindre Disney.'; });
}
document.getElementById('prevMonth').onclick = function(){ cursor.setMonth(cursor.getMonth()-1); render(); };
document.getElementById('nextMonth').onclick = function(){ cursor.setMonth(cursor.getMonth()+1); render(); };
document.getElementById('passList').addEventListener('change', loadCalendar);
document.getElementById('accessCalendar').addEventListener('click', function(e){
  var day = e.target.closest('.day.open');
  if (!day) return;
  window.open('https://www.disneylandparis.com/ars-guest-calendar/fr-fr/add/select-party', '_blank');
});
if (saved.length) loadCalendar();
</script>
