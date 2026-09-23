{{-- Shared by the wizard's Part A step and the read-only card on the order
     detail page. Colours come from the theme's --srd-* tokens (defined on
     :root in css/srd-theme.css), so they follow the app's primary colour and
     resolve anywhere on the page - unlike the order detail's own --od-* tokens,
     which only exist inside .order-section. --}}
<style>
	/* The form's own title, at the top of the form - centred like the printed
	   original rather than the left-aligned labelled banner it replaced. */
	.rf-banner{
		background: var(--srd-accent-tint); border: 1px solid var(--srd-border);
		border-radius: var(--srd-radius); padding: 16px 18px; margin-bottom: 16px;
		text-align: center;
	}
	.rf-banner .rf-banner-title{
		font-weight: 700; color: var(--srd-accent-dark); font-size: 16px;
		letter-spacing: .02em; text-transform: uppercase;
	}

	.rf-meta{
		display: flex; flex-wrap: wrap; background: #fff; overflow: hidden;
		border: 1px solid var(--srd-border); border-radius: var(--srd-radius); margin-bottom: 20px;
	}
	.rf-meta > div{ flex: 1 1 180px; padding: 12px 16px; border-right: 1px solid var(--srd-border-soft); }
	.rf-meta > div:last-child{ border-right: none; }
	.rf-meta .k{ font-size: 11px; letter-spacing: .06em; text-transform: uppercase; color: var(--srd-muted); font-weight: 600; margin-bottom: 3px; }
	.rf-meta .v{ color: var(--srd-ink); font-size: 14px; }

	.rf-q{
		background: #fff; border: 1px solid var(--srd-border);
		border-radius: var(--srd-radius); padding: 16px 18px; margin-bottom: 14px;
	}
	.rf-q.has-error{ border-color: var(--srd-danger); box-shadow: 0 0 0 3px var(--srd-danger-tint); }
	.rf-q-title{ font-weight: 700; color: var(--srd-ink); margin-bottom: 10px; line-height: 1.5; }
	.rf-q-no{ color: var(--srd-accent); margin-right: 4px; }
	.rf-req{ color: var(--srd-danger); margin-left: 2px; }
	.rf-hint{ font-size: 12px; color: var(--srd-muted); font-weight: 400; margin-left: 6px; }

	.rf-opts{ display: flex; flex-wrap: wrap; gap: 8px; }
	.rf-chip{ position: relative; margin: 0; }
	.rf-chip input{ position: absolute; opacity: 0; pointer-events: none; }
	.rf-chip span{
		display: inline-block; padding: 6px 13px; border: 1px solid var(--srd-border);
		border-radius: 999px; background: #fff; font-size: 13px; cursor: pointer;
		color: var(--srd-ink-soft); user-select: none; transition: border-color .12s, background .12s;
	}
	.rf-chip:hover span{ border-color: var(--srd-accent); }
	.rf-chip input:checked + span{ background: var(--srd-accent); border-color: var(--srd-accent); color: #fff; font-weight: 600; }
	.rf-chip input:focus-visible + span{ outline: 2px solid var(--srd-accent-dark); outline-offset: 2px; }

	.rf-fields{ display: flex; flex-wrap: wrap; gap: 12px; margin-top: 12px; }
	.rf-field{ flex: 1 1 180px; }
	.rf-field label{ display: block; font-size: 12px; font-weight: 600; color: var(--srd-muted); margin-bottom: 3px; }

	.rf-followup{
		margin-top: 12px; padding-top: 12px; border-top: 1px dashed var(--srd-border);
		display: flex; flex-wrap: wrap; align-items: center; gap: 8px 14px;
	}
	.rf-followup .lbl{ font-style: italic; color: var(--srd-muted); font-size: 13px; }

	.rf-declaration{ background: var(--srd-accent-tint); border-color: var(--srd-border); }
	.rf-declaration label{ margin: 0; cursor: pointer; color: var(--srd-ink); font-weight: 600; }

	/* Read-only view */
	.rf-chip-static{
		display: inline-block; padding: 3px 11px; border-radius: 999px; font-size: 12.5px; font-weight: 600;
		background: var(--srd-accent-tint); color: var(--srd-accent-dark); border: 1px solid #c5e0e5;
	}
	.rf-none{ color: var(--srd-muted); font-size: 13px; }
	.rf-kv{ font-size: 13px; color: var(--srd-ink-soft); margin-right: 18px; }
	.rf-kv b{ color: var(--srd-muted); font-weight: 600; }
	.rf-ro .rf-q{ padding: 12px 16px; margin-bottom: 10px; }
	.rf-ro .rf-q-title{ margin-bottom: 8px; font-size: 13.5px; }
</style>
