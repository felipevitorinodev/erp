@props(['linhas' => []])
@if($linhas !== [])
    <table class="sign">
        <tr>
            @foreach($linhas as $linha)
                @if(! $loop->first)
                    <td class="sign-gap"></td>
                @endif
                <td><div class="sign-line">{{ $linha }}</div></td>
            @endforeach
        </tr>
    </table>
@endif
