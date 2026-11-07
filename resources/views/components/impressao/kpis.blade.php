@props(['cards' => []])
@if($cards !== [])
    <table class="kpis">
        <tr>
            @foreach($cards as $card)
                <td class="kpi">
                    <div class="kpi-label">{{ $card['label'] }}</div>
                    <div class="kpi-value num">{{ $card['valor'] }}</div>
                    @if(!empty($card['detalhe']))
                        <div class="kpi-note">{{ $card['detalhe'] }}</div>
                    @endif
                </td>
            @endforeach
        </tr>
    </table>
@endif
