{{--
    A sum written the way a till prints it: one line per amount, the sign it
    enters the sum with, and the result under a rule. Nothing on the page is
    a figure without the sum that produced it.

    $caption  what the sum works out, for a screen reader
    $rows     list of:
                sign    '', '+', '−', '×', '÷', '='
                label   what the amount is
                how     where it comes from, in one line (optional)
                amount  already formatted
                key     colour of the matching slice of the bar beside it (optional)
                result  true on a line that closes a sum
                final   true on the line the whole receipt is about
--}}
@php
    $spoken = ['+' => 'plus', '−' => 'minus', '×' => 'times', '÷' => 'divided by', '=' => 'equals'];
@endphp
<table class="receipt">
    <caption class="sr-only">{{ $caption }}</caption>
    <tbody>
        @foreach ($rows as $row)
            <tr @class(['is-result' => ! empty($row['result']), 'is-final' => ! empty($row['final'])])>
                <th scope="row">
                    <span class="receipt-label">
                        @if (! empty($row['key']))
                            <span class="swatch k-{{ $row['key'] }}" aria-hidden="true"></span>
                        @endif
                        {{ $row['label'] }}
                    </span>
                    @if (! empty($row['how']))
                        <small>{{ $row['how'] }}</small>
                    @endif
                </th>
                <td>
                    @if (($row['sign'] ?? '') !== '')
                        <span class="receipt-sign" aria-hidden="true">{{ $row['sign'] }}</span>
                        <span class="sr-only">{{ $spoken[$row['sign']] ?? '' }}</span>
                    @endif
                    <span class="receipt-amount">{{ $row['amount'] }}</span>
                </td>
            </tr>
        @endforeach
    </tbody>
</table>
