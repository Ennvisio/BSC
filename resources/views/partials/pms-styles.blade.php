{{-- Shared look for the PMS screens, built on the --od-* tokens layouts.pms
	 defines on .pms-shell (see partials.pms-shell-styles) - every PMS page is
	 rendered inside that shell, so these custom properties always resolve. --}}
<style>
.pms-tabs{ display:flex; flex-wrap:wrap; gap:6px; margin:0 0 14px; }
.pms-tab{
	display:inline-flex; align-items:center; gap:7px; padding:8px 14px;
	border:1px solid var(--od-line); border-radius:999px; background:var(--od-surface);
	font-size:13px; font-weight:500; text-decoration:none;
}
{{-- `.srd a` (specificity 0,1,1) otherwise outranks a plain single class on
	 the same <a> (0,1,0) - every link-colour rule here is written as
	 a.classname to match it, and wins the tie on source order. --}}
a.pms-tab{ color:var(--od-ink-soft); }
.pms-tab i{ color:var(--od-muted); font-size:12px; }
a.pms-tab:hover{ color:var(--od-accent-dark); }
.pms-tab:hover{ background:var(--od-accent-tint); text-decoration:none; }
a.pms-tab.active{ color:#fff; }
.pms-tab.active{ background:var(--od-accent); border-color:var(--od-accent); }
.pms-tab.active i{ color:#fff; }
.pms-note{ font-size:13px; color:var(--od-muted); margin:0 0 14px; max-width:75ch; }
.pms-muted{ color:var(--od-muted); }
.pms-num{ font-variant-numeric:tabular-nums; text-align:right; }
.pms-flag{
	display:inline-flex; align-items:center; gap:4px; padding:2px 8px; border-radius:999px;
	font-size:11px; font-weight:600; letter-spacing:.02em; line-height:1.6; white-space:nowrap;
}
.pms-flag-yes{ background:var(--od-accent-tint); color:var(--od-accent-dark); }
.pms-flag-critical{ background:var(--od-danger-tint); color:var(--od-danger); }
.pms-flag-class{ background:var(--od-accent-tint); color:var(--od-accent-dark); }
.pms-flag-counter{ background:var(--od-warn-tint); color:var(--od-warn-dark); }
.pms-flag-inactive{ background:var(--od-ground); color:var(--od-muted); border:1px solid var(--od-line); }

/* ---- Component tree ---- */
.pms-toolbar{ display:flex; flex-wrap:wrap; align-items:center; gap:10px; margin-bottom:16px; }
.pms-toolbar .form-control{ width:auto; min-width:220px; }
.pms-toolbar .pms-search{ flex:1 1 240px; max-width:360px; }
.pms-toolbar .spacer{ flex:1 1 auto; }
.pms-location{ border:1px solid var(--od-line); border-radius:var(--od-radius); margin-bottom:14px; background:var(--od-surface); overflow:hidden; }
.pms-location-head{
	display:flex; align-items:center; gap:10px; padding:11px 16px;
	background:var(--od-ground); border-bottom:1px solid var(--od-line); cursor:pointer; user-select:none;
}
.pms-location-head h3{ margin:0; font-size:13.5px; font-weight:700; letter-spacing:.06em; text-transform:uppercase; color:var(--od-ink); }
.pms-location-head .pms-loc-count{ font-size:12px; color:var(--od-muted); }
.pms-location-head .caret{ color:var(--od-muted); transition:transform .15s; font-size:12px; }
.pms-location.collapsed .pms-location-head .caret{ transform:rotate(-90deg); }
.pms-location.collapsed .pms-location-body{ display:none; }
.pms-location-empty{ padding:14px 16px; font-size:13px; color:var(--od-muted); }
.pms-tree, .pms-tree ul{ list-style:none; margin:0; padding:0; }
.pms-tree ul{ border-left:1px dashed var(--od-line); margin-left:22px; }
.pms-node-row{
	display:flex; align-items:center; gap:10px; padding:8px 14px 8px 10px;
	border-bottom:1px solid var(--od-line-soft);
}
.pms-node-row:hover{ background:#fafcfc; }
.pms-node.inactive > .pms-node-row .pms-node-name{ color:var(--od-muted); text-decoration:line-through; }
.pms-node-toggle{
	width:22px; height:22px; flex:0 0 22px; display:inline-flex; align-items:center; justify-content:center;
	border:0; background:transparent; color:var(--od-muted); border-radius:5px; padding:0; cursor:pointer;
}
.pms-node-toggle:hover{ background:var(--od-accent-tint); color:var(--od-accent-dark); }
.pms-node-toggle i{ transition:transform .15s; font-size:11px; }
.pms-node.collapsed > .pms-node-row .pms-node-toggle i{ transform:rotate(-90deg); }
.pms-node.collapsed > ul{ display:none; }
.pms-node-toggle.leaf{ visibility:hidden; }
.pms-node-main{ flex:1 1 auto; min-width:0; }
.pms-node-name{ font-weight:600; color:var(--od-ink); font-size:13.5px; }
.pms-node-meta{ font-size:12px; color:var(--od-muted); margin-top:1px; overflow-wrap:anywhere; }
.pms-node-meta span + span::before{ content:"·"; margin:0 6px; color:var(--od-line); }
.pms-node-flags{ display:flex; flex-wrap:wrap; gap:4px; justify-content:flex-end; }
.pms-node-rh{ font-size:12px; color:var(--od-ink-soft); white-space:nowrap; font-variant-numeric:tabular-nums; }
.pms-node-rh a{ color:var(--od-accent-dark); font-weight:600; }
.pms-node-actions{ display:flex; gap:4px; flex:0 0 auto; }
.pms-node-actions .btn{ padding:3px 8px; font-size:12px; }
.pms-cond{ font-size:11.5px; font-weight:600; padding:2px 8px; border-radius:999px; background:var(--od-ground); color:var(--od-ink-soft); white-space:nowrap; }
.pms-cond-good{ background:var(--od-accent-tint); color:var(--od-accent-dark); }
.pms-cond-fair{ background:var(--od-warn-tint); color:var(--od-warn-dark); }
.pms-cond-bad{ background:var(--od-danger-tint); color:var(--od-danger); }
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
.pms-section{ font-size:11.5px; font-weight:700; letter-spacing:.08em; text-transform:uppercase; color:var(--od-muted); margin:18px 0 10px; padding-bottom:6px; border-bottom:1px solid var(--od-line-soft); }
.modal-body > .pms-section:first-of-type{ margin-top:4px; }
.pms-seg{ display:inline-flex; border:1px solid var(--od-line); border-radius:8px; overflow:hidden; }
.pms-seg input{ position:absolute; opacity:0; pointer-events:none; }
.pms-seg label{ margin:0; padding:6px 16px; font-size:13px; cursor:pointer; color:var(--od-ink-soft); background:var(--od-surface); }
.pms-seg label + input + label{ border-left:1px solid var(--od-line); }
.pms-seg input:checked + label{ background:var(--od-accent); color:#fff; }
.pms-seg input:focus-visible + label{ outline:2px solid var(--od-accent-dark); outline-offset:-2px; }
.jd-interval{ display:flex; align-items:center; gap:8px; margin-bottom:8px; max-width:440px; }
.jd-interval input{ width:110px; flex:0 0 110px; }
.jd-interval select{ flex:1 1 auto; }
.jd-checklist{ padding-left:22px; margin:0 0 8px; }
.jd-check{ margin-bottom:6px; }
.jd-check > *{ vertical-align:middle; }
.jd-check input{ display:inline-block; width:calc(100% - 46px); }

/* ---- Job status ---- */
.pms-status{ display:inline-block; padding:2px 9px; border-radius:999px; font-size:11.5px; font-weight:600; white-space:nowrap; background:var(--od-ground); color:var(--od-ink-soft); }
.pms-status-Planned{ background:var(--od-accent-tint); color:var(--od-accent-dark); }
.pms-status-DueSoon{ background:var(--od-warn-tint); color:var(--od-warn-dark); }
.pms-status-Overdue{ background:var(--od-danger-tint); color:var(--od-danger); }
.pms-status-WaitingApproval{ background:#efe9fb; color:#5b3fa6; }
.pms-status-Completed{ background:var(--od-accent-tint); color:var(--od-accent-dark); }
.pms-status-Cancelled{ background:var(--od-ground); color:var(--od-muted); text-decoration:line-through; }
.pms-overdue-date{ color:var(--od-danger); font-weight:700; }

/* ---- Job count / Add Job in the component tree ---- */
.pms-node-jobs{ font-size:12px; white-space:nowrap; color:var(--od-ink-soft); }
.pms-node-jobs a{ color:var(--od-accent-dark); font-weight:600; }
.pms-node-jobs .none{ color:var(--od-muted); }

/* ---- Jobs page ---- */
.pms-jobs-layout{ display:grid; grid-template-columns:300px minmax(0,1fr); gap:16px; align-items:start; }
.pms-jobs-tree{ border:1px solid var(--od-line); border-radius:var(--od-radius); background:var(--od-surface); max-height:calc(100vh - 220px); overflow:auto; position:sticky; top:12px; }
.pms-jt-all, .pms-jt-label{ display:flex; align-items:center; gap:6px; width:100%; border:0; background:none; text-align:left; padding:5px 8px; border-radius:5px; font-size:13px; color:var(--od-ink); }
.pms-jt-all{ font-weight:700; padding:9px 12px; border-bottom:1px solid var(--od-line-soft); border-radius:0; }
.pms-jt-label:hover, .pms-jt-all:hover, .pms-jt-label:focus, .pms-jt-all:focus{ background:var(--od-accent-tint); outline:none; }
.pms-jt-label.is-selected, .pms-jt-all.is-selected{ background:var(--od-accent); color:#fff; }
.pms-jt-label.is-selected .pms-jt-count, .pms-jt-all.is-selected .pms-jt-count{ color:#fff; background:rgba(255,255,255,.2); }
.pms-jt-name{ flex:1 1 auto; min-width:0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.pms-jt-count{ font-size:11px; font-weight:600; font-variant-numeric:tabular-nums; color:var(--od-accent-dark); background:var(--od-accent-tint); border-radius:999px; padding:0 7px; }
.pms-jt-count.zero{ visibility:hidden; }
{{-- Rescheduled / due soon / overdue - always shown (not tied to the active
	 filter pill), rolled up through the tree and into the location header. --}}
.pms-jt-badges{ display:flex; align-items:center; gap:4px; flex-shrink:0; }
.pms-jt-badge{
	font-size:10.5px; font-weight:700; font-variant-numeric:tabular-nums; border-radius:999px;
	padding:1px 6px; line-height:1.5;
}
.pms-jt-badge.zero{ display:none; }
.pms-jt-badge-resched{ background:var(--od-line-soft); color:var(--od-ink-soft); }
.pms-jt-badge-duesoon{ background:var(--od-warn-tint); color:var(--od-warn-dark); }
.pms-jt-badge-overdue{ background:var(--od-danger-tint); color:var(--od-danger); }
.pms-jt-label.is-selected .pms-jt-badge-resched{ background:rgba(255,255,255,.25); color:#fff; }
.pms-jt-label.is-selected .pms-jt-badge-duesoon{ background:rgba(255,255,255,.3); color:#fff; }
.pms-jt-label.is-selected .pms-jt-badge-overdue{ background:rgba(255,255,255,.35); color:#fff; }
.pms-jt-location{ margin-top:2px; }
.pms-jt-loc-head{
	display:flex; align-items:center; gap:6px; width:100%; border:0; background:none; text-align:left;
	padding:9px 12px 4px; margin:0; cursor:pointer; border-radius:5px;
}
.pms-jt-loc-head:hover, .pms-jt-loc-head:focus{ background:var(--od-accent-tint); outline:none; }
.pms-jt-loc-name{ flex:1 1 auto; min-width:0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; font-size:11.5px; font-weight:700; letter-spacing:.07em; text-transform:uppercase; color:var(--od-accent-dark); }
.pms-jt-loc-caret{ font-size:9px; color:var(--od-accent-dark); transition:transform .15s; flex-shrink:0; }
.pms-jt-location.collapsed .pms-jt-loc-caret{ transform:rotate(-90deg); }
.pms-jt-location.collapsed > ul{ display:none; }
.pms-jt, .pms-jt ul{ list-style:none; margin:0; padding:0 6px; }
.pms-jt ul{ padding-left:14px; }
.pms-jt-row{ display:flex; align-items:center; }
.pms-jt-caret{ width:18px; flex:0 0 18px; border:0; background:none; padding:0; color:var(--od-muted); font-size:10px; }
.pms-jt-caret.leaf{ visibility:hidden; }
.pms-jt li.collapsed > ul{ display:none; }
.pms-jt li.collapsed > .pms-jt-row .pms-jt-caret i{ transform:rotate(-90deg); }
.pms-filters{ display:flex; flex-wrap:wrap; gap:6px; }
.pms-filter{ border:1px solid var(--od-line); background:var(--od-surface); border-radius:999px; padding:5px 12px; font-size:12.5px; color:var(--od-ink-soft); display:inline-flex; gap:6px; align-items:center; }
.pms-filter b{ font-variant-numeric:tabular-nums; }
.pms-filter.active{ background:var(--od-accent); border-color:var(--od-accent); color:#fff; }
.pms-filter[data-filter="overdue"]:not(.active) b{ color:var(--od-danger); }
.pms-filter[data-filter="duesoon"]:not(.active) b{ color:var(--od-warn-dark); }
#pms-jobs-table tbody tr{ cursor:pointer; }
#pms-jobs-table td{ vertical-align:middle; }
.pms-job-sub{ display:block; font-size:11.5px; color:var(--od-muted); }

/* ---- Job dialog ---- */
.pms-jd-dl{ display:grid; grid-template-columns:repeat(3, minmax(0,1fr)); gap:10px 18px; margin:0 0 14px; }
.pms-jd-dl dt{ font-size:11px; font-weight:600; letter-spacing:.05em; text-transform:uppercase; color:var(--od-muted); margin:0; }
.pms-jd-dl dd{ margin:2px 0 0; font-size:13.5px; color:var(--od-ink); font-variant-numeric:tabular-nums; overflow-wrap:anywhere; }
.pms-jd-panel{ border:1px solid var(--od-line); border-radius:var(--od-radius); padding:14px 16px; margin-top:12px; background:var(--od-ground); }
.pms-jd-checks li{ margin-bottom:4px; }
.pms-history-item{ border-left:3px solid var(--od-line); padding:6px 0 6px 12px; margin-bottom:10px; font-size:13px; }
.pms-history-item.Completed{ border-left-color:var(--od-accent-dark); }
.pms-history-item.WaitingApproval{ border-left-color:#5b3fa6; }
@media (max-width:991px){
	.pms-jobs-layout{ grid-template-columns:1fr; }
	.pms-jobs-tree{ position:static; max-height:280px; }
	.pms-jd-dl{ grid-template-columns:repeat(2, minmax(0,1fr)); }
}
/* ---- Required parts ---- */
.pp-search-wrap{ position:relative; }
.pp-results{ position:absolute; z-index:5; left:0; right:0; top:68px; max-height:260px; overflow:auto; background:var(--od-surface); border:1px solid var(--od-line); border-radius:6px; box-shadow:0 8px 24px rgba(0,0,0,.12); }
.pp-result{ display:block; width:100%; text-align:left; border:0; background:none; padding:7px 12px; font-size:13px; border-bottom:1px solid var(--od-line-soft); }
.pp-result:hover, .pp-result:focus{ background:var(--od-accent-tint); outline:none; }
.pp-none{ padding:8px 12px; font-size:13px; }
.pp-table td, .pp-table th{ vertical-align:middle; font-size:13px; }
.pp-sub{ display:block; font-size:11.5px; color:var(--od-muted); }
.pp-req{ white-space:nowrap; }
.pp-used-row{ display:flex; align-items:center; gap:8px; margin-bottom:6px; }
.pp-used-row .pp-used-name{ flex:1 1 auto; min-width:0; font-size:13px; }
.pp-used-row input{ width:90px; flex:0 0 90px; }
.pp-group{ border:1px solid var(--od-line); border-radius:var(--od-radius); margin-bottom:14px; overflow:hidden; background:var(--od-surface); }
.pp-group-head{ display:flex; flex-wrap:wrap; align-items:center; gap:10px; padding:10px 14px; background:var(--od-ground); border-bottom:1px solid var(--od-line); }
.pp-group-head h3{ margin:0; font-size:14px; font-weight:700; }
/* ---- Component import ---- */
.pms-import-grid{ display:grid; grid-template-columns:minmax(0,1fr) minmax(0,1.2fr); gap:24px; align-items:start; }
.pms-import-form{ border:1px solid var(--od-line); border-radius:var(--od-radius); padding:18px; background:var(--od-ground); }
.pms-import-help ul{ padding-left:18px; font-size:13px; color:var(--od-ink-soft); }
.pms-import-help li{ margin-bottom:6px; }
.pms-import-counts{ display:flex; flex-wrap:wrap; gap:12px; margin:0 0 16px; }
.pms-import-count{ flex:1 1 160px; border:1px solid var(--od-line); border-radius:var(--od-radius); padding:12px 16px; background:var(--od-surface); }
.pms-import-count b{ display:block; font-size:26px; font-variant-numeric:tabular-nums; line-height:1.1; }
.pms-import-count span{ font-size:13px; color:var(--od-ink-soft); }
.pms-import-count small{ display:block; font-size:12px; color:var(--od-muted); }
.pms-import-count.create b{ color:var(--od-accent-dark); }
.pms-import-count.error b{ color:var(--od-danger); }
@media (max-width:991px){ .pms-import-grid{ grid-template-columns:1fr; } }
/* ---- Reports ---- */
.pms-report-tabs{ display:flex; flex-wrap:wrap; gap:6px; margin:0 0 16px; }
.pms-report-filters{ display:flex; flex-wrap:wrap; align-items:flex-end; gap:10px 14px; padding:14px 16px; margin-bottom:16px; border:1px solid var(--od-line); border-radius:var(--od-radius); background:var(--od-ground); }
.pms-report-filters .form-group{ margin:0; display:flex; flex-direction:column; gap:4px; }
.pms-report-filters label{ margin:0; font-size:11.5px; font-weight:600; letter-spacing:.04em; text-transform:uppercase; color:var(--od-muted); }
.pms-report-filters .form-control{ min-width:150px; height:36px; }
.pms-report-filters .pms-check{ flex-direction:row; align-items:center; gap:6px; height:36px; }
.pms-report-filters .pms-check label{ text-transform:none; letter-spacing:0; font-size:13.5px; color:var(--od-ink-soft); font-weight:500; }
.pms-report-filters .actions{ display:flex; gap:8px; margin-left:auto; }
.pms-report-table th{ white-space:nowrap; font-size:12.5px; vertical-align:bottom; }
.pms-report-table td{ font-size:13px; vertical-align:middle; }
.pms-report-table th a{ color:inherit; text-decoration:none; display:inline-flex; align-items:center; gap:5px; }
.pms-report-table th a:hover{ color:var(--od-accent-dark); }
.pms-report-table th .fa-sort{ color:var(--od-line); }
.pms-report-table tfoot td{ font-weight:700; background:var(--od-ground); border-top:2px solid var(--od-line); }
.pms-report-table a.pms-num-link{ font-weight:600; color:var(--od-accent-dark); }
.pms-report-table .zero{ color:var(--od-muted); }
.pms-band{ display:inline-block; min-width:56px; text-align:center; padding:2px 8px; border-radius:999px; font-weight:700; font-size:12px; font-variant-numeric:tabular-nums; }
.pms-band-good{ background:var(--od-accent-tint); color:var(--od-accent-dark); }
.pms-band-warn{ background:var(--od-warn-tint); color:var(--od-warn-dark); }
.pms-band-bad{ background:var(--od-danger-tint); color:var(--od-danger); }
.pms-report-summary{ display:flex; flex-wrap:wrap; gap:12px; margin-bottom:16px; }
.pms-report-stat{ border:1px solid var(--od-line); border-radius:var(--od-radius); padding:10px 14px; background:var(--od-surface); min-width:120px; }
.pms-report-stat b{ display:block; font-size:22px; line-height:1.15; font-variant-numeric:tabular-nums; }
.pms-report-stat span{ font-size:12px; color:var(--od-muted); }
.pms-report-stat.danger b{ color:var(--od-danger); }
.pms-report-ranks{ display:flex; flex-wrap:wrap; gap:6px; align-items:center; font-size:12.5px; }
.pms-report-ranks .chip{ border:1px solid var(--od-line); border-radius:999px; padding:2px 9px; background:var(--od-surface); font-variant-numeric:tabular-nums; }
.pms-report-footer{ display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px; margin-top:12px; font-size:13px; color:var(--od-ink-soft); }
.pms-report-footer .pagination{ margin:0; }

/* ---- PMS Dashboard ---- */
.pms-dash-head{ display:flex; flex-wrap:wrap; align-items:flex-end; justify-content:space-between; gap:12px; margin-bottom:20px; }
.pms-dash-title{ margin:0; font-size:26px; font-weight:700; color:var(--od-ink); }
.pms-dash-sub{ margin:4px 0 0; font-size:14px; color:var(--od-muted); }
.pms-dash-tiles{ display:grid; grid-template-columns:repeat(auto-fit, minmax(200px, 1fr)); gap:16px; margin-bottom:20px; }
.pms-dash-tile{
	display:flex; flex-direction:column; gap:6px; padding:18px 20px; background:var(--od-surface);
	border:1px solid var(--od-line); border-radius:var(--od-radius); text-decoration:none;
}
a.pms-dash-tile, a.pms-dash-tile:hover{ color:var(--od-ink); }
.pms-dash-tile:hover{ border-color:var(--od-accent); text-decoration:none; }
.pms-dash-tile-label{ font-size:13px; color:var(--od-muted); font-weight:500; }
.pms-dash-tile-value{ font-size:32px; font-weight:700; line-height:1.1; color:var(--od-ink); }
.pms-dash-tile-value.pms-dash-tile-danger{ color:var(--od-danger); }
.pms-dash-tile-value.pms-dash-tile-warn{ color:var(--od-warn-dark); }
.pms-dash-tile-value.pms-dash-tile-accent{ color:var(--od-accent-dark); }
.pms-dash-tile-sub{ font-size:12px; color:var(--od-muted); }
.pms-dash-row{ display:flex; flex-wrap:wrap; gap:20px; align-items:flex-start; margin-bottom:20px; }
.pms-dash-card{ min-width:0; }
.pms-dash-card-main{ flex:999 1 640px; }
.pms-dash-card-side{ flex:1 1 320px; }
.pms-dash-table{ width:100%; margin:0; font-size:14px; }
.pms-dash-table thead th{ color:var(--od-muted); font-size:12px; text-transform:uppercase; letter-spacing:.04em; font-weight:600; border-top:0; padding:10px 20px; }
.pms-dash-table thead th:first-child, .pms-dash-table tbody td:first-child{ padding-left:20px; }
.pms-dash-table thead th:last-child, .pms-dash-table tbody td:last-child{ padding-right:20px; }
.pms-dash-table tbody td{ padding:11px 12px; vertical-align:middle; border-top:1px solid var(--od-line-soft); }
.pms-dash-total td{ border-top:2px solid var(--od-line); font-weight:700; }
.pms-dash-bar{ display:flex; align-items:center; gap:10px; }
.pms-dash-bar-track{ flex:1; height:8px; background:var(--od-line-soft); border-radius:4px; overflow:hidden; }
.pms-dash-bar-fill{ height:8px; border-radius:4px; }
.pms-dash-attention{ list-style:none; margin:0; padding:8px 0; }
.pms-dash-attention a{ display:flex; align-items:center; gap:12px; padding:12px 20px; text-decoration:none; color:var(--od-ink); }
.pms-dash-attention a:hover{ background:var(--od-ground); text-decoration:none; color:var(--od-ink); }
.pms-dash-pill{
	min-width:52px; height:32px; padding:0 8px; box-sizing:border-box; border-radius:16px;
	font-weight:700; font-size:14px; display:flex; align-items:center; justify-content:center; flex-shrink:0;
}
.pms-dash-pill-danger{ background:var(--od-danger-tint); color:var(--od-danger); }
.pms-dash-pill-warn{ background:var(--od-warn-tint); color:var(--od-warn-dark); }
.pms-dash-pill-neutral{ background:var(--od-line-soft); color:var(--od-ink-soft); }
.pms-dash-attn-text{ display:flex; flex-direction:column; gap:2px; min-width:0; }
.pms-dash-attn-text strong{ font-size:14px; font-weight:600; }
.pms-dash-attn-text span{ font-size:12px; color:var(--od-muted); }
.pms-dash-conditions{ display:flex; flex-direction:column; gap:14px; }
.pms-dash-cond-row{ display:flex; flex-direction:column; gap:6px; }
.pms-dash-cond-label{ display:flex; justify-content:space-between; font-size:14px; }

/* ---- Dashboard charts - plain HTML/CSS bars, no chart library ---- */
.pms-chart-readout{ font-size:13px; font-weight:600; color:var(--od-ink-soft); min-height:18px; margin-bottom:10px; }
.pms-chart-plot{ position:relative; height:160px; margin-left:34px; }
.pms-chart-grid{ position:absolute; inset:0; }
.pms-chart-gridline{ position:absolute; left:0; right:0; border-top:1px solid var(--od-line-soft); }
.pms-chart-gridline span{
	position:absolute; left:-34px; top:-7px; width:28px; text-align:right;
	font-size:10px; color:var(--od-muted); font-variant-numeric:tabular-nums;
}
.pms-chart-bars{ position:absolute; inset:0; display:flex; align-items:flex-end; gap:4px; }
.pms-chart-bar{
	flex:1; min-width:0; min-height:2px; background:var(--od-accent); border:0;
	border-radius:3px 3px 0 0; cursor:pointer; padding:0;
}
.pms-chart-bar:hover, .pms-chart-bar:focus-visible{ background:var(--od-accent-dark); outline:2px solid var(--od-accent-dark); outline-offset:1px; }
/* The current (in-progress) month/week - same colour, a subtle stripe marks it as not-yet-final. */
.pms-chart-bar.is-current{ background-image:repeating-linear-gradient(45deg, rgba(255,255,255,.35) 0 4px, transparent 4px 8px); }
.pms-chart-labels{ display:flex; gap:4px; margin:8px 0 0 34px; }
.pms-chart-label{ flex:1; min-width:0; text-align:center; font-size:10px; color:var(--od-muted); overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }

.pms-chart-hrows{ display:flex; flex-direction:column; gap:10px; }
.pms-chart-hrow{ display:flex; align-items:center; gap:10px; }
.pms-chart-hrow-label{ width:140px; flex-shrink:0; font-size:13px; font-weight:600; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.pms-chart-hbar{ flex:1; display:flex; gap:2px; height:22px; background:var(--od-line-soft); border-radius:4px; overflow:hidden; }
.pms-chart-hseg{ border:0; padding:0; cursor:pointer; height:100%; }
.pms-chart-hseg:hover, .pms-chart-hseg:focus-visible{ filter:brightness(.85); outline:2px solid var(--od-ink); outline-offset:-2px; }
.pms-chart-hrow-total{ width:30px; text-align:right; font-size:13px; font-weight:600; flex-shrink:0; font-variant-numeric:tabular-nums; }
.pms-chart-legend{ display:flex; flex-wrap:wrap; gap:14px; margin-top:14px; font-size:12px; color:var(--od-muted); }
.pms-chart-legend-swatch{ display:inline-block; width:10px; height:10px; border-radius:2px; margin-right:5px; vertical-align:middle; }

@media (max-width:767px){
	.pms-dash-tiles{ grid-template-columns:1fr 1fr; }
	.pms-chart-hrow-label{ width:84px; }
}
</style>
