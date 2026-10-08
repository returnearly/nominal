<x-mail::message>
# {{ $report->name }}

{{ $summary->periodLabel() }}

Overall uptime: {{ $summary->uptimeLabel() }}. Downtime: {{ $summary->downtimeLabel() }}. Outages: {{ $summary->outages }}.

@if ($summary->rows === [])
No monitors matched this report.
@else
<x-mail::table>
| Monitor | Uptime | Downtime | Outages |
| :------ | -----: | -------: | ------: |
@foreach ($summary->rows as $row)
| {{ str_replace('|', '/', $row->name) }} | {{ $row->uptimeLabel() }} | {{ $row->downtimeLabel() }} | {{ $row->outages }} |
@endforeach
</x-mail::table>
@endif

Uptime is the share of checks that succeeded. Downtime is that failed share of the period.

@if ($summary->note)
{{ $summary->note }}
@endif
</x-mail::message>
