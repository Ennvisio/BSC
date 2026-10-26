{{-- The PMS area's own chrome: header, tab bar, content wrapper. Scoped to
	 .pms-shell so it never leaks into the rest of the app, which keeps its
	 own --srd-* sidebar theme untouched. Palette and spacing match
	 docs/pms-design/ (Main.dc.html / Jobs.dc.html / Header.dc.html). --}}
<style>
.pms-shell{
	--od-ink:        #15211F;
	--od-ink-soft:   #24312F;
	--od-muted:      #6B7877;
	--od-faint:      #93A0A0;
	--od-line:       #DFE5E4;
	--od-line-soft:  #ECF0EF;
	--od-surface:    #FFFFFF;
	--od-ground:     #F7F9F9;
	--od-accent:     #0E7C74;
	--od-accent-dark:#0A5D57;
	--od-accent-tint:#EAF6F4;
	--od-warn:       #C2700C;
	--od-warn-dark:  #8A4B08;
	--od-warn-tint:  #FFF4E5;
	--od-danger:     #B3261E;
	--od-danger-tint:#FDECEA;
	--od-radius:     12px;
	--od-radius-sm:  8px;
	font-family:'Public Sans',-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Arial,sans-serif;
	color:var(--od-ink);
	background:var(--od-ground);
	display:block;
}
.pms-shell .srd-content{ padding:0; max-width:none; }

/* ---- header: brand + module switcher + user menu ---- */
.pms-header{ background:var(--od-surface); border-bottom:1px solid var(--od-line); }
.pms-header-row{
	max-width:1440px; margin:0 auto; padding:12px 24px;
	display:flex; flex-wrap:wrap; align-items:center; gap:16px;
}
.pms-brand{ display:flex; align-items:center; gap:10px; text-decoration:none; flex-shrink:0; }
/* `.srd a` (specificity 0,1,1) outranks a plain single class on the same
   <a> (0,1,0) - every link-colour rule below is written as a.classname to
   match it, and wins the tie on source order (this partial loads after
   srd-theme.css - see layouts.pms). The module switcher itself (.srd-module-
   switch / .srd-module-tab) is shared with the main topbar - see css/srd-
   theme.css and partials.module-switch. */
a.pms-brand{ color:var(--od-ink); }
.pms-brand-mark{
	width:36px; height:36px; border-radius:8px; background:var(--od-accent);
	display:flex; align-items:center; justify-content:center; color:#fff; font-size:16px; flex-shrink:0;
}
.pms-brand-text{ display:flex; flex-direction:column; line-height:1.2; }
.pms-brand-text strong{ font-weight:700; font-size:15px; }
.pms-brand-text span{ font-size:12px; color:var(--od-muted); }
.pms-header-user{ margin-left:auto; display:flex; align-items:center; gap:10px; flex-shrink:0; }
.pms-avatar{
	width:36px; height:36px; border-radius:50%; background:var(--od-accent-tint); color:var(--od-accent-dark);
	font-weight:700; font-size:13px; display:flex; align-items:center; justify-content:center; flex-shrink:0;
}
.pms-header-user .dropdown-toggle::after{ display:none; }
.pms-user-meta{ display:flex; flex-direction:column; line-height:1.2; text-align:left; }
.pms-user-meta strong{ font-weight:600; font-size:14px; color:var(--od-ink); }
.pms-user-meta span{ font-size:12px; color:var(--od-muted); }

/* ---- tab bar ---- */
.pms-nav{
	max-width:1440px; margin:0 auto; padding:0 24px; display:flex; flex-wrap:nowrap;
	align-items:flex-end; gap:4px; overflow-x:auto;
}
.pms-nav-link{
	padding:12px 16px 15px; font-size:14px; text-decoration:none;
	white-space:nowrap; flex-shrink:0; border-bottom:3px solid transparent;
}
a.pms-nav-link{ color:var(--od-ink-soft); }
a.pms-nav-link:hover{ color:var(--od-accent-dark); text-decoration:none; }
a.pms-nav-link.active{ color:var(--od-accent-dark); }
.pms-nav-link.active{ padding:12px 16px; font-weight:600; border-bottom-color:var(--od-accent); }
.pms-nav-spacer{ flex:1 1 auto; min-width:12px; }
.pms-vessel-form{ display:flex; align-items:center; gap:8px; padding-bottom:8px; font-size:13px; color:var(--od-muted); flex-shrink:0; }
.pms-vessel-select{
	height:36px; border:1px solid var(--od-line); border-radius:8px; padding:0 10px;
	font:inherit; font-size:14px; color:var(--od-ink); background:var(--od-surface);
}
.pms-vessel-name{ padding-bottom:8px; font-size:14px; font-weight:600; color:var(--od-ink); flex-shrink:0; }

/* ---- content wrapper ---- */
.pms-main{ max-width:1440px; margin:0 auto; padding:24px; }
.pms-shell .card{ border:1px solid var(--od-line); border-radius:var(--od-radius); }
.pms-shell .card-header.pv-card-hader{ background:var(--od-surface); border-bottom:1px solid var(--od-line-soft); border-radius:var(--od-radius) var(--od-radius) 0 0; }

@media (max-width:767px){
	.pms-header-row{ padding:12px 16px; }
	.pms-nav{ padding:0 16px; }
	.pms-main{ padding:16px; }
}
</style>
