<!doctype html>
<html><head><meta charset="utf-8"><style>
@page { margin: 34px 42px; }
body { font-family: "DejaVu Sans", sans-serif; font-size: 10px; color: #17202a; }
h1 { font-size: 20px; margin: 0 0 4px; color: #315c2b; }
h2 { font-size: 12px; margin: 18px 0 7px; color: #315c2b; border-bottom: 1px solid #c8d5c5; padding-bottom: 4px; }
.meta, .items { width: 100%; border-collapse: collapse; }
.meta td { padding: 4px 6px; vertical-align: top; }
.items th { background: #315c2b; color: white; padding: 7px 5px; text-align: left; }
.items td { border-bottom: 1px solid #dfe7dc; padding: 6px 5px; }
.num { text-align: right; }
.footer { position: fixed; bottom: -16px; left: 0; right: 0; font-size: 8px; color: #69757d; text-align: center; }
</style></head><body>
<h1>Measurement Certificate</h1>
<div>Generated from Kuwalee SiteFlow</div>
<h2>Measurement details</h2>
<table class="meta">
<tr><td><strong>Project</strong><br>{{ $measurement->project->project_name }}</td><td><strong>Site</strong><br>{{ $measurement->site->site_name }}</td><td><strong>Status</strong><br>{{ ucfirst($measurement->status) }}</td></tr>
<tr><td><strong>Date</strong><br>{{ $measurement->measurement_date->format('d M Y') }}</td><td><strong>Recorded by</strong><br>{{ $measurement->creator->name }}</td><td><strong>Approved by</strong><br>{{ $measurement->approver?->name ?? 'Not approved' }}</td></tr>
</table>
<h2>Measured work</h2>
<table class="items"><thead><tr><th style="width:12%">Item</th><th>Description</th><th style="width:8%">Unit</th><th class="num" style="width:14%">Previous</th><th class="num" style="width:14%">Current</th><th class="num" style="width:14%">Cumulative</th></tr></thead><tbody>
@foreach($measurement->items as $item)
<tr><td>{{ $item->boqItem->item_number }}</td><td>{{ $item->boqItem->description }}</td><td>{{ $item->unit }}</td><td class="num">{{ $item->previous_quantity }}</td><td class="num">{{ $item->current_quantity }}</td><td class="num">{{ $item->cumulative_quantity }}</td></tr>
@endforeach
</tbody></table>
@if($measurement->remarks)<h2>Remarks</h2><div>{{ $measurement->remarks }}</div>@endif
<div class="footer">Measurement export is policy gated and audit logged.</div>
</body></html>
