<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

if (!defined('PAGE_ROLE')) {
    $signedInUser = currentUser();
    if ($signedInUser !== null) {
        header('Location: ' . (($signedInUser['role'] ?? '') === 'ADMIN' ? 'admin.php' : 'user.php'));
        exit;
    }

    define('PAGE_ROLE', 'LOGIN');
}
?>
<!doctype html>
<html lang="th">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= APP_NAME ?><?= PAGE_ROLE === 'ADMIN' ? ' — ADMIN' : (PAGE_ROLE === 'USER' ? ' — USER' : '') ?></title>
<style>
[data-page-role="ADMIN"] #account .pill,[data-page-role="ADMIN"] #account .account-user{display:none!important}
:root{--ink:#18342b;--muted:#61736a;--paper:#f4f0e7;--panel:#fffdf8;--line:#d9dfd1;--green:#1f6048;--green-dark:#164534;--lime:#e1e8bd;--orange:#b96d38;--red:#a8423b;--shadow:0 10px 26px rgba(24,52,43,.08)}*{box-sizing:border-box}body{margin:0;background:var(--paper);color:var(--ink);font-family:system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;line-height:1.45}body:before{content:"";position:fixed;inset:0;pointer-events:none;opacity:.26;background-image:radial-gradient(#426454 .55px,transparent .7px);background-size:10px 10px}button,input,select,textarea{font:inherit}button{cursor:pointer;border:0;transition:transform .15s,background .15s,box-shadow .15s}button:focus-visible,input:focus-visible,select:focus-visible,textarea:focus-visible{outline:3px solid #aecf75;outline-offset:2px}.shell{position:relative;max-width:1240px;margin:auto;padding:24px 26px 48px}.topbar{display:flex;justify-content:space-between;align-items:center;padding:0 0 22px;margin-bottom:8px;border-bottom:1px solid var(--line)}.brand{font-family:Georgia,"Times New Roman",serif;font-size:29px;font-weight:700;letter-spacing:-.035em}.brand small{font:700 10px/1.5 system-ui,sans-serif;color:var(--green);display:block;letter-spacing:.16em;text-transform:uppercase;margin-top:2px}.pill{display:inline-flex;align-items:center;gap:5px;border:1px solid #c9d5bc;border-radius:4px;padding:7px 10px;background:#f2f6e7;font-size:12px;font-weight:700}.grid{display:grid;grid-template-columns:minmax(0,1.18fr) minmax(360px,.82fr);gap:20px;align-items:start}.panel{background:var(--panel);border:1px solid var(--line);box-shadow:var(--shadow);padding:24px;border-radius:4px}.grid>section:first-child>.panel:first-child{border-top:4px solid var(--green)}.modal-backdrop{position:fixed;inset:0;background:#122d23a8;display:grid;place-items:center;padding:18px;z-index:20}.modal{width:min(720px,100%);max-height:90vh;overflow:auto;background:var(--panel);border:1px solid var(--line);box-shadow:0 24px 80px #17221d66;padding:26px;border-radius:5px}.modal-head{display:flex;justify-content:space-between;align-items:center;gap:12px}.hero{padding:31px 0 25px;display:flex;justify-content:space-between;align-items:end;gap:30px}.hero h1{font-family:Georgia,"Times New Roman",serif;font-size:clamp(34px,4.5vw,58px);letter-spacing:-.055em;line-height:1.02;margin:5px 0 0;max-width:720px}.hero p{max-width:455px;margin:0 0 3px;color:var(--muted);font-size:14px;line-height:1.65}.eyebrow{font-size:10px;color:var(--green);font-weight:800;letter-spacing:.15em;text-transform:uppercase}.stat-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:9px;margin:15px 0}.stat{padding:13px 14px;background:#f0f4e4;border-left:3px solid #aec477}.stat b{font-size:24px;line-height:1.1;display:block}.stat span,.metric{color:var(--muted);font-size:12px}.inventory-total{display:flex;align-items:center;gap:17px;padding:14px 0 19px;border-bottom:1px solid var(--line)}.inventory-total strong{font-family:Georgia,"Times New Roman",serif;font-size:62px;letter-spacing:-.06em;line-height:.8;color:var(--green-dark)}.inventory-total span{max-width:155px;color:var(--muted);font-size:12px;line-height:1.35}.form-row{display:grid;grid-template-columns:1fr 1fr;gap:13px}label{font-size:12px;font-weight:700;color:#3f594d;display:block;margin:15px 0 6px}input,select,textarea{width:100%;border:1px solid #cbd5c7;padding:10px 11px;border-radius:3px;background:#fff;color:var(--ink)}textarea{line-height:1.5;resize:vertical}input:hover,select:hover,textarea:hover{border-color:#91aa92}.primary{background:var(--green);color:#fff;padding:11px 16px;border-radius:3px;font-weight:750}.primary:hover{background:var(--green-dark);box-shadow:0 5px 12px #1f604830;transform:translateY(-1px)}.secondary{background:#e7edce;color:#294837;padding:9px 12px;border:1px solid #cedbb3;border-radius:3px;font-size:13px;font-weight:700}.secondary:hover{background:#dae6b5}.danger{background:#f5dfd7;color:#883b34;padding:9px 11px;border-radius:3px;font-size:13px;font-weight:700}.table-wrap{overflow:auto;border-top:1px solid var(--line)}table{width:100%;border-collapse:collapse;font-size:13px}th,td{text-align:left;padding:12px 8px;border-bottom:1px solid #e4e7df;white-space:nowrap}tbody tr{transition:background .15s}tbody tr:hover{background:#f7f8f0}th{color:var(--muted);font-size:10px;font-weight:800;letter-spacing:.09em;text-transform:uppercase;background:#fafaf4}.badge{display:inline-block;padding:4px 7px;border-radius:3px;font-size:10px;font-weight:800;letter-spacing:.04em;background:#e9eee7}.badge.FLAGGED,.badge.WATCH{background:#f7e7b6;color:#75530e}.badge.PENDING_REVIEW,.badge.SUSPICIOUS{background:#f5ddd1;color:#88412d}.badge.BLOCKED,.badge.HIGH_RISK,.badge.REJECTED{background:#edc6c1;color:#7d302c}.notice{padding:11px 12px;background:#edf4df;border-left:3px solid var(--green);font-size:13px;margin:13px 0}.error{color:var(--red);font-size:13px}.hidden{display:none!important}.login{max-width:440px;margin:10vh auto}.login h1,h2{font-family:Georgia,"Times New Roman",serif;letter-spacing:-.025em}.login h1{font-size:35px;margin:7px 0}.tabs{display:flex;gap:8px;margin-bottom:18px}.tabs button{background:transparent;padding:9px 12px;border-bottom:2px solid transparent}.tabs button.active{border-color:var(--green);color:var(--green)}.actions{display:flex;gap:8px;flex-wrap:wrap;align-items:center}.contributor{display:inline-flex;align-items:center;gap:8px;font-weight:700}.avatar{display:inline-grid;place-items:center;width:27px;height:27px;border-radius:50%;background:var(--green);color:white;font-size:10px;font-weight:800;letter-spacing:.03em}.quantity-add{color:var(--green);font-weight:800}.quantity-remove{color:var(--orange);font-weight:800}@media(max-width:760px){.shell{padding:18px 15px 36px}.topbar{align-items:flex-start;gap:12px}.topbar #account{display:flex;justify-content:flex-end;gap:6px;flex-wrap:wrap}.hero{display:block;padding:25px 0 20px}.hero h1{font-size:39px}.hero p{margin-top:13px}.grid{grid-template-columns:1fr;gap:14px}.panel{padding:18px}.stat-grid{grid-template-columns:1fr 1fr}.stat-grid .stat:last-child{grid-column:span 2}.form-row{grid-template-columns:1fr}.inventory-total strong{font-size:54px}th,td{padding:11px 7px}.actions .primary{flex:1}.pill{font-size:11px}}
/* Fluid layout: keep rows single-line and shorten long values instead of causing page overflow. */
.table-wrap{overflow:visible}
table{table-layout:fixed}
th,td{white-space:nowrap;overflow:hidden;text-overflow:ellipsis;vertical-align:middle}
.grid{grid-template-columns:minmax(0,.88fr) minmax(0,1.12fr)}
td .contributor{max-width:100%;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
table td[style]{white-space:nowrap!important;max-width:0!important}
.account-user{display:inline-flex;align-items:center;gap:7px;white-space:nowrap}.account-avatar{display:inline-grid;place-items:center;width:28px;height:28px;border-radius:50%;background:var(--green);color:#fff;font-size:11px;font-weight:800}.account-status{display:inline-flex;align-items:center;gap:5px;padding:6px 9px;border:1px solid #c9d5bc;border-radius:999px;background:#f2f6e7;color:var(--green-dark);font-size:11px;font-weight:800;white-space:nowrap}.account-status:before{content:"";width:7px;height:7px;border-radius:50%;background:#3f9b61}.logout-button{white-space:nowrap}
.quantity-remove{color:var(--red)!important}.action-add{border-color:#73a58a;background:#edf7f0;color:var(--green-dark)}.action-remove{border-color:#d99a95;background:#fff0ee;color:#8b312c}.withdraw-submit{background:var(--red)!important}.withdraw-submit:hover{background:#85312c!important}
@media(max-width:760px){.table-wrap table{font-size:12px}.table-wrap th,.table-wrap td{padding:10px 5px}.table-wrap th:first-child,.table-wrap td:first-child{width:18%}.contributor{gap:5px}.avatar{width:24px;height:24px;flex:0 0 24px}.hero h1{max-width:100%}}
/* White layered workspace: canvas > zones > panels > nested controls. */
:root{--paper:#fff;--panel:#fff;--surface:#f7faf8;--surface-soft:#fbfcfb;--line:#d8e1dc;--line-strong:#c4d1ca;--shadow:0 12px 30px rgba(24,52,43,.07),0 2px 7px rgba(24,52,43,.045)}
html{background:#fff}
body{background:#fff}
body:before{display:none}
.shell{max-width:1280px;padding-top:22px}
.topbar{min-height:62px;margin:0 0 18px;padding:0 2px 18px;border-bottom:1px solid var(--line-strong);background:#fff}
.hero{position:relative;margin:0 0 22px;padding:28px 30px;background:var(--surface);border:1px solid var(--line);border-top:4px solid var(--green);border-radius:5px;box-shadow:0 7px 20px rgba(24,52,43,.045)}
.panel{position:relative;background:var(--panel);border-color:var(--line-strong);border-top:3px solid #d7e3dc;padding:24px;box-shadow:var(--shadow)}
.grid>section:first-child>.panel:first-child{border-top-color:var(--green)}
.grid>section>.panel+ .panel{margin-top:18px!important}
.panel h2{margin-top:6px;margin-bottom:16px}
.stat-grid{gap:11px}
.stat{background:var(--surface);border:1px solid var(--line);border-left:3px solid #8eac8f;box-shadow:0 2px 5px rgba(24,52,43,.035)}
.inventory-total{margin-bottom:15px;padding:13px 2px 20px;border-bottom-color:var(--line-strong)}
.table-wrap{overflow:visible;background:var(--surface-soft);border:1px solid var(--line);border-radius:3px;box-shadow:inset 0 1px 0 #fff}
table{background:#fff}
th,td{border-bottom-color:#e4ebe7}
th{background:#f4f8f5;color:#52685d}
tbody tr:nth-child(even){background:#fcfdfc}
tbody tr:last-child td{border-bottom:0}
tbody tr:hover{background:#f1f7f3}
form{margin-top:12px;padding:18px;background:var(--surface);border:1px solid var(--line);border-radius:4px;box-shadow:inset 0 1px 0 #fff}
.bulk-row+.bulk-row{margin-top:11px;padding-top:11px;border-top:1px solid var(--line)}
input,select,textarea{border-color:var(--line-strong);background:#fff;box-shadow:0 1px 2px rgba(24,52,43,.035)}
input:focus,select:focus,textarea:focus{border-color:#7e9f8d;box-shadow:0 0 0 3px rgba(31,96,72,.08)}
.primary{box-shadow:0 3px 8px rgba(31,96,72,.16)}
.secondary{background:#f0f5f1;border-color:#cbd8d0}
.secondary:hover{background:#e5eee8}
.danger{background:#fff0ee;border:1px solid #e6bbb7;color:#8b312c}
.notice{background:#f0f7f2;border:1px solid #d5e5da;border-left:3px solid var(--green);border-radius:3px}
.modal-backdrop{background:rgba(18,45,35,.58)}
.modal{background:#fff;border-color:var(--line-strong);border-top:4px solid var(--green);box-shadow:0 26px 70px rgba(18,45,35,.24)}
.modal .table-wrap{margin-top:16px}
.login{border-top-color:var(--green)}
.login form{margin-top:18px}
@media(max-width:760px){.shell{padding-top:14px}.topbar{min-height:0;margin-bottom:13px;padding-bottom:14px}.hero{margin-bottom:14px;padding:22px 18px}.panel{padding:17px}.grid>section>.panel+ .panel{margin-top:14px!important}form{padding:14px}.table-wrap{overflow:visible}.modal{padding:20px 16px}}
/* Keep workflow panels readable on tablet and mobile; this must follow the fluid desktop grid. */
@media(max-width:900px){.grid{grid-template-columns:minmax(0,1fr);gap:16px}}
.user-inventory-list{display:grid;gap:14px;margin-top:18px}.user-inventory-card{padding:16px;border:1px solid var(--line);border-radius:4px;background:var(--surface-soft);box-shadow:0 4px 12px rgba(24,52,43,.05)}.user-inventory-head{display:flex;align-items:baseline;justify-content:space-between;gap:12px;margin-bottom:10px}.user-inventory-head h3{margin:0;font-size:17px}.user-inventory-total{color:var(--green-dark);font-weight:800;white-space:nowrap}
/* Final visual system: a calm, compact operational ledger. */
:root{
  --ink:#17352b;
  --muted:#60746a;
  --paper:#fff;
  --panel:#fff;
  --surface:#f7faf8;
  --surface-soft:#fbfcfb;
  --line:#dce5e0;
  --line-strong:#c9d6cf;
  --green:#236347;
  --green-dark:#174832;
  --red:#a33f39;
  --shadow:0 8px 24px rgba(26,66,49,.055);
}
body{font-size:14px;line-height:1.5;overflow-x:hidden}
.shell{width:100%;max-width:1360px;padding:20px 28px 48px}
.topbar{min-height:58px;gap:18px;margin-bottom:16px;padding:0 0 16px}
.brand{flex:0 0 auto;font-size:27px;line-height:1}
.brand small{margin-top:5px;color:#527063;letter-spacing:.14em}
#account{display:flex;justify-content:flex-end;align-items:center;gap:7px;flex-wrap:wrap}
.account-user{padding:5px 2px;color:var(--ink);font-size:13px}
.account-status{padding:5px 8px;border-color:#cfe0d5;border-radius:4px;background:#f0f7f2;color:#22563e}
.logout-button{min-height:34px}
.hero{display:grid;grid-template-columns:minmax(0,1fr) minmax(290px,420px);grid-template-rows:auto auto;align-items:end;column-gap:48px;row-gap:5px;margin-bottom:18px;padding:22px 26px;border-top-width:3px;box-shadow:none}
.hero .eyebrow{grid-column:1;grid-row:1}
.hero h1{grid-column:1;grid-row:2;margin:0;font-size:clamp(30px,3vw,43px);line-height:1.08;letter-spacing:-.04em}
.hero p{grid-column:2;grid-row:1 / span 2;align-self:center;max-width:none;margin:0;font-size:13px;line-height:1.7}
.eyebrow{font-size:10px;letter-spacing:.13em}
.grid{grid-template-columns:minmax(0,.9fr) minmax(0,1.1fr);gap:18px}
.panel{padding:22px;border-color:var(--line);border-top:1px solid var(--line-strong);border-radius:5px;box-shadow:var(--shadow)}
.grid>section:first-child>.panel:first-child{border-top:3px solid var(--green)}
.panel h2{margin:4px 0 15px;font-size:23px;line-height:1.25}
.stat-grid{gap:10px;margin:0 0 16px}
.stat{min-width:0;padding:12px 14px;border-left-width:3px;border-radius:3px;box-shadow:none}
.stat b{font-size:22px;font-variant-numeric:tabular-nums}
.inventory-total{gap:15px;margin:6px 0 15px;padding:10px 2px 18px}
.inventory-total strong{font-size:52px;font-variant-numeric:tabular-nums}
form{margin-top:10px;padding:16px;border-radius:4px;box-shadow:none}
label{margin:12px 0 5px;font-size:11px;letter-spacing:.01em}
input,select,textarea{min-height:40px;padding:9px 10px;border-radius:4px}
textarea{min-height:84px}
.primary,.secondary,.danger{min-height:36px;border-radius:4px;line-height:1.25}
.primary{padding:10px 15px;box-shadow:none}
.primary:hover{box-shadow:0 4px 10px rgba(31,96,72,.17)}
.secondary{background:#f4f8f5;border-color:#cedbd3;color:#254c3a}
.danger{background:#fff5f4;border-color:#e9c3bf;color:#913832}
.table-wrap{width:100%;min-width:0;border-radius:4px;overflow:hidden}
table{font-size:12px}
th,td{height:43px;padding:10px 11px}
th{font-size:9px;letter-spacing:.1em}
.metric{font-size:11px;line-height:1.4}
.badge{padding:3px 6px;font-size:9px}
.notice{margin:13px 0 0}
.modal-backdrop{padding:20px;backdrop-filter:blur(2px)}
.modal{width:min(680px,100%);padding:24px;border-radius:6px}
.modal-head h2{margin:3px 0 0;font-size:24px}
.modal-head>div{min-width:0}
.modal-head h2{overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.user-inventory-modal{width:min(660px,100%);overflow-x:hidden}
.user-inventory-view{min-width:0}
.user-inventory-list{display:grid;gap:0;margin-top:18px;border:1px solid var(--line);border-radius:5px;overflow:hidden}
.user-inventory-card{display:grid;grid-template-columns:minmax(0,1fr) auto auto;align-items:center;gap:18px;min-width:0;width:100%;padding:14px 15px;border:0;border-bottom:1px solid var(--line);border-radius:0;background:#fff;box-shadow:none}
.user-inventory-card:last-child{border-bottom:0}
.user-inventory-card:hover{background:var(--surface)}
.user-inventory-name{min-width:0;margin:0;overflow:hidden;color:var(--ink);font-size:14px;font-weight:800;text-overflow:ellipsis;white-space:nowrap}
.user-inventory-total{min-width:92px;color:var(--green-dark);font-size:13px;font-weight:800;text-align:right;white-space:nowrap}
.user-inventory-open{min-width:88px;min-height:34px;padding:7px 11px;font-size:11px}
.user-inventory-detail-summary{display:flex;align-items:center;justify-content:space-between;gap:16px;margin:17px 0 13px;padding:13px 15px;border:1px solid var(--line);border-left:3px solid var(--green);border-radius:4px;background:var(--surface)}
.user-inventory-detail-summary span{color:var(--muted);font-size:11px}
.user-inventory-detail-summary strong{color:var(--green-dark);font-size:22px;font-variant-numeric:tabular-nums lining-nums;white-space:nowrap}
.user-inventory-table-wrap{margin-top:0!important}
.user-inventory-table{table-layout:fixed}
.user-inventory-table th:last-child,.user-inventory-table td:last-child{width:112px;text-align:right}
.user-inventory-empty{margin-top:18px;padding:28px 18px;border:1px dashed #c7d8ce;border-radius:4px;background:var(--surface);color:var(--muted);font-size:12px;text-align:center}
.user-inventory-loading{margin-top:18px;padding:26px 18px;color:var(--muted);font-size:12px;text-align:center}
.user-inventory-loading:before{content:"";display:inline-block;width:13px;height:13px;margin-right:8px;border:2px solid #c8d7ce;border-top-color:var(--green);border-radius:50%;vertical-align:-2px;animation:inventory-spin .7s linear infinite}
.history-pagination{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-top:14px;padding-top:14px;border-top:1px solid var(--line)}
.history-pagination-status{color:var(--muted);font-size:12px;font-weight:700;font-variant-numeric:tabular-nums}
@keyframes inventory-spin{to{transform:rotate(360deg)}}
.inventory-table th:first-child,.inventory-table td:first-child{width:44px;text-align:center}
.inventory-table th:last-child,.inventory-table td:last-child{width:132px;text-align:right}

/* Numeric rhythm: stable glyph widths and ledger-style column alignment. */
.stat b,
.inventory-total strong,
.metric strong,
.review-count,
.review-trust,
.packet-amount,
.packet-meta,
.risk-cell,
.user-inventory-total,
.quantity-add,
.quantity-remove,
.numeric-ledger td,
input[type="number"]{
  font-variant-numeric:tabular-nums lining-nums;
  font-feature-settings:"tnum" 1,"lnum" 1;
}
.numeric-column{text-align:right!important}
.numeric-column strong{font-weight:800}
.temporal-column{font-variant-numeric:tabular-nums lining-nums;font-feature-settings:"tnum" 1,"lnum" 1;text-align:center!important}
input[type="number"]{font-size:15px;font-weight:750;letter-spacing:.015em;text-align:right}
.stat b{letter-spacing:-.015em}
.review-trust,.packet-meta{letter-spacing:.01em}

/* Review packets: one readable row per grouped submission. */
.review-panel{border-top:3px solid var(--green)}
.review-panel-head{display:flex;align-items:end;justify-content:space-between;gap:20px;margin-bottom:14px}
.review-panel-head h2{margin-bottom:0}
.review-count{flex:0 0 auto;color:var(--muted);font-size:12px;font-weight:700}
.review-table col:nth-child(1){width:17%}
.review-table col:nth-child(2){width:28%}
.review-table col:nth-child(3){width:13%}
.review-table col:nth-child(4){width:22%}
.review-table col:nth-child(5){width:20%}
.review-table td{height:68px}
.review-user{display:block;overflow:hidden;color:var(--ink);font-size:13px;font-weight:800;text-overflow:ellipsis}
.review-trust{display:block;margin-top:2px;color:var(--muted);font-size:10px}
.packet-summary{display:flex;align-items:center;gap:9px;min-width:0}
.packet-amount{display:block;font-size:14px;line-height:1.2}
.packet-meta{display:block;margin-top:3px;color:var(--muted);font-size:10px}
.details-button{flex:0 0 auto;min-height:32px;padding:7px 10px;background:#e8f2ec;border:1px solid #b8d0c1;color:var(--green-dark);font-size:11px}
.details-button:hover{background:#dcece2;border-color:#93b39f}
.risk-cell strong{display:inline-block;min-width:18px;font-variant-numeric:tabular-nums}
.reason-cell{color:#405a4e}
.review-actions{flex-wrap:nowrap;gap:6px}
.review-actions button{min-height:33px;padding:7px 10px;font-size:11px}
.empty-state{padding:26px 18px;border:1px dashed #c7d8ce;border-radius:4px;background:var(--surface);color:var(--muted);text-align:center}
.batch-modal .inventory-total{background:var(--surface);border:1px solid var(--line);padding:15px;margin:17px 0 14px}
.batch-modal .inventory-total strong{margin-left:10px}
.batch-modal .table-wrap{margin-top:0}
.batch-modal .modal-actions{justify-content:flex-end;padding-top:2px}

@media(max-width:820px){
  .review-panel-head{display:block;margin-bottom:11px}
  .review-count{display:block;margin-top:5px}
  .review-table thead{display:none}
  .review-table,.review-table tbody,.review-table tr,.review-table td{display:block;width:100%}
  .review-table tbody tr td,.review-table tbody tr td:first-child{width:100%!important;max-width:none!important}
  .review-table{background:transparent}
  .review-table tbody{display:grid;gap:10px}
  .review-table tr{padding:13px;border:1px solid var(--line);border-left:3px solid #8cab98;border-radius:4px;background:#fff;box-shadow:0 3px 10px rgba(24,52,43,.045)}
  .review-table td{position:relative;height:auto;min-height:0;padding:6px 0 6px 88px;border:0;overflow:visible;white-space:normal;text-overflow:clip}
  .review-table td:before{content:attr(data-label);position:absolute;left:0;top:7px;width:76px;color:var(--muted);font-size:9px;font-weight:800;letter-spacing:.08em;text-transform:uppercase}
  .review-table .reason-cell{display:-webkit-box;padding-right:2px;overflow:hidden;-webkit-box-orient:vertical;-webkit-line-clamp:2}
  .review-table .numeric-column{text-align:left!important}
  .packet-summary{align-items:flex-start;justify-content:space-between}
  .review-actions{padding-top:9px}
  .review-actions button{flex:1}
}
@media(max-width:900px){
  .shell{padding:17px 20px 40px}
  .hero{grid-template-columns:minmax(0,1fr) minmax(240px,340px);column-gap:28px;padding:20px 22px}
  .hero h1{font-size:32px}
  .grid{grid-template-columns:minmax(0,1fr);gap:16px}
}
@media(max-width:640px){
  .shell{padding:13px 12px 30px}
  .topbar{display:block;padding-bottom:13px}
  .brand{margin-bottom:13px;font-size:25px}
  #account{justify-content:flex-start;gap:6px}
  .account-user{order:-2}
  .account-status{order:-1}
  .logout-button{min-height:33px;padding:7px 9px;font-size:11px}
  .hero{display:block;margin-bottom:12px;padding:18px 16px}
  .hero h1{margin-top:4px;font-size:29px}
  .hero p{margin-top:10px;line-height:1.55}
  .panel{padding:15px}
  .panel h2{font-size:21px}
  .stat-grid{grid-template-columns:repeat(3,minmax(0,1fr));gap:6px}
  .stat-grid .stat:last-child{grid-column:auto}
  .stat{padding:10px 8px}
  .stat b{font-size:19px}
  .stat span{display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
  form{padding:12px}
  .actions{gap:7px}
  .modal-backdrop{align-items:end;padding:0}
  .modal{width:100%;max-height:92vh;padding:19px 14px;border-radius:8px 8px 0 0}
  .modal-head h2{font-size:21px}
  .table-wrap:not(.review-table-wrap){overflow-x:auto}
  .table-wrap:not(.review-table-wrap) table{min-width:520px}
  .batch-modal .table-wrap table{min-width:0}
  .inventory-table-wrap,.user-inventory-table-wrap{overflow:hidden!important}
  .inventory-table-wrap table,.user-inventory-table-wrap table{width:100%;min-width:0!important}
  .inventory-table th:first-child,.inventory-table td:first-child{width:34px;padding-left:4px;padding-right:4px}
  .inventory-table th:last-child,.inventory-table td:last-child{width:92px;padding-left:5px;padding-right:8px;text-align:right}
  .inventory-table th:nth-child(2),.inventory-table td:nth-child(2){width:auto}
  .user-inventory-head{align-items:center}
  .user-inventory-total{margin-left:auto}
  .user-inventory-table th:first-child,.user-inventory-table td:first-child{width:auto}
  .user-inventory-table th:last-child,.user-inventory-table td:last-child{width:76px}
  .user-inventory-card{grid-template-columns:minmax(0,1fr) auto;gap:5px 10px;padding:13px 12px}
  .user-inventory-total{min-width:0;margin-left:0;font-size:12px}
  .user-inventory-open{grid-column:1 / -1;width:100%;margin-top:5px}
  .user-inventory-detail-summary{padding:12px}
}
</style>
</head>
<body><main class="shell"><div id="app"></div></main>
<script>
  const api=async(path,options={})=>{const headers={'Content-Type':'application/json',...(options.headers||{})};if(state.csrfToken)headers['X-CSRF-Token']=state.csrfToken;const r=await fetch('api.php?route='+encodeURIComponent(path),{...options,headers});const d=await r.json();if(!r.ok)throw Error(d.error||'เกิดข้อผิดพลาด');if(path.startsWith('cages/global-history/'))state.globalHistoryMeta=d;return d};
const esc=v=>String(v??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));
const displayTime=v=>esc(String(v??'').replace(/\+00(?::?00)?$/,''));
const globalHistoryRows=history=>history.map(x=>'<tr><td>'+displayTime(x.created_at)+'</td><td><strong>'+esc(x.username)+'</strong></td><td>'+esc(x.cage_type)+'</td><td>'+esc(x.action)+' '+esc(x.quantity)+'</td><td>'+status(x.status)+'</td></tr>').join('');
const status=v=>v==='APPROVED'&&state.user?.uses_user_view?'':'<span class="badge '+esc(v)+'">'+esc(v)+'</span>';
  let state={user:null,csrfToken:null,globalHistoryMeta:null};
  let globalHistoryPage=1;
  function decorateHistoryPagination(){const table=[...document.querySelectorAll('#content table')].find(candidate=>[...candidate.querySelectorAll('thead th')].some(header=>header.textContent.trim()==='ผู้ทำรายการ'));if(!table||table.parentElement.querySelector('.history-pagination'))return;const meta=state.globalHistoryMeta;if(!meta)return;const footer=document.createElement('div');footer.className='history-pagination';footer.innerHTML='<button type="button" class="secondary" data-history-previous '+(meta.has_previous?'':'disabled')+'>ก่อนหน้า</button><span class="history-pagination-status">หน้า '+esc(meta.page)+' · แสดง '+esc(meta.history.length)+' รายการ</span><button type="button" class="secondary" data-history-next '+(meta.has_next?'':'disabled')+'>ถัดไป</button>';table.parentElement.appendChild(footer);const loadPage=async page=>{footer.querySelectorAll('button').forEach(button=>button.disabled=true);try{const result=await api('cages/global-history/'+page);globalHistoryPage=result.page;table.querySelector('tbody').innerHTML=globalHistoryRows(result.history);footer.remove();decorateContributors()}catch(error){footer.querySelector('.history-pagination-status').textContent=error.message;footer.querySelectorAll('button').forEach(button=>button.disabled=false)}};footer.querySelector('[data-history-previous]').onclick=()=>loadPage(Math.max(1,Number(meta.page)-1));footer.querySelector('[data-history-next]').onclick=()=>loadPage(Number(meta.page)+1)}
  function decorateNumericTables(root=document){const quantitative=new Set(['จำนวน','จำนวนรวม','เพิ่มสะสม','ลดสะสม','ยอดสุทธิ','Trust','Transactions','รายการทั้งหมด','Risk','ความเสี่ยง']);root.querySelectorAll('table:not([data-numeric-decorated])').forEach(table=>{table.dataset.numericDecorated='1';table.classList.add('numeric-ledger');const headers=[...table.querySelectorAll('thead th')];headers.forEach((header,index)=>{const label=header.textContent.trim();const className=quantitative.has(label)?'numeric-column':label==='เวลา'?'temporal-column':'';if(!className)return;header.classList.add(className);table.querySelectorAll('tbody tr').forEach(row=>row.children[index]?.classList.add(className))})})}
  function decorateContributors(){decorateNumericTables();document.querySelectorAll('#content .eyebrow').forEach(label=>{if(label.textContent.trim()==='All users inventory')label.textContent='กรงรวมที่ทุกคนส่ง'});document.querySelectorAll('#content select[name="action"]').forEach(select=>{const paint=()=>{const removing=select.value==='REMOVE';select.classList.toggle('action-add',!removing);select.classList.toggle('action-remove',removing);select.closest('form')?.querySelector('button.primary')?.classList.toggle('withdraw-submit',removing)};paint();if(!select.dataset.colorBound){select.dataset.colorBound='1';select.addEventListener('change',paint)}});document.querySelectorAll('#content table').forEach(table=>{const headers=[...table.querySelectorAll('thead th')];const amountIndex=headers.findIndex(header=>header.textContent.trim()==='จำนวน');if(amountIndex>=0)table.querySelectorAll('tbody tr').forEach(row=>{const cell=row.children[amountIndex];if(!cell)return;const value=cell.textContent.trim();cell.classList.toggle('quantity-add',value.startsWith('ADD'));cell.classList.toggle('quantity-remove',value.startsWith('REMOVE'))})});decorateHistoryPagination();if(pageRole==='ADMIN')return;document.querySelectorAll('#content table').forEach(table=>{const headers=[...table.querySelectorAll('thead th')];if(!headers.some(header=>header.textContent.trim()==='ผู้ทำรายการ'))return;const statusIndex=headers.findIndex(header=>header.textContent.trim()==='สถานะ');if(statusIndex<0)return;headers[statusIndex].style.display='none';table.querySelectorAll('tbody tr').forEach(row=>{if(row.children[statusIndex])row.children[statusIndex].style.display='none'})})}
  function layout(content){document.querySelector('#app').innerHTML='<header class="topbar"><div class="brand">BEACON TEAM<small>Minecraft inventory control</small></div><div id="account"></div></header>'+content;const contentNode=document.querySelector('#content');if(contentNode)new MutationObserver(decorateContributors).observe(contentNode,{childList:true,subtree:true});}
  async function start(){try{const result=await api('auth/me');state.user=result.user;state.csrfToken=result.csrf_token}catch(e){}state.user?renderApp():renderLogin()}
  function renderLogin(){layout('<section class="panel login"><div class="eyebrow">Internal access</div><h1>เข้าสู่ระบบ</h1><p class="metric">ผู้ใช้ทั่วไปกรอกเฉพาะชื่อ ส่วนผู้ดูแลระบบต้องกรอกรหัสผ่าน</p><form id="login"><label>ชื่อผู้ใช้</label><input name="username" maxlength="80" required autofocus autocomplete="username"><label>รหัสผ่าน (เฉพาะ ADMIN)</label><input name="password" type="password" inputmode="numeric" autocomplete="current-password"><button class="primary" style="margin-top:18px;width:100%">เข้าสู่ระบบ</button><div id="msg"></div></form></section>');document.querySelector('#login').onsubmit=async e=>{e.preventDefault();const f=new FormData(e.target);try{const result=await api('auth/login',{method:'POST',body:JSON.stringify(Object.fromEntries(f))});state.user=result.user;state.csrfToken=result.csrf_token;renderApp()}catch(x){document.querySelector('#msg').innerHTML='<p class="error">'+esc(x.message)+'</p>'}}}
async function renderApp(){layout('<section class="hero"><div class="eyebrow">'+(state.user.role==='ADMIN'?'Operations console':'Shared inventory')+'</div><h1>'+ (state.user.role==='ADMIN'?'ดูเฉพาะสิ่งที่ผิดปกติ':'จำนวนกรงที่ทุกคนส่ง อยู่ในที่เดียว')+'</h1><p>'+(state.user.role==='ADMIN'?'รายการปกติจะผ่านอัตโนมัติ หน้านี้จะแสดงเฉพาะรายการที่ต้องใช้ดุลยพินิจ':'ยอดกรงหน้านี้รวมรายการที่ผ่านการอนุมัติของผู้ใช้ทุกคน')+'</p></section><div id="content"></div>');document.querySelector('#account').innerHTML='<span class="pill">'+esc(state.user.username)+' · '+esc(state.user.role)+'</span> '+(state.user.role==='ADMIN'?'<button id="addCages" class="secondary">หน้าเพิ่มกรง</button><button id="addUsers" class="secondary">เพิ่มคน</button>':'<button id="myTotal" class="secondary">ยอดกรงที่ทุกคนส่ง</button>')+' <button id="logout" class="secondary">ออกจากระบบ</button>';document.querySelector('#logout').onclick=async()=>{await api('auth/logout',{method:'POST'});state.user=null;renderLogin()};if(state.user.role==='ADMIN'){document.querySelector('#addCages').onclick=adminAddPage;document.querySelector('#addUsers').onclick=adminUsersPage}else document.querySelector('#myTotal').onclick=openMyTotal;state.user.role==='ADMIN'?adminView():userView()}
async function adminAddPage(){await userView();const back=document.createElement('button');back.className='secondary';back.textContent='กลับ Dashboard';back.onclick=adminView;document.querySelector('#content').prepend(back)}
async function userView(){const [b,types,h]=await Promise.all([api('cages/balance'),api('cages/types'),api('cages/history')]);document.querySelector('#content').innerHTML='<div class="grid"><section><div class="panel"><div class="eyebrow">Current balance</div><div class="stat-grid">'+b.balances.map(x=>'<div class="stat"><b>'+esc(x.quantity)+'</b><span>'+esc(x.cage_type)+'</span></div>').join('')+'</div><div class="metric">Trust score: <strong>'+Number(b.trust_score).toFixed(1)+'</strong> / 100</div></div><div class="panel" style="margin-top:22px"><div class="eyebrow">New transaction</div><h2>อัปเดตจำนวนกรง</h2><form id="tx"><div class="form-row"><div><label>ชนิดกรง</label><select name="cage_type">'+types.cages.map(x=>'<option>'+esc(x.cage_type)+'</option>').join('')+'</select></div><div><label>การทำรายการ</label><select name="action"><option value="ADD">เพิ่มกรง</option><option value="REMOVE">ลดกรง</option></select></div></div><label>จำนวน</label><input type="number" name="quantity" min="1" max="10000" required><label>หมายเหตุ (ถ้ามี)</label><textarea name="reason" rows="2"></textarea><button class="primary" style="margin-top:14px">ส่งรายการ</button><div id="txmsg"></div></form></div></section><section class="panel"><div class="eyebrow">Your history</div><h2>ประวัติรายการ</h2><div class="table-wrap"><table><thead><tr><th>เวลา</th><th>กรง</th><th>จำนวน</th><th>สถานะ</th></tr></thead><tbody>'+h.history.map(x=>'<tr><td>'+displayTime(x.created_at)+'</td><td>'+esc(x.cage_type)+'</td><td>'+esc(x.action)+' '+esc(x.quantity)+'</td><td>'+status(x.status)+'</td></tr>').join('')+'</tbody></table></div></section></div>';document.querySelector('#tx').onsubmit=async e=>{e.preventDefault();const f=new FormData(e.target);try{const r=await api('cages/transactions',{method:'POST',body:JSON.stringify(Object.fromEntries(f))});document.querySelector('#txmsg').innerHTML='<div class="notice">'+esc(r.message)+'</div>';userView()}catch(x){document.querySelector('#txmsg').innerHTML='<p class="error">'+esc(x.message)+'</p>'}}}
function parseCageText(value){return value.split(/\r?\n/).map(line=>line.trim()).filter(Boolean).map(line=>{const match=line.match(/^(.+?)\s*(?:[:=,]|\s)\s*(\d+)\s*$/);return match?{cage_type:match[1].trim(),quantity:Number(match[2])}:null}).filter(Boolean)}
async function userView(){const [b,types,h]=await Promise.all([api('cages/global-balance'),api('cages/types'),api('cages/global-history/'+globalHistoryPage)]);const cageOptions=types.cages.map(x=>'<option value="'+esc(x.cage_type)+'">'+esc(x.cage_type)+'</option>').join('');const balanceRows=b.balances.sort((a,z)=>Number(z.quantity)-Number(a.quantity)).map((x,i)=>'<tr><td>'+((i+1))+'</td><td>'+esc(x.cage_type)+'</td><td><strong>'+esc(x.quantity)+'</strong></td></tr>').join('');document.querySelector('#content').innerHTML='<div class="grid"><section><div class="panel"><div class="eyebrow">All users inventory</div><div class="inventory-total"><strong>'+esc(b.total_quantity)+'</strong><span>กรงที่ทุกคนส่งรวมกัน</span></div><div class="table-wrap inventory-table-wrap"><table class="inventory-table"><thead><tr><th>#</th><th>ชนิดกรง</th><th>จำนวนรวม</th></tr></thead><tbody>'+balanceRows+'</tbody></table></div><div class="metric" style="margin-top:14px">ตารางนี้รวมยอดของผู้ใช้ทุกคน</div></div><div class="panel" style="margin-top:22px"><div class="eyebrow">Bulk transaction</div><h2>เพิ่มหลายชนิดพร้อมกัน</h2><form id="tx"><div class="form-row"><div><label>การทำรายการ</label><select name="action"><option value="ADD">เพิ่มกรง</option><option value="REMOVE">ลดกรง</option></select></div><div><label>หมายเหตุ (ถ้ามี)</label><input name="reason"></div></div><label>วางรายการแบบข้อความ</label><textarea name="cage_text" rows="6" placeholder="Enderman 28\nMagma Cube 18\nZombie Piglin 22"></textarea><p class="metric">หนึ่งรายการต่อหนึ่งบรรทัด: ชื่อกรง ตามด้วยจำนวน</p><div id="bulkRows"><div class="form-row bulk-row"><div><label>ชนิดกรง</label><select name="cage_type">'+cageOptions+'</select></div><div><label>จำนวน</label><input type="number" name="quantity" min="1" max="10000" value="1" required></div></div></div><div class="actions" style="margin-top:12px"><button type="button" class="secondary" id="addRow">+ เพิ่มชนิดกรง</button><button class="primary">ส่งรายการทั้งหมด</button></div><div id="txmsg"></div></form></div></section><section class="panel"><div class="eyebrow">Shared history</div><h2>ประวัติรายการทั้งหมด</h2><div class="table-wrap"><table><thead><tr><th><center>เวลา</center></th><th>ผู้ทำรายการ</th><th>กรง</th><th>จำนวน</th><th>สถานะ</th></tr></thead><tbody>'+globalHistoryRows(h.history)+'</tbody></table></div></section></div>';document.querySelector('#addRow').onclick=()=>{const row=document.createElement('div');row.className='form-row bulk-row';row.innerHTML='<div><label>ชนิดกรง</label><select name="cage_type">'+cageOptions+'</select></div><div><label>จำนวน</label><div class="actions"><input type="number" name="quantity" min="1" max="10000" value="1" required><button type="button" class="danger removeRow">ลบ</button></div></div>';row.querySelector('.removeRow').onclick=()=>row.remove();document.querySelector('#bulkRows').appendChild(row)};document.querySelector('#tx').onsubmit=async e=>{e.preventDefault();const form=new FormData(e.target);const rows=form.get('cage_text').trim()?parseCageText(form.get('cage_text')):[...document.querySelectorAll('.bulk-row')].map(row=>({cage_type:row.querySelector('[name="cage_type"]').value,quantity:Number(row.querySelector('[name="quantity"]').value)}));try{const r=await api('cages/transactions',{method:'POST',body:JSON.stringify({action:form.get('action'),reason:form.get('reason'),items:rows})});document.querySelector('#txmsg').innerHTML='<div class="notice">'+esc(r.message)+' '+r.items.map(x=>esc(x.cage_type)+' '+status(x.status)).join(' · ')+'</div>';globalHistoryPage=1;userView()}catch(x){document.querySelector('#txmsg').innerHTML='<p class="error">'+esc(x.message)+'</p>'}}}
async function adminView(){const [o,q]=await Promise.all([api('admin/overview'),api('admin/pending-reviews')]);const counts=Object.fromEntries(o.summary.map(x=>[x.status,x.count]));document.querySelector('#content').innerHTML='<div class="stat-grid"><div class="stat"><b>'+Number(counts.PENDING_REVIEW||0)+'</b><span>Pending review</span></div><div class="stat"><b>'+Number(counts.BLOCKED||0)+'</b><span>High risk blocked</span></div><div class="stat"><b>'+Number(counts.FLAGGED||0)+'</b><span>Flagged but approved</span></div></div><section class="panel"><div class="eyebrow">Review queue</div><h2>รายการที่ระบบคัดมาให้ตรวจ</h2><div class="table-wrap"><table><thead><tr><th>User</th><th>รายการ</th><th>Risk</th><th>เหตุผล</th><th>Action</th></tr></thead><tbody>'+q.items.map(x=>'<tr><td>'+esc(x.username)+'<br><span class="metric">Trust '+Number(x.trust_score).toFixed(1)+'</span></td><td>'+esc(x.action)+' '+esc(x.quantity)+' '+esc(x.cage_type)+'<br>'+status(x.status)+'</td><td><strong>'+esc(x.risk_score)+'</strong> '+status(x.risk_level)+'</td><td style="max-width:300px;white-space:normal">'+esc(x.risk_reasons||'ตรวจสอบรูปแบบโดยรวม')+'</td><td><div class="actions">'+(x.status!=='BLOCKED'?'<button class="secondary" onclick="review('+x.id+',\'approve\')">อนุมัติ</button>':'')+'<button class="danger" onclick="review('+x.id+',\'reject\')">ปฏิเสธ</button></div></td></tr>').join('')+'</tbody></table></div></section><section class="panel" style="margin-top:22px"><div class="eyebrow">Trust watchlist</div><h2>ผู้ใช้ที่ควรติดตาม</h2><div class="table-wrap"><table><thead><tr><th>User</th><th>Trust</th><th>Transactions</th></tr></thead><tbody>'+o.suspicious_users.map(x=>'<tr><td>'+esc(x.username)+'</td><td>'+Number(x.trust_score).toFixed(1)+'</td><td>'+esc(x.transactions)+'</td></tr>').join('')+'</tbody></table></div></section>'}
  async function adminUsersPage(){const result=await api('admin/users');document.querySelector('#content').innerHTML='<button class="secondary" id="backAdmin">กลับ Dashboard</button><div class="grid" style="margin-top:18px"><section class="panel"><div class="eyebrow">User management</div><h2>เพิ่มคน</h2><p class="metric">ผู้ใช้ใหม่จะเข้าสู่ระบบด้วยชื่อที่กำหนดไว้</p><form id="newUser"><label>ชื่อผู้ใช้</label><input name="username" maxlength="80" required><button class="primary" style="margin-top:14px">เพิ่มผู้ใช้</button><div id="userMsg"></div></form></section><section class="panel"><div class="eyebrow">People</div><h2>รายชื่อผู้ใช้</h2><div class="table-wrap"><table><thead><tr><th>ชื่อ</th><th>สิทธิ์</th><th>Trust</th><th>รายการ</th></tr></thead><tbody>'+result.users.map(x=>'<tr><td>'+esc(x.username)+'</td><td>'+esc(x.role)+'</td><td>'+Number(x.trust_score).toFixed(1)+'</td><td>'+esc(x.transactions)+'</td></tr>').join('')+'</tbody></table></div></section></div>';document.querySelector('#backAdmin').onclick=adminView;document.querySelector('#newUser').onsubmit=async e=>{e.preventDefault();const form=new FormData(e.target);try{const created=await api('admin/users',{method:'POST',body:JSON.stringify({username:form.get('username')})});document.querySelector('#userMsg').innerHTML='<div class="notice">'+esc(created.message)+'</div>';adminUsersPage()}catch(error){document.querySelector('#userMsg').innerHTML='<p class="error">'+esc(error.message)+'</p>'}}}
async function openMyTotal(){const result=await api('cages/my-total');const modal=document.createElement('div');modal.className='modal-backdrop';modal.innerHTML='<section class="modal" role="dialog" aria-modal="true"><div class="modal-head"><div><div class="eyebrow">My submitted cages</div><h2>'+esc(result.username)+'</h2></div><button type="button" class="secondary" id="closeMyTotal">กลับ</button></div><div class="stat-grid"><div class="stat"><b>'+esc(result.submitted_quantity)+'</b><span>เพิ่มสะสม</span></div><div class="stat"><b>'+esc(result.total_quantity)+'</b><span>ยอดสุทธิ</span></div><div class="stat"><b>'+esc(result.items.reduce((sum,item)=>sum+Number(item.transaction_count),0))+'</b><span>รายการ</span></div></div><div class="table-wrap"><table><thead><tr><th>#</th><th>ชนิดกรง</th><th>เพิ่มสะสม</th><th>ลดสะสม</th><th>ยอดสุทธิ</th></tr></thead><tbody>'+result.items.map((item,index)=>'<tr><td>'+((index+1))+'</td><td>'+esc(item.cage_type)+'</td><td>'+esc(item.added_quantity)+'</td><td>'+esc(item.removed_quantity)+'</td><td><strong>'+esc(item.total_quantity)+'</strong></td></tr>').join('')+'</tbody></table></div></section>';document.body.appendChild(modal);const close=()=>modal.remove();modal.querySelector('#closeMyTotal').onclick=close;modal.onclick=event=>{if(event.target===modal)close()}}
async function review(id,action){try{await api('admin/reviews/'+id+'/'+action,{method:'POST'});adminView()}catch(e){alert(e.message)}}
async function openAddCagesModal(){const types=(await api('cages/types')).cages;const options=types.map(x=>'<option value="'+esc(x.cage_type)+'">'+esc(x.cage_type)+'</option>').join('');const modal=document.createElement('div');modal.className='modal-backdrop';modal.innerHTML='<section class="modal" role="dialog" aria-modal="true"><div class="modal-head"><div><div class="eyebrow">Admin transaction</div><h2>เพิ่มกรง</h2></div><button type="button" class="secondary" id="closeAddCages">กลับ Dashboard</button></div><form id="adminTx"><div class="form-row"><div><label>การทำรายการ</label><select name="action"><option value="ADD">เพิ่มกรง</option><option value="REMOVE">ลดกรง</option></select></div><div><label>หมายเหตุ</label><input name="reason"></div></div><label>วางรายการแบบข้อความ</label><textarea name="cage_text" rows="6" placeholder="Enderman 28\nMagma Cube 18\nZombie Piglin 22"></textarea><p class="metric">หนึ่งรายการต่อหนึ่งบรรทัด: ชื่อกรง ตามด้วยจำนวน</p><div id="adminRows"><div class="form-row bulk-row"><div><label>ชนิดกรง</label><select name="cage_type">'+options+'</select></div><div><label>จำนวน</label><input name="quantity" type="number" min="1" max="10000" value="1" required></div></div></div><div class="actions" style="margin-top:14px"><button type="button" class="secondary" id="adminAddRow">+ เพิ่มชนิดกรง</button><button class="primary">ส่งรายการ</button></div><div id="adminTxMsg"></div></form></section>';document.body.appendChild(modal);const close=()=>modal.remove();modal.querySelector('#closeAddCages').onclick=close;modal.onclick=e=>{if(e.target===modal)close()};modal.querySelector('#adminAddRow').onclick=()=>{const row=document.createElement('div');row.className='form-row bulk-row';row.innerHTML='<div><label>ชนิดกรง</label><select name="cage_type">'+options+'</select></div><div><label>จำนวน</label><div class="actions"><input name="quantity" type="number" min="1" max="10000" value="1" required><button type="button" class="danger removeRow">ลบ</button></div></div>';row.querySelector('.removeRow').onclick=()=>row.remove();modal.querySelector('#adminRows').appendChild(row)};modal.querySelector('#adminTx').onsubmit=async e=>{e.preventDefault();const form=new FormData(e.target);const items=form.get('cage_text').trim()?parseCageText(form.get('cage_text')):[...modal.querySelectorAll('.bulk-row')].map(row=>({cage_type:row.querySelector('[name="cage_type"]').value,quantity:Number(row.querySelector('[name="quantity"]').value)}));try{const result=await api('cages/transactions',{method:'POST',body:JSON.stringify({action:form.get('action'),reason:form.get('reason'),items})});modal.querySelector('#adminTxMsg').innerHTML='<div class="notice">'+esc(result.message)+'</div>';setTimeout(close,900)}catch(error){modal.querySelector('#adminTxMsg').innerHTML='<p class="error">'+esc(error.message)+'</p>'}}}
async function adminView(){
    const [o,q]=await Promise.all([api('admin/overview'),api('admin/pending-reviews')]);
    const counts=Object.fromEntries(o.summary.map(x=>[x.status,x.count]));
    state.reviewGroups=Object.fromEntries(q.items.map(group=>[group.group_id,group]));
    const rows=q.items.map(group=>'<tr><td data-label="ผู้ส่ง"><span class="review-user">'+esc(group.username)+'</span><span class="review-trust">Trust '+Number(group.trust_score).toFixed(1)+'</span></td><td data-label="ชุดรายการ"><div class="packet-summary"><div><strong class="packet-amount '+(group.action==='ADD'?'quantity-add':'quantity-remove')+'">'+esc(group.action)+' '+esc(group.total_quantity)+' กรง</strong><span class="packet-meta">'+esc(group.item_count)+' ชนิดในชุดนี้</span></div><button class="secondary details-button" onclick="openReviewBatch(\''+group.group_id+'\')">ดูรายการ</button></div></td><td class="risk-cell" data-label="ความเสี่ยง"><strong>'+esc(group.risk_score)+'</strong> '+status(group.risk_level)+'</td><td class="reason-cell" data-label="เหตุผล" title="'+esc(group.risk_reasons||group.reason||'รอตรวจสอบ')+'">'+esc(group.risk_reasons||group.reason||'รอตรวจสอบ')+'</td><td data-label="จัดการ"><div class="actions review-actions"><button class="secondary" onclick="reviewBatch(\''+group.group_id+'\',\'approve\')">อนุมัติ</button><button class="danger" onclick="reviewBatch(\''+group.group_id+'\',\'reject\')">ปฏิเสธ</button></div></td></tr>').join('');
    document.querySelector('#content').innerHTML='<div class="stat-grid"><div class="stat"><b>'+q.items.length+'</b><span>ชุดที่รอตรวจ</span></div><div class="stat"><b>'+Number(counts.BLOCKED||0)+'</b><span>ความเสี่ยงสูง</span></div><div class="stat"><b>'+Number(counts.FLAGGED||0)+'</b><span>อนุมัติพร้อมธง</span></div></div><section class="panel review-panel"><div class="review-panel-head"><div><div class="eyebrow">Review queue</div><h2>รายการที่รอตรวจสอบ</h2></div><span class="review-count">'+q.items.length+' ชุดคำขอ</span></div>'+(rows?'<div class="table-wrap review-table-wrap"><table class="review-table"><colgroup><col><col><col><col><col></colgroup><thead><tr><th>ผู้ส่ง</th><th>ชุดรายการ</th><th>ความเสี่ยง</th><th>เหตุผล</th><th>จัดการ</th></tr></thead><tbody>'+rows+'</tbody></table></div>':'<div class="empty-state">ไม่มีรายการที่รอตรวจสอบในขณะนี้</div>')+'</section><section class="panel" style="margin-top:22px"><div class="eyebrow">Trust watchlist</div><h2>ผู้ใช้ที่ควรติดตาม</h2><div class="table-wrap"><table><thead><tr><th>ผู้ใช้</th><th>Trust</th><th>รายการทั้งหมด</th></tr></thead><tbody>'+o.suspicious_users.map(x=>'<tr><td>'+esc(x.username)+'</td><td>'+Number(x.trust_score).toFixed(1)+'</td><td>'+esc(x.transactions)+'</td></tr>').join('')+'</tbody></table></div></section>';
}
function openReviewBatch(groupId){const group=state.reviewGroups?.[groupId];if(!group)return;const modal=document.createElement('div');modal.className='modal-backdrop';modal.innerHTML='<section class="modal batch-modal" role="dialog" aria-modal="true" aria-labelledby="batchTitle"><div class="modal-head"><div><div class="eyebrow">รายละเอียดชุดคำขอ</div><h2 id="batchTitle">'+esc(group.username)+' · '+esc(group.action)+'</h2></div><button type="button" class="secondary" id="closeBatch">ปิด</button></div><div class="inventory-total"><strong>'+esc(group.total_quantity)+'</strong><span>กรงรวมทั้งหมด<br>'+esc(group.item_count)+' ชนิดในชุดนี้</span></div><div class="table-wrap"><table><thead><tr><th>#</th><th>ชนิดกรง</th><th>จำนวน</th></tr></thead><tbody>'+group.items.map((item,index)=>'<tr><td>'+Number(index+1)+'</td><td>'+esc(item.cage_type)+'</td><td><strong class="'+(item.action==='ADD'?'quantity-add':'quantity-remove')+'">'+esc(item.quantity)+'</strong></td></tr>').join('')+'</tbody></table></div><div class="actions modal-actions" style="margin-top:16px"><button class="secondary" id="approveBatch">อนุมัติทั้งชุด</button><button class="danger" id="rejectBatch">ปฏิเสธทั้งชุด</button></div></section>';document.body.appendChild(modal);const close=()=>modal.remove();modal.querySelector('#closeBatch').onclick=close;modal.onclick=event=>{if(event.target===modal)close()};modal.querySelector('#approveBatch').onclick=()=>{close();reviewBatch(groupId,'approve')};modal.querySelector('#rejectBatch').onclick=()=>{close();reviewBatch(groupId,'reject')}}
async function reviewBatch(groupId,action){try{await api('admin/review-batches/'+groupId+'/'+action,{method:'POST'});adminView()}catch(error){alert(error.message)}}
async function openAdminUserInventories(){const opener=document.activeElement;const modal=document.createElement('div');modal.className='modal-backdrop';modal.innerHTML='<section class="modal user-inventory-modal" role="dialog" aria-modal="true" aria-labelledby="userInventoryTitle"><div class="modal-head"><div><div class="eyebrow">ADMIN VIEW</div><h2 id="userInventoryTitle">กรงของแต่ละคนที่ส่งมา</h2></div><button type="button" class="secondary" data-close-user-inventories>ปิด</button></div><div class="user-inventory-view"><div class="user-inventory-loading" role="status">กำลังโหลดรายชื่อผู้ใช้</div></div></section>';document.body.appendChild(modal);const view=modal.querySelector('.user-inventory-view');const title=modal.querySelector('#userInventoryTitle');const close=()=>{modal.remove();opener?.focus?.()};const showList=users=>{title.textContent='กรงของแต่ละคนที่ส่งมา';const rows=users.map((user,index)=>'<section class="user-inventory-card"><h3 class="user-inventory-name" title="'+esc(user.username)+'">'+esc(user.username)+'</h3><span class="user-inventory-total">รวม '+esc(user.total_quantity)+' กรง</span><button type="button" class="secondary user-inventory-open" data-user-index="'+index+'">ดูรายการ</button></section>').join('');view.innerHTML=rows?'<div class="user-inventory-list">'+rows+'</div>':'<div class="user-inventory-empty">ยังไม่มีผู้ใช้ในระบบ</div>';view.querySelectorAll('[data-user-index]').forEach(button=>button.onclick=()=>showDetail(users[Number(button.dataset.userIndex)]))};const showDetail=user=>{title.textContent=user.username;const items=Array.isArray(user.items)?user.items:[];const rows=items.map(item=>'<tr><td>'+esc(item.cage_type)+'</td><td><strong>'+esc(item.quantity)+'</strong></td></tr>').join('');view.innerHTML='<div class="user-inventory-detail-summary"><span>ยอดกรงที่ผ่านการอนุมัติ</span><strong>'+esc(user.total_quantity)+' กรง</strong></div>'+(rows?'<div class="table-wrap user-inventory-table-wrap"><table class="user-inventory-table"><thead><tr><th>ชนิดกรง</th><th>จำนวน</th></tr></thead><tbody>'+rows+'</tbody></table></div>':'<div class="user-inventory-empty">ผู้ใช้นี้ยังไม่มีกรงที่ผ่านการอนุมัติ</div>')+'<div class="actions" style="margin-top:16px"><button type="button" class="secondary" data-back-user-list>← กลับไปรายชื่อผู้ใช้</button></div>';view.querySelector('[data-back-user-list]').onclick=()=>showList(result.users)};modal.querySelector('[data-close-user-inventories]').onclick=close;modal.onclick=event=>{if(event.target===modal)close()};modal.onkeydown=event=>{if(event.key==='Escape')close()};modal.querySelector('[data-close-user-inventories]').focus();let result;try{result=await api('admin/user-inventories');result.users=Array.isArray(result.users)?result.users:[];showList(result.users)}catch(error){view.innerHTML='<div class="user-inventory-empty"><strong>โหลดข้อมูลไม่สำเร็จ</strong><br>'+esc(error.message)+'</div>'}}
function decorateAccount(){const account=document.querySelector('#account');if(!account||account.dataset.decorated)return;const pill=account.querySelector('.pill');if(!pill)return;const name=(state.user&&state.user.username)||pill.textContent.split('·')[0].trim();account.dataset.decorated='1';pill.className='account-user';pill.textContent=name;if(pageRole==='ADMIN')document.querySelector('.hero')?.remove();account.querySelectorAll('button').forEach(button=>button.classList.add('logout-button'));const myTotal=account.querySelector('#myTotal');if(myTotal)myTotal.textContent='กรงของแต่ละคนที่ส่งมา';if(pageRole==='ADMIN'&&state.user?.is_primary_admin&&!account.querySelector('#allUserInventories')){const button=document.createElement('button');button.id='allUserInventories';button.className='secondary';button.textContent='กรงของแต่ละคน';button.onclick=openAdminUserInventories;const logout=account.querySelector('#logout');account.insertBefore(button,logout)}}
function adjustHistoryTime(){document.querySelectorAll('#content table').forEach(table=>{if(table.dataset.timeAdjusted)return;const headers=[...table.querySelectorAll('thead th')];if(!headers.some(header=>header.textContent.trim()==='ผู้ทำรายการ'))return;table.dataset.timeAdjusted='1';headers[0].textContent='เวลา';headers[0].style.width='28%'})}
new MutationObserver(adjustHistoryTime).observe(document.body,{childList:true,subtree:true});
new MutationObserver(()=>{decorateNumericTables();const account=document.querySelector('#account');if(account&&account.dataset.decorated!=='1')decorateAccount()}).observe(document.body,{childList:true,subtree:true});

const pageRole=<?= json_encode(PAGE_ROLE, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

const renderPrimaryAdminView=adminView;
adminView=async function(){
  if(state.user?.is_primary_admin){
    try{return await renderPrimaryAdminView()}
    catch(error){
      document.querySelector('#content').innerHTML='<section class="panel"><div class="eyebrow">DASHBOARD ERROR</div><h2>โหลดข้อมูล Dashboard ไม่สำเร็จ</h2><p class="error">'+esc(error.message)+'</p><button type="button" class="secondary" id="retryDashboard">ลองใหม่</button></section>';
      document.querySelector('#retryDashboard').onclick=adminView;
      return;
    }
  }
  const q=await api('admin/pending-reviews');
  state.reviewGroups=Object.fromEntries(q.items.map(group=>[group.group_id,group]));
  const rows=q.items.map(group=>'<tr><td data-label="ผู้ส่ง"><span class="review-user">'+esc(group.username)+'</span></td><td data-label="รายการ"><div class="packet-summary"><div><strong class="packet-amount '+(group.action==='ADD'?'quantity-add':'quantity-remove')+'">'+esc(group.action==='ADD'?'เพิ่ม':'ลบ')+' '+esc(group.total_quantity)+' กรง</strong><span class="packet-meta">'+esc(group.item_count)+' ชนิดในชุดนี้</span></div><button class="secondary details-button" onclick="openReviewBatch(\''+group.group_id+'\')">ดูรายการ</button></div></td><td data-label="จัดการ"><div class="actions review-actions"><button class="secondary" onclick="reviewBatch(\''+group.group_id+'\',\'approve\')">อนุมัติ</button><button class="danger" onclick="reviewBatch(\''+group.group_id+'\',\'reject\')">ปฏิเสธ</button></div></td></tr>').join('');
  document.querySelector('#content').innerHTML='<section class="panel review-panel"><div class="review-panel-head"><div><div class="eyebrow">APPROVAL DASHBOARD</div><h2>อนุมัติรายการเพิ่มหรือลบกรง</h2></div><span class="review-count">'+q.items.length+' ชุดคำขอ</span></div>'+(rows?'<div class="table-wrap review-table-wrap"><table class="review-table"><thead><tr><th>ชื่อผู้ส่ง</th><th>รายการ</th><th>จัดการ</th></tr></thead><tbody>'+rows+'</tbody></table></div>':'<div class="empty-state">ไม่มีรายการที่รออนุมัติในขณะนี้</div>')+'</section>';
};

const renderPrimaryAdminUsersPage=adminUsersPage;
adminUsersPage=async function(){
  if(state.user?.is_primary_admin)return renderPrimaryAdminUsersPage();
  const result=await api('admin/users');
  const rows=result.users.map(user=>'<tr><td><strong>'+esc(user.username)+'</strong></td></tr>').join('');
  document.querySelector('#content').innerHTML='<button class="secondary" id="backAdmin">กลับ Dashboard</button><div class="grid" style="margin-top:18px"><section class="panel"><div class="eyebrow">USER MANAGEMENT</div><h2>เพิ่มคน</h2><p class="metric">ผู้ใช้ใหม่เข้าสู่ระบบด้วยชื่อที่กำหนดไว้ โดยไม่ต้องใช้รหัสผ่าน</p><form id="newUser"><label>ชื่อผู้ใช้</label><input name="username" maxlength="80" required><button class="primary" style="margin-top:14px">เพิ่มผู้ใช้</button><div id="userMsg"></div></form></section><section class="panel"><div class="eyebrow">PEOPLE</div><h2>รายชื่อผู้ใช้</h2><div class="table-wrap"><table><thead><tr><th>ชื่อผู้ใช้</th></tr></thead><tbody>'+rows+'</tbody></table></div></section></div>';
  document.querySelector('#backAdmin').onclick=adminView;
  document.querySelector('#newUser').onsubmit=async event=>{event.preventDefault();const form=new FormData(event.target);try{const created=await api('admin/users',{method:'POST',body:JSON.stringify({username:form.get('username')})});document.querySelector('#userMsg').innerHTML='<div class="notice">'+esc(created.message)+'</div>';adminUsersPage()}catch(error){document.querySelector('#userMsg').innerHTML='<p class="error">'+esc(error.message)+'</p>'}};
};

renderApp=async function(){
  const adminPage=pageRole==='ADMIN';
  layout('<section class="hero"><div class="eyebrow">'+(adminPage?'Operations console':'Shared inventory')+'</div><h1>'+(adminPage?'ดูเฉพาะสิ่งที่ผิดปกติ':'จำนวนกรงที่ทุกคนส่ง อยู่ในที่เดียว')+'</h1><p>'+(adminPage?'รายการปกติจะผ่านอัตโนมัติ หน้านี้จะแสดงเฉพาะรายการที่ต้องใช้ดุลยพินิจ':'ยอดกรงหน้านี้รวมรายการที่ผ่านการอนุมัติของผู้ใช้ทุกคน')+'</p></section><div id="content"></div>');
  document.querySelector('#account').innerHTML='<span class="pill">'+esc(state.user.username)+'</span> '+(adminPage?(state.user.is_primary_admin?'<button id="dashboardHome" class="secondary">Dashboard</button><button id="addCages" class="secondary">หน้าเพิ่มกรง</button>':'')+'<button id="addUsers" class="secondary">เพิ่มคน</button>'+(state.user.uses_user_view?'<button id="openUserPage" class="secondary">หน้า User</button>':''):'<button id="myTotal" class="secondary">ยอดกรงที่ทุกคนส่ง</button>'+(state.user.role==='ADMIN'?'<button id="openAdminPage" class="secondary">Dashboard</button>':''))+' <button id="logout" class="secondary">ออกจากระบบ</button>';
  document.querySelector('#logout').onclick=async()=>{await api('auth/logout',{method:'POST'});state.user=null;location.replace('index.php')};
  if(adminPage){
    if(document.querySelector('#dashboardHome'))document.querySelector('#dashboardHome').onclick=adminView;
    if(document.querySelector('#addCages'))document.querySelector('#addCages').onclick=adminAddPage;
    document.querySelector('#addUsers').onclick=adminUsersPage;
    if(document.querySelector('#openUserPage'))document.querySelector('#openUserPage').onclick=()=>location.assign('user.php');
    adminView();
  }else{
    document.querySelector('#myTotal').onclick=openMyTotal;
    if(document.querySelector('#openAdminPage'))document.querySelector('#openAdminPage').onclick=()=>location.assign('admin.php');
    userView();
  }
};

function renderUserLogin(){
  layout('<section class="panel login"><div class="eyebrow">USER ACCESS</div><h1>เข้าสู่ระบบผู้ใช้</h1><form id="login"><label>ชื่อผู้ใช้</label><input name="username" maxlength="80" required autofocus autocomplete="username"><button class="primary" style="margin-top:18px;width:100%">เข้าสู่ระบบ</button><div id="msg"></div></form></section>');
  bindLoginRedirect('USER');
}

function renderAdminLogin(){
  layout('<section class="panel login"><div class="eyebrow">ADMIN ACCESS</div><h1>เข้าสู่ระบบ ADMIN</h1><p class="metric">หน้านี้สำหรับผู้ดูแลระบบเท่านั้น</p><form id="login"><label>ID ผู้ดูแลระบบ</label><input name="username" maxlength="80" required autofocus autocomplete="username"><label>รหัสผ่าน</label><input name="password" type="password" required autocomplete="current-password"><button class="primary" style="margin-top:18px;width:100%">เข้าสู่ระบบ ADMIN</button><div id="msg"></div></form><div class="actions" style="justify-content:center;margin-top:18px"><a class="secondary" style="text-decoration:none" href="index.php">กลับหน้า User</a></div></section>');
  bindLoginRedirect('ADMIN');
}

function bindLoginRedirect(expectedRole){
  const form=document.querySelector('#login');
  if(!form)return;
  form.onsubmit=async event=>{
    event.preventDefault();
    const data=new FormData(form);
    try{
      const result=await api('auth/login',{method:'POST',body:JSON.stringify(Object.fromEntries(data))});
      state.user=result.user;
      state.csrfToken=result.csrf_token;
      if(result.user.role!==expectedRole){
        await api('auth/logout',{method:'POST'});
        state.user=null;
        throw new Error(expectedRole==='ADMIN'?'บัญชีนี้ไม่ใช่ ADMIN':'บัญชี ADMIN กรุณาเข้าสู่ระบบที่หน้า ADMIN');
      }
      location.replace(result.user.role==='ADMIN'?'admin.php':'user.php');
    }catch(error){
      document.querySelector('#msg').innerHTML='<p class="error">'+esc(error.message)+'</p>';
    }
  };
}

async function routeStart(){
  document.body.dataset.pageRole=pageRole;
  try{
    const result=await api('auth/me');
    state.user=result.user;
    state.csrfToken=result.csrf_token;
  }catch(error){
    state.user=null;
  }

  if(pageRole==='LOGIN'){
    renderUserLogin();
    return;
  }

  if(!state.user){
    if(pageRole==='ADMIN')renderAdminLogin();
    else location.replace('index.php');
    return;
  }

  const pageAllowed=pageRole==='ADMIN' ? state.user.role==='ADMIN' : state.user.uses_user_view;
  if(!pageAllowed){
    location.replace(state.user.role==='ADMIN'?'admin.php':'user.php');
    return;
  }

  renderApp();
}

document.addEventListener('click',async event=>{
  const logout=event.target.closest('#logout');
  if(!logout)return;
  event.preventDefault();
  event.stopImmediatePropagation();
  logout.disabled=true;
  try{await api('auth/logout',{method:'POST'})}finally{location.replace('index.php')}
},true);

routeStart();
</script></body></html>
