{{-- Shared look for the PMS screens, built on the app's own srd-theme tokens. --}}
<style>
.pms-tabs{ display:flex; flex-wrap:wrap; gap:6px; margin:0 0 14px; }
.pms-tab{
	display:inline-flex; align-items:center; gap:7px; padding:8px 14px;
	border:1px solid var(--srd-border); border-radius:999px; background:var(--srd-card);
	color:var(--srd-ink-soft); font-size:13px; font-weight:500; text-decoration:none;
}
.pms-tab i{ color:var(--srd-muted); font-size:12px; }
.pms-tab:hover{ background:var(--srd-accent-tint); color:var(--srd-accent-dark); text-decoration:none; }
.pms-tab.active{ background:var(--srd-accent); border-color:var(--srd-accent); color:#fff; }
.pms-tab.active i{ color:#fff; }
.pms-note{ font-size:13px; color:var(--srd-muted); margin:0 0 14px; max-width:75ch; }
.pms-muted{ color:var(--srd-muted); }
.pms-num{ font-variant-numeric:tabular-nums; text-align:right; }
.pms-flag{
	display:inline-flex; align-items:center; gap:4px; padding:2px 8px; border-radius:999px;
	font-size:11px; font-weight:600; letter-spacing:.02em; line-height:1.6; white-space:nowrap;
}
.pms-flag-yes{ background:var(--srd-success-tint); color:var(--srd-success); }
.pms-flag-critical{ background:var(--srd-danger-tint); color:var(--srd-danger); }
.pms-flag-class{ background:var(--srd-accent-tint); color:var(--srd-accent-dark); }
.pms-flag-counter{ background:var(--srd-warning-tint); color:var(--srd-warning); }
.pms-flag-inactive{ background:var(--srd-surface); color:var(--srd-muted); border:1px solid var(--srd-border); }

/* ---- Component tree ---- */
.pms-toolbar{ display:flex; flex-wrap:wrap; align-items:center; gap:10px; margin-bottom:16px; }
.pms-toolbar .form-control{ width:auto; min-width:220px; }
.pms-toolbar .pms-search{ flex:1 1 240px; max-width:360px; }
.pms-toolbar .spacer{ flex:1 1 auto; }
.pms-location{ border:1px solid var(--srd-border); border-radius:var(--srd-radius); margin-bottom:14px; background:var(--srd-card); overflow:hidden; }
.pms-location-head{
	display:flex; align-items:center; gap:10px; padding:11px 16px;
	background:var(--srd-surface); border-bottom:1px solid var(--srd-border); cursor:pointer; user-select:none;
}
.pms-location-head h3{ margin:0; font-size:13.5px; font-weight:700; letter-spacing:.06em; text-transform:uppercase; color:var(--srd-ink); }
.pms-location-head .pms-loc-count{ font-size:12px; color:var(--srd-muted); }
.pms-location-head .caret{ color:var(--srd-muted); transition:transform .15s; font-size:12px; }
.pms-location.collapsed .pms-location-head .caret{ transform:rotate(-90deg); }
.pms-location.collapsed .pms-location-body{ display:none; }
.pms-location-empty{ padding:14px 16px; font-size:13px; color:var(--srd-muted); }
.pms-tree, .pms-tree ul{ list-style:none; margin:0; padding:0; }
.pms-tree ul{ border-left:1px dashed var(--srd-border); margin-left:22px; }
.pms-node-row{
	display:flex; align-items:center; gap:10px; padding:8px 14px 8px 10px;
	border-bottom:1px solid var(--srd-border-soft);
}
.pms-node-row:hover{ background:#fafcfc; }
.pms-node.inactive > .pms-node-row .pms-node-name{ color:var(--srd-muted); text-decoration:line-through; }
.pms-node-toggle{
	width:22px; height:22px; flex:0 0 22px; display:inline-flex; align-items:center; justify-content:center;
	border:0; background:transparent; color:var(--srd-muted); border-radius:5px; padding:0; cursor:pointer;
}
.pms-node-toggle:hover{ background:var(--srd-accent-tint); color:var(--srd-accent-dark); }
.pms-node-toggle i{ transition:transform .15s; font-size:11px; }
.pms-node.collapsed > .pms-node-row .pms-node-toggle i{ transform:rotate(-90deg); }
.pms-node.collapsed > ul{ display:none; }
.pms-node-toggle.leaf{ visibility:hidden; }
.pms-node-main{ flex:1 1 auto; min-width:0; }
.pms-node-name{ font-weight:600; color:var(--srd-ink); font-size:13.5px; }
.pms-node-meta{ font-size:12px; color:var(--srd-muted); margin-top:1px; overflow-wrap:anywhere; }
.pms-node-meta span + span::before{ content:"·"; margin:0 6px; color:var(--srd-border); }
.pms-node-flags{ display:flex; flex-wrap:wrap; gap:4px; justify-content:flex-end; }
.pms-node-rh{ font-size:12px; color:var(--srd-ink-soft); white-space:nowrap; font-variant-numeric:tabular-nums; }
.pms-node-rh a{ color:var(--srd-accent-dark); font-weight:600; }
.pms-node-actions{ display:flex; gap:4px; flex:0 0 auto; }
.pms-node-actions .btn{ padding:3px 8px; font-size:12px; }
.pms-cond{ font-size:11.5px; font-weight:600; padding:2px 8px; border-radius:999px; background:var(--srd-surface); color:var(--srd-ink-soft); white-space:nowrap; }
.pms-cond-good{ background:var(--srd-success-tint); color:var(--srd-success); }
.pms-cond-fair{ background:var(--srd-warning-tint); color:var(--srd-warning); }
.pms-cond-bad{ background:var(--srd-danger-tint); color:var(--srd-danger); }
.pms-hidden{ display:none !important; }
.pms-form-grid{ display:grid; grid-template-columns:repeat(2, minmax(0,1fr)); gap:0 16px; }
.pms-form-grid .full{ grid-column:1 / -1; }
.pms-checks{ display:flex; flex-wrap:wrap; gap:6px 22px; padding:4px 0 6px; }
@media (max-width:767px){
	.pms-form-grid{ grid-template-columns:1fr; }
	.pms-node-row{ flex-wrap:wrap; }
	.pms-node-flags{ justify-content:flex-start; }
}
/* ---- Job definition modal ---- */
.pms-section{ font-size:11.5px; font-weight:700; letter-spacing:.08em; text-transform:uppercase; color:var(--srd-muted); margin:18px 0 10px; padding-bottom:6px; border-bottom:1px solid var(--srd-border-soft); }
.modal-body > .pms-section:first-of-type{ margin-top:4px; }
.pms-seg{ display:inline-flex; border:1px solid var(--srd-border); border-radius:8px; overflow:hidden; }
.pms-seg input{ position:absolute; opacity:0; pointer-events:none; }
.pms-seg label{ margin:0; padding:6px 16px; font-size:13px; cursor:pointer; color:var(--srd-ink-soft); background:var(--srd-card); }
.pms-seg label + input + label{ border-left:1px solid var(--srd-border); }
.pms-seg input:checked + label{ background:var(--srd-accent); color:#fff; }
.pms-seg input:focus-visible + label{ outline:2px solid var(--srd-accent-dark); outline-offset:-2px; }
.jd-interval{ display:flex; align-items:center; gap:8px; margin-bottom:8px; max-width:440px; }
.jd-interval input{ width:110px; flex:0 0 110px; }
.jd-interval select{ flex:1 1 auto; }
.jd-checklist{ padding-left:22px; margin:0 0 8px; }
.jd-check{ margin-bottom:6px; }
.jd-check > *{ vertical-align:middle; }
.jd-check input{ display:inline-block; width:calc(100% - 46px); }

/* ---- Job status ---- */
.pms-status{ display:inline-block; padding:2px 9px; border-radius:999px; font-size:11.5px; font-weight:600; white-space:nowrap; background:var(--srd-surface); color:var(--srd-ink-soft); }
.pms-status-Planned{ background:var(--srd-accent-tint); color:var(--srd-accent-dark); }
.pms-status-DueSoon{ background:var(--srd-warning-tint); color:var(--srd-warning); }
.pms-status-Overdue{ background:var(--srd-danger-tint); color:var(--srd-danger); }
.pms-status-WaitingApproval{ background:#efe9fb; color:#5b3fa6; }
.pms-status-Completed{ background:var(--srd-success-tint); color:var(--srd-success); }
.pms-status-Cancelled{ background:var(--srd-surface); color:var(--srd-muted); text-decoration:line-through; }
.pms-overdue-date{ color:var(--srd-danger); font-weight:700; }

/* ---- Job count / Add Job in the component tree ---- */
.pms-node-jobs{ font-size:12px; white-space:nowrap; color:var(--srd-ink-soft); }
.pms-node-jobs a{ color:var(--srd-accent-dark); font-weight:600; }
.pms-node-jobs .none{ color:var(--srd-muted); }

/* ---- Jobs page ---- */
.pms-jobs-layout{ display:grid; grid-template-columns:300px minmax(0,1fr); gap:16px; align-items:start; }
.pms-jobs-tree{ border:1px solid var(--srd-border); border-radius:var(--srd-radius); background:var(--srd-card); max-height:calc(100vh - 220px); overflow:auto; position:sticky; top:12px; }
.pms-jt-all, .pms-jt-label{ display:flex; align-items:center; gap:6px; width:100%; border:0; background:none; text-align:left; padding:5px 8px; border-radius:5px; font-size:13px; color:var(--srd-ink); }
.pms-jt-all{ font-weight:700; padding:9px 12px; border-bottom:1px solid var(--srd-border-soft); border-radius:0; }
.pms-jt-label:hover, .pms-jt-all:hover, .pms-jt-label:focus, .pms-jt-all:focus{ background:var(--srd-accent-tint); outline:none; }
.pms-jt-label.is-selected, .pms-jt-all.is-selected{ background:var(--srd-accent); color:#fff; }
.pms-jt-label.is-selected .pms-jt-count, .pms-jt-all.is-selected .pms-jt-count{ color:#fff; background:rgba(255,255,255,.2); }
.pms-jt-name{ flex:1 1 auto; min-width:0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.pms-jt-count{ font-size:11px; font-weight:600; font-variant-numeric:tabular-nums; color:var(--srd-muted); background:var(--srd-surface); border-radius:999px; padding:0 7px; }
.pms-jt-count.zero{ visibility:hidden; }
.pms-jt-loc{ font-size:11px; font-weight:700; letter-spacing:.07em; text-transform:uppercase; color:var(--srd-muted); padding:10px 12px 4px; }
.pms-jt, .pms-jt ul{ list-style:none; margin:0; padding:0 6px; }
.pms-jt ul{ padding-left:14px; }
.pms-jt-row{ display:flex; align-items:center; }
.pms-jt-caret{ width:18px; flex:0 0 18px; border:0; background:none; padding:0; color:var(--srd-muted); font-size:10px; }
.pms-jt-caret.leaf{ visibility:hidden; }
.pms-jt li.collapsed > ul{ display:none; }
.pms-jt li.collapsed > .pms-jt-row .pms-jt-caret i{ transform:rotate(-90deg); }
.pms-filters{ display:flex; flex-wrap:wrap; gap:6px; }
.pms-filter{ border:1px solid var(--srd-border); background:var(--srd-card); border-radius:999px; padding:5px 12px; font-size:12.5px; color:var(--srd-ink-soft); display:inline-flex; gap:6px; align-items:center; }
.pms-filter b{ font-variant-numeric:tabular-nums; }
.pms-filter.active{ background:var(--srd-accent); border-color:var(--srd-accent); color:#fff; }
.pms-filter[data-filter="overdue"]:not(.active) b{ color:var(--srd-danger); }
.pms-filter[data-filter="duesoon"]:not(.active) b{ color:var(--srd-warning); }
#pms-jobs-table tbody tr{ cursor:pointer; }
#pms-jobs-table td{ vertical-align:middle; }
.pms-job-sub{ display:block; font-size:11.5px; color:var(--srd-muted); }

/* ---- Job dialog ---- */
.pms-jd-dl{ display:grid; grid-template-columns:repeat(3, minmax(0,1fr)); gap:10px 18px; margin:0 0 14px; }
.pms-jd-dl dt{ font-size:11px; font-weight:600; letter-spacing:.05em; text-transform:uppercase; color:var(--srd-muted); margin:0; }
.pms-jd-dl dd{ margin:2px 0 0; font-size:13.5px; color:var(--srd-ink); font-variant-numeric:tabular-nums; overflow-wrap:anywhere; }
.pms-jd-panel{ border:1px solid var(--srd-border); border-radius:var(--srd-radius); padding:14px 16px; margin-top:12px; background:var(--srd-surface); }
.pms-jd-checks li{ margin-bottom:4px; }
.pms-history-item{ border-left:3px solid var(--srd-border); padding:6px 0 6px 12px; margin-bottom:10px; font-size:13px; }
.pms-history-item.Completed{ border-left-color:var(--srd-success); }
.pms-history-item.WaitingApproval{ border-left-color:#5b3fa6; }
@media (max-width:991px){
	.pms-jobs-layout{ grid-template-columns:1fr; }
	.pms-jobs-tree{ position:static; max-height:280px; }
	.pms-jd-dl{ grid-template-columns:repeat(2, minmax(0,1fr)); }
}
/* ---- Required parts ---- */
.pp-search-wrap{ position:relative; }
.pp-results{ position:absolute; z-index:5; left:0; right:0; top:68px; max-height:260px; overflow:auto; background:var(--srd-card); border:1px solid var(--srd-border); border-radius:6px; box-shadow:0 8px 24px rgba(0,0,0,.12); }
.pp-result{ display:block; width:100%; text-align:left; border:0; background:none; padding:7px 12px; font-size:13px; border-bottom:1px solid var(--srd-border-soft); }
.pp-result:hover, .pp-result:focus{ background:var(--srd-accent-tint); outline:none; }
.pp-none{ padding:8px 12px; font-size:13px; }
.pp-table td, .pp-table th{ vertical-align:middle; font-size:13px; }
.pp-sub{ display:block; font-size:11.5px; color:var(--srd-muted); }
.pp-req{ white-space:nowrap; }
.pp-used-row{ display:flex; align-items:center; gap:8px; margin-bottom:6px; }
.pp-used-row .pp-used-name{ flex:1 1 auto; min-width:0; font-size:13px; }
.pp-used-row input{ width:90px; flex:0 0 90px; }
.pp-group{ border:1px solid var(--srd-border); border-radius:var(--srd-radius); margin-bottom:14px; overflow:hidden; background:var(--srd-card); }
.pp-group-head{ display:flex; flex-wrap:wrap; align-items:center; gap:10px; padding:10px 14px; background:var(--srd-surface); border-bottom:1px solid var(--srd-border); }
.pp-group-head h3{ margin:0; font-size:14px; font-weight:700; }
/* ---- Component import ---- */
.pms-import-grid{ display:grid; grid-template-columns:minmax(0,1fr) minmax(0,1.2fr); gap:24px; align-items:start; }
.pms-import-form{ border:1px solid var(--srd-border); border-radius:var(--srd-radius); padding:18px; background:var(--srd-surface); }
.pms-import-help ul{ padding-left:18px; font-size:13px; color:var(--srd-ink-soft); }
.pms-import-help li{ margin-bottom:6px; }
.pms-import-counts{ display:flex; flex-wrap:wrap; gap:12px; margin:0 0 16px; }
.pms-import-count{ flex:1 1 160px; border:1px solid var(--srd-border); border-radius:var(--srd-radius); padding:12px 16px; background:var(--srd-card); }
.pms-import-count b{ display:block; font-size:26px; font-variant-numeric:tabular-nums; line-height:1.1; }
.pms-import-count span{ font-size:13px; color:var(--srd-ink-soft); }
.pms-import-count small{ display:block; font-size:12px; color:var(--srd-muted); }
.pms-import-count.create b{ color:var(--srd-success); }
.pms-import-count.error b{ color:var(--srd-danger); }
@media (max-width:991px){ .pms-import-grid{ grid-template-columns:1fr; } }
</style>
